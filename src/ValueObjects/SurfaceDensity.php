<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\Helpers\NumberHelper;

/**
 * Surface density in kg/m², not negative. Zero is allowed by the allow_zero type setting.
 */
final readonly class SurfaceDensity implements FieldValue, JsonSerializable, Stringable
{
    // const
    public const G_PER_M2_IN_KG_PER_M2 = 1000;

    public const EPSILON = 0.000001;

    // static
    public static function fromKilogramsPerSquareMeter(float|int|string $kilogramsPerSquareMeter): self
    {
        if (is_string($kilogramsPerSquareMeter)) {
            if (!is_numeric($kilogramsPerSquareMeter)) {
                throw new InvalidArgumentException('Surface density must be numeric, "'.$kilogramsPerSquareMeter.'" given');
            }

            $kilogramsPerSquareMeter = (float)$kilogramsPerSquareMeter;
        }

        return new self((float)$kilogramsPerSquareMeter);
    }

    public static function fromGramsPerSquareMeter(float|int $gramsPerSquareMeter): self
    {
        return new self($gramsPerSquareMeter / self::G_PER_M2_IN_KG_PER_M2);
    }

    public static function fromWeightAndArea(Weight $weight, float|int $squareMeters): self
    {
        if ($squareMeters <= 0) {
            throw new InvalidArgumentException('Area must be greater than zero, '.$squareMeters.' m2 given');
        }

        return new self($weight->kilograms / $squareMeters);
    }

    public static function fromDensityAndThickness(Density $density, Length $thickness): self
    {
        return new self($density->kilogramsPerCubicMeter * $thickness->meters);
    }

    /**
     * From a field value: null for null or an empty string, otherwise as fromKilogramsPerSquareMeter().
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
            throw new InvalidArgumentException('Surface density must be numeric, '.get_debug_type($value).' given');
        }

        return self::fromKilogramsPerSquareMeter($value);
    }

    // public
    public function __construct(
        public float $kilogramsPerSquareMeter
    ) {
        if (!is_finite($kilogramsPerSquareMeter)) {
            throw new InvalidArgumentException('Surface density must be a finite number');
        }

        if ($kilogramsPerSquareMeter < 0) {
            throw new InvalidArgumentException('Surface density cannot be negative, '.$kilogramsPerSquareMeter.' kg/m2 given');
        }
    }

    public function toKilogramsPerSquareMeter(): float
    {
        return $this->kilogramsPerSquareMeter;
    }

    public function toGramsPerSquareMeter(): float
    {
        return $this->kilogramsPerSquareMeter * self::G_PER_M2_IN_KG_PER_M2;
    }

    public function weightOf(float|int $squareMeters): Weight
    {
        return new Weight($this->kilogramsPerSquareMeter * $squareMeters);
    }

    public function areaOf(Weight $weight): float
    {
        if ($this->isZero()) {
            throw new InvalidArgumentException('Area cannot be derived from zero surface density');
        }

        return $weight->kilograms / $this->kilogramsPerSquareMeter;
    }

    public function thicknessOf(Density $density): Length
    {
        if ($density->isZero()) {
            throw new InvalidArgumentException('Thickness cannot be derived from zero density');
        }

        return new Length($this->kilogramsPerSquareMeter / $density->kilogramsPerCubicMeter);
    }

    public function multiply(float|int $factor): self
    {
        return new self($this->kilogramsPerSquareMeter * $factor);
    }

    public function divide(float|int $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Surface density cannot be divided by zero');
        }

        return new self($this->kilogramsPerSquareMeter / $divisor);
    }

    public function compareTo(self $other): int
    {
        if ($this->equals($other)) {
            return 0;
        }

        return $this->kilogramsPerSquareMeter <=> $other->kilogramsPerSquareMeter;
    }

    public function equals(self $other): bool
    {
        return abs($this->kilogramsPerSquareMeter - $other->kilogramsPerSquareMeter) < self::EPSILON;
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
        return $this->kilogramsPerSquareMeter < self::EPSILON;
    }

    /**
     * Format in kg/m² without trailing zeros.
     */
    public function format(int $decimals = 3, ?string $decimalSeparator = null, ?string $thousandsSeparator = null): string
    {
        $formatted = NumberHelper::format($this->kilogramsPerSquareMeter, $decimals, $decimalSeparator, $thousandsSeparator);

        return $formatted.' '.__('mutable-content::units.kg_per_m2');
    }

    public function toFieldValue(): float
    {
        return $this->kilogramsPerSquareMeter;
    }

    public function jsonSerialize(): float
    {
        return $this->kilogramsPerSquareMeter;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
