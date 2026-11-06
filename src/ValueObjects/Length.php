<?php

namespace Amarenkov\MutableContent\ValueObjects;

use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\ValueObjects\Concerns\HasUnitScale;

/**
 * Length in meters.
 */
final readonly class Length implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    use HasUnitScale;

    // const
    protected const NAME = 'Length';

    public const MILLIMETERS_IN_METER = 1000;
    public const CENTIMETERS_IN_METER = 100;
    public const METERS_IN_KILOMETER = 1000;

    public const EPSILON = 0.000001;

    public const UNIT_MILLIMETER = 'mm';
    public const UNIT_CENTIMETER = 'cm';
    public const UNIT_METER = 'm';
    public const UNIT_KILOMETER = 'km';

    public const UNITS = [self::UNIT_MILLIMETER, self::UNIT_CENTIMETER, self::UNIT_METER, self::UNIT_KILOMETER];

    protected const BASE_UNIT = self::UNIT_METER;

    protected const DEFAULT_FORMAT_UNIT = self::UNIT_METER;

    protected const UNIT_FACTORS = [
        self::UNIT_MILLIMETER => 1 / self::MILLIMETERS_IN_METER,
        self::UNIT_CENTIMETER => 1 / self::CENTIMETERS_IN_METER,
        self::UNIT_METER => 1,
        self::UNIT_KILOMETER => self::METERS_IN_KILOMETER,
    ];

    protected const UNIT_PRECISION = [
        self::UNIT_MILLIMETER => 3,
        self::UNIT_CENTIMETER => 4,
        self::UNIT_METER => 6,
        self::UNIT_KILOMETER => 9,
    ];

    protected const UNIT_FORMAT_DECIMALS = [
        self::UNIT_MILLIMETER => 3,
        self::UNIT_CENTIMETER => 1,
        self::UNIT_METER => 3,
        self::UNIT_KILOMETER => 6,
    ];

    // static
    public static function fromMeters(float|int|string $meters): self
    {
        return self::fromBaseValue($meters);
    }

    public static function fromMillimeters(float|int $millimeters): self
    {
        return new self($millimeters / self::MILLIMETERS_IN_METER);
    }

    public static function fromCentimeters(float|int $centimeters): self
    {
        return new self($centimeters / self::CENTIMETERS_IN_METER);
    }

    public static function fromKilometers(float|int $kilometers): self
    {
        return new self($kilometers * self::METERS_IN_KILOMETER);
    }

    // protected
    protected function baseValue(): float
    {
        return $this->meters;
    }

    // public
    public function __construct(
        public float $meters
    ) {
        self::assertFinite($meters);
    }

    public function toMeters(): float
    {
        return $this->meters;
    }

    public function toMillimeters(): float
    {
        return $this->meters * self::MILLIMETERS_IN_METER;
    }

    public function toCentimeters(): float
    {
        return $this->meters * self::CENTIMETERS_IN_METER;
    }

    public function toKilometers(): float
    {
        return $this->meters / self::METERS_IN_KILOMETER;
    }

}
