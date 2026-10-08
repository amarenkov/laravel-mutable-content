<?php

namespace Amarenkov\MutableContent\Tests\Unit\ValueObjects;

use InvalidArgumentException;

use PHPUnit\Framework\Attributes\DataProvider;

use Amarenkov\MutableContent\Tests\TestCase;
use Amarenkov\MutableContent\ValueObjects\Area;
use Amarenkov\MutableContent\ValueObjects\HasUnits;
use Amarenkov\MutableContent\ValueObjects\Length;
use Amarenkov\MutableContent\ValueObjects\Volume;
use Amarenkov\MutableContent\ValueObjects\Weight;

class UnitValueObjectsTest extends TestCase
{
    public static function classes(): array
    {
        return [
            'weight' => [Weight::class, 'kg', ['g', 'kg', 't']],
            'length' => [Length::class, 'm', ['mm', 'cm', 'm', 'km']],
            'area' => [Area::class, 'm2', ['mm2', 'cm2', 'm2']],
            'volume' => [Volume::class, 'm3', ['cm3', 'l', 'm3']],
        ];
    }

    public static function classesOnly(): array
    {
        return array_map(fn (array $row) => [$row[0]], self::classes());
    }

    /**
     * @param class-string<HasUnits> $class
     * @param array<string> $units
     */
    #[DataProvider('classes')]
    public function test_units(string $class, string $baseUnit, array $units): void
    {
        $this->assertSame($baseUnit, $class::baseUnit());
        $this->assertSame($units, $class::units());
        $this->assertSame($units, array_keys($class::unitOptions()));
    }

    /**
     * @param class-string<HasUnits> $class
     * @param array<string> $units
     */
    #[DataProvider('classes')]
    public function test_unit_round_trip(string $class, string $baseUnit, array $units): void
    {
        foreach ($units as $unit) {
            $this->assertEqualsWithDelta(1234.0, $class::fromUnit(1234, $unit)->toUnit($unit), 1e-9, $unit);
        }

        $this->assertSame(1.0, $class::fromUnit(1, $baseUnit)->toFieldValue());
    }

    /**
     * @param class-string<HasUnits> $class
     */
    #[DataProvider('classesOnly')]
    public function test_field_value(string $class): void
    {
        $this->assertNull($class::fromFieldValue(null));
        $this->assertNull($class::fromFieldValue(''));
        $this->assertSame(2.5, $class::fromFieldValue('2.5')->toFieldValue());
        $this->assertTrue($class::zero()->isZero());
    }

    /**
     * @param class-string<HasUnits> $class
     */
    #[DataProvider('classesOnly')]
    public function test_rejects_unknown_unit(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);

        $class::fromUnit(1, 'parsec');
    }

    /**
     * @param class-string<HasUnits> $class
     */
    #[DataProvider('classesOnly')]
    public function test_rejects_negative(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);

        $class::fromFieldValue(-1);
    }

    public function test_conversions(): void
    {
        $this->assertSame(1250.0, Length::fromMeters(1.25)->toMillimeters());
        $this->assertSame(125.0, Length::fromMeters(1.25)->toCentimeters());
        $this->assertSame(3.0, Length::fromKilometers(0.003)->toMeters());
        $this->assertSame(1.0, Area::fromUnit(10000, Area::UNIT_SQUARE_CENTIMETER)->toSquareMeters());
        $this->assertSame(0.001, Volume::fromUnit(1, Volume::UNIT_LITER)->toCubicMeters());
    }

    public function test_area_is_stored_to_the_square_millimeter(): void
    {
        $this->assertSame(7.0, Area::fromUnit(7.25, Area::UNIT_SQUARE_MILLIMETER)->toUnit(Area::UNIT_SQUARE_MILLIMETER));
        $this->assertSame(7.25, Area::fromUnit(7.25, Area::UNIT_SQUARE_CENTIMETER)->toUnit(Area::UNIT_SQUARE_CENTIMETER));
    }

    public function test_derived_values(): void
    {
        $area = Area::fromLengths(Length::fromMeters(2), Length::fromMeters(3));

        $this->assertSame(6.0, $area->toSquareMeters());
        $this->assertSame(24.0, Volume::fromLengths(Length::fromMeters(2), Length::fromMeters(3), Length::fromMeters(4))->toCubicMeters());
        $this->assertSame(3.0, Volume::fromAreaAndLength($area, Length::fromMeters(0.5))->toCubicMeters());
    }

    public function test_format(): void
    {
        $this->assertSame('12,5 m', Length::fromMeters(12.5)->format());
        $this->assertSame('3,6 m²', Area::fromSquareMeters(3.6)->format());
        $this->assertSame('0,045 m³', Volume::fromCubicMeters(0.045)->format());
        $this->assertSame('45 l', Volume::fromCubicMeters(0.045)->format(unit: Volume::UNIT_LITER));
    }
}
