<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxWords implements ValidationRule
{
    public function __construct(private int $max) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (self::count($value) > $this->max) {
            $fail('The :attribute field must not be greater than '.$this->max.' words.');
        }
    }

    public static function count(string $value): int
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
