const farePerSeat = 750;
const unavailableSeats = {
    lower: new Set(["1B", "2C", "4A", "5D", "7B", "9C"]),
    upper: new Set(["1A", "3D", "4B", "6C", "8A"])
};
const confirmationForm = document.querySelector("#confirmation-form");
const bookingError = document.querySelector("#booking-error");
const passengerList = document.querySelector("#passenger-list");
const confirmationMessage = document.querySelector("#confirmation-message");
const selectedSeatKeys = new URLSearchParams(window.location.search).getAll("seat");
const validSeatPattern = /^(lower|upper)-(10|[1-9])([A-D])$/;
const selectedSeats = selectedSeatKeys
    .map((key) => {
        const match = key.match(validSeatPattern);
        return match ? { deck: match[1], seat: `${match[2]}${match[3]}` } : null;
    })
    .filter((seat) => seat !== null);
const hasInvalidSeats = selectedSeats.length !== selectedSeatKeys.length;
const hasDuplicateSeats = new Set(selectedSeatKeys).size !== selectedSeatKeys.length;
const hasUnavailableSeats = selectedSeats.some((seat) => unavailableSeats[seat.deck].has(seat.seat));

function formatFare(amount) {
    return new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP"
    }).format(amount);
}

if (selectedSeats.length === 0 || hasInvalidSeats || hasDuplicateSeats || hasUnavailableSeats) {
    confirmationForm.hidden = true;
    bookingError.hidden = false;
    bookingError.textContent = "Walang valid na napiling upuan. Bumalik sa seat selection at pumili ng available na seat.";
} else {
    const fare = farePerSeat * selectedSeats.length;
    const seatSummary = selectedSeats
        .map((seat) => `${seat.seat} (${seat.deck === "lower" ? "Lower" : "Upper"} Deck)`)
        .join(", ");

    document.querySelector("#confirmation-summary").textContent =
        `${selectedSeats.length} seat${selectedSeats.length === 1 ? "" : "s"} selected: ${seatSummary}. Total: ${formatFare(fare)}`;
    document.querySelector("#confirmation-amount").textContent = formatFare(fare);
}

let passengerEntryCount = 1;

document.querySelector("#add-passenger").addEventListener("click", () => {
    passengerEntryCount += 1;
    const entry = document.createElement("fieldset");
    entry.className = "passenger-entry";
    entry.innerHTML = `
        <legend>Passenger ${passengerEntryCount}</legend>
        <div class="passenger-fields">
            <div class="confirmation-field full-name-field">
                <label for="passenger-name-${passengerEntryCount}">Full name</label>
                <input id="passenger-name-${passengerEntryCount}" name="passengerName[]" type="text"
                    autocomplete="name" placeholder="Enter full name" required>
            </div>
            <div class="confirmation-field">
                <label for="passenger-email-${passengerEntryCount}">Email</label>
                <input id="passenger-email-${passengerEntryCount}" name="passengerEmail[]" type="email"
                    autocomplete="email" placeholder="you@example.com" required>
            </div>
            <div class="confirmation-field">
                <label for="passenger-mobile-${passengerEntryCount}">Mobile number</label>
                <input id="passenger-mobile-${passengerEntryCount}" name="passengerMobile[]" type="tel"
                    autocomplete="tel" placeholder="09XX XXX XXXX" required>
            </div>
        </div>
        <button class="remove-passenger-button" type="button" aria-label="Remove passenger ${passengerEntryCount}">Remove</button>
    `;
    passengerList.append(entry);
    entry.querySelector(".remove-passenger-button").addEventListener("click", () => entry.remove());
    entry.querySelector("input").focus();
});

confirmationForm.addEventListener("submit", (event) => {
    event.preventDefault();
    if (passengerList.querySelectorAll(".passenger-entry").length !== selectedSeats.length) {
        confirmationMessage.textContent =
            "The number of passenger details must match the number of selected seats.";
        return;
    }

    const passengers = Array.from(passengerList.querySelectorAll(".passenger-entry"), (entry) => ({
        name: entry.querySelector('[name="passengerName[]"]').value.trim(),
        email: entry.querySelector('[name="passengerEmail[]"]').value.trim(),
        mobile: entry.querySelector('[name="passengerMobile[]"]').value.trim()
    }));
    const booking = {
        reference: `JX-${Date.now().toString(36).toUpperCase()}-${Math.random().toString(36).slice(2, 7).toUpperCase()}`,
        passengers,
        seats: selectedSeats,
        paymentMethod: confirmationForm.querySelector('[name="paymentMethod"]:checked').value,
        route: "Metro Manila - Baguio City",
        departureDate: "December 30",
        departureTime: "8:00 AM",
        busType: "Luxury",
        total: farePerSeat * selectedSeats.length
    };

    try {
        window.sessionStorage.setItem("javaExpressBooking", JSON.stringify(booking));
    } catch (error) {
        confirmationMessage.textContent =
            "Hindi maihanda ang ticket sa browser na ito. Pakisubukang muli o gumamit ng ibang browser.";
        console.error("Could not save booking ticket for this browser session.", error);
        return;
    }

    window.location.href = "booking-complete.html";
});
