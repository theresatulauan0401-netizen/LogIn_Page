<?php /** @var \App\Models\Route $route @var array $upcoming */ ?>
<div class="row g-3">
    <div class="col-lg-5"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3"><h2 class="h5 mb-0"><?= e($route->routeCode()) ?></h2><?= status_badge($route->status()) ?></div>
        <dl class="detail-list row mb-0">
            <dt class="col-5">Origin</dt><dd class="col-7"><?= e($route->origin()) ?></dd>
            <dt class="col-5">Destination</dt><dd class="col-7"><?= e($route->destination()) ?></dd>
            <dt class="col-5">Distance</dt><dd class="col-7"><?= $route->distanceKm() !== null ? e(number_format((float) $route->distanceKm(), 1)) . ' km' : '—' ?></dd>
            <dt class="col-5">Duration</dt><dd class="col-7"><?= $route->durationMinutes() !== null ? e(intdiv($route->durationMinutes(), 60) . 'h ' . ($route->durationMinutes() % 60) . 'm') : '—' ?></dd>
            <dt class="col-5">Base fare</dt><dd class="col-7"><?= e(format_money($route->baseFare())) ?></dd>
        </dl>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php if (can('routes.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/routes/' . $route->id() . '/edit')) ?>">Edit</a><?php endif; ?>
            <?php if (can('routes.deactivate')): ?>
                <?php if ($route->isActive()): ?>
                    <?= post_button('/routes/' . $route->id() . '/status', 'Deactivate', 'btn-outline-danger', 'Deactivate this route?', ['status' => 'inactive']) ?>
                <?php else: ?>
                    <?= post_button('/routes/' . $route->id() . '/status', 'Activate', 'btn-outline-success', null, ['status' => 'active']) ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div></div></div>
    <div class="col-lg-7"><div class="card h-100">
        <div class="card-header fw-semibold">Scheduled &amp; boarding trips</div>
        <?php if (!$upcoming): ?>
            <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No open trips on this route.</p></div>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>Schedule</th><th>Bus</th><th>Departure</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($upcoming as $s): ?>
                    <tr><td><a href="<?= e(url('/schedules/' . (int) $s['id'])) ?>"><?= e($s['schedule_code']) ?></a></td>
                        <td><?= e($s['bus_number']) ?></td><td><?= e(format_datetime($s['departure_datetime'])) ?></td>
                        <td><?= status_badge((string) $s['status']) ?></td></tr>
                <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div></div>
</div>
