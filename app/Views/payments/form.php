<?php /** @var array $booking */ $blocked = $booking['status'] !== 'pending'; ?>
<div class="row g-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-body">
        <h2 class="h6">Booking</h2>
        <dl class="detail-list mb-0">
            <dt>Reference</dt><dd><a href="<?= e(url('/bookings/' . (int) $booking['id'])) ?>"><?= e($booking['booking_reference']) ?></a> <?= status_badge((string) $booking['status']) ?></dd>
            <dt>Passenger</dt><dd><?= e($booking['first_name'] . ' ' . $booking['last_name']) ?></dd>
            <dt>Trip</dt><dd><?= e($booking['origin']) ?> to <?= e($booking['destination']) ?><br><span class="small text-muted"><?= e(format_datetime($booking['departure_datetime'])) ?> &middot; seat <?= e($booking['seat_number']) ?></span></dd>
            <dt>Amount due</dt><dd class="fs-5 fw-bold"><?= e(format_money($booking['total_amount'])) ?></dd>
        </dl>
    </div></div></div>
    <div class="col-lg-8"><div class="card"><div class="card-body">
        <?php if ($blocked): ?>
            <div class="alert alert-warning mb-0">Only pending bookings can receive a payment.</div>
        <?php else: ?>
        <?php if ($booking['has_pending_payment']): ?><div class="alert alert-warning">A pending payment already exists. Open it from the booking page to verify it or mark it failed.</div><?php endif; ?>
        <form method="post" action="<?= e(url('/payments')) ?>" novalidate data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
            <div class="row g-3">
                <?= form_select('method', 'Payment method', ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'e_wallet' => 'E-wallet', 'card' => 'Card (terminal)'], ['required' => true, 'value' => 'cash']) ?>
                <?= form_select('payment_status', 'Result', ['successful' => 'Successful (received & verified)', 'pending' => 'Pending verification', 'failed' => 'Failed attempt'], ['required' => true, 'value' => 'successful']) ?>
                <?= form_input('amount', 'Amount (PHP)', ['type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true, 'value' => $booking['total_amount'], 'help' => 'Must equal the amount due.']) ?>
                <?= form_input('transaction_reference', 'Verified reference', ['maxlength' => 150, 'help' => 'Required for non-cash. Use the bank/e-wallet/terminal reference. Never a card number.']) ?>
                <div class="col-12"><div class="form-check">
                    <input class="form-check-input <?= field_error('verified') ? 'is-invalid' : '' ?>" type="checkbox" name="verified" value="1" id="verified">
                    <label class="form-check-label" for="verified">I confirm the money was received / the reference was verified (needed for "Successful").</label>
                    <?php if (field_error('verified')): ?><div class="invalid-feedback d-block"><?= e(field_error('verified')) ?></div><?php endif; ?>
                </div></div>
            </div>
            <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Record payment</button>
                <a class="btn btn-light" href="<?= e(url('/bookings/' . (int) $booking['id'])) ?>">Cancel</a></div>
        </form>
        <?php endif; ?>
    </div></div></div>
</div>
