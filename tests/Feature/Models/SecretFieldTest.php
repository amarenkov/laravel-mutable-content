<?php

namespace Amarenkov\MutableContent\Tests\Feature\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use Amarenkov\MutableContent\Domain\Field\Lov\Type;
use Amarenkov\MutableContent\Domain\Field\TypeSettings;
use Amarenkov\MutableContent\Helpers\LogHelper;
use Amarenkov\MutableContent\Helpers\RuleHelper;
use Amarenkov\MutableContent\Helpers\SecretHelper;
use Amarenkov\MutableContent\Models\Log\Entry;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class SecretFieldTest extends FeatureTestCase
{
    protected function makeRecord(array $fields = []): Record
    {
        $record = new Record();
        $record->fill(['code' => 'a', Record::FIELD_QUANTITY => 1] + $fields);
        $record->save();

        return $record;
    }

    public function test_value_is_stored_encrypted_and_read_decrypted(): void
    {
        $record = $this->makeRecord([Record::FIELD_TOKEN => 'sk-123']);

        $stored = DB::table($record->getTable())->where('id', $record->id)->value('fields');

        $this->assertStringNotContainsString('sk-123', $stored);
        $this->assertNotSame('sk-123', $record->getField(Record::FIELD_TOKEN));
        $this->assertSame('sk-123', $record->fresh()->getSecret(Record::FIELD_TOKEN));
    }

    public function test_the_same_text_keeps_the_ciphertext_and_an_empty_one_removes_the_value(): void
    {
        $record = $this->makeRecord([Record::FIELD_TOKEN => 'sk-123']);
        $ciphertext = $record->getField(Record::FIELD_TOKEN);

        $record->fill([Record::FIELD_TOKEN => 'sk-123']);
        $this->assertFalse($record->isDirty());

        $record->setField(Record::FIELD_TOKEN, $ciphertext);
        $this->assertSame('sk-123', $record->getSecret(Record::FIELD_TOKEN));

        $record->fill([Record::FIELD_TOKEN => '']);
        $this->assertNull($record->getField(Record::FIELD_TOKEN));
        $this->assertNull($record->getSecret(Record::FIELD_TOKEN));
    }

    public function test_value_not_encrypted_with_the_application_key_reads_as_null(): void
    {
        $record = $this->makeRecord();
        $record->mergeWithFields([Record::FIELD_TOKEN => 'plain']);

        $this->assertNull($record->getSecret(Record::FIELD_TOKEN));
    }

    public function test_mask_keeps_a_few_characters_at_the_ends(): void
    {
        $this->assertSame('sk****89', SecretHelper::mask('sk-123456789'));
        $this->assertSame('sk-1****', SecretHelper::mask('sk-123456789', 4, 0));
        $this->assertSame(SecretHelper::MASK, SecretHelper::mask('sk-123456789', 0, 0));
        $this->assertSame(SecretHelper::MASK, SecretHelper::mask('sk-1234'));
        $this->assertSame('sk****45', SecretHelper::mask('sk-12345'));
        $this->assertNull(SecretHelper::mask(''));
    }

    public function test_masked_secret_follows_the_field_settings(): void
    {
        $record = $this->makeRecord([Record::FIELD_TOKEN => 'sk-123456789']);

        $this->assertSame('sk****89', $record->maskedSecret(Record::FIELD_TOKEN));
        $this->assertSame(
            [TypeSettings::VISIBLE_START => 4, TypeSettings::VISIBLE_END => 0],
            TypeSettings::normalize(Type::TYPE_SECRET, [TypeSettings::VISIBLE_START => 4, TypeSettings::VISIBLE_END => 0]),
        );
        $this->assertSame(range(0, TypeSettings::MAX_VISIBLE), TypeSettings::getAllowedValues(Type::TYPE_SECRET)[TypeSettings::VISIBLE_START]);

        $record->mergeWithFields([Record::FIELD_TOKEN => 'not encrypted']);

        $this->assertSame(SecretHelper::MASK, $record->maskedSecret(Record::FIELD_TOKEN));
    }

    public function test_change_log_shows_a_mask(): void
    {
        $record = $this->makeRecord([Record::FIELD_TOKEN => 'sk-123456789']);

        $record->fill([Record::FIELD_TOKEN => 'sk-987654321']);
        $record->save();

        $entries = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->orderBy('id')->get();
        $logged = json_encode(DB::table($this->logsTableOf(Record::class))->where('entity_id', $record->id)->get());

        $this->assertContains('Token: sk****89', $entries->flatMap(fn (Entry $entry) => $entry->describeChanges())->all());
        $this->assertStringNotContainsString('sk-123', $logged);
        $this->assertStringNotContainsString('sk-987', $logged);
    }

    public function test_validation_takes_a_string(): void
    {
        $rules = RuleHelper::getValidationRules(Record::class);

        $this->assertTrue(Validator::make(['code' => 'a', 'quantity' => 1, 'token' => 'sk-1'], $rules)->passes());
        $this->assertFalse(Validator::make(['code' => 'a', 'quantity' => 1, 'token' => ['sk-1']], $rules)->passes());
    }
}
