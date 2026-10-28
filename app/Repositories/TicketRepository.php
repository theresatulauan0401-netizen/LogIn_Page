<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Ticket;

final class TicketRepository extends Repository
{
    private const FROM = ' FROM tickets t
        JOIN bookings b ON b.id = t.booking_id
        JOIN passengers p ON p.id = b.passenger_id
        JOIN schedules s ON s.id = b.schedule_id
        JOIN routes r ON r.id = s.route_id';

    private function where(array $f): array
    {
        $sql = ' WHERE 1=1';
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql .= " AND (t.ticket_number LIKE :q1 OR b.booking_reference LIKE :q2 OR CONCAT(p.first_name, ' ', p.last_name) LIKE :q3)";
            $p[':q1'] = $p[':q2'] = $p[':q3'] = $like;
        }
        if (($f['status'] ?? '') !== '') {
            $sql .= ' AND t.ticket_status = :status';
            $p[':status'] = $f['status'];
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
            "SELECT t.id, t.ticket_number, t.ticket_status, t.issued_at, b.id AS booking_id, b.booking_reference, b.seat_number,
                    CONCAT(p.first_name, ' ', p.last_name) AS passenger_name, s.departure_datetime, CONCAT(r.origin, ' to ', r.destination) AS route_name"
            . self::FROM . $where . ' ORDER BY t.id DESC LIMIT :limit OFFSET :offset',
            $p + [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    public function find(int $id, bool $forUpdate = false): ?Ticket
    {
        $row = $this->run('SELECT * FROM tickets WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $id])->fetch();
        return $row ? Ticket::fromRow($row) : null;
    }

    public function findByBooking(int $bookingId, bool $forUpdate = false): ?Ticket
    {
        $row = $this->run('SELECT * FROM tickets WHERE booking_id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $bookingId])->fetch();
        return $row ? Ticket::fromRow($row) : null;
    }

    public function findIdByNumber(string $number): ?int
    {
        $v = $this->run('SELECT id FROM tickets WHERE ticket_number = :n LIMIT 1', [':n' => $number])->fetchColumn();
        return $v === false ? null : (int) $v;
    }

    /** Ticket joined with everything shown on the printed ticket. */
    public function detail(int $id): ?array
    {
        $row = $this->run(
            "SELECT t.*, b.booking_reference, b.seat_number, b.total_amount, b.status AS booking_status,
                    p.passenger_code, p.first_name, p.last_name, p.phone,
                    s.schedule_code, s.departure_datetime, s.arrival_datetime, s.status AS schedule_status,
                    bu.bus_number, bu.plate_number, bu.bus_type, r.route_code, r.origin, r.destination, u.full_name AS used_by_name
             FROM tickets t
             JOIN bookings b ON b.id = t.booking_id
             JOIN passengers p ON p.id = b.passenger_id
             JOIN schedules s ON s.id = b.schedule_id
             JOIN buses bu ON bu.id = s.bus_id
             JOIN routes r ON r.id = s.route_id
             LEFT JOIN users u ON u.id = t.used_by
             WHERE t.id = :id LIMIT 1",
            [':id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public function insert(string $number, int $bookingId): int
    {
        $this->run("INSERT INTO tickets (ticket_number, booking_id, issued_at, ticket_status) VALUES (:n, :b, NOW(), 'valid')", [':n' => $number, ':b' => $bookingId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function markUsed(int $id, ?int $userId): bool
    {
        return $this->run(
            "UPDATE tickets SET ticket_status = 'used', used_at = NOW(), used_by = :u WHERE id = :id AND ticket_status = 'valid'",
            [':u' => $userId, ':id' => $id]
        )->rowCount() === 1;
    }

    public function cancelValidForBooking(int $bookingId): void
    {
        $this->run("UPDATE tickets SET ticket_status = 'cancelled' WHERE booking_id = :id AND ticket_status = 'valid'", [':id' => $bookingId]);
    }

    public function expireValidForBooking(int $bookingId): void
    {
        $this->run("UPDATE tickets SET ticket_status = 'expired' WHERE booking_id = :id AND ticket_status = 'valid'", [':id' => $bookingId]);
    }
}
