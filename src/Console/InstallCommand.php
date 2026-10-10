<?php

namespace Amarenkov\MutableContent\Console;

use Illuminate\Console\Command;

use Amarenkov\MutableContent\Database\Seeders\FieldsSeeder;
use Amarenkov\MutableContent\Database\Seeders\LovsSeeder;

class InstallCommand extends Command
{
    // protected
    protected $signature = 'mutable-content:install
        {--config : Publish the config file too}
        {--no-migrate : Only publish the migrations}';

    protected $description = 'Publish the migrations, migrate and seed the system fields and lists of values';

    // public
    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'mutable-content-migrations']);

        if ($this->option('config')) {
            $this->call('vendor:publish', ['--tag' => 'mutable-content-config']);
        }

        if ($this->option('no-migrate')) {
            $this->info('Run migrations, then: php artisan db:seed --class="'.LovsSeeder::class.'" and --class="'.FieldsSeeder::class.'"');

            return self::SUCCESS;
        }

        $this->call('migrate');
        $this->call('db:seed', ['--class' => LovsSeeder::class]);
        $this->call('db:seed', ['--class' => FieldsSeeder::class]);

        $this->info('Done. Register your models in MutableClassRegistry and run the seeders again after adding fields in code.');

        return self::SUCCESS;
    }
}
