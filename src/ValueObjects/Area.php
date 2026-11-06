<?php

namespace Amarenkov\MutableContent\ValueObjects;

use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\ValueObjects\Concerns\HasUnitScale;

/**
 * Area in square meters.
 */
final readonly class Area implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    use HasUnitScale;

    // const
    protected const NAME = 'Area';

    public const EPSILON = 0.000001;

    public const UNIT_SQUARE_MILLIMETER = 'mm2';
    public const UNIT_SQUARE_CENTIMETER = 'cm2';
    public const UNIT_SQUARE_METER = 'm2';

    public const UNITS = [self::UNIT_SQUARE_MILLIMETER, self::UNIT_SQUARE_CENTIMETER, self::UNIT_SQUARE_METER];

    protected const BASE_UNIT = self::UNIT_SQUARE_METER;

    protected const DEFAULT_FORMAT_UNIT = self::UNIT_SQUARE_METER;

    protected const UNIT_FACTORS = [
        self::UNIT_SQUARE_MILLIMETER => 0.000001,
        self::UNIT_SQUARE_CENTIMETER => 0.0001,
        self::UNIT_SQUARE_METER => 1,
    ];

    protected const UNIT_PRECISION = [
        self::UNIT_SQUARE_MILLIMETER => 0,
        self::UNIT_SQUARE_CENTIMETER => 2,
        self::UNIT_SQUARE_METER => 6,
    ];

    protected const UNIT_FORMAT_DECIMALS = [
        self::UNIT_SQUARE_MILLIMETER => 0,
        self::UNIT_SQUARE_CENTIMETER => 2,
        self::UNIT_SQUARE_METER => 3,
    ];

    // static
    public static function fromSquareMeters(float|int|string $squareMeters): self
    {
        return self::fromBaseValue($squareMeters);
    }

    public static function fromLengths(Length $length, Length $width): self
    {
        return new self($length->meters * $width->meters);
    }

    // protected
    protected function baseValue(): float
    {
        return $this->squareMeters;
    }

    // public
    public function __construct(
        public float $squareMeters
    ) {
        self::assertFinite($squareMeters);
    }

    public function toSquareMeters(): float
    {
        return $this->squareMeters;
    }

}
