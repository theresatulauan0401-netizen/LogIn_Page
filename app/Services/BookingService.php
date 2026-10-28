<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Reference;
use App\Core\ValidationException;
use App\Repositories\BookingRepository;
use App\Repositories\BookingStatusLogRepository;
use App\Repositories\PassengerRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\SystemSettingRepository;
use App\Repositories\TicketRepository;
use PDOException;

/**
 * Reservation rules. Every seat-changing method runs in ONE transaction and locks in the same
 * order (schedule row -> booking row) so concurrent staff can neither double-book a seat nor
 * corrupt the available_seats counter. The DB unique key on (schedule, seat, active flag) is the last line of defence.
 */
final class BookingService
{
    public const TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['completed', 'cancelled', 'no_show'],
        'completed' => [],
        'cancelled' => [],
        'no_show'   => [],
    ];

    public function __construct(
        private BookingRepository $bookings,
        private ScheduleRepository $schedules,
        private PassengerRepository $passengers,
        private PassengerService $passengerService,
        private PaymentRepository $payments,
        private TicketRepository $tickets,
        private BookingStatusLogRepository $logs,
        private SystemSettingRepository $settings,
        private Database $db,
        private Auth $auth,
        private ActivityLogService $activity
    ) {
    }

    /** @return array{rows:array,paginator:Paginator} */
    public function paginate(array $filters, int $page): array
    {
        $paginator = Paginator::make($this->bookings->count($filters), $page);
        return ['rows' => $this->bookings->search($filters, $paginator->perPage, $paginator->offset()), 'paginator' => $paginator];
    }

    /** @return array<string,mixed> everything the booking page shows */
    public function details(int $id): array
    {
        $b = $this->bookings->detail($id) ?? throw new HttpException(404, 'Booking not found.');
        return [
            'booking'  => $b,
            'payments' => $this->payments->forBooking($id),
            'ticket'   => $this->tickets->findByBooking($id),
            'history'  => $this->logs->forBooking($id),
            'allowed'  => self::TRANSITIONS[$b['status']] ?? [],
            'successfulPayment' => $this->payments->successfulForBooking($id),
        ];
    }

    /** Data for the 3-step booking form (route -> trip -> seat/passenger). */
    public function formData(?int $routeId, ?int $scheduleId, \App\Repositories\RouteRepository $routes): array
    {
        $data = ['routes' => $routes->activeOptions(), 'trips' => [], 'trip' => null, 'seats' => [], 'held' => [], 'passengers' => []];
        if ($scheduleId) {
            $trip = $this->schedules->detail($scheduleId);
            if ($trip && $trip['status'] === 'scheduled' && strtotime($trip['departure_datetime']) > time()) {
                $data['trip'] = $trip;
                $data['seats'] = SeatLayout::labels((int) $trip['capacity']);
                $data['held'] = $this->schedules->heldSeats($scheduleId);
                $data['passengers'] = $this->passengers->activeOptions();
                $routeId = (int) $trip['route_id'];
            }
        }
        if ($routeId) {
            $data['trips'] = $this->schedules->bookableForRoute($routeId);
        }
        $data['routeId'] = $routeId;
        return $data;
    }

    /**
     * @param array{schedule_id:int,seat_number:string,mode:string,passenger_id:?int,new:?array,notes:?string} $in
     */
    public function create(array $in): int
    {
        $bookingId = $this->db->transaction(function () use ($in): int {
            $scheduleId = (int) $in['schedule_id'];
            $trip = $this->schedules->lockWithCapacity($scheduleId)
                ?? throw ValidationException::general('The selected trip no longer exists.');

            if ($trip['status'] !== 'scheduled' || strtotime($trip['departure_datetime']) <= time()) {
                throw ValidationException::general('This trip is not open for booking (cancelled, departed or already left).');
            }
            $seat = strtoupper(trim((string) $in['seat_number']));
            if (!SeatLayout::isValid($seat, (int) $trip['capacity'])) {
                throw ValidationException::field('seat_number', 'That seat does not exist on this bus.');
            }
            if ($this->bookings->seatHeld($scheduleId, $seat)) {
                throw ValidationException::field('seat_number', "Seat $seat has just been taken. Please choose another seat.");
            }
            if ((int) $trip['available_seats'] <= 0) {
                throw ValidationException::general('This trip is sold out.');
            }

            if ($in['mode'] === 'new') {
                $passengerId = $this->passengerService->create($in['new'] ?? []);
            } else {
                $passenger = $this->passengers->find((int) $in['passenger_id'], true)
                    ?? throw ValidationException::field('passenger_id', 'Please select a passenger.');
                if (!$passenger->isActive()) {
                    throw ValidationException::field('passenger_id', 'This passenger is inactive.');
                }
                $passengerId = $passenger->id();
            }
            if ($this->bookings->passengerHoldsSeat($passengerId, $scheduleId)) {
                throw ValidationException::general('This passenger already has a booking on this trip.');
            }

            // Total is ALWAYS taken from the schedule on the server; the browser sends no amount.
            $total = number_format((float) $trip['fare'], 2, '.', '');

            $bookingId = null;
            for ($attempt = 0; $attempt < 5 && $bookingId === null; $attempt++) {
                try {
                    $bookingId = $this->bookings->insert([
                        'reference' => Reference::make('BKG'), 'passenger_id' => $passengerId, 'schedule_id' => $scheduleId,
                        'seat' => $seat, 'total' => $total, 'notes' => $in['notes'] ?? null, 'created_by' => $this->auth->id(),
                    ]);
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }
                    if (str_contains($e->getMessage(), 'uq_bookings_active_seat')) {
                        throw ValidationException::field('seat_number', "Seat $seat has just been taken. Please choose another seat.");
                    }
                    // otherwise a reference collision: loop and generate another
                }
            }
            if ($bookingId === null) {
                throw ValidationException::general('Could not generate a booking reference. Please try again.');
            }
            if (!$this->schedules->takeSeat($scheduleId)) {
                throw ValidationException::general('This trip is sold out.');
            }
            $this->logs->create($bookingId, null, 'pending', $this->auth->id(), 'Booking created for seat ' . $seat);
            return $bookingId;
        });
        $this->activity->log('create_booking', 'bookings', "Booking #$bookingId created");
        return $bookingId;
    }

    /** Workflow E. $refund records the refund of a successful payment in the same transaction. */
    public function cancel(int $id, string $reason, bool $refund, bool $override): void
    {
        $ref = $this->db->transaction(function () use ($id, $reason, $refund, $override): string {
            $scheduleId = $this->bookings->scheduleIdOf($id) ?? throw new HttpException(404, 'Booking not found.');
            $trip = $this->schedules->lockWithCapacity($scheduleId) ?? throw new HttpException(404);
            $booking = $this->bookings->find($id, true) ?? throw new HttpException(404, 'Booking not found.');

            if (!in_array('cancelled', self::TRANSITIONS[$booking->status()] ?? [], true)) {
                throw ValidationException::general("A {$booking->status()} booking cannot be cancelled.");
            }
            if ($this->settings->get('booking_cancellation_enabled', '1') !== '1') {
                throw ValidationException::general('Cancellations are currently disabled in Settings.');
            }
            if (!in_array($trip['status'], ['scheduled', 'boarding'], true)) {
                throw ValidationException::general("The trip is {$trip['status']}, so this booking can no longer be cancelled.");
            }
            $cutoff = (int) $this->settings->get('cancellation_cutoff_hours', '2');
            if (!$override && strtotime($trip['departure_datetime']) - time() < $cutoff * 3600) {
                throw ValidationException::general("Cancellations within $cutoff hours of departure need an administrator.");
            }

            $paid = $this->payments->successfulForBooking($id);
            if ($refund && $paid === null) {
                throw ValidationException::general('There is no successful payment to refund.');
            }

            $this->bookings->setStatus($id, 'cancelled');
            $this->schedules->releaseSeat($scheduleId);
            $this->tickets->cancelValidForBooking($id);
            $this->payments->failPendingForBooking($id, $this->auth->id());
            if ($refund && $paid !== null) {
                $this->payments->markRefunded((int) $paid['id'], $this->auth->id(), 'Refund on cancellation: ' . $reason);
            }
            $this->logs->create($id, $booking->status(), 'cancelled', $this->auth->id(),
                'Cancelled: ' . $reason . ($refund ? ' (payment refunded)' : ($paid !== null ? ' (payment NOT refunded)' : '')));
            return $booking->reference();
        });
        $this->activity->log('cancel_booking', 'bookings', "Booking $ref cancelled");
    }

    /** confirmed -> completed | no_show (only after the trip has left). */
    public function finish(int $id, string $outcome): void
    {
        if (!in_array($outcome, ['completed', 'no_show'], true)) {
            throw ValidationException::general('Invalid outcome.');
        }
        $ref = $this->db->transaction(function () use ($id, $outcome): string {
            $scheduleId = $this->bookings->scheduleIdOf($id) ?? throw new HttpException(404, 'Booking not found.');
            $trip = $this->schedules->lockWithCapacity($scheduleId) ?? throw new HttpException(404);
            $booking = $this->bookings->find($id, true) ?? throw new HttpException(404, 'Booking not found.');

            if (!in_array($outcome, self::TRANSITIONS[$booking->status()] ?? [], true)) {
                throw ValidationException::general("A {$booking->status()} booking cannot be marked $outcome.");
            }
            if (!in_array($trip['status'], ['departed', 'completed'], true)) {
                throw ValidationException::general('Bookings can only be closed after the trip has departed.');
            }
            $this->bookings->setStatus($id, $outcome);
            $this->tickets->expireValidForBooking($id);
            if ($outcome === 'no_show') {
                $this->schedules->releaseSeat($scheduleId);
            }
            $this->logs->create($id, $booking->status(), $outcome, $this->auth->id(), null);
            return $booking->reference();
        });
        $this->activity->log('booking_' . $outcome, 'bookings', "Booking $ref marked $outcome");
    }

    /**
     * Called by ScheduleService (inside its transaction, schedule row already locked) when a trip becomes
     * "completed": confirmed bookings become completed; unpaid pending ones are cancelled.
     */
    public function closeBookingsForCompletedTrip(int $scheduleId): void
    {
        foreach ($this->bookings->idsBySchedule($scheduleId, ['confirmed']) as $id) {
            $this->bookings->setStatus($id, 'completed');
            $this->tickets->expireValidForBooking($id);
            $this->logs->create($id, 'confirmed', 'completed', $this->auth->id(), 'Trip completed');
        }
        foreach ($this->bookings->idsBySchedule($scheduleId, ['pending']) as $id) {
            $this->bookings->setStatus($id, 'cancelled');
            $this->schedules->releaseSeat($scheduleId);
            $this->payments->failPendingForBooking($id, $this->auth->id());
            $this->logs->create($id, 'pending', 'cancelled', $this->auth->id(), 'Trip completed without payment');
        }
    }
}
