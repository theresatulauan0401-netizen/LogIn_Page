<?php

declare(strict_types=1);

namespace App\Models;

/** A staff/administrator account. Encapsulated: state is read through getters. */
final class User
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_BOOKING_STAFF = 'booking_staff';
    public const ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_BOOKING_STAFF];

    public function __construct(
        private int $id,
        private string $fullName,
        private string $email,
        private string $passwordHash,
        private string $role,
        private string $status,
        private ?string $lastLoginAt
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['full_name'],
            (string) $row['email'],
            (string) $row['password'],
            (string) $row['role'],
            (string) $row['status'],
            $row['last_login_at'] ?? null
        );
    }

    public function id(): int { return $this->id; }
    public function fullName(): string { return $this->fullName; }
    public function email(): string { return $this->email; }
    public function passwordHash(): string { return $this->passwordHash; }
    public function role(): string { return $this->role; }
    public function status(): string { return $this->status; }
    public function lastLoginAt(): ?string { return $this->lastLoginAt; }
    public function isActive(): bool { return $this->status === 'active'; }

    /** Shape expected by Auth::login()/refresh(); never includes the password hash. */
    public function toIdentity(): array
    {
        return ['id' => $this->id, 'full_name' => $this->fullName, 'email' => $this->email, 'role' => $this->role];
    }
}
