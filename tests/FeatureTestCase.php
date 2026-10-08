<?php

namespace Amarenkov\MutableContent\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use Amarenkov\MutableContent\Database\Seeders\FieldsSeeder;
use Amarenkov\MutableContent\Database\Seeders\LovsSeeder;
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

        $app['config']->set('database.default', 'pgsql');
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
        foreach (['public', 'logs', 'fixtures'] as $schema) {
            DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
        }

        DB::statement('CREATE SCHEMA public');

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
}
