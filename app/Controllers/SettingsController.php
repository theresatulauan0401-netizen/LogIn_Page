<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\SettingsService;
use DateTimeZone;

final class SettingsController extends BaseController
{
    public function __construct(Request $request, Auth $auth, Session $session, Csrf $csrf, private SettingsService $settings)
    {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('settings.view');
        $timezones = array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers());
        return $this->render('settings/index', [
            'title' => 'Settings', 'subtitle' => 'Company information and booking options.', 'breadcrumbs' => [['Settings', null]],
            'values' => $this->settings->all(), 'currencies' => SettingsService::CURRENCIES, 'timezones' => $timezones,
            'editable' => can('settings.edit'),
        ]);
    }

    public function update(): void
    {
        $this->auth->authorize('settings.edit');
        $zones = implode(',', DateTimeZone::listIdentifiers());
        $d = $this->validate([
            'company_name'    => 'required|max:150',
            'company_address' => 'max:255',
            'company_phone'   => 'phone',
            'company_email'   => 'email|max:190',
            'currency'        => 'required|in:' . implode(',', array_keys(SettingsService::CURRENCIES)),
            'timezone'        => 'required|in:' . $zones,
            'cancellation_cutoff_hours' => 'required|integer|min:0|max:168',
        ], ['company_name' => 'Company name', 'company_address' => 'Address', 'company_phone' => 'Phone', 'company_email' => 'E-mail',
            'cancellation_cutoff_hours' => 'Cancellation cutoff']);
        $d['booking_cancellation_enabled'] = $this->request->post('booking_cancellation_enabled') === '1';
        $this->settings->save($d);
        $this->success('Settings saved.');
        $this->redirect('/settings');
    }
}
