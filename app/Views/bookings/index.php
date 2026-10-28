<?php /** @var array $filters @var array $rows @var \App\Core\Paginator $paginator */ ?>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/bookings')) ?>" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Reference, passenger, phone"></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status"><option value="">All</option>
                <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'no_show'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="from">Booked from</label><input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="to">Booked until</label><input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a class="btn btn-light" href="<?= e(url('/bookings')) ?>">Reset</a></div>
    </form>
</div></div>
<div class="card">
<?php if (!$rows): ?>
    <div class="empty-state"><i class="bi bi-journal"></i><p>No bookings match your filters.</p></div>
<?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Reference</th><th>Passenger</th><th>Route</th><th>Departure</th><th>Seat</th><th class="text-end">Total</th><th>Payment</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $b): ?>
            <tr>
                <td class="fw-semibold"><a class="text-decoration-none" href="<?= e(url('/bookings/' . (int) $b['id'])) ?>"><?= e($b['booking_reference']) ?></a></td>
                <td><?= e($b['passenger_name']) ?><div class="small text-muted"><?= e($b['passenger_code']) ?></div></td>
                <td><?= e($b['route_name']) ?></td>
                <td><?= e(format_datetime($b['departure_datetime'])) ?></td>
                <td><?= e($b['seat_number']) ?></td>
                <td class="text-end"><?= e(format_money($b['total_amount'])) ?></td>
                <td><?= $b['payment_status'] ? status_badge((string) $b['payment_status']) : '<span class="text-muted small">none</span>' ?></td>
                <td><?= status_badge((string) $b['status']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/bookings/' . (int) $b['id'])) ?>">View</a></td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= (int) $paginator->total ?> booking(s)</span>
        <?= partial('layouts/partials/pagination', ['page' => $paginator->page, 'pages' => $paginator->pages, 'baseUrl' => '/bookings', 'query' => array_filter($filters, 'strlen')]) ?>
    </div>
<?php endif; ?>
</div>
