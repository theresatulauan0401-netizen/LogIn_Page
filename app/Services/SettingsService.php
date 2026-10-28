<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\SystemSettingRepository;

final class SettingsService
{
    public const CURRENCIES = ['PHP' => 'Philippine Peso (₱)', 'USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)'];
    public const KEYS = [
        'company_name', 'company_address', 'company_phone', 'company_email',
        'currency', 'timezone', 'booking_cancellation_enabled', 'cancellation_cutoff_hours',
    ];

    public function __construct(
        private SystemSettingRepository $settings,
        private Auth $auth,
        private ActivityLogService $activity
    ) {
    }

    /** @return array<string,?string> */
    public function all(): array
    {
        return $this->settings->allAsMap();
    }

    /** @param array<string,mixed> $in already validated */
    public function save(array $in): void
    {
        $values = [
            'company_name'    => trim((string) $in['company_name']),
            'company_address' => (string) ($in['company_address'] ?? ''),
            'company_phone'   => (string) ($in['company_phone'] ?? ''),
            'company_email'   => (string) ($in['company_email'] ?? ''),
            'currency'        => (string) $in['currency'],
            'timezone'        => (string) $in['timezone'],
            'booking_cancellation_enabled' => !empty($in['booking_cancellation_enabled']) ? '1' : '0',
            'cancellation_cutoff_hours'    => (string) (int) ($in['cancellation_cutoff_hours'] ?? 2),
        ];
        foreach ($values as $key => $value) {
            $this->settings->set($key, $value, $this->auth->id());
        }
        $this->activity->log('update_settings', 'settings', 'System settings updated');
    }
}
