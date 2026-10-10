<?php

namespace Amarenkov\MutableContent\Macros\Database\Schema;

use Closure;

use LogicException;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as BaseBuilder;

use Amarenkov\MutableContent\Database\Log\LogContext;
use Amarenkov\MutableContent\Domain\Log\LogData;

use Amarenkov\MutableContent\Helpers\DatabaseHelper;
use Amarenkov\MutableContent\Helpers\LogHelper;

class Builder
{
    // protected
    public static function getLogsTableName($table, ?Connection $connection = null)
    {
        return DatabaseHelper::logsTableName($table, $connection);
    }

    /**
     * Create the log functions and triggers of the table. Recreate them after renaming the table.
     */
    public static function createLogTriggers(Connection $connection, string $table): void
    {
        DatabaseHelper::driver($connection);

        if (DatabaseHelper::isMariaDb($connection)) {
            static::createMariaDbLogTriggers($connection, $table);

            return;
        }

        $logsTable = static::getLogsTableName($table, $connection);

        $context = LogContext::sqlExpressions($connection);

        $connection->statement("
            CREATE OR REPLACE FUNCTION {$logsTable}_log_after_update()
            returns trigger AS $$
            BEGIN
                INSERT INTO {$logsTable} (
                    entity_id,
                    action,
                    fields_old,
                    fields_new,
                    data,
                    transaction_id
                )
                SELECT
                    nd.id,
                    CASE
                        WHEN od.id IS NULL THEN '".LogHelper::ACTION_CREATED."'
                        WHEN nd.deleted_at IS NOT NULL AND od.deleted_at IS NULL THEN '".LogHelper::ACTION_DELETED."'
                        WHEN nd.deleted_at IS NULL AND od.deleted_at IS NOT NULL THEN '".LogHelper::ACTION_RESTORED."'
                        ELSE '".LogHelper::ACTION_UPDATED."'
                    END,
                    od.fields,
                    nd.fields,
                    {$context[LogContext::DATA]},
                    {$context[LogContext::TRANSACTION_ID]}
                FROM new_data nd
                LEFT JOIN old_data od ON nd.id = od.id
                WHERE
                    od.id IS NULL OR
                    nd.fields IS DISTINCT FROM od.fields OR
                    (nd.deleted_at IS NULL) <> (od.deleted_at IS NULL);

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        ");

        $connection->statement("
            CREATE OR REPLACE FUNCTION {$logsTable}_log_after_insert()
            returns trigger AS $$
            BEGIN
                INSERT INTO {$logsTable} (
                    entity_id,
                    action,
                    fields_new,
                    data,
                    transaction_id
                )
                SELECT
                    nd.id,
                    '".LogHelper::ACTION_CREATED."',
                    nd.fields,
                    {$context[LogContext::DATA]},
                    {$context[LogContext::TRANSACTION_ID]}
                FROM new_data nd;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        ");

        $connection->statement("
            CREATE OR REPLACE TRIGGER log_after_insert
            AFTER INSERT ON {$table}
            REFERENCING NEW TABLE AS new_data
            FOR EACH STATEMENT
            EXECUTE FUNCTION {$logsTable}_log_after_insert()
        ");

        $connection->statement("
            CREATE OR REPLACE TRIGGER log_after_update
            AFTER UPDATE ON {$table}
            REFERENCING NEW TABLE AS new_data OLD TABLE AS old_data
            FOR EACH STATEMENT
            EXECUTE FUNCTION {$logsTable}_log_after_update()
        ");
    }

    /**
     * MariaDB has no statement triggers with transition tables: rows are logged one by one.
     * Existing log triggers of the table are replaced.
     */
    protected static function createMariaDbLogTriggers(Connection $connection, string $table): void
    {
        $table = DatabaseHelper::tableName($table, $connection);
        $logsTable = static::getLogsTableName($table, $connection);

        $grammar = $connection->getQueryGrammar();

        $wrappedTable = $grammar->wrapTable($table);
        $wrappedLogsTable = $grammar->wrapTable($logsTable);

        $context = implode(', ', LogContext::sqlExpressions($connection));

        static::dropMariaDbLogTriggers($connection, $table);

        [$afterInsert, $afterUpdate] = static::mariaDbLogTriggerNames($table);

        $connection->unprepared("
            CREATE TRIGGER {$grammar->wrap($afterInsert)} AFTER INSERT ON {$wrappedTable} FOR EACH ROW
            INSERT INTO {$wrappedLogsTable} (entity_id, action, fields_new, data, transaction_id)
            VALUES (NEW.id, '".LogHelper::ACTION_CREATED."', NEW.fields, {$context})
        ");

        $connection->unprepared("
            CREATE TRIGGER {$grammar->wrap($afterUpdate)} AFTER UPDATE ON {$wrappedTable} FOR EACH ROW
            BEGIN
                IF OLD.fields IS NULL OR NEW.fields IS NULL OR NOT JSON_EQUALS(OLD.fields, NEW.fields)
                    OR (NEW.deleted_at IS NULL) <> (OLD.deleted_at IS NULL) THEN
                    INSERT INTO {$wrappedLogsTable} (entity_id, action, fields_old, fields_new, data, transaction_id)
                    VALUES (
                        NEW.id,
                        CASE
                            WHEN NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN '".LogHelper::ACTION_DELETED."'
                            WHEN NEW.deleted_at IS NULL AND OLD.deleted_at IS NOT NULL THEN '".LogHelper::ACTION_RESTORED."'
                            ELSE '".LogHelper::ACTION_UPDATED."'
                        END,
                        OLD.fields,
                        NEW.fields,
                        {$context}
                    );
                END IF;
            END
        ");
    }

    /**
     * Author id of a log entry taken from its data, null unless it is a whole number.
     */
    public static function logUserIdExpression(Connection $connection): string
    {
        $pattern = "'^[0-9]{1,18}$'";

        if (DatabaseHelper::isMariaDb($connection)) {
            $value = "json_value(data, '$.".LogData::USER_ID."')";

            return "case when {$value} regexp {$pattern} then cast({$value} as unsigned) end";
        }

        $value = "(data ->> '".LogData::USER_ID."')";

        return "case when {$value} ~ {$pattern} then {$value}::bigint end";
    }

    public static function dropMariaDbLogTriggers(Connection $connection, string $table): void
    {
        $grammar = $connection->getQueryGrammar();

        $table = DatabaseHelper::tableName($table, $connection);

        foreach (static::mariaDbLogTriggerNames($table) as $trigger) {
            $connection->unprepared('DROP TRIGGER IF EXISTS '.$grammar->wrap($trigger));
        }
    }

    /**
     * @return array{string, string}
     */
    protected static function mariaDbLogTriggerNames(string $table): array
    {
        return [
            DatabaseHelper::identifier($table, 'log_after_insert'),
            DatabaseHelper::identifier($table, 'log_after_update'),
        ];
    }

    /**
     * Rename schema indexes and sequences by exact name or name prefix: old => new. The first match wins.
     *
     * @param array<string, string> $prefixes
     */
    public static function renameRelationsByPrefix(Connection $connection, string $schema, array $prefixes): void
    {
        $relations = $connection->select(
            "select c.relname, c.relkind from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = ? and c.relkind in ('i', 'S')",
            [$schema]
        );

        foreach ($relations as $relation) {
            foreach ($prefixes as $old => $new) {
                if ($relation->relname !== $old && !str_starts_with($relation->relname, $old.'_')) {
                    continue;
                }

                $kind = $relation->relkind === 'S' ? 'SEQUENCE' : 'INDEX';
                $newName = $new.substr($relation->relname, strlen($old));

                $connection->statement("ALTER {$kind} \"{$schema}\".\"{$relation->relname}\" RENAME TO \"{$newName}\"");

                break;
            }
        }
    }

    // public
    public static function add()
    {
        BaseBuilder::macro('createSchema', function ($schema) {
            if (DatabaseHelper::isMariaDb($this->getConnection())) {
                return;
            }

            $this->getConnection()->statement("CREATE SCHEMA IF NOT EXISTS {$schema};");
        });

        BaseBuilder::macro('createWithLog', function ($table, Closure $callback) {
            $table = DatabaseHelper::tableName($table, $this->getConnection());

            $blueprint = tap($this->createBlueprint($table), function ($blueprint) use ($callback) {
                $blueprint->create();

                $callback($blueprint);
            });

            $columns = array_map(function($item){
                return $item->name;
            }, $blueprint->getColumns());

            if (!in_array('fields', $columns)) {
                throw new LogicException("Table {$table} must contain field column (use fieldsBase function e.g.).");
            }

            if (!in_array('updated_at', $columns)) {
                throw new LogicException("Table {$table} must contain updated_at column (use fieldsUpdatedAt function e.g.).");
            }

            if (!in_array('deleted_at', $columns)) {
                throw new LogicException("Table {$table} must contain deleted_at column (use softDeletes function e.g.).");
            }

            $this->build($blueprint);

            // logs section
            $this->createSchema(DatabaseHelper::LOGS_SCHEMA);
            
            $logsTable = Builder::getLogsTableName($table, $this->getConnection());
            
            $blueprint = tap($this->createBlueprint($logsTable), function ($blueprint) {
                $blueprint->create();

                $blueprint->bigIncrements('id');

                $blueprint->unsignedBigInteger('entity_id');

                $blueprint->string('action', 16);

                $blueprint->jsonb('fields_old')->nullable();
                $blueprint->jsonb('fields_new')->nullable();
                $blueprint->jsonb('fields_changed')->nullable();
                $blueprint->string('compression_status', 16)->default(LogHelper::COMPRESSION_PENDING);

                $blueprint->timestampTz('date')->useCurrent();

                $blueprint->jsonb('data')->nullable();
                $blueprint->unsignedBigInteger('user_id')->nullable()->storedAs(Builder::logUserIdExpression($this->getConnection()));

                $blueprint->unsignedBigInteger('transaction_id')->nullable();

                $blueprint->index('entity_id');
                $blueprint->index('date');
                $blueprint->index('user_id');
                $blueprint->index('compression_status');
                $blueprint->index('transaction_id');
            });

            $this->build($blueprint);

            Builder::createLogTriggers($this->getConnection(), $table);
        });

        BaseBuilder::macro('dropIfExistsWithLog', function ($table) {
            $table = DatabaseHelper::tableName($table, $this->getConnection());

            $this->build(tap($this->createBlueprint($table), function ($blueprint) {
                $blueprint->dropIfExists();
            }));

            $logsTable = Builder::getLogsTableName($table, $this->getConnection());

            $this->build(tap($this->createBlueprint($logsTable), function ($blueprint) {
                $blueprint->dropIfExists();
            }));

            if (DatabaseHelper::isMariaDb($this->getConnection())) {
                return;
            }

            $this->getConnection()->statement("DROP FUNCTION {$logsTable}_log_after_insert()");
            $this->getConnection()->statement("DROP FUNCTION {$logsTable}_log_after_update()");
        });

        BaseBuilder::macro('renameWithLog', function ($from, $to) {
            [$fromSchema, $fromName] = str_contains($from, '.') ? explode('.', $from, 2) : [null, $from];
            [$toSchema, $toName] = str_contains($to, '.') ? explode('.', $to, 2) : [null, $to];

            if ($fromSchema !== $toSchema) {
                throw new LogicException("Table {$from} can be renamed only within its schema.");
            }

            $connection = $this->getConnection();

            if (DatabaseHelper::isMariaDb($connection)) {
                $grammar = $connection->getQueryGrammar();

                $from = DatabaseHelper::tableName($from, $connection);
                $to = DatabaseHelper::tableName($to, $connection);

                Builder::dropMariaDbLogTriggers($connection, $from);

                $connection->statement('RENAME TABLE '.$grammar->wrapTable($from).' TO '.$grammar->wrapTable($to)
                    .', '.$grammar->wrapTable(Builder::getLogsTableName($from, $connection)).' TO '.$grammar->wrapTable(Builder::getLogsTableName($to, $connection)));

                Builder::createLogTriggers($connection, $to);

                return;
            }

            $fromLogsTable = Builder::getLogsTableName($from, $connection);
            $toLogsTable = Builder::getLogsTableName($to, $connection);

            $connection->statement("DROP TRIGGER log_after_insert ON {$from}");
            $connection->statement("DROP TRIGGER log_after_update ON {$from}");
            $connection->statement("DROP FUNCTION {$fromLogsTable}_log_after_insert()");
            $connection->statement("DROP FUNCTION {$fromLogsTable}_log_after_update()");

            $connection->statement("ALTER TABLE {$from} RENAME TO {$toName}");
            $connection->statement("ALTER TABLE {$fromLogsTable} RENAME TO ".explode('.', $toLogsTable, 2)[1]);

            Builder::renameRelationsByPrefix($connection, $fromSchema ?? 'public', [
                str_replace('.', '_', $from) => str_replace('.', '_', $to),
                $fromName => $toName,
            ]);
            $fromLogsName = explode('.', $fromLogsTable, 2)[1];
            $toLogsName = explode('.', $toLogsTable, 2)[1];

            Builder::renameRelationsByPrefix($connection, 'logs', [
                str_replace('.', '_', $fromLogsTable) => str_replace('.', '_', $toLogsTable),
                $fromLogsName.'_id_seq' => $toLogsName.'_id_seq',
                $fromLogsName.'_pkey' => $toLogsName.'_pkey',
            ]);

            Builder::createLogTriggers($connection, $to);
        });

        BaseBuilder::macro('upParseTimestamptz', function ($table) {
            if (DatabaseHelper::isMariaDb($this->getConnection())) {
                throw new LogicException('parse_timestamptz() is available on PostgreSQL only');
            }

            $this->getConnection()->statement("
                CREATE OR REPLACE FUNCTION parse_timestamptz(text)
                RETURNS timestamptz
                IMMUTABLE
                STRICT
                LANGUAGE sql
                AS $$
                    SELECT $1::timestamptz;
                $$;
            ");
        });
    }
}