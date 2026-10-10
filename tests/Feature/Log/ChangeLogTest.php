<?php

namespace Amarenkov\MutableContent\Tests\Feature\Log;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use LogicException;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

use Amarenkov\MutableContent\Database\Log\LogContext;
use Amarenkov\MutableContent\Domain\Log\Changes;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;
use Amarenkov\MutableContent\Macros\Database\Schema\Builder;
use Amarenkov\MutableContent\Helpers\LogHelper;
use Amarenkov\MutableContent\Models\Field\Field as FieldModel;
use Amarenkov\MutableContent\Models\Log\Entry;

use Amarenkov\MutableContent\Tests\FeatureTestCase;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Owner;
use Amarenkov\MutableContent\Tests\Fixtures\Models\Record;
use Amarenkov\MutableContent\Tests\Fixtures\Models\SecondaryRecord;

class ChangeLogTest extends FeatureTestCase
{
    protected function logRows(string $class, int $id): array
    {
        return DB::table($this->logsTableOf($class))->where('entity_id', $id)->orderBy('id')->get()->map(function ($row) {
            $data = json_decode((string)$row->data, true) ?? [];

            return [
                'action' => $row->action,
                'old' => json_decode((string)$row->fields_old, true),
                'new' => json_decode((string)$row->fields_new, true),
                'user_id' => $row->user_id === null ? null : (int)$row->user_id,
                'comment' => $data['comment'] ?? null,
                'extra' => array_diff_key($data, ['user_id' => 0, 'comment' => 0]) ?: null,
                'transaction_id' => $row->transaction_id,
            ];
        })->all();
    }

    protected function makeRecord(): Record
    {
        $record = new Record();
        $record->fill(['code' => 'REC-1', 'quantity' => 1]);
        $record->withLogContext('sync')->user(7);
        $record->save();

        return $record;
    }

    public function test_create_update_delete_and_restore_are_logged(): void
    {
        $record = $this->makeRecord();

        $record->quantity = 2;
        $record->withLogContext('manual')->user(8);
        $record->save();

        $record->save();

        $record->withLogContext('cleanup')->user(9);
        $record->delete();

        $record->restore();

        $rows = $this->logRows(Record::class, $record->id);

        $this->assertCount(4, $rows);

        $this->assertSame([LogHelper::ACTION_CREATED, null, ['code' => 'REC-1', 'quantity' => 1], 7, 'sync'], array_slice(array_values($rows[0]), 0, 5));
        $this->assertSame([LogHelper::ACTION_UPDATED, ['code' => 'REC-1', 'quantity' => 1], ['code' => 'REC-1', 'quantity' => 2], 8, 'manual'], array_slice(array_values($rows[1]), 0, 5));
        $this->assertSame([LogHelper::ACTION_DELETED, 9, 'cleanup'], [$rows[2]['action'], $rows[2]['user_id'], $rows[2]['comment']]);
        $this->assertSame([LogHelper::ACTION_RESTORED, null, null], [$rows[3]['action'], $rows[3]['user_id'], $rows[3]['comment']]);
    }

    public function test_entity_table_has_no_author_columns(): void
    {
        $this->assertFalse(Schema::hasColumn($this->tableOf(Record::class), 'updated_by_user_id'));
        $this->assertFalse(Schema::hasColumn($this->tableOf(Record::class), 'updated_with_comment'));
    }

    public function test_long_comment_and_meta_are_logged(): void
    {
        $comment = str_repeat('Long comment. ', 100);

        $record = new Record();
        $record->fill(['code' => 'REC-1', 'quantity' => 1]);
        $record->withLogContext($comment)->user(7)->data(['source' => 'import', 'batch' => ['id' => 3]])->save();

        $row = $this->logRows(Record::class, $record->id)[0];

        $this->assertSame($comment, $row['comment']);
        $this->assertEquals(['source' => 'import', 'batch' => ['id' => 3]], $row['extra']);
        $this->assertNotNull($row['transaction_id']);
    }

