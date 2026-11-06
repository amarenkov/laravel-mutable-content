<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Helpers\ObjectHelper;
use Amarenkov\MutableContent\Rules\ObjectCode;
use Amarenkov\MutableContent\Rules\ObjectCodeExists;
use Amarenkov\MutableContent\Rules\ObjectExists;

/**
 * Reference to an object of the class of the field: its id or, with the link by code setting, its code.
 */
class ObjectType extends FieldType
{
    // public
    public function comparesAsNumber(Field $field): bool
    {
        return !TypeSettings::linksByCode($field);
    }

    public function rules(Field $field): array
    {
        if (TypeSettings::linksByCode($field)) {
            return [TypeSettings::allowsUnlistedCodes($field) ? new ObjectCode($field->objectClass) : new ObjectCodeExists($field->objectClass)];
        }

        return $field->objectClass ? ['integer', new ObjectExists($field->objectClass)] : ['integer'];
    }

    public function format(Field $field, mixed $value): string
    {
        if (!$field->objectClass || !is_scalar($value)) {
            return parent::format($field, $value);
        }

        $title = TypeSettings::linksByCode($field)
            ? ObjectHelper::getTitleByCode($field->objectClass, $value)
            : ObjectHelper::getTitleById($field->objectClass, $value);

        return $title ?? (string)$value;
    }

    public function settings(): array
    {
        return [
            TypeSettings::LINK_BY_CODE => [true, false],
            TypeSettings::ALLOW_UNLISTED_CODES => [true, false],
        ];
    }

    public function kind(): string
    {
        return self::KIND_INTEGER;
    }
}
