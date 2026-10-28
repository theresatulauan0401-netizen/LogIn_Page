<?php /** @var int $status @var string $message @var bool $loggedIn */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= partial('layouts/partials/head') ?>
    <title>Error <?= (int) $status ?></title>
</head>
<body class="auth-body">
<main class="auth-wrap text-center">
    <div class="card auth-card"><div class="card-body p-5">
        <div class="display-4 fw-bold text-primary"><?= (int) $status ?></div>
        <p class="lead"><?= e($message) ?></p>
        <a class="btn btn-primary" href="<?= e(url($loggedIn ? '/dashboard' : '/login')) ?>">
            <?= $loggedIn ? 'Back to dashboard' : 'Go to sign in' ?></a>
    </div></div>
</main>
</body>
</html>
