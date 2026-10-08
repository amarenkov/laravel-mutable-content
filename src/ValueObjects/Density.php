<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Density in kg/m³, not negative. Zero is allowed by the allow_zero type setting.
 */
final readonly class Density implements FieldValue, JsonSerializable, Stringable
{
    // const
    public const KG_PER_M3_IN_G_PER_CM3 = 1000;
    public const KG_PER_M3_IN_KG_PER_L = 1000;
    public const KG_PER_M3_IN_T_PER_M3 = 1000;

    public const EPSILON = 0.000001;

    // static
    public static function fromKilogramsPerCubicMeter(float|int|string $kilogramsPerCubicMeter): self
    {
        if (is_string($kilogramsPerCubicMeter)) {
            if (!is_numeric($kilogramsPerCubicMeter)) {
                throw new InvalidArgumentException('Density must be numeric, "'.$kilogramsPerCubicMeter.'" given');
            }

            $kilogramsPerCubicMeter = (float)$kilogramsPerCubicMeter;
        }

        return new self((float)$kilogramsPerCubicMeter);
    }

    public static function fromGramsPerCubicCentimeter(float|int $gramsPerCubicCentimeter): self
    {
        return new self($gramsPerCubicCentimeter * self::KG_PER_M3_IN_G_PER_CM3);
    }

    public static function fromKilogramsPerLiter(float|int $kilogramsPerLiter): self
    {
        return new self($kilogramsPerLiter * self::KG_PER_M3_IN_KG_PER_L);
    }

    public static function fromTonnesPerCubicMeter(float|int $tonnesPerCubicMeter): self
    {
        return new self($tonnesPerCubicMeter * self::KG_PER_M3_IN_T_PER_M3);
    }

    public static function fromWeightAndVolume(Weight $weight, float|int $cubicMeters): self
    {
        if ($cubicMeters <= 0) {
            throw new InvalidArgumentException('Volume must be greater than zero, '.$cubicMeters.' m3 given');
        }

        return new self($weight->kilograms / $cubicMeters);
    }

    /**
     * From a field value: null for null or an empty string, otherwise as fromKilogramsPerCubicMeter().
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
            throw new InvalidArgumentException('Density must be numeric, '.get_debug_type($value).' given');
        }

        return self::fromKilogramsPerCubicMeter($value);
    }

    // public
    public function __construct(
        public float $kilogramsPerCubicMeter
    ) {
        if (!is_finite($kilogramsPerCubicMeter)) {
            throw new InvalidArgumentException('Density must be a finite number');
        }

        if ($kilogramsPerCubicMeter < 0) {
            throw new InvalidArgumentException('Density cannot be negative, '.$kilogramsPerCubicMeter.' kg/m3 given');
        }
    }

    public function toKilogramsPerCubicMeter(): float
    {
        return $this->kilogramsPerCubicMeter;
    }

    public function toGramsPerCubicCentimeter(): float
    {
        return $this->kilogramsPerCubicMeter / self::KG_PER_M3_IN_G_PER_CM3;
    }

    public function toKilogramsPerLiter(): float
    {
        return $this->kilogramsPerCubicMeter / self::KG_PER_M3_IN_KG_PER_L;
    }

    public function toTonnesPerCubicMeter(): float
    {
        return $this->kilogramsPerCubicMeter / self::KG_PER_M3_IN_T_PER_M3;
    }

    public function weightOf(float|int $cubicMeters): Weight
    {
        return new Weight($this->kilogramsPerCubicMeter * $cubicMeters);
    }

    public function volumeOf(Weight $weight): float
    {
        if ($this->isZero()) {
            throw new InvalidArgumentException('Volume cannot be derived from zero density');
        }

        return $weight->kilograms / $this->kilogramsPerCubicMeter;
    }

    public function multiply(float|int $factor): self
    {
        return new self($this->kilogramsPerCubicMeter * $factor);
    }

    public function divide(float|int $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Density cannot be divided by zero');
        }

        return new self($this->kilogramsPerCubicMeter / $divisor);
    }

    public function compareTo(self $other): int
    {
        if ($this->equals($other)) {
            return 0;
        }

        return $this->kilogramsPerCubicMeter <=> $other->kilogramsPerCubicMeter;
    }

    public function equals(self $other): bool
    {
        return abs($this->kilogramsPerCubicMeter - $other->kilogramsPerCubicMeter) < self::EPSILON;
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
        return $this->kilogramsPerCubicMeter < self::EPSILON;
    }

    /**
     * Format in kg/m³ without trailing zeros.
     */
    public function format(int $decimals = 3, string $decimalSeparator = ',', string $thousandsSeparator = ' '): string
    {
        $formatted = number_format($this->kilogramsPerCubicMeter, $decimals, $decimalSeparator, $thousandsSeparator);

        if ($decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), $decimalSeparator);
        }

        return $formatted.' '.__('mutable-content::units.kg_per_m3');
    }

    public function toFieldValue(): float
    {
        return $this->kilogramsPerCubicMeter;
    }

    public function jsonSerialize(): float
    {
        return $this->kilogramsPerCubicMeter;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
