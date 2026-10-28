<!DOCTYPE html>
<html lang="en">
<head>
    <?= partial('layouts/partials/head') ?>
    <title><?= e($title ?? 'Sign in') ?> &middot; <?= e(setting('company_name', config('app.name'))) ?></title>
</head>
<body class="auth-body">
<main class="auth-wrap">
    <?= partial('layouts/partials/flash') ?>
    <?= $content ?>
    <p class="text-center text-muted small mt-4 mb-0">&copy; <?= date('Y') ?> <?= e(setting('company_name', config('app.name'))) ?></p>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
