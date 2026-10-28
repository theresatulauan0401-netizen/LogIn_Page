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
use App\Repositories\PaymentRepository;
use App\Repositories\ScheduleRepository;
use PDOException;

/**
 * Records payments received by staff (cash, or bank/e-wallet/card payments whose reference was verified by hand).
 * This is NOT an online gateway: nothing here contacts a bank or stores card numbers/CVVs.
 */
final class PaymentService
{
    public const METHODS = ['cash', 'bank_transfer', 'e_wallet', 'card'];

    public function __construct(
        private PaymentRepository $payments,
        private BookingRepository $bookings,
        private ScheduleRepository $schedules,
        private BookingStatusLogRepository $logs,
        private TicketService $ticketService,
        private Database $db,
        private Auth $auth,
        private ActivityLogService $activity
    ) {
    }

    /** @return array{rows:array,paginator:Paginator} */
    public function paginate(array $filters, int $page): array
    {
        $paginator = Paginator::make($this->payments->count($filters), $page);
        return ['rows' => $this->payments->search($filters, $paginator->perPage, $paginator->offset()), 'paginator' => $paginator];
    }

    public function detail(int $id): array
    {
        return $this->payments->detail($id) ?? throw new HttpException(404, 'Payment not found.');
    }

    /** Booking summary for the "record payment" form. */
    public function bookingForForm(int $bookingId): array
    {
        $b = $this->bookings->detail($bookingId) ?? throw new HttpException(404, 'Booking not found.');
        $b['has_pending_payment'] = $this->payments->hasPending($bookingId);
        return $b;
    }

    /**
     * Workflow D. $in: booking_id, method, payment_status (successful|pending|failed), amount,
     * transaction_reference?, verified (bool: staff confirms the money/reference was really checked).
     */
    public function record(array $in): int
    {
        $paymentId = $this->db->transaction(function () use ($in): int {
            $bookingId = (int) $in['booking_id'];
            $scheduleId = $this->bookings->scheduleIdOf($bookingId) ?? throw new HttpException(404, 'Booking not found.');
            $trip = $this->schedules->lockWithCapacity($scheduleId) ?? throw new HttpException(404);
            $booking = $this->bookings->find($bookingId, true) ?? throw new HttpException(404, 'Booking not found.');

            if ($booking->status() !== 'pending') {
                throw ValidationException::general('Payments can only be recorded for pending bookings.');
            }
            if (!in_array($trip['status'], ['scheduled', 'boarding'], true)) {
                throw ValidationException::general("The trip is {$trip['status']}; payments can no longer be recorded.");
            }
            $amount = number_format((float) $in['amount'], 2, '.', '');
            if ($amount !== number_format((float) $booking->totalAmount(), 2, '.', '')) {
                throw ValidationException::field('amount', 'Amount must equal the booking total of ' . format_money($booking->totalAmount()) . '.');
            }
            $method = (string) $in['method'];
            $status = (string) $in['payment_status'];
            $txn = $this->cleanReference($in['transaction_reference'] ?? null);

            if ($status === 'pending' && $this->payments->hasPending($bookingId)) {
                throw ValidationException::general('A pending payment already exists for this booking. Verify it or mark it failed first.');
            }
            if ($status === 'successful') {
                $this->assertCanSucceed($bookingId, $method, $txn, !empty($in['verified']), null);
            }

            try {
                $paymentId = $this->payments->insert([
                    'reference' => Reference::make('PAY'), 'booking_id' => $bookingId, 'amount' => $amount, 'method' => $method,
                    'status' => $status, 'transaction_reference' => $txn,
                    'paid_at' => $status === 'successful' ? date('Y-m-d H:i:s') : null,
                    'received_by' => $this->auth->id(),
                ]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw ValidationException::general('This payment was already recorded (duplicate). Please refresh the booking.');
                }
                throw $e;
            }

            if ($status === 'successful') {
                $this->confirmBooking($booking->id(), $booking->status());
            }
            return $paymentId;
        });
        $this->activity->log('record_payment', 'payments', "Payment #$paymentId recorded ({$in['payment_status']})");
        return $paymentId;
    }

