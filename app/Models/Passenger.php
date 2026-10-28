<?php

declare(strict_types=1);

namespace App\Models;

final class Passenger
{
    public function __construct(
        private int $id, private string $code, private string $firstName, private string $lastName,
        private ?string $email, private string $phone, private ?string $gender, private ?string $dateOfBirth, private string $status
    ) {
    }

    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], (string) $r['passenger_code'], (string) $r['first_name'], (string) $r['last_name'],
            $r['email'] ?? null, (string) $r['phone'], $r['gender'] ?? null, $r['date_of_birth'] ?? null, (string) $r['status']);
    }

    public function id(): int { return $this->id; }
    public function code(): string { return $this->code; }
    public function firstName(): string { return $this->firstName; }
    public function lastName(): string { return $this->lastName; }
    public function fullName(): string { return $this->firstName . ' ' . $this->lastName; }
    public function email(): ?string { return $this->email; }
    public function phone(): string { return $this->phone; }
    public function gender(): ?string { return $this->gender; }
    public function dateOfBirth(): ?string { return $this->dateOfBirth; }
    public function status(): string { return $this->status; }
    public function isActive(): bool { return $this->status === 'active'; }
}
