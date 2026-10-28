<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;

/** For the login page: signed-in users go straight to the dashboard. */
final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth)
    {
    }

    public function handle(Request $request, array $args = []): void
    {
        if ($this->auth->check()) {
            redirect_to('/dashboard');
        }
    }
}
