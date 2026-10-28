<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\ValidationException;
use App\Models\Passenger;
use App\Repositories\PassengerRepository;
use PDOException;

final class PassengerService
{
    public function __construct(
        private PassengerRepository $passengers,
        private Database $db,
        private ActivityLogService $activity
    ) {
    }

    /** @return array{rows:array,paginator:Paginator} */
    public function paginate(array $filters, int $page): array
    {
        $paginator = Paginator::make($this->passengers->count($filters), $page);
        return ['rows' => $this->passengers->search($filters, $paginator->perPage, $paginator->offset()), 'paginator' => $paginator];
    }

    public function find(int $id): Passenger
    {
        return $this->passengers->find($id) ?? throw new HttpException(404, 'Passenger not found.');
    }

    /** @return array{passenger:Passenger,history:array,activeBookings:int} */
    public function profile(int $id): array
    {
        return [
            'passenger' => $this->find($id),
            'history' => $this->passengers->history($id),
            'activeBookings' => $this->passengers->activeBookingCount($id),
        ];
    }

    public function activeOptions(): array
    {
        return $this->passengers->activeOptions();
    }

    /** Joins an open transaction (used by BookingService when registering a new passenger inline). */
    public function create(array $input): int
    {
        $d = $this->normalise($input);
        if ($this->passengers->duplicateExists($d['first_name'], $d['last_name'], $d['phone'])) {
            throw ValidationException::general('A passenger with the same name and phone number already exists. Select them instead of creating a duplicate.');
        }
        $id = $this->db->transaction(function () use ($d): int {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $code = sprintf('PAX-%04d', $this->passengers->maxCodeNumber() + 1 + $attempt);
                try {
                    return $this->passengers->insert($code, $d);
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') {
                        throw $e;
                    }
                }
            }
            throw ValidationException::general('Could not generate a passenger code. Please try again.');
        });
        $this->activity->log('create_passenger', 'passengers', "Passenger #$id created");
        return $id;
    }

    public function update(int $id, array $input): void
    {
        $d = $this->normalise($input);
        $this->db->transaction(function () use ($id, $d): void {
            $this->passengers->find($id, true) ?? throw new HttpException(404, 'Passenger not found.');
            if ($this->passengers->duplicateExists($d['first_name'], $d['last_name'], $d['phone'], $id)) {
                throw ValidationException::general('Another passenger already has the same name and phone number.');
            }
            $this->passengers->update($id, $d);
        });
        $this->activity->log('update_passenger', 'passengers', "Passenger #$id updated");
    }

    /** Passengers are never deleted (booking history); they are deactivated. */
    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::general('Invalid status.');
        }
        $this->db->transaction(function () use ($id, $status): void {
            $this->passengers->find($id, true) ?? throw new HttpException(404, 'Passenger not found.');
            if ($status === 'inactive' && $this->passengers->activeBookingCount($id) > 0) {
                throw ValidationException::general('This passenger has pending or confirmed bookings and cannot be deactivated yet.');
            }
            $this->passengers->setStatus($id, $status);
        });
        $this->activity->log('set_passenger_status', 'passengers', "Passenger #$id set to $status");
    }

    private function normalise(array $in): array
    {
        $dob = $in['date_of_birth'] ?? null;
        if ($dob !== null && ($dob > date('Y-m-d') || $dob < '1900-01-01')) {
            throw ValidationException::field('date_of_birth', 'Date of birth is not realistic.');
        }
        return [
            'first_name' => trim((string) $in['first_name']),
            'last_name' => trim((string) $in['last_name']),
            'email' => ($in['email'] ?? null) !== null ? strtolower(trim((string) $in['email'])) : null,
            'phone' => trim((string) $in['phone']),
            'gender' => $in['gender'] ?? null,
            'date_of_birth' => $dob,
        ];
    }
}
