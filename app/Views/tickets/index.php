<?php /** @var array $filters @var array $rows @var \App\Core\Paginator $paginator */ ?>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/tickets')) ?>" class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Ticket no., booking ref or passenger"></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status"><option value="">All</option>
                <?php foreach (['valid', 'used', 'cancelled', 'expired'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button><a class="btn btn-light" href="<?= e(url('/tickets')) ?>">Reset</a></div>
    </form>
</div></div>
<div class="card">
<?php if (!$rows): ?>
    <div class="empty-state"><i class="bi bi-ticket-perforated"></i><p>No tickets found.</p></div>
<?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Ticket</th><th>Passenger</th><th>Route</th><th>Departure</th><th>Seat</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($rows as $t): ?>
            <tr><td class="fw-semibold"><a class="text-decoration-none" href="<?= e(url('/tickets/' . (int) $t['id'])) ?>"><?= e($t['ticket_number']) ?></a><div class="small text-muted"><?= e($t['booking_reference']) ?></div></td>
                <td><?= e($t['passenger_name']) ?></td><td><?= e($t['route_name']) ?></td><td><?= e(format_datetime($t['departure_datetime'])) ?></td>
                <td><?= e($t['seat_number']) ?></td><td><?= status_badge((string) $t['ticket_status']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/tickets/' . (int) $t['id'])) ?>">View / print</a></td></tr>
        <?php endforeach; ?></tbody></table></div>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= (int) $paginator->total ?> ticket(s)</span>
        <?= partial('layouts/partials/pagination', ['page' => $paginator->page, 'pages' => $paginator->pages, 'baseUrl' => '/tickets', 'query' => array_filter($filters, 'strlen')]) ?>
    </div>
<?php endif; ?>
</div>
