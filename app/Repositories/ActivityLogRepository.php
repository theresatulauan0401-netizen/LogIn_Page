<?php

declare(strict_types=1);

namespace App\Repositories;

final class ActivityLogRepository extends Repository
{
    public function create(?int $userId, string $action, string $module, ?string $description, ?string $ip): void
    {
        $this->run(
            'INSERT INTO activity_logs (user_id, action, module, description, ip_address)
             VALUES (:uid, :action, :module, :description, :ip)',
            [':uid' => $userId, ':action' => $action, ':module' => $module, ':description' => $description, ':ip' => $ip]
        );
    }
}
