<?php

namespace Amarenkov\MutableContent\Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Console\WipeCommand;
use Amarenkov\MutableContent\Helpers\DatabaseHelper;
use Amarenkov\MutableContent\Macros\Database\Schema\Builder;

use Amarenkov\MutableContent\Tests\FeatureTestCase;

class WipeTest extends FeatureTestCase
{
    protected function schemaExists(string $schema): bool
    {
        return (bool)DB::scalar('select count(*) from pg_namespace where nspname = ?', [$schema]);
    }

    public function test_wipe_command_is_replaced(): void
    {
        $this->assertInstanceOf(WipeCommand::class, Artisan::all()['db:wipe']);
    }

    public function test_only_logs_and_schemas_created_by_the_package_are_dropped(): void
    {
        if (DatabaseHelper::isMariaDb()) {
            $this->markTestSkipped('MariaDB has no schemas: its tables are dropped with the database tables.');
        }

        DB::statement('CREATE SCHEMA wipe_foreign');
        Schema::createSchema('wipe_created');
        Schema::createSchema('wipe_foreign');

        DB::statement('CREATE TABLE wipe_created.items (id int)');

        Builder::dropCreatedSchemas(DB::connection());

        $this->assertFalse($this->schemaExists('wipe_created'));
        $this->assertFalse($this->schemaExists(DatabaseHelper::LOGS_SCHEMA));
        $this->assertTrue($this->schemaExists('wipe_foreign'));
        $this->assertTrue($this->schemaExists('public'));
    }
}
