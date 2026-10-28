<?php /** @var \App\Models\Bus $bus @var array $upcoming */ ?>
<div class="row g-3">
    <div class="col-lg-5"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h2 class="h5 mb-0"><?= e($bus->busNumber()) ?></h2><?= status_badge($bus->status()) ?>
        </div>
        <dl class="detail-list row mb-0">
            <dt class="col-5">Plate number</dt><dd class="col-7"><?= e($bus->plateNumber()) ?></dd>
            <dt class="col-5">Type</dt><dd class="col-7"><?= e($bus->busType()) ?></dd>
            <dt class="col-5">Model</dt><dd class="col-7"><?= e($bus->busModel() ?: '—') ?></dd>
            <dt class="col-5">Seat capacity</dt><dd class="col-7"><?= $bus->capacity() ?></dd>
            <dt class="col-5">Amenities</dt>
            <dd class="col-7"><?= $bus->amenities() ? implode(' ', array_map(static fn ($a) => '<span class="badge text-bg-light border me-1 mb-1">' . e($a) . '</span>', $bus->amenities())) : '—' ?></dd>
        </dl>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php if (can('buses.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/buses/' . $bus->id() . '/edit')) ?>">Edit</a><?php endif; ?>
            <?php if (can('buses.deactivate')): ?>
                <?php if ($bus->status() === 'active'): ?>
                    <?= post_button('/buses/' . $bus->id() . '/status', 'Set maintenance', 'btn-outline-warning', 'Put this bus in maintenance?', ['status' => 'maintenance']) ?>
                    <?= post_button('/buses/' . $bus->id() . '/status', 'Deactivate', 'btn-outline-danger', 'Deactivate this bus?', ['status' => 'inactive']) ?>
                <?php else: ?>
                    <?= post_button('/buses/' . $bus->id() . '/status', 'Activate', 'btn-outline-success', null, ['status' => 'active']) ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div></div></div>
    <div class="col-lg-7"><div class="card h-100">
        <div class="card-header fw-semibold">Scheduled &amp; boarding trips</div>
        <?php if (!$upcoming): ?>
            <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No open trips for this bus.</p></div>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>Schedule</th><th>Route</th><th>Departure</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($upcoming as $s): ?>
                    <tr><td><a href="<?= e(url('/schedules/' . (int) $s['id'])) ?>"><?= e($s['schedule_code']) ?></a></td>
                        <td><?= e($s['route_name']) ?></td><td><?= e(format_datetime($s['departure_datetime'])) ?></td>
                        <td><?= status_badge((string) $s['status']) ?></td></tr>
                <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div></div>
</div>
