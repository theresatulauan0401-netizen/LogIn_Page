<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Passenger;

final class PassengerRepository extends Repository
{
    private function where(array $f): array
    {
        $sql = ' WHERE 1=1';
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE :q1 OR p.passenger_code LIKE :q2 OR p.email LIKE :q3 OR p.phone LIKE :q4)";
            $p[':q1'] = $p[':q2'] = $p[':q3'] = $p[':q4'] = $like;
        }
        if (($f['status'] ?? '') !== '') {
            $sql .= ' AND p.status = :status';
            $p[':status'] = $f['status'];
        }
        return [$sql, $p];
    }

    public function count(array $filters): int
    {
        [$where, $p] = $this->where($filters);
        return (int) $this->run('SELECT COUNT(*) FROM passengers p' . $where, $p)->fetchColumn();
    }

    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $p] = $this->where($filters);
        return $this->run(
            "SELECT p.*, (SELECT COUNT(*) FROM bookings b WHERE b.passenger_id = p.id) AS booking_count
             FROM passengers p" . $where . ' ORDER BY p.last_name, p.first_name LIMIT :limit OFFSET :offset',
            $p + [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    public function find(int $id, bool $forUpdate = false): ?Passenger
    {
        $row = $this->run('SELECT * FROM passengers WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $id])->fetch();
        return $row ? Passenger::fromRow($row) : null;
    }

    public function duplicateExists(string $first, string $last, string $phone, ?int $exceptId = null): bool
    {
        return (int) $this->run(
            'SELECT COUNT(*) FROM passengers WHERE first_name = :f AND last_name = :l AND phone = :p AND id <> :id',
            [':f' => $first, ':l' => $last, ':p' => $phone, ':id' => $exceptId ?? 0]
        )->fetchColumn() > 0;
    }

    public function maxCodeNumber(): int
    {
        return (int) $this->run("SELECT COALESCE(MAX(CAST(SUBSTRING(passenger_code, 5) AS UNSIGNED)), 0) FROM passengers WHERE passenger_code REGEXP '^PAX-[0-9]+$'")->fetchColumn();
    }

    public function insert(string $code, array $d): int
    {
        $this->run(
            'INSERT INTO passengers (passenger_code, first_name, last_name, email, phone, gender, date_of_birth, status)
             VALUES (:code, :first, :last, :email, :phone, :gender, :dob, :status)',
            [':code' => $code] + $this->params($d) + [':status' => 'active']
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $this->run(
            'UPDATE passengers SET first_name = :first, last_name = :last, email = :email, phone = :phone, gender = :gender, date_of_birth = :dob WHERE id = :id',
            $this->params($d) + [':id' => $id]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE passengers SET status = :s WHERE id = :id', [':s' => $status, ':id' => $id]);
    }

    public function activeBookingCount(int $id): int
    {
        return (int) $this->run("SELECT COUNT(*) FROM bookings WHERE passenger_id = :id AND status IN ('pending','confirmed')", [':id' => $id])->fetchColumn();
    }

    /** Active passengers for the booking form: id => "PAX-0001 - Name (phone)". */
    public function activeOptions(int $limit = 500): array
    {
        $out = [];
        $rows = $this->run(
            "SELECT id, passenger_code, first_name, last_name, phone FROM passengers WHERE status = 'active' ORDER BY last_name, first_name LIMIT :n",
            [':n' => $limit]
        )->fetchAll();
        foreach ($rows as $r) {
            $out[(int) $r['id']] = "{$r['passenger_code']} - {$r['last_name']}, {$r['first_name']} ({$r['phone']})";
        }
        return $out;
    }

    public function history(int $id, int $limit = 50): array
    {
        return $this->run(
            "SELECT b.id, b.booking_reference, b.seat_number, b.total_amount, b.status, s.schedule_code, s.departure_datetime,
                    CONCAT(r.origin, ' to ', r.destination) AS route_name
             FROM bookings b JOIN schedules s ON s.id = b.schedule_id JOIN routes r ON r.id = s.route_id
             WHERE b.passenger_id = :id ORDER BY b.booking_date DESC, b.id DESC LIMIT :n",
            [':id' => $id, ':n' => $limit]
        )->fetchAll();
    }

    private function params(array $d): array
    {
        return [':first' => $d['first_name'], ':last' => $d['last_name'], ':email' => $d['email'], ':phone' => $d['phone'],
                ':gender' => $d['gender'], ':dob' => $d['date_of_birth']];
    }
}
