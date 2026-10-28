<?php /** @var array $payment */ $refunded = $payment['payment_status'] === 'refunded'; ?>
<div class="mb-3 no-print d-flex gap-2">
    <button type="button" class="btn btn-primary" data-print><i class="bi bi-printer me-1"></i>Print receipt</button>
    <a class="btn btn-light" href="<?= e(url('/payments/' . (int) $payment['id'])) ?>">Back</a>
</div>
<div class="card receipt mx-auto"><div class="card-body p-4">
    <div class="text-center mb-3">
        <div class="h5 mb-0"><?= e(setting('company_name', config('app.name'))) ?></div>
        <div class="small text-muted"><?= e(setting('company_address', '')) ?> &middot; <?= e(setting('company_phone', '')) ?></div>
        <div class="fw-semibold mt-2 text-uppercase">Payment receipt<?= $refunded ? ' (REFUNDED)' : '' ?></div>
    </div>
    <table class="table table-sm mb-3"><tbody>
        <tr><th>Receipt no.</th><td><?= e($payment['payment_reference']) ?></td></tr>
        <tr><th>Date paid</th><td><?= e(format_datetime($payment['paid_at'])) ?></td></tr>
        <tr><th>Booking</th><td><?= e($payment['booking_reference']) ?></td></tr>
        <tr><th>Passenger</th><td><?= e($payment['first_name'] . ' ' . $payment['last_name']) ?></td></tr>
        <tr><th>Trip</th><td><?= e($payment['origin']) ?> to <?= e($payment['destination']) ?>, <?= e(format_datetime($payment['departure_datetime'])) ?>, seat <?= e($payment['seat_number']) ?></td></tr>
        <tr><th>Method</th><td><?= e(ucwords(str_replace('_', ' ', $payment['payment_method']))) ?><?= $payment['transaction_reference'] ? ' &middot; ref ' . e($payment['transaction_reference']) : '' ?></td></tr>
        <tr><th>Received by</th><td><?= e($payment['received_by_name'] ?: '—') ?></td></tr>
        <tr class="fs-5"><th>Amount</th><td class="fw-bold"><?= e(format_money($payment['amount'])) ?></td></tr>
        <?php if ($refunded): ?><tr class="table-danger"><th>Refunded</th><td><?= e(format_datetime($payment['refunded_at'])) ?> &middot; <?= e($payment['refund_reason']) ?></td></tr><?php endif; ?>
    </tbody></table>
    <p class="small text-muted text-center mb-0">Recorded payment. Not issued by an online payment gateway. Keep this receipt for your records.</p>
</div></div>
