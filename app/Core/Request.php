<?php

declare(strict_types=1);

namespace App\Core;

/** Immutable view of the current HTTP request. */
final class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private string $basePath,
        private array $query,
        private array $post,
        private string $ip,
        private array $server = []
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uriPath = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));

        // Works from a VirtualHost root, /public, or htdocs/<project>/ (root .htaccess).
        $script = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        $candidates = array_unique([$script, rtrim(dirname($script), '/\\')]);
        usort($candidates, static fn ($a, $b) => strlen($b) <=> strlen($a));

        $basePath = '';
        $path = $uriPath;
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $candidate !== '.' && ($uriPath === $candidate || str_starts_with($uriPath, $candidate . '/'))) {
                $basePath = $candidate;
                $path = substr($uriPath, strlen($candidate));
                break;
            }
        }
        $path = '/' . trim($path, '/');

        // REMOTE_ADDR only: forwarded-for headers are client-controlled and spoofable.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        return new self($method, $path, $basePath, $_GET, $_POST, $ip, $_SERVER);
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function basePath(): string { return $this->basePath; }
    public function ip(): string { return $this->ip; }
    public function isPost(): bool { return $this->method === 'POST'; }

    public function query(string $key, mixed $default = null): mixed { return $this->query[$key] ?? $default; }
    public function post(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $default; }
    public function input(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $this->query[$key] ?? $default; }
    public function allPost(): array { return $this->post; }
    public function allQuery(): array { return $this->query; }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }
}
