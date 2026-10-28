<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Payment;

final class PaymentRepository extends Repository
{
    private const FROM = ' FROM payments pay
        JOIN bookings b ON b.id = pay.booking_id
        JOIN passengers p ON p.id = b.passenger_id';

    private function where(array $f): array
    {
        $sql = ' WHERE 1=1';
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql .= ' AND (pay.payment_reference LIKE :q1 OR b.booking_reference LIKE :q2 OR pay.transaction_reference LIKE :q3)';
            $p[':q1'] = $p[':q2'] = $p[':q3'] = $like;
        }
        foreach (['status' => 'pay.payment_status', 'method' => 'pay.payment_method', 'booking_id' => 'pay.booking_id'] as $key => $col) {
            if (($f[$key] ?? '') !== '') {
                $sql .= " AND $col = :$key";
                $p[":$key"] = $f[$key];
            }
        }
        if (($f['from'] ?? '') !== '') {
            $sql .= ' AND COALESCE(pay.paid_at, pay.created_at) >= :from';
            $p[':from'] = $f['from'] . ' 00:00:00';
        }
        if (($f['to'] ?? '') !== '') {
            $sql .= ' AND COALESCE(pay.paid_at, pay.created_at) <= :to';
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
            "SELECT pay.id, pay.payment_reference, pay.amount, pay.payment_method, pay.payment_status, pay.transaction_reference,
                    pay.paid_at, pay.created_at, b.id AS booking_id, b.booking_reference, CONCAT(p.first_name, ' ', p.last_name) AS passenger_name"
            . self::FROM . $where . ' ORDER BY pay.id DESC LIMIT :limit OFFSET :offset',
            $p + [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    public function find(int $id, bool $forUpdate = false): ?Payment
    {
        $row = $this->run('SELECT * FROM payments WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $id])->fetch();
        return $row ? Payment::fromRow($row) : null;
    }

    public function bookingIdOf(int $paymentId): ?int
    {
        $v = $this->run('SELECT booking_id FROM payments WHERE id = :id', [':id' => $paymentId])->fetchColumn();
        return $v === false ? null : (int) $v;
    }

    public function detail(int $id): ?array
    {
        $row = $this->run(
            "SELECT pay.*, b.booking_reference, b.status AS booking_status, b.total_amount, b.seat_number,
                    p.passenger_code, p.first_name, p.last_name, s.schedule_code, s.departure_datetime,
                    r.origin, r.destination, u.full_name AS received_by_name, ru.full_name AS refunded_by_name
             FROM payments pay
             JOIN bookings b ON b.id = pay.booking_id
             JOIN passengers p ON p.id = b.passenger_id
             JOIN schedules s ON s.id = b.schedule_id
             JOIN routes r ON r.id = s.route_id
             LEFT JOIN users u ON u.id = pay.received_by
             LEFT JOIN users ru ON ru.id = pay.refunded_by
             WHERE pay.id = :id LIMIT 1",
            [':id' => $id]
        )->fetch();
        return $row ?: null;
    }

    public function forBooking(int $bookingId): array
    {
        return $this->run(
            'SELECT pay.*, u.full_name AS received_by_name FROM payments pay LEFT JOIN users u ON u.id = pay.received_by
             WHERE pay.booking_id = :id ORDER BY pay.id ASC',
            [':id' => $bookingId]
        )->fetchAll();
    }

    public function successfulForBooking(int $bookingId): ?array
    {
        $row = $this->run("SELECT * FROM payments WHERE booking_id = :id AND payment_status = 'successful' LIMIT 1", [':id' => $bookingId])->fetch();
        return $row ?: null;
    }

    public function hasPending(int $bookingId): bool
    {
        return (int) $this->run("SELECT COUNT(*) FROM payments WHERE booking_id = :id AND payment_status = 'pending'", [':id' => $bookingId])->fetchColumn() > 0;
    }

    public function successTxnTaken(string $reference, ?int $exceptId = null): bool
    {
        return (int) $this->run(
            "SELECT COUNT(*) FROM payments WHERE payment_status = 'successful' AND transaction_reference = :t AND id <> :id",
            [':t' => $reference, ':id' => $exceptId ?? 0]
        )->fetchColumn() > 0;
    }

    public function insert(array $d): int
    {
        $this->run(
            'INSERT INTO payments (payment_reference, booking_id, amount, payment_method, payment_status, transaction_reference, paid_at, received_by)
             VALUES (:ref, :booking, :amount, :method, :status, :txn, :paid_at, :by)',
            [':ref' => $d['reference'], ':booking' => $d['booking_id'], ':amount' => $d['amount'], ':method' => $d['method'],
             ':status' => $d['status'], ':txn' => $d['transaction_reference'], ':paid_at' => $d['paid_at'], ':by' => $d['received_by']]
        );
        return (int) $this->pdo->lastInsertId();
    }

    /** pending -> successful (false if it was no longer pending). */
    public function markSuccessful(int $id, ?string $transactionReference, ?int $userId): bool
    {
        return $this->run(
            "UPDATE payments SET payment_status = 'successful', transaction_reference = :t, paid_at = NOW(), received_by = :u
             WHERE id = :id AND payment_status = 'pending'",
            [':t' => $transactionReference, ':u' => $userId, ':id' => $id]
        )->rowCount() === 1;
    }

    public function markFailed(int $id, ?int $userId): bool
    {
        return $this->run(
            "UPDATE payments SET payment_status = 'failed', received_by = :u WHERE id = :id AND payment_status = 'pending'",
            [':u' => $userId, ':id' => $id]
        )->rowCount() === 1;
    }

    public function failPendingForBooking(int $bookingId, ?int $userId): void
    {
        $this->run("UPDATE payments SET payment_status = 'failed', received_by = :u WHERE booking_id = :id AND payment_status = 'pending'", [':u' => $userId, ':id' => $bookingId]);
    }

    /** successful -> refunded, kept as an auditable record. */
    public function markRefunded(int $id, ?int $userId, string $reason): bool
    {
        return $this->run(
            "UPDATE payments SET payment_status = 'refunded', refunded_at = NOW(), refunded_by = :u, refund_reason = :r
             WHERE id = :id AND payment_status = 'successful'",
            [':u' => $userId, ':r' => mb_substr($reason, 0, 1000), ':id' => $id]
        )->rowCount() === 1;
    }
}
