<?php

namespace Amarenkov\MutableContent\Macros\Database\Schema;

use Closure;

use LogicException;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as BaseBuilder;

class Builder
{
    // protected
    public static function getLogsTableName($table)
    {
        $result = $table;

        if (strpos($result, '.') !== false) {
            $result = str_replace('.', '_', $result);
        }

        return 'logs.'. $result;
    }

    /**
     * Create the log functions and triggers of the table. Recreate them after renaming the table.
     */
    public static function createLogTriggers(Connection $connection, string $table): void
    {
        $logsTable = static::getLogsTableName($table);

        $connection->statement("
            CREATE OR REPLACE FUNCTION {$logsTable}_log_after_update()
            returns trigger AS $$
            BEGIN
                IF pg_trigger_depth() > 1 THEN
                    RETURN NULL;
                END IF;

                INSERT INTO {$logsTable} (
                    entity_id,
                    fields_old,
                    fields_new,
                    is_deleted,
                    user_id,
                    comment
                )
                SELECT
                    nd.id,
                    od.fields,
                    nd.fields,
                    CASE WHEN nd.deleted_at IS NOT NULL THEN true ELSE false END,
                    nd.updated_by_user_id,
                    nd.updated_with_comment
                FROM new_data nd
                LEFT JOIN old_data od ON nd.id = od.id
                WHERE
                    od.fields IS NULL OR
                    nd.fields IS DISTINCT FROM od.fields OR
                    (nd.deleted_at IS NULL AND od.deleted_at IS NOT NULL) OR
                    (nd.deleted_at IS NOT NULL AND od.deleted_at IS NULL);
                
                UPDATE {$table} t
                SET 
                    updated_by_user_id = NULL, 
                    updated_with_comment = NULL
                FROM new_data nd
                WHERE t.id = nd.id;

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
                    fields_new,
                    is_deleted,
                    user_id,
                    comment
                )
                SELECT
                    nd.id,
                    nd.fields,
                    CASE WHEN nd.deleted_at IS NOT NULL THEN true ELSE false END,
                    nd.updated_by_user_id,
                    nd.updated_with_comment
                FROM new_data nd;
                
                UPDATE {$table} t
                SET 
                    updated_by_user_id = NULL, 
                    updated_with_comment = NULL
                FROM new_data nd
                WHERE t.id = nd.id;

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
     * Rename schema indexes and sequences by name prefix: old prefix => new. The first matching prefix wins.
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
                if (!str_starts_with($relation->relname, $old.'_')) {
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
            $this->getConnection()->statement("CREATE SCHEMA IF NOT EXISTS {$schema};");
        });

        BaseBuilder::macro('createWithLog', function ($table, Closure $callback) {
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

            $hasSoftDelete = in_array('deleted_at', $columns);
            $hasUpdatedByUserId = in_array('updated_by_user_id', $columns);
            $updatedWithComment = in_array('updated_with_comment', $columns);

            if (!($hasSoftDelete && $hasUpdatedByUserId && $updatedWithComment)) {
                throw new LogicException("Oh, sorry, for that combination of fields I must refactor my log function.");
            }

            $this->build($blueprint);

            // logs section
            $this->createSchema('logs');
            
            $logsTable = Builder::getLogsTableName($table);
            
            $blueprint = tap($this->createBlueprint($logsTable), function ($blueprint) {
                $blueprint->create();

                $blueprint->unsignedInteger('entity_id');
                
                $blueprint->jsonb('fields_old')->nullable();
                $blueprint->jsonb('fields_new')->nullable();
                $blueprint->jsonb('fields_changed')->nullable();

                $blueprint->boolean('is_deleted');

                $blueprint->timestamp('date')->useCurrent();
                
                $blueprint->integer('user_id')->nullable();
                $blueprint->string('comment')->nullable();

                $blueprint->index('entity_id');
                $blueprint->index('date');
            });

            $this->build($blueprint);

            Builder::createLogTriggers($this->getConnection(), $table);
        });

        BaseBuilder::macro('dropIfExistsWithLog', function ($table) {
            $this->build(tap($this->createBlueprint($table), function ($blueprint) {
                $blueprint->dropIfExists();
            }));

            $logsTable = Builder::getLogsTableName($table);

            $this->build(tap($this->createBlueprint($logsTable), function ($blueprint) {
                $blueprint->dropIfExists();
            }));

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

            $fromLogsTable = Builder::getLogsTableName($from);
            $toLogsTable = Builder::getLogsTableName($to);

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
            Builder::renameRelationsByPrefix($connection, 'logs', [
                str_replace('.', '_', $fromLogsTable) => str_replace('.', '_', $toLogsTable),
            ]);

            Builder::createLogTriggers($connection, $to);
        });

        BaseBuilder::macro('upParseTimestamptz', function ($table) {
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