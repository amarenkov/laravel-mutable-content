<?php

namespace Amarenkov\MutableContent\Tests\Feature\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use LogicException;

use Amarenkov\MutableContent\Models\ModelWithFields;
use Amarenkov\MutableContent\ValueObjects\Weight;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class ModelWithFieldsTest extends FeatureTestCase
{
    public function test_fields_are_stored_in_jsonb_and_read_as_attributes(): void
    {
        $record = new Record();
        $record->code = 'REC-1';
        $record->quantity = 10;
        $record->save();

        $this->assertSame(['code' => 'REC-1', 'quantity' => 10], json_decode(DB::table('fixtures.records')->where('id', $record->id)->value('fields'), true));
        $this->assertSame('REC-1', DB::table('fixtures.records')->where('id', $record->id)->value('code'));

        $fresh = Record::find($record->id);

        $this->assertSame('REC-1', $fresh->code);
        $this->assertSame(10, $fresh->quantity);
        $this->assertNull($fresh->weight);
    }

    public function test_fill_routes_fields_to_jsonb(): void
    {
        $record = new Record();
        $record->fill(['code' => 'REC-2', 'quantity' => '5', 'is_active' => true]);

        $this->assertSame(5, $record->quantity);
        $this->assertTrue($record->is_active);
        $this->assertSame(['code' => 'REC-2', 'quantity' => 5, 'is_active' => true], $record->fields->getArrayCopy());
    }

    public function test_values_are_normalized_by_type(): void
    {
        $record = new Record();
        $record->weight = Weight::fromGrams(1500);
        $record->due_date = Carbon::parse('2026-10-08 15:30');
        $record->quantity = '7';

        $this->assertSame(1.5, $record->weight);
        $this->assertSame('2026-10-08', $record->due_date);
        $this->assertSame(7, $record->quantity);
        $this->assertSame(1.5, $record->getWeight('weight')->toKilograms());
        $this->assertSame('2026-10-08', $record->getDate('due_date')->format('Y-m-d'));

        $record->weight = '2';

        $this->assertSame(2.0, $record->weight);
    }

    public function test_null_removes_a_field(): void
    {
        $record = new Record();
        $record->fill(['code' => 'REC-3', 'quantity' => 1, 'weight' => 3]);
        $record->weight = null;

        $this->assertArrayNotHasKey('weight', $record->fields->getArrayCopy());

        $record->mergeWithFields(['quantity' => null]);

        $this->assertSame(['code' => 'REC-3'], $record->fields->getArrayCopy());
    }

    public function test_system_fields_are_hidden_from_array(): void
    {
        $record = new Record();
        $record->fill(['code' => 'REC-4', 'quantity' => 1, 'sync_state' => ['step' => 2]]);

        $this->assertSame(['step' => 2], $record->sync_state);
        $this->assertArrayNotHasKey('sync_state', $record->toArray()['fields']);
    }

    public function test_soft_deleted_duplicate_is_restored_instead_of_insert(): void
    {
        $owner = new Owner();
        $owner->fill(['code' => 'OWN-1', 'label' => 'Owner One']);
        $owner->save();
        $owner->delete();

        $again = new Owner();
        $again->fill(['code' => 'OWN-1', 'label' => 'Owner One Ltd']);
        $again->save();

        $this->assertSame($owner->id, $again->id);
        $this->assertNull($again->deleted_at);
        $this->assertSame('Owner One Ltd', Owner::find($owner->id)->label);
        $this->assertSame(1, Owner::withTrashed()->count());
    }

    public function test_child_class_must_redeclare_field_definitions(): void
    {
        $this->expectException(LogicException::class);

        ModelWithFields::getFieldDefinitions();
    }

    public function test_field_max_lengths_come_from_generated_columns(): void
    {
        $this->assertSame(['code' => 20], Owner::getFieldMaxLengths());
    }
}
