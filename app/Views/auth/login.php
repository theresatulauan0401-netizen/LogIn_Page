<div class="card auth-card">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="auth-logo"><i class="bi bi-bus-front-fill"></i></div>
            <h1 class="h4 mb-1">Bus Reservation System</h1>
            <p class="text-muted mb-0">Sign in to the staff dashboard</p>
        </div>
        <form method="post" action="<?= e(url('/login')) ?>" novalidate data-loading>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control <?= field_error('email') ? 'is-invalid' : '' ?>" id="email" name="email"
                       value="<?= e(old('email')) ?>" autocomplete="username" maxlength="190" required autofocus>
                <div class="invalid-feedback"><?= e(field_error('email')) ?></div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control <?= field_error('password') ? 'is-invalid' : '' ?>" id="password" name="password"
                       autocomplete="current-password" maxlength="255" required>
                <div class="invalid-feedback"><?= e(field_error('password')) ?></div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Sign in</button>
        </form>
        <p class="text-muted small text-center mt-4 mb-0">Accounts are created by an administrator. There is no public registration.</p>
    </div>
</div>
