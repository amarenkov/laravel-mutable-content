<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\Helpers\NumberHelper;

/**
 * Weight in kilograms.
 */
final readonly class Weight implements FieldValue, HasUnits, JsonSerializable, Stringable
{
    // const
    public const GRAMS_IN_KILOGRAM = 1000;
    public const KILOGRAMS_IN_TONNE = 1000;

    public const EPSILON = 0.000001;

    public const UNIT_GRAM = 'g';
    public const UNIT_KILOGRAM = 'kg';
    public const UNIT_TONNE = 't';

    public const UNITS = [self::UNIT_GRAM, self::UNIT_KILOGRAM, self::UNIT_TONNE];

    protected const UNIT_KILOGRAMS = [
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
        if (is_string($kilograms)) {
            if (!is_numeric($kilograms)) {
                throw new InvalidArgumentException('Weight must be numeric, "'.$kilograms.'" given');
            }

            $kilograms = (float)$kilograms;
        }

        return new self((float)$kilograms);
    }

    public static function fromGrams(float|int $grams): self
    {
        return new self($grams / self::GRAMS_IN_KILOGRAM);
    }

    public static function fromTonnes(float|int $tonnes): self
    {
        return new self($tonnes * self::KILOGRAMS_IN_TONNE);
    }

    public static function fromUnit(float|int|string $value, string $unit): self
    {
        if (is_string($value)) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException('Weight must be numeric, "'.$value.'" given');
            }

            $value = (float)$value;
        }

        return new self(round($value * self::unitKilograms($unit), self::UNIT_PRECISION[self::UNIT_KILOGRAM]));
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    /**
     * From a field value: null for null or an empty string, otherwise as fromKilograms().
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
            throw new InvalidArgumentException('Weight must be numeric, '.get_debug_type($value).' given');
        }

        return self::fromKilograms($value);
    }

    /**
     * @param iterable<self> $weights
     */
    public static function sum(iterable $weights): self
    {
        $kilograms = 0.0;

        foreach ($weights as $weight) {
            $kilograms += $weight->kilograms;
        }

        return new self($kilograms);
    }

    public static function units(): array
    {
        return self::UNITS;
    }

    public static function baseUnit(): string
    {
        return self::UNIT_KILOGRAM;
    }

    public static function unitLabel(string $unit): string
    {
        self::unitKilograms($unit);

        return __('mutable-content::units.'.$unit);
    }

    /**
     * @return array<string, string> unit => label
     */
    public static function unitOptions(): array
    {
        return array_combine(self::UNITS, array_map(fn ($unit) => self::unitLabel($unit), self::UNITS));
    }

    protected static function unitKilograms(string $unit): float|int
    {
        if (!isset(self::UNIT_KILOGRAMS[$unit])) {
            throw new InvalidArgumentException('Unknown weight unit "'.$unit.'"');
        }

        return self::UNIT_KILOGRAMS[$unit];
    }

    // public
    public function __construct(
        public float $kilograms
    ) {
        if (!is_finite($kilograms)) {
            throw new InvalidArgumentException('Weight must be a finite number');
        }

        if ($kilograms < 0) {
            throw new InvalidArgumentException('Weight cannot be negative, '.$kilograms.' kg given');
        }
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

    public function toUnit(string $unit): float
    {
        return round($this->kilograms / self::unitKilograms($unit), self::UNIT_PRECISION[$unit]);
    }

    public function add(self $other): self
    {
        return new self($this->kilograms + $other->kilograms);
    }

    public function subtract(self $other): self
    {
        $kilograms = $this->kilograms - $other->kilograms;

        if ($kilograms < 0 && $kilograms > -self::EPSILON) {
            $kilograms = 0.0;
        }

        return new self($kilograms);
    }

    public function multiply(float|int $factor): self
    {
        return new self($this->kilograms * $factor);
    }

    public function divide(float|int $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Weight cannot be divided by zero');
        }

        return new self($this->kilograms / $divisor);
    }

    public function compareTo(self $other): int
    {
        if ($this->equals($other)) {
            return 0;
        }

        return $this->kilograms <=> $other->kilograms;
    }

    public function equals(self $other): bool
    {
        return abs($this->kilograms - $other->kilograms) < self::EPSILON;
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
        return $this->kilograms < self::EPSILON;
    }

    /**
     * Format to the gram without trailing zeros.
     */
    public function format(?int $decimals = null, ?string $decimalSeparator = null, ?string $thousandsSeparator = null, ?string $unit = null): string
    {
        $unit ??= self::UNIT_KILOGRAM;
        $value = $this->toUnit($unit);
        $decimals ??= self::UNIT_FORMAT_DECIMALS[$unit];

        $formatted = NumberHelper::format($value, $decimals, $decimalSeparator, $thousandsSeparator);

        return $formatted.' '.self::unitLabel($unit);
    }

    public function toFieldValue(): float
    {
        return $this->kilograms;
    }

    public function jsonSerialize(): float
    {
        return $this->kilograms;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
