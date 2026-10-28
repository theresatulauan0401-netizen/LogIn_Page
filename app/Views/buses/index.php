<?php /** @var array $filters @var array $rows @var \App\Core\Paginator $paginator */ ?>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/buses')) ?>" class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Bus number, plate or type"></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (['active', 'maintenance', 'inactive'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a class="btn btn-light" href="<?= e(url('/buses')) ?>">Reset</a>
        </div>
    </form>
</div></div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="bi bi-bus-front"></i><p>No buses match your search.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Bus no.</th><th>Plate</th><th>Type</th><th class="text-end">Seats</th><th class="text-end">Open trips</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $b): ?>
                <tr>
                    <td class="fw-semibold"><a href="<?= e(url('/buses/' . (int) $b['id'])) ?>" class="text-decoration-none"><?= e($b['bus_number']) ?></a></td>
                    <td><?= e($b['plate_number']) ?></td>
                    <td><?= e($b['bus_type']) ?><?php if ($b['bus_model']): ?><div class="small text-muted"><?= e($b['bus_model']) ?></div><?php endif; ?></td>
                    <td class="text-end"><?= (int) $b['capacity'] ?></td>
                    <td class="text-end"><?= (int) $b['upcoming_trips'] ?></td>
                    <td><?= status_badge((string) $b['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/buses/' . (int) $b['id'])) ?>">View</a>
                        <?php if (can('buses.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/buses/' . (int) $b['id'] . '/edit')) ?>">Edit</a><?php endif; ?>
                        <?php if (can('buses.deactivate')): ?>
                            <?php if ($b['status'] === 'active'): ?>
                                <?= post_button('/buses/' . (int) $b['id'] . '/status', 'Deactivate', 'btn-outline-danger', 'Deactivate bus ' . $b['bus_number'] . '?', ['status' => 'inactive']) ?>
                            <?php else: ?>
                                <?= post_button('/buses/' . (int) $b['id'] . '/status', 'Activate', 'btn-outline-success', null, ['status' => 'active']) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= (int) $paginator->total ?> bus(es)</span>
        <?= partial('layouts/partials/pagination', ['page' => $paginator->page, 'pages' => $paginator->pages, 'baseUrl' => '/buses', 'query' => array_filter($filters, 'strlen')]) ?>
    </div>
    <?php endif; ?>
</div>
