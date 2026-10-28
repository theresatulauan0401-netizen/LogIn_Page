<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration base class: rebuilds a throw-away database from database/database.sql before every
 * test, inserts one admin user and signs them in on a fake (non-started) Session.
 * NEVER point TEST_DB_NAME at your real database: it is dropped and recreated.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Container $c;
    protected PDO $pdo;
    protected int $userId;

    protected function setUp(): void
    {
        $name = (string) (getenv('TEST_DB_NAME') ?: '');
        if ($name === '' || !str_contains($name, 'test')) {
            $this->markTestSkipped('Set TEST_DB_NAME to a database whose name contains "test".');
        }
        $cfg = (array) Config::get('database');
        try {
            $root = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], $cfg['port']), (string) $cfg['username'], (string) $cfg['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL is not reachable: ' . $e->getMessage());
        }
        $root->exec("DROP DATABASE IF EXISTS `$name`");
        $sql = str_replace('bus_reservation_system', $name, (string) file_get_contents(BASE_PATH . '/database/database.sql'));
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
            $root->exec($statement);
        }
        $root->exec('USE `' . $name . '`');
        $this->pdo = $root;

        $this->pdo->exec("INSERT INTO users (full_name, email, password, role) VALUES ('Test Admin', 'admin@test.local', 'x', 'admin')");
        $this->userId = (int) $this->pdo->lastInsertId();

        $db = new Database(['database' => $name] + $cfg, 'Asia/Manila');
        $session = new Session(['name' => 'TEST', 'lifetime' => 7200]);
        $_SESSION = [];
        $session->set('_auth_user', ['id' => $this->userId, 'name' => 'Test Admin', 'email' => 'admin@test.local', 'role' => 'admin']);

        $this->c = new Container();
        Container::setInstance($this->c);
        $this->c->set(Database::class, $db);
        $this->c->set(PDO::class, $db->connection());
        $this->c->set(Session::class, $session);
        $this->c->set(Auth::class, new Auth($session, (array) Config::get('permissions')));
        $this->c->set(Csrf::class, new Csrf($session));
        $this->c->set(Request::class, new Request('POST', '/', '', [], [], '127.0.0.1'));
        $this->pdo = $db->connection();
    }

    /** Books a seat for an existing passenger and returns the booking id. */
    protected function book(int $scheduleId, string $seat, int $passengerId = 1): int
    {
        return $this->c->get(\App\Services\BookingService::class)->create([
            'schedule_id' => $scheduleId, 'seat_number' => $seat, 'mode' => 'existing',
            'passenger_id' => $passengerId, 'new' => null, 'notes' => null,
        ]);
    }

    protected function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    protected function available(int $scheduleId): int
    {
        return (int) $this->scalar('SELECT available_seats FROM schedules WHERE id = ?', [$scheduleId]);
    }
}