    /** Resolve a pending payment: $outcome = successful | failed. */
    public function resolve(int $paymentId, string $outcome, ?string $transactionReference, bool $verified): void
    {
        $this->db->transaction(function () use ($paymentId, $outcome, $transactionReference, $verified): void {
            $bookingId = $this->payments->bookingIdOf($paymentId) ?? throw new HttpException(404, 'Payment not found.');
            $scheduleId = $this->bookings->scheduleIdOf($bookingId) ?? throw new HttpException(404);
            $trip = $this->schedules->lockWithCapacity($scheduleId) ?? throw new HttpException(404);
            $booking = $this->bookings->find($bookingId, true) ?? throw new HttpException(404);
            $payment = $this->payments->find($paymentId, true) ?? throw new HttpException(404, 'Payment not found.');

            if ($payment->status() !== 'pending') {
                throw ValidationException::general('Only pending payments can be verified or failed.');
            }
            if ($outcome === 'failed') {
                $this->payments->markFailed($paymentId, $this->auth->id());
                return;
            }
            if ($booking->status() !== 'pending' || !in_array($trip['status'], ['scheduled', 'boarding'], true)) {
                throw ValidationException::general('This booking can no longer be confirmed by payment.');
            }
            $txn = $this->cleanReference($transactionReference ?? $payment->transactionReference());
            $this->assertCanSucceed($bookingId, $payment->method(), $txn, $verified, $paymentId);
            if (!$this->payments->markSuccessful($paymentId, $txn, $this->auth->id())) {
                throw ValidationException::general('This payment was already processed.');
            }
            $this->confirmBooking($bookingId, $booking->status());
        });
        $this->activity->log('resolve_payment', 'payments', "Payment #$paymentId marked $outcome");
    }

    /** Audited refund of a successful payment whose booking is already cancelled. */
    public function refund(int $paymentId, string $reason): void
    {
        $this->db->transaction(function () use ($paymentId, $reason): void {
            $bookingId = $this->payments->bookingIdOf($paymentId) ?? throw new HttpException(404, 'Payment not found.');
            $scheduleId = $this->bookings->scheduleIdOf($bookingId) ?? throw new HttpException(404);
            $this->schedules->lockWithCapacity($scheduleId);
            $booking = $this->bookings->find($bookingId, true) ?? throw new HttpException(404);
            $payment = $this->payments->find($paymentId, true) ?? throw new HttpException(404, 'Payment not found.');

            if ($payment->status() !== 'successful') {
                throw ValidationException::general('Only successful payments can be refunded.');
            }
            if ($booking->status() !== 'cancelled') {
                throw ValidationException::general('Cancel the booking first, then refund its payment (or tick "refund" while cancelling).');
            }
            if (!$this->payments->markRefunded($paymentId, $this->auth->id(), $reason)) {
                throw ValidationException::general('This payment was already refunded.');
            }
            $this->logs->create($bookingId, 'cancelled', 'cancelled', $this->auth->id(), 'Payment ' . $payment->reference() . ' refunded: ' . $reason);
        });
        $this->activity->log('refund_payment', 'payments', "Payment #$paymentId refunded");
    }

    // -------------------------------------------------------------------- helpers
    private function assertCanSucceed(int $bookingId, string $method, ?string $txn, bool $verified, ?int $exceptPaymentId): void
    {
        if (!$verified) {
            throw ValidationException::field('verified', 'Confirm that the payment was received / the reference was verified.');
        }
        if ($method !== 'cash' && $txn === null) {
            throw ValidationException::field('transaction_reference', 'A verified transaction reference is required for this payment method.');
        }
        if ($this->payments->successfulForBooking($bookingId) !== null) {
            throw ValidationException::general('This booking already has a successful payment.');
        }
        if ($txn !== null && $this->payments->successTxnTaken($txn, $exceptPaymentId)) {
            throw ValidationException::field('transaction_reference', 'This transaction reference was already used for another payment.');
        }
    }

    private function confirmBooking(int $bookingId, string $oldStatus): void
    {
        $this->bookings->setStatus($bookingId, 'confirmed');
        $this->logs->create($bookingId, $oldStatus, 'confirmed', $this->auth->id(), 'Payment recorded');
        $this->ticketService->issueWithinTransaction($bookingId); // exactly once (UNIQUE booking_id)
    }

    /** Plain reference text only: no card-number-like values (we never store card data). */
    private function cleanReference(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $ref = trim((string) $value);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-_\/]{2,149}$/', $ref)) {
            throw ValidationException::field('transaction_reference', 'Reference may contain letters, numbers, spaces, dashes and slashes (3-150 characters).');
        }
        if (preg_match('/^\d{13,19}$/', preg_replace('/[\s-]/', '', $ref))) {
            throw ValidationException::field('transaction_reference', 'That looks like a card number. Never enter card numbers: use the bank/terminal approval or receipt reference.');
        }
        return $ref;
    }
}
