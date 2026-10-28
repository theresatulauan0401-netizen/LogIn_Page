<?php /** @var \App\Models\User|null $user @var string[] $roles */
$editing = $user !== null; $action = $editing ? '/users/' . $user->id() : '/users';
$labels = []; foreach ($roles as $r) { $labels[$r] = ucwords(str_replace('_', ' ', $r)); }
$isSelf = $editing && $user->id() === (int) auth()->id(); ?>
<div class="card"><div class="card-body">
    <form method="post" action="<?= e(url($action)) ?>" novalidate data-loading autocomplete="off">
        <?= csrf_field() ?>
        <div class="row g-3">
            <?= form_input('full_name', 'Full name', ['required' => true, 'maxlength' => 150, 'value' => $user?->fullName()]) ?>
            <?= form_input('email', 'E-mail (login)', ['type' => 'email', 'required' => true, 'maxlength' => 190, 'value' => $user?->email()]) ?>
            <?= form_select('role', 'Role', $labels, ['required' => true, 'value' => $user?->role() ?? 'booking_staff', 'disabled' => $isSelf,
                'help' => $isSelf ? 'You cannot change your own role.' : (count($roles) === 1 ? 'Administrators can create Booking Staff accounts only.' : '')]) ?>
            <?php if ($isSelf): ?><input type="hidden" name="role" value="<?= e($user->role()) ?>"><?php endif; ?>
            <?php if (!$editing): ?>
                <div class="col-md-6"></div>
                <?= form_input('password', 'Password', ['type' => 'password', 'required' => true, 'help' => 'At least 12 characters with upper-case, lower-case and a number.']) ?>
                <?= form_input('password_confirmation', 'Repeat password', ['type' => 'password', 'required' => true]) ?>
            <?php endif; ?>
        </div>
        <div class="mt-4 d-flex gap-2"><button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Create user' ?></button>
            <a class="btn btn-light" href="<?= e(url('/users')) ?>">Cancel</a></div>
    </form>
</div></div>
