<?php

namespace Amarenkov\MutableContent\Tests\Feature\Fields;

use InvalidArgumentException;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Models\Field\Field;
use Amarenkov\MutableContent\Models\Field\Usage;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class FieldDefinitionsTest extends FeatureTestCase
{
    protected function makeField(string $code, string $type, string $label, array $extra = []): Field
    {
        $field = new Field();
        $field->fill(['code' => $code, 'field_type' => $type, 'label' => $label] + $extra);
        $field->save();

        return $field;
    }

    protected function bind(Field $field, string $class, array $extra = []): Usage
    {
        $usage = new Usage();
        $usage->fill([Usage::FIELD_FIELD_ID => $field->id, Usage::CODE_MUTABLE_CLASS => $class] + $extra);
        $usage->save();

        return $usage;
    }

    public function test_code_fields_are_seeded_as_system_fields(): void
    {
        $field = Field::where('code', 'quantity')->first();

        $this->assertNotNull($field);
        $this->assertTrue($field->isSystem());
        $this->assertSame(Type::TYPE_INT, $field->field_type);
        $this->assertSame('Quantity', $field->label);
        $this->assertSame([Usage::makeScope(Usage::CODE_MUTABLE_CLASS, Record::class)], $field->usages()->pluck('scope')->all());
        $this->assertTrue((bool)$field->usages()->first()->is_required);
    }

    public function test_field_added_in_database_extends_class_definition(): void
    {
        $this->assertArrayNotHasKey('comment', Record::getFieldDefinitions());

        $this->bind($this->makeField('comment', Type::TYPE_TEXT, 'Comment'), Record::class, ['is_required' => true]);

        $definitions = Record::getFieldDefinitions();

        $this->assertArrayHasKey('comment', $definitions);
        $this->assertSame('Comment', $definitions['comment']->label);
        $this->assertTrue($definitions['comment']->usage()->isRequired);
        $this->assertArrayNotHasKey('comment', Owner::getFieldDefinitions());

        $record = new Record();
        $record->comment = 'Some text';

        $this->assertSame('Some text', $record->fields['comment']);
    }

    public function test_database_label_overrides_code_label(): void
    {
        $field = Field::where('code', 'weight')->first();
        $field->label = 'Gross weight';
        $field->save();

        $this->assertSame('Gross weight', Record::getFieldDefinitions()['weight']->label);
    }

    public function test_usage_flags_from_database_can_only_be_turned_on(): void
    {
        $quantityUsage = Field::where('code', 'quantity')->first()->usages()->first();
        $quantityUsage->is_required = false;
        $quantityUsage->save();

        $weightUsage = Field::where('code', 'weight')->first()->usages()->first();
        $weightUsage->is_required = true;
        $weightUsage->save();

        $definitions = Record::getFieldDefinitions();

        $this->assertTrue($definitions['quantity']->usage()->isRequired);
        $this->assertTrue($definitions['weight']->usage()->isRequired);
    }

    public function test_definitions_cache_is_flushed_on_field_changes(): void
    {
        $field = $this->makeField('note', Type::TYPE_STRING, 'Note');
        $this->bind($field, Record::class);

        $this->assertSame('Note', Record::getFieldDefinitions()['note']->label);

        $field->label = 'Notes';
        $field->save();

        $this->assertSame('Notes', Record::getFieldDefinitions()['note']->label);

        $field->delete();

        $this->assertArrayNotHasKey('note', Record::getFieldDefinitions());
    }

    public function test_field_code_format_is_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeField('Bad Code', Type::TYPE_STRING, 'Bad');
    }

    public function test_service_codes_are_reserved(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeField('created_at', Type::TYPE_STRING, 'Created');
    }

    public function test_relation_names_are_reserved_in_class(): void
    {
        $field = $this->makeField('owner', Type::TYPE_STRING, 'Owner');

        $this->bind($field, Owner::class);

        $this->expectException(InvalidArgumentException::class);

        $this->bind($field, Record::class);
    }

    public function test_usage_needs_a_class(): void
    {
        $field = $this->makeField('extra', Type::TYPE_STRING, 'Extra');

        $usage = new Usage();
        $usage->fill([Usage::FIELD_FIELD_ID => $field->id]);

        $this->expectException(InvalidArgumentException::class);

        $usage->save();
    }

    public function test_class_without_types_rejects_a_type(): void
    {
        $field = $this->makeField('extra', Type::TYPE_STRING, 'Extra');

        $this->expectException(InvalidArgumentException::class);

        $this->bind($field, Record::class, [Usage::CODE_TYPE_CODE => 'record_status']);
    }

    public function test_system_type_is_only_for_code_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeField('state', Type::TYPE_SYSTEM, 'State');
    }

    public function test_type_settings_are_normalized_for_the_type(): void
    {
        $field = $this->makeField('volume_note', Type::TYPE_WEIGHT, 'Net weight', [
            'field_type_settings' => [TypeSettings::DISPLAY_UNIT => 'g', TypeSettings::LINK_BY_CODE => true],
        ]);

        $this->assertSame([TypeSettings::DISPLAY_UNIT => 'g'], $field->fresh()->field_type_settings);

        $field->field_type_settings = [TypeSettings::DISPLAY_UNIT => 'lb'];

        $this->expectException(InvalidArgumentException::class);

        $field->save();
    }

    public function test_usage_type_settings_override_field_settings(): void
    {
        $field = $this->makeField('net_weight', Type::TYPE_WEIGHT, 'Net weight', [
            'field_type_settings' => [TypeSettings::DISPLAY_UNIT => 'g', TypeSettings::ALLOW_ZERO => true],
        ]);

        $this->bind($field, Record::class, ['field_type_settings' => [TypeSettings::DISPLAY_UNIT => 't']]);

        $definition = Record::getFieldDefinitions()['net_weight'];

        $this->assertSame('t', TypeSettings::getValue($definition, TypeSettings::DISPLAY_UNIT));
        $this->assertTrue(TypeSettings::allowsZero($definition));
    }
}
