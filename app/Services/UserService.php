<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\ValidationException;
use App\Models\User;
use App\Repositories\UserRepository;

/**
 * Account management with privilege-escalation protection (enforced HERE, not just in the form):
 *  - super_admin manages everyone; admin manages ONLY booking_staff accounts;
 *  - nobody may change their own role/status, and the last active Super Admin can never be removed;
 *  - passwords are only ever stored as password_hash() output and never logged.
 */
final class UserService
{
    public function __construct(
        private UserRepository $users,
        private Database $db,
        private Auth $auth,
        private ActivityLogService $activity
    ) {
    }

    public static function canManage(?string $actorRole, string $targetRole): bool
    {
        return $actorRole === 'super_admin' || ($actorRole === 'admin' && $targetRole === 'booking_staff');
    }

    /** @return string[] roles the current user may assign */
    public function assignableRoles(): array
    {
        return match ($this->auth->role()) {
            'super_admin' => User::ROLES,
            'admin'       => [User::ROLE_BOOKING_STAFF],
            default       => [],
        };
    }

    /** @return array{rows:array,paginator:Paginator} */
    public function paginate(array $filters, int $page): array
    {
        $paginator = Paginator::make($this->users->count($filters), $page);
        return ['rows' => $this->users->search($filters, $paginator->perPage, $paginator->offset()), 'paginator' => $paginator];
    }

    public function findManageable(int $id): User
    {
        $user = $this->users->findById($id) ?? throw new HttpException(404, 'User not found.');
        if (!self::canManage($this->auth->role(), $user->role())) {
            throw new HttpException(403);
        }
        return $user;
    }

    public function create(array $in): int
    {
        $role = (string) $in['role'];
        $this->assertAssignable($role);
        $email = strtolower(trim((string) $in['email']));
        if ($this->users->emailTaken($email)) {
            throw ValidationException::field('email', 'This e-mail address is already registered.');
        }
        $id = $this->users->insert(trim((string) $in['full_name']), $email, password_hash((string) $in['password'], PASSWORD_DEFAULT), $role);
        $this->activity->log('create_user', 'users', "User #$id created with role $role");
        return $id;
    }

    public function update(int $id, array $in): void
    {
        $newRole = (string) $in['role'];
        $email = strtolower(trim((string) $in['email']));
        $change = $this->db->transaction(function () use ($id, $in, $newRole, $email): string {
            $target = $this->users->findById($id, true) ?? throw new HttpException(404, 'User not found.');
            if (!self::canManage($this->auth->role(), $target->role())) {
                throw new HttpException(403);
            }
            $this->assertAssignable($newRole);
            if ($id === $this->auth->id() && $newRole !== $target->role()) {
                throw ValidationException::field('role', 'You cannot change your own role.');
            }
            if ($this->users->emailTaken($email, $id)) {
                throw ValidationException::field('email', 'This e-mail address is already registered.');
            }
            if ($target->role() === 'super_admin' && $newRole !== 'super_admin' && $target->isActive()
                && $this->users->countActiveSuperAdmins() <= 1) {
                throw ValidationException::field('role', 'The last active Super Admin cannot be demoted.');
            }
            $this->users->update($id, trim((string) $in['full_name']), $email, $newRole);
            return $target->role() !== $newRole ? "role changed {$target->role()} -> $newRole" : 'profile updated';
        });
        $this->activity->log('update_user', 'users', "User #$id: $change");
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::general('Invalid status.');
        }
        $this->db->transaction(function () use ($id, $status): void {
            $target = $this->users->findById($id, true) ?? throw new HttpException(404, 'User not found.');
            if (!self::canManage($this->auth->role(), $target->role())) {
                throw new HttpException(403);
            }
            if ($id === $this->auth->id()) {
                throw ValidationException::general('You cannot change your own status.');
            }
            if ($status === 'inactive' && $target->role() === 'super_admin' && $target->isActive() && $this->users->countActiveSuperAdmins() <= 1) {
                throw ValidationException::general('The last active Super Admin cannot be deactivated.');
            }
            $this->users->setStatus($id, $status);
        });
        $this->activity->log('set_user_status', 'users', "User #$id set to $status");
    }

    /** Admin-initiated reset of ANOTHER user's password. */
    public function resetPassword(int $id, string $newPassword): void
    {
        $target = $this->findManageable($id);
        if ($target->id() === $this->auth->id()) {
            throw ValidationException::general('Use "My password" to change your own password.');
        }
        $this->users->updatePasswordHash($id, password_hash($newPassword, PASSWORD_DEFAULT));
        $this->activity->log('reset_user_password', 'users', "Password reset for user #$id"); // never the password itself
    }

    public function changeOwnPassword(string $current, string $new): void
    {
        $me = $this->users->findById((int) $this->auth->id()) ?? throw new HttpException(403);
        if (!password_verify($current, $me->passwordHash())) {
            throw ValidationException::field('current_password', 'Your current password is incorrect.');
        }
        if (hash_equals($current, $new)) {
            throw ValidationException::field('password', 'The new password must be different from the current one.');
        }
        $this->users->updatePasswordHash($me->id(), password_hash($new, PASSWORD_DEFAULT));
        $this->activity->log('change_own_password', 'users', 'User changed their own password');
    }

    private function assertAssignable(string $role): void
    {
        if (!in_array($role, $this->assignableRoles(), true)) {
            throw ValidationException::field('role', 'You are not allowed to assign that role.');
        }
    }
}
