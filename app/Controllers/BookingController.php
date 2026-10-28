<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Repositories\RouteRepository;
use App\Services\BookingService;

final class BookingController extends BaseController
{
    public function __construct(
        Request $request, Auth $auth, Session $session, Csrf $csrf,
        private BookingService $bookings,
        private RouteRepository $routes
    ) {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('bookings.view');
        $filters = $this->filters(['q', 'status', 'from', 'to']);
        foreach (['from', 'to'] as $key) {
            if ($filters[$key] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
                $filters[$key] = '';
            }
        }
        $result = $this->bookings->paginate($filters, $this->pageNumber());
        return $this->render('bookings/index', [
            'title' => 'Bookings', 'subtitle' => 'Reservations, seats and payment status.', 'breadcrumbs' => [['Bookings', null]],
            'filters' => $filters, 'rows' => $result['rows'], 'paginator' => $result['paginator'],
            'actions' => can('bookings.create') ? '<a class="btn btn-primary" href="' . e(url('/bookings/create')) . '"><i class="bi bi-plus-lg me-1"></i>New booking</a>' : '',
        ]);
    }

    public function create(): string
    {
        $this->auth->authorize('bookings.create');
        $routeId = ctype_digit((string) $this->request->query('route_id', '')) ? (int) $this->request->query('route_id') : null;
        $scheduleId = ctype_digit((string) $this->request->query('schedule_id', '')) ? (int) $this->request->query('schedule_id') : null;
        return $this->render('bookings/create', [
            'title' => 'New booking', 'subtitle' => 'Choose a route, a trip, a seat and the passenger.',
            'breadcrumbs' => [['Bookings', '/bookings'], ['New', null]],
        ] + $this->bookings->formData($routeId, $scheduleId, $this->routes));
    }

    public function store(): void
    {
        $this->auth->authorize('bookings.create');
        $mode = $this->request->post('passenger_mode') === 'new' ? 'new' : 'existing';
        $rules = [
            'schedule_id'    => 'required|integer|min:1',
            'seat_number'    => ['required', 'regex:/^[0-9]{1,2}[A-D]$/'],
            'passenger_mode' => 'required|in:existing,new',
            'notes'          => 'max:1000',
        ];
        if ($mode === 'existing') {
            $rules['passenger_id'] = 'required|integer|min:1';
        } else {
            $rules += [
                'new_first_name' => 'required|max:100', 'new_last_name' => 'required|max:100', 'new_email' => 'email|max:190',
                'new_phone' => 'required|phone', 'new_gender' => 'in:Male,Female,Other', 'new_date_of_birth' => 'date',
            ];
        }
        $labels = ['seat_number' => 'Seat', 'passenger_id' => 'Passenger', 'new_first_name' => 'First name', 'new_last_name' => 'Last name',
                   'new_email' => 'E-mail', 'new_phone' => 'Phone', 'new_gender' => 'Gender', 'new_date_of_birth' => 'Date of birth'];
        $d = $this->validate($rules, $labels);

        try {
            $id = $this->bookings->create([
                'schedule_id' => (int) $d['schedule_id'], 'seat_number' => $d['seat_number'], 'mode' => $mode,
                'passenger_id' => $d['passenger_id'] ?? null, 'notes' => $d['notes'],
                'new' => $mode === 'new' ? [
                    'first_name' => $d['new_first_name'], 'last_name' => $d['new_last_name'], 'email' => $d['new_email'],
                    'phone' => $d['new_phone'], 'gender' => $d['new_gender'], 'date_of_birth' => $d['new_date_of_birth'],
                ] : null,
            ]);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Booking created. Record the payment to confirm it.');
        $this->redirect('/bookings/' . $id);
    }

    public function show(int $id): string
    {
        $this->auth->authorize('bookings.view');
        $details = $this->bookings->details($id);
        return $this->render('bookings/show', [
            'title' => 'Booking ' . $details['booking']['booking_reference'],
            'breadcrumbs' => [['Bookings', '/bookings'], [$details['booking']['booking_reference'], null]],
        ] + $details);
    }

    public function cancel(int $id): void
    {
        $this->auth->authorize('bookings.cancel');
        $d = $this->validate(['reason' => 'required|max:500']);
        $refund = $this->request->post('refund') === '1';
        if ($refund) {
            $this->auth->authorize('payments.refund');
        }
        try {
            $this->bookings->cancel($id, $d['reason'], $refund, $this->auth->can('bookings.cancel_override'));
            $this->success('Booking cancelled.' . ($refund ? ' The payment was marked as refunded.' : ''));
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->redirect('/bookings/' . $id);
    }

    public function finish(int $id): void
    {
        $this->auth->authorize('bookings.complete');
        $d = $this->validate(['outcome' => 'required|in:completed,no_show']);
        try {
            $this->bookings->finish($id, $d['outcome']);
            $this->success('Booking marked ' . str_replace('_', ' ', $d['outcome']) . '.');
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->redirect('/bookings/' . $id);
    }
}
