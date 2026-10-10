<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\Helpers\NumberHelper;

/**
 * Volume in cubic meters.
 */
final readonly class Volume implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    // const
    public const EPSILON = 0.000000001;

    public const UNIT_CUBIC_CENTIMETER = 'cm3';
    public const UNIT_LITER = 'l';
    public const UNIT_CUBIC_METER = 'm3';

    public const UNITS = [self::UNIT_CUBIC_CENTIMETER, self::UNIT_LITER, self::UNIT_CUBIC_METER];

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
        if (is_string($cubicMeters)) {
            if (!is_numeric($cubicMeters)) {
                throw new InvalidArgumentException('Volume must be numeric, "'.$cubicMeters.'" given');
            }

            $cubicMeters = (float)$cubicMeters;
        }

        return new self((float)$cubicMeters);
    }

    public static function fromLengths(Length $length, Length $width, Length $height): self
    {
        return new self($length->meters * $width->meters * $height->meters);
    }

    public static function fromAreaAndLength(Area $area, Length $height): self
    {
        return new self($area->squareMeters * $height->meters);
    }

    public static function fromUnit(float|int|string $value, string $unit): self
    {
        if (is_string($value)) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException('Volume must be numeric, "'.$value.'" given');
            }

            $value = (float)$value;
        }

        return new self(round($value * self::unitFactor($unit), self::UNIT_PRECISION[self::UNIT_CUBIC_METER]));
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    /**
     * From a field value: null for null or an empty string, otherwise as fromCubicMeters().
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
            throw new InvalidArgumentException('Volume must be numeric, '.get_debug_type($value).' given');
        }

        return self::fromCubicMeters($value);
    }

    /**
     * @param iterable<self> $values
     */
    public static function sum(iterable $values): self
    {
        $result = 0.0;

        foreach ($values as $value) {
            $result += $value->cubicMeters;
        }

        return new self($result);
    }

    public static function units(): array
    {
        return self::UNITS;
    }

    public static function baseUnit(): string
    {
        return self::UNIT_CUBIC_METER;
    }

    public static function unitLabel(string $unit): string
    {
        self::unitFactor($unit);

        return __('mutable-content::units.'.$unit);
    }

    /**
     * @return array<string, string> unit => label
     */
    public static function unitOptions(): array
    {
        return array_combine(self::UNITS, array_map(fn ($unit) => self::unitLabel($unit), self::UNITS));
    }

    protected static function unitFactor(string $unit): float|int
    {
        if (!isset(self::UNIT_FACTORS[$unit])) {
            throw new InvalidArgumentException('Unknown volume unit "'.$unit.'"');
        }

        return self::UNIT_FACTORS[$unit];
    }

    // public
    public function __construct(
        public float $cubicMeters
    ) {
        if (!is_finite($cubicMeters)) {
            throw new InvalidArgumentException('Volume must be a finite number');
        }

        if ($cubicMeters < 0) {
            throw new InvalidArgumentException('Volume cannot be negative, '.$cubicMeters.' m3 given');
        }
    }

    public function toCubicMeters(): float
    {
        return $this->cubicMeters;
    }

    public function toUnit(string $unit): float
    {
        return round($this->cubicMeters / self::unitFactor($unit), self::UNIT_PRECISION[$unit]);
    }

    public function add(self $other): self
    {
        return new self($this->cubicMeters + $other->cubicMeters);
    }

    public function subtract(self $other): self
    {
        $result = $this->cubicMeters - $other->cubicMeters;

        if ($result < 0 && $result > -self::EPSILON) {
            $result = 0.0;
        }

        return new self($result);
    }

    public function multiply(float|int $factor): self
    {
        return new self($this->cubicMeters * $factor);
    }

    public function divide(float|int $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Volume cannot be divided by zero');
        }

        return new self($this->cubicMeters / $divisor);
    }

    public function compareTo(self $other): int
    {
        if ($this->equals($other)) {
            return 0;
        }

        return $this->cubicMeters <=> $other->cubicMeters;
    }

    public function equals(self $other): bool
    {
        return abs($this->cubicMeters - $other->cubicMeters) < self::EPSILON;
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
        return $this->cubicMeters < self::EPSILON;
    }

    /**
     * Format without trailing zeros.
     */
    public function format(?int $decimals = null, ?string $decimalSeparator = null, ?string $thousandsSeparator = null, ?string $unit = null): string
    {
        $unit ??= self::UNIT_CUBIC_METER;
        $value = $this->toUnit($unit);
        $decimals ??= self::UNIT_FORMAT_DECIMALS[$unit];

        $formatted = NumberHelper::format($value, $decimals, $decimalSeparator, $thousandsSeparator);

        return $formatted.' '.self::unitLabel($unit);
    }

    public function toFieldValue(): float
    {
        return $this->cubicMeters;
    }

    public function jsonSerialize(): float
    {
        return $this->cubicMeters;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
