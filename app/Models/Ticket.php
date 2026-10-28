<?php

declare(strict_types=1);

namespace App\Models;

final class Ticket
{
    public function __construct(private int $id, private string $number, private int $bookingId, private string $status)
    {
    }

    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], (string) $r['ticket_number'], (int) $r['booking_id'], (string) $r['ticket_status']);
    }

    public function id(): int { return $this->id; }
    public function number(): string { return $this->number; }
    public function bookingId(): int { return $this->bookingId; }
    public function status(): string { return $this->status; }
    public function isValid(): bool { return $this->status === 'valid'; }
}
