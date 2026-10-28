<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;

final class DashboardService
{
    private const BOOKING_STATUSES = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

    public function __construct(private DashboardRepository $repo)
    {
    }

    /** @return array<string,mixed> */
    public function overview(): array
    {
        $statusCounts = $this->repo->bookingStatusCounts();
        $statusLabels = [];
        $statusData = [];
        foreach (self::BOOKING_STATUSES as $status) {
            $statusLabels[] = ucwords(str_replace('_', ' ', $status));
            $statusData[] = $statusCounts[$status] ?? 0;
        }

        // Last 7 days including today; days without sales show 0.
        $revenue = $this->repo->revenueSince(date('Y-m-d', strtotime('-6 days')));
        $revLabels = [];
        $revData = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $revLabels[] = date('M d', strtotime($day));
            $revData[] = round($revenue[$day] ?? 0, 2);
        }

        return [
            'counts'        => $this->repo->counts(),
            'statusChart'   => ['labels' => $statusLabels, 'data' => $statusData, 'hasData' => array_sum($statusData) > 0],
            'revenueChart'  => ['labels' => $revLabels, 'data' => $revData, 'hasData' => array_sum($revData) > 0],
            'recentBookings' => $this->repo->recentBookings(8),
            'upcoming'      => $this->repo->upcomingDepartures(6),
        ];
    }

    /** @return array{items:array<int,array{label:string,count:int,url:string,icon:string}>,total:int} */
    public function notifications(): array
    {
        $c = $this->repo->notificationCounts();
        $items = [];
        $defs = [
            ['pending_bookings',  'Pending bookings',            '/bookings?status=pending',  'bi-hourglass-split'],
            ['pending_payments',  'Payments awaiting action',    '/payments?status=pending',  'bi-cash-coin'],
            ['departing_soon',    'Trips departing within 24h',  '/schedules',                'bi-clock-history'],
            ['maintenance_buses', 'Buses in maintenance',        '/buses?status=maintenance', 'bi-wrench-adjustable'],
        ];
        $total = 0;
        foreach ($defs as [$key, $label, $url, $icon]) {
            $count = (int) ($c[$key] ?? 0);
            if ($count > 0) {
                $items[] = ['label' => $label, 'count' => $count, 'url' => $url, 'icon' => $icon];
                $total += $count;
            }
        }
        return ['items' => $items, 'total' => $total];
    }
}
