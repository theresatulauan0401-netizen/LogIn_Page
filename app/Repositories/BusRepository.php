<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Bus;

final class BusRepository extends Repository
{
    /** @return array{0:string,1:array<string,mixed>} */
    private function where(array $f): array
    {
        $sql = ' WHERE 1=1';
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql .= ' AND (b.bus_number LIKE :q1 OR b.plate_number LIKE :q2 OR b.bus_type LIKE :q3)';
            $p[':q1'] = $p[':q2'] = $p[':q3'] = $like;
        }
        if (($f['status'] ?? '') !== '') {
            $sql .= ' AND b.status = :status';
            $p[':status'] = $f['status'];
        }
        return [$sql, $p];
    }

    public function count(array $filters): int
    {
        [$where, $p] = $this->where($filters);
        return (int) $this->run('SELECT COUNT(*) FROM buses b' . $where, $p)->fetchColumn();
    }

    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $p] = $this->where($filters);
        return $this->run(
            "SELECT b.*, (SELECT COUNT(*) FROM schedules s WHERE s.bus_id = b.id AND s.status IN ('scheduled','boarding')) AS upcoming_trips
             FROM buses b" . $where . ' ORDER BY b.bus_number ASC LIMIT :limit OFFSET :offset',
            $p + [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    public function find(int $id, bool $forUpdate = false): ?Bus
    {
        $row = $this->run('SELECT * FROM buses WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $id])->fetch();
        return $row ? Bus::fromRow($row) : null;
    }

    public function busNumberTaken(string $number, ?int $exceptId = null): bool
    {
        return (int) $this->run('SELECT COUNT(*) FROM buses WHERE bus_number = :v AND id <> :id', [':v' => $number, ':id' => $exceptId ?? 0])->fetchColumn() > 0;
    }

    public function plateTaken(string $plate, ?int $exceptId = null): bool
    {
        return (int) $this->run('SELECT COUNT(*) FROM buses WHERE plate_number = :v AND id <> :id', [':v' => $plate, ':id' => $exceptId ?? 0])->fetchColumn() > 0;
    }

    public function hasOpenSchedules(int $busId): bool
    {
        return (int) $this->run(
            "SELECT COUNT(*) FROM schedules WHERE bus_id = :id AND status IN ('scheduled','boarding')",
            [':id' => $busId]
        )->fetchColumn() > 0;
    }

    public function insert(array $d): int
    {
        $this->run(
            'INSERT INTO buses (bus_number, plate_number, bus_type, bus_model, capacity, amenities, status)
             VALUES (:bus_number, :plate_number, :bus_type, :bus_model, :capacity, :amenities, :status)',
            $this->params($d)
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE buses SET bus_number = :bus_number, plate_number = :plate_number, bus_type = :bus_type,
                    bus_model = :bus_model, capacity = :capacity, amenities = :amenities, status = :status
             WHERE id = :id',
            $this->params($d) + [':id' => $id]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE buses SET status = :s WHERE id = :id', [':s' => $status, ':id' => $id]);
    }

    /** Buses that can be assigned to a new trip: id => label. */
    public function activeOptions(): array
    {
        $out = [];
        foreach ($this->run("SELECT id, bus_number, bus_type, capacity FROM buses WHERE status = 'active' ORDER BY bus_number")->fetchAll() as $r) {
            $out[(int) $r['id']] = "{$r['bus_number']} - {$r['bus_type']} ({$r['capacity']} seats)";
        }
        return $out;
    }

    public function upcomingSchedules(int $busId, int $limit = 10): array
    {
        return $this->run(
            "SELECT s.id, s.schedule_code, s.departure_datetime, s.status, CONCAT(r.origin, ' to ', r.destination) AS route_name
             FROM schedules s JOIN routes r ON r.id = s.route_id
             WHERE s.bus_id = :id AND s.status IN ('scheduled','boarding')
             ORDER BY s.departure_datetime LIMIT :n",
            [':id' => $busId, ':n' => $limit]
        )->fetchAll();
    }

    private function params(array $d): array
    {
        return [
            ':bus_number' => $d['bus_number'], ':plate_number' => $d['plate_number'], ':bus_type' => $d['bus_type'],
            ':bus_model' => $d['bus_model'], ':capacity' => (int) $d['capacity'],
            ':amenities' => $d['amenities'] ? json_encode(array_values($d['amenities']), JSON_UNESCAPED_UNICODE) : null,
            ':status' => $d['status'],
        ];
    }
}
