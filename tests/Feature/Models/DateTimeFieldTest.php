<?php

namespace Amarenkov\MutableContent\Tests\Feature\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

use Amarenkov\MutableContent\Helpers\RuleHelper;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class DateTimeFieldTest extends FeatureTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.timezone', 'Europe/Moscow');
    }

    protected function makeRecord(string $code, mixed $startedAt): Record
    {
        $record = new Record();
        $record->fill(['code' => $code, Record::FIELD_QUANTITY => 1, Record::FIELD_STARTED_AT => $startedAt]);
        $record->save();

        return $record;
    }

    public function test_date_object_is_stored_in_utc(): void
    {
        $record = $this->makeRecord('a', Carbon::parse('2026-10-10 12:30:15', 'Asia/Novosibirsk'));

        $this->assertSame('2026-10-10T05:30:15Z', $record->fresh()->{Record::FIELD_STARTED_AT});
    }

    public function test_string_without_timezone_is_in_the_application_timezone(): void
    {
        $record = $this->makeRecord('a', '2026-10-10 12:00');

        $this->assertSame('2026-10-10T09:00:00Z', $record->fresh()->{Record::FIELD_STARTED_AT});

        $withOffset = $this->makeRecord('b', '2026-10-10T12:00:00+01:00');

        $this->assertSame('2026-10-10T11:00:00Z', $withOffset->fresh()->{Record::FIELD_STARTED_AT});
    }

    public function test_value_is_read_in_the_application_timezone(): void
    {
        $startedAt = $this->makeRecord('a', '2026-10-10T09:00:00Z')->fresh()->getDateTime(Record::FIELD_STARTED_AT);

        $this->assertSame('2026-10-10 12:00:00', $startedAt->format('Y-m-d H:i:s'));
        $this->assertSame('Europe/Moscow', $startedAt->getTimezone()->getName());
        $this->assertNull($this->makeRecord('b', null)->fresh()->getDateTime(Record::FIELD_STARTED_AT));
    }

    public function test_where_field_compares_moments(): void
    {
        $this->makeRecord('early', '2026-10-10 08:00');
        $this->makeRecord('late', '2026-10-10 20:00');

        $codes = Record::query()->whereField(Record::FIELD_STARTED_AT, '>', Carbon::parse('2026-10-10 12:00', 'Europe/Moscow'))->get()->map->code()->all();

        $this->assertSame(['late'], $codes);
        $this->assertSame(['early'], Record::query()->whereField(Record::FIELD_STARTED_AT, '<', '2026-10-10 12:00')->get()->map->code()->all());
    }

    public function test_validation_accepts_dates_only(): void
    {
        $rules = RuleHelper::getValidationRules(Record::class);

        $this->assertTrue(Validator::make([Record::FIELD_STARTED_AT => '2026-10-10 12:00'], [Record::FIELD_STARTED_AT => $rules[Record::FIELD_STARTED_AT]])->passes());
        $this->assertFalse(Validator::make([Record::FIELD_STARTED_AT => 'soon'], [Record::FIELD_STARTED_AT => $rules[Record::FIELD_STARTED_AT]])->passes());
    }
}
