<?php

namespace Amarenkov\MutableContent\Tests\Unit\ValueObjects;

use InvalidArgumentException;

use Amarenkov\MutableContent\Tests\TestCase;
use Amarenkov\MutableContent\ValueObjects\Duration;

class DurationTest extends TestCase
{
    public function test_units(): void
    {
        $this->assertSame('s', Duration::baseUnit());
        $this->assertSame(['s', 'min', 'h'], Duration::units());
        $this->assertSame(['s', 'min', 'h'], array_keys(Duration::unitOptions()));
    }

    public function test_stored_in_whole_seconds(): void
    {
        $this->assertSame(5400, Duration::fromUnit(1.5, Duration::UNIT_HOUR)->toFieldValue());
        $this->assertSame(90, Duration::fromUnit('1.5', Duration::UNIT_MINUTE)->toFieldValue());
        $this->assertSame(3, Duration::fromSeconds(2.6)->toFieldValue());
        $this->assertSame(1.5, Duration::fromSeconds(5400)->toUnit(Duration::UNIT_HOUR));
        $this->assertSame(90.0, Duration::fromHours(1.5)->toMinutes());
    }

    public function test_field_value(): void
    {
        $this->assertNull(Duration::fromFieldValue(null));
        $this->assertNull(Duration::fromFieldValue(''));
        $this->assertSame(120, Duration::fromFieldValue('120')->toFieldValue());
        $this->assertTrue(Duration::zero()->isZero());
    }

    public function test_rejects_unknown_unit_and_non_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::fromUnit(1, 'day');
    }

    public function test_rejects_non_numeric_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::fromFieldValue('1:30');
    }

    public function test_arithmetic(): void
    {
        $sum = Duration::sum([Duration::fromMinutes(30), Duration::fromMinutes(45)]);

        $this->assertSame(4500, $sum->toSeconds());
        $this->assertTrue($sum->isGreaterThan(Duration::fromHours(1)));
        $this->assertSame(-900, Duration::fromMinutes(30)->subtract(Duration::fromMinutes(45))->toSeconds());
        $this->assertTrue(Duration::fromSeconds(-1)->isNegative());
        $this->assertSame(2250, $sum->divide(2)->toSeconds());
    }

    public function test_format(): void
    {
        $this->assertSame('1.5 h', Duration::fromSeconds(5400)->format());
        $this->assertSame('1.33 h', Duration::fromSeconds(4800)->format());
        $this->assertSame('80 min', Duration::fromSeconds(4800)->format(unit: Duration::UNIT_MINUTE));
        $this->assertSame('1:20:05', Duration::fromSeconds(4805)->clock());
        $this->assertSame('1:20', Duration::fromSeconds(4805)->clock(false));
        $this->assertSame('-0:01:00', Duration::fromSeconds(-60)->clock());
    }
}
