<?php

namespace Amarenkov\MutableContent\Tests\Unit\ValueObjects;

use InvalidArgumentException;

use Amarenkov\MutableContent\Tests\TestCase;
use Amarenkov\MutableContent\ValueObjects\Density;
use Amarenkov\MutableContent\ValueObjects\Length;
use Amarenkov\MutableContent\ValueObjects\SurfaceDensity;
use Amarenkov\MutableContent\ValueObjects\Weight;

class DensityTest extends TestCase
{
    public function test_density_conversions(): void
    {
        $steel = Density::fromGramsPerCubicCentimeter(7.85);

        $this->assertEqualsWithDelta(7850.0, $steel->toKilogramsPerCubicMeter(), 1e-9);
        $this->assertEqualsWithDelta(7.85, $steel->toKilogramsPerLiter(), 1e-9);
        $this->assertEqualsWithDelta(7.85, $steel->toTonnesPerCubicMeter(), 1e-9);
        $this->assertEqualsWithDelta(1000.0, Density::fromKilogramsPerLiter(1)->toKilogramsPerCubicMeter(), 1e-9);
    }

    public function test_density_weight_and_volume(): void
    {
        $density = Density::fromWeightAndVolume(Weight::fromKilograms(500), 0.25);

        $this->assertSame(2000.0, $density->toKilogramsPerCubicMeter());
        $this->assertSame(100.0, $density->weightOf(0.05)->toKilograms());
        $this->assertSame(0.5, $density->volumeOf(Weight::fromKilograms(1000)));
    }

    public function test_density_allows_zero_but_not_negative(): void
    {
        $this->assertTrue(Density::fromKilogramsPerCubicMeter(0)->isZero());

        $this->expectException(InvalidArgumentException::class);

        Density::fromKilogramsPerCubicMeter(-1);
    }

    public function test_volume_of_zero_density_fails(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Density::fromKilogramsPerCubicMeter(0)->volumeOf(Weight::fromKilograms(1));
    }

    public function test_surface_density(): void
    {
        $paper = SurfaceDensity::fromGramsPerSquareMeter(80);

        $this->assertSame(0.08, $paper->toKilogramsPerSquareMeter());
        $this->assertEqualsWithDelta(8.0, $paper->weightOf(100)->toKilograms(), 1e-9);
        $this->assertEqualsWithDelta(50.0, $paper->areaOf(Weight::fromKilograms(4)), 1e-9);
        $this->assertSame(1.25, SurfaceDensity::fromWeightAndArea(Weight::fromKilograms(5), 4)->toKilogramsPerSquareMeter());
    }

    public function test_surface_density_and_thickness(): void
    {
        $steel = Density::fromKilogramsPerCubicMeter(7850);
        $sheet = SurfaceDensity::fromDensityAndThickness($steel, Length::fromMillimeters(2));

        $this->assertEqualsWithDelta(15.7, $sheet->toKilogramsPerSquareMeter(), 1e-9);
        $this->assertEqualsWithDelta(0.002, $sheet->thicknessOf($steel)->toMeters(), 1e-12);
    }

    public function test_field_values(): void
    {
        $this->assertNull(Density::fromFieldValue(''));
        $this->assertNull(SurfaceDensity::fromFieldValue(null));
        $this->assertSame(1.5, SurfaceDensity::fromFieldValue('1.5')->toFieldValue());
    }

    public function test_format(): void
    {
        $this->assertSame('7 850 kg/m³', Density::fromKilogramsPerCubicMeter(7850)->format());
        $this->assertSame('1,25 kg/m²', SurfaceDensity::fromKilogramsPerSquareMeter(1.25)->format());
    }
}
