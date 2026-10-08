<?php

namespace Amarenkov\MutableContent\Tests\Feature\Log;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use LogicException;

use Amarenkov\MutableContent\Helpers\LogHelper;
use Amarenkov\MutableContent\Models\Log\Entry;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;

class ChangeLogTest extends FeatureTestCase
{
    protected function logRows(string $table, int $id): array
    {
        return DB::table($table)->where('entity_id', $id)->orderBy('id')->get()->map(fn ($row) => [
            'old' => json_decode((string)$row->fields_old, true),
            'new' => json_decode((string)$row->fields_new, true),
            'is_deleted' => $row->is_deleted,
            'user_id' => $row->user_id,
            'comment' => $row->comment,
        ])->all();
    }

    protected function makeRecord(): Record
    {
        $record = new Record();
        $record->fill(['code' => 'REC-1', 'quantity' => 1]);
        $record->setUpdatedByIfDirty('sync', 7);
        $record->save();

        return $record;
    }

    public function test_create_update_delete_and_restore_are_logged(): void
    {
        $record = $this->makeRecord();

        $record->quantity = 2;
        $record->setUpdatedByIfDirty('manual', 8);
        $record->save();

        $record->save();

        $record->setUpdatedBy('cleanup', 9);
        $record->delete();

        $record->restore();

        $rows = $this->logRows('logs.fixtures_records', $record->id);

        $this->assertCount(4, $rows);

        $this->assertSame([null, ['code' => 'REC-1', 'quantity' => 1], false, 7, 'sync'], array_values($rows[0]));
        $this->assertSame([['code' => 'REC-1', 'quantity' => 1], ['code' => 'REC-1', 'quantity' => 2], false, 8, 'manual'], array_values($rows[1]));
        $this->assertSame([true, 9, 'cleanup'], [$rows[2]['is_deleted'], $rows[2]['user_id'], $rows[2]['comment']]);
        $this->assertSame([false, null, null], [$rows[3]['is_deleted'], $rows[3]['user_id'], $rows[3]['comment']]);
    }

    public function test_author_columns_are_cleared_after_save(): void
    {
        $record = $this->makeRecord();

        $this->assertNull($record->updated_by_user_id);
        $this->assertSame([null, null], array_values((array)DB::table('fixtures.records')->where('id', $record->id)->first(['updated_by_user_id', 'updated_with_comment'])));
    }

    public function test_actions_and_descriptions(): void
    {
        $record = $this->makeRecord();

        $record->quantity = 3;
        $record->is_active = true;
        $record->save();

        $record->writeLog('Sync skipped');

        $record->delete();

        $entries = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->get();

        $this->assertEqualsCanonicalizing(
            [LogHelper::ACTION_CREATED, LogHelper::ACTION_UPDATED, LogHelper::ACTION_EVENT, LogHelper::ACTION_DELETED],
            $entries->map(fn (Entry $entry) => $entry->action())->all()
        );

        $updated = $entries->first(fn (Entry $entry) => $entry->action() === LogHelper::ACTION_UPDATED);
        $event = $entries->first(fn (Entry $entry) => $entry->action() === LogHelper::ACTION_EVENT);

        $this->assertSame(['Quantity: 1 → 3', 'Active: yes'], $updated->describe());
        $this->assertSame(['Sync skipped'], $event->describe());
        $this->assertSame(Record::class, $updated->object_class);
    }

    public function test_describe_changes(): void
    {
        $this->assertSame(
            ['Quantity: 1 → 2', 'Weight: 1,5 kg → empty', 'unknown_field: a → b', 'Status: Draft'],
            LogHelper::describeChanges(Record::class, ['quantity' => 1, 'weight' => 1.5, 'unknown_field' => 'a'], ['quantity' => 2, 'status' => 'draft', 'unknown_field' => 'b'])
        );
    }

    public function test_union_of_logs_of_several_classes(): void
    {
        $record = $this->makeRecord();

        $owner = new Owner();
        $owner->fill(['code' => 'OWN-1', 'label' => 'Owner One']);
        $owner->save();

        $classes = Entry::fromQuery(
            LogHelper::query(Record::class)->where('entity_id', $record->id)
                ->union(LogHelper::query(Owner::class)->where('entity_id', $owner->id))
        )->pluck('object_class')->sort()->values()->all();

        $this->assertSame([Owner::class, Record::class], $classes);
    }

    public function test_rename_with_log_keeps_logging(): void
    {
        $this->makeRecord();

        Schema::renameWithLog('fixtures.records', 'fixtures.archived_records');

        $this->assertTrue(Schema::hasTable('fixtures.archived_records'));
        $this->assertTrue(Schema::hasTable('logs.fixtures_archived_records'));

        DB::table('fixtures.archived_records')->update(['fields' => json_encode(['code' => 'REC-1', 'quantity' => 5])]);

        $this->assertSame(2, DB::table('logs.fixtures_archived_records')->count());

        $relations = DB::table('pg_class')->join('pg_namespace', 'pg_namespace.oid', '=', 'pg_class.relnamespace')
            ->where('pg_namespace.nspname', 'logs')->where('pg_class.relname', 'like', 'fixtures_%records%')->pluck('relname')->all();

        $this->assertContains('fixtures_archived_records_id_seq', $relations);
        $this->assertContains('fixtures_archived_records_pkey', $relations);
        $this->assertNotContains('fixtures_records_id_seq', $relations);
    }

    public function test_create_with_log_requires_service_columns(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Table broken must contain deleted_at, updated_by_user_id and updated_with_comment columns');

        Schema::createWithLog('broken', function (Blueprint $table) {
            $table->fieldsBase();
            $table->fieldsUpdatedAt();
        });
    }

    public function test_entries_of_one_transaction_keep_their_order(): void
    {
        $record = $this->makeRecord();

        foreach ([2, 3, 4] as $quantity) {
            $record->quantity = $quantity;
            $record->save();
        }

        $descriptions = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))
            ->orderBy('id')
            ->get()
            ->map(fn (Entry $entry) => $entry->describe())
            ->all();

        $this->assertSame([['Quantity: 3 → 4'], ['Quantity: 2 → 3'], ['Quantity: 1 → 2'], ['Code: REC-1', 'Quantity: 1']], $descriptions);
    }

    public function test_upgrade_migration_adds_id_to_old_log_tables(): void
    {
        DB::statement('CREATE TABLE logs.legacy (entity_id integer, comment varchar(255))');
        DB::table('logs.legacy')->insert([['entity_id' => 1, 'comment' => 'first'], ['entity_id' => 1, 'comment' => 'second']]);

        $migration = require __DIR__.'/../../../database/migrations/2026_10_09_000000_add_id_to_log_tables.php';
        $migration->up();
        $migration->up();

        $this->assertSame(['first', 'second'], DB::table('logs.legacy')->orderBy('id')->pluck('comment')->all());
        $this->assertSame(3, DB::table('logs.legacy')->insertGetId(['entity_id' => 1, 'comment' => 'third']));
    }
}
