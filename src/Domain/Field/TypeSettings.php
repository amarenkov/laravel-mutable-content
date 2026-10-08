<?php

namespace Amarenkov\MutableContent\Domain\Field;

use ArrayObject;
use InvalidArgumentException;

use Amarenkov\MutableContent\Domain\Field\Lov\Type as FieldType;

use Amarenkov\MutableContent\ValueObjects\Area;
use Amarenkov\MutableContent\ValueObjects\HasUnits;
use Amarenkov\MutableContent\ValueObjects\Length;
use Amarenkov\MutableContent\ValueObjects\Volume;
use Amarenkov\MutableContent\ValueObjects\Weight;

/**
 * Field type settings. Usage settings override field settings.
 */
class TypeSettings
{
    // const
    public const DISPLAY_UNIT = 'display_unit';

    public const ALLOW_UNLISTED_CODES = 'allow_unlisted_codes';

    public const LINK_BY_CODE = 'link_by_code';

    public const ALLOW_ZERO = 'allow_zero';

    public const ALLOW_ZERO_TYPES = [
        FieldType::TYPE_WEIGHT,
        FieldType::TYPE_LENGTH,
        FieldType::TYPE_AREA,
        FieldType::TYPE_VOLUME,
        FieldType::TYPE_DENSITY,
        FieldType::TYPE_SURFACE_DENSITY,
    ];

    // static
    /**
     * Type settings and their allowed values.
     *
     * @return array<string, array<string|bool>>
     */
    public static function getAllowedValues(string $fieldType): array
    {
        if ($fieldType === FieldType::TYPE_LOV_ITEM) {
            return [self::ALLOW_UNLISTED_CODES => [true, false]];
        }

        if ($fieldType === FieldType::TYPE_OBJECT) {
            return [
                self::LINK_BY_CODE => [true, false],
                self::ALLOW_UNLISTED_CODES => [true, false],
            ];
        }

        $result = [];

        $unitClass = static::getUnitClass($fieldType);

        if ($unitClass) {
            $result[self::DISPLAY_UNIT] = $unitClass::units();
        }

        if (in_array($fieldType, self::ALLOW_ZERO_TYPES, true)) {
            $result[self::ALLOW_ZERO] = [true, false];
        }

        return $result;
    }

    /**
     * Value object class of a type with units, or null.
     *
     * @return ?class-string<HasUnits>
     */
    public static function getUnitClass(string $fieldType): ?string
    {
        return match ($fieldType) {
            FieldType::TYPE_WEIGHT => Weight::class,
            FieldType::TYPE_LENGTH => Length::class,
            FieldType::TYPE_AREA => Area::class,
            FieldType::TYPE_VOLUME => Volume::class,
            default => null,
        };
    }

    /**
     * Settings to store: only set ones known to the type, or null if none.
     * Throws on an invalid value.
     */
    public static function normalize(string $fieldType, mixed $settings): ?array
    {
        if ($settings instanceof ArrayObject) {
            $settings = $settings->getArrayCopy();
        }

        if ($settings === null || $settings === '') {
            return null;
        }

        if (!is_array($settings)) {
            throw new InvalidArgumentException('Field type settings must be an array, '.get_debug_type($settings).' given');
        }

        $result = [];

        foreach (static::getAllowedValues($fieldType) as $code => $values) {
            $value = $settings[$code] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (!in_array($value, $values, true)) {
                throw new InvalidArgumentException(__('mutable-content::validation.field_type_setting', ['setting' => $code, 'value' => is_scalar($value) ? $value : get_debug_type($value)]));
            }

            $result[$code] = $value;
        }

        return $result ?: null;
    }

    /**
     * Effective setting value; null if not set or not allowed for the type.
     */
    public static function getValue(Field $field, string $code): mixed
    {
        $value = $field->getTypeSettings()[$code] ?? null;

        return in_array($value, static::getAllowedValues($field->fieldType)[$code] ?? [], true) ? $value : null;
    }

    public static function allowsUnlistedCodes(Field $field): bool
    {
        if ($field->fieldType === FieldType::TYPE_OBJECT && !static::linksByCode($field)) {
            return false;
        }

        return static::getValue($field, self::ALLOW_UNLISTED_CODES) === true;
    }

    public static function linksByCode(Field $field): bool
    {
        return $field->fieldType === FieldType::TYPE_OBJECT
            && $field->objectClass
            && static::getValue($field, self::LINK_BY_CODE) === true
            && class_exists($field->objectClass)
            && isset($field->objectClass::getFieldDefinitions()[Field::COMMON_CODE_CODE]);
    }

    public static function allowsZero(Field $field): bool
    {
        return static::getValue($field, self::ALLOW_ZERO) === true;
    }
}
