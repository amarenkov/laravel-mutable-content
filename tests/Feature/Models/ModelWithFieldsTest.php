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

    public function test_helpers_work_without_code_and_label_fields(): void
    {
        $record = new Record();
        $record->fill(['quantity' => 1]);

        $this->assertSame('', $record->label());
        $this->assertNull($record->label);
        $this->assertSame('', $record->code());
        $this->assertFalse($record->isSystem());
    }

    public function test_fields_path_attribute(): void
    {
        $record = new Record();
        $record->fill(['code' => 'REC-5', 'quantity' => 1, 'sync_state' => ['step' => 2, 'source' => ['name' => 'api']]]);

        $this->assertSame('REC-5', $record->getAttribute('fields->code'));
        $this->assertSame(2, $record->getAttribute('fields->sync_state->step'));
        $this->assertSame('api', $record->getAttribute('fields->sync_state->source->name'));
        $this->assertNull($record->getAttribute('fields->sync_state->missing'));
        $this->assertNull($record->getAttribute('fields->code->deeper'));
    }

    public function test_fields_column_cannot_be_written_directly(): void
    {
        $record = $this->recordWithUndeclaredField();

        $attempts = [
            'fill' => fn () => $record->fill(['fields' => ['quantity' => 2]]),
            'fill path' => fn () => $record->fill(['fields->quantity' => 2]),
            'update' => fn () => $record->update(['fields' => []]),
            'force fill' => fn () => $record->forceFill(['fields' => []]),
            'create' => fn () => Record::create(['fields' => ['code' => 'REC-X']]),
            'assign' => fn () => $record->fields = [],
            'assign path' => fn () => $record->{'fields->quantity'} = 2,
            'offset set' => fn () => $record->fields['quantity'] = 2,
            'offset unset' => function () use ($record) {
                unset($record->fields['legacy']);
            },
            'property set' => fn () => $record->fields->quantity = 2,
            'exchange' => fn () => $record->fields->exchangeArray([]),
        ];

        foreach ($attempts as $name => $attempt) {
            try {
                $attempt();

                $this->fail($name.' did not throw');
            } catch (LogicException) {
            }
        }

        $record->save();

        $this->assertSame(['code' => 'REC-6', 'legacy' => 'keep', 'quantity' => 1], $this->storedFields($record));
    }

    public function test_fields_cannot_be_changed_when_not_loaded(): void
    {
        $record = $this->recordWithUndeclaredField();

        $partial = Record::select('id')->find($record->id);

        foreach ([
            fn () => $partial->quantity = 5,
            fn () => $partial->fill(['quantity' => 5]),
            fn () => $partial->mergeWithFields(['quantity' => 5]),
            fn () => $partial->setField('quantity', 5),
        ] as $attempt) {
            try {
                $attempt();

                $this->fail('Change of not loaded fields did not throw');
            } catch (LogicException) {
            }
        }

        $this->assertSame(['code' => 'REC-6', 'legacy' => 'keep', 'quantity' => 1], $this->storedFields($record));
    }

    public function test_key_of_saved_object_cannot_be_filled(): void
    {
        $record = $this->recordWithUndeclaredField();

        $record->fill(['id' => (string)$record->id, 'quantity' => 2]);

        $this->expectException(LogicException::class);

        $record->fill(['id' => $record->id + 1]);
    }

    public function test_merge_replaces_whole_field_values(): void
    {
        $record = new Record();
        $record->fill(['code' => 'REC-7', 'quantity' => 1, 'sync_state' => ['errors' => ['a', 'b', 'c'], 'step' => 2]]);

        $record->fill(['sync_state' => ['errors' => ['x']]]);

        $this->assertSame(['errors' => ['x']], $record->sync_state);
    }

    public function test_undeclared_fields_survive_all_operations(): void
    {
        $record = $this->recordWithUndeclaredField();

        $operations = [
            'set field' => fn (Record $record) => $record->quantity = 2,
            'set null' => fn (Record $record) => $record->weight = null,
            'fill' => fn (Record $record) => $record->fill(['quantity' => 3, 'weight' => null]),
            'merge' => fn (Record $record) => $record->mergeWithFields(['quantity' => 4, 'is_active' => null]),
            'update' => fn (Record $record) => $record->update(['quantity' => 5]),
            'delete' => fn (Record $record) => $record->delete(),
            'restore' => fn (Record $record) => $record->restore(),
        ];

        foreach ($operations as $name => $operation) {
            $record = Record::withTrashed()->find($record->id);

            $operation($record);
            $record->save();

            $this->assertSame('keep', $this->storedFields($record)['legacy'] ?? null, $name);
        }
    }

    public function test_undeclared_fields_survive_restore_of_trashed_duplicate(): void
    {
        $owner = new Owner();
        $owner->fill(['code' => 'OWN-2', 'label' => 'Owner Two']);
        $owner->mergeWithFields(['legacy' => 'keep']);
        $owner->save();
        $owner->delete();

        $again = new Owner();
        $again->fill(['code' => 'OWN-2']);
        $again->save();

        $this->assertSame($owner->id, $again->id);
        $this->assertSame('keep', Owner::find($owner->id)->getField('legacy'));
    }

    public function test_same_values_keep_object_clean(): void
    {
        $record = Record::find($this->recordWithUndeclaredField()->id);

        $record->fill(['code' => 'REC-6', 'quantity' => 1]);
        $record->mergeWithFields(['legacy' => 'keep']);

        $this->assertFalse($record->isDirty());
    }

    private function recordWithUndeclaredField(): Record
    {
        $record = new Record();
        $record->fill(['code' => 'REC-6', 'quantity' => 1]);
        $record->mergeWithFields(['legacy' => 'keep']);
        $record->save();

        return $record;
    }

    private function storedFields(Record $record): array
    {
        return json_decode(DB::table('fixtures.records')->where('id', $record->id)->value('fields'), true);
    }
}
