<?php /** @var array $filters @var array $rows @var \App\Core\Paginator $paginator */ ?>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/routes')) ?>" class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Code, origin or destination"></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (['active', 'inactive'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a class="btn btn-light" href="<?= e(url('/routes')) ?>">Reset</a></div>
    </form>
</div></div>
<div class="card">
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="bi bi-signpost-split"></i><p>No routes match your search.</p></div>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Code</th><th>Route</th><th class="text-end">Distance</th><th class="text-end">Duration</th><th class="text-end">Base fare</th><th class="text-end">Open trips</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><a class="text-decoration-none" href="<?= e(url('/routes/' . (int) $r['id'])) ?>"><?= e($r['route_code']) ?></a></td>
                <td><?= e($r['origin']) ?> <i class="bi bi-arrow-right small text-muted"></i> <?= e($r['destination']) ?></td>
                <td class="text-end"><?= $r['distance_km'] !== null ? e(number_format((float) $r['distance_km'], 1)) . ' km' : '—' ?></td>
                <td class="text-end"><?= $r['estimated_duration_minutes'] !== null ? e(intdiv((int) $r['estimated_duration_minutes'], 60) . 'h ' . ((int) $r['estimated_duration_minutes'] % 60) . 'm') : '—' ?></td>
                <td class="text-end"><?= e(format_money($r['base_fare'])) ?></td>
                <td class="text-end"><?= (int) $r['upcoming_trips'] ?></td>
                <td><?= status_badge((string) $r['status']) ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/routes/' . (int) $r['id'])) ?>">View</a>
                    <?php if (can('routes.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/routes/' . (int) $r['id'] . '/edit')) ?>">Edit</a><?php endif; ?>
                    <?php if (can('routes.deactivate')): ?>
                        <?php if ($r['status'] === 'active'): ?>
                            <?= post_button('/routes/' . (int) $r['id'] . '/status', 'Deactivate', 'btn-outline-danger', 'Deactivate route ' . $r['route_code'] . '?', ['status' => 'inactive']) ?>
                        <?php else: ?>
                            <?= post_button('/routes/' . (int) $r['id'] . '/status', 'Activate', 'btn-outline-success', null, ['status' => 'active']) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= (int) $paginator->total ?> route(s)</span>
        <?= partial('layouts/partials/pagination', ['page' => $paginator->page, 'pages' => $paginator->pages, 'baseUrl' => '/routes', 'query' => array_filter($filters, 'strlen')]) ?>
    </div>
    <?php endif; ?>
</div>
