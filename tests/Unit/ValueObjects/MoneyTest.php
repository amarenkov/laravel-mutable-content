<?php

namespace Amarenkov\MutableContent\Tests\Unit\ValueObjects;

use InvalidArgumentException;

use Amarenkov\MutableContent\Tests\TestCase;
use Amarenkov\MutableContent\ValueObjects\Money;

class MoneyTest extends TestCase
{
    public function test_amount_is_kept_as_a_decimal_string(): void
    {
        $this->assertSame('1234.5', Money::of('1234.50', 'USD')->amount);
        $this->assertSame('1234.56', Money::of(1234.56, 'USD')->amount);
        $this->assertSame('0.3', Money::of(0.1 + 0.2, 'USD')->amount);
        $this->assertSame('-3', Money::of(-3, 'USD')->amount);
        $this->assertSame('0', Money::of('-0.00', 'USD')->amount);
        $this->assertSame('0.5', Money::of('+.5', 'USD')->amount);
        $this->assertSame('12', Money::of(' 012 ', 'USD')->amount);
    }

    public function test_malformed_amounts_are_rejected(): void
    {
        foreach (['', 'abc', '1,5', '1e3', '1.2.3'] as $amount) {
            $this->assertFalse(Money::isAmount($amount), $amount);
        }

        $this->assertFalse(Money::isAmount(INF));
        $this->assertFalse(Money::isAmount(null));
        $this->assertTrue(Money::isAmount('1.'));

        $this->expectException(InvalidArgumentException::class);

        Money::of('1e3', 'USD');
    }

    public function test_arithmetic_has_no_float_errors(): void
    {
        $sum = Money::of('0.1', 'USD')->add(Money::of('0.2', 'USD'));

        $this->assertSame('0.3', $sum->amount);
        $this->assertSame('-0.1', Money::of('0.2', 'USD')->subtract(Money::of('0.3', 'USD'))->amount);
        $this->assertSame('5', Money::of(-5, 'USD')->abs()->amount);
        $this->assertSame('-5', Money::of(5, 'USD')->negate()->amount);
        $this->assertSame('90071992547409.93', Money::of('90071992547409.92', 'USD')->add(Money::of('0.01', 'USD'))->amount);
    }

    public function test_comparison(): void
    {
        $this->assertSame(1, Money::of(2, 'USD')->compareTo(Money::of('1.99', 'USD')));
        $this->assertTrue(Money::of('1.50', 'USD')->equals(Money::of(1.5, 'USD')));
        $this->assertFalse(Money::of(1, 'USD')->equals(Money::of(1, 'EUR')));
        $this->assertTrue(Money::zero('USD')->isZero());
        $this->assertTrue(Money::of(-1, 'USD')->isNegative());
        $this->assertTrue(Money::of('0.01', 'USD')->isPositive());
        $this->assertSame(2, Money::of('1.25', 'USD')->scale());
    }

    public function test_different_currencies_do_not_mix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(1, 'USD')->add(Money::of(1, 'EUR'));
    }

    public function test_field_value_has_a_number_amount(): void
    {
        $this->assertSame(['amount' => 1234.56, 'currency' => 'USD'], Money::of('1234.56', 'USD')->toFieldValue());
        $this->assertSame(['amount' => 10, 'currency' => 'USD'], Money::of('10.00', 'USD')->toFieldValue());
        $this->assertSame('1234.56', Money::fromFieldValue(['amount' => 1234.56, 'currency' => 'USD'])->amount);
        $this->assertNull(Money::fromFieldValue(['amount' => 'x', 'currency' => 'USD']));
        $this->assertNull(Money::fromFieldValue(['amount' => 1]));
        $this->assertNull(Money::fromFieldValue(null));
    }
}
