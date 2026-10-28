<?php

declare(strict_types=1);

namespace App\Core;

use DateTime;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** Owns the single PDO connection (prepared statements only, exceptions on). */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private array $config, private string $timezone = 'UTC')
    {
    }

    /** Applies the Settings timezone to MySQL's session so NOW() matches PHP. */
    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
        if ($this->pdo !== null) {
            $this->pdo->exec("SET time_zone = '" . $this->offsetFor($timezone) . "'");
        }
    }

    private function offsetFor(string $timezone): string
    {
        $offset = (new DateTimeZone($timezone))->getOffset(new DateTime('now'));
        $abs = abs($offset);
        return sprintf('%s%02d:%02d', $offset < 0 ? '-' : '+', intdiv($abs, 3600), intdiv($abs % 3600, 60));
    }

    public function connection(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4'
        );

        // Make MySQL NOW()/CURRENT_TIMESTAMP agree with the PHP/app timezone.
        $tzOffset = $this->offsetFor($this->timezone);

        try {
            $this->pdo = new PDO($dsn, (string) $this->config['username'], (string) $this->config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            $this->pdo->exec("SET time_zone = '" . $tzOffset . "'");
        } catch (PDOException $e) {
            // Log details privately; never leak host/user/password to the browser.
            Logger::error('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Could not connect to the database. Check your .env settings and that MySQL is running.');
        }

        return $this->pdo;
    }

    /**
     * Runs $callback inside a transaction; commits on success, rolls back and
     * rethrows on any Throwable. Joins an already-open transaction.
     *
     * @template T
     * @param callable(PDO):T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->connection();
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
