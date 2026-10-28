<?php /** @var array $filters @var array $rows @var \App\Core\Paginator $paginator @var array $routeOptions @var array $busOptions */ ?>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/schedules')) ?>" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1" for="q">Schedule code</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="SCH-..."></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="route_id">Route</label>
            <select class="form-select" id="route_id" name="route_id"><option value="">All routes</option>
                <?php foreach ($routeOptions as $id => $label): ?><option value="<?= (int) $id ?>" <?= $filters['route_id'] === (string) $id ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="bus_id">Bus</label>
            <select class="form-select" id="bus_id" name="bus_id"><option value="">All buses</option>
                <?php foreach ($busOptions as $id => $label): ?><option value="<?= (int) $id ?>" <?= $filters['bus_id'] === (string) $id ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status"><option value="">All statuses</option>
                <?php foreach (['scheduled', 'boarding', 'departed', 'completed', 'cancelled'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="from">Departs from</label>
            <input type="date" class="form-control" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="to">Departs until</label>
            <input type="date" class="form-control" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="col-md-6 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a class="btn btn-light" href="<?= e(url('/schedules')) ?>">Reset</a></div>
    </form>
</div></div>
<div class="card">
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No schedules match your filters.</p></div>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Code</th><th>Route</th><th>Bus</th><th>Departure</th><th>Arrival</th><th class="text-end">Fare</th><th class="text-end">Seats left</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $s): ?>
            <tr>
                <td class="fw-semibold"><a class="text-decoration-none" href="<?= e(url('/schedules/' . (int) $s['id'])) ?>"><?= e($s['schedule_code']) ?></a></td>
                <td><?= e($s['route_name']) ?></td>
                <td><?= e($s['bus_number']) ?></td>
                <td><?= e(format_datetime($s['departure_datetime'])) ?></td>
                <td><?= e(format_datetime($s['arrival_datetime'])) ?></td>
                <td class="text-end"><?= e(format_money($s['fare'])) ?></td>
                <td class="text-end"><?= (int) $s['available_seats'] ?> / <?= (int) $s['capacity'] ?></td>
                <td><?= status_badge((string) $s['status']) ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/schedules/' . (int) $s['id'])) ?>">View</a>
                    <?php if (can('schedules.edit') && $s['status'] === 'scheduled'): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/schedules/' . (int) $s['id'] . '/edit')) ?>">Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= (int) $paginator->total ?> schedule(s)</span>
        <?= partial('layouts/partials/pagination', ['page' => $paginator->page, 'pages' => $paginator->pages, 'baseUrl' => '/schedules', 'query' => array_filter($filters, 'strlen')]) ?>
    </div>
    <?php endif; ?>
</div>
