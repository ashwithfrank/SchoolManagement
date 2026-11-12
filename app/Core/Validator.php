<?php
namespace App\Core;

/** Collects field errors; every rule trims input first. */
class Validator
{
    private array $in;
    private array $errors = [];
    private array $clean = [];

    public function __construct(array $input)
    {
        $this->in = $input;
    }

    public function raw(string $f): string
    {
        $v = $this->in[$f] ?? '';
        return is_array($v) ? '' : trim((string) $v);
    }

    public function text(string $f, string $label, bool $required = false, int $max = 255): ?string
    {
        $v = $this->raw($f);
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
        if ($v === '') {
            if ($required) {
                $this->errors[$f] = "$label is required.";
            }
            return $this->clean[$f] = null;
        }
        if (mb_strlen($v) > $max) {
            $this->errors[$f] = "$label must be at most $max characters.";
        }
        return $this->clean[$f] = $v;
    }

    public function date(string $f, string $label, bool $required = false): ?string
    {
        $v = $this->raw($f);
        if ($v === '') {
            if ($required) {
                $this->errors[$f] = "$label is required.";
            }
            return $this->clean[$f] = null;
        }
        if (!Dates::isValid($v)) {
            $this->errors[$f] = "$label is not a valid date.";
            return $this->clean[$f] = null;
        }
        return $this->clean[$f] = $v;
    }

    public function email(string $f, string $label, bool $required = false): ?string
    {
        $v = $this->text($f, $label, $required, 150);
        if ($v !== null && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$f] = "$label is not a valid email address.";
        }
        return $v;
    }

    /** Indian-style phone: 10 digits (optionally +91 / leading 0) or a landline with STD code. */
    public function phone(string $f, string $label, bool $required = false): ?string
    {
        $v = $this->text($f, $label, $required, 20);
        if ($v === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $v);
        if (strlen($digits) < 8 || strlen($digits) > 13) {
            $this->errors[$f] = "$label must be a valid phone number.";
        }
        return $v;
    }

    public function pin(string $f, string $label): ?string
    {
        $v = $this->text($f, $label, false, 6);
        if ($v !== null && !preg_match('/^\d{6}$/', $v)) {
            $this->errors[$f] = "$label must be 6 digits.";
        }
        return $v;
    }

    public function int(string $f, string $label, bool $required = false, ?int $min = null, ?int $max = null): ?int
    {
        $v = $this->raw($f);
        if ($v === '') {
            if ($required) {
                $this->errors[$f] = "$label is required.";
            }
            return $this->clean[$f] = null;
        }
        if (!preg_match('/^-?\d{1,9}$/', $v)) {
            $this->errors[$f] = "$label must be a whole number.";
            return $this->clean[$f] = null;
        }
        $n = (int) $v;
        if (($min !== null && $n < $min) || ($max !== null && $n > $max)) {
            $this->errors[$f] = "$label must be between " . ($min ?? '-∞') . ' and ' . ($max ?? '∞') . '.';
        }
        return $this->clean[$f] = $n;
    }

    public function in(string $f, string $label, array $allowed, bool $required = true): ?string
    {
        $v = $this->raw($f);
        if ($v === '') {
            if ($required) {
                $this->errors[$f] = "$label is required.";
            }
            return $this->clean[$f] = null;
        }
        if (!in_array($v, $allowed, true)) {
            $this->errors[$f] = "$label has an invalid value.";
            return $this->clean[$f] = null;
        }
        return $this->clean[$f] = $v;
    }

    public function money(string $f, string $label, bool $required = true, bool $allowZero = false): ?int
    {
        $v = $this->raw($f);
        if ($v === '') {
            if ($required) {
                $this->errors[$f] = "$label is required.";
            }
            return null;
        }
        $p = Money::parse($v);
        if ($p === null) {
            $this->errors[$f] = "$label must be an amount like 1500 or 1500.50.";
            return null;
        }
        if ($p === 0 && !$allowZero) {
            $this->errors[$f] = "$label must be greater than zero.";
        }
        return $p;
    }

    public function error(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function fails(): bool
    {
        return (bool) $this->errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return $this->errors ? reset($this->errors) : '';
    }

    /** Throws a UserError listing the problems. */
    public function check(): void
    {
        if ($this->errors) {
            throw new UserError(implode(' ', array_values($this->errors)));
        }
    }
}
