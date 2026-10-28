<?php

declare(strict_types=1);

namespace Tests;

use App\Core\ValidationException;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\TicketService;

final class PaymentTicketTest extends DatabaseTestCase
{
    private function pay(int $bookingId, array $over = []): int
    {
        return $this->c->get(PaymentService::class)->record($over + [
            'booking_id' => $bookingId, 'method' => 'cash', 'payment_status' => 'successful',
            'amount' => '250.00', 'transaction_reference' => null, 'verified' => true,
        ]);
    }

    public function testSuccessfulPaymentConfirmsBookingAndIssuesExactlyOneTicket(): void
    {
        $b = $this->book(6, '3C');
        $this->pay($b);
        $this->assertSame('confirmed', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$b]));
        $this->assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM tickets WHERE booking_id = ?', [$b]));

        $this->expectException(ValidationException::class); // duplicate ticket refused
        $this->c->get(TicketService::class)->issue($b);
    }

    public function testWrongAmountIsRejected(): void
    {
        $b = $this->book(6, '3C');
        $this->expectException(ValidationException::class);
        $this->pay($b, ['amount' => '100.00']);
    }

    public function testSuccessfulWithoutVerificationIsRejected(): void
    {
        $b = $this->book(6, '3C');
        $this->expectException(ValidationException::class);
        $this->pay($b, ['verified' => false]);
    }

    public function testNonCashNeedsAReferenceAndCardNumbersAreRefused(): void
    {
        $b = $this->book(6, '3C');
        try {
            $this->pay($b, ['method' => 'bank_transfer', 'transaction_reference' => null]);
            $this->fail('Reference should be required.');
        } catch (ValidationException) {
        }
        $this->expectException(ValidationException::class);
        $this->pay($b, ['method' => 'card', 'transaction_reference' => '4111 1111 1111 1111']);
    }

    public function testSecondSuccessfulPaymentIsRejected(): void
    {
        $b = $this->book(6, '3C');
        $this->pay($b);
        $this->expectException(ValidationException::class); // booking is no longer pending
        $this->pay($b);
    }

    public function testFailedAttemptIsRecordedAndBookingStaysPending(): void
    {
        $b = $this->book(6, '3C');
        $this->pay($b, ['payment_status' => 'failed', 'verified' => false]);
        $this->assertSame('pending', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$b]));
        $this->assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM tickets WHERE booking_id = ?', [$b]));
    }

    public function testCancellingAPaidBookingCancelsTicketAndRefundIsAudited(): void
    {
        $b = $this->book(6, '3C');
        $p = $this->pay($b);
        $this->c->get(BookingService::class)->cancel($b, 'changed plans', true, true);
        $this->assertSame('cancelled', $this->scalar('SELECT ticket_status FROM tickets WHERE booking_id = ?', [$b]));
        $this->assertSame('refunded', $this->scalar('SELECT payment_status FROM payments WHERE id = ?', [$p]));
        $this->assertNotNull($this->scalar('SELECT refunded_at FROM payments WHERE id = ?', [$p]));
    }

    public function testRefundRequiresCancelledBooking(): void
    {
        $b = $this->book(6, '3C');
        $p = $this->pay($b);
        $this->expectException(ValidationException::class);
        $this->c->get(PaymentService::class)->refund($p, 'no reason');
    }

    public function testRevenueCountsSuccessfulPaymentsOnly(): void
    {
        // Demo data: successful 500 + 500 + 350 + 500 = 1850; failed/pending/refunded excluded.
        $this->assertSame('1850.00', $this->scalar("SELECT SUM(amount) FROM payments WHERE payment_status = 'successful'"));
    }
}
