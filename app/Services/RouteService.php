<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\ValidationException;
use App\Models\Route;
use App\Repositories\RouteRepository;
use PDOException;

final class RouteService
{
    public function __construct(
        private RouteRepository $routes,
        private Database $db,
        private ActivityLogService $activity
    ) {
    }

    /** @return array{rows:array,paginator:Paginator} */
    public function paginate(array $filters, int $page): array
    {
        $paginator = Paginator::make($this->routes->count($filters), $page);
        return ['rows' => $this->routes->search($filters, $paginator->perPage, $paginator->offset()), 'paginator' => $paginator];
    }

    public function find(int $id): Route
    {
        return $this->routes->find($id) ?? throw new HttpException(404, 'Route not found.');
    }

    /** @return array{route:Route,upcoming:array} */
    public function details(int $id): array
    {
        return ['route' => $this->find($id), 'upcoming' => $this->routes->upcomingSchedules($id)];
    }

    public function create(array $input): int
    {
        $d = $this->normalise($input);
        // Route codes are generated (RT-001, RT-002 ...); retry if two staff save at once.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = sprintf('RT-%03d', $this->routes->maxCodeNumber() + 1 + $attempt);
            try {
                $id = $this->routes->insert($code, $d);
                $this->activity->log('create_route', 'routes', "Route $code ({$d['origin']} to {$d['destination']}) created");
                return $id;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
        throw ValidationException::general('Could not generate a unique route code. Please try again.');
    }

    public function update(int $id, array $input): void
    {
        $d = $this->normalise($input);
        $code = $this->db->transaction(function () use ($id, $d): string {
            $route = $this->routes->find($id, true) ?? throw new HttpException(404, 'Route not found.');
            if ($d['status'] !== 'active' && $this->routes->hasOpenSchedules($id)) {
                throw ValidationException::field('status', 'This route has scheduled or boarding trips. Cancel or complete them first.');
            }
            $this->routes->update($id, $d);
            return $route->routeCode();
        });
        $this->activity->log('update_route', 'routes', "Route $code updated");
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::general('Invalid status.');
        }
        $code = $this->db->transaction(function () use ($id, $status): string {
            $route = $this->routes->find($id, true) ?? throw new HttpException(404, 'Route not found.');
            if ($status === 'inactive' && $this->routes->hasOpenSchedules($id)) {
                throw ValidationException::general('This route has scheduled or boarding trips. Cancel or complete them before deactivating it.');
            }
            $this->routes->setStatus($id, $status);
            return $route->routeCode();
        });
        $this->activity->log('set_route_status', 'routes', "Route $code set to $status");
    }

    private function normalise(array $in): array
    {
        $origin = trim((string) $in['origin']);
        $destination = trim((string) $in['destination']);
        if (mb_strtolower($origin) === mb_strtolower($destination)) {
            throw ValidationException::field('destination', 'Origin and destination must be different.');
        }
        return [
            'origin' => $origin,
            'destination' => $destination,
            'distance_km' => ($in['distance_km'] ?? null) !== null ? (string) $in['distance_km'] : null,
            'estimated_duration_minutes' => ($in['estimated_duration_minutes'] ?? null) !== null ? (int) $in['estimated_duration_minutes'] : null,
            'base_fare' => (string) $in['base_fare'],
            'status' => (string) $in['status'],
        ];
    }
}
