<?php /** @var array $filters @var array $rows @var \App\Core\Paginator $paginator */ ?>
<div class="alert alert-secondary small"><i class="bi bi-info-circle me-1"></i>These are <strong>recorded</strong> payments (cash received, or bank/e-wallet/card references verified by staff). No online gateway is connected and no card data is stored.</div>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/payments')) ?>" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Payment / booking / txn ref"></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status"><option value="">All</option>
                <?php foreach (['pending', 'successful', 'failed', 'refunded'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="method">Method</label>
            <select class="form-select" id="method" name="method"><option value="">All</option>
                <?php foreach (['cash', 'bank_transfer', 'e_wallet', 'card'] as $m): ?><option value="<?= e($m) ?>" <?= $filters['method'] === $m ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $m))) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="col-md-1 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
    </form>
</div></div>
<div class="card">
<?php if (!$rows): ?>
    <div class="empty-state"><i class="bi bi-cash-coin"></i><p>No payments match your filters.</p></div>
<?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Payment</th><th>Booking</th><th>Passenger</th><th>Method</th><th class="text-end">Amount</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $p): ?>
            <tr>
                <td class="fw-semibold"><a class="text-decoration-none" href="<?= e(url('/payments/' . (int) $p['id'])) ?>"><?= e($p['payment_reference']) ?></a></td>
                <td><a href="<?= e(url('/bookings/' . (int) $p['booking_id'])) ?>"><?= e($p['booking_reference']) ?></a></td>
                <td><?= e($p['passenger_name']) ?></td>
                <td><?= e(ucwords(str_replace('_', ' ', $p['payment_method']))) ?></td>
                <td class="text-end"><?= e(format_money($p['amount'])) ?></td>
                <td><?= e(format_datetime($p['paid_at'] ?: $p['created_at'])) ?></td>
                <td><?= status_badge((string) $p['payment_status']) ?></td>
                <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/payments/' . (int) $p['id'])) ?>">View</a>
                    <?php if (in_array($p['payment_status'], ['successful', 'refunded'], true)): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/payments/' . (int) $p['id'] . '/receipt')) ?>">Receipt</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= (int) $paginator->total ?> payment(s)</span>
        <?= partial('layouts/partials/pagination', ['page' => $paginator->page, 'pages' => $paginator->pages, 'baseUrl' => '/payments', 'query' => array_filter($filters, 'strlen')]) ?>
    </div>
<?php endif; ?>
</div>
