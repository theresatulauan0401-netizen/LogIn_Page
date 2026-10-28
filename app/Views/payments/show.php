<?php /** @var array $payment */
$id = (int) $payment['id']; $status = (string) $payment['payment_status']; ?>
<div class="row g-3">
    <div class="col-lg-7"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3"><h2 class="h5 mb-0"><?= e($payment['payment_reference']) ?></h2><?= status_badge($status) ?></div>
        <dl class="detail-list row mb-0">
            <dt class="col-4">Booking</dt><dd class="col-8"><a href="<?= e(url('/bookings/' . (int) $payment['booking_id'])) ?>"><?= e($payment['booking_reference']) ?></a> <?= status_badge((string) $payment['booking_status']) ?></dd>
            <dt class="col-4">Passenger</dt><dd class="col-8"><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?> (<?= e($payment['passenger_code']) ?>)</dd>
            <dt class="col-4">Amount</dt><dd class="col-8"><?= e(format_money($payment['amount'])) ?></dd>
            <dt class="col-4">Method</dt><dd class="col-8"><?= e(ucwords(str_replace('_', ' ', $payment['payment_method']))) ?></dd>
            <dt class="col-4">Reference</dt><dd class="col-8"><?= e($payment['transaction_reference'] ?: '—') ?></dd>
            <dt class="col-4">Recorded</dt><dd class="col-8"><?= e(format_datetime($payment['created_at'])) ?> by <?= e($payment['received_by_name'] ?: 'system / demo') ?></dd>
            <dt class="col-4">Paid at</dt><dd class="col-8"><?= e(format_datetime($payment['paid_at'])) ?></dd>
            <?php if ($status === 'refunded'): ?>
                <dt class="col-4">Refunded</dt><dd class="col-8"><?= e(format_datetime($payment['refunded_at'])) ?> by <?= e($payment['refunded_by_name'] ?: 'system / demo') ?><br><span class="text-muted small"><?= e($payment['refund_reason']) ?></span></dd>
            <?php endif; ?>
        </dl>
        <?php if (in_array($status, ['successful', 'refunded'], true)): ?>
            <a class="btn btn-outline-primary btn-sm mt-2" href="<?= e(url('/payments/' . $id . '/receipt')) ?>"><i class="bi bi-receipt me-1"></i>View / print receipt</a>
        <?php endif; ?>
    </div></div></div>
    <div class="col-lg-5 d-flex flex-column gap-3">
        <?php if ($status === 'pending' && can('payments.record')): ?>
        <div class="card"><div class="card-body">
            <h3 class="h6">Verify this payment</h3>
            <form method="post" action="<?= e(url('/payments/' . $id . '/resolve')) ?>" data-loading>
                <?= csrf_field() ?><input type="hidden" name="outcome" value="successful">
                <?php if ($payment['payment_method'] !== 'cash'): ?>
                    <input class="form-control form-control-sm mb-2" name="transaction_reference" maxlength="150" placeholder="Verified reference" value="<?= e($payment['transaction_reference']) ?>">
                <?php endif; ?>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="verified" value="1" id="v1" required><label class="form-check-label small" for="v1">Money received / reference verified</label></div>
                <button class="btn btn-success btn-sm">Mark successful &amp; confirm booking</button>
            </form>
            <hr>
            <?= post_button('/payments/' . $id . '/resolve', 'Mark failed', 'btn-outline-danger', 'Mark this payment attempt as failed?', ['outcome' => 'failed']) ?>
        </div></div>
        <?php endif; ?>
        <?php if ($status === 'successful' && can('payments.refund')): ?>
        <div class="card"><div class="card-body">
            <h3 class="h6">Refund</h3>
            <?php if ($payment['booking_status'] === 'cancelled'): ?>
                <form method="post" action="<?= e(url('/payments/' . $id . '/refund')) ?>" data-confirm="Record a refund of <?= e(format_money($payment['amount'])) ?>? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input class="form-control form-control-sm mb-2" name="reason" maxlength="500" placeholder="Refund reason (required)" required>
                    <button class="btn btn-outline-danger btn-sm">Record refund</button>
                </form>
            <?php else: ?>
                <p class="small text-muted mb-0">Cancel the booking first (you can tick "refund" there), then refund here.</p>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>
    </div>
</div>
