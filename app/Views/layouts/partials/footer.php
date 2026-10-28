<footer class="app-footer">
    <span>&copy; <?= date('Y') ?> <?= e(setting('company_name', config('app.name'))) ?></span>
    <span class="text-muted">Recorded-payment demo system &middot; not an online payment gateway</span>
</footer>

<!-- Reusable confirmation dialog: add data-confirm="Message" to any <form> -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Please confirm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="confirmModalBody">Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmModalOk">Yes, continue</button>
            </div>
        </div>
    </div>
</div>
