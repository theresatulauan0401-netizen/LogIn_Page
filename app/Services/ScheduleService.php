<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\ValidationException;
use App\Models\Bus;
use App\Models\Route;
use App\Repositories\BusRepository;
use App\Repositories\RouteRepository;
use App\Repositories\ScheduleRepository;
use PDOException;

final class ScheduleService
{
    /** Allowed status transitions. A cancelled/completed trip is final. */
    public const TRANSITIONS = [
        'scheduled' => ['boarding', 'cancelled'],
        'boarding'  => ['departed', 'cancelled'],
        'departed'  => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private ScheduleRepository $schedules,
        private BusRepository $buses,
        private RouteRepository $routes,
        private BookingService $bookingService,
        private Database $db,
        private ActivityLogService $activity
    ) {
    }

    /** @return array{rows:array,paginator:Paginator} */
    public function paginate(array $filters, int $page): array
    {
        $paginator = Paginator::make($this->schedules->count($filters), $page);
        return ['rows' => $this->schedules->search($filters, $paginator->perPage, $paginator->offset()), 'paginator' => $paginator];
    }

    /** @return array{buses:array<int,string>,routes:array<int,string>} */
    public function formOptions(): array
    {
        return ['buses' => $this->buses->activeOptions(), 'routes' => $this->routes->activeOptions()];
    }

    /** @return array<string,mixed> */
    public function details(int $id): array
    {
        $row = $this->schedules->detail($id) ?? throw new HttpException(404, 'Schedule not found.');
        $held = $this->schedules->heldSeats($id);
        $capacity = (int) $row['capacity'];
        return [
            'schedule'   => $row,
            'seats'      => SeatLayout::labels($capacity),
            'heldSeats'  => $held,
            'booked'     => count($held),
            'activeBookings' => $this->schedules->activeBookingCount($id),
            'transitions' => self::TRANSITIONS[$row['status']] ?? [],
        ];
    }

    public function activeBookingCount(int $id): int
    {
        return $this->schedules->activeBookingCount($id);
    }

