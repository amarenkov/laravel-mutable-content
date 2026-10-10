<?php

namespace Amarenkov\MutableContent\Helpers;

class NumberHelper
{
    // static
    public static function decimalSeparator(): string
    {
        return __('mutable-content::units.decimal_separator');
    }

    public static function thousandsSeparator(): string
    {
        return __('mutable-content::units.thousands_separator');
    }

    /**
     * Number rounded to $decimals with trailing zeros dropped; separators of the current locale by default.
     */
    public static function format(float|int $value, int $decimals, ?string $decimalSeparator = null, ?string $thousandsSeparator = null): string
    {
        $decimalSeparator ??= static::decimalSeparator();

        $formatted = number_format($value, $decimals, $decimalSeparator, $thousandsSeparator ?? static::thousandsSeparator());

        if ($decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), $decimalSeparator);
        }

        return $formatted;
    }
}
