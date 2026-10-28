<?php

declare(strict_types=1);

namespace App\Core;

use DateTime;
use InvalidArgumentException;

/**
 * Server-side input validation.
 *
 *   $v = Validator::make($_POST, [
 *       'email' => 'required|email|max:190',
 *       'fare'  => 'required|decimal|min:0',
 *       'code'  => ['required', 'regex:/^[A-Z]{3}-\d{4}$/'],   // arrays for rules containing "|"
 *   ]);
 *   if ($v->fails()) { $v->firstErrors(); }  else { $data = $v->validated(); }
 *
 * Rules: required nullable string email integer numeric decimal min:N max:N
 *        in:a,b,c date datetime regex:/../ same:field phone password alpha_dash
 * min/max compare the VALUE for integer/numeric/decimal fields, otherwise the LENGTH.
 * "datetime" accepts "Y-m-d H:i[:s]" and HTML datetime-local ("Y-m-d\TH:i") and is
 * normalised to "Y-m-d H:i:s" in validated(). Optional empty fields become null.
 */
final class Validator
{
    /** @var array<string,string[]> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $validated = [];

    private function __construct(private array $data, private array $rules, private array $labels)
    {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules, $labels);
        $validator->run();
        return $validator;
    }

    public function fails(): bool { return $this->errors !== []; }
    public function passes(): bool { return $this->errors === []; }
    public function errors(): array { return $this->errors; }
    public function validated(): array { return $this->validated; }

    /** @return array<string,string> */
    public function firstErrors(): array
    {
        return array_map(static fn (array $messages) => $messages[0], $this->errors);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $spec) {
            $rules = is_array($spec) ? $spec : explode('|', (string) $spec);
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value === null || $value === '') {
                if (in_array('required', $rules, true)) {
                    $this->fail($field, 'required', null);
                } else {
                    $this->validated[$field] = null;
                }
                continue;
            }
            if (is_array($value)) {
                $this->fail($field, 'string', null);
                continue;
            }

            $numeric = array_intersect($rules, ['integer', 'numeric', 'decimal']) !== [];
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', (string) $rule, 2), 2, null);
                if ($name === 'required' || $name === 'nullable') {
                    continue;
                }
                if (!$this->check($name, $arg, (string) $value, $numeric)) {
                    $this->fail($field, $name, $arg);
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = in_array('datetime', $rules, true)
                    ? $this->normaliseDateTime((string) $value)
                    : $value;
            }
        }
    }

    private function check(string $rule, ?string $arg, string $value, bool $numeric): bool
    {
        return match ($rule) {
            'string'     => true,
            'email'      => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'integer'    => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric'    => is_numeric($value),
            'decimal'    => (bool) preg_match('/^\d+(\.\d{1,2})?$/', $value),
            'min'        => $numeric ? (float) $value >= (float) $arg : mb_strlen($value) >= (int) $arg,
            'max'        => $numeric ? (float) $value <= (float) $arg : mb_strlen($value) <= (int) $arg,
            'in'         => in_array($value, explode(',', (string) $arg), true),
            'date'       => $this->isDate($value, 'Y-m-d'),
            'datetime'   => $this->normaliseDateTime($value) !== null,
            'regex'      => (bool) preg_match((string) $arg, $value),
            'same'       => isset($this->data[(string) $arg]) && $this->data[(string) $arg] === $value,
            'phone'      => (bool) preg_match('/^[0-9+\-\s()]{7,20}$/', $value),
            'alpha_dash' => (bool) preg_match('/^[A-Za-z0-9_\-]+$/', $value),
            'password'   => mb_strlen($value) >= 12
                            && preg_match('/[a-z]/', $value) && preg_match('/[A-Z]/', $value)
                            && preg_match('/\d/', $value),
            default      => throw new InvalidArgumentException("Unknown validation rule [$rule]."),
        };
    }

    private function isDate(string $value, string $format): bool
    {
        $d = DateTime::createFromFormat('!' . $format, $value);
        return $d !== false && $d->format($format) === $value;
    }

    private function normaliseDateTime(string $value): ?string
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
            $d = DateTime::createFromFormat('!' . $format, $value);
            if ($d !== false && $d->format($format) === $value) {
                return $d->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    private function fail(string $field, string $rule, ?string $arg): void
    {
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $this->errors[$field][] = match ($rule) {
            'required'   => "$label is required.",
            'email'      => "$label must be a valid e-mail address.",
            'integer'    => "$label must be a whole number.",
            'numeric'    => "$label must be a number.",
            'decimal'    => "$label must be a non-negative amount with at most 2 decimals.",
            'min'        => "$label is too small or too short (minimum $arg).",
            'max'        => "$label is too large or too long (maximum $arg).",
            'in'         => "$label has an invalid selection.",
            'date'       => "$label must be a valid date (YYYY-MM-DD).",
            'datetime'   => "$label must be a valid date and time.",
            'same'       => "$label does not match.",
            'phone'      => "$label must be a valid phone number.",
            'alpha_dash' => "$label may contain only letters, numbers, dashes and underscores.",
            'password'   => "$label must be at least 12 characters with upper-case, lower-case and a number.",
            default      => "$label is invalid.",
        };
    }
}
