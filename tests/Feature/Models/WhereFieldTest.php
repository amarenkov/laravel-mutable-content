<?php

namespace Amarenkov\MutableContent\Tests\Feature\Models;

use Illuminate\Support\Carbon;

use InvalidArgumentException;

use Amarenkov\MutableContent\ValueObjects\Weight;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Lovs\RecordStatus;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class WhereFieldTest extends FeatureTestCase
{
    protected function record(string $code, array $fields): Record
    {
        $record = new Record();
        $record->mergeWithFields(['code' => $code] + $fields);
        $record->save();

        return $record;
    }

    protected function codes($query): array
    {
        return $query->orderBy('id')->get()->pluck('code')->all();
    }

    public function test_numbers_are_compared_as_numbers(): void
    {
        $this->record('A', ['quantity' => 9, 'weight' => 9.5]);
        $this->record('B', ['quantity' => 10, 'weight' => 10.25]);
        $this->record('C', ['quantity' => 100]);

        $this->assertSame(['B', 'C'], $this->codes(Record::whereField('quantity', '>', 9)));
        $this->assertSame(['B'], $this->codes(Record::whereField('weight', '>=', 10)));
        $this->assertSame(['A'], $this->codes(Record::whereField('quantity', 9)));
    }

    public function test_value_objects_are_compared_in_base_units(): void
    {
        $this->record('A', ['weight' => Weight::fromGrams(500)]);
        $this->record('B', ['weight' => Weight::fromKilograms(2)]);

        $this->assertSame(['B'], $this->codes(Record::whereField('weight', '>', Weight::fromGrams(1500))));
        $this->assertSame(['A'], $this->codes(Record::whereField('weight', Weight::fromKilograms(0.5))));
    }

    public function test_dates_bools_and_lov_items(): void
    {
        $this->record('A', ['due_date' => '2026-01-15', 'is_active' => true, 'status' => RecordStatus::ACTIVE]);
        $this->record('B', ['due_date' => '2026-03-01', 'is_active' => false, 'status' => RecordStatus::DRAFT]);

        $this->assertSame(['B'], $this->codes(Record::whereField('due_date', '>', Carbon::parse('2026-02-01'))));
        $this->assertSame(['A'], $this->codes(Record::whereField('is_active', true)));
        $this->assertSame(['B'], $this->codes(Record::whereField('is_active', false)));
        $this->assertSame(['B'], $this->codes(Record::whereField('status', RecordStatus::DRAFT)));
    }

    public function test_extracted_and_linked_by_code_objects(): void
    {
        $owner = new Owner();
        $owner->mergeWithFields(['code' => 'OWN']);
        $owner->save();

        $this->record('A', ['owner_id' => $owner->id, 'owner_code' => 'OWN']);
        $this->record('B', []);

        $this->assertSame(['A'], $this->codes(Record::whereField('owner_id', $owner->id)));
        $this->assertSame(['A'], $this->codes(Record::whereField('owner_code', 'OWN')));
        $this->assertSame(['A'], $this->codes(Record::whereField('code', 'A')));
    }

    public function test_null_and_or(): void
    {
        $this->record('A', ['quantity' => 1]);
        $this->record('B', ['quantity' => 5]);
        $this->record('C', []);

        $this->assertSame(['C'], $this->codes(Record::whereField('quantity', null)));
        $this->assertSame(['A', 'B'], $this->codes(Record::whereField('quantity', '<>', null)));
        $this->assertSame(['A', 'C'], $this->codes(Record::where(fn ($query) => $query->whereField('quantity', '<', 2)->orWhereField('quantity', null))));
    }

    public function test_unsupported_operator_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Record::whereField('quantity', 'like', 1);
    }
}
