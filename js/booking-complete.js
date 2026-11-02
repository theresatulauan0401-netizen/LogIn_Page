const ticketCard = document.querySelector("#ticket-card");
const ticketError = document.querySelector("#ticket-error");
const ticketActions = document.querySelector("#ticket-actions");
const printNote = document.querySelector("#ticket-print-note");
const demoNote = document.querySelector("#ticket-demo-note");
let booking;

try {
    booking = JSON.parse(window.sessionStorage.getItem("javaExpressBooking"));
} catch (error) {
    console.error("Could not read the booking ticket from this browser session.", error);
}

if (!booking || !Array.isArray(booking.passengers) || !Array.isArray(booking.seats)) {
    ticketError.hidden = false;
} else {
    const seatNames = booking.seats.map((seat) =>
        `${seat.seat} (${seat.deck === "lower" ? "Lower" : "Upper"} Deck)`
    );
    const paymentLabels = {
        gcash: "GCash",
        maya: "Maya",
        card: "Credit or Debit Card",
        terminal: "Pay at terminal"
    };
    const passengers = document.querySelector("#ticket-passenger-list");

    document.querySelector("#ticket-bus-type").textContent = booking.busType;
    document.querySelector("#ticket-reference").textContent = booking.reference;
    document.querySelector("#ticket-departure").textContent =
        `${booking.departureDate}, ${booking.departureTime}`;
    document.querySelector("#ticket-seats").textContent = seatNames.join(", ");
    document.querySelector("#ticket-route").textContent = booking.route;
    document.querySelector("#ticket-total").textContent = new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP"
    }).format(booking.total);
    document.querySelector("#ticket-payment").textContent =
        paymentLabels[booking.paymentMethod] || "Not specified";

    booking.passengers.forEach((passenger, index) => {
        const name = document.createElement("p");
        name.className = "ticket-passenger-name";
        name.textContent = `${index + 1}. ${passenger.name}`;
        passengers.append(name);
    });

    ticketCard.hidden = false;
    ticketActions.hidden = false;
    printNote.hidden = false;
    demoNote.hidden = false;

    document.querySelector("#download-ticket").addEventListener("click", () => {
        window.print();
    });
}
