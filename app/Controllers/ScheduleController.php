<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Repositories\ScheduleRepository;
use App\Services\ScheduleService;

final class ScheduleController extends BaseController
{
    // bus/route are optional here because they are not submitted when a trip is locked by bookings;
    // ScheduleService requires them whenever they are actually needed.
    private const RULES = [
        'bus_id'             => 'integer|min:1',
        'route_id'           => 'integer|min:1',
        'departure_datetime' => 'required|datetime',
        'arrival_datetime'   => 'datetime',
        'fare'               => 'decimal|max:1000000',
    ];

    public function __construct(
        Request $request,
        Auth $auth,
        Session $session,
        Csrf $csrf,
        private ScheduleService $schedules,
        private ScheduleRepository $scheduleRepo
    ) {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('schedules.view');
        $filters = $this->filters(['q', 'status', 'route_id', 'bus_id', 'from', 'to']);
        foreach (['from', 'to'] as $key) { // ignore malformed dates
            if ($filters[$key] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
                $filters[$key] = '';
            }
        }
        foreach (['route_id', 'bus_id'] as $key) {
            if ($filters[$key] !== '' && !ctype_digit($filters[$key])) {
                $filters[$key] = '';
            }
        }
        $result = $this->schedules->paginate($filters, $this->pageNumber());
        $options = $this->schedules->formOptions();

        return $this->render('schedules/index', [
            'title' => 'Schedules', 'subtitle' => 'Bus trips, fares and seat availability.', 'breadcrumbs' => [['Schedules', null]],
            'filters' => $filters, 'rows' => $result['rows'], 'paginator' => $result['paginator'],
            'routeOptions' => $options['routes'], 'busOptions' => $options['buses'],
            'actions' => can('schedules.create') ? '<a class="btn btn-primary" href="' . e(url('/schedules/create')) . '"><i class="bi bi-plus-lg me-1"></i>New schedule</a>' : '',
        ]);
    }

    public function create(): string
    {
        $this->auth->authorize('schedules.create');
        return $this->render('schedules/form', [
            'title' => 'New schedule', 'breadcrumbs' => [['Schedules', '/schedules'], ['New', null]],
            'schedule' => null, 'locked' => false,
        ] + $this->schedules->formOptions());
    }

    public function store(): void
    {
        $this->auth->authorize('schedules.create');
        $data = $this->validate(self::RULES);
        try {
            $id = $this->schedules->create($data);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Schedule created and open for booking.');
        $this->redirect('/schedules/' . $id);
    }

    public function show(int $id): string
    {
        $this->auth->authorize('schedules.view');
        $details = $this->schedules->details($id);
        return $this->render('schedules/show', [
            'title' => 'Schedule ' . $details['schedule']['schedule_code'],
            'breadcrumbs' => [['Schedules', '/schedules'], [$details['schedule']['schedule_code'], null]],
        ] + $details);
    }

    public function edit(int $id): string
    {
        $this->auth->authorize('schedules.edit');
        $schedule = $this->schedules->details($id)['schedule'];
        $locked = $this->schedules->activeBookingCount($id) > 0 || $this->scheduleRepo->seatHoldingCount($id) > 0;
        return $this->render('schedules/form', [
            'title' => 'Edit schedule ' . $schedule['schedule_code'],
            'breadcrumbs' => [['Schedules', '/schedules'], [$schedule['schedule_code'], '/schedules/' . $id], ['Edit', null]],
            'schedule' => $schedule, 'locked' => $locked,
        ] + $this->schedules->formOptions());
    }

    public function update(int $id): void
    {
        $this->auth->authorize('schedules.edit');
        $data = $this->validate(self::RULES);
        try {
            $this->schedules->update($id, $data);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Schedule updated.');
        $this->redirect('/schedules/' . $id);
    }

    public function status(int $id): void
    {
        $data = $this->validate(['status' => 'required|in:boarding,departed,completed,cancelled']);
        $this->auth->authorize($data['status'] === 'cancelled' ? 'schedules.cancel' : 'schedules.status');
        try {
            $this->schedules->changeStatus($id, $data['status']);
            $this->success('Trip status changed to ' . $data['status'] . '.');
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->back('/schedules/' . $id);
    }
}
