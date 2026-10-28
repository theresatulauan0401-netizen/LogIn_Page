<?php

declare(strict_types=1);

namespace App\Core;

/** Human-friendly unique references such as BKG-20261027-9F3A1C (uniqueness is still enforced by DB keys). */
final class Reference
{
    public static function make(string $prefix): string
    {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
