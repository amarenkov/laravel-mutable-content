<?php

namespace Amarenkov\MutableContent\Domain\Field;

use ArrayObject;
use InvalidArgumentException;

use Amarenkov\MutableContent\Domain\Field\Lov\Type as FieldType;
use Amarenkov\MutableContent\Domain\LovRegistry;

use Amarenkov\MutableContent\Models\Lov\Currency;

use Amarenkov\MutableContent\ValueObjects\Area;
use Amarenkov\MutableContent\ValueObjects\Duration;
use Amarenkov\MutableContent\ValueObjects\HasUnits;
use Amarenkov\MutableContent\ValueObjects\Length;
use Amarenkov\MutableContent\ValueObjects\Money;
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

    public const ALLOW_NEGATIVE = 'allow_negative';

    public const CURRENCY = 'currency';

    public const DECIMALS = 'decimals';

    public const VISIBLE_START = 'visible_start';

    public const VISIBLE_END = 'visible_end';

    public const FORMAT = 'format';

    public const FORMAT_PLAIN = 'plain';

    public const FORMAT_MARKDOWN = 'markdown';

    public const FORMATS = [self::FORMAT_PLAIN, self::FORMAT_MARKDOWN];

    /**
     * Characters of a secret shown at the start and at the end by default, and at most.
     */
    public const DEFAULT_VISIBLE = 2;

    public const MAX_VISIBLE = 8;

    /**
     * Measurement types: zero and negative values are off by default.
     */
    public const ALLOW_ZERO_TYPES = [
        FieldType::TYPE_WEIGHT,
        FieldType::TYPE_LENGTH,
        FieldType::TYPE_AREA,
        FieldType::TYPE_VOLUME,
        FieldType::TYPE_DENSITY,
        FieldType::TYPE_SURFACE_DENSITY,
        FieldType::TYPE_DURATION,
    ];

    /**
     * Number types: zero and negative values are on by default.
     */
    public const SIGNED_TYPES = [
        FieldType::TYPE_INT,
        FieldType::TYPE_FLOAT,
        FieldType::TYPE_MONEY,
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

        if (in_array($fieldType, self::ALLOW_ZERO_TYPES, true) || in_array($fieldType, self::SIGNED_TYPES, true)) {
            $result[self::ALLOW_NEGATIVE] = [true, false];
            $result[self::ALLOW_ZERO] = [true, false];
        }

        if ($fieldType === FieldType::TYPE_MONEY) {
            $result[self::CURRENCY] = array_map('strval', array_keys(app(LovRegistry::class)->getLovItemsOptions(Currency::CODE)));
            $result[self::DECIMALS] = range(0, Money::MAX_DECIMALS);
        }

        if ($fieldType === FieldType::TYPE_TEXT) {
            $result[self::FORMAT] = self::FORMATS;
        }

        if ($fieldType === FieldType::TYPE_SECRET) {
            $result[self::VISIBLE_START] = range(0, self::MAX_VISIBLE);
            $result[self::VISIBLE_END] = range(0, self::MAX_VISIBLE);
        }

        return $result;
    }

    /**
     * Value of a setting that is not set: zero and negative values are allowed for number types and not for measurements,
     * secrets show two characters at each end.
     */
    public static function getDefaultValue(string $fieldType, string $code): mixed
    {
        return match ($code) {
            self::ALLOW_ZERO, self::ALLOW_NEGATIVE => in_array($fieldType, self::SIGNED_TYPES, true),
            self::VISIBLE_START, self::VISIBLE_END => $fieldType === FieldType::TYPE_SECRET ? self::DEFAULT_VISIBLE : null,
            self::FORMAT => $fieldType === FieldType::TYPE_TEXT ? self::FORMAT_PLAIN : null,
            default => null,
        };
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
            FieldType::TYPE_DURATION => Duration::class,
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
        return (static::getValue($field, self::ALLOW_ZERO) ?? static::getDefaultValue($field->fieldType, self::ALLOW_ZERO)) === true;
    }

    public static function allowsNegative(Field $field): bool
    {
        return (static::getValue($field, self::ALLOW_NEGATIVE) ?? static::getDefaultValue($field->fieldType, self::ALLOW_NEGATIVE)) === true;
    }

    /**
     * The only currency of a money field, or null if any currency of the LOV is allowed.
     */
    public static function currency(Field $field): ?string
    {
        return $field->fieldType === FieldType::TYPE_MONEY ? static::getValue($field, self::CURRENCY) : null;
    }

    /**
     * Digits after the point of a money field instead of the decimals of its currency, or null to use the currency ones.
     */
    public static function decimals(Field $field): ?int
    {
        return $field->fieldType === FieldType::TYPE_MONEY ? static::getValue($field, self::DECIMALS) : null;
    }

    /**
     * Characters of a secret shown at the start.
     */
    public static function visibleStart(Field $field): int
    {
        return static::getValue($field, self::VISIBLE_START) ?? static::getDefaultValue($field->fieldType, self::VISIBLE_START) ?? 0;
    }

    /**
     * Characters of a secret shown at the end.
     */
    public static function visibleEnd(Field $field): int
    {
        return static::getValue($field, self::VISIBLE_END) ?? static::getDefaultValue($field->fieldType, self::VISIBLE_END) ?? 0;
    }

    /**
     * Markup of a text field value: plain text by default or Markdown.
     */
    public static function format(Field $field): string
    {
        return static::getValue($field, self::FORMAT) ?? static::getDefaultValue($field->fieldType, self::FORMAT) ?? self::FORMAT_PLAIN;
    }

    public static function isMarkdown(Field $field): bool
    {
        return static::format($field) === self::FORMAT_MARKDOWN;
    }
}
