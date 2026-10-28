<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;

/** Escape for HTML output. Use on EVERY dynamic value printed in a view. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app(?string $id = null): mixed
{
    $container = Container::getInstance();
    return $id === null ? $container : $container->get($id);
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** Absolute-path URL that works from a VirtualHost root, /public, or htdocs/<project>/. */
function url(string $path = ''): string
{
    return app(Request::class)->basePath() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return app(Csrf::class)->field();
}

function csrf_token(): string
{
    return app(Csrf::class)->token();
}

function old(string $key, mixed $default = ''): mixed
{
    return app(Session::class)->old($key, $default);
}

function field_error(string $field): ?string
{
    return app(Session::class)->error($field);
}

function auth(): Auth
{
    return app(Auth::class);
}

function can(string $permission): bool
{
    return auth()->can($permission);
}

function format_money(float|int|string|null $amount): string
{
    $symbols = ['PHP' => '₱', 'USD' => '$', 'EUR' => '€'];
    $currency = (string) setting('currency', 'PHP');
    return ($symbols[$currency] ?? $currency . ' ') . number_format((float) $amount, 2);
}

function format_datetime(?string $value, string $format = 'M d, Y h:i A'): string
{
    return $value ? date($format, strtotime($value)) : '—';
}

function format_date(?string $value): string
{
    return format_datetime($value, 'M d, Y');
}

/** Consistent coloured badge for any status value used across the system. */
function status_badge(string $status): string
{
    $map = [
        'active' => 'success', 'successful' => 'success', 'confirmed' => 'success', 'valid' => 'success', 'completed' => 'secondary',
        'scheduled' => 'primary', 'boarding' => 'info', 'departed' => 'dark',
        'pending' => 'warning', 'maintenance' => 'warning',
        'cancelled' => 'danger', 'failed' => 'danger', 'no_show' => 'danger', 'expired' => 'danger',
        'inactive' => 'secondary', 'refunded' => 'info', 'used' => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $color . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function current_path(): string
{
    return app(Request::class)->path();
}

/** Redirect from outside a controller (middleware, index.php). */
function redirect_to(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

/** Render a view fragment with its own variables and return the HTML. */
function partial(string $name, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    include BASE_PATH . '/app/Views/' . $name . '.php';
    return (string) ob_get_clean();
}

/** A system_settings value (cached per request); falls back safely if the DB is unavailable. */
function setting(string $key, ?string $default = null): ?string
{
    static $map = null;
    if ($map === null) {
        try {
            $map = app(\App\Repositories\SystemSettingRepository::class)->allAsMap();
        } catch (\Throwable) {
            $map = [];
        }
    }
    return $map[$key] ?? $default;
}

/** key => value pairs to HTML attributes (false/null skipped, true => bare attribute). */
function html_attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $key => $value) {
        if ($value === false || $value === null) {
            continue;
        }
        $out .= $value === true ? ' ' . e($key) : ' ' . e($key) . '="' . e($value) . '"';
    }
    return $out;
}

/**
 * Reusable form input. Options: type, value, required, maxlength, min, max, step,
 * placeholder, readonly, disabled, help, col (grid class), list.
 * Re-fills from old input and shows the field's validation error.
 */
function form_input(string $name, string $label, array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $error = field_error($name);
    $attrs = [
        'type' => $type, 'id' => 'f_' . $name, 'name' => $name,
        'class' => 'form-control' . ($error ? ' is-invalid' : ''),
        'value' => $type === 'password' ? null : (string) old($name, $o['value'] ?? ''),
        'required' => !empty($o['required']), 'readonly' => !empty($o['readonly']), 'disabled' => !empty($o['disabled']),
        'maxlength' => $o['maxlength'] ?? null, 'min' => $o['min'] ?? null, 'max' => $o['max'] ?? null,
        'step' => $o['step'] ?? null, 'placeholder' => $o['placeholder'] ?? null, 'list' => $o['list'] ?? null,
    ];
    return '<div class="' . e($o['col'] ?? 'col-md-6') . '"><label for="f_' . e($name) . '" class="form-label">' . e($label)
        . (!empty($o['required']) ? ' <span class="text-danger">*</span>' : '') . '</label><input' . html_attrs($attrs) . '>'
        . (!empty($o['help']) ? '<div class="form-text">' . e($o['help']) . '</div>' : '')
        . ($error ? '<div class="invalid-feedback">' . e($error) . '</div>' : '') . '</div>';
}

/** Reusable <select>. $options is value => label. Options: value, required, disabled, help, col, placeholder. */
function form_select(string $name, string $label, array $options, array $o = []): string
{
    $error = field_error($name);
    $selected = (string) old($name, $o['value'] ?? '');
    $attrs = [
        'id' => 'f_' . $name, 'name' => $name, 'class' => 'form-select' . ($error ? ' is-invalid' : ''),
        'required' => !empty($o['required']), 'disabled' => !empty($o['disabled']),
    ];
    $html = '<div class="' . e($o['col'] ?? 'col-md-6') . '"><label for="f_' . e($name) . '" class="form-label">' . e($label)
        . (!empty($o['required']) ? ' <span class="text-danger">*</span>' : '') . '</label><select' . html_attrs($attrs) . '>';
    if (isset($o['placeholder'])) {
        $html .= '<option value="">' . e($o['placeholder']) . '</option>';
    }
    foreach ($options as $value => $text) {
        $html .= '<option value="' . e($value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . e($text) . '</option>';
    }
    return $html . '</select>' . (!empty($o['help']) ? '<div class="form-text">' . e($o['help']) . '</div>' : '')
        . ($error ? '<div class="invalid-feedback">' . e($error) . '</div>' : '') . '</div>';
}

/** POST button form (for status changes etc.) with CSRF and optional confirmation dialog. */
function post_button(string $path, string $label, string $class = 'btn-outline-secondary', ?string $confirm = null, array $fields = []): string
{
    $hidden = '';
    foreach ($fields as $k => $v) {
        $hidden .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return '<form method="post" action="' . e(url($path)) . '" class="d-inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . csrf_field() . $hidden . '<button type="submit" class="btn btn-sm ' . e($class) . '">' . e($label) . '</button></form>';
}
