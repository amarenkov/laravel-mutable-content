<?php

namespace Amarenkov\MutableContent\Helpers;

use Throwable;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;

/**
 * Generated and plain columns of tables, kept in the application cache between requests and dropped after migrations.
 */
class SchemaHelper
{
    // const
    public const CACHE_KEY = 'mutable-content:columns';

    // static
    /**
     * @var ?array<string, array{generated: array<string, string>, plain: array<string>}>
     */
    protected static ?array $columns = null;

    protected static bool $migrating = false;

    /**
     * Generated columns of the table: name => type.
     *
     * @return array<string, string>
     */
    public static function generatedColumns(Connection $connection, string $table): array
    {
        return static::columns($connection, $table)['generated'];
    }

    /**
     * Names of the columns of the table that are not generated; empty when the database is not available.
     *
     * @return array<string>
     */
    public static function plainColumns(Connection $connection, string $table): array
    {
        try {
            return static::columns($connection, $table)['plain'];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{generated: array<string, string>, plain: array<string>}
     */
    protected static function columns(Connection $connection, string $table): array
    {
        $key = $connection->getName().':'.$table;
        $cached = !self::$migrating && config('mutable-content.schema_cache');

        if ($cached) {
            self::$columns ??= Cache::get(self::CACHE_KEY, []);

            if (isset(self::$columns[$key])) {
                return self::$columns[$key];
            }
        }

        $result = ['generated' => [], 'plain' => []];

        foreach ($connection->getSchemaBuilder()->getColumns($table) as $column) {
            if ($column['generation'] !== null) {
                $result['generated'][$column['name']] = $column['type'];
            } else {
                $result['plain'][] = $column['name'];
            }
        }

        if ($cached) {
            self::$columns[$key] = $result;

            Cache::forever(self::CACHE_KEY, self::$columns);
        }

        return $result;
    }

    public static function migrationsStarted(): void
    {
        self::$migrating = true;
        self::$columns = null;
    }

    public static function migrationsEnded(): void
    {
        self::$migrating = false;

        self::flush();
    }

    public static function flush(): void
    {
        self::$columns = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
        }
    }
}
