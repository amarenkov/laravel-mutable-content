<?php

namespace Amarenkov\MutableContent\Helpers;

use Throwable;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;

/**
 * Generated columns of tables, kept in the application cache between requests and dropped after migrations.
 */
class SchemaHelper
{
    // const
    public const CACHE_KEY = 'mutable-content:generated-columns';

    // static
    /**
     * @var ?array<string, array<string, string>>
     */
    protected static ?array $generatedColumns = null;

    protected static bool $migrating = false;

    /**
     * Generated columns of the table: name => type.
     *
     * @return array<string, string>
     */
    public static function generatedColumns(Connection $connection, string $table): array
    {
        $key = $connection->getName().':'.$table;
        $cached = !self::$migrating && config('mutable-content.schema_cache');

        if ($cached) {
            self::$generatedColumns ??= Cache::get(self::CACHE_KEY, []);

            if (isset(self::$generatedColumns[$key])) {
                return self::$generatedColumns[$key];
            }
        }

        $result = [];

        foreach ($connection->getSchemaBuilder()->getColumns($table) as $column) {
            if ($column['generation'] !== null) {
                $result[$column['name']] = $column['type'];
            }
        }

        if ($cached) {
            self::$generatedColumns[$key] = $result;

            Cache::forever(self::CACHE_KEY, self::$generatedColumns);
        }

        return $result;
    }

    public static function migrationsStarted(): void
    {
        self::$migrating = true;
        self::$generatedColumns = null;
    }

    public static function migrationsEnded(): void
    {
        self::$migrating = false;

        self::flush();
    }

    public static function flush(): void
    {
        self::$generatedColumns = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
        }
    }
}
