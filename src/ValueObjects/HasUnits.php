<?php

namespace Amarenkov\MutableContent\ValueObjects;

/**
 * Value object entered and displayed in a chosen unit (TypeSettings::DISPLAY_UNIT), stored in the base one.
 */
interface HasUnits
{
    /**
     * @return array<string> units from smallest to largest
     */
    public static function units(): array;

    public static function baseUnit(): string;

    public static function unitLabel(string $unit): string;

    /**
     * @return array<string, string> unit => label, from smallest to largest
     */
    public static function unitOptions(): array;

    public static function fromUnit(float|int|string $value, string $unit): self;

    public static function fromFieldValue(mixed $value): ?self;

    public function toUnit(string $unit): float;

    public function format(?int $decimals = null, ?string $decimalSeparator = null, ?string $thousandsSeparator = null, ?string $unit = null): string;
}
