<?php

declare(strict_types=1);

namespace App\Models;

final class Booking
{
    public function __construct(
        private int $id, private string $reference, private int $passengerId, private int $scheduleId,
        private string $seat, private string $totalAmount, private string $status
    ) {
    }

    public static function fromRow(array $r): self
    {
        return new self((int) $r['id'], (string) $r['booking_reference'], (int) $r['passenger_id'], (int) $r['schedule_id'],
            (string) $r['seat_number'], (string) $r['total_amount'], (string) $r['status']);
    }

    public function id(): int { return $this->id; }
    public function reference(): string { return $this->reference; }
    public function passengerId(): int { return $this->passengerId; }
    public function scheduleId(): int { return $this->scheduleId; }
    public function seat(): string { return $this->seat; }
    public function totalAmount(): string { return $this->totalAmount; }
    public function status(): string { return $this->status; }
    public function holdsSeat(): bool { return in_array($this->status, ['pending', 'confirmed', 'completed'], true); }
}
