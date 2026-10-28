<?php /** @var array $filters @var array $rows @var \App\Core\Paginator $paginator */
$me = auth()->user(); $roleLabel = static fn (string $r): string => ucwords(str_replace('_', ' ', $r)); ?>
<div class="card mb-3"><div class="card-body">
    <form method="get" action="<?= e(url('/users')) ?>" class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small mb-1" for="q">Search</label><input type="search" class="form-control" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name or e-mail"></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="role">Role</label>
            <select class="form-select" id="role" name="role"><option value="">All roles</option>
                <?php foreach (['super_admin', 'admin', 'booking_staff'] as $r): ?><option value="<?= e($r) ?>" <?= $filters['role'] === $r ? 'selected' : '' ?>><?= e($roleLabel($r)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status"><option value="">All</option>
                <?php foreach (['active', 'inactive'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button><a class="btn btn-light" href="<?= e(url('/users')) ?>">Reset</a></div>
    </form>
</div></div>
<div class="card">
<?php if (!$rows): ?><div class="empty-state"><i class="bi bi-people"></i><p>No users found.</p></div>
<?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Name</th><th>E-mail</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php foreach ($rows as $u):
            $manage = can('users.manage_staff') && \App\Services\UserService::canManage($me['role'] ?? null, (string) $u['role']);
            $self = (int) $u['id'] === (int) ($me['id'] ?? 0); ?>
            <tr>
                <td class="fw-semibold"><?= e($u['full_name']) ?><?= $self ? ' <span class="badge text-bg-light border">you</span>' : '' ?></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="badge text-bg-primary"><?= e($roleLabel((string) $u['role'])) ?></span></td>
                <td><?= status_badge((string) $u['status']) ?></td>
                <td><?= e(format_datetime($u['last_login_at'])) ?></td>
                <td class="text-end text-nowrap">
                    <?php if ($manage): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/users/' . (int) $u['id'] . '/edit')) ?>">Edit</a>
                        <?php if (!$self): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/users/' . (int) $u['id'] . '/password')) ?>">Reset password</a>
                            <?php if ($u['status'] === 'active'): ?>
                                <?= post_button('/users/' . (int) $u['id'] . '/status', 'Deactivate', 'btn-outline-danger', 'Deactivate ' . $u['full_name'] . '? They will be signed out on their next request.', ['status' => 'inactive']) ?>
                            <?php else: ?>
                                <?= post_button('/users/' . (int) $u['id'] . '/status', 'Activate', 'btn-outline-success', null, ['status' => 'active']) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?><span class="text-muted small">view only</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
    <div class="card-body border-top d-flex justify-content-between align-items-center">
        <span class="small text-muted"><?= (int) $paginator->total ?> user(s)</span>
        <?= partial('layouts/partials/pagination', ['page' => $paginator->page, 'pages' => $paginator->pages, 'baseUrl' => '/users', 'query' => array_filter($filters, 'strlen')]) ?>
    </div>
<?php endif; ?>
</div>
