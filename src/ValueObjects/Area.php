<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\Helpers\NumberHelper;

/**
 * Area in square meters.
 */
final readonly class Area implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    // const
    public const EPSILON = 0.000001;

    public const UNIT_SQUARE_MILLIMETER = 'mm2';
    public const UNIT_SQUARE_CENTIMETER = 'cm2';
    public const UNIT_SQUARE_METER = 'm2';

    public const UNITS = [self::UNIT_SQUARE_MILLIMETER, self::UNIT_SQUARE_CENTIMETER, self::UNIT_SQUARE_METER];

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
        if (is_string($squareMeters)) {
            if (!is_numeric($squareMeters)) {
                throw new InvalidArgumentException('Area must be numeric, "'.$squareMeters.'" given');
            }

            $squareMeters = (float)$squareMeters;
        }

        return new self((float)$squareMeters);
    }

    public static function fromLengths(Length $length, Length $width): self
    {
        return new self($length->meters * $width->meters);
    }

    public static function fromUnit(float|int|string $value, string $unit): self
    {
        if (is_string($value)) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException('Area must be numeric, "'.$value.'" given');
            }

            $value = (float)$value;
        }

        return new self(round($value * self::unitFactor($unit), self::UNIT_PRECISION[self::UNIT_SQUARE_METER]));
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    /**
     * From a field value: null for null or an empty string, otherwise as fromSquareMeters().
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
            throw new InvalidArgumentException('Area must be numeric, '.get_debug_type($value).' given');
        }

        return self::fromSquareMeters($value);
    }

    /**
     * @param iterable<self> $values
     */
    public static function sum(iterable $values): self
    {
        $result = 0.0;

        foreach ($values as $value) {
            $result += $value->squareMeters;
        }

        return new self($result);
    }

    public static function units(): array
    {
        return self::UNITS;
    }

    public static function baseUnit(): string
    {
        return self::UNIT_SQUARE_METER;
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
            throw new InvalidArgumentException('Unknown area unit "'.$unit.'"');
        }

        return self::UNIT_FACTORS[$unit];
    }

    // public
    public function __construct(
        public float $squareMeters
    ) {
        if (!is_finite($squareMeters)) {
            throw new InvalidArgumentException('Area must be a finite number');
        }

        if ($squareMeters < 0) {
            throw new InvalidArgumentException('Area cannot be negative, '.$squareMeters.' m2 given');
        }
    }

    public function toSquareMeters(): float
    {
        return $this->squareMeters;
    }

    public function toUnit(string $unit): float
    {
        return round($this->squareMeters / self::unitFactor($unit), self::UNIT_PRECISION[$unit]);
    }

    public function add(self $other): self
    {
        return new self($this->squareMeters + $other->squareMeters);
    }

    public function subtract(self $other): self
    {
        $result = $this->squareMeters - $other->squareMeters;

        if ($result < 0 && $result > -self::EPSILON) {
            $result = 0.0;
        }

        return new self($result);
    }

    public function multiply(float|int $factor): self
    {
        return new self($this->squareMeters * $factor);
    }

    public function divide(float|int $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Area cannot be divided by zero');
        }

        return new self($this->squareMeters / $divisor);
    }

    public function compareTo(self $other): int
    {
        if ($this->equals($other)) {
            return 0;
        }

        return $this->squareMeters <=> $other->squareMeters;
    }

    public function equals(self $other): bool
    {
        return abs($this->squareMeters - $other->squareMeters) < self::EPSILON;
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
        return $this->squareMeters < self::EPSILON;
    }

    /**
     * Format without trailing zeros.
     */
    public function format(?int $decimals = null, ?string $decimalSeparator = null, ?string $thousandsSeparator = null, ?string $unit = null): string
    {
        $unit ??= self::UNIT_SQUARE_METER;
        $value = $this->toUnit($unit);
        $decimals ??= self::UNIT_FORMAT_DECIMALS[$unit];

        $formatted = NumberHelper::format($value, $decimals, $decimalSeparator, $thousandsSeparator);

        return $formatted.' '.self::unitLabel($unit);
    }

    public function toFieldValue(): float
    {
        return $this->squareMeters;
    }

    public function jsonSerialize(): float
    {
        return $this->squareMeters;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
