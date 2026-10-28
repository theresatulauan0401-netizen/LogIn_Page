<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

final class UserRepository extends Repository
{
    public function findById(int $id, bool $forUpdate = false): ?User
    {
        $row = $this->run('SELECT * FROM users WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [':id' => $id])->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->run('SELECT * FROM users WHERE email = :email LIMIT 1', [':email' => $email])->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function touchLastLogin(int $id): void
    {
        $this->run('UPDATE users SET last_login_at = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->run('UPDATE users SET password = :hash WHERE id = :id', [':hash' => $hash, ':id' => $id]);
    }

    private function where(array $f): array
    {
        $sql = ' WHERE 1=1';
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $sql .= ' AND (u.full_name LIKE :q1 OR u.email LIKE :q2)';
            $p[':q1'] = $p[':q2'] = $like;
        }
        foreach (['role' => 'u.role', 'status' => 'u.status'] as $key => $col) {
            if (($f[$key] ?? '') !== '') {
                $sql .= " AND $col = :$key";
                $p[":$key"] = $f[$key];
            }
        }
        return [$sql, $p];
    }

    public function count(array $filters): int
    {
        [$where, $p] = $this->where($filters);
        return (int) $this->run('SELECT COUNT(*) FROM users u' . $where, $p)->fetchColumn();
    }

    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $p] = $this->where($filters);
        return $this->run(
            'SELECT u.id, u.full_name, u.email, u.role, u.status, u.last_login_at FROM users u' . $where
            . ' ORDER BY u.full_name LIMIT :limit OFFSET :offset',
            $p + [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return (int) $this->run('SELECT COUNT(*) FROM users WHERE email = :e AND id <> :id', [':e' => $email, ':id' => $exceptId ?? 0])->fetchColumn() > 0;
    }

    public function insert(string $name, string $email, string $hash, string $role): int
    {
        $this->run(
            "INSERT INTO users (full_name, email, password, role, status) VALUES (:n, :e, :h, :r, 'active')",
            [':n' => $name, ':e' => $email, ':h' => $hash, ':r' => $role]
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $email, string $role): void
    {
        $this->run('UPDATE users SET full_name = :n, email = :e, role = :r WHERE id = :id', [':n' => $name, ':e' => $email, ':r' => $role, ':id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE users SET status = :s WHERE id = :id', [':s' => $status, ':id' => $id]);
    }

    /** Locks and counts active Super Admins (guards against removing the last one). */
    public function countActiveSuperAdmins(): int
    {
        return (int) $this->run("SELECT COUNT(*) FROM (SELECT id FROM users WHERE role = 'super_admin' AND status = 'active' FOR UPDATE) t")->fetchColumn();
    }
}