    public function create(array $in): int
    {
        $id = $this->db->transaction(function () use ($in): int {
            // Locking the bus row serialises concurrent schedule changes for that bus,
            // so two admins cannot both pass the overlap check for the same slot.
            $bus = $this->requireActiveBus($in['bus_id'] ?? null);
            $route = $this->requireActiveRoute($in['route_id'] ?? null);
            [$dep, $arr] = $this->resolveTimes($in, $route);
            $fare = $this->resolveFare($in, $route);
            $this->assertNoOverlap($bus->id(), $dep, $arr, null);

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $code = 'SCH-' . date('ymd', strtotime($dep)) . '-' . strtoupper(bin2hex(random_bytes(2)));
                if ($this->schedules->codeTaken($code)) {
                    continue;
                }
                try {
                    return $this->schedules->insert($code, [
                        'bus_id' => $bus->id(), 'route_id' => $route->id(), 'departure' => $dep, 'arrival' => $arr,
                        'fare' => $fare, 'available_seats' => $bus->capacity(),
                    ]);
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }
                }
            }
            throw ValidationException::general('Could not generate a unique schedule code. Please try again.');
        });
        $this->activity->log('create_schedule', 'schedules', "Schedule #$id created");
        return $id;
    }

    public function update(int $id, array $in): void
    {
        $code = $this->db->transaction(function () use ($id, $in): string {
            $schedule = $this->schedules->find($id, true) ?? throw new HttpException(404, 'Schedule not found.');
            if ($schedule->status() !== 'scheduled') {
                throw ValidationException::general('Only trips with status "scheduled" can be edited.');
            }
            $locked = $this->schedules->activeBookingCount($id) > 0 || $this->schedules->seatHoldingCount($id) > 0;

            if ($locked) {
                // Bookings already priced/seated against this trip: keep bus, route and fare.
                $bus = $this->buses->find($schedule->busId(), true) ?? throw new HttpException(404, 'Bus not found.');
                $route = $this->routes->find($schedule->routeId()) ?? throw new HttpException(404, 'Route not found.');
                $fare = $schedule->fare();
                $available = $schedule->availableSeats();
            } else {
                $bus = $this->requireActiveBus($in['bus_id'] ?? null);
                $route = $this->requireActiveRoute($in['route_id'] ?? null);
                $fare = $this->resolveFare($in, $route);
                $available = $bus->capacity();
            }
            [$dep, $arr] = $this->resolveTimes($in, $route);
            $this->assertNoOverlap($bus->id(), $dep, $arr, $id);

            $this->schedules->update($id, [
                'bus_id' => $bus->id(), 'route_id' => $route->id(), 'departure' => $dep, 'arrival' => $arr,
                'fare' => $fare, 'available_seats' => $available,
            ]);
            return $schedule->scheduleCode();
        });
        $this->activity->log('update_schedule', 'schedules', "Schedule $code updated");
    }

    public function changeStatus(int $id, string $new): void
    {
        $result = $this->db->transaction(function () use ($id, $new): array {
            $schedule = $this->schedules->find($id, true) ?? throw new HttpException(404, 'Schedule not found.');
            $allowed = self::TRANSITIONS[$schedule->status()] ?? [];
            if (!in_array($new, $allowed, true)) {
                throw ValidationException::general("A {$schedule->status()} trip cannot be changed to $new.");
            }
            if ($new === 'cancelled' && $this->schedules->activeBookingCount($id) > 0) {
                throw ValidationException::general('This trip still has pending or confirmed bookings. Cancel those bookings first.');
            }
            $this->schedules->setStatus($id, $new);
            if ($new === 'completed') {
                $this->bookingService->closeBookingsForCompletedTrip($id);
            }
            return [$schedule->scheduleCode(), $schedule->status()];
        });
        $this->activity->log($new === 'cancelled' ? 'cancel_schedule' : 'set_schedule_status', 'schedules',
            "Schedule {$result[0]} changed from {$result[1]} to $new");
    }

    // ------------------------------------------------------------------ helpers
    private function requireActiveBus(mixed $busId): Bus
    {
        if (!is_numeric($busId)) {
            throw ValidationException::field('bus_id', 'Please select a bus.');
        }
        $bus = $this->buses->find((int) $busId, true);
        if ($bus === null || !$bus->isActive()) {
            throw ValidationException::field('bus_id', 'Please select an active bus.');
        }
        return $bus;
    }

    private function requireActiveRoute(mixed $routeId): Route
    {
        if (!is_numeric($routeId)) {
            throw ValidationException::field('route_id', 'Please select a route.');
        }
        $route = $this->routes->find((int) $routeId);
        if ($route === null || !$route->isActive()) {
            throw ValidationException::field('route_id', 'Please select an active route.');
        }
        return $route;
    }

    /** @return array{0:string,1:string} departure, arrival as "Y-m-d H:i:s" */
    private function resolveTimes(array $in, Route $route): array
    {
        $dep = (string) ($in['departure_datetime'] ?? '');
        if ($dep === '') {
            throw ValidationException::field('departure_datetime', 'Departure date and time is required.');
        }
        if (strtotime($dep) <= time()) {
            throw ValidationException::field('departure_datetime', 'Departure must be in the future.');
        }
        $arr = (string) ($in['arrival_datetime'] ?? '');
        if ($arr === '') {
            if ($route->durationMinutes() === null) {
                throw ValidationException::field('arrival_datetime', 'Enter an arrival time (this route has no estimated duration).');
            }
            $arr = date('Y-m-d H:i:s', strtotime($dep) + $route->durationMinutes() * 60);
        }
        if (strtotime($arr) <= strtotime($dep)) {
            throw ValidationException::field('arrival_datetime', 'Arrival must be later than departure.');
        }
        return [$dep, $arr];
    }

    private function resolveFare(array $in, Route $route): string
    {
        $fare = $in['fare'] ?? null;
        if ($fare === null || $fare === '') {
            return $route->baseFare();
        }
        if (!is_numeric($fare) || (float) $fare < 0) {
            throw ValidationException::field('fare', 'Fare must be zero or greater.');
        }
        return number_format((float) $fare, 2, '.', '');
    }

    private function assertNoOverlap(int $busId, string $dep, string $arr, ?int $exceptId): void
    {
        $clash = $this->schedules->findOverlap($busId, $dep, $arr, $exceptId);
        if ($clash !== null) {
            throw ValidationException::field(
                'departure_datetime',
                "This bus is already assigned to {$clash['schedule_code']} ("
                . format_datetime($clash['departure_datetime']) . ' - ' . format_datetime($clash['arrival_datetime']) . ').'
            );
        }
    }
}
