<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public readonly int $pages;
    public readonly int $page;

    public function __construct(public readonly int $total, int $requestedPage, public readonly int $perPage)
    {
        $this->pages = max(1, (int) ceil($total / max(1, $perPage)));
        $this->page = min(max(1, $requestedPage), $this->pages);
    }

    public static function make(int $total, int $requestedPage): self
    {
        return new self($total, $requestedPage, (int) Config::get('app.per_page', 15));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
