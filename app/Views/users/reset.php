<?php /** @var \App\Models\User $user */ ?>
<div class="card" style="max-width:560px"><div class="card-body">
    <p>Set a new password for <strong><?= e($user->fullName()) ?></strong> (<?= e($user->email()) ?>). Share it through a secure channel; it is stored only as a hash.</p>
    <form method="post" action="<?= e(url('/users/' . $user->id() . '/password')) ?>" novalidate data-loading autocomplete="off">
        <?= csrf_field() ?>
        <div class="row g-3">
            <?= form_input('password', 'New password', ['type' => 'password', 'required' => true, 'col' => 'col-12', 'help' => 'At least 12 characters with upper-case, lower-case and a number.']) ?>
            <?= form_input('password_confirmation', 'Repeat new password', ['type' => 'password', 'required' => true, 'col' => 'col-12']) ?>
        </div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Reset password</button><a class="btn btn-light" href="<?= e(url('/users')) ?>">Cancel</a></div>
    </form>
</div></div>
