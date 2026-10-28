<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\UserService;

final class UserController extends BaseController
{
    public function __construct(Request $request, Auth $auth, Session $session, Csrf $csrf, private UserService $users)
    {
        parent::__construct($request, $auth, $session, $csrf);
    }

    public function index(): string
    {
        $this->auth->authorize('users.view');
        $filters = $this->filters(['q', 'role', 'status']);
        $result = $this->users->paginate($filters, $this->pageNumber());
        return $this->render('users/index', [
            'title' => 'Users', 'subtitle' => 'Staff accounts and roles.', 'breadcrumbs' => [['Users', null]],
            'filters' => $filters, 'rows' => $result['rows'], 'paginator' => $result['paginator'],
            'actions' => can('users.manage_staff') ? '<a class="btn btn-primary" href="' . e(url('/users/create')) . '"><i class="bi bi-person-plus me-1"></i>Add user</a>' : '',
        ]);
    }

    public function create(): string
    {
        $this->auth->authorize('users.manage_staff');
        return $this->render('users/form', [
            'title' => 'Add user', 'breadcrumbs' => [['Users', '/users'], ['Add', null]], 'user' => null, 'roles' => $this->users->assignableRoles(),
        ]);
    }

    public function store(): void
    {
        $this->auth->authorize('users.manage_staff');
        $d = $this->validate([
            'full_name' => 'required|max:150', 'email' => 'required|email|max:190',
            'role' => 'required|in:super_admin,admin,booking_staff',
            'password' => 'required|password', 'password_confirmation' => 'required|same:password',
        ], ['full_name' => 'Full name', 'password_confirmation' => 'Password confirmation']);
        try {
            $id = $this->users->create($d);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('User created.');
        $this->redirect('/users');
    }

    public function edit(int $id): string
    {
        $this->auth->authorize('users.manage_staff');
        $user = $this->users->findManageable($id);
        return $this->render('users/form', [
            'title' => 'Edit ' . $user->fullName(), 'breadcrumbs' => [['Users', '/users'], [$user->fullName(), null]],
            'user' => $user, 'roles' => $this->users->assignableRoles(),
        ]);
    }

    public function update(int $id): void
    {
        $this->auth->authorize('users.manage_staff');
        $d = $this->validate([
            'full_name' => 'required|max:150', 'email' => 'required|email|max:190',
            'role' => 'required|in:super_admin,admin,booking_staff',
        ], ['full_name' => 'Full name']);
        try {
            $this->users->update($id, $d);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('User updated.');
        $this->redirect('/users');
    }

    public function status(int $id): void
    {
        $this->auth->authorize('users.manage_staff');
        $d = $this->validate(['status' => 'required|in:active,inactive']);
        try {
            $this->users->setStatus($id, $d['status']);
            $this->success('User is now ' . $d['status'] . '.');
        } catch (ValidationException $e) {
            $this->error($e->getMessage());
        }
        $this->redirect('/users');
    }

    public function resetForm(int $id): string
    {
        $this->auth->authorize('users.manage_staff');
        $user = $this->users->findManageable($id);
        return $this->render('users/reset', [
            'title' => 'Reset password', 'breadcrumbs' => [['Users', '/users'], [$user->fullName(), null]], 'user' => $user,
        ]);
    }

    public function reset(int $id): void
    {
        $this->auth->authorize('users.manage_staff');
        $d = $this->validate(['password' => 'required|password', 'password_confirmation' => 'required|same:password'],
            ['password_confirmation' => 'Password confirmation']);
        try {
            $this->users->resetPassword($id, $d['password']);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Password reset. Give the new password to the user through a secure channel.');
        $this->redirect('/users');
    }

    public function accountForm(): string
    {
        return $this->render('users/account', ['title' => 'My password', 'breadcrumbs' => [['My password', null]]]);
    }

    public function changeOwnPassword(): void
    {
        $d = $this->validate([
            'current_password' => 'required|max:255', 'password' => 'required|password', 'password_confirmation' => 'required|same:password',
        ], ['current_password' => 'Current password', 'password_confirmation' => 'Password confirmation']);
        try {
            $this->users->changeOwnPassword((string) $this->request->post('current_password'), $d['password']);
        } catch (ValidationException $e) {
            $this->failWith($e);
        }
        $this->success('Your password was changed.');
        $this->redirect('/dashboard');
    }
}
