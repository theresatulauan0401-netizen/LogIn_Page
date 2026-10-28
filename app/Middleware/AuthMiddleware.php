<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\UserRepository;

/**
 * Blocks unauthenticated access AND re-checks the account on every request, so a
 * user who is deactivated or has their role changed is affected immediately.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth, private Session $session, private UserRepository $users)
    {
    }

    public function handle(Request $request, array $args = []): void
    {
        if (!$this->auth->check()) {
            $this->session->flash('warning', 'Please log in to continue.');
            redirect_to('/login');
        }

        $user = $this->users->findById((int) $this->auth->id());
        if ($user === null || !$user->isActive()) {
            $this->auth->logout();
            $this->session->start(); // fresh session so the notice can be shown
            $this->session->flash('danger', 'Your session has ended. Please log in again.');
            redirect_to('/login');
        }
        $this->auth->refresh($user->toIdentity());
    }
}
