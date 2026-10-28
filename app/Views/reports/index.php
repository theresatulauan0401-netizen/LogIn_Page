<?php /** @var string $type @var array $f @var array $report @var array $types @var array $groups */
$qs = static fn (array $over = []): string => http_build_query(array_filter(array_merge(['type' => $type] + $f, $over), 'strlen'));
$fmt = static function (mixed $v, string $t): string {
    if ($v === null || $v === '') { return '—'; }
    return match ($t) {
        'money' => format_money($v), 'int' => number_format((float) $v), 'percent' => number_format((float) $v, 1) . '%',
        'datetime' => format_datetime((string) $v), default => (string) $v,
    };
}; ?>
<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    <?php foreach ($types as $key => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $key === $type ? 'active' : 'bg-white' ?>" href="<?= e(url('/reports?' . $qs(['type' => $key]))) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/reports')) ?>" class="row g-2 align-items-end">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="col-md-2"><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($f['from']) ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($f['to']) ?>"></div>
        <?php if (in_array($type, ['bookings', 'revenue'], true)): ?>
            <div class="col-md-2"><label class="form-label small mb-1" for="group">Group by</label>
                <select class="form-select" id="group" name="group"><?php foreach ($groups as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['group'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <?php if (in_array($type, ['bookings', 'passengers'], true)): ?>
            <div class="col-md-2"><label class="form-label small mb-1" for="status">Booking status</label>
                <select class="form-select" id="status" name="status"><option value="">All</option>
                    <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'no_show'] as $s): ?><option value="<?= e($s) ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary">Run report</button>
            <a class="btn btn-outline-success" href="<?= e(url('/reports/export?' . $qs())) ?>"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a></div>
    </form>
</div></div>
<div class="card">
    <div class="card-header"><span class="fw-semibold"><?= e($report['title']) ?></span> <span class="text-muted small">&middot; <?= e(format_date($f['from'])) ?> to <?= e(format_date($f['to'])) ?></span></div>
    <?php if (!$report['rows']): ?>
        <div class="empty-state"><i class="bi bi-bar-chart-line"></i><p>No records in this date range.</p></div>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><?php foreach ($report['columns'] as $c): ?><th class="<?= in_array($c['type'], ['int', 'money', 'percent'], true) ? 'text-end' : '' ?>"><?= e($c['label']) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($report['rows'] as $row): ?>
            <tr><?php foreach ($report['columns'] as $c): ?><td class="<?= in_array($c['type'], ['int', 'money', 'percent'], true) ? 'text-end' : '' ?>"><?= e($fmt($row[$c['key']] ?? null, $c['type'])) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if ($report['totals']): ?>
            <tr class="table-light fw-bold"><?php foreach ($report['columns'] as $c): ?><td class="<?= in_array($c['type'], ['int', 'money', 'percent'], true) ? 'text-end' : '' ?>"><?= $c['sum'] ? e($fmt($report['totals'][$c['key']], $c['type'])) : e((string) $report['totals'][$c['key']]) ?></td><?php endforeach; ?></tr>
        <?php endif; ?>
        </tbody></table></div>
    <?php endif; ?>
    <div class="card-body border-top small text-muted"><i class="bi bi-info-circle me-1"></i><?= e($report['note']) ?></div>
</div>
