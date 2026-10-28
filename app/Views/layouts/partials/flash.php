<?php foreach (app(\App\Core\Session::class)->pullFlash() as $flash):
    $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
    $icon = ['success' => 'check-circle-fill', 'danger' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'][$type]; ?>
    <div class="alert alert-<?= $type ?> alert-dismissible d-flex align-items-center flash-message" role="alert">
        <i class="bi bi-<?= $icon ?> me-2"></i>
        <div><?= e($flash['message']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
