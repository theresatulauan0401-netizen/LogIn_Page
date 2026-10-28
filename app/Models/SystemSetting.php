<?php

declare(strict_types=1);

namespace App\Models;

final class SystemSetting
{
    public function __construct(private string $key, private ?string $value)
    {
    }

    public static function fromRow(array $row): self
    {
        return new self((string) $row['setting_key'], $row['setting_value'] ?? null);
    }

    public function key(): string { return $this->key; }
    public function value(): ?string { return $this->value; }
}
