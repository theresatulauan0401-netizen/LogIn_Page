<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuthenticationService;

final class AuthController extends BaseController
{
    public function __construct(
        Request $request,
        Auth $auth,
        Session $session,
        Csrf $csrf,
        private AuthenticationService $authentication
    ) {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function root(): void
    {
        $this->redirect($this->auth->check() ? '/dashboard' : '/login');
    }

    public function showLogin(): string
    {
        return $this->render('auth/login', ['title' => 'Sign in'], 'layouts/auth');
    }

    public function login(): void
    {
        $data = $this->validate(
            ['email' => 'required|email|max:190', 'password' => 'required|max:255'],
            ['email' => 'E-mail'],
            '/login'
        );

        $result = $this->authentication->attempt($data['email'], (string) $this->request->post('password', ''), $this->request->ip());
        if (!$result['ok']) {
            $this->session->flashOld(['email' => $data['email']]);
            $this->error($result['message']);
            $this->redirect('/login');
        }
        $this->success($result['message']);
        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        $this->authentication->logout();
        $this->session->start(); // new clean session to carry the notice
        $this->success('You have been signed out.');
        $this->redirect('/login');
    }
}
