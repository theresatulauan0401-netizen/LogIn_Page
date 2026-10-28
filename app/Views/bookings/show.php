<?php
/** @var array $booking @var array $payments @var \App\Models\Ticket|null $ticket @var array $history @var string[] $allowed @var array|null $successfulPayment */
$id = (int) $booking['id'];
$status = (string) $booking['status'];
$canRefund = can('payments.refund') && $successfulPayment !== null;
?>
<div class="row g-3 mb-3">
    <div class="col-lg-7"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h2 class="h5 mb-0"><?= e($booking['booking_reference']) ?></h2><?= status_badge($status) ?>
        </div>
        <dl class="detail-list row mb-0">
            <dt class="col-4">Passenger</dt><dd class="col-8"><a href="<?= e(url('/passengers/' . (int) $booking['passenger_id'])) ?>"><?= e($booking['first_name'] . ' ' . $booking['last_name']) ?></a> (<?= e($booking['passenger_code']) ?>) &middot; <?= e($booking['phone']) ?></dd>
            <dt class="col-4">Trip</dt><dd class="col-8"><a href="<?= e(url('/schedules/' . (int) $booking['schedule_id'])) ?>"><?= e($booking['schedule_code']) ?></a> &middot; <?= e($booking['origin']) ?> to <?= e($booking['destination']) ?> <?= status_badge((string) $booking['schedule_status']) ?></dd>
            <dt class="col-4">Departure</dt><dd class="col-8"><?= e(format_datetime($booking['departure_datetime'])) ?> &rarr; <?= e(format_datetime($booking['arrival_datetime'])) ?></dd>
            <dt class="col-4">Bus / seat</dt><dd class="col-8"><?= e($booking['bus_number']) ?> (<?= e($booking['bus_type']) ?>) &middot; Seat <strong><?= e($booking['seat_number']) ?></strong></dd>
            <dt class="col-4">Total</dt><dd class="col-8"><?= e(format_money($booking['total_amount'])) ?></dd>
            <dt class="col-4">Booked</dt><dd class="col-8"><?= e(format_datetime($booking['booking_date'])) ?> by <?= e($booking['created_by_name'] ?: 'system / demo') ?></dd>
            <?php if ($booking['notes']): ?><dt class="col-4">Notes</dt><dd class="col-8"><?= nl2br(e($booking['notes'])) ?></dd><?php endif; ?>
        </dl>
    </div></div></div>
    <div class="col-lg-5"><div class="card h-100"><div class="card-body d-flex flex-column gap-3">
        <div>
            <h3 class="h6">Ticket</h3>
            <?php if ($ticket): ?>
                <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/tickets/' . $ticket->id())) ?>"><i class="bi bi-ticket-perforated me-1"></i><?= e($ticket->number()) ?></a> <?= status_badge($ticket->status()) ?>
            <?php elseif ($status === 'confirmed' && can('tickets.issue')): ?>
                <?= post_button('/bookings/' . $id . '/ticket', 'Generate ticket', 'btn-primary') ?>
            <?php else: ?><span class="text-muted small">Issued automatically when payment confirms the booking.</span><?php endif; ?>
        </div>
        <?php if ($status === 'pending' && can('payments.record')): ?>
            <div><a class="btn btn-primary" href="<?= e(url('/payments/create?booking_id=' . $id)) ?>"><i class="bi bi-cash-coin me-1"></i>Record payment</a></div>
        <?php endif; ?>
        <?php if (in_array('cancelled', $allowed, true) && can('bookings.cancel')): ?>
            <form method="post" action="<?= e(url('/bookings/' . $id . '/cancel')) ?>" data-confirm="Cancel this booking? The seat will be released.">
                <?= csrf_field() ?>
                <h3 class="h6">Cancel booking</h3>
                <input type="text" class="form-control form-control-sm mb-2" name="reason" maxlength="500" placeholder="Reason (required)" required>
                <?php if ($canRefund): ?>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="refund" value="1" id="refund"><label class="form-check-label small" for="refund">Also refund the <?= e(format_money($successfulPayment['amount'])) ?> payment</label></div>
                <?php elseif ($successfulPayment): ?>
                    <p class="small text-muted mb-2">This booking is paid. An administrator must record any refund.</p>
                <?php endif; ?>
                <button class="btn btn-outline-danger btn-sm">Cancel booking</button>
            </form>
        <?php endif; ?>
        <?php if (in_array('completed', $allowed, true) && can('bookings.complete')): ?>
            <div class="d-flex gap-2">
                <?= post_button('/bookings/' . $id . '/finish', 'Mark completed', 'btn-outline-success', 'Mark this booking completed?', ['outcome' => 'completed']) ?>
                <?= post_button('/bookings/' . $id . '/finish', 'Mark no-show', 'btn-outline-warning', 'Mark this passenger as a no-show?', ['outcome' => 'no_show']) ?>
            </div>
        <?php endif; ?>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card h-100">
        <div class="card-header fw-semibold">Payments</div>
        <?php if (!$payments): ?><div class="empty-state"><i class="bi bi-cash-coin"></i><p>No payments recorded.</p></div>
        <?php else: ?>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Reference</th><th>Method</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($payments as $p): ?>
                <tr><td><a href="<?= e(url('/payments/' . (int) $p['id'])) ?>"><?= e($p['payment_reference']) ?></a></td>
                    <td><?= e(ucwords(str_replace('_', ' ', $p['payment_method']))) ?></td>
                    <td class="text-end"><?= e(format_money($p['amount'])) ?></td><td><?= status_badge((string) $p['payment_status']) ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div></div>
    <div class="col-lg-5"><div class="card h-100">
        <div class="card-header fw-semibold">Status history</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($history as $h): ?>
                <li class="list-group-item">
                    <div><?= $h['old_status'] ? status_badge((string) $h['old_status']) . ' &rarr; ' : '' ?><?= status_badge((string) $h['new_status']) ?></div>
                    <div class="small text-muted"><?= e(format_datetime($h['created_at'])) ?> &middot; <?= e($h['changed_by_name'] ?: 'system / demo') ?><?= $h['remarks'] ? ' &middot; ' . e($h['remarks']) : '' ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div></div>
</div>