    public function test_update_with_deletion_is_logged_as_deletion_with_changes(): void
    {
        $record = $this->makeRecord();

        $record->quantity = 5;
        $record->deleted_at = now();
        $record->save();

        $entry = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->orderBy('id')->first();

        $this->assertSame(LogHelper::ACTION_DELETED, $entry->action());
        $this->assertSame(['Quantity: 1 → 5'], $entry->describeChanges());
    }

    public function test_raw_sql_gets_the_context_set_with_sql(): void
    {
        $record = $this->makeRecord();

        if (DatabaseHelper::isMariaDb()) {
            DB::statement('SET @mutable_content_log_data = \'{"user_id": 5, "comment": "raw"}\'');
        } else {
            DB::select('select set_config(\'mutable_content.data\', \'{"user_id": 5, "comment": "raw"}\', true)');
        }

        DB::table($this->tableOf(Record::class))->where('id', $record->id)->update(['fields' => $this->fieldsWithKey('external', 'sync')]);

        if (DatabaseHelper::isMariaDb()) {
            DB::statement('SET @mutable_content_log_data = NULL');
        }

        $row = $this->logRows(Record::class, $record->id)[1];

        $this->assertSame([LogHelper::ACTION_UPDATED, 5, 'raw'], [$row['action'], $row['user_id'], $row['comment']]);
    }

    public function test_raw_sql_gets_the_context_of_run(): void
    {
        $record = $this->makeRecord();

        app(LogContext::class)->run(function () use ($record) {
            DB::table($this->tableOf(Record::class))->where('id', $record->id)->update(['fields' => $this->fieldsWithKey('external', 'sync')]);
        }, ['user_id' => 5, 'comment' => 'raw', 'source' => 'script']);

        DB::table($this->tableOf(Record::class))->where('id', $record->id)->update(['fields' => $this->fieldsWithKey('external', 'again')]);

        $rows = $this->logRows(Record::class, $record->id);

        $this->assertSame([5, 'raw', ['source' => 'script']], [$rows[1]['user_id'], $rows[1]['comment'], $rows[1]['extra']]);
        $this->assertSame([null, null, null], [$rows[2]['user_id'], $rows[2]['comment'], $rows[2]['extra']]);
    }

    public function test_nested_context_overrides_and_restores_the_outer_one(): void
    {
        $record = $this->makeRecord();

        app(LogContext::class)->run(function () use ($record) {
            $record->quantity = 2;
            $record->withLogContext('inner')->user(8)->data(['step' => 'save'])->save();

            $record->quantity = 3;
            $record->save();
        }, ['user_id' => 7, 'comment' => 'outer', 'source' => 'job']);

        $rows = $this->logRows(Record::class, $record->id);

        $this->assertEquals([8, 'inner', ['source' => 'job', 'step' => 'save']], [$rows[1]['user_id'], $rows[1]['comment'], $rows[1]['extra']]);
        $this->assertSame([7, 'outer', ['source' => 'job']], [$rows[2]['user_id'], $rows[2]['comment'], $rows[2]['extra']]);
    }

    public function test_ambient_context_is_used_by_saves_without_author(): void
    {
        app(LogContext::class)->set(['user_id' => 11, 'comment' => 'request', 'source' => 'api']);

        $record = new Record();
        $record->fill(['code' => 'REC-1', 'quantity' => 1]);
        $record->save();

        $record->quantity = 2;
        $record->withLogContext()->user(12)->save();

        app(LogContext::class)->clear();

        $record->quantity = 3;
        $record->save();

        $rows = $this->logRows(Record::class, $record->id);

        $this->assertSame([11, 'request', ['source' => 'api']], [$rows[0]['user_id'], $rows[0]['comment'], $rows[0]['extra']]);
        $this->assertSame([12, 'request', ['source' => 'api']], [$rows[1]['user_id'], $rows[1]['comment'], $rows[1]['extra']]);
        $this->assertSame([null, null, null], [$rows[2]['user_id'], $rows[2]['comment'], $rows[2]['extra']]);
    }

