<?php $roleLabel = ucwords(str_replace('_', ' ', (string) ($user['role'] ?? ''))); ?>
<header class="app-topbar">
    <button class="btn btn-light btn-icon" type="button" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list fs-5"></i>
    </button>
    <div class="ms-auto d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-light btn-icon position-relative" data-bs-toggle="dropdown" aria-label="Notifications" aria-expanded="false">
                <i class="bi bi-bell fs-5"></i>
                <?php if (($notifications['total'] ?? 0) > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int) $notifications['total'] ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow-sm notif-menu">
                <h6 class="dropdown-header">Notifications</h6>
                <?php if (empty($notifications['items'])): ?>
                    <div class="px-3 py-2 text-muted small">Nothing needs your attention.</div>
                <?php else: foreach ($notifications['items'] as $n): ?>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= e(url($n['url'])) ?>">
                        <i class="bi <?= e($n['icon']) ?> text-primary"></i>
                        <span class="flex-grow-1"><?= e($n['label']) ?></span>
                        <span class="badge text-bg-light"><?= (int) $n['count'] ?></span>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="dropdown">
            <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" role="button" tabindex="0" aria-label="Account menu" aria-expanded="false" style="cursor:pointer">
                <div class="avatar"><?= e(strtoupper(mb_substr((string) ($user['name'] ?? '?'), 0, 1))) ?></div>
                <div class="lh-sm d-none d-sm-block">
                    <div class="fw-semibold small"><?= e($user['name'] ?? '') ?></div>
                    <div class="text-muted small"><?= e($roleLabel) ?></div>
                </div>
            </div>
            <div class="dropdown-menu dropdown-menu-end shadow-sm">
                <a class="dropdown-item" href="<?= e(url('/account/password')) ?>"><i class="bi bi-key me-2"></i>My password</a>
            </div>
        </div>
    </div>
</header>
