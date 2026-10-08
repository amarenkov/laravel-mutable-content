<?php
 
namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

use Amarenkov\MutableContent\Helpers\ObjectHelper;
 
/**
 * Id of a non-deleted object of the class.
 */
class ObjectExists implements ValidationRule
{
    /**
     * @param class-string<\Amarenkov\MutableContent\Models\ModelWithFields> $objectClass
     */
    public function __construct(protected string $objectClass)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $id = ObjectHelper::toId($value);

        if ($id === null) {
            return;
        }

        if (!$this->objectClass::whereKey($id)->exists()) {
            $fail(__('mutable-content::validation.object', [
                'class' => ObjectHelper::getClassLabel($this->objectClass),
            ]));
        }
    }
}
