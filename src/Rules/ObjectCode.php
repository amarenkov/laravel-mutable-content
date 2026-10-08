<?php

namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Object code that may match no object of the class (TypeSettings::ALLOW_UNLISTED_CODES).
 */
class ObjectCode implements ValidationRule
{
    /**
     * @param class-string<\Amarenkov\MutableContent\Models\ModelWithFields> $objectClass
     */
    public function __construct(public readonly string $objectClass)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) && !is_int($value)) {
            $fail('validation.string')->translate();
        }
    }
}
