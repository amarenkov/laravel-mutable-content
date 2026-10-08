<?php
 
namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

use Amarenkov\MutableContent\Domain\LovRegistry;
 
class InLov implements ValidationRule
{
    public function __construct(protected string $lovCode)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $lovRegistry = app(LovRegistry::class);

        if (!$lovRegistry->hasLovItem($this->lovCode, $value)) {
            $fail(__('mutable-content::validation.in_lov', [
                'lov' => $lovRegistry->getLovLabel($this->lovCode) ?? $this->lovCode,
            ]));
        }
    }
}