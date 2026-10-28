<?php /** @var array $ticket */ $st = (string) $ticket['ticket_status']; ?>
<div class="mb-3 no-print d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-primary" data-print><i class="bi bi-printer me-1"></i>Print ticket</button>
    <a class="btn btn-light" href="<?= e(url('/bookings/' . (int) $ticket['booking_id'])) ?>">View booking</a>
    <?php if ($st === 'valid' && can('tickets.validate')): ?>
        <?= post_button('/tickets/' . (int) $ticket['id'] . '/use', 'Mark as used', 'btn-outline-success', 'Mark this ticket as used (boarded)?') ?>
    <?php endif; ?>
</div>
<div class="card ticket-card mx-auto"><div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div><div class="h5 mb-0"><?= e(setting('company_name', config('app.name'))) ?></div><div class="small text-muted">Boarding ticket</div></div>
        <div><?= status_badge($st) ?></div>
    </div>
    <div class="ticket-route"><span><?= e($ticket['origin']) ?></span><i class="bi bi-arrow-right"></i><span><?= e($ticket['destination']) ?></span></div>
    <div class="row g-3 mt-1">
        <div class="col-6"><div class="small text-muted">Passenger</div><div class="fw-semibold"><?= e($ticket['first_name'] . ' ' . $ticket['last_name']) ?></div></div>
        <div class="col-3"><div class="small text-muted">Seat</div><div class="fw-bold fs-4"><?= e($ticket['seat_number']) ?></div></div>
        <div class="col-3"><div class="small text-muted">Fare</div><div class="fw-semibold"><?= e(format_money($ticket['total_amount'])) ?></div></div>
        <div class="col-6"><div class="small text-muted">Departure</div><div><?= e(format_datetime($ticket['departure_datetime'])) ?></div></div>
        <div class="col-6"><div class="small text-muted">Arrival</div><div><?= e(format_datetime($ticket['arrival_datetime'])) ?></div></div>
        <div class="col-6"><div class="small text-muted">Bus</div><div><?= e($ticket['bus_number']) ?> &middot; <?= e($ticket['plate_number']) ?></div></div>
        <div class="col-6"><div class="small text-muted">Trip</div><div><?= e($ticket['schedule_code']) ?></div></div>
    </div>
    <hr>
    <div class="d-flex justify-content-between flex-wrap gap-2">
        <div><div class="small text-muted">Booking reference</div><div class="fw-semibold"><?= e($ticket['booking_reference']) ?></div></div>
        <div class="text-end"><div class="small text-muted">Ticket number</div><div class="ticket-number"><?= e($ticket['ticket_number']) ?></div></div>
    </div>
    <?php if ($st === 'used'): ?><p class="small text-muted mt-3 mb-0">Used <?= e(format_datetime($ticket['used_at'])) ?> (validated by <?= e($ticket['used_by_name'] ?: 'staff') ?>).</p><?php endif; ?>
    <?php if ($st !== 'valid'): ?><p class="small text-danger mt-3 mb-0">This ticket is not valid for travel.</p><?php endif; ?>
</div></div>