    public function test_user_id_is_null_when_not_a_whole_number(): void
    {
        $record = $this->makeRecord();

        app(LogContext::class)->run(function () use ($record) {
            $record->quantity = 2;
            $record->save();
        }, ['user_id' => 'admin']);

        $row = $this->logRows(Record::class, $record->id)[1];

        $this->assertSame([null, ['user_id' => 'admin']], [$row['user_id'], json_decode((string)DB::table($this->logsTableOf(Record::class))->where('entity_id', $record->id)->orderByDesc('id')->value('data'), true)]);
    }

    public function test_failed_write_does_not_leak_the_context(): void
    {
        $record = $this->makeRecord();

        try {
            app(LogContext::class)->run(function () {
                throw new LogicException('failed');
            }, ['user_id' => 5, 'comment' => 'failed']);
        } catch (LogicException) {
        }

        DB::table($this->tableOf(Record::class))->where('id', $record->id)->update(['fields' => $this->fieldsWithKey('external', 'sync')]);

        $rows = $this->logRows(Record::class, $record->id);

        $this->assertSame([null, null], [$rows[1]['user_id'], $rows[1]['comment']]);
    }

    public function test_context_of_writes_outside_transactions(): void
    {
        DB::rollBack();

        $record = null;

        try {
            $record = new Record();
            $record->fill(['code' => 'REC-OUT', 'quantity' => 1]);
            $record->withLogContext('first')->user(1)->save();

            $record->quantity = 2;
            $record->withLogContext('second')->user(2)->save();

            DB::table($this->tableOf(Record::class))->where('id', $record->id)->update(['fields' => $this->fieldsWithKey('external', 'sync')]);

            $rows = $this->logRows(Record::class, $record->id);
        } finally {
            if ($record?->id) {
                DB::table($this->tableOf(Record::class))->where('id', $record->id)->delete();
                DB::table($this->logsTableOf(Record::class))->where('entity_id', $record->id)->delete();
            }

            DB::beginTransaction();
        }

        $this->assertSame([[1, 'first'], [2, 'second'], [null, null]], array_map(fn ($row) => [$row['user_id'], $row['comment']], $rows));

        $this->assertNotNull($rows[0]['transaction_id']);
        $this->assertNotEquals($rows[0]['transaction_id'], $rows[1]['transaction_id']);

        if (DatabaseHelper::isMariaDb()) {
            $this->assertNull($rows[2]['transaction_id']);
        } else {
            $this->assertNotEquals($rows[1]['transaction_id'], $rows[2]['transaction_id']);
        }
    }

    public function test_context_inside_an_application_transaction_does_not_leak(): void
    {
        DB::rollBack();

        $record = null;

        try {
            DB::transaction(function () use (&$record) {
                $record = new Record();
                $record->fill(['code' => 'REC-APP', 'quantity' => 1]);
                $record->withLogContext('inside')->user(1)->save();
            });

            DB::table($this->tableOf(Record::class))->where('id', $record->id)->update(['fields' => $this->fieldsWithKey('external', 'sync')]);

            $rows = $this->logRows(Record::class, $record->id);
        } finally {
            if ($record?->id) {
                DB::table($this->tableOf(Record::class))->where('id', $record->id)->delete();
                DB::table($this->logsTableOf(Record::class))->where('entity_id', $record->id)->delete();
            }

            DB::beginTransaction();
        }

        $this->assertSame([[1, 'inside'], [null, null]], array_map(fn ($row) => [$row['user_id'], $row['comment']], $rows));
        $this->assertNotNull($rows[0]['transaction_id']);
        $this->assertNotEquals($rows[0]['transaction_id'], $rows[1]['transaction_id']);
    }

