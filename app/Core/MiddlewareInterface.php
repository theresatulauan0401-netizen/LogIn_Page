<?php

declare(strict_types=1);

namespace App\Core;

interface MiddlewareInterface
{
    /**
     * Must either return (request may continue) or abort by redirecting or
     * throwing HttpException.
     *
     * @param string[] $args values after the colon in "role:admin,super_admin"
     */
    public function handle(Request $request, array $args = []): void;
}
