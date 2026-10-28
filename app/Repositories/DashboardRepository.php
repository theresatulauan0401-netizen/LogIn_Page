<?php

declare(strict_types=1);

namespace App\Repositories;

/** Read-only aggregate queries for the dashboard. Nothing here is hard-coded. */
final class DashboardRepository extends Repository
{
    /** @return array<string,int|float|string> */
    public function counts(): array
    {
        return $this->run(
            "SELECT
               (SELECT COUNT(*) FROM buses) AS total_buses,
               (SELECT COUNT(*) FROM buses WHERE status = 'active') AS active_buses,
               (SELECT COUNT(*) FROM routes) AS total_routes,
               (SELECT COUNT(*) FROM schedules WHERE departure_datetime > NOW() AND status IN ('scheduled','boarding')) AS upcoming_schedules,
               (SELECT COUNT(*) FROM passengers) AS total_passengers,
               (SELECT COUNT(*) FROM bookings) AS total_bookings,
               (SELECT COUNT(*) FROM bookings WHERE status = 'pending') AS pending_bookings,
               (SELECT COUNT(*) FROM bookings WHERE status = 'confirmed') AS confirmed_bookings,
               (SELECT COUNT(*) FROM bookings WHERE status = 'cancelled') AS cancelled_bookings,
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'successful') AS total_revenue"
        )->fetch() ?: [];
    }

    /** @return array<string,int> status => count */
    public function bookingStatusCounts(): array
    {
        $rows = $this->run('SELECT status, COUNT(*) AS c FROM bookings GROUP BY status')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['c'];
        }
        return $out;
    }

    /** @return array<string,float> 'Y-m-d' => revenue (days without sales are absent) */
    public function revenueSince(string $fromDate): array
    {
        $rows = $this->run(
            "SELECT DATE(paid_at) AS d, SUM(amount) AS total
             FROM payments
             WHERE payment_status = 'successful' AND paid_at >= :from
             GROUP BY DATE(paid_at)",
            [':from' => $fromDate . ' 00:00:00']
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['d']] = (float) $row['total'];
        }
        return $out;
    }

    public function recentBookings(int $limit): array
    {
        return $this->run(
            "SELECT b.id, b.booking_reference, b.seat_number, b.total_amount, b.status, b.booking_date,
                    CONCAT(p.first_name, ' ', p.last_name) AS passenger_name,
                    CONCAT(r.origin, ' to ', r.destination) AS route_name
             FROM bookings b
             JOIN passengers p ON p.id = b.passenger_id
             JOIN schedules s ON s.id = b.schedule_id
             JOIN routes r ON r.id = s.route_id
             ORDER BY b.booking_date DESC, b.id DESC
             LIMIT :n",
            [':n' => $limit]
        )->fetchAll();
    }

    public function upcomingDepartures(int $limit): array
    {
        return $this->run(
            "SELECT s.id, s.schedule_code, s.departure_datetime, s.available_seats, s.status,
                    bu.bus_number, CONCAT(r.origin, ' to ', r.destination) AS route_name
             FROM schedules s
             JOIN buses bu ON bu.id = s.bus_id
             JOIN routes r ON r.id = s.route_id
             WHERE s.departure_datetime > NOW() AND s.status IN ('scheduled','boarding')
             ORDER BY s.departure_datetime ASC
             LIMIT :n",
            [':n' => $limit]
        )->fetchAll();
    }

    /** @return array<string,int> */
    public function notificationCounts(): array
    {
        return $this->run(
            "SELECT
               (SELECT COUNT(*) FROM bookings WHERE status = 'pending') AS pending_bookings,
               (SELECT COUNT(*) FROM payments WHERE payment_status = 'pending') AS pending_payments,
               (SELECT COUNT(*) FROM buses WHERE status = 'maintenance') AS maintenance_buses,
               (SELECT COUNT(*) FROM schedules WHERE status = 'scheduled'
                  AND departure_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)) AS departing_soon"
        )->fetch() ?: [];
    }
}