    public function test_entries_of_one_transaction_share_the_transaction_id(): void
    {
        $record = $this->makeRecord();

        $record->quantity = 2;
        $record->withLogContext('manual')->user(8)->save();

        $record->logEvent('Checked')->write();

        $ids = array_unique(array_column($this->logRows(Record::class, $record->id), 'transaction_id'));

        $this->assertCount(1, $ids);
        $this->assertNotNull($ids[0]);
    }

    public function test_actions_and_descriptions(): void
    {
        $record = $this->makeRecord();

        $record->quantity = 3;
        $record->is_active = true;
        $record->save();

        $record->logEvent('Sync skipped for :code', ['code' => 'REC-1'])->comment('external system is down')->user(4)->data(['source' => 'sync'])->write();

        $record->delete();

        $entries = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->get();

        $this->assertEqualsCanonicalizing(
            [LogHelper::ACTION_CREATED, LogHelper::ACTION_UPDATED, LogHelper::ACTION_EVENT, LogHelper::ACTION_DELETED],
            $entries->map(fn (Entry $entry) => $entry->action())->all()
        );

        $updated = $entries->first(fn (Entry $entry) => $entry->action() === LogHelper::ACTION_UPDATED);
        $event = $entries->first(fn (Entry $entry) => $entry->action() === LogHelper::ACTION_EVENT);

        $this->assertSame(['Quantity: 1 → 3', 'Active: yes'], $updated->describe());
        $this->assertSame(['Sync skipped for REC-1'], $event->describe());
        $this->assertSame(['external system is down', 4], [$event->comment(), (int)$event->user_id]);
        $this->assertEquals(['user_id' => 4, 'comment' => 'external system is down', 'source' => 'sync', 'event' => 'Sync skipped for :code', 'code' => 'REC-1'], $event->data);
        $this->assertSame(Record::class, $updated->object_class);
    }

    public function test_event_text_is_translated(): void
    {
        app('translator')->addLines(['log.sync_skipped' => 'Синхронизация пропущена: :code'], 'ru');
        app()->setLocale('ru');

        $record = $this->makeRecord();
        $record->logEvent('log.sync_skipped', ['code' => 'REC-1'])->write();

        $event = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->get()
            ->first(fn (Entry $entry) => $entry->action() === LogHelper::ACTION_EVENT);

        $this->assertSame(['Синхронизация пропущена: REC-1'], $event->describe());
    }

    public function test_compress_keeps_the_changes(): void
    {
        $record = $this->makeRecord();

        $record->quantity = 2;
        $record->save();

        $record->logEvent('Checked')->write();

        $record->delete();

        $before = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->orderBy('id')->get()
            ->map(fn (Entry $entry) => [$entry->action(), $entry->describe()])->all();

        $this->assertSame(4, LogHelper::compress(Record::class));
        $this->assertSame(0, LogHelper::compress(Record::class));

        $entries = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->orderBy('id')->get();

        $this->assertSame($before, $entries->map(fn (Entry $entry) => [$entry->action(), $entry->describe()])->all());

        $deleted = $entries->first(fn (Entry $entry) => $entry->action() === LogHelper::ACTION_DELETED);

        $this->assertSame([null, null, []], [$deleted->fields_old, $deleted->fields_new, $deleted->fields_changed]);
    }

