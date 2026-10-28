<?php /** @var array $filters @var array $rows @var \App\Core\Paginator $paginator */ ?>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/passengers')) ?>" class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, code, e-mail or phone"></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status"><option value="">All</option>
                <?php foreach (['active', 'inactive'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a class="btn btn-light" href="<?= e(url('/passengers')) ?>">Reset</a></div>
    </form>
</div></div>
<div class="card">
<?php if (!$rows): ?>
    <div class="empty-state"><i class="bi bi-people"></i><p>No passengers found.</p></div>
<?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Code</th><th>Name</th><th>Phone</th><th>E-mail</th><th class="text-end">Bookings</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php foreach ($rows as $p): ?>
            <tr>
                <td class="fw-semibold"><?= e($p['passenger_code']) ?></td>
                <td><a class="text-decoration-none" href="<?= e(url('/passengers/' . (int) $p['id'])) ?>"><?= e($p['last_name'] . ', ' . $p['first_name']) ?></a></td>
                <td><?= e($p['phone']) ?></td><td><?= e($p['email'] ?: '—') ?></td>
                <td class="text-end"><?= (int) $p['booking_count'] ?></td>
                <td><?= status_badge((string) $p['status']) ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/passengers/' . (int) $p['id'])) ?>">Profile</a>
                    <?php if (can('passengers.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/passengers/' . (int) $p['id'] . '/edit')) ?>">Edit</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= (int) $paginator->total ?> passenger(s)</span>
        <?= partial('layouts/partials/pagination', ['page' => $paginator->page, 'pages' => $paginator->pages, 'baseUrl' => '/passengers', 'query' => array_filter($filters, 'strlen')]) ?>
    </div>
<?php endif; ?>
</div>
