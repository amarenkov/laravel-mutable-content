<?php

namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * LOV item code that may be missing from the LOV (TypeSettings::ALLOW_UNLISTED_CODES).
 */
class LovItemCode implements ValidationRule
{
    public function __construct(public readonly string $lovCode)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) && !is_int($value)) {
            $fail('validation.string')->translate();
        }
    }
}
