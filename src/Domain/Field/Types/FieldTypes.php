<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\ValueObjects\Area;
use Amarenkov\MutableContent\ValueObjects\Density;
use Amarenkov\MutableContent\ValueObjects\Length;
use Amarenkov\MutableContent\ValueObjects\SurfaceDensity;
use Amarenkov\MutableContent\ValueObjects\Volume;
use Amarenkov\MutableContent\ValueObjects\Weight;

/**
 * The field types by their codes.
 */
class FieldTypes
{
    // static
    /**
     * @var array<string, FieldType>
     */
    protected static array $types = [];

    protected static function make(string $code): FieldType
    {
        return match ($code) {
            Type::TYPE_TEXT => new TextType(),
            Type::TYPE_BOOL => new BoolType(),
            Type::TYPE_INT => new IntType(),
            Type::TYPE_FLOAT => new FloatType(),
            Type::TYPE_LOV => new LovType(),
            Type::TYPE_LOV_ITEM => new LovItemType(),
            Type::TYPE_OBJECT => new ObjectType(),
            Type::TYPE_WEIGHT => new MeasureType(Weight::class),
            Type::TYPE_DENSITY => new MeasureType(Density::class),
            Type::TYPE_SURFACE_DENSITY => new MeasureType(SurfaceDensity::class),
            Type::TYPE_LENGTH => new MeasureType(Length::class),
            Type::TYPE_AREA => new MeasureType(Area::class),
            Type::TYPE_VOLUME => new MeasureType(Volume::class),
            Type::TYPE_DURATION => new DurationType(),
            Type::TYPE_DATE => new DateType(),
            Type::TYPE_DATETIME => new DateTimeType(),
            Type::TYPE_MONEY => new MoneyType(),
            Type::TYPE_ICON => new IconType(),
            Type::TYPE_SECRET => new SecretType(),
            default => new FieldType(),
        };
    }

    public static function for(string $code): FieldType
    {
        return self::$types[$code] ??= self::make($code);
    }

    /**
     * Human-readable value of a field, or null if it is empty.
     */
    public static function display(Field $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return self::for($field->fieldType)->format($field, $value);
    }
}
