<?php
 
namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

use Amarenkov\MutableContent\Helpers\ObjectHelper;
 
/**
 * Code of exactly one non-deleted object of the class.
 */
class ObjectCodeExists implements ValidationRule
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

            return;
        }

        $ids = ObjectHelper::getIdsByCode($this->objectClass, $value);

        if (!$ids) {
            $fail(__('mutable-content::validation.object_code', [
                'class' => ObjectHelper::getClassLabel($this->objectClass),
            ]));
        } elseif (count($ids) > 1) {
            $fail(__('mutable-content::validation.object_code_ambiguous', [
                'class' => ObjectHelper::getClassLabel($this->objectClass),
            ]));
        }
    }
}
