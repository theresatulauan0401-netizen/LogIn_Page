<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Route;

final class RouteRepository extends Repository
{
    private function where(array $f): array
    {
        $sql = ' WHERE 1=1';
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql .= ' AND (r.route_code LIKE :q1 OR r.origin LIKE :q2 OR r.destination LIKE :q3)';
            $p[':q1'] = $p[':q2'] = $p[':q3'] = $like;
        }
        if (($f['status'] ?? '') !== '') {
            $sql .= ' AND r.status = :status';
            $p[':status'] = $f['status'];
        }
        return [$sql, $p];
    }

    public function count(array $filters): int
    {
        [$where, $p] = $this->where($filters);
        return (int) $this->run('SELECT COUNT(*) FROM routes r' . $where, $p)->fetchColumn();
    }

    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $p] = $this->where($filters);
        return $this->run(
            "SELECT r.*, (SELECT COUNT(*) FROM schedules s WHERE s.route_id = r.id AND s.status IN ('scheduled','boarding')) AS upcoming_trips
             FROM routes r" . $where . ' ORDER BY r.route_code ASC LIMIT :limit OFFSET :offset',
            $p + [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    public function find(int $id, bool $forUpdate = false): ?Route
    {
        $row = $this->run('SELECT * FROM routes WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $id])->fetch();
        return $row ? Route::fromRow($row) : null;
    }

    public function codeTaken(string $code): bool
    {
        return (int) $this->run('SELECT COUNT(*) FROM routes WHERE route_code = :c', [':c' => $code])->fetchColumn() > 0;
    }

    /** Highest numeric suffix of RT-### codes, so the next code is max+1. */
    public function maxCodeNumber(): int
    {
        return (int) $this->run("SELECT COALESCE(MAX(CAST(SUBSTRING(route_code, 4) AS UNSIGNED)), 0) FROM routes WHERE route_code REGEXP '^RT-[0-9]+$'")->fetchColumn();
    }

    public function hasOpenSchedules(int $routeId): bool
    {
        return (int) $this->run(
            "SELECT COUNT(*) FROM schedules WHERE route_id = :id AND status IN ('scheduled','boarding')",
            [':id' => $routeId]
        )->fetchColumn() > 0;
    }

    public function insert(string $code, array $d): int
    {
        $this->run(
            'INSERT INTO routes (route_code, origin, destination, distance_km, estimated_duration_minutes, base_fare, status)
             VALUES (:code, :origin, :destination, :distance, :duration, :fare, :status)',
            [':code' => $code] + $this->params($d)
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE routes SET origin = :origin, destination = :destination, distance_km = :distance,
                    estimated_duration_minutes = :duration, base_fare = :fare, status = :status WHERE id = :id',
            $this->params($d) + [':id' => $id]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE routes SET status = :s WHERE id = :id', [':s' => $status, ':id' => $id]);
    }

    public function activeOptions(): array
    {
        $out = [];
        foreach ($this->run("SELECT id, route_code, origin, destination, base_fare FROM routes WHERE status = 'active' ORDER BY route_code")->fetchAll() as $r) {
            $out[(int) $r['id']] = "{$r['route_code']} - {$r['origin']} to {$r['destination']} (" . format_money($r['base_fare']) . ')';
        }
        return $out;
    }

    public function upcomingSchedules(int $routeId, int $limit = 10): array
    {
        return $this->run(
            "SELECT s.id, s.schedule_code, s.departure_datetime, s.status, bu.bus_number
             FROM schedules s JOIN buses bu ON bu.id = s.bus_id
             WHERE s.route_id = :id AND s.status IN ('scheduled','boarding')
             ORDER BY s.departure_datetime LIMIT :n",
            [':id' => $routeId, ':n' => $limit]
        )->fetchAll();
    }

    private function params(array $d): array
    {
        return [
            ':origin' => $d['origin'], ':destination' => $d['destination'],
            ':distance' => $d['distance_km'], ':duration' => $d['estimated_duration_minutes'],
            ':fare' => $d['base_fare'], ':status' => $d['status'],
        ];
    }
}
