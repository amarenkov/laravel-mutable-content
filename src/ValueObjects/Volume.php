<?php

namespace Amarenkov\MutableContent\ValueObjects;

use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\ValueObjects\Concerns\HasUnitScale;

/**
 * Volume in cubic meters.
 */
final readonly class Volume implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    use HasUnitScale;

    // const
    protected const NAME = 'Volume';

    public const EPSILON = 0.000000001;

    public const UNIT_CUBIC_CENTIMETER = 'cm3';
    public const UNIT_LITER = 'l';
    public const UNIT_CUBIC_METER = 'm3';

    public const UNITS = [self::UNIT_CUBIC_CENTIMETER, self::UNIT_LITER, self::UNIT_CUBIC_METER];

    protected const BASE_UNIT = self::UNIT_CUBIC_METER;

    protected const DEFAULT_FORMAT_UNIT = self::UNIT_CUBIC_METER;

    protected const UNIT_FACTORS = [
        self::UNIT_CUBIC_CENTIMETER => 0.000001,
        self::UNIT_LITER => 0.001,
        self::UNIT_CUBIC_METER => 1,
    ];

    protected const UNIT_PRECISION = [
        self::UNIT_CUBIC_CENTIMETER => 3,
        self::UNIT_LITER => 6,
        self::UNIT_CUBIC_METER => 9,
    ];

    protected const UNIT_FORMAT_DECIMALS = [
        self::UNIT_CUBIC_CENTIMETER => 0,
        self::UNIT_LITER => 3,
        self::UNIT_CUBIC_METER => 3,
    ];

    // static
    public static function fromCubicMeters(float|int|string $cubicMeters): self
    {
        return self::fromBaseValue($cubicMeters);
    }

    public static function fromLengths(Length $length, Length $width, Length $height): self
    {
        return new self($length->meters * $width->meters * $height->meters);
    }

    public static function fromAreaAndLength(Area $area, Length $height): self
    {
        return new self($area->squareMeters * $height->meters);
    }

    // protected
    protected function baseValue(): float
    {
        return $this->cubicMeters;
    }

    // public
    public function __construct(
        public float $cubicMeters
    ) {
        self::assertFinite($cubicMeters);
    }

    public function toCubicMeters(): float
    {
        return $this->cubicMeters;
    }

}
