<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Repositories\ActivityLogRepository;
use Throwable;

/**
 * Audit trail. NEVER pass passwords, session ids, CSRF tokens or card data in $description.
 * A logging failure must not break the business action, so errors go to the file log.
 */
final class ActivityLogService
{
    public function __construct(
        private ActivityLogRepository $logs,
        private Auth $auth,
        private Request $request
    ) {
    }

    public function log(string $action, string $module, ?string $description = null, ?int $userId = null): void
    {
        try {
            $this->logs->create(
                $userId ?? $this->auth->id(),
                mb_substr($action, 0, 100),
                mb_substr($module, 0, 100),
                $description !== null ? mb_substr($description, 0, 2000) : null,
                $this->request->ip()
            );
        } catch (Throwable $e) {
            Logger::error('Activity log write failed: ' . $e->getMessage());
        }
    }
}
