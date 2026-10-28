<?php
/** Expects: $page (int), $pages (int), $baseUrl (path), $query (array of current filters). */
if (($pages ?? 1) <= 1) { return; }
$build = static fn (int $p): string => url($baseUrl) . '?' . http_build_query(array_merge($query ?? [], ['page' => $p]));
$from = max(1, $page - 2);
$to = min($pages, $page + 2);
?>
<nav aria-label="Pagination">
    <ul class="pagination pagination-sm mb-0 justify-content-end">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($build(max(1, $page - 1))) ?>">&laquo;</a></li>
        <?php for ($p = $from; $p <= $to; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($build($p)) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($build(min($pages, $page + 1))) ?>">&raquo;</a></li>
    </ul>
</nav>