    public function test_compress_of_creation_deletion_and_cleared_fields(): void
    {
        $record = $this->makeRecord();

        $record->withLogContext('cleanup')->user(9);
        $record->delete();

        DB::table($this->tableOf(Record::class))->where('id', $record->id)->update(['fields' => '{}']);

        $logsTable = $this->logsTableOf(Record::class);

        foreach ([[null, '{"code": "REC-2"}'], ['[]', '{"code": "REC-2"}'], ['{"code": "REC-2"}', null], ['{"code": "REC-2"}', '{}']] as [$old, $new]) {
            DB::table($logsTable)->insert(['entity_id' => $record->id, 'action' => LogHelper::ACTION_UPDATED, 'fields_old' => $old, 'fields_new' => $new]);
        }

        $entries = fn () => Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->orderByDesc('id')->get();

        $before = $entries()->map(fn (Entry $entry) => [$entry->action(), $entry->changes()->toArray(), $entry->describe()])->all();

        LogHelper::compress(Record::class);

        $after = $entries();

        $this->assertSame($before, $after->map(fn (Entry $entry) => [$entry->action(), $entry->changes()->toArray(), $entry->describe()])->all());

        $this->assertSame(
            [
                [LogHelper::ACTION_CREATED, ['code' => [null, 'REC-1'], 'quantity' => [null, 1]], ['Code: REC-1', 'Quantity: 1']],
                [LogHelper::ACTION_DELETED, [], []],
                [LogHelper::ACTION_UPDATED, ['code' => ['REC-1', null], 'quantity' => [1, null]], ['Code: REC-1 → empty', 'Quantity: 1 → empty']],
                [LogHelper::ACTION_UPDATED, ['code' => [null, 'REC-2']], ['Code: REC-2']],
                [LogHelper::ACTION_UPDATED, ['code' => [null, 'REC-2']], ['Code: REC-2']],
                [LogHelper::ACTION_UPDATED, ['code' => ['REC-2', null]], ['Code: REC-2 → empty']],
                [LogHelper::ACTION_UPDATED, ['code' => ['REC-2', null]], ['Code: REC-2 → empty']],
            ],
            $before
        );

        foreach ($after as $entry) {
            $this->assertSame([LogHelper::COMPRESSION_COMPRESSED, null, null], [$entry->compression_status, $entry->fields_old, $entry->fields_new]);
            $this->assertIsArray($entry->fields_changed);
        }
    }

    public function test_compress_of_entries_without_changes(): void
    {
        $record = $this->makeRecord();

        $updatedAt = DB::table($this->tableOf(Record::class))->where('id', $record->id)->value('updated_at');

        $record->logEvent('Checked by :by', ['by' => 'robot'])->comment('nightly check')->user(3)->write();

        $this->assertSame($updatedAt, DB::table($this->tableOf(Record::class))->where('id', $record->id)->value('updated_at'));

        $record->delete();
        $record->restore();

        $logsTable = $this->logsTableOf(Record::class);
        $fields = '{"code": "REC-1", "quantity": 1}';

        foreach ([
            [LogHelper::ACTION_EVENT, $fields, $fields, '{"event": "Synced"}'],
            [LogHelper::ACTION_UPDATED, $fields, $fields, null],
            [LogHelper::ACTION_UPDATED, null, null, null],
            [LogHelper::ACTION_UPDATED, '{}', '[]', null],
        ] as [$action, $old, $new, $data]) {
            DB::table($logsTable)->insert(['entity_id' => $record->id, 'action' => $action, 'fields_old' => $old, 'fields_new' => $new, 'data' => $data]);
        }

        Log::spy();

        $this->assertSame(8, LogHelper::compress(Record::class));
        $this->assertSame(0, LogHelper::compress(Record::class));

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => str_contains($message, Record::class) && count($context['ids']) === 3);

        $entries = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->orderByDesc('id')->get();

        $this->assertSame(
            [
                [LogHelper::ACTION_CREATED, LogHelper::COMPRESSION_COMPRESSED, false, []],
                [LogHelper::ACTION_EVENT, LogHelper::COMPRESSION_COMPRESSED, true, ['Checked by robot']],
                [LogHelper::ACTION_DELETED, LogHelper::COMPRESSION_COMPRESSED, true, []],
                [LogHelper::ACTION_RESTORED, LogHelper::COMPRESSION_COMPRESSED, true, []],
                [LogHelper::ACTION_EVENT, LogHelper::COMPRESSION_COMPRESSED, true, ['Synced']],
                [LogHelper::ACTION_UPDATED, LogHelper::COMPRESSION_ERROR, true, []],
                [LogHelper::ACTION_UPDATED, LogHelper::COMPRESSION_ERROR, true, []],
                [LogHelper::ACTION_UPDATED, LogHelper::COMPRESSION_ERROR, true, []],
            ],
            $entries->map(fn (Entry $entry) => [$entry->action(), $entry->compression_status, $entry->changes()->isEmpty(), $entry->action() === LogHelper::ACTION_CREATED ? [] : $entry->describe()])->all()
        );

