<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\Helpers\NumberHelper;
use Amarenkov\MutableContent\ValueObjects\Concerns\IsMeasure;

/**
 * Surface density in kg/m². Zero and negative values are allowed by the allow_zero and allow_negative type settings.
 */
final readonly class SurfaceDensity implements FieldValue, JsonSerializable, Stringable
{
    use IsMeasure;

    // const
    protected const NAME = 'Surface density';

    public const G_PER_M2_IN_KG_PER_M2 = 1000;

    public const EPSILON = 0.000001;

    // static
    public static function fromKilogramsPerSquareMeter(float|int|string $kilogramsPerSquareMeter): self
    {
        return self::fromBaseValue($kilogramsPerSquareMeter);
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

    // protected
    protected function baseValue(): float
    {
        return $this->kilogramsPerSquareMeter;
    }

    // public
    public function __construct(
        public float $kilogramsPerSquareMeter
    ) {
        self::assertFinite($kilogramsPerSquareMeter);
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

    /**
     * Format in kg/m² without trailing zeros.
     */
    public function format(int $decimals = 3, ?string $decimalSeparator = null, ?string $thousandsSeparator = null): string
    {
        $formatted = NumberHelper::format($this->kilogramsPerSquareMeter, $decimals, $decimalSeparator, $thousandsSeparator);

        return $formatted.' '.__('mutable-content::units.kg_per_m2');
    }

}
