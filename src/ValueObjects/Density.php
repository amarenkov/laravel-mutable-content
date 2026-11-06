<?php

namespace Amarenkov\MutableContent\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use Amarenkov\MutableContent\Helpers\NumberHelper;
use Amarenkov\MutableContent\ValueObjects\Concerns\IsMeasure;

/**
 * Density in kg/m³. Zero and negative values are allowed by the allow_zero and allow_negative type settings.
 */
final readonly class Density implements FieldValue, JsonSerializable, Stringable
{
    use IsMeasure;

    // const
    protected const NAME = 'Density';

    public const KG_PER_M3_IN_G_PER_CM3 = 1000;
    public const KG_PER_M3_IN_KG_PER_L = 1000;
    public const KG_PER_M3_IN_T_PER_M3 = 1000;

    public const EPSILON = 0.000001;

    // static
    public static function fromKilogramsPerCubicMeter(float|int|string $kilogramsPerCubicMeter): self
    {
        return self::fromBaseValue($kilogramsPerCubicMeter);
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

    // protected
    protected function baseValue(): float
    {
        return $this->kilogramsPerCubicMeter;
    }

    // public
    public function __construct(
        public float $kilogramsPerCubicMeter
    ) {
        self::assertFinite($kilogramsPerCubicMeter);
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

    /**
     * Format in kg/m³ without trailing zeros.
     */
    public function format(int $decimals = 3, ?string $decimalSeparator = null, ?string $thousandsSeparator = null): string
    {
        $formatted = NumberHelper::format($this->kilogramsPerCubicMeter, $decimals, $decimalSeparator, $thousandsSeparator);

        return $formatted.' '.__('mutable-content::units.kg_per_m3');
    }

}
