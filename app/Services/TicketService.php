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
use App\Repositories\PaymentRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\TicketRepository;
use PDOException;

final class TicketService
{
    public function __construct(
        private TicketRepository $tickets,
        private BookingRepository $bookings,
        private PaymentRepository $payments,
        private ScheduleRepository $schedules,
        private Database $db,
        private Auth $auth,
        private ActivityLogService $activity
    ) {
    }

    /** @return array{rows:array,paginator:Paginator} */
    public function paginate(array $filters, int $page): array
    {
        $paginator = Paginator::make($this->tickets->count($filters), $page);
        return ['rows' => $this->tickets->search($filters, $paginator->perPage, $paginator->offset()), 'paginator' => $paginator];
    }

    public function detail(int $id): array
    {
        return $this->tickets->detail($id) ?? throw new HttpException(404, 'Ticket not found.');
    }

    /**
     * Issues the ticket for an eligible booking (confirmed + successful payment + no ticket yet).
     * Must run inside a transaction that already holds the booking lock (PaymentService does that);
     * the UNIQUE(booking_id) key is the final guard against duplicates.
     */
    public function issueWithinTransaction(int $bookingId): int
    {
        $booking = $this->bookings->find($bookingId, true) ?? throw new HttpException(404, 'Booking not found.');
        if ($booking->status() !== 'confirmed') {
            throw ValidationException::general('Tickets can only be issued for confirmed bookings.');
        }
        if ($this->payments->successfulForBooking($bookingId) === null) {
            throw ValidationException::general('A successful payment is required before a ticket can be issued.');
        }
        if ($this->tickets->findByBooking($bookingId, true) !== null) {
            throw ValidationException::general('A ticket has already been issued for this booking.');
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $id = $this->tickets->insert(Reference::make('TKT'), $bookingId);
                $this->activity->log('issue_ticket', 'tickets', "Ticket issued for booking {$booking->reference()}");
                return $id;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                if ($this->tickets->findByBooking($bookingId) !== null) {
                    throw ValidationException::general('A ticket has already been issued for this booking.');
                }
            }
        }
        throw ValidationException::general('Could not generate a unique ticket number. Please try again.');
    }

    /** Manual issue (e.g. a confirmed booking that somehow has no ticket). */
    public function issue(int $bookingId): int
    {
        return $this->db->transaction(function () use ($bookingId): int {
            $scheduleId = $this->bookings->scheduleIdOf($bookingId) ?? throw new HttpException(404, 'Booking not found.');
            $this->schedules->lockWithCapacity($scheduleId);
            return $this->issueWithinTransaction($bookingId);
        });
    }

    /**
     * Looks a ticket number up and explains whether it may be used right now.
     *
     * @return array{ticket:array,usable:bool,reason:string}|null
     */
    public function lookup(string $number): ?array
    {
        $id = $this->tickets->findIdByNumber(strtoupper(trim($number)));
        if ($id === null) {
            return null;
        }
        $t = $this->tickets->detail($id);
        [$usable, $reason] = $this->usability($t);
        return ['ticket' => $t, 'usable' => $usable, 'reason' => $reason];
    }

    /** Marks a ticket used. Re-validates inside a transaction (no double scanning). */
    public function markUsed(int $ticketId): void
    {
        $this->db->transaction(function () use ($ticketId): void {
            $ticket = $this->tickets->find($ticketId, true) ?? throw new HttpException(404, 'Ticket not found.');
            $scheduleId = $this->bookings->scheduleIdOf($ticket->bookingId()) ?? throw new HttpException(404);
            $this->schedules->lockWithCapacity($scheduleId);
            $detail = $this->tickets->detail($ticketId);
            [$usable, $reason] = $this->usability($detail);
            if (!$usable || !$this->tickets->markUsed($ticketId, $this->auth->id())) {
                throw ValidationException::general($usable ? 'Ticket could not be marked as used.' : $reason);
            }
        });
        $this->activity->log('use_ticket', 'tickets', "Ticket #$ticketId marked as used");
    }

    /** @return array{0:bool,1:string} */
    private function usability(array $t): array
    {
        return match (true) {
            $t['ticket_status'] === 'used'      => [false, 'This ticket was already used' . ($t['used_at'] ? ' on ' . format_datetime($t['used_at']) : '') . '.'],
            $t['ticket_status'] === 'cancelled' => [false, 'This ticket was cancelled.'],
            $t['ticket_status'] === 'expired'   => [false, 'This ticket has expired.'],
            $t['booking_status'] !== 'confirmed' => [false, 'The booking is not confirmed (status: ' . $t['booking_status'] . ').'],
            !in_array($t['schedule_status'], ['scheduled', 'boarding', 'departed'], true) => [false, 'The trip is ' . $t['schedule_status'] . '.'],
            default => [true, 'Ticket is valid for travel.'],
        };
    }
}
