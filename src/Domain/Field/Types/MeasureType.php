<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use InvalidArgumentException;

use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\ValueObjects\HasUnits;

/**
 * A quantity stored as a number in the base unit of its value object. Zero and negative values only when set.
 */
class MeasureType extends FloatType
{
    // public
    /**
     * @param class-string $valueClass
     */
    public function __construct(
        protected string $valueClass,
    )
    {
        parent::__construct(signed: false);
    }

    public function format(Field $field, mixed $value): string
    {
        try {
            $object = $this->valueClass::fromFieldValue($value);

            return $object instanceof HasUnits
                ? $object->format(unit: TypeSettings::displayUnit($field))
                : (string)$object;
        } catch (InvalidArgumentException) {
            return parent::format($field, $value);
        }
    }

    public function settings(): array
    {
        $units = $this->unitClass();

        return ($units ? [TypeSettings::DISPLAY_UNIT => $units::units()] : []) + parent::settings();
    }

    public function unitClass(): ?string
    {
        return is_subclass_of($this->valueClass, HasUnits::class) ? $this->valueClass : null;
    }
}
