<?php

declare(strict_types=1);

namespace App\Repositories;

final class LoginAttemptRepository extends Repository
{
    public function record(string $email, string $ip, bool $success): void
    {
        $this->run(
            'INSERT INTO login_attempts (email, ip_address, was_successful) VALUES (:e, :ip, :ok)',
            [':e' => mb_substr($email, 0, 190), ':ip' => $ip, ':ok' => $success ? 1 : 0]
        );
    }

    public function failuresForEmail(string $email, string $sinceDateTime): int
    {
        return (int) $this->run(
            'SELECT COUNT(*) FROM login_attempts WHERE email = :e AND was_successful = 0 AND attempted_at >= :since',
            [':e' => $email, ':since' => $sinceDateTime]
        )->fetchColumn();
    }

    public function failuresForIp(string $ip, string $sinceDateTime): int
    {
        return (int) $this->run(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND was_successful = 0 AND attempted_at >= :since',
            [':ip' => $ip, ':since' => $sinceDateTime]
        )->fetchColumn();
    }

    /** After a successful login the e-mail's failure counter starts again. */
    public function clearFailuresForEmail(string $email): void
    {
        $this->run('DELETE FROM login_attempts WHERE email = :e AND was_successful = 0', [':e' => $email]);
    }

    public function purgeOlderThan(string $dateTime): void
    {
        $this->run('DELETE FROM login_attempts WHERE attempted_at < :t', [':t' => $dateTime]);
    }
}
