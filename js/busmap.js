const selectedSeats = new Set();
const farePerSeat = 750;
const decks = {
    lower: { taken: new Set(["1B", "2C", "4A", "5D", "7B", "9C"]) },
    upper: { taken: new Set(["1A", "3D", "4B", "6C", "8A"]) }
};

function renderDeck(deckName) {
    const map = document.querySelector(`[data-deck-map="${deckName}"]`);
    const takenSeats = decks[deckName].taken;

    for (let row = 1; row <= 10; row += 1) {
        const rowLabel = document.createElement("span");
        rowLabel.className = "seat-row-number";
        rowLabel.textContent = String(row);
        map.append(rowLabel);

        ["A", "B", "aisle", "C", "D"].forEach((column) => {
            if (column === "aisle") {
                const aisle = document.createElement("span");
                aisle.className = "seat-aisle";
                aisle.setAttribute("aria-hidden", "true");
                map.append(aisle);
                return;
            }

            const seatId = `${row}${column}`;
            const seat = document.createElement("button");
            seat.type = "button";
            seat.className = "seat";
            seat.textContent = seatId;
            seat.dataset.seat = seatId;
            seat.dataset.deck = deckName;

            if (takenSeats.has(seatId)) {
                seat.classList.add("taken");
                seat.disabled = true;
                seat.setAttribute("aria-label", `Seat ${seatId}, Taken`);
            } else {
                seat.setAttribute("aria-label", `Seat ${seatId}, Available`);
                seat.addEventListener("click", () => toggleSeat(seat));
            }

            map.append(seat);
        });
    }
}

function toggleSeat(seat) {
    const seatKey = `${seat.dataset.deck}-${seat.dataset.seat}`;

    if (selectedSeats.has(seatKey)) {
        selectedSeats.delete(seatKey);
        seat.classList.remove("selected");
        seat.setAttribute("aria-label", `Seat ${seat.dataset.seat}, Available`);
    } else {
        selectedSeats.add(seatKey);
        seat.classList.add("selected");
        seat.setAttribute("aria-label", `Seat ${seat.dataset.seat}, Selected`);
    }

    updateSummary();
}

function updateSummary() {
    const count = document.querySelector("#passenger-count");
    const list = document.querySelector("#selected-seats");
    const selections = Array.from(selectedSeats)
        .map((seatKey) => {
            const [deck, seat] = seatKey.split("-");
            return `${seat} (${deck === "lower" ? "Lower" : "Upper"} Deck)`;
        })
        .sort();
    const fare = farePerSeat * selections.length;
    const formattedFare = new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP"
    }).format(fare);

    count.textContent = String(selections.length);
    list.textContent = selections.length ? selections.join(", ") : "None selected";
    document.querySelector("#base-fare").textContent = formattedFare;
    document.querySelector("#total-fare").textContent = formattedFare;
}

const discountTypes = Array.from(document.querySelectorAll('input[name="discount-type"]'));
const discountUpload = document.querySelector("#discount-ids");
const applyDiscountButton = document.querySelector("#apply-discount");
const discountMessage = document.querySelector("#discount-message");

function updateDiscountEligibility() {
    const hasDiscountType = discountTypes.some((checkbox) => checkbox.checked);
    const hasUploadedIds = discountUpload.files.length > 0;
    applyDiscountButton.disabled = !hasDiscountType || !hasUploadedIds;
}

discountTypes.forEach((checkbox) => {
    checkbox.addEventListener("change", updateDiscountEligibility);
});
discountUpload.addEventListener("change", updateDiscountEligibility);

applyDiscountButton.addEventListener("click", () => {
    const selectedTypes = discountTypes
        .filter((checkbox) => checkbox.checked)
        .map((checkbox) => checkbox.value);

    document.querySelector("#discount-summary").textContent = `${selectedTypes.join(", ")} - Pending verification`;
    discountMessage.textContent = "ID(s) submitted for verification. No discount has been applied to the total yet.";
});

function goToBookingConfirmation() {
    const bookingMessage = document.querySelector("#booking-action-message");
    if (selectedSeats.size === 0) {
        bookingMessage.textContent = "Please select at least one seat before booking.";
        return;
    }

    const confirmationUrl = new URL("confirmation.html", window.location.href);
    selectedSeats.forEach((seatKey) => confirmationUrl.searchParams.append("seat", seatKey));
    window.location.href = confirmationUrl.href;
}

document.querySelectorAll("[data-booking-action]").forEach((button) => {
    button.addEventListener("click", () => {
        if (button.dataset.bookingAction === "Book now") {
            goToBookingConfirmation();
            return;
        }
        document.querySelector("#booking-action-message").textContent =
            `${button.dataset.bookingAction} is not connected to a booking system yet.`;
    });
});

renderDeck("lower");
renderDeck("upper");
