<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\RouteService;

final class RouteController extends BaseController
{
    private const RULES = [
        'origin'      => 'required|max:150',
        'destination' => 'required|max:150',
        'distance_km' => 'decimal|max:99999',
        'estimated_duration_minutes' => 'integer|min:1|max:5000',
        'base_fare'   => 'required|decimal|max:1000000',
        'status'      => 'required|in:active,inactive',
    ];

    public function __construct(Request $request, Auth $auth, Session $session, Csrf $csrf, private RouteService $routes)
    {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('routes.view');
        $filters = $this->filters(['q', 'status']);
        $result = $this->routes->paginate($filters, $this->pageNumber());

        return $this->render('routes/index', [
            'title' => 'Routes', 'subtitle' => 'Origins, destinations and base fares.', 'breadcrumbs' => [['Routes', null]],
            'filters' => $filters, 'rows' => $result['rows'], 'paginator' => $result['paginator'],
            'actions' => can('routes.create') ? '<a class="btn btn-primary" href="' . e(url('/routes/create')) . '"><i class="bi bi-plus-lg me-1"></i>Add route</a>' : '',
        ]);
    }

    public function create(): string
    {
        $this->auth->authorize('routes.create');
        return $this->render('routes/form', ['title' => 'Add route', 'breadcrumbs' => [['Routes', '/routes'], ['Add', null]], 'route' => null]);
    }

    public function store(): void
    {
        $this->auth->authorize('routes.create');
        $data = $this->validate(self::RULES);
        try {
            $id = $this->routes->create($data);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Route added.');
        $this->redirect('/routes/' . $id);
    }

    public function show(int $id): string
    {
        $this->auth->authorize('routes.view');
        $details = $this->routes->details($id);
        return $this->render('routes/show', [
            'title' => 'Route ' . $details['route']->routeCode(),
            'breadcrumbs' => [['Routes', '/routes'], [$details['route']->routeCode(), null]],
        ] + $details);
    }

    public function edit(int $id): string
    {
        $this->auth->authorize('routes.edit');
        $route = $this->routes->find($id);
        return $this->render('routes/form', [
            'title' => 'Edit route ' . $route->routeCode(),
            'breadcrumbs' => [['Routes', '/routes'], [$route->routeCode(), '/routes/' . $id], ['Edit', null]], 'route' => $route,
        ]);
    }

    public function update(int $id): void
    {
        $this->auth->authorize('routes.edit');
        $data = $this->validate(self::RULES);
        try {
            $this->routes->update($id, $data);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Route updated. New fares apply to schedules created from now on.');
        $this->redirect('/routes/' . $id);
    }

    public function status(int $id): void
    {
        $this->auth->authorize('routes.deactivate');
        $data = $this->validate(['status' => 'required|in:active,inactive']);
        try {
            $this->routes->setStatus($id, $data['status']);
            $this->success('Route is now ' . $data['status'] . '.');
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->back('/routes/' . $id);
    }
}
