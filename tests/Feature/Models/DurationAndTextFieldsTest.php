<?php

namespace Amarenkov\MutableContent\Tests\Feature\Models;

use Illuminate\Support\Facades\Validator;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Helpers\LogHelper;
use Amarenkov\MutableContent\Helpers\RuleHelper;
use Amarenkov\MutableContent\ValueObjects\Duration;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class DurationAndTextFieldsTest extends FeatureTestCase
{
    protected function makeRecord(string $code, array $fields = []): Record
    {
        $record = new Record();
        $record->fill(['code' => $code, Record::FIELD_QUANTITY => 1] + $fields);
        $record->save();

        return $record;
    }

    protected function errors(array $data): array
    {
        return Validator::make(array_replace(['code' => 'a', 'quantity' => 1], $data), RuleHelper::getValidationRules(Record::class), [], RuleHelper::getValidationAttributes(Record::class))
            ->errors()
            ->toArray();
    }

    public function test_duration_is_stored_in_whole_seconds(): void
    {
        $record = $this->makeRecord('a', [Record::FIELD_ESTIMATE => '5400.4']);

        $this->assertSame(5400, $record->fresh()->getField(Record::FIELD_ESTIMATE));
        $this->assertSame(1.5, $record->fresh()->getDuration(Record::FIELD_ESTIMATE)->toHours());

        $record->setField(Record::FIELD_ESTIMATE, Duration::fromMinutes(30));
        $record->save();

        $this->assertSame(1800, $record->fresh()->getField(Record::FIELD_ESTIMATE));
    }

    public function test_duration_has_units_and_is_positive_by_default(): void
    {
        $field = Record::getFieldDefinitions()[Record::FIELD_ESTIMATE];

        $this->assertSame(Duration::class, TypeSettings::getUnitClass(Type::TYPE_DURATION));
        $this->assertSame(Duration::UNIT_HOUR, TypeSettings::getValue($field, TypeSettings::DISPLAY_UNIT));
        $this->assertFalse(TypeSettings::allowsZero($field));
        $this->assertFalse(TypeSettings::allowsNegative($field));
        $this->assertSame([], $this->errors([Record::FIELD_ESTIMATE => 5400]));
        $this->assertArrayHasKey(Record::FIELD_ESTIMATE, $this->errors([Record::FIELD_ESTIMATE => 0]));
        $this->assertArrayHasKey(Record::FIELD_ESTIMATE, $this->errors([Record::FIELD_ESTIMATE => 'soon']));
    }

    public function test_duration_is_compared_as_a_number_and_logged_in_the_display_unit(): void
    {
        $this->makeRecord('a', [Record::FIELD_ESTIMATE => 900]);
        $this->makeRecord('b', [Record::FIELD_ESTIMATE => 7200]);

        $this->assertSame(['b'], Record::whereField(Record::FIELD_ESTIMATE, '>', 3600)->pluck('code')->all());
        $this->assertSame('2 h', LogHelper::formatValue(Record::getFieldDefinitions()[Record::FIELD_ESTIMATE], 7200));
    }

    public function test_text_format_setting(): void
    {
        $definitions = Record::getFieldDefinitions();

        $this->assertSame(TypeSettings::FORMAT_MARKDOWN, TypeSettings::format($definitions[Record::FIELD_NOTES]));
        $this->assertTrue(TypeSettings::isMarkdown($definitions[Record::FIELD_NOTES]));
        $this->assertSame(TypeSettings::FORMATS, TypeSettings::getAllowedValues(Type::TYPE_TEXT)[TypeSettings::FORMAT]);
        $this->assertArrayNotHasKey(TypeSettings::FORMAT, TypeSettings::getAllowedValues(Type::TYPE_STRING));
        $this->assertSame([TypeSettings::FORMAT => TypeSettings::FORMAT_MARKDOWN], TypeSettings::normalize(Type::TYPE_TEXT, [TypeSettings::FORMAT => TypeSettings::FORMAT_MARKDOWN, TypeSettings::DISPLAY_UNIT => 'h']));
        $this->assertNull(TypeSettings::normalize(Type::TYPE_STRING, [TypeSettings::FORMAT => TypeSettings::FORMAT_MARKDOWN]));
    }
}
