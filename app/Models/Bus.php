<?php

declare(strict_types=1);

namespace App\Models;

final class Bus
{
    /** @param string[] $amenities */
    public function __construct(
        private int $id,
        private string $busNumber,
        private string $plateNumber,
        private string $busType,
        private ?string $busModel,
        private int $capacity,
        private array $amenities,
        private string $status
    ) {
    }

    public static function fromRow(array $row): self
    {
        $amenities = [];
        if (!empty($row['amenities'])) {
            $decoded = json_decode((string) $row['amenities'], true);
            $amenities = is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
        }
        return new self(
            (int) $row['id'], (string) $row['bus_number'], (string) $row['plate_number'], (string) $row['bus_type'],
            $row['bus_model'] ?? null, (int) $row['capacity'], $amenities, (string) $row['status']
        );
    }

    public function id(): int { return $this->id; }
    public function busNumber(): string { return $this->busNumber; }
    public function plateNumber(): string { return $this->plateNumber; }
    public function busType(): string { return $this->busType; }
    public function busModel(): ?string { return $this->busModel; }
    public function capacity(): int { return $this->capacity; }
    /** @return string[] */
    public function amenities(): array { return $this->amenities; }
    public function status(): string { return $this->status; }
    public function isActive(): bool { return $this->status === 'active'; }
}
