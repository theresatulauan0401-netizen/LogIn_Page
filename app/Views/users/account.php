<div class="card" style="max-width:560px"><div class="card-body">
    <form method="post" action="<?= e(url('/account/password')) ?>" novalidate data-loading autocomplete="off">
        <?= csrf_field() ?>
        <div class="row g-3">
            <?= form_input('current_password', 'Current password', ['type' => 'password', 'required' => true, 'col' => 'col-12']) ?>
            <?= form_input('password', 'New password', ['type' => 'password', 'required' => true, 'col' => 'col-12', 'help' => 'At least 12 characters with upper-case, lower-case and a number.']) ?>
            <?= form_input('password_confirmation', 'Repeat new password', ['type' => 'password', 'required' => true, 'col' => 'col-12']) ?>
        </div>
        <div class="mt-4"><button class="btn btn-primary" type="submit">Change password</button></div>
    </form>
</div></div>
