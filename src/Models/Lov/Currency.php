<?php

namespace Amarenkov\MutableContent\Models\Lov;

use Amarenkov\MutableContent\Domain\Field\Lov\Type as DomainLovFieldType;

use Amarenkov\MutableContent\Domain\LovRegistry;

use Amarenkov\MutableContent\Attributes\Class\Code as ClassCode;
use Amarenkov\MutableContent\Attributes\Class\Label as ClassLabel;
use Amarenkov\MutableContent\Attributes\Lov\Item as LovItem;
use Amarenkov\MutableContent\Attributes\Lov\ItemField;

use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Type as CFAType;
use Amarenkov\MutableContent\Attributes\FieldAttr\Common\Label as CFALabel;

/**
 * Currencies of money fields. Item codes are ISO 4217 codes; the package declares only USD,
 * an application adds its own currencies as items of this LOV.
 */
#[ClassCode(Currency::CODE)]
#[ClassLabel('Currency')]

#[LovItem(Currency::USD, 'US dollar', fields: [Currency::ITEM_FIELD_DECIMALS => 2, Currency::ITEM_FIELD_SYMBOL => '$'])]
class Currency extends Lov
{
    // const
    public const CODE = 'currency';

    public const USD = 'USD';

    public const DEFAULT_DECIMALS = 2;

    #[ItemField, CFAType(DomainLovFieldType::TYPE_INT), CFALabel('Decimal places')]
    public const ITEM_FIELD_DECIMALS = 'decimals';

    #[ItemField, CFAType(DomainLovFieldType::TYPE_STRING), CFALabel('Symbol')]
    public const ITEM_FIELD_SYMBOL = 'symbol';

    // static
    public static function exists(mixed $code): bool
    {
        return app(LovRegistry::class)->hasLovItem(self::CODE, $code);
    }

    /**
     * Digits after the decimal point; DEFAULT_DECIMALS when the item does not set it.
     */
    public static function decimalsOf(string $code): int
    {
        $decimals = app(LovRegistry::class)->getLovItemField(self::CODE, $code, self::ITEM_FIELD_DECIMALS);

        return is_int($decimals) && $decimals >= 0 ? $decimals : self::DEFAULT_DECIMALS;
    }

    /**
     * Symbol to show with an amount; the code when the item has none.
     */
    public static function symbolOf(string $code): string
    {
        $symbol = (string)app(LovRegistry::class)->getLovItemField(self::CODE, $code, self::ITEM_FIELD_SYMBOL);

        return $symbol === '' ? $code : $symbol;
    }
}
