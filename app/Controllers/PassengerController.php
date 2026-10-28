<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\PassengerService;

final class PassengerController extends BaseController
{
    private const RULES = [
        'first_name'    => 'required|max:100',
        'last_name'     => 'required|max:100',
        'email'         => 'email|max:190',
        'phone'         => 'required|phone',
        'gender'        => 'in:Male,Female,Other',
        'date_of_birth' => 'date',
    ];

    public function __construct(Request $request, Auth $auth, Session $session, Csrf $csrf, private PassengerService $passengers)
    {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('passengers.view');
        $filters = $this->filters(['q', 'status']);
        $result = $this->passengers->paginate($filters, $this->pageNumber());
        return $this->render('passengers/index', [
            'title' => 'Passengers', 'subtitle' => 'Passenger records and booking history.', 'breadcrumbs' => [['Passengers', null]],
            'filters' => $filters, 'rows' => $result['rows'], 'paginator' => $result['paginator'],
            'actions' => can('passengers.create') ? '<a class="btn btn-primary" href="' . e(url('/passengers/create')) . '"><i class="bi bi-person-plus me-1"></i>Add passenger</a>' : '',
        ]);
    }

    public function create(): string
    {
        $this->auth->authorize('passengers.create');
        return $this->render('passengers/form', ['title' => 'Add passenger', 'breadcrumbs' => [['Passengers', '/passengers'], ['Add', null]], 'passenger' => null]);
    }

    public function store(): void
    {
        $this->auth->authorize('passengers.create');
        $data = $this->validate(self::RULES);
        try {
            $id = $this->passengers->create($data);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Passenger added.');
        $this->redirect('/passengers/' . $id);
    }

    public function show(int $id): string
    {
        $this->auth->authorize('passengers.view');
        $profile = $this->passengers->profile($id);
        return $this->render('passengers/show', [
            'title' => $profile['passenger']->fullName(),
            'breadcrumbs' => [['Passengers', '/passengers'], [$profile['passenger']->code(), null]],
        ] + $profile);
    }

    public function edit(int $id): string
    {
        $this->auth->authorize('passengers.edit');
        $p = $this->passengers->find($id);
        return $this->render('passengers/form', [
            'title' => 'Edit ' . $p->fullName(),
            'breadcrumbs' => [['Passengers', '/passengers'], [$p->code(), '/passengers/' . $id], ['Edit', null]], 'passenger' => $p,
        ]);
    }

    public function update(int $id): void
    {
        $this->auth->authorize('passengers.edit');
        $data = $this->validate(self::RULES);
        try {
            $this->passengers->update($id, $data);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Passenger updated.');
        $this->redirect('/passengers/' . $id);
    }

    public function status(int $id): void
    {
        $this->auth->authorize('passengers.deactivate');
        $data = $this->validate(['status' => 'required|in:active,inactive']);
        try {
            $this->passengers->setStatus($id, $data['status']);
            $this->success('Passenger is now ' . $data['status'] . '.');
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->back('/passengers/' . $id);
    }
}
