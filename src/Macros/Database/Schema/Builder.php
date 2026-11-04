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
    // const
    public const LOG_TRIGGERS = ['log_after_insert', 'log_after_update', 'log_after_delete'];

    protected const LOG_COMMENT_COLUMNS = 'columns';

    // protected
    public static function getLogsTableName($table, ?Connection $connection = null)
    {
        return DatabaseHelper::logsTableName($table, $connection);
    }

    /**
     * Create the log functions and triggers of the table by its current columns. Recreate them after renaming the table
     * or changing its key, fields, deleted_at or logged columns.
     */
    public static function createLogTriggers(Connection $connection, string $table): void
    {
        DatabaseHelper::driver($connection);

        $table = DatabaseHelper::tableName($table, $connection);

        $key = static::logKeyColumn($connection, $table);
        $columns = static::loggedColumns($connection, $table);

        $present = array_column($connection->getSchemaBuilder()->getColumns($table), 'name');

        $hasFields = in_array(DatabaseHelper::FIELDS_COLUMN, $present, true);
        $hasDeletedAt = in_array(DatabaseHelper::DELETED_AT_COLUMN, $present, true);

        if (DatabaseHelper::isMariaDb($connection)) {
            static::createMariaDbLogTriggers($connection, $table, $key, $columns, $hasFields, $hasDeletedAt);

            return;
        }

        $logsTable = static::getLogsTableName($table, $connection);

        $context = LogContext::sqlExpressions($connection);

        $grammar = $connection->getQueryGrammar();

        $wrappedKey = $grammar->wrap($key);

        $newSnapshot = static::pgsqlLogSnapshot($connection, 'nd', $columns, $hasFields);
        $oldSnapshot = static::pgsqlLogSnapshot($connection, 'od', $columns, $hasFields);

        $softDeleteCases = $hasDeletedAt ? "
                        WHEN nd.deleted_at IS NOT NULL AND od.deleted_at IS NULL THEN '".LogHelper::ACTION_DELETED."'
                        WHEN nd.deleted_at IS NULL AND od.deleted_at IS NOT NULL THEN '".LogHelper::ACTION_RESTORED."'" : '';

        $softDeleteCondition = $hasDeletedAt ? ' OR
                    (nd.deleted_at IS NULL) <> (od.deleted_at IS NULL)' : '';

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
                    nd.{$wrappedKey},
                    CASE
                        WHEN od.{$wrappedKey} IS NULL THEN '".LogHelper::ACTION_CREATED."'{$softDeleteCases}
                        ELSE '".LogHelper::ACTION_UPDATED."'
                    END,
                    {$oldSnapshot},
                    {$newSnapshot},
                    {$context[LogContext::DATA]},
                    {$context[LogContext::TRANSACTION_ID]}
                FROM new_data nd
                LEFT JOIN old_data od ON nd.{$wrappedKey} = od.{$wrappedKey}
                WHERE
                    od.{$wrappedKey} IS NULL OR
                    {$newSnapshot} IS DISTINCT FROM {$oldSnapshot}{$softDeleteCondition};

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
                    nd.{$wrappedKey},
                    '".LogHelper::ACTION_CREATED."',
                    {$newSnapshot},
                    {$context[LogContext::DATA]},
                    {$context[LogContext::TRANSACTION_ID]}
                FROM new_data nd;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        ");

        $connection->statement("
            CREATE OR REPLACE FUNCTION {$logsTable}_log_after_delete()
            returns trigger AS $$
            BEGIN
                INSERT INTO {$logsTable} (
                    entity_id,
                    action,
                    fields_old,
                    data,
                    transaction_id
                )
                SELECT
                    od.{$wrappedKey},
                    '".LogHelper::ACTION_FORCE_DELETED."',
                    {$oldSnapshot},
                    {$context[LogContext::DATA]},
                    {$context[LogContext::TRANSACTION_ID]}
                FROM old_data od;

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

        $connection->statement("
            CREATE OR REPLACE TRIGGER log_after_delete
            AFTER DELETE ON {$table}
            REFERENCING OLD TABLE AS old_data
            FOR EACH STATEMENT
            EXECUTE FUNCTION {$logsTable}_log_after_delete()
        ");
    }

    /**
     * Fields and logged columns of a row as a JSON expression of a PostgreSQL trigger.
     *
     * @param array<string> $columns
     */
    protected static function pgsqlLogSnapshot(Connection $connection, string $row, array $columns, bool $hasFields): string
    {
        $fields = $row.'.'.DatabaseHelper::FIELDS_COLUMN;

        if (!$columns) {
            return $hasFields ? $fields : "'{}'::jsonb";
        }

        $grammar = $connection->getQueryGrammar();

        $pairs = array_map(fn ($column) => $connection->escape($column).', '.$row.'.'.$grammar->wrap($column), $columns);

        $object = 'jsonb_build_object('.implode(', ', $pairs).')';

        return $hasFields ? "(coalesce({$fields}, '{}'::jsonb) || {$object})" : $object;
    }

    /**
     * Fields and logged columns of a row as a JSON expression of a MariaDB trigger.
     *
     * @param array<string> $columns
     */
    protected static function mariaDbLogSnapshot(Connection $connection, string $row, array $columns, bool $hasFields): string
    {
        $fields = $row.'.'.DatabaseHelper::FIELDS_COLUMN;

        if (!$columns) {
            return $hasFields ? $fields : "'{}'";
        }

        $grammar = $connection->getQueryGrammar();

        $pairs = array_map(fn ($column) => $connection->escape($column).', '.$row.'.'.$grammar->wrap($column), $columns);

        $object = 'JSON_OBJECT('.implode(', ', $pairs).')';

        return $hasFields ? "JSON_MERGE_PATCH(COALESCE({$fields}, '{}'), {$object})" : $object;
    }

    /**
     * MariaDB has no statement triggers with transition tables: rows are logged one by one.
     * Existing log triggers of the table are replaced.
     *
     * @param array<string> $columns
     */
    protected static function createMariaDbLogTriggers(Connection $connection, string $table, string $key, array $columns, bool $hasFields, bool $hasDeletedAt): void
    {
        $logsTable = static::getLogsTableName($table, $connection);

        $grammar = $connection->getQueryGrammar();

        $wrappedTable = $grammar->wrapTable($table);
        $wrappedLogsTable = $grammar->wrapTable($logsTable);
        $wrappedKey = $grammar->wrap($key);

        $context = implode(', ', LogContext::sqlExpressions($connection));

        $newSnapshot = static::mariaDbLogSnapshot($connection, 'NEW', $columns, $hasFields);
        $oldSnapshot = static::mariaDbLogSnapshot($connection, 'OLD', $columns, $hasFields);

        $changed = "NOT JSON_EQUALS({$oldSnapshot}, {$newSnapshot})";

        if ($hasFields && !$columns) {
            $changed = "OLD.fields IS NULL OR NEW.fields IS NULL OR {$changed}";
        }

        $updateAction = $hasDeletedAt ? "CASE
                            WHEN NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL THEN '".LogHelper::ACTION_DELETED."'
                            WHEN NEW.deleted_at IS NULL AND OLD.deleted_at IS NOT NULL THEN '".LogHelper::ACTION_RESTORED."'
                            ELSE '".LogHelper::ACTION_UPDATED."'
                        END" : "'".LogHelper::ACTION_UPDATED."'";

        $softDeleteCondition = $hasDeletedAt ? '
                    OR (NEW.deleted_at IS NULL) <> (OLD.deleted_at IS NULL)' : '';

        static::dropMariaDbLogTriggers($connection, $table);

        [$afterInsert, $afterUpdate, $afterDelete] = static::mariaDbLogTriggerNames($table);

        $connection->unprepared("
            CREATE TRIGGER {$grammar->wrap($afterInsert)} AFTER INSERT ON {$wrappedTable} FOR EACH ROW
            INSERT INTO {$wrappedLogsTable} (entity_id, action, fields_new, data, transaction_id)
            VALUES (NEW.{$wrappedKey}, '".LogHelper::ACTION_CREATED."', {$newSnapshot}, {$context})
        ");

        $connection->unprepared("
            CREATE TRIGGER {$grammar->wrap($afterUpdate)} AFTER UPDATE ON {$wrappedTable} FOR EACH ROW
            BEGIN
                IF {$changed}{$softDeleteCondition} THEN
                    INSERT INTO {$wrappedLogsTable} (entity_id, action, fields_old, fields_new, data, transaction_id)
                    VALUES (
                        NEW.{$wrappedKey},
                        {$updateAction},
                        {$oldSnapshot},
                        {$newSnapshot},
                        {$context}
                    );
                END IF;
            END
        ");

        $connection->unprepared("
            CREATE TRIGGER {$grammar->wrap($afterDelete)} AFTER DELETE ON {$wrappedTable} FOR EACH ROW
            INSERT INTO {$wrappedLogsTable} (entity_id, action, fields_old, data, transaction_id)
            VALUES (OLD.{$wrappedKey}, '".LogHelper::ACTION_FORCE_DELETED."', {$oldSnapshot}, {$context})
        ");
    }

    /**
     * Single-column primary key of the table, or its id column when it has no primary key.
     */
    protected static function logKeyColumn(Connection $connection, string $table): string
    {
        $schema = $connection->getSchemaBuilder();

        foreach ($schema->getIndexes($table) as $index) {
            if (!$index['primary']) {
                continue;
            }

            if (count($index['columns']) !== 1) {
                throw new LogicException("Table {$table} cannot be logged: its primary key has several columns.");
            }

            return $index['columns'][0];
        }

        if (!in_array('id', array_column($schema->getColumns($table), 'name'), true)) {
            throw new LogicException("Table {$table} cannot be logged: it has no primary key.");
        }

        return 'id';
    }

    /**
     * Columns logged besides the fields, kept in the comment of the log table.
     *
     * @return array<string>
     */
    public static function loggedColumns(Connection $connection, string $table): array
    {
        $logsTable = static::getLogsTableName($table, $connection);

        if (DatabaseHelper::isMariaDb($connection)) {
            $comment = $connection->scalar(
                'select table_comment from information_schema.tables where table_schema = database() and table_name = ?',
                [$logsTable]
            );
        } else {
            $comment = $connection->scalar("select obj_description(to_regclass(?), 'pg_class')", [$logsTable]);
        }

        return json_decode((string)$comment, true)[self::LOG_COMMENT_COLUMNS] ?? [];
    }

    /**
     * @param array<string> $columns
     */
    protected static function saveLoggedColumns(Connection $connection, string $table, array $columns): void
    {
        $logsTable = static::getLogsTableName($table, $connection);

        $comment = $connection->escape($columns ? json_encode([self::LOG_COMMENT_COLUMNS => $columns]) : '');

        if (DatabaseHelper::isMariaDb($connection)) {
            $connection->statement('ALTER TABLE '.$connection->getQueryGrammar()->wrapTable($logsTable).' COMMENT = '.$comment);
        } else {
            $connection->statement("COMMENT ON TABLE {$logsTable} IS {$comment}");
        }
    }

    /**
     * @param array<string> $columns
     */
    protected static function checkLoggedColumns(Connection $connection, string $table, array $columns): void
    {
        $present = array_column($connection->getSchemaBuilder()->getColumns($table), 'name');

        foreach ($columns as $column) {
            if (!in_array($column, $present, true)) {
                throw new LogicException("Table {$table} has no column {$column} to log.");
            }

            if ($column === DatabaseHelper::FIELDS_COLUMN) {
                throw new LogicException("Column {$column} of table {$table} is logged without being listed.");
            }
        }
    }

    /**
     * Create the log table and the triggers of an existing table; history starts now.
     *
     * @param array<string> $columns columns logged besides the fields
     */
    public static function addLog(BaseBuilder $schema, string $table, array $columns = []): void
    {
        $connection = $schema->getConnection();

        $table = DatabaseHelper::tableName($table, $connection);

        static::checkLoggedColumns($connection, $table, $columns);

        $key = static::logKeyColumn($connection, $table);

        $keyType = null;

        foreach ($schema->getColumns($table) as $column) {
            if ($column['name'] === $key) {
                $keyType = $column;
            }
        }

        $schema->createSchema(DatabaseHelper::LOGS_SCHEMA);

        $logsTable = static::getLogsTableName($table, $connection);

        $schema->create($logsTable, function ($blueprint) use ($connection, $keyType) {
            $blueprint->bigIncrements('id');

            if (preg_match('/^(tiny|small|medium|big)?int[248]?$|serial/i', $keyType['type_name'])) {
                $blueprint->unsignedBigInteger('entity_id');
            } else {
                $blueprint->rawColumn('entity_id', $keyType['type']);
            }

            $blueprint->string('action', 16);

            $blueprint->jsonb('fields_old')->nullable();
            $blueprint->jsonb('fields_new')->nullable();
            $blueprint->jsonb('fields_changed')->nullable();
            $blueprint->string('compression_status', 16)->default(LogHelper::COMPRESSION_PENDING);

            $blueprint->timestampTz('date')->useCurrent();

            $blueprint->jsonb('data')->nullable();
            $blueprint->unsignedBigInteger('user_id')->nullable()->storedAs(static::logUserIdExpression($connection));

            $blueprint->unsignedBigInteger('transaction_id')->nullable();

            $blueprint->index('entity_id');
            $blueprint->index('date');
            $blueprint->index('user_id');
            $blueprint->index('compression_status');
            $blueprint->index('transaction_id');
        });

        if ($columns) {
            static::saveLoggedColumns($connection, $table, $columns);
        }

        static::createLogTriggers($connection, $table);
    }

    /**
     * Rebuild the log triggers by the current columns of the table.
     *
     * @param ?array<string> $columns new list of logged columns; the current one is kept when null
     */
    public static function refreshLog(BaseBuilder $schema, string $table, ?array $columns = null): void
    {
        $connection = $schema->getConnection();

        $table = DatabaseHelper::tableName($table, $connection);

        if ($columns !== null) {
            static::checkLoggedColumns($connection, $table, $columns);
            static::saveLoggedColumns($connection, $table, $columns);
        }

        static::createLogTriggers($connection, $table);
    }

    /**
     * Drop the log triggers, functions and table; the table itself stays.
     */
    public static function dropLog(BaseBuilder $schema, string $table): void
    {
        $connection = $schema->getConnection();

        $table = DatabaseHelper::tableName($table, $connection);

        $logsTable = static::getLogsTableName($table, $connection);

        if (DatabaseHelper::isMariaDb($connection)) {
            static::dropMariaDbLogTriggers($connection, $table);
        } else {
            foreach (self::LOG_TRIGGERS as $trigger) {
                $connection->statement("DROP TRIGGER IF EXISTS {$trigger} ON {$table}");
            }
        }

        $schema->dropIfExists($logsTable);

        if (!DatabaseHelper::isMariaDb($connection)) {
            foreach (self::LOG_TRIGGERS as $trigger) {
                $connection->statement("DROP FUNCTION IF EXISTS {$logsTable}_{$trigger}()");
            }
        }
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
     * @return array<string>
     */
    protected static function mariaDbLogTriggerNames(string $table): array
    {
        return array_map(fn ($trigger) => DatabaseHelper::identifier($table, $trigger), self::LOG_TRIGGERS);
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

    /**
     * Drop the logs schema and the schemas made by createSchema(), which db:wipe leaves as they are not on the search path.
     */
    public static function dropCreatedSchemas(Connection $connection): void
    {
        if (DatabaseHelper::isMariaDb($connection)) {
            return;
        }

        $schemas = $connection->select(
            "select nspname from pg_namespace where nspname = ? or obj_description(oid, 'pg_namespace') = ?",
            [DatabaseHelper::LOGS_SCHEMA, DatabaseHelper::CREATED_SCHEMA_COMMENT]
        );

        foreach ($schemas as $schema) {
            $connection->statement('DROP SCHEMA '.$connection->getQueryGrammar()->wrap($schema->nspname).' CASCADE');
        }
    }

    // public
    public static function add()
    {
        BaseBuilder::macro('createSchema', function ($schema) {
            if (DatabaseHelper::isMariaDb($this->getConnection())) {
                return;
            }

            $connection = $this->getConnection();

            if ($connection->scalar('select count(*) from pg_namespace where nspname = ?', [$schema])) {
                return;
            }

            $connection->statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
            $connection->statement("COMMENT ON SCHEMA {$schema} IS '".DatabaseHelper::CREATED_SCHEMA_COMMENT."'");
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

            Builder::addLog($this, $table);
        });

        BaseBuilder::macro('dropIfExistsWithLog', function ($table) {
            $table = DatabaseHelper::tableName($table, $this->getConnection());

            Builder::dropLog($this, $table);

            $this->dropIfExists($table);
        });

        BaseBuilder::macro('addLog', function ($table, array $columns = []) {
            Builder::addLog($this, $table, $columns);
        });

        BaseBuilder::macro('refreshLog', function ($table, ?array $columns = null) {
            Builder::refreshLog($this, $table, $columns);
        });

        BaseBuilder::macro('dropLog', function ($table) {
            Builder::dropLog($this, $table);
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

            foreach (Builder::LOG_TRIGGERS as $trigger) {
                $connection->statement("DROP TRIGGER IF EXISTS {$trigger} ON {$from}");
                $connection->statement("DROP FUNCTION IF EXISTS {$fromLogsTable}_{$trigger}()");
            }

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