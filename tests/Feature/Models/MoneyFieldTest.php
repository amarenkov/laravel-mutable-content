<?php

namespace Amarenkov\MutableContent\Tests\Feature\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use Amarenkov\MutableContent\Database\Seeders\LovsSeeder;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Domain\LovRegistry;
use Amarenkov\MutableContent\Helpers\DatabaseHelper;
use Amarenkov\MutableContent\Helpers\RuleHelper;
use Amarenkov\MutableContent\Models\Field\Field;
use Amarenkov\MutableContent\Models\Lov\Currency;
use Amarenkov\MutableContent\Models\Lov\Item;
use Amarenkov\MutableContent\Rules\NotZero;
use Amarenkov\MutableContent\ValueObjects\Money;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class MoneyFieldTest extends FeatureTestCase
{
    protected function makeRecord(string $code, array $fields = []): Record
    {
        $record = new Record();
        $record->fill(['code' => $code, Record::FIELD_QUANTITY => 1] + $fields);
        $record->save();

        return $record;
    }

    protected function addCurrency(string $code, string $label, ?int $decimals, ?string $symbol): Item
    {
        $item = new Item();
        $item->fill(array_filter([
            Item::FIELD_LOV_ID => Currency::instance()->id,
            'code' => $code,
            'label' => $label,
            Currency::ITEM_FIELD_DECIMALS => $decimals,
            Currency::ITEM_FIELD_SYMBOL => $symbol,
        ], fn ($value) => $value !== null));
        $item->save();

        return $item;
    }

    protected function errors(array $data): array
    {
        return Validator::make(array_replace(['code' => 'a', 'quantity' => 1], $data), RuleHelper::getValidationRules(Record::class), [], RuleHelper::getValidationAttributes(Record::class))
            ->errors()
            ->toArray();
    }

    public function test_currency_lov_has_usd_with_item_fields(): void
    {
        $usd = Currency::instance()->items()->where('code', Currency::USD)->first();

        $this->assertTrue(Currency::instance()->isSystem());
        $this->assertSame(['USD'], Currency::instance()->items()->pluck('code')->all());
        $this->assertSame(2, $usd->{Currency::ITEM_FIELD_DECIMALS});
        $this->assertSame('$', $usd->{Currency::ITEM_FIELD_SYMBOL});
        $this->assertSame(2, Currency::decimalsOf(Currency::USD));
        $this->assertSame('$', Currency::symbolOf(Currency::USD));
    }

    public function test_seeder_keeps_item_fields_changed_in_the_admin(): void
    {
        $usd = Currency::instance()->items()->where('code', Currency::USD)->first();
        $usd->{Currency::ITEM_FIELD_SYMBOL} = 'US$';
        $usd->save();

        $this->seed(LovsSeeder::class);

        $this->assertSame('US$', $usd->fresh()->{Currency::ITEM_FIELD_SYMBOL});
        $this->assertSame(2, $usd->fresh()->{Currency::ITEM_FIELD_DECIMALS});
    }

    public function test_application_currencies_are_items_of_the_lov(): void
    {
        $this->addCurrency('BYN', 'Belarusian ruble', 2, 'Br');
        $this->addCurrency('JPY', 'Japanese yen', 0, null);

        $this->assertTrue(Currency::exists('BYN'));
        $this->assertSame('Br', Currency::symbolOf('BYN'));
        $this->assertSame(0, Currency::decimalsOf('JPY'));
        $this->assertSame('JPY', Currency::symbolOf('JPY'));
        $this->assertSame(Currency::DEFAULT_DECIMALS, Currency::decimalsOf('XXX'));
        $this->assertSame('Br', app(LovRegistry::class)->getLovItemField(Currency::CODE, 'BYN', Currency::ITEM_FIELD_SYMBOL));
    }

    public function test_amount_is_stored_as_a_json_number(): void
    {
        $record = $this->makeRecord('a', [Record::FIELD_PRICE => ['amount' => '1234.50', 'currency' => 'USD', 'extra' => 1]]);

        $this->assertSame(['amount' => 1234.5, 'currency' => 'USD'], $record->fresh()->{Record::FIELD_PRICE});
        $type = DatabaseHelper::isMariaDb()
            ? "json_type(json_extract(fields, '$.price.amount'))"
            : "jsonb_typeof(fields->'price'->'amount')";

        $this->assertContains(strtolower((string)Record::query()->whereKey($record->id)->toBase()->value(DB::raw($type))), ['number', 'double']);

        $record->{Record::FIELD_PRICE} = Money::of('-10', 'USD');
        $record->save();

        $this->assertSame(['amount' => -10, 'currency' => 'USD'], $record->fresh()->{Record::FIELD_PRICE});
    }

    public function test_empty_amount_clears_the_field(): void
    {
        $record = $this->makeRecord('a', [Record::FIELD_PRICE => ['amount' => '', 'currency' => 'USD']]);

        $this->assertNull($record->fresh()->{Record::FIELD_PRICE});
        $this->assertNull($record->fresh()->getMoney(Record::FIELD_PRICE));
    }

    public function test_get_money(): void
    {
        $money = $this->makeRecord('a', [Record::FIELD_PRICE => Money::of('99.90', 'USD')])->fresh()->getMoney(Record::FIELD_PRICE);

        $this->assertInstanceOf(Money::class, $money);
        $this->assertSame('99.9', $money->amount);
        $this->assertSame('USD', $money->currency);
        $this->assertSame("99.90\u{00A0}$", $money->format());
        $this->assertSame("-1,234,567.00\u{00A0}$", Money::of(-1234567, 'USD')->format());
    }

    public function test_format_uses_the_locale_separators(): void
    {
        app()->setLocale('ru');

        $this->assertSame("1 234,50\u{00A0}$", Money::of('1234.5', 'USD')->format());
        $this->assertSame('1 235', Money::of('1234.5', 'USD')->formatAmount(0));
    }

    public function test_validation_checks_the_currency_and_decimals(): void
    {
        $this->addCurrency('JPY', 'Japanese yen', 0, null);

        $this->assertSame([], $this->errors([Record::FIELD_PRICE => ['amount' => '-10.5', 'currency' => 'USD']]));
        $this->assertSame([], $this->errors([Record::FIELD_PRICE => Money::of(0, 'JPY')]));
        $this->assertSame(['The Price currency must be an item of the "Currency" LOV.'], $this->errors([Record::FIELD_PRICE => ['amount' => 1, 'currency' => 'EUR']])['price']);
        $this->assertSame(['The Price must have at most 0 digits after the decimal point.'], $this->errors([Record::FIELD_PRICE => ['amount' => '1.5', 'currency' => 'JPY']])['price']);
        $this->assertSame(['The Price must have at most 2 digits after the decimal point.'], $this->errors([Record::FIELD_PRICE => ['amount' => '1.005', 'currency' => 'USD']])['price']);
        $this->assertSame(['The Price must have an amount and a currency.'], $this->errors([Record::FIELD_PRICE => ['amount' => 'ten', 'currency' => 'USD']])['price']);
        $this->assertSame(['The Price must have an amount and a currency.'], $this->errors([Record::FIELD_PRICE => '10'])['price']);
    }

    public function test_type_settings_fix_the_currency_and_the_sign(): void
    {
        $this->addCurrency('BYN', 'Belarusian ruble', 2, 'Br');

        $this->assertSame([], $this->errors([Record::FIELD_FEE => ['amount' => 5, 'currency' => 'USD']]));
        $this->assertSame(['The Fee currency must be USD.'], $this->errors([Record::FIELD_FEE => ['amount' => 5, 'currency' => 'BYN']])['fee']);
        $this->assertSame(['The Fee must not be negative.'], $this->errors([Record::FIELD_FEE => ['amount' => -5, 'currency' => 'USD']])['fee']);
        $this->assertSame(['The Fee must not be zero.'], $this->errors([Record::FIELD_FEE => ['amount' => '0.00', 'currency' => 'USD']])['fee']);
    }

    public function test_currency_setting_accepts_only_currencies_of_the_lov(): void
    {
        $this->assertContains(Currency::USD, TypeSettings::getAllowedValues('money')[TypeSettings::CURRENCY]);
        $this->assertSame([TypeSettings::CURRENCY => 'USD'], TypeSettings::normalize('money', [TypeSettings::CURRENCY => 'USD']));

        $this->expectException(\InvalidArgumentException::class);

        TypeSettings::normalize('money', [TypeSettings::CURRENCY => 'EUR']);
    }

    public function test_decimals_setting_replaces_the_currency_decimals(): void
    {
        $this->assertSame(range(0, Money::MAX_DECIMALS), TypeSettings::getAllowedValues('money')[TypeSettings::DECIMALS]);
        $this->assertSame([TypeSettings::DECIMALS => 6], TypeSettings::normalize('money', [TypeSettings::DECIMALS => 6]));
        $this->assertArrayNotHasKey(TypeSettings::DECIMALS, TypeSettings::getAllowedValues('float'));

        $field = Record::getFieldDefinitions()[Record::FIELD_PRICE];

        $this->assertNull(TypeSettings::decimals($field));
        $this->assertArrayHasKey('price', $this->errors([Record::FIELD_PRICE => ['amount' => '0.00452', 'currency' => 'USD']]));

        $usage = Field::where('code', Record::FIELD_PRICE)->first()->usages()->first();
        $usage->field_type_settings = [TypeSettings::DECIMALS => 6];
        $usage->save();
        Record::flushFieldDefinitions();

        $this->assertSame(6, TypeSettings::decimals(Record::getFieldDefinitions()[Record::FIELD_PRICE]));
        $this->assertSame([], $this->errors([Record::FIELD_PRICE => ['amount' => '0.00452', 'currency' => 'USD']]));
        $this->assertSame(['The Price must have at most 6 digits after the decimal point.'], $this->errors([Record::FIELD_PRICE => ['amount' => '0.0045201', 'currency' => 'USD']])['price']);

        $usage->field_type_settings = [TypeSettings::DECIMALS => 0];
        $usage->save();
        Record::flushFieldDefinitions();

        $this->assertSame(['The Price must have at most 0 digits after the decimal point.'], $this->errors([Record::FIELD_PRICE => ['amount' => '1.5', 'currency' => 'USD']])['price']);

        $record = $this->makeRecord('a', [Record::FIELD_PRICE => Money::of('0.004520', 'USD')]);

        $this->assertSame('0.00452', $record->fresh()->getMoney(Record::FIELD_PRICE)->amount);
    }

    public function test_decimals_setting_rejects_other_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TypeSettings::normalize('money', [TypeSettings::DECIMALS => Money::MAX_DECIMALS + 1]);
    }

    public function test_sign_settings_of_number_fields(): void
    {
        $this->assertSame(['integer', 'required'], RuleHelper::getValidationRules(Record::class)['quantity']);

        $usage = Field::where('code', 'quantity')->first()->usages()->first();

        $usage->field_type_settings = [TypeSettings::ALLOW_NEGATIVE => false];
        $usage->save();
        Record::flushFieldDefinitions();

        $this->assertSame(['integer', 'min:0', 'required'], RuleHelper::getValidationRules(Record::class)['quantity']);
        $this->assertArrayHasKey('quantity', $this->errors(['quantity' => -1]));

        $usage->field_type_settings = [TypeSettings::ALLOW_NEGATIVE => false, TypeSettings::ALLOW_ZERO => false];
        $usage->save();
        Record::flushFieldDefinitions();

        $this->assertSame(['integer', 'gt:0', 'required'], RuleHelper::getValidationRules(Record::class)['quantity']);

        $usage->field_type_settings = [TypeSettings::ALLOW_ZERO => false];
        $usage->save();
        Record::flushFieldDefinitions();

        $rules = RuleHelper::getValidationRules(Record::class)['quantity'];

        $this->assertInstanceOf(NotZero::class, $rules[1]);
        $this->assertSame(['The Quantity must not be zero.'], $this->errors(['quantity' => 0])['quantity']);
        $this->assertSame([], $this->errors(['quantity' => -3]));
    }

    public function test_measurements_keep_their_defaults(): void
    {
        $this->assertSame(['nullable', 'numeric', 'gt:0'], RuleHelper::getValidationRules(Record::class)['weight']);
        $this->assertArrayHasKey('weight', $this->errors(['weight' => -1]));

        $usage = Field::where('code', 'weight')->first()->usages()->first();
        $usage->field_type_settings = [TypeSettings::ALLOW_NEGATIVE => true];
        $usage->save();
        Record::flushFieldDefinitions();

        $this->assertEquals(['nullable', 'numeric', new NotZero()], RuleHelper::getValidationRules(Record::class)['weight']);
        $this->assertSame([], $this->errors(['weight' => -1.5]));

        $record = $this->makeRecord('a', [Record::FIELD_WEIGHT => -1.5]);

        $this->assertSame(-1.5, $record->fresh()->getWeight(Record::FIELD_WEIGHT)->kilograms);
    }
}
