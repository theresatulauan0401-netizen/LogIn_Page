<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\ValidationException;
use App\Models\Bus;
use App\Repositories\BusRepository;
use PDOException;

final class BusService
{
    public const STATUSES = ['active', 'maintenance', 'inactive'];

    public function __construct(
        private BusRepository $buses,
        private Database $db,
        private ActivityLogService $activity
    ) {
    }

    /** @return array{rows:array,paginator:Paginator} */
    public function paginate(array $filters, int $page): array
    {
        $paginator = Paginator::make($this->buses->count($filters), $page);
        return ['rows' => $this->buses->search($filters, $paginator->perPage, $paginator->offset()), 'paginator' => $paginator];
    }

    public function find(int $id): Bus
    {
        return $this->buses->find($id) ?? throw new HttpException(404, 'Bus not found.');
    }

    /** @return array{bus:Bus,upcoming:array} */
    public function details(int $id): array
    {
        return ['bus' => $this->find($id), 'upcoming' => $this->buses->upcomingSchedules($id)];
    }

    public function create(array $input): int
    {
        $d = $this->normalise($input);
        $this->assertUnique($d, null);
        try {
            $id = $this->buses->insert($d);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // lost a race against another insert
                throw ValidationException::general('Bus number or plate number already exists.');
            }
            throw $e;
        }
        $this->activity->log('create_bus', 'buses', "Bus {$d['bus_number']} created");
        return $id;
    }

    public function update(int $id, array $input): void
    {
        $d = $this->normalise($input);
        $this->assertUnique($d, $id);

        $this->db->transaction(function () use ($id, $d): void {
            $bus = $this->buses->find($id, true) ?? throw new HttpException(404, 'Bus not found.');
            $open = $this->buses->hasOpenSchedules($id);

            if ($open && $d['capacity'] !== $bus->capacity()) {
                throw ValidationException::field('capacity', 'Capacity cannot change while the bus has scheduled or boarding trips.');
            }
            if ($open && $d['status'] !== 'active') {
                throw ValidationException::field('status', 'This bus has scheduled or boarding trips. Cancel or complete them first.');
            }
            $this->buses->update($id, $d);
        });
        $this->activity->log('update_bus', 'buses', "Bus {$d['bus_number']} updated");
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw ValidationException::general('Invalid status.');
        }
        $number = $this->db->transaction(function () use ($id, $status): string {
            $bus = $this->buses->find($id, true) ?? throw new HttpException(404, 'Bus not found.');
            if ($status !== 'active' && $this->buses->hasOpenSchedules($id)) {
                throw ValidationException::general('This bus has scheduled or boarding trips. Cancel or complete them before deactivating it.');
            }
            $this->buses->setStatus($id, $status);
            return $bus->busNumber();
        });
        $this->activity->log('set_bus_status', 'buses', "Bus $number set to $status");
    }

    private function assertUnique(array $d, ?int $exceptId): void
    {
        $errors = [];
        if ($this->buses->busNumberTaken($d['bus_number'], $exceptId)) {
            $errors['bus_number'] = 'This bus number is already in use.';
        }
        if ($this->buses->plateTaken($d['plate_number'], $exceptId)) {
            $errors['plate_number'] = 'This plate number is already in use.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
    }

    private function normalise(array $in): array
    {
        $amenities = [];
        foreach (preg_split('/[,\r\n]+/', (string) ($in['amenities'] ?? '')) ?: [] as $item) {
            $item = trim($item);
            if ($item !== '' && mb_strlen($item) <= 50) {
                $amenities[mb_strtolower($item)] = $item;
            }
        }
        return [
            'bus_number'   => strtoupper(trim((string) $in['bus_number'])),
            'plate_number' => strtoupper(trim((string) $in['plate_number'])),
            'bus_type'     => trim((string) $in['bus_type']),
            'bus_model'    => ($in['bus_model'] ?? null) !== null ? trim((string) $in['bus_model']) : null,
            'capacity'     => (int) $in['capacity'],
            'amenities'    => array_slice(array_values($amenities), 0, 20),
            'status'       => (string) $in['status'],
        ];
    }
}
