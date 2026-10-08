<?php

namespace Amarenkov\MutableContent\Tests\Feature\Validation;

use Illuminate\Support\Facades\Validator;

use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Helpers\RuleHelper;
use Amarenkov\MutableContent\Models\Field\Field;
use Amarenkov\MutableContent\Rules\InLov;
use Amarenkov\MutableContent\Rules\ObjectCodeExists;
use Amarenkov\MutableContent\Rules\ObjectExists;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class RuleHelperTest extends FeatureTestCase
{
    protected function validate(array $data): array
    {
        return Validator::make($data, RuleHelper::getValidationRules(Record::class), [], RuleHelper::getValidationAttributes(Record::class))
            ->errors()
            ->toArray();
    }

    protected function makeOwner(string $code): Owner
    {
        $owner = new Owner();
        $owner->fill(['code' => $code, 'label' => $code]);
        $owner->save();

        return $owner;
    }

    public function test_rules_by_field_type(): void
    {
        $rules = RuleHelper::getValidationRules(Record::class);

        $this->assertSame(['string', 'max:50', 'required'], $rules['code']);
        $this->assertSame(['integer', 'required'], $rules['quantity']);
        $this->assertSame(['nullable', 'numeric', 'gt:0'], $rules['weight']);
        $this->assertSame(['nullable', 'boolean'], $rules['is_active']);
        $this->assertSame(['nullable', 'date_format:Y-m-d'], $rules['due_date']);
        $this->assertInstanceOf(InLov::class, $rules['status'][1]);
        $this->assertSame('integer', $rules['owner_id'][1]);
        $this->assertInstanceOf(ObjectExists::class, $rules['owner_id'][2]);
        $this->assertInstanceOf(ObjectCodeExists::class, $rules['owner_code'][1]);
        $this->assertArrayNotHasKey('external_id', $rules);
        $this->assertArrayNotHasKey('sync_state', $rules);
    }

    public function test_prefix(): void
    {
        $this->assertArrayHasKey('records.*.quantity', RuleHelper::getValidationRules(Record::class, 'records.*'));
    }

    public function test_valid_data_passes(): void
    {
        $owner = $this->makeOwner('OWN-1');

        $this->assertSame([], $this->validate([
            'code' => 'REC-1',
            'quantity' => 1,
            'weight' => 2.5,
            'status' => 'draft',
            'owner_id' => $owner->id,
            'owner_code' => 'OWN-1',
            'due_date' => '2026-10-08',
        ]));
    }

    public function test_invalid_data_fails_with_field_labels(): void
    {
        $deleted = $this->makeOwner('OWN-X');
        $deleted->delete();

        $errors = $this->validate([
            'code' => str_repeat('x', 51),
            'weight' => 0,
            'status' => 'unknown',
            'owner_id' => $deleted->id,
            'owner_code' => 'OWN-X',
            'due_date' => '08.10.2026',
        ]);

        $this->assertSame(['code', 'quantity', 'weight', 'due_date', 'status', 'owner_id', 'owner_code'], array_keys($errors));
        $this->assertSame('The Quantity field is required.', $errors['quantity'][0]);
        $this->assertSame('The Status must be an item of the "Record status" LOV.', $errors['status'][0]);
        $this->assertSame('The Owner must be a "Owner" object.', $errors['owner_id'][0]);
        $this->assertSame('The Owner code must be a code of a "Owner" object.', $errors['owner_code'][0]);
    }

    public function test_allow_zero_setting(): void
    {
        $usage = Field::where('code', 'weight')->first()->usages()->first();
        $usage->field_type_settings = [TypeSettings::ALLOW_ZERO => true];
        $usage->save();

        $this->assertSame(['nullable', 'numeric', 'min:0'], RuleHelper::getValidationRules(Record::class)['weight']);
        $this->assertArrayNotHasKey('weight', $this->validate(['quantity' => 1, 'weight' => 0]));
    }

    public function test_validated_fields_are_filtered(): void
    {
        $this->assertSame(
            ['code' => 'REC-1', 'quantity' => 1],
            RuleHelper::getValidatedFields(Record::class, ['code' => 'REC-1', 'quantity' => 1, 'external_id' => 'X', 'sync_state' => [], 'junk' => 1])
        );
    }
}
