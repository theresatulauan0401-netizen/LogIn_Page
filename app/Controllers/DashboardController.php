<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\DashboardService;

final class DashboardController extends BaseController
{
    public function __construct(
        Request $request,
        Auth $auth,
        Session $session,
        Csrf $csrf,
        private DashboardService $dashboard
    ) {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('dashboard.view');

        return $this->render('dashboard/index', [
            'title'       => 'Dashboard',
            'subtitle'    => 'Live overview of fleet, bookings and revenue.',
            'breadcrumbs' => [['Dashboard', null]],
            'data'        => $this->dashboard->overview(),
        ]);
    }
}
