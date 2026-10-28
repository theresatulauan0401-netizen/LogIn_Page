<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;

/** Shared controller plumbing: rendering, redirects, flash messages, validation. */
abstract class BaseController
{
    public function __construct(
        protected Request $request,
        protected Auth $auth,
        protected Session $session,
        protected Csrf $csrf
    ) {
    }

    /**
     * Renders app/Views/<view>.php inside a layout (null = no layout) and returns the HTML.
     * Views only display data; they contain no SQL or business rules.
     */
    protected function render(string $view, array $data = [], ?string $layout = 'layouts/main'): string
    {
        if ($layout === 'layouts/main' && $this->auth->check()) {
            $data += ['notifications' => app(\App\Services\DashboardService::class)->notifications()];
        }
        $content = $this->renderFile($view, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->renderFile($layout, $data + ['content' => $content]);
    }

    private function renderFile(string $view, array $data): string
    {
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new HttpException(500, "View [$view] not found.");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . url($path), true, 303);
        exit;
    }

    protected function back(string $fallback = '/dashboard'): never
    {
        $referer = $this->request->header('Referer');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Only follow a Referer that points at this same host (prevents open redirects).
        if ($referer && parse_url($referer, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
            header('Location: ' . $referer, true, 303);
            exit;
        }
        $this->redirect($fallback);
    }

    protected function success(string $message): void { $this->session->flash('success', $message); }
    protected function error(string $message): void { $this->session->flash('danger', $message); }

    /**
     * Validates POST data. On failure: flashes errors and old input, then redirects back.
     *
     * @return array<string,mixed> validated data
     */
    protected function validate(array $rules, array $labels = [], ?string $failPath = null): array
    {
        $validator = Validator::make($this->request->allPost(), $rules, $labels);
        if ($validator->fails()) {
            $this->session->flashErrors($validator->firstErrors());
            $this->session->flashOld($this->request->allPost());
            $this->error('Please correct the highlighted fields.');
            $failPath === null ? $this->back() : $this->redirect($failPath);
        }
        return $validator->validated();
    }

    protected function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Show a service's ValidationException on the form (field errors + old input) and go back. */
    protected function failWith(ValidationException $e, ?string $path = null): never
    {
        $fieldErrors = array_filter($e->errors(), static fn ($key) => $key !== '_', ARRAY_FILTER_USE_KEY);
        if ($fieldErrors) {
            $this->session->flashErrors($fieldErrors);
        }
        $this->session->flashOld($this->request->allPost());
        $this->error($e->errors()['_'] ?? 'Please correct the highlighted fields.');
        $path === null ? $this->back() : $this->redirect($path);
    }

    /**
     * Trimmed GET filters limited to the allowed keys (missing/empty => '').
     *
     * @param string[] $allowed
     * @return array<string,string>
     */
    protected function filters(array $allowed): array
    {
        $out = [];
        foreach ($allowed as $key) {
            $value = $this->request->query($key, '');
            $out[$key] = is_string($value) ? trim($value) : '';
        }
        return $out;
    }

    protected function pageNumber(): int
    {
        return max(1, (int) $this->request->query('page', 1));
    }
}
