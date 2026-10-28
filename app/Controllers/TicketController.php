<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\TicketService;

final class TicketController extends BaseController
{
    public function __construct(Request $request, Auth $auth, Session $session, Csrf $csrf, private TicketService $tickets)
    {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('tickets.view');
        $filters = $this->filters(['q', 'status']);
        $result = $this->tickets->paginate($filters, $this->pageNumber());
        return $this->render('tickets/index', [
            'title' => 'Tickets', 'subtitle' => 'Issued tickets. Tickets are created automatically when a payment confirms a booking.',
            'breadcrumbs' => [['Tickets', null]],
            'filters' => $filters, 'rows' => $result['rows'], 'paginator' => $result['paginator'],
            'actions' => can('tickets.validate') ? '<a class="btn btn-primary" href="' . e(url('/tickets/validate')) . '"><i class="bi bi-upc-scan me-1"></i>Validate ticket</a>' : '',
        ]);
    }

    public function show(int $id): string
    {
        $this->auth->authorize('tickets.view');
        $t = $this->tickets->detail($id);
        return $this->render('tickets/show', [
            'title' => 'Ticket ' . $t['ticket_number'],
            'breadcrumbs' => [['Tickets', '/tickets'], [$t['ticket_number'], null]], 'ticket' => $t,
        ]);
    }

    public function validateForm(): string
    {
        $this->auth->authorize('tickets.validate');
        $number = trim((string) $this->request->query('ticket_number', ''));
        $result = $number !== '' ? $this->tickets->lookup($number) : null;
        return $this->render('tickets/validate', [
            'title' => 'Validate ticket', 'breadcrumbs' => [['Tickets', '/tickets'], ['Validate', null]],
            'number' => $number, 'result' => $result,
        ]);
    }

    public function markUsed(int $id): void
    {
        $this->auth->authorize('tickets.validate');
        try {
            $this->tickets->markUsed($id);
            $this->success('Ticket marked as used.');
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->redirect('/tickets/' . $id);
    }

    public function issue(int $bookingId): void
    {
        $this->auth->authorize('tickets.issue');
        try {
            $id = $this->tickets->issue($bookingId);
            $this->success('Ticket issued.');
            $this->redirect('/tickets/' . $id);
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->redirect('/bookings/' . $bookingId);
    }
}
