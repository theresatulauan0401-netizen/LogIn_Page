<?php

declare(strict_types=1);

namespace Tests;

use App\Core\ValidationException;
use App\Services\BookingService;
use App\Services\ScheduleService;

/** Needs MySQL + a TEST_DB_NAME database (see phpunit.xml). Uses the seeded demo data: schedule 6 = 40-seat bus, fare 250. */
final class BookingServiceTest extends DatabaseTestCase
{
    public function testBookingAnAvailableSeatCreatesPendingBookingAndDecrementsAvailability(): void
    {
        $id = $this->book(6, '3C');
        $this->assertGreaterThan(0, $id);
        $this->assertSame(39, $this->available(6));
        $this->assertSame('pending', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$id]));
        $this->assertSame('250.00', $this->scalar('SELECT total_amount FROM bookings WHERE id = ?', [$id])); // server-side fare
        $this->assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM booking_status_logs WHERE booking_id = ?', [$id]));
    }

    public function testDuplicateSeatReservationIsRejected(): void
    {
        $this->book(6, '3C', 1);
        $this->expectException(ValidationException::class);
        try {
            $this->book(6, '3C', 2);
        } finally {
            $this->assertSame(39, $this->available(6)); // failed attempt must not change the counter
        }
    }

    public function testSeatOfACancelledBookingCanBeBookedAgain(): void
    {
        // Demo booking 8 (cancelled) used seat 1A on schedule 6.
        $id = $this->book(6, '1A', 1);
        $this->assertGreaterThan(8, $id);
    }

    public function testInvalidSeatLabelIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->book(6, '11A'); // a 40-seat bus has rows 1..10
    }

    public function testReservationOnACancelledTripIsRejected(): void
    {
        $this->c->get(ScheduleService::class)->changeStatus(4, 'cancelled'); // demo schedule 4 has no active bookings
        $this->expectException(ValidationException::class);
        $this->book(4, '2B');
    }

    public function testPassengerCannotHoldTwoSeatsOnOneTrip(): void
    {
        $this->book(6, '3C', 1);
        $this->expectException(ValidationException::class);
        $this->book(6, '3D', 1);
    }

    public function testCancellingReleasesTheSeatAndLogsTheChange(): void
    {
        $id = $this->book(6, '3C');
        $this->c->get(BookingService::class)->cancel($id, 'customer request', false, true);
        $this->assertSame(40, $this->available(6));
        $this->assertSame('cancelled', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$id]));
        $this->assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM booking_status_logs WHERE booking_id = ?', [$id]));
    }

    public function testCancellationInsideCutoffNeedsOverride(): void
    {
        $id = $this->book(6, '3C');
        $this->pdo->exec('UPDATE schedules SET departure_datetime = NOW() + INTERVAL 1 HOUR, arrival_datetime = NOW() + INTERVAL 3 HOUR WHERE id = 6');
        try {
            $this->c->get(BookingService::class)->cancel($id, 'late', false, false);
            $this->fail('Expected the cutoff rule to block the cancellation.');
        } catch (ValidationException) {
            $this->assertSame('pending', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$id]));
        }
        $this->c->get(BookingService::class)->cancel($id, 'late', false, true); // administrator override
        $this->assertSame('cancelled', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$id]));
    }

    public function testAvailableSeatsAlwaysEqualsCapacityMinusSeatHoldingBookings(): void
    {
        $a = $this->book(6, '2A', 1);
        $this->book(6, '2B', 2);
        $this->c->get(BookingService::class)->cancel($a, 'x', false, true);
        $this->book(6, '2A', 3);

        $held = (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE schedule_id = 6 AND status IN ('pending','confirmed','completed')");
        $this->assertSame(40 - $held, $this->available(6));
    }

    public function testSeedDataIsConsistent(): void
    {
        foreach ($this->pdo->query('SELECT s.id, s.available_seats, bu.capacity FROM schedules s JOIN buses bu ON bu.id = s.bus_id')->fetchAll() as $s) {
            $held = (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE schedule_id = ? AND status IN ('pending','confirmed','completed')", [$s['id']]);
            $this->assertSame((int) $s['capacity'] - $held, (int) $s['available_seats'], 'schedule ' . $s['id']);
        }
        $this->assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM tickets t JOIN bookings b ON b.id = t.booking_id WHERE t.ticket_status = 'valid' AND b.status <> 'confirmed'"));
    }
}
