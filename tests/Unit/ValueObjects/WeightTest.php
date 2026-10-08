<?php

namespace Amarenkov\MutableContent\Tests\Unit\ValueObjects;

use InvalidArgumentException;

use PHPUnit\Framework\Attributes\DataProvider;

use Amarenkov\MutableContent\Tests\TestCase;
use Amarenkov\MutableContent\ValueObjects\Weight;

class WeightTest extends TestCase
{
    public function test_converts_between_units(): void
    {
        $weight = Weight::fromGrams(1500);

        $this->assertSame(1.5, $weight->toKilograms());
        $this->assertSame(1500.0, $weight->toGrams());
        $this->assertSame(0.0015, $weight->toTonnes());
        $this->assertSame(2500.0, Weight::fromTonnes(2.5)->toKilograms());
        $this->assertSame(0.25, Weight::fromUnit(250, Weight::UNIT_GRAM)->toKilograms());
        $this->assertSame(1.2, Weight::fromKilograms(1200)->toUnit(Weight::UNIT_TONNE));
    }

    public function test_accepts_numeric_strings(): void
    {
        $this->assertSame(12.5, Weight::fromKilograms('12.5')->kilograms);
        $this->assertSame(0.5, Weight::fromUnit('500', Weight::UNIT_GRAM)->kilograms);
    }

    public static function invalidValues(): array
    {
        return [
            'negative' => [fn () => new Weight(-1)],
            'infinite' => [fn () => new Weight(INF)],
            'not numeric' => [fn () => Weight::fromKilograms('abc')],
            'unknown unit' => [fn () => Weight::fromUnit(1, 'lb')],
            'division by zero' => [fn () => Weight::fromKilograms(1)->divide(0)],
            'array field value' => [fn () => Weight::fromFieldValue([1])],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_values(callable $make): void
    {
        $this->expectException(InvalidArgumentException::class);

        $make();
    }

    public function test_field_value_round_trip(): void
    {
        $this->assertNull(Weight::fromFieldValue(null));
        $this->assertNull(Weight::fromFieldValue(''));
        $this->assertSame(3.25, Weight::fromFieldValue('3.25')->toFieldValue());

        $weight = Weight::fromKilograms(2);

        $this->assertSame($weight, Weight::fromFieldValue($weight));
        $this->assertSame('{"w":2.0}', json_encode(['w' => $weight], JSON_PRESERVE_ZERO_FRACTION));
        $this->assertSame(2.0, $weight->jsonSerialize());
    }

    public function test_arithmetic(): void
    {
        $a = Weight::fromKilograms(3);
        $b = Weight::fromKilograms(1.5);

        $this->assertSame(4.5, $a->add($b)->kilograms);
        $this->assertSame(1.5, $a->subtract($b)->kilograms);
        $this->assertSame(6.0, $a->multiply(2)->kilograms);
        $this->assertSame(1.0, $a->divide(3)->kilograms);
        $this->assertSame(6.0, Weight::sum([$a, $b, $b])->kilograms);
        $this->assertTrue(Weight::sum([])->isZero());
    }

    public function test_subtract_clamps_rounding_noise_to_zero(): void
    {
        $result = Weight::fromKilograms(0.3)->subtract(Weight::fromKilograms(0.1 + 0.2));

        $this->assertTrue($result->isZero());

        $this->expectException(InvalidArgumentException::class);

        Weight::fromKilograms(1)->subtract(Weight::fromKilograms(2));
    }

    public function test_comparison_with_tolerance(): void
    {
        $a = Weight::fromKilograms(1);

        $this->assertTrue($a->equals(Weight::fromKilograms(1.0000001)));
        $this->assertSame(0, $a->compareTo(Weight::fromKilograms(1.0000001)));
        $this->assertTrue($a->isGreaterThan(Weight::fromKilograms(0.5)));
        $this->assertTrue($a->isLessThan(Weight::fromKilograms(2)));
        $this->assertFalse($a->isZero());
    }

    public function test_format(): void
    {
        $this->assertSame('12.5 kg', Weight::fromKilograms(12.5)->format(decimalSeparator: '.'));
        $this->assertSame('1 234,567 kg', Weight::fromKilograms(1234.5671)->format());
        $this->assertSame('500 g', Weight::fromKilograms(0.5)->format(unit: Weight::UNIT_GRAM));
        $this->assertSame('3 kg', (string)Weight::fromKilograms(3));
    }

    public function test_format_uses_current_locale(): void
    {
        $this->app->setLocale('ru');

        $this->assertSame('12,5 кг', Weight::fromKilograms(12.5)->format());
        $this->assertSame(['g' => 'г', 'kg' => 'кг', 't' => 'т'], Weight::unitOptions());
    }
}
