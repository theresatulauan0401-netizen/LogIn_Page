<?php

declare(strict_types=1);

namespace App\Repositories;

final class BookingStatusLogRepository extends Repository
{
    public function create(int $bookingId, ?string $old, string $new, ?int $userId, ?string $remarks): void
    {
        $this->run(
            'INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, remarks)
             VALUES (:b, :old, :new, :u, :r)',
            [':b' => $bookingId, ':old' => $old, ':new' => $new, ':u' => $userId, ':r' => $remarks !== null ? mb_substr($remarks, 0, 2000) : null]
        );
    }

    public function forBooking(int $bookingId): array
    {
        return $this->run(
            'SELECT l.*, u.full_name AS changed_by_name FROM booking_status_logs l
             LEFT JOIN users u ON u.id = l.changed_by WHERE l.booking_id = :id ORDER BY l.id ASC',
            [':id' => $bookingId]
        )->fetchAll();
    }
}
