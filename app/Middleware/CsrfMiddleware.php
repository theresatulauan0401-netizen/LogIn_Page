<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

/** Rejects any POST without a valid token (HTTP 419). Put on every POST route. */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private Csrf $csrf)
    {
    }

    public function handle(Request $request, array $args = []): void
    {
        if ($request->isPost() && !$this->csrf->verifyRequest($request)) {
            throw new HttpException(419);
        }
    }
}
