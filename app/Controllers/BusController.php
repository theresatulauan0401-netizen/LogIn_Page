<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\BusService;

final class BusController extends BaseController
{
    private const RULES = [
        'bus_number'   => 'required|max:50|alpha_dash',
        'plate_number' => ['required', 'regex:/^[A-Za-z0-9][A-Za-z0-9\- ]{1,28}[A-Za-z0-9]$/'],
        'bus_type'     => 'required|max:100',
        'bus_model'    => 'max:100',
        'capacity'     => 'required|integer|min:1|max:100',
        'amenities'    => 'max:500',
        'status'       => 'required|in:active,maintenance,inactive',
    ];

    public function __construct(Request $request, Auth $auth, Session $session, Csrf $csrf, private BusService $buses)
    {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('buses.view');
        $filters = $this->filters(['q', 'status']);
        $result = $this->buses->paginate($filters, $this->pageNumber());

        return $this->render('buses/index', [
            'title' => 'Buses', 'subtitle' => 'Manage the fleet.', 'breadcrumbs' => [['Buses', null]],
            'filters' => $filters, 'rows' => $result['rows'], 'paginator' => $result['paginator'],
            'actions' => can('buses.create') ? '<a class="btn btn-primary" href="' . e(url('/buses/create')) . '"><i class="bi bi-plus-lg me-1"></i>Add bus</a>' : '',
        ]);
    }

    public function create(): string
    {
        $this->auth->authorize('buses.create');
        return $this->render('buses/form', [
            'title' => 'Add bus', 'breadcrumbs' => [['Buses', '/buses'], ['Add', null]], 'bus' => null,
        ]);
    }

    public function store(): void
    {
        $this->auth->authorize('buses.create');
        $data = $this->validate(self::RULES);
        try {
            $id = $this->buses->create($data);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Bus added.');
        $this->redirect('/buses/' . $id);
    }

    public function show(int $id): string
    {
        $this->auth->authorize('buses.view');
        $details = $this->buses->details($id);
        return $this->render('buses/show', [
            'title' => 'Bus ' . $details['bus']->busNumber(),
            'breadcrumbs' => [['Buses', '/buses'], [$details['bus']->busNumber(), null]],
        ] + $details);
    }

    public function edit(int $id): string
    {
        $this->auth->authorize('buses.edit');
        $bus = $this->buses->find($id);
        return $this->render('buses/form', [
            'title' => 'Edit bus ' . $bus->busNumber(),
            'breadcrumbs' => [['Buses', '/buses'], [$bus->busNumber(), '/buses/' . $id], ['Edit', null]], 'bus' => $bus,
        ]);
    }

    public function update(int $id): void
    {
        $this->auth->authorize('buses.edit');
        $data = $this->validate(self::RULES);
        try {
            $this->buses->update($id, $data);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Bus updated.');
        $this->redirect('/buses/' . $id);
    }

    public function status(int $id): void
    {
        $this->auth->authorize('buses.deactivate');
        $data = $this->validate(['status' => 'required|in:active,maintenance,inactive']);
        try {
            $this->buses->setStatus($id, $data['status']);
            $this->success('Bus status changed to ' . $data['status'] . '.');
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->back('/buses/' . $id);
    }
}
