<?php

declare(strict_types=1);

namespace App\Models;

final class Payment
{
    public function __construct(
        private int $id, private string $reference, private int $bookingId, private string $amount,
        private string $method, private string $status, private ?string $transactionReference
    ) {
    }

    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], (string) $r['payment_reference'], (int) $r['booking_id'], (string) $r['amount'],
            (string) $r['payment_method'], (string) $r['payment_status'], $r['transaction_reference'] ?? null);
    }

    public function id(): int { return $this->id; }
    public function reference(): string { return $this->reference; }
    public function bookingId(): int { return $this->bookingId; }
    public function amount(): string { return $this->amount; }
    public function method(): string { return $this->method; }
    public function status(): string { return $this->status; }
    public function transactionReference(): ?string { return $this->transactionReference; }
}
