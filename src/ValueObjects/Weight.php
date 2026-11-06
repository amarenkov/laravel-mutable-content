<?php

namespace Amarenkov\MutableContent\ValueObjects;

use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\ValueObjects\Concerns\HasUnitScale;

/**
 * Weight in kilograms.
 */
final readonly class Weight implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    use HasUnitScale;

    // const
    protected const NAME = 'Weight';

    public const GRAMS_IN_KILOGRAM = 1000;
    public const KILOGRAMS_IN_TONNE = 1000;

    public const EPSILON = 0.000001;

    public const UNIT_GRAM = 'g';
    public const UNIT_KILOGRAM = 'kg';
    public const UNIT_TONNE = 't';

    public const UNITS = [self::UNIT_GRAM, self::UNIT_KILOGRAM, self::UNIT_TONNE];

    protected const BASE_UNIT = self::UNIT_KILOGRAM;

    protected const DEFAULT_FORMAT_UNIT = self::UNIT_KILOGRAM;

    protected const UNIT_FACTORS = [
        self::UNIT_GRAM => 1 / self::GRAMS_IN_KILOGRAM,
        self::UNIT_KILOGRAM => 1,
        self::UNIT_TONNE => self::KILOGRAMS_IN_TONNE,
    ];

    protected const UNIT_PRECISION = [
        self::UNIT_GRAM => 3,
        self::UNIT_KILOGRAM => 6,
        self::UNIT_TONNE => 9,
    ];

    protected const UNIT_FORMAT_DECIMALS = [
        self::UNIT_GRAM => 3,
        self::UNIT_KILOGRAM => 3,
        self::UNIT_TONNE => 6,
    ];

    // static
    public static function fromKilograms(float|int|string $kilograms): self
    {
        return self::fromBaseValue($kilograms);
    }

    public static function fromGrams(float|int $grams): self
    {
        return new self($grams / self::GRAMS_IN_KILOGRAM);
    }

    public static function fromTonnes(float|int $tonnes): self
    {
        return new self($tonnes * self::KILOGRAMS_IN_TONNE);
    }

    // protected
    protected function baseValue(): float
    {
        return $this->kilograms;
    }

    // public
    public function __construct(
        public float $kilograms
    ) {
        self::assertFinite($kilograms);
    }

    public function toKilograms(): float
    {
        return $this->kilograms;
    }

    public function toGrams(): float
    {
        return $this->kilograms * self::GRAMS_IN_KILOGRAM;
    }

    public function toTonnes(): float
    {
        return $this->kilograms / self::KILOGRAMS_IN_TONNE;
    }

}
