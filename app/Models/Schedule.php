<?php

declare(strict_types=1);

namespace App\Models;

final class Schedule
{
    public function __construct(
        private int $id,
        private string $scheduleCode,
        private int $busId,
        private int $routeId,
        private string $departure,
        private string $arrival,
        private string $fare,
        private int $availableSeats,
        private string $status
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'], (string) $row['schedule_code'], (int) $row['bus_id'], (int) $row['route_id'],
            (string) $row['departure_datetime'], (string) $row['arrival_datetime'], (string) $row['fare'],
            (int) $row['available_seats'], (string) $row['status']
        );
    }

    public function id(): int { return $this->id; }
    public function scheduleCode(): string { return $this->scheduleCode; }
    public function busId(): int { return $this->busId; }
    public function routeId(): int { return $this->routeId; }
    public function departure(): string { return $this->departure; }
    public function arrival(): string { return $this->arrival; }
    public function fare(): string { return $this->fare; }
    public function availableSeats(): int { return $this->availableSeats; }
    public function status(): string { return $this->status; }
    public function acceptsBookings(): bool { return in_array($this->status, ['scheduled'], true) && strtotime($this->departure) > time(); }
}
