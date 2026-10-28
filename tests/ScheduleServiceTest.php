<?php

declare(strict_types=1);

namespace Tests;

use App\Core\ValidationException;
use App\Services\ScheduleService;

final class ScheduleServiceTest extends DatabaseTestCase
{
    private function create(array $over = []): int
    {
        return $this->c->get(ScheduleService::class)->create($over + [
            'bus_id' => 1, 'route_id' => 2,
            'departure_datetime' => date('Y-m-d', strtotime('+10 days')) . ' 06:00:00',
            'arrival_datetime' => date('Y-m-d', strtotime('+10 days')) . ' 08:00:00', 'fare' => '250.00',
        ]);
    }

    public function testValidScheduleIsCreatedWithFullSeatAvailability(): void
    {
        $id = $this->create();
        $this->assertSame(45, $this->available($id));
    }

    public function testOverlappingTripForTheSameBusIsRejected(): void
    {
        $this->create();
        $this->expectException(ValidationException::class);
        $this->create(['departure_datetime' => date('Y-m-d', strtotime('+10 days')) . ' 07:00:00', 'arrival_datetime' => date('Y-m-d', strtotime('+10 days')) . ' 09:00:00']);
    }

    public function testBackToBackTripsAreAllowed(): void
    {
        $this->create();
        $id = $this->create(['departure_datetime' => date('Y-m-d', strtotime('+10 days')) . ' 08:00:00', 'arrival_datetime' => date('Y-m-d', strtotime('+10 days')) . ' 10:00:00']);
        $this->assertGreaterThan(0, $id);
    }

    public function testArrivalMustBeAfterDeparture(): void
    {
        $this->expectException(ValidationException::class);
        $this->create(['arrival_datetime' => date('Y-m-d', strtotime('+10 days')) . ' 05:00:00']);
    }

    public function testDepartureMustBeInTheFuture(): void
    {
        $this->expectException(ValidationException::class);
        $this->create(['departure_datetime' => '2020-01-01 06:00:00', 'arrival_datetime' => '2020-01-01 08:00:00']);
    }

    public function testBusInMaintenanceCannotBeScheduled(): void
    {
        $this->expectException(ValidationException::class);
        $this->create(['bus_id' => 3]);
    }

    public function testTripWithActiveBookingsCannotBeCancelled(): void
    {
        $this->expectException(ValidationException::class);
        $this->c->get(ScheduleService::class)->changeStatus(1, 'cancelled'); // demo bookings 1 and 2 are confirmed
    }
}
