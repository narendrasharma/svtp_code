<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MenuLinkUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isSafe($value)) {
            $fail('Use an internal path, https:// URL, mailto: address, or tel: number.');
        }
    }

    public static function isSafe(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) {
            return false;
        }

        if (str_starts_with($value, '//')) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $matches)) {
            $scheme = strtolower($matches[1]);
            if (in_array($scheme, ['http', 'https'], true)) {
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            }

            return in_array($scheme, ['mailto', 'tel'], true) && strlen($value) > strlen($scheme) + 1;
        }

        return ! str_contains(explode('/', $value)[0], ':');
    }
}
