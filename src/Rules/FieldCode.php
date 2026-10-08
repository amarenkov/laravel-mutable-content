<?php
 
namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

use Amarenkov\MutableContent\Helpers\FieldCodeHelper;
 
class FieldCode implements ValidationRule
{
    /**
     * @param array<class-string> $mutableClasses classes the field is already bound to
     */
    public function __construct(protected array $mutableClasses = [])
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('validation.string')->translate();

            return;
        }

        $error = FieldCodeHelper::getError($value);

        foreach ($this->mutableClasses as $mutableClass) {
            $error ??= FieldCodeHelper::getErrorForClass($value, $mutableClass);
        }

        if ($error) {
            $fail($error);
        }
    }
}
