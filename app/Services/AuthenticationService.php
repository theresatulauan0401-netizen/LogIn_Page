<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserRepository;

final class AuthenticationService
{
    public const GENERIC_FAILURE = 'The e-mail or password is incorrect.';

    public function __construct(
        private UserRepository $users,
        private LoginAttemptRepository $attempts,
        private Auth $auth,
        private ActivityLogService $activity
    ) {
    }

    /** @return array{ok:bool,message:string} */
    public function attempt(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));

        // 1) Throttle (per e-mail and per IP) before touching credentials.
        $minutes = (int) Config::get('app.throttle.window_minutes', 15);
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);
        $maxEmail = (int) Config::get('app.throttle.max_attempts', 5);
        $maxIp = (int) Config::get('app.throttle.max_attempts_per_ip', 20);

        if ($this->attempts->failuresForEmail($email, $since) >= $maxEmail
            || $this->attempts->failuresForIp($ip, $since) >= $maxIp) {
            return ['ok' => false, 'message' => "Too many failed attempts. Please wait up to {$minutes} minutes and try again."];
        }

        // 2) Verify. A dummy hash is checked for unknown e-mails so response time
        //    does not reveal whether an account exists.
        $user = $this->users->findByEmail($email);
        $hash = $user?->passwordHash() ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $passwordOk = password_verify($password, $hash);

        // Disabled accounts get the SAME generic error as a wrong password.
        if ($user === null || !$passwordOk || !$user->isActive()) {
            $this->attempts->record($email, $ip, false);
            return ['ok' => false, 'message' => self::GENERIC_FAILURE];
        }

        // 3) Success.
        if (password_needs_rehash($user->passwordHash(), PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash($user->id(), password_hash($password, PASSWORD_DEFAULT));
        }
        $this->attempts->clearFailuresForEmail($email);
        $this->attempts->record($email, $ip, true);
        $this->users->touchLastLogin($user->id());
        $this->auth->login($user->toIdentity());
        $this->activity->log('login', 'auth', 'User logged in', $user->id());

        // Housekeeping: keep the attempts table small.
        $this->attempts->purgeOlderThan(date('Y-m-d H:i:s', time() - 86400));

        return ['ok' => true, 'message' => 'Welcome back, ' . $user->fullName() . '.'];
    }

    public function logout(): void
    {
        if ($this->auth->check()) {
            $this->activity->log('logout', 'auth', 'User logged out');
        }
        $this->auth->logout();
    }
}
