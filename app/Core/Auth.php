<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session-based authentication and role-based authorization.
 * Only a minimal identity (id, name, email, role) lives in the session; the
 * password hash never does. AuthMiddleware re-checks the account is still
 * active on every request (added with the user repository in Part 2).
 */
final class Auth
{
    private const KEY = '_auth_user';

    /** @param array<string,string[]> $permissions role => permission list */
    public function __construct(private Session $session, private array $permissions)
    {
    }

    /** @param array{id:int|string,full_name:string,email:string,role:string} $user */
    public function login(array $user): void
    {
        $this->session->regenerate(); // prevents session fixation
        $this->session->set(self::KEY, [
            'id'    => (int) $user['id'],
            'name'  => (string) $user['full_name'],
            'email' => (string) $user['email'],
            'role'  => (string) $user['role'],
        ]);
        $this->session->forget('_csrf_token'); // fresh CSRF token for the new login
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    /** Re-sync identity from the database (role/name changes) WITHOUT regenerating the session. */
    public function refresh(array $user): void
    {
        $this->session->set(self::KEY, [
            'id'    => (int) $user['id'],
            'name'  => (string) $user['full_name'],
            'email' => (string) $user['email'],
            'role'  => (string) $user['role'],
        ]);
    }

    public function check(): bool
    {
        return is_array($this->session->get(self::KEY));
    }

    /** @return array{id:int,name:string,email:string,role:string}|null */
    public function user(): ?array
    {
        $user = $this->session->get(self::KEY);
        return is_array($user) ? $user : null;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->role() !== null && in_array($this->role(), $roles, true);
    }

    /** Wildcards: "*" (all) and "module.*". */
    public function can(string $permission): bool
    {
        $role = $this->role();
        if ($role === null) {
            return false;
        }
        foreach ($this->permissions[$role] ?? [] as $granted) {
            if ($granted === '*' || $granted === $permission) {
                return true;
            }
            if (str_ends_with($granted, '.*') && str_starts_with($permission, substr($granted, 0, -1))) {
                return true;
            }
        }
        return false;
    }

    /** Throws 403 unless the current user holds the permission. Call at the top of every protected action. */
    public function authorize(string $permission): void
    {
        if (!$this->can($permission)) {
            throw new HttpException(403);
        }
    }
}
