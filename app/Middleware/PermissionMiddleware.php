<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

/** Usage: 'can:buses.view' - checks config/permissions.php for the user's role. */
final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth)
    {
    }

    public function handle(Request $request, array $args = []): void
    {
        foreach ($args as $permission) {
            if (!$this->auth->can($permission)) {
                throw new HttpException(403);
            }
        }
    }
}
