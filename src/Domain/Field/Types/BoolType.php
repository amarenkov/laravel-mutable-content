<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Domain\Field\Field;

class BoolType extends FieldType
{
    // public
    public function queryValue(mixed $value): mixed
    {
        return $value === null ? null : (bool)$value;
    }

    public function rules(Field $field): array
    {
        return ['boolean'];
    }

    public function format(Field $field, mixed $value): string
    {
        if (!is_scalar($value)) {
            return parent::format($field, $value);
        }

        return $value ? __('mutable-content::log.yes') : __('mutable-content::log.no');
    }

    public function kind(): string
    {
        return self::KIND_BOOLEAN;
    }
}
