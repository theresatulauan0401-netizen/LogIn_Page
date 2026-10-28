<?php /** @var string $number @var array|null $result */ ?>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/tickets/validate')) ?>" class="row g-2">
        <div class="col-md-8"><label class="form-label" for="ticket_number">Ticket number</label>
            <input class="form-control" id="ticket_number" name="ticket_number" value="<?= e($number) ?>" placeholder="TKT-20261027-ABC123" maxlength="50" autofocus></div>
        <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary">Check ticket</button></div>
    </form>
</div></div>
<?php if ($number !== '' && $result === null): ?>
    <div class="alert alert-danger"><i class="bi bi-x-octagon me-1"></i>No ticket found with that number.</div>
<?php elseif ($result): $t = $result['ticket']; ?>
    <div class="card"><div class="card-body">
        <div class="alert alert-<?= $result['usable'] ? 'success' : 'danger' ?>"><i class="bi bi-<?= $result['usable'] ? 'check-circle' : 'x-octagon' ?> me-1"></i><?= e($result['reason']) ?></div>
        <dl class="detail-list row mb-3">
            <dt class="col-4">Ticket</dt><dd class="col-8"><?= e($t['ticket_number']) ?> <?= status_badge((string) $t['ticket_status']) ?></dd>
            <dt class="col-4">Passenger</dt><dd class="col-8"><?= e($t['first_name'] . ' ' . $t['last_name']) ?> &middot; seat <?= e($t['seat_number']) ?></dd>
            <dt class="col-4">Trip</dt><dd class="col-8"><?= e($t['origin']) ?> to <?= e($t['destination']) ?> &middot; <?= e(format_datetime($t['departure_datetime'])) ?> <?= status_badge((string) $t['schedule_status']) ?></dd>
            <dt class="col-4">Booking</dt><dd class="col-8"><?= e($t['booking_reference']) ?> <?= status_badge((string) $t['booking_status']) ?></dd>
        </dl>
        <div class="d-flex gap-2">
            <?php if ($result['usable']): ?><?= post_button('/tickets/' . (int) $t['id'] . '/use', 'Mark as used', 'btn-success', 'Mark this ticket as used (boarded)?') ?><?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/tickets/' . (int) $t['id'])) ?>">Open ticket</a>
        </div>
    </div></div>
<?php endif; ?>
