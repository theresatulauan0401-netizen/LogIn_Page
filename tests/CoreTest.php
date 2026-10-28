<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Services\SeatLayout;
use App\Services\UserService;
use PHPUnit\Framework\TestCase;

/** Pure unit tests: no database needed. */
final class CoreTest extends TestCase
{
    public function testValidatorRejectsBadInputAndNormalisesGoodInput(): void
    {
        $v = Validator::make(['email' => 'nope', 'fare' => '-5', 'status' => 'x', 'when' => '2030-01-02T10:30'], [
            'email' => 'required|email', 'fare' => 'required|decimal', 'status' => 'in:a,b', 'when' => 'datetime', 'name' => 'required',
        ]);
        $this->assertTrue($v->fails());
        $this->assertSame(['email', 'fare', 'status', 'name'], array_keys($v->errors()));

        $ok = Validator::make(['when' => '2030-01-02T10:30', 'note' => ''], ['when' => 'datetime', 'note' => 'max:5']);
        $this->assertTrue($ok->passes());
        $this->assertSame('2030-01-02 10:30:00', $ok->validated()['when']);
        $this->assertNull($ok->validated()['note']);
    }

    public function testPasswordPolicy(): void
    {
        $this->assertTrue(Validator::make(['p' => 'short1A'], ['p' => 'password'])->fails());
        $this->assertTrue(Validator::make(['p' => 'alllowercase123'], ['p' => 'password'])->fails());
        $this->assertTrue(Validator::make(['p' => 'GoodPassw0rdHere'], ['p' => 'password'])->passes());
    }

    public function testCsrfTokenMustMatch(): void
    {
        $_SESSION = [];
        $csrf = new Csrf(new Session(['name' => 'T']));
        $token = $csrf->token();
        $this->assertTrue($csrf->verify($token));
        $this->assertFalse($csrf->verify('forged'));
        $this->assertFalse($csrf->verify(null));
    }

    public function testRolePermissions(): void
    {
        $perms = (array) Config::get('permissions');
        $session = new Session(['name' => 'T']);
        $as = function (string $role) use ($session, $perms): Auth {
            $_SESSION = [];
            $session->set('_auth_user', ['id' => 1, 'name' => 'x', 'email' => 'x@x', 'role' => $role]);
            return new Auth($session, $perms);
        };
        $this->assertTrue($as('super_admin')->can('settings.edit'));
        $this->assertFalse($as('admin')->can('settings.edit'));
        $this->assertTrue($as('admin')->can('buses.create'));
        $this->assertFalse($as('booking_staff')->can('buses.create'));
        $this->assertTrue($as('booking_staff')->can('bookings.create'));
        $this->assertFalse($as('booking_staff')->can('payments.refund'));
        $this->assertFalse($as('booking_staff')->can('users.view'));
    }

    public function testPrivilegeEscalationRules(): void
    {
        $this->assertTrue(UserService::canManage('super_admin', 'admin'));
        $this->assertTrue(UserService::canManage('admin', 'booking_staff'));
        $this->assertFalse(UserService::canManage('admin', 'admin'));
        $this->assertFalse(UserService::canManage('admin', 'super_admin'));
        $this->assertFalse(UserService::canManage('booking_staff', 'booking_staff'));
    }

    public function testSeatLayout(): void
    {
        $this->assertSame(['1A', '1B', '1C', '1D', '2A'], SeatLayout::labels(5));
        $this->assertTrue(SeatLayout::isValid('10D', 40));
        $this->assertFalse(SeatLayout::isValid('11A', 40));
        $this->assertFalse(SeatLayout::isValid('1E', 40));
    }
}