        $event = $entries[1];

        $this->assertSame([[], 'nightly check', 3], [$event->fields_changed, $event->comment(), (int)$event->user_id]);
        $this->assertEquals(['user_id' => 3, 'comment' => 'nightly check', 'event' => 'Checked by :by', 'by' => 'robot'], $event->data);

        $this->assertSame([[], []], [$entries[2]->fields_changed, $entries[3]->fields_changed]);

        $error = $entries[5];

        $this->assertSame([null, ['code' => 'REC-1', 'quantity' => 1], ['code' => 'REC-1', 'quantity' => 1]], [$error->fields_changed, $error->fields_old, $error->fields_new]);
    }

    public function test_compress_stops_at_the_limit(): void
    {
        $record = $this->makeRecord();

        foreach ([2, 3] as $quantity) {
            $record->quantity = $quantity;
            $record->save();
        }

        $compressed = fn () => DB::table($this->logsTableOf(Record::class))->where('entity_id', $record->id)->where('compression_status', LogHelper::COMPRESSION_COMPRESSED)->count();

        $this->assertSame(2, LogHelper::compress(Record::class, limit: 2, chunkSize: 1));
        $this->assertSame(2, $compressed());

        $this->assertSame(1, LogHelper::compress(Record::class, limit: 2, chunkSize: 1));
        $this->assertSame(3, $compressed());
    }

    public function test_prune_deletes_old_entries(): void
    {
        $record = $this->makeRecord();

        foreach ([2, 3, 4, 5] as $quantity) {
            $record->quantity = $quantity;
            $record->save();
        }

        $this->assertSame(0, LogHelper::prune(Record::class, now()->subDay()));
        $this->assertSame(5, LogHelper::prune(Record::class, now()->addDay(), chunkSize: 2));
        $this->assertSame([], $this->logRows(Record::class, $record->id));
    }

    public function test_log_commands(): void
    {
        $record = $this->makeRecord();

        $this->artisan('mutable-content:compress-log', ['--limit' => 0])->assertFailed();

        $this->artisan('mutable-content:compress-log', ['--class' => [Record::class]])
            ->expectsOutput(Record::class.': 1 entries processed')
            ->assertSuccessful();

        Carbon::setTestNow(now()->addDays(2));

        $this->artisan('mutable-content:prune-log')->assertFailed();

        $this->artisan('mutable-content:prune-log', ['--days' => 1])
            ->expectsOutput(Record::class.': 1 entries deleted')
            ->assertSuccessful();

        Carbon::setTestNow();

        $this->assertSame([], $this->logRows(Record::class, $record->id));
    }

    public function test_describe_changes(): void
    {
        $this->assertSame(
            ['Quantity: 1 → 2', 'Weight: 1.5 kg → empty', 'unknown_field: a → b', 'Status: Draft'],
            LogHelper::describeChanges(Record::class, Changes::between(['quantity' => 1, 'weight' => 1.5, 'unknown_field' => 'a'], ['quantity' => 2, 'status' => 'draft', 'unknown_field' => 'b']))
        );
    }

    public function test_event_of_unsaved_object_is_not_written(): void
    {
        $this->expectException(LogicException::class);

        new Record()->logEvent('Checked')->write();
    }

    public function test_log_data_is_kept_until_a_successful_save(): void
    {
        $record = $this->makeRecord();

        $record->withLogContext('prepared')->user(5)->data(['source' => 'form', 'step' => 1]);
        $record->withLogContext()->data(['step' => null]);

        $record->quantity = 2;
        $record->save();

        $record->quantity = 3;
        $record->save();

        $rows = $this->logRows(Record::class, $record->id);

        $this->assertSame([5, 'prepared', ['source' => 'form']], [$rows[1]['user_id'], $rows[1]['comment'], $rows[1]['extra']]);
        $this->assertSame([null, null, null], [$rows[2]['user_id'], $rows[2]['comment'], $rows[2]['extra']]);
    }

    public function test_clone_does_not_share_log_data(): void
    {
        $record = new Record();
        $record->fill(['code' => 'REC-1', 'quantity' => 1]);
        $record->withLogContext('original');

        $copy = clone $record;
        $copy->withLogContext('copy');

        $record->save();

        $this->assertSame('original', $this->logRows(Record::class, $record->id)[0]['comment']);
    }

    public function test_non_numeric_user_id_is_kept_in_data(): void
    {
        $record = new Record();
        $record->fill(['code' => 'REC-1', 'quantity' => 1]);
        $record->withLogContext()->user('9b1deb4d-3b7d')->save();

        $row = DB::table($this->logsTableOf(Record::class))->where('entity_id', $record->id)->first();

        $this->assertNull($row->user_id);
        $this->assertSame('9b1deb4d-3b7d', json_decode($row->data, true)['user_id']);
    }

    public function test_entry_date_is_in_the_application_timezone(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        $record = $this->makeRecord();

        $entry = Entry::fromQuery(LogHelper::query(Record::class)->where('entity_id', $record->id))->first();

        $this->assertSame('Asia/Tokyo', $entry->date->getTimezone()->getName());
        $this->assertEqualsWithDelta(now()->getTimestamp(), $entry->date->getTimestamp(), 60);
    }

    public function test_seeders_write_in_one_context(): void
    {
        $data = DB::table($this->logsTableOf(FieldModel::class))->pluck('data')
            ->map(fn ($value) => json_decode((string)$value, true))->unique()->values()->all();

        $this->assertSame([['comment' => 'FieldsSeeder']], $data);
    }

    protected function scheduledCommands(): array
    {
        $this->app->forgetInstance(Schedule::class);

        return collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string)$event->command, 'mutable-content:'))
            ->map(fn ($event) => [trim(substr($event->command, strpos($event->command, 'mutable-content:'))), $event->expression, $event->withoutOverlapping ? $event->expiresAt : null])
            ->values()->all();
    }

    public function test_only_compression_is_scheduled(): void
    {
        $this->assertSame([['mutable-content:compress-log', '* * * * *', 60]], $this->scheduledCommands());

        config(['mutable-content.log.compress.schedule' => '*/5 * * * *']);

        $this->assertSame([['mutable-content:compress-log', '*/5 * * * *', 60]], $this->scheduledCommands());

        config(['mutable-content.log.compress.schedule' => null]);

        $this->assertSame([], $this->scheduledCommands());
    }

    public function test_compress_limit_from_config(): void
    {
        $record = $this->makeRecord();

        $record->quantity = 2;
        $record->save();

        config(['mutable-content.log.compress.limit' => 1]);

        $this->artisan('mutable-content:compress-log', ['--class' => [Record::class]])
            ->expectsOutput(Record::class.': 1 entries processed')
            ->assertSuccessful();
    }

    public function test_event_takes_the_pending_log_context(): void
    {
        $record = $this->makeRecord();

        $record->withLogContext('import')->user(5)->data(['source' => 'csv']);
        $record->logEvent('Checked', ['source' => 'event'])->write();

        $record->quantity = 2;
        $record->save();

        $rows = $this->logRows(Record::class, $record->id);

        $this->assertSame([LogHelper::ACTION_EVENT, 5, 'import'], [$rows[1]['action'], $rows[1]['user_id'], $rows[1]['comment']]);
        $this->assertEquals(['source' => 'event', 'event' => 'Checked'], $rows[1]['extra']);
        $this->assertSame([LogHelper::ACTION_UPDATED, 5, 'import', ['source' => 'csv']], [$rows[2]['action'], $rows[2]['user_id'], $rows[2]['comment'], $rows[2]['extra']]);
    }

    public function test_log_triggers_can_be_recreated(): void
    {
        $record = $this->makeRecord();

        $this->rebuildDatabaseAfterTest();

        $connection = DB::connection();
        $table = $this->tableOf(Record::class);

        Builder::createLogTriggers($connection, 'fixtures.records');
        Builder::createLogTriggers($connection, 'fixtures.records');

        DB::table($table)->where('id', $record->id)->update(['fields' => $this->fieldsWithKey('external', 'sync')]);

        $this->assertCount(2, $this->logRows(Record::class, $record->id));

        if (DatabaseHelper::isMariaDb()) {
            $triggers = collect(DB::select('select trigger_name from information_schema.triggers where event_object_schema = database() and event_object_table = ?', [$table]))
                ->map(fn ($row) => array_change_key_case((array)$row)['trigger_name'])->sort()->values()->all();

            $this->assertSame([$table.'_log_after_insert', $table.'_log_after_update'], $triggers);
        }
    }

    public function test_log_table_of_a_model_on_another_connection(): void
    {
        config(['database.connections.secondary' => config('database.connections.'.config('database.default'))]);

        $this->assertSame('secondary', LogHelper::query(SecondaryRecord::class)->getConnection()->getName());
        $this->assertSame(LogHelper::getLogsTable(Record::class), LogHelper::getLogsTable(SecondaryRecord::class));
    }

    public function test_prune_log_of_some_classes(): void
    {
        $record = $this->makeRecord();

        $owner = new Owner();
        $owner->fill(['code' => 'OWN-1', 'label' => 'Owner One']);
        $owner->save();

        Carbon::setTestNow(now()->addDays(2));

        $this->artisan('mutable-content:prune-log', ['--days' => 1, '--class' => [Owner::class, 'stdClass']])
            ->expectsOutput('stdClass is not a mutable class, skipped.')
            ->expectsOutput(Owner::class.': 1 entries deleted')
            ->doesntExpectOutputToContain(Record::class)
            ->assertSuccessful();

        Carbon::setTestNow();

        $this->assertCount(1, $this->logRows(Record::class, $record->id));
        $this->assertSame([], $this->logRows(Owner::class, $owner->id));
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

        $this->rebuildDatabaseAfterTest();

        Schema::renameWithLog('fixtures.records', 'fixtures.archived_records');

        $table = DatabaseHelper::tableName('fixtures.archived_records');
        $logsTable = DatabaseHelper::logsTableName('fixtures.archived_records');

        $this->assertTrue(Schema::hasTable($table));
        $this->assertTrue(Schema::hasTable($logsTable));

        DB::table($table)->update(['fields' => json_encode(['code' => 'REC-1', 'quantity' => 5])]);

        $this->assertSame(2, DB::table($logsTable)->count());

        if (DatabaseHelper::isMariaDb()) {
            return;
        }

        $relations = DB::table('pg_class')->join('pg_namespace', 'pg_namespace.oid', '=', 'pg_class.relnamespace')
            ->where('pg_namespace.nspname', 'logs')->where('pg_class.relname', 'like', 'fixtures_%records%')->pluck('relname')->all();

        $this->assertContains('fixtures_archived_records_id_seq', $relations);
        $this->assertContains('fixtures_archived_records_pkey', $relations);
        $this->assertNotContains('fixtures_records_id_seq', $relations);
    }

    public function test_create_with_log_requires_service_columns(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Table broken must contain deleted_at column');

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
}
