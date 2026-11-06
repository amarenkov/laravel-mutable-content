<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Rules\InLovs;

/**
 * Code of a LOV.
 */
class LovType extends FieldType
{
    // public
    public function rules(Field $field): array
    {
        return [new InLovs()];
    }

    public function format(Field $field, mixed $value): string
    {
        return is_scalar($value) ? (app(LovRegistry::class)->getLovLabel($value) ?? (string)$value) : parent::format($field, $value);
    }
}
