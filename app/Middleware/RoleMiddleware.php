<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

/** Usage: 'role:super_admin,admin'. Server-side role gate (run after 'auth'). */
final class RoleMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth)
    {
    }

    public function handle(Request $request, array $args = []): void
    {
        if (!$this->auth->hasRole(...$args)) {
            throw new HttpException(403);
        }
    }
}
