<?php

declare(strict_types=1);

namespace App\Repositories;

/** Read-only report queries. Period expressions come from a fixed whitelist, never from user input. */
final class ReportRepository extends Repository
{
    private const PERIOD = [
        'day'   => "DATE_FORMAT(%s, '%%Y-%%m-%%d')",
        'week'  => "DATE_FORMAT(%s, '%%x-W%%v')",
        'month' => "DATE_FORMAT(%s, '%%Y-%%m')",
    ];

    private function period(string $group, string $column): string
    {
        return sprintf(self::PERIOD[$group] ?? self::PERIOD['day'], $column);
    }

    public function bookingsByPeriod(string $group, string $from, string $to, string $status): array
    {
        $expr = $this->period($group, 'b.booking_date');
        $params = [':from' => $from, ':to' => $to];
        $extra = '';
        if ($status !== '') {
            $extra = ' AND b.status = :status';
            $params[':status'] = $status;
        }
        return $this->run(
            "SELECT $expr AS period_label, COUNT(*) AS total,
                    SUM(b.status = 'pending') AS pending, SUM(b.status = 'confirmed') AS confirmed, SUM(b.status = 'completed') AS completed,
                    SUM(b.status = 'cancelled') AS cancelled, SUM(b.status = 'no_show') AS no_show,
                    COALESCE(SUM(b.total_amount), 0) AS booked_value
             FROM bookings b WHERE b.booking_date BETWEEN :from AND :to $extra
             GROUP BY period_label ORDER BY period_label",
            $params
        )->fetchAll();
    }

