<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\Helpers\NumberHelper;

/**
 * Length in meters.
 */
final readonly class Length implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    // const
    public const MILLIMETERS_IN_METER = 1000;
    public const CENTIMETERS_IN_METER = 100;
    public const METERS_IN_KILOMETER = 1000;

    public const EPSILON = 0.000001;

    public const UNIT_MILLIMETER = 'mm';
    public const UNIT_CENTIMETER = 'cm';
    public const UNIT_METER = 'm';
    public const UNIT_KILOMETER = 'km';

    public const UNITS = [self::UNIT_MILLIMETER, self::UNIT_CENTIMETER, self::UNIT_METER, self::UNIT_KILOMETER];

    protected const UNIT_METERS = [
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
        if (is_string($meters)) {
            if (!is_numeric($meters)) {
                throw new InvalidArgumentException('Length must be numeric, "'.$meters.'" given');
            }

            $meters = (float)$meters;
        }

        return new self((float)$meters);
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

    public static function fromUnit(float|int|string $value, string $unit): self
    {
        if (is_string($value)) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException('Length must be numeric, "'.$value.'" given');
            }

            $value = (float)$value;
        }

        return new self(round($value * self::unitMeters($unit), self::UNIT_PRECISION[self::UNIT_METER]));
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    /**
     * From a field value: null for null or an empty string, otherwise as fromMeters().
     */
    public static function fromFieldValue(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new InvalidArgumentException('Length must be numeric, '.get_debug_type($value).' given');
        }

        return self::fromMeters($value);
    }

    /**
     * @param iterable<self> $lengths
     */
    public static function sum(iterable $lengths): self
    {
        $meters = 0.0;

        foreach ($lengths as $length) {
            $meters += $length->meters;
        }

        return new self($meters);
    }

    public static function units(): array
    {
        return self::UNITS;
    }

    public static function baseUnit(): string
    {
        return self::UNIT_METER;
    }

    public static function unitLabel(string $unit): string
    {
        self::unitMeters($unit);

        return __('mutable-content::units.'.$unit);
    }

    /**
     * @return array<string, string> unit => label
     */
    public static function unitOptions(): array
    {
        return array_combine(self::UNITS, array_map(fn ($unit) => self::unitLabel($unit), self::UNITS));
    }

    protected static function unitMeters(string $unit): float|int
    {
        if (!isset(self::UNIT_METERS[$unit])) {
            throw new InvalidArgumentException('Unknown length unit "'.$unit.'"');
        }

        return self::UNIT_METERS[$unit];
    }

    // public
    public function __construct(
        public float $meters
    ) {
        if (!is_finite($meters)) {
            throw new InvalidArgumentException('Length must be a finite number');
        }

        if ($meters < 0) {
            throw new InvalidArgumentException('Length cannot be negative, '.$meters.' m given');
        }
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

    public function toUnit(string $unit): float
    {
        return round($this->meters / self::unitMeters($unit), self::UNIT_PRECISION[$unit]);
    }

    public function add(self $other): self
    {
        return new self($this->meters + $other->meters);
    }

    public function subtract(self $other): self
    {
        $meters = $this->meters - $other->meters;

        if ($meters < 0 && $meters > -self::EPSILON) {
            $meters = 0.0;
        }

        return new self($meters);
    }

    public function multiply(float|int $factor): self
    {
        return new self($this->meters * $factor);
    }

    public function divide(float|int $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Length cannot be divided by zero');
        }

        return new self($this->meters / $divisor);
    }

    public function compareTo(self $other): int
    {
        if ($this->equals($other)) {
            return 0;
        }

        return $this->meters <=> $other->meters;
    }

    public function equals(self $other): bool
    {
        return abs($this->meters - $other->meters) < self::EPSILON;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isZero(): bool
    {
        return $this->meters < self::EPSILON;
    }

    /**
     * Format to the millimeter without trailing zeros.
     */
    public function format(?int $decimals = null, ?string $decimalSeparator = null, ?string $thousandsSeparator = null, ?string $unit = null): string
    {
        $unit ??= self::UNIT_METER;
        $value = $this->toUnit($unit);
        $decimals ??= self::UNIT_FORMAT_DECIMALS[$unit];

        $formatted = NumberHelper::format($value, $decimals, $decimalSeparator, $thousandsSeparator);

        return $formatted.' '.self::unitLabel($unit);
    }

    public function toFieldValue(): float
    {
        return $this->meters;
    }

    public function jsonSerialize(): float
    {
        return $this->meters;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
