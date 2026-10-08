<?php
 
namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

use Amarenkov\MutableContent\Domain\LovRegistry;
 
class InLovs implements ValidationRule
{
    public function __construct()
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $lovRegistry = app(LovRegistry::class);

        if ((!is_string($value) && !is_int($value)) || !array_key_exists($value, $lovRegistry->getLovsOptions())) {
            $fail(__('mutable-content::validation.in_lovs'));
        }
    }
}