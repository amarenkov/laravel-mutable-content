<?php

namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A number other than zero; other values are left to the type rule.
 */
class NotZero implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_numeric($value) && (float)$value == 0) {
            $fail(__('mutable-content::validation.not_zero'));
        }
    }
}
