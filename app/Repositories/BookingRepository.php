<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Booking;

final class BookingRepository extends Repository
{
    private const FROM = ' FROM bookings b
        JOIN passengers p ON p.id = b.passenger_id
        JOIN schedules s ON s.id = b.schedule_id
        JOIN routes r ON r.id = s.route_id';

    private function where(array $f): array
    {
        $sql = ' WHERE 1=1';
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql .= " AND (b.booking_reference LIKE :q1 OR CONCAT(p.first_name, ' ', p.last_name) LIKE :q2 OR p.passenger_code LIKE :q3 OR p.phone LIKE :q4)";
            $p[':q1'] = $p[':q2'] = $p[':q3'] = $p[':q4'] = $like;
        }
        foreach (['status' => 'b.status', 'schedule_id' => 'b.schedule_id', 'passenger_id' => 'b.passenger_id'] as $key => $col) {
            if (($f[$key] ?? '') !== '') {
                $sql .= " AND $col = :$key";
                $p[":$key"] = $f[$key];
            }
        }
        if (($f['from'] ?? '') !== '') {
            $sql .= ' AND b.booking_date >= :from';
            $p[':from'] = $f['from'] . ' 00:00:00';
        }
        if (($f['to'] ?? '') !== '') {
            $sql .= ' AND b.booking_date <= :to';
            $p[':to'] = $f['to'] . ' 23:59:59';
        }
        return [$sql, $p];
    }

    public function count(array $filters): int
    {
        [$where, $p] = $this->where($filters);
        return (int) $this->run('SELECT COUNT(*)' . self::FROM . $where, $p)->fetchColumn();
    }

    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $p] = $this->where($filters);
        return $this->run(
            "SELECT b.id, b.booking_reference, b.seat_number, b.total_amount, b.status, b.booking_date,
                    CONCAT(p.first_name, ' ', p.last_name) AS passenger_name, p.passenger_code,
                    s.schedule_code, s.departure_datetime, CONCAT(r.origin, ' to ', r.destination) AS route_name,
                    (SELECT pay.payment_status FROM payments pay WHERE pay.booking_id = b.id ORDER BY pay.id DESC LIMIT 1) AS payment_status"
            . self::FROM . $where . ' ORDER BY b.booking_date DESC, b.id DESC LIMIT :limit OFFSET :offset',
            $p + [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    public function find(int $id, bool $forUpdate = false): ?Booking
    {
        $row = $this->run('SELECT * FROM bookings WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $id])->fetch();
        return $row ? Booking::fromRow($row) : null;
    }

    public function scheduleIdOf(int $bookingId): ?int
    {
        $v = $this->run('SELECT schedule_id FROM bookings WHERE id = :id', [':id' => $bookingId])->fetchColumn();
        return $v === false ? null : (int) $v;
    }

    public function detail(int $id): ?array
    {
        $row = $this->run(
            "SELECT b.*, p.passenger_code, p.first_name, p.last_name, p.phone, p.email,
                    s.schedule_code, s.departure_datetime, s.arrival_datetime, s.status AS schedule_status,
                    bu.bus_number, bu.plate_number, bu.bus_type, r.route_code, r.origin, r.destination, u.full_name AS created_by_name
             FROM bookings b
             JOIN passengers p ON p.id = b.passenger_id
             JOIN schedules s ON s.id = b.schedule_id
             JOIN buses bu ON bu.id = s.bus_id
             JOIN routes r ON r.id = s.route_id
             LEFT JOIN users u ON u.id = b.created_by
             WHERE b.id = :id LIMIT 1",
            [':id' => $id]
        )->fetch();
        return $row ?: null;
    }

    /** True if a booking that holds a seat already uses it (locks the matching row). */
    public function seatHeld(int $scheduleId, string $seat): bool
    {
        return $this->run(
            "SELECT id FROM bookings WHERE schedule_id = :s AND seat_number = :seat AND status IN ('pending','confirmed','completed') LIMIT 1 FOR UPDATE",
            [':s' => $scheduleId, ':seat' => $seat]
        )->fetch() !== false;
    }

    public function passengerHoldsSeat(int $passengerId, int $scheduleId): bool
    {
        return (int) $this->run(
            "SELECT COUNT(*) FROM bookings WHERE passenger_id = :p AND schedule_id = :s AND status IN ('pending','confirmed','completed')",
            [':p' => $passengerId, ':s' => $scheduleId]
        )->fetchColumn() > 0;
    }

    public function insert(array $d): int
    {
        $this->run(
            "INSERT INTO bookings (booking_reference, passenger_id, schedule_id, booking_date, seat_number, passenger_count, total_amount, status, notes, created_by)
             VALUES (:ref, :pax, :sch, NOW(), :seat, 1, :total, 'pending', :notes, :by)",
            [':ref' => $d['reference'], ':pax' => $d['passenger_id'], ':sch' => $d['schedule_id'], ':seat' => $d['seat'],
             ':total' => $d['total'], ':notes' => $d['notes'], ':by' => $d['created_by']]
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE bookings SET status = :s WHERE id = :id', [':s' => $status, ':id' => $id]);
    }

    /** @return int[] */
    public function idsBySchedule(int $scheduleId, array $statuses): array
    {
        $marks = implode(',', array_fill(0, count($statuses), '?'));
        $rows = $this->run("SELECT id FROM bookings WHERE schedule_id = ? AND status IN ($marks) ORDER BY id", array_merge([$scheduleId], $statuses))->fetchAll();
        return array_map(static fn ($r) => (int) $r['id'], $rows);
    }
}
