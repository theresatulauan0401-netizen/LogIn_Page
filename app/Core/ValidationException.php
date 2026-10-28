<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * A user-correctable failure raised by services (business rules, uniqueness...).
 * errors: field => message; the special key "_" is a general message not tied to a field.
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(private array $errors)
    {
        parent::__construct((string) (reset($errors) ?: 'Validation failed.'));
    }

    public static function general(string $message): self
    {
        return new self(['_' => $message]);
    }

    public static function field(string $field, string $message): self
    {
        return new self([$field => $message]);
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
