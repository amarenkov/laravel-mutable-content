<?php

namespace Amarenkov\MutableContent\Tests\Feature\Database;

use InvalidArgumentException;
use LogicException;
use stdClass;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Domain\Log\Changes;
use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;
use Amarenkov\MutableContent\Helpers\FieldCodeHelper;
use Amarenkov\MutableContent\Helpers\LogHelper;

use Amarenkov\MutableContent\Macros\Database\Schema\Builder;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Asset;
use Amarenkov\MutableContent\Tests\Fixtures\Models\CollidingMember;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Member;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class AnyTableTest extends FeatureTestCase
{
    // protected
    protected function rows(string $class, mixed $id): array
    {
        return LogHelper::query($class)->where(LogHelper::COLUMN_ENTITY_ID, $id)->orderBy(LogHelper::COLUMN_LOG_ID)->get()->all();
    }

    protected function decoded(mixed $value): ?array
    {
        return LogHelper::decode($value);
    }

    protected function makeRecord(): Record
    {
        $record = new Record();
        $record->code = 'force-'.uniqid();
        $record->quantity = 1;
        $record->save();

        return $record;
    }

    // public
    public function test_fields_are_added_to_a_table_with_rows(): void
    {
        $member = Member::query()->where('name', 'Existing')->firstOrFail();

        $this->assertSame([], $member->fields->getArrayCopy());
        $this->assertNull($member->nickname);
    }

    public function test_model_on_a_plain_table_has_fields_and_columns(): void
    {
        $member = new Member(['name' => 'Ann', 'status' => 'active', 'nickname' => 'annie', 'rating' => '5']);
        $member->save();

        $member = Member::query()->findOrFail($member->id);

        $this->assertSame('Ann', $member->name);
        $this->assertSame('active', $member->status);
        $this->assertSame('annie', $member->nickname);
        $this->assertSame(5, $member->rating);

        $this->assertSame('annie', DB::table('members')->where('id', $member->id)->value('nickname'));
        $this->assertSame($member->id, Member::query()->whereField('rating', '>', 4)->value('id'));

        $this->assertEquals(['rating' => 5, 'nickname' => 'annie'], array_intersect_key($member->toArray()['fields'], ['rating' => 1, 'nickname' => 1]));
    }

    public function test_log_has_the_fields_and_the_listed_columns(): void
    {
        $member = new Member(['name' => 'Ann', 'rating' => 1]);
        $member->save();

        $member->status = 'active';
        $member->save();

        $member->rating = 2;
        $member->withLogContext('promoted')->save();

        $member->touch();

        $rows = $this->rows(Member::class, $member->id);

        $this->assertSame([LogHelper::ACTION_CREATED, LogHelper::ACTION_UPDATED, LogHelper::ACTION_UPDATED], array_column($rows, 'action'));

        $this->assertSame('Ann', $this->decoded($rows[0]->fields_new)['name']);
        $this->assertSame(1, $this->decoded($rows[0]->fields_new)['rating']);

        $this->assertNull($this->decoded($rows[1]->fields_old)['status'] ?? null);
        $this->assertSame('active', $this->decoded($rows[1]->fields_new)['status']);

        $this->assertSame(2, $this->decoded($rows[2]->fields_new)['rating']);
        $this->assertSame('promoted', $this->decoded($rows[2]->data)['comment']);
    }

    public function test_deleting_a_row_without_soft_deletes_is_logged(): void
    {
        $member = new Member(['name' => 'Ann', 'rating' => 1]);
        $member->save();

        $id = $member->id;

        $member->withLogContext('mistake')->delete();

        $rows = $this->rows(Member::class, $id);
        $last = end($rows);

        $this->assertSame(LogHelper::ACTION_FORCE_DELETED, $last->action);
        $this->assertSame('Ann', $this->decoded($last->fields_old)['name']);
        $this->assertNull($last->fields_new);
        $this->assertSame('mistake', $this->decoded($last->data)['comment']);
    }

    public function test_force_delete_and_a_delete_query_are_logged(): void
    {
        $record = $this->makeRecord();
        $id = $record->id;

        $record->delete();
        $record->forceDelete();

        $this->assertSame(
            [LogHelper::ACTION_CREATED, LogHelper::ACTION_DELETED, LogHelper::ACTION_FORCE_DELETED],
            array_column($this->rows(Record::class, $id), 'action')
        );

        $other = $this->makeRecord();

        Record::query()->whereKey($other->id)->forceDelete();

        $rows = $this->rows(Record::class, $other->id);

        $this->assertSame(LogHelper::ACTION_FORCE_DELETED, end($rows)->action);
    }

    public function test_forced_deletion_keeps_the_values_after_compression(): void
    {
        $record = $this->makeRecord();
        $id = $record->id;

        $record->forceDelete();

        LogHelper::compress(Record::class);

        $rows = $this->rows(Record::class, $id);
        $last = end($rows);

        $this->assertSame(LogHelper::COMPRESSION_COMPRESSED, $last->compression_status);
        $this->assertSame([1, null], $this->decoded($last->fields_changed)['quantity']);
    }

    public function test_log_of_a_table_with_a_uuid_key_and_without_fields(): void
    {
        $asset = Asset::query()->create(['title' => 'Laptop']);

        $asset->update(['title' => 'Old laptop']);
        $asset->logEvent('Checked')->write();
        $asset->delete();
        $asset->restore();
        $asset->forceDelete();

        $rows = $this->rows(Asset::class, $asset->id);

        $this->assertSame([
            LogHelper::ACTION_CREATED,
            LogHelper::ACTION_UPDATED,
            LogHelper::ACTION_EVENT,
            LogHelper::ACTION_DELETED,
            LogHelper::ACTION_RESTORED,
            LogHelper::ACTION_FORCE_DELETED,
        ], array_column($rows, 'action'));

        $this->assertSame('Laptop', $this->decoded($rows[1]->fields_old)['title']);
        $this->assertSame('Old laptop', $this->decoded($rows[1]->fields_new)['title']);
    }

    public function test_changes_of_a_model_without_fields_are_described_by_column_names(): void
    {
        $this->assertSame(
            ['title: a → b'],
            LogHelper::describeChanges(Asset::class, new Changes(['title' => ['a', 'b']]))
        );
    }

    public function test_flush_resets_the_definitions_of_models_using_the_trait(): void
    {
        $definitions = Member::getFieldDefinitions();

        $this->assertArrayHasKey('rating', $definitions);
        $this->assertSame($definitions, Member::getFieldDefinitions());

        $definitions['rating']->label = 'Changed';

        ModelWithFields::flushFieldDefinitions();

        $this->assertSame('Rating', Member::getFieldDefinitions()['rating']->label);
    }

    public function test_field_code_of_a_plain_column_is_rejected(): void
    {
        $this->assertContains('name', FieldCodeHelper::getReservedForClass(Member::class));
        $this->assertNotContains('nickname', FieldCodeHelper::getReservedForClass(Member::class));

        $this->expectException(LogicException::class);

        CollidingMember::getSystemFields();
    }

    public function test_registry_accepts_models_with_fields_only(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MutableClassRegistry()->add(stdClass::class);
    }

    public function test_table_without_a_single_column_key_cannot_be_logged(): void
    {
        $this->expectExceptionMessage('several columns');

        Schema::addLog('composite_keys');
    }

    public function test_unknown_column_cannot_be_logged(): void
    {
        $this->expectExceptionMessage('no column');

        Schema::refreshLog('members', columns: ['missing']);
    }

    public function test_logged_columns_can_be_changed_and_are_kept_on_refresh(): void
    {
        $this->rebuildDatabaseAfterTest();

        Schema::refreshLog('members', columns: ['status']);

        $connection = DB::connection();

        $this->assertSame(['status'], Builder::loggedColumns($connection, 'members'));

        Schema::refreshLog('members');

        $this->assertSame(['status'], Builder::loggedColumns($connection, 'members'));

        $member = new Member(['name' => 'Ann', 'status' => 'active']);
        $member->save();

        $new = $this->decoded($this->rows(Member::class, $member->id)[0]->fields_new);

        $this->assertSame('active', $new['status']);
        $this->assertArrayNotHasKey('name', $new);
    }

    public function test_log_can_be_dropped_and_added_again(): void
    {
        $this->rebuildDatabaseAfterTest();

        $logsTable = LogHelper::getLogsTable(Asset::class);

        Schema::dropLog('assets');

        $this->assertFalse(Schema::hasTable($logsTable));
        $this->assertTrue(Schema::hasTable('assets'));

        $asset = Asset::query()->create(['title' => 'No log']);
        $asset->update(['title' => 'Still no log']);
        $asset->forceDelete();

        if (!DatabaseHelper::isMariaDb()) {
            $this->assertSame(0, DB::scalar("select count(*) from pg_proc where proname like 'assets_log_after_%'"));
        }

        Schema::addLog('assets', columns: ['title']);

        $asset = Asset::query()->create(['title' => 'Logged']);

        $this->assertCount(1, $this->rows(Asset::class, $asset->id));
    }
}
