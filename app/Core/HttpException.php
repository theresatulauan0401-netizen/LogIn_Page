<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown to abort a request with an HTTP status (403, 404, 405, 419 ...). */
final class HttpException extends RuntimeException
{
    public function __construct(private int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            403 => 'You do not have permission to do that.',
            404 => 'The page you requested could not be found.',
            405 => 'Method not allowed.',
            419 => 'Your session expired or the form token is invalid. Please go back and try again.',
            429 => 'Too many requests. Please wait and try again.',
            default => 'Something went wrong.',
        };
    }
}
