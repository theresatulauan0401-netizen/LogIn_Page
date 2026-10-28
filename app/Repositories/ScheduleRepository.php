<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Schedule;

final class ScheduleRepository extends Repository
{
    private function where(array $f): array
    {
        $sql = ' WHERE 1=1';
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $sql .= ' AND s.schedule_code LIKE :q';
            $p[':q'] = '%' . addcslashes($f['q'], '%_\\') . '%';
        }
        foreach (['status' => 's.status', 'route_id' => 's.route_id', 'bus_id' => 's.bus_id'] as $key => $col) {
            if (($f[$key] ?? '') !== '') {
                $sql .= " AND $col = :$key";
                $p[":$key"] = $f[$key];
            }
        }
        if (($f['from'] ?? '') !== '') {
            $sql .= ' AND s.departure_datetime >= :from';
            $p[':from'] = $f['from'] . ' 00:00:00';
        }
        if (($f['to'] ?? '') !== '') {
            $sql .= ' AND s.departure_datetime <= :to';
            $p[':to'] = $f['to'] . ' 23:59:59';
        }
        return [$sql, $p];
    }

    public function count(array $filters): int
    {
        [$where, $p] = $this->where($filters);
        return (int) $this->run('SELECT COUNT(*) FROM schedules s' . $where, $p)->fetchColumn();
    }

    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $p] = $this->where($filters);
        return $this->run(
            "SELECT s.*, bu.bus_number, bu.capacity, CONCAT(r.origin, ' to ', r.destination) AS route_name, r.route_code
             FROM schedules s JOIN buses bu ON bu.id = s.bus_id JOIN routes r ON r.id = s.route_id" . $where
            . ' ORDER BY s.departure_datetime DESC, s.id DESC LIMIT :limit OFFSET :offset',
            $p + [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    public function find(int $id, bool $forUpdate = false): ?Schedule
    {
        $row = $this->run('SELECT * FROM schedules WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $id])->fetch();
        return $row ? Schedule::fromRow($row) : null;
    }

    /** Schedule joined with bus and route details for display. */
    public function detail(int $id): ?array
    {
        $row = $this->run(
            "SELECT s.*, bu.bus_number, bu.plate_number, bu.bus_type, bu.capacity, bu.status AS bus_status,
                    r.route_code, r.origin, r.destination, r.estimated_duration_minutes
             FROM schedules s JOIN buses bu ON bu.id = s.bus_id JOIN routes r ON r.id = s.route_id
             WHERE s.id = :id LIMIT 1",
            [':id' => $id]
        )->fetch();
        return $row ?: null;
    }

    /** First other non-cancelled trip of the bus whose time range overlaps [dep, arr). */
    public function findOverlap(int $busId, string $dep, string $arr, ?int $exceptId = null): ?array
    {
        $row = $this->run(
            "SELECT id, schedule_code, departure_datetime, arrival_datetime FROM schedules
             WHERE bus_id = :bus AND status <> 'cancelled' AND id <> :ex
               AND departure_datetime < :arr AND arrival_datetime > :dep
             LIMIT 1",
            [':bus' => $busId, ':ex' => $exceptId ?? 0, ':arr' => $arr, ':dep' => $dep]
        )->fetch();
        return $row ?: null;
    }

    /** Bookings that still need the trip to run (pending or confirmed). */
    public function activeBookingCount(int $scheduleId): int
    {
        return (int) $this->run(
            "SELECT COUNT(*) FROM bookings WHERE schedule_id = :id AND status IN ('pending','confirmed')",
            [':id' => $scheduleId]
        )->fetchColumn();
    }

    /** Bookings that hold a seat (pending, confirmed, completed). */
    public function seatHoldingCount(int $scheduleId): int
    {
        return (int) $this->run(
            "SELECT COUNT(*) FROM bookings WHERE schedule_id = :id AND status IN ('pending','confirmed','completed')",
            [':id' => $scheduleId]
        )->fetchColumn();
    }

    /** @return array<string,string> seat_number => booking status, for seats currently held */
    public function heldSeats(int $scheduleId): array
    {
        $rows = $this->run(
            "SELECT seat_number, status FROM bookings WHERE schedule_id = :id AND status IN ('pending','confirmed','completed')",
            [':id' => $scheduleId]
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['seat_number']] = $r['status'];
        }
        return $out;
    }

    public function codeTaken(string $code): bool
    {
        return (int) $this->run('SELECT COUNT(*) FROM schedules WHERE schedule_code = :c', [':c' => $code])->fetchColumn() > 0;
    }

    public function insert(string $code, array $d): int
    {
        $this->run(
            'INSERT INTO schedules (schedule_code, bus_id, route_id, departure_datetime, arrival_datetime, fare, available_seats, status)
             VALUES (:code, :bus, :route, :dep, :arr, :fare, :seats, :status)',
            [':code' => $code, ':bus' => $d['bus_id'], ':route' => $d['route_id'], ':dep' => $d['departure'], ':arr' => $d['arrival'],
             ':fare' => $d['fare'], ':seats' => $d['available_seats'], ':status' => 'scheduled']
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE schedules SET bus_id = :bus, route_id = :route, departure_datetime = :dep, arrival_datetime = :arr,
                    fare = :fare, available_seats = :seats WHERE id = :id',
            [':bus' => $d['bus_id'], ':route' => $d['route_id'], ':dep' => $d['departure'], ':arr' => $d['arrival'],
             ':fare' => $d['fare'], ':seats' => $d['available_seats'], ':id' => $id]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE schedules SET status = :s WHERE id = :id', [':s' => $status, ':id' => $id]);
    }

    /**
     * Locks the schedule row (FOR UPDATE) and returns it with the bus capacity.
     * ALL seat-changing code locks the schedule FIRST, then bookings, to avoid deadlocks.
     * (The capacity sub-select is intentionally not locked.)
     */
    public function lockWithCapacity(int $id): ?array
    {
        $row = $this->run(
            'SELECT s.*, (SELECT capacity FROM buses WHERE id = s.bus_id) AS capacity
             FROM schedules s WHERE s.id = :id FOR UPDATE',
            [':id' => $id]
        )->fetch();
        return $row ?: null;
    }

    /** Atomically takes one seat; false when none are left. */
    public function takeSeat(int $id): bool
    {
        return $this->run(
            'UPDATE schedules SET available_seats = available_seats - 1 WHERE id = :id AND available_seats > 0',
            [':id' => $id]
        )->rowCount() === 1;
    }

    /** Returns one seat, never exceeding the bus capacity. */
    public function releaseSeat(int $id): void
    {
        $this->run(
            'UPDATE schedules SET available_seats = LEAST(available_seats + 1, (SELECT capacity FROM buses WHERE id = schedules.bus_id))
             WHERE id = :id',
            [':id' => $id]
        );
    }

    /** Future, still-scheduled trips on a route that have free seats. */
    public function bookableForRoute(int $routeId): array
    {
        return $this->run(
            "SELECT s.id, s.schedule_code, s.departure_datetime, s.arrival_datetime, s.fare, s.available_seats, bu.bus_number, bu.bus_type
             FROM schedules s JOIN buses bu ON bu.id = s.bus_id
             WHERE s.route_id = :r AND s.status = 'scheduled' AND s.departure_datetime > NOW() AND s.available_seats > 0
             ORDER BY s.departure_datetime ASC LIMIT 50",
            [':r' => $routeId]
        )->fetchAll();
    }
}
