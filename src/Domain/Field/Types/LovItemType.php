<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Rules\InLov;
use Amarenkov\MutableContent\Rules\LovItemCode;

/**
 * Code of an item of the LOV of the field.
 */
class LovItemType extends FieldType
{
    // public
    public function rules(Field $field): array
    {
        return [TypeSettings::allowsUnlistedCodes($field) ? new LovItemCode($field->lovCode) : new InLov($field->lovCode)];
    }

    public function format(Field $field, mixed $value): string
    {
        return is_scalar($value) ? (app(LovRegistry::class)->getLovItemLabel($field->lovCode, $value) ?? (string)$value) : parent::format($field, $value);
    }

    public function settings(): array
    {
        return [TypeSettings::ALLOW_UNLISTED_CODES => [true, false]];
    }
}
