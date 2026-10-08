<?php
 
namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

use Amarenkov\MutableContent\Helpers\FieldCodeHelper;
 
/**
 * The field code must not be reserved in the class.
 */
class FieldAllowedInClass implements ValidationRule
{
    public function __construct(protected string $fieldCode)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !class_exists($value)) {
            $fail(__('mutable-content::validation.mutable_class'));

            return;
        }

        $error = FieldCodeHelper::getErrorForClass($this->fieldCode, $value);

        if ($error) {
            $fail($error);
        }
    }
}