    public function revenueByPeriod(string $group, string $from, string $to): array
    {
        $expr = $this->period($group, 'p.paid_at');
        return $this->run(
            "SELECT $expr AS period_label, COUNT(*) AS payments,
                    SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END) AS cash,
                    SUM(CASE WHEN p.payment_method = 'bank_transfer' THEN p.amount ELSE 0 END) AS bank_transfer,
                    SUM(CASE WHEN p.payment_method = 'e_wallet' THEN p.amount ELSE 0 END) AS e_wallet,
                    SUM(CASE WHEN p.payment_method = 'card' THEN p.amount ELSE 0 END) AS card,
                    SUM(p.amount) AS revenue
             FROM payments p WHERE p.payment_status = 'successful' AND p.paid_at BETWEEN :from AND :to
             GROUP BY period_label ORDER BY period_label",
            [':from' => $from, ':to' => $to]
        )->fetchAll();
    }

    /** Trips are counted by departure date; revenue = successful payments of bookings on those trips. */
    public function routePerformance(string $from, string $to): array
    {
        return $this->run(
            "SELECT r.route_code, CONCAT(r.origin, ' to ', r.destination) AS route_name,
              (SELECT COUNT(*) FROM schedules s WHERE s.route_id = r.id AND s.status <> 'cancelled' AND s.departure_datetime BETWEEN :f1 AND :t1) AS trips,
              (SELECT COUNT(*) FROM bookings b JOIN schedules s ON s.id = b.schedule_id
                 WHERE s.route_id = r.id AND b.status IN ('pending','confirmed','completed') AND s.departure_datetime BETWEEN :f2 AND :t2) AS seats_sold,
              (SELECT COALESCE(SUM(bu.capacity), 0) FROM schedules s JOIN buses bu ON bu.id = s.bus_id
                 WHERE s.route_id = r.id AND s.status <> 'cancelled' AND s.departure_datetime BETWEEN :f3 AND :t3) AS seats_offered,
              (SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN schedules s ON s.id = b.schedule_id
                 WHERE s.route_id = r.id AND p.payment_status = 'successful' AND s.departure_datetime BETWEEN :f4 AND :t4) AS revenue
             FROM routes r ORDER BY revenue DESC, r.route_code",
            [':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => $to, ':f3' => $from, ':t3' => $to, ':f4' => $from, ':t4' => $to]
        )->fetchAll();
    }

    public function busUtilization(string $from, string $to): array
    {
        return $this->run(
            "SELECT bu.bus_number, bu.plate_number, bu.capacity,
              (SELECT COUNT(*) FROM schedules s WHERE s.bus_id = bu.id AND s.status <> 'cancelled' AND s.departure_datetime BETWEEN :f1 AND :t1) AS trips,
              (SELECT COUNT(*) FROM bookings b JOIN schedules s ON s.id = b.schedule_id
                 WHERE s.bus_id = bu.id AND b.status IN ('pending','confirmed','completed') AND s.departure_datetime BETWEEN :f2 AND :t2) AS seats_sold,
              (SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN schedules s ON s.id = b.schedule_id
                 WHERE s.bus_id = bu.id AND p.payment_status = 'successful' AND s.departure_datetime BETWEEN :f3 AND :t3) AS revenue
             FROM buses bu ORDER BY bu.bus_number",
            [':f1' => $from, ':t1' => $to, ':f2' => $from, ':t2' => $to, ':f3' => $from, ':t3' => $to]
        )->fetchAll();
    }

    public function passengerBookings(string $from, string $to, string $status): array
    {
        $params = [':from' => $from, ':to' => $to, ':f2' => $from, ':t2' => $to];
        $extra = '';
        if ($status !== '') {
            $extra = ' AND b.status = :status';
            $params[':status'] = $status;
        }
        return $this->run(
            "SELECT p.passenger_code, CONCAT(p.first_name, ' ', p.last_name) AS passenger_name, p.phone,
                    COUNT(b.id) AS bookings, SUM(b.status = 'cancelled') AS cancelled,
                    (SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay JOIN bookings b2 ON b2.id = pay.booking_id
                       WHERE b2.passenger_id = p.id AND pay.payment_status = 'successful' AND b2.booking_date BETWEEN :f2 AND :t2) AS paid
             FROM passengers p JOIN bookings b ON b.passenger_id = p.id
             WHERE b.booking_date BETWEEN :from AND :to $extra
             GROUP BY p.id, p.passenger_code, p.first_name, p.last_name, p.phone
             ORDER BY bookings DESC, passenger_name LIMIT 500",
            $params
        )->fetchAll();
    }

    public function cancellations(string $from, string $to): array
    {
        return $this->run(
            "SELECT b.booking_reference, CONCAT(p.first_name, ' ', p.last_name) AS passenger_name,
                    CONCAT(r.origin, ' to ', r.destination) AS route_name, s.departure_datetime, b.total_amount,
                    (SELECT l.created_at FROM booking_status_logs l WHERE l.booking_id = b.id AND l.new_status = 'cancelled'
                       AND (l.old_status IS NULL OR l.old_status <> 'cancelled') ORDER BY l.id DESC LIMIT 1) AS cancelled_at,
                    (SELECT l.remarks FROM booking_status_logs l WHERE l.booking_id = b.id AND l.new_status = 'cancelled'
                       AND (l.old_status IS NULL OR l.old_status <> 'cancelled') ORDER BY l.id DESC LIMIT 1) AS reason,
                    (SELECT pay.payment_status FROM payments pay WHERE pay.booking_id = b.id AND pay.payment_status IN ('successful','refunded') LIMIT 1) AS payment_outcome
             FROM bookings b JOIN passengers p ON p.id = b.passenger_id JOIN schedules s ON s.id = b.schedule_id JOIN routes r ON r.id = s.route_id
             WHERE b.status = 'cancelled' AND EXISTS (
                SELECT 1 FROM booking_status_logs l WHERE l.booking_id = b.id AND l.new_status = 'cancelled'
                  AND (l.old_status IS NULL OR l.old_status <> 'cancelled') AND l.created_at BETWEEN :from AND :to)
             ORDER BY cancelled_at DESC LIMIT 1000",
            [':from' => $from, ':to' => $to]
        )->fetchAll();
    }
}
