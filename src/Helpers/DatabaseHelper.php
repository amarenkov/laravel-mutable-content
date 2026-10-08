<?php

namespace Amarenkov\MutableContent\Helpers;

use LogicException;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\MariaDbGrammar as MariaDbQueryGrammar;
use Illuminate\Support\Facades\DB;

/**
 * Differences between the supported database engines: PostgreSQL and MariaDB.
 */
class DatabaseHelper
{
    // const
    public const DRIVER_PGSQL = 'pgsql';
    public const DRIVER_MARIADB = 'mariadb';

    public const DRIVERS = [self::DRIVER_PGSQL, self::DRIVER_MARIADB];

    public const SCHEMA_SEPARATOR = '__';

    public const LOGS_SCHEMA = 'logs';

    public const IDENTIFIER_MAX_LENGTH = 64;

    // static
    public static function driver(?Connection $connection = null): string
    {
        $driver = ($connection ?? DB::connection())->getDriverName();

        if (!in_array($driver, self::DRIVERS, true)) {
            throw new LogicException('Mutable content supports PostgreSQL and MariaDB only, '.$driver.' given');
        }

        return $driver;
    }

    public static function isMariaDb(?Connection $connection = null): bool
    {
        return ($connection ?? DB::connection())->getDriverName() === self::DRIVER_MARIADB;
    }

    /**
     * Table name for the connection: "schema.table" stays as is on PostgreSQL and becomes "schema__table" on MariaDB.
     */
    public static function tableName(string $table, ?Connection $connection = null): string
    {
        if (!static::isMariaDb($connection)) {
            return $table;
        }

        return str_replace('.', self::SCHEMA_SEPARATOR, $table);
    }

    /**
     * Log table of the table, both resolved by tableName().
     */
    public static function logsTableName(string $table, ?Connection $connection = null): string
    {
        $table = static::tableName($table, $connection);

        if (static::isMariaDb($connection)) {
            return self::LOGS_SCHEMA.self::SCHEMA_SEPARATOR.$table;
        }

        return self::LOGS_SCHEMA.'.'.str_replace('.', '_', $table);
    }

    /**
     * Identifier derived from a table name, shortened with a hash to the engine limit.
     */
    public static function identifier(string $table, string $suffix): string
    {
        $name = str_replace('.', '_', $table).'_'.$suffix;

        if (strlen($name) <= self::IDENTIFIER_MAX_LENGTH) {
            return $name;
        }

        $hash = substr(md5($name), 0, 8);

        return substr($name, 0, self::IDENTIFIER_MAX_LENGTH - strlen($suffix) - strlen($hash) - 2).'_'.$hash.'_'.$suffix;
    }

    /**
     * Case-insensitive "like" on a column or a fields->code path.
     */
    public static function whereLike(QueryBuilder|EloquentBuilder $query, string $column, string $pattern, string $boolean = 'and'): QueryBuilder|EloquentBuilder
    {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;

        if ($base->getGrammar() instanceof MariaDbQueryGrammar) {
            return $query->whereRaw('lower('.$base->getGrammar()->wrap($column).') like lower(?)', [$pattern], $boolean);
        }

        return $query->where($column, 'ilike', $pattern, $boolean);
    }

    public static function orWhereLike(QueryBuilder|EloquentBuilder $query, string $column, string $pattern): QueryBuilder|EloquentBuilder
    {
        return static::whereLike($query, $column, $pattern, 'or');
    }
}
