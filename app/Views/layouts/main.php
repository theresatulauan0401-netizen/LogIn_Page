<?php
$user = auth()->user();
$path = current_path();
$title = $title ?? 'Dashboard';
$subtitle = $subtitle ?? '';
$breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= partial('layouts/partials/head') ?>
    <title><?= e($title) ?> &middot; <?= e(setting('company_name', config('app.name'))) ?></title>
</head>
<body class="app-body">
<div class="app-shell" id="appShell">
    <?= partial('layouts/partials/sidebar', ['path' => $path]) ?>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
        <?= partial('layouts/partials/header', ['user' => $user, 'notifications' => $notifications ?? ['items' => [], 'total' => 0]]) ?>
        <main class="app-content">
            <?php if ($breadcrumbs): ?>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small mb-2">
                        <li class="breadcrumb-item"><a href="<?= e(url('/dashboard')) ?>">Home</a></li>
                        <?php foreach ($breadcrumbs as [$label, $href]): ?>
                            <?php if ($href): ?>
                                <li class="breadcrumb-item"><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active" aria-current="page"><?= e($label) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            <?php endif; ?>
            <div class="page-header">
                <div>
                    <h1 class="page-title"><?= e($title) ?></h1>
                    <?php if ($subtitle !== ''): ?><p class="page-subtitle"><?= e($subtitle) ?></p><?php endif; ?>
                </div>
                <?php if (!empty($actions)): ?><div class="page-actions"><?= $actions /* trusted HTML built by the view */ ?></div><?php endif; ?>
            </div>
            <?= partial('layouts/partials/flash') ?>
            <?= $content ?>
        </main>
        <?= partial('layouts/partials/footer') ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
