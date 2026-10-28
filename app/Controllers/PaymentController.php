<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\PaymentService;

final class PaymentController extends BaseController
{
    public function __construct(Request $request, Auth $auth, Session $session, Csrf $csrf, private PaymentService $payments)
    {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('payments.view');
        $filters = $this->filters(['q', 'status', 'method', 'from', 'to']);
        foreach (['from', 'to'] as $key) {
            if ($filters[$key] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
                $filters[$key] = '';
            }
        }
        $result = $this->payments->paginate($filters, $this->pageNumber());
        return $this->render('payments/index', [
            'title' => 'Payments', 'subtitle' => 'Recorded payments (cash and manually verified references).',
            'breadcrumbs' => [['Payments', null]],
            'filters' => $filters, 'rows' => $result['rows'], 'paginator' => $result['paginator'],
        ]);
    }

    public function create(): string
    {
        $this->auth->authorize('payments.record');
        $bookingId = (int) $this->request->query('booking_id', 0);
        if ($bookingId < 1) {
            throw new HttpException(404, 'Open a pending booking and choose "Record payment".');
        }
        return $this->render('payments/form', [
            'title' => 'Record payment',
            'breadcrumbs' => [['Payments', '/payments'], ['Record', null]],
            'booking' => $this->payments->bookingForForm($bookingId),
        ]);
    }

    public function store(): void
    {
        $this->auth->authorize('payments.record');
        $d = $this->validate([
            'booking_id' => 'required|integer|min:1',
            'method' => 'required|in:cash,bank_transfer,e_wallet,card',
            'payment_status' => 'required|in:successful,pending,failed',
            'amount' => 'required|decimal|max:10000000',
            'transaction_reference' => 'max:150',
        ], ['payment_status' => 'Payment result', 'transaction_reference' => 'Reference']);
        $d['verified'] = $this->request->post('verified') === '1';

        try {
            $id = $this->payments->record($d + ['booking_id' => (int) $d['booking_id']]);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success($d['payment_status'] === 'successful' ? 'Payment recorded, booking confirmed and ticket issued.' : 'Payment attempt recorded.');
        $this->redirect('/payments/' . $id);
    }

    public function show(int $id): string
    {
        $this->auth->authorize('payments.view');
        $p = $this->payments->detail($id);
        return $this->render('payments/show', [
            'title' => 'Payment ' . $p['payment_reference'],
            'breadcrumbs' => [['Payments', '/payments'], [$p['payment_reference'], null]], 'payment' => $p,
        ]);
    }

    public function receipt(int $id): string
    {
        $this->auth->authorize('payments.view');
        $p = $this->payments->detail($id);
        if (!in_array($p['payment_status'], ['successful', 'refunded'], true)) {
            throw new HttpException(404, 'A receipt is only available for successful or refunded payments.');
        }
        return $this->render('payments/receipt', [
            'title' => 'Receipt ' . $p['payment_reference'],
            'breadcrumbs' => [['Payments', '/payments'], [$p['payment_reference'], '/payments/' . $id], ['Receipt', null]], 'payment' => $p,
        ]);
    }

    public function resolve(int $id): void
    {
        $this->auth->authorize('payments.record');
        $d = $this->validate(['outcome' => 'required|in:successful,failed', 'transaction_reference' => 'max:150']);
        try {
            $this->payments->resolve($id, $d['outcome'], $d['transaction_reference'], $this->request->post('verified') === '1');
            $this->success($d['outcome'] === 'successful' ? 'Payment verified, booking confirmed and ticket issued.' : 'Payment marked as failed.');
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->redirect('/payments/' . $id);
    }

    public function refund(int $id): void
    {
        $this->auth->authorize('payments.refund');
        $d = $this->validate(['reason' => 'required|max:500']);
        try {
            $this->payments->refund($id, $d['reason']);
            $this->success('Payment refunded and recorded in the audit trail.');
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->redirect('/payments/' . $id);
    }
}
