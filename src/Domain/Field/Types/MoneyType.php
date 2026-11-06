<?php

namespace Amarenkov\MutableContent\Domain\Field\Types;

use Amarenkov\MutableContent\Contracts\MutableModel;
use Amarenkov\MutableContent\Domain\Field\Field;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Models\Lov\Currency;
use Amarenkov\MutableContent\Rules\Money as MoneyRule;
use Amarenkov\MutableContent\ValueObjects\Money;

/**
 * An amount in a currency, see Money.
 */
class MoneyType extends NumberType
{
    // public
    public function normalize(mixed $value, MutableModel $model, string $code): mixed
    {
        return $model::toMoneyValue($value);
    }

    public function comparesAsNumber(Field $field): bool
    {
        return false;
    }

    public function rules(Field $field): array
    {
        return [MoneyRule::forField($field)];
    }

    public function format(Field $field, mixed $value): string
    {
        $money = Money::fromFieldValue($value);

        return $money ? $money->format(TypeSettings::decimals($field)) : parent::format($field, $value);
    }

    public function settings(): array
    {
        return parent::settings() + [
            TypeSettings::CURRENCY => array_map('strval', array_keys(app(LovRegistry::class)->getLovItemsOptions(Currency::CODE))),
            TypeSettings::DECIMALS => range(0, Money::MAX_DECIMALS),
        ];
    }
}
