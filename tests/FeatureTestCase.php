<?php

namespace Amarenkov\MutableContent\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use Amarenkov\MutableContent\Database\Seeders\FieldsSeeder;
use Amarenkov\MutableContent\Database\Seeders\LovsSeeder;
use Amarenkov\MutableContent\Helpers\DatabaseHelper;
use Amarenkov\MutableContent\Helpers\LogHelper;
use Amarenkov\MutableContent\Models\ModelWithFields;
use Amarenkov\MutableContent\MutableContentServiceProvider;

use Amarenkov\MutableContent\Tests\Fixtures\FixturesServiceProvider;

abstract class FeatureTestCase extends TestCase
{
    use DatabaseTransactions;

    protected static bool $databaseReady = false;

    protected function getPackageProviders($app): array
    {
        return [
            MutableContentServiceProvider::class,
            FixturesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', env('DB_CONNECTION', 'pgsql'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        ModelWithFields::flushFieldDefinitions();
    }

    protected function setUpTraits()
    {
        if (!static::$databaseReady) {
            $this->prepareDatabase();

            static::$databaseReady = true;
        }

        return parent::setUpTraits();
    }

    protected function prepareDatabase(): void
    {
        if (DatabaseHelper::isMariaDb()) {
            foreach (DB::connection()->getSchemaBuilder()->getTableListing(schemaQualified: false) as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$table.'`');
            }
        } else {
            foreach (['public', 'logs', 'fixtures'] as $schema) {
                DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
            }

            DB::statement('CREATE SCHEMA public');
        }

        Artisan::call('migrate', [
            '--path' => [
                realpath(__DIR__.'/../database/migrations'),
                realpath(__DIR__.'/Fixtures/database/migrations'),
            ],
            '--realpath' => true,
        ]);

        $this->seed(LovsSeeder::class);
        $this->seed(FieldsSeeder::class);

        ModelWithFields::flushFieldDefinitions();
    }

    /**
     * Rebuild the database before the next test: DDL commits the test transaction on MariaDB.
     */
    protected function rebuildDatabaseAfterTest(): void
    {
        if (DatabaseHelper::isMariaDb()) {
            static::$databaseReady = false;
        }
    }

    protected function tableOf(string $class): string
    {
        return new $class()->getTable();
    }

    protected function logsTableOf(string $class): string
    {
        return LogHelper::getLogsTable($class);
    }

    /**
     * SQL expression adding a string key to the fields column.
     */
    protected function fieldsWithKey(string $key, string $value): mixed
    {
        return DatabaseHelper::isMariaDb()
            ? DB::raw("json_set(fields, '$.\"{$key}\"', '{$value}')")
            : DB::raw("fields || jsonb_build_object('{$key}', '{$value}')");
    }
}
