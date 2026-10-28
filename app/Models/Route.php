<?php

declare(strict_types=1);

namespace App\Models;

final class Route
{
    public function __construct(
        private int $id,
        private string $routeCode,
        private string $origin,
        private string $destination,
        private ?string $distanceKm,
        private ?int $durationMinutes,
        private string $baseFare,
        private string $status
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'], (string) $row['route_code'], (string) $row['origin'], (string) $row['destination'],
            $row['distance_km'] !== null ? (string) $row['distance_km'] : null,
            $row['estimated_duration_minutes'] !== null ? (int) $row['estimated_duration_minutes'] : null,
            (string) $row['base_fare'], (string) $row['status']
        );
    }

    public function id(): int { return $this->id; }
    public function routeCode(): string { return $this->routeCode; }
    public function origin(): string { return $this->origin; }
    public function destination(): string { return $this->destination; }
    public function name(): string { return $this->origin . ' to ' . $this->destination; }
    public function distanceKm(): ?string { return $this->distanceKm; }
    public function durationMinutes(): ?int { return $this->durationMinutes; }
    public function baseFare(): string { return $this->baseFare; }
    public function status(): string { return $this->status; }
    public function isActive(): bool { return $this->status === 'active'; }
}
