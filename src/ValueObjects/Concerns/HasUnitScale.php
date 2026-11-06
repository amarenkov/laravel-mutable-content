<?php

namespace Amarenkov\MutableContent\ValueObjects\Concerns;

use InvalidArgumentException;

use Amarenkov\MutableContent\Helpers\NumberHelper;

/**
 * Units of a measure, see HasUnits. The class has the UNITS, BASE_UNIT, UNIT_FACTORS, UNIT_PRECISION and UNIT_FORMAT_DECIMALS constants.
 */
trait HasUnitScale
{
    use IsMeasure;

    // static
    public static function fromUnit(float|int|string $value, string $unit): self
    {
        return new self(round(self::toNumber($value) * self::unitFactor($unit), self::UNIT_PRECISION[self::BASE_UNIT]));
    }

    public static function zero(): self
    {
        return self::fromBaseValue(0);
    }

    /**
     * @param iterable<self> $values
     */
    public static function sum(iterable $values): self
    {
        $result = 0;

        foreach ($values as $value) {
            $result += $value->baseValue();
        }

        return self::fromBaseValue($result);
    }

    public static function units(): array
    {
        return self::UNITS;
    }

    public static function baseUnit(): string
    {
        return self::BASE_UNIT;
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
            throw new InvalidArgumentException('Unknown '.strtolower(self::NAME).' unit "'.$unit.'"');
        }

        return self::UNIT_FACTORS[$unit];
    }

    // public
    public function toUnit(string $unit): float
    {
        return round($this->baseValue() / self::unitFactor($unit), self::UNIT_PRECISION[$unit]);
    }

    public function add(self $other): self
    {
        return self::fromBaseValue($this->baseValue() + $other->baseValue());
    }

    public function subtract(self $other): self
    {
        $result = $this->baseValue() - $other->baseValue();

        return self::fromBaseValue(abs($result) < self::EPSILON ? 0 : $result);
    }

    /**
     * Format in the unit without trailing zeros, in the base unit by default.
     */
    public function format(?int $decimals = null, ?string $decimalSeparator = null, ?string $thousandsSeparator = null, ?string $unit = null): string
    {
        $unit ??= self::DEFAULT_FORMAT_UNIT;

        $formatted = NumberHelper::format($this->toUnit($unit), $decimals ?? self::UNIT_FORMAT_DECIMALS[$unit], $decimalSeparator, $thousandsSeparator);

        return $formatted.' '.self::unitLabel($unit);
    }
}
