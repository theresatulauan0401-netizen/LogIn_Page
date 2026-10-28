<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReportRepository;

/**
 * Builds report definitions (columns + rows + totals) from real database queries.
 * The same structure feeds the HTML table and the CSV export, so both always agree.
 * Column types: text | int | money | percent | datetime.
 */
final class ReportService
{
    public const TYPES = [
        'bookings'      => 'Bookings',
        'revenue'       => 'Revenue',
        'routes'        => 'Route performance',
        'buses'         => 'Bus utilization',
        'passengers'    => 'Passenger bookings',
        'cancellations' => 'Cancellations',
    ];
    public const GROUPS = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'];

    public function __construct(private ReportRepository $repo)
    {
    }

    /**
     * @param array{from:string,to:string,group:string,status:string} $f
     * @return array{title:string,note:string,columns:array,rows:array,totals:?array}
     */
    public function run(string $type, array $f): array
    {
        $from = $f['from'] . ' 00:00:00';
        $to = $f['to'] . ' 23:59:59';
        $period = ['key' => 'period_label', 'label' => self::GROUPS[$f['group']] ?? 'Period', 'type' => 'text'];

        switch ($type) {
            case 'revenue':
                $columns = [$period, $this->c('payments', 'Payments', 'int', true), $this->c('cash', 'Cash', 'money', true),
                    $this->c('bank_transfer', 'Bank transfer', 'money', true), $this->c('e_wallet', 'E-wallet', 'money', true),
                    $this->c('card', 'Card', 'money', true), $this->c('revenue', 'Revenue', 'money', true)];
                $rows = $this->repo->revenueByPeriod($f['group'], $from, $to);
                $note = 'Counts successful payments only, by payment date. Refunded and failed payments are excluded.';
                break;
            case 'routes':
                $columns = [$this->c('route_code', 'Code', 'text'), $this->c('route_name', 'Route', 'text'), $this->c('trips', 'Trips', 'int', true),
                    $this->c('seats_sold', 'Seats sold', 'int', true), $this->c('seats_offered', 'Seats offered', 'int', true),
                    $this->c('occupancy', 'Occupancy', 'percent'), $this->c('revenue', 'Revenue', 'money', true)];
                $rows = $this->withOccupancy($this->repo->routePerformance($from, $to));
                $note = 'Trips are counted by departure date (cancelled trips excluded). Seats sold = pending, confirmed and completed bookings.';
                break;
            case 'buses':
                $columns = [$this->c('bus_number', 'Bus', 'text'), $this->c('plate_number', 'Plate', 'text'), $this->c('capacity', 'Seats', 'int'),
                    $this->c('trips', 'Trips', 'int', true), $this->c('seats_sold', 'Seats sold', 'int', true),
                    $this->c('occupancy', 'Occupancy', 'percent'), $this->c('revenue', 'Revenue', 'money', true)];
                $rows = $this->repo->busUtilization($from, $to);
                foreach ($rows as &$r) {
                    $offered = (int) $r['capacity'] * (int) $r['trips'];
                    $r['occupancy'] = $offered > 0 ? round((int) $r['seats_sold'] / $offered * 100, 1) : 0.0;
                }
                unset($r);
                $note = 'Occupancy = seats sold / (seat capacity x trips) for trips departing in the range.';
                break;
            case 'passengers':
                $columns = [$this->c('passenger_code', 'Code', 'text'), $this->c('passenger_name', 'Passenger', 'text'), $this->c('phone', 'Phone', 'text'),
                    $this->c('bookings', 'Bookings', 'int', true), $this->c('cancelled', 'Cancelled', 'int', true), $this->c('paid', 'Paid (successful)', 'money', true)];
                $rows = $this->repo->passengerBookings($from, $to, $f['status']);
                $note = 'Passengers with bookings made in the range (top 500). Optional booking-status filter applies.';
                break;
            case 'cancellations':
                $columns = [$this->c('booking_reference', 'Booking', 'text'), $this->c('passenger_name', 'Passenger', 'text'), $this->c('route_name', 'Route', 'text'),
                    $this->c('departure_datetime', 'Departure', 'datetime'), $this->c('cancelled_at', 'Cancelled at', 'datetime'),
                    $this->c('total_amount', 'Amount', 'money', true), $this->c('payment_outcome', 'Payment', 'text'), $this->c('reason', 'Reason', 'text')];
                $rows = $this->repo->cancellations($from, $to);
                $note = 'Bookings cancelled within the range, with the logged reason and what happened to the payment.';
                break;
            default: // bookings
                $type = 'bookings';
                $columns = [$period, $this->c('total', 'Total', 'int', true), $this->c('pending', 'Pending', 'int', true), $this->c('confirmed', 'Confirmed', 'int', true),
                    $this->c('completed', 'Completed', 'int', true), $this->c('cancelled', 'Cancelled', 'int', true), $this->c('no_show', 'No-show', 'int', true),
                    $this->c('booked_value', 'Booked value', 'money', true)];
                $rows = $this->repo->bookingsByPeriod($f['group'], $from, $to, $f['status']);
                $note = 'Bookings by creation date. "Booked value" is the sum of booking totals, not collected revenue.';
        }

        return ['title' => self::TYPES[$type], 'note' => $note, 'columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($columns, $rows)];
    }

    private function c(string $key, string $label, string $type, bool $sum = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'sum' => $sum];
    }

    private function withOccupancy(array $rows): array
    {
        foreach ($rows as &$r) {
            $r['occupancy'] = (int) $r['seats_offered'] > 0 ? round((int) $r['seats_sold'] / (int) $r['seats_offered'] * 100, 1) : 0.0;
        }
        return $rows;
    }

    private function totals(array $columns, array $rows): ?array
    {
        if (!$rows) {
            return null;
        }
        $totals = [];
        foreach ($columns as $i => $col) {
            $totals[$col['key']] = $col['sum'] ? array_sum(array_map(static fn ($r) => (float) ($r[$col['key']] ?? 0), $rows)) : ($i === 0 ? 'Total' : '');
        }
        return $totals;
    }
}
