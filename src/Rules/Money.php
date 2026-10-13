<?php

namespace Amarenkov\MutableContent\Rules;

use Closure;

use Illuminate\Contracts\Validation\ValidationRule;

use Amarenkov\MutableContent\Domain\LovRegistry;

use Amarenkov\MutableContent\Models\Lov\Currency;

use Amarenkov\MutableContent\ValueObjects\Money as MoneyValue;

/**
 * Money field value: {"amount": number, "currency": code of a currency LOV item} or a Money object.
 */
class Money implements ValidationRule
{
    public function __construct(
        protected ?string $currency = null,
        protected bool $allowNegative = true,
        protected bool $allowZero = true
    )
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof MoneyValue) {
            $value = $value->toFieldValue();
        }

        if (!is_array($value) || !MoneyValue::isAmount($value[MoneyValue::KEY_AMOUNT] ?? null)) {
            $fail(__('mutable-content::validation.money.format'));

            return;
        }

        $currency = $value[MoneyValue::KEY_CURRENCY] ?? null;

        if (!Currency::exists($currency)) {
            $fail(__('mutable-content::validation.money.currency', [
                'lov' => app(LovRegistry::class)->getLovLabel(Currency::CODE) ?? Currency::CODE,
            ]));

            return;
        }

        if ($this->currency !== null && $currency !== $this->currency) {
            $fail(__('mutable-content::validation.money.currency_fixed', ['currency' => $this->currency]));

            return;
        }

        $money = MoneyValue::of($value[MoneyValue::KEY_AMOUNT], $currency);

        if ($money->scale() > $money->decimals()) {
            $fail(__('mutable-content::validation.money.decimals', ['decimals' => $money->decimals()]));
        }

        if (!$this->allowNegative && $money->isNegative()) {
            $fail(__('mutable-content::validation.not_negative'));
        }

        if (!$this->allowZero && $money->isZero()) {
            $fail(__('mutable-content::validation.not_zero'));
        }
    }
}
