<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOStatement;

/** Base class: every query goes through prepared statements. */
abstract class Repository
{
    public function __construct(protected PDO $pdo)
    {
    }

    protected function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue(is_int($name) ? $name + 1 : $name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }
}
