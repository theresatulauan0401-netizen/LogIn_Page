<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SystemSetting;

final class SystemSettingRepository extends Repository
{
    /** @return array<string,?string> */
    public function allAsMap(): array
    {
        $map = [];
        foreach ($this->all() as $setting) {
            $map[$setting->key()] = $setting->value();
        }
        return $map;
    }

    /** @return SystemSetting[] */
    public function all(): array
    {
        $rows = $this->run('SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key')->fetchAll();
        return array_map([SystemSetting::class, 'fromRow'], $rows);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->run('SELECT setting_value FROM system_settings WHERE setting_key = :k', [':k' => $key])->fetchColumn();
        return $value === false || $value === null ? $default : (string) $value;
    }

    public function set(string $key, ?string $value, ?int $userId): void
    {
        $this->run(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (:k, :v, :u)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            [':k' => $key, ':v' => $value, ':u' => $userId]
        );
    }
}
