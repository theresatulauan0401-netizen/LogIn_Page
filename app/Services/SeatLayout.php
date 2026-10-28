<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Seat numbering shared by schedules (seat map) and bookings (seat picker):
 * 4 seats per row, labelled 1A 1B 1C 1D 2A ... The last row may be partial.
 */
final class SeatLayout
{
    public const LETTERS = ['A', 'B', 'C', 'D'];

    /** @return string[] */
    public static function labels(int $capacity): array
    {
        $labels = [];
        for ($i = 0; $i < $capacity; $i++) {
            $labels[] = (intdiv($i, 4) + 1) . self::LETTERS[$i % 4];
        }
        return $labels;
    }

    public static function isValid(string $seat, int $capacity): bool
    {
        return in_array($seat, self::labels($capacity), true);
    }
}
