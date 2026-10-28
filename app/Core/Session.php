<?php

declare(strict_types=1);

namespace App\Core;

/** Secure session wrapper with flash messages, flashed old input and flashed errors. */
final class Session
{
    public function __construct(private array $config)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name((string) ($this->config['name'] ?? 'BRS_SESSION'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $lifetime = (int) ($this->config['lifetime'] ?? 7200);
        if (isset($_SESSION['_last_activity']) && (time() - (int) $_SESSION['_last_activity']) > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last_activity'] = time();

        // Data flashed on the previous request becomes readable now, then disappears.
        $_SESSION['_old_current']    = $_SESSION['_old_next'] ?? [];
        $_SESSION['_errors_current'] = $_SESSION['_errors_next'] ?? [];
        unset($_SESSION['_old_next'], $_SESSION['_errors_next']);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
                'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Queue a message ("success" | "danger" | "warning" | "info") for the next page. */
    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public function pullFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    /** Keep submitted form values (never passwords or tokens) for re-display. */
    public function flashOld(array $input): void
    {
        foreach (['password', 'password_confirmation', 'current_password', '_token'] as $secret) {
            unset($input[$secret]);
        }
        $_SESSION['_old_next'] = $input;
    }

    public function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_old_current'][$key] ?? $default;
    }

    /** @param array<string,string> $errors field => first message */
    public function flashErrors(array $errors): void
    {
        $_SESSION['_errors_next'] = $errors;
    }

    public function error(string $field): ?string
    {
        return $_SESSION['_errors_current'][$field] ?? null;
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $_SESSION['_errors_current'] ?? [];
    }
}
