<?php

declare(strict_types=1);

namespace App\Core;

/** Synchronizer-token CSRF protection for every state-changing (POST) request. */
final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }
        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="'
            . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public function regenerate(): void
    {
        $this->session->forget(self::KEY);
    }

    public function verify(?string $submitted): bool
    {
        $known = $this->session->get(self::KEY);
        return is_string($known) && $known !== '' && is_string($submitted) && hash_equals($known, $submitted);
    }

    public function verifyRequest(Request $request): bool
    {
        $submitted = $request->post('_token');
        if (!is_string($submitted)) {
            $submitted = $request->header('X-CSRF-TOKEN');
        }
        return $this->verify($submitted);
    }
}
