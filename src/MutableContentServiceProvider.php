<?php

namespace Amarenkov\MutableContent;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

use Illuminate\Queue\Events\JobProcessing;

use Illuminate\Console\Scheduling\Schedule;

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;

use Amarenkov\MutableContent\Console\CompressLogCommand;
use Amarenkov\MutableContent\Console\PruneLogCommand;

use Amarenkov\MutableContent\Macros\Database\Schema\Blueprint as DatabaseSchemaBlueprintMacros;
use Amarenkov\MutableContent\Macros\Database\Schema\Builder as DatabaseSchemaBuilderMacros;
use Amarenkov\MutableContent\Macros\Database\Schema\Grammars\Grammar as DatabaseSchemaGrammarMacros;
use Amarenkov\MutableContent\Macros\Support\Str as SupportStrMacros;

use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContent\Domain\LovRegistry;

use Amarenkov\MutableContent\Database\Log\LogContext;

use Amarenkov\MutableContent\Models\ModelWithFields;

use Amarenkov\MutableContent\Models\Field\Field as ModelField;
use Amarenkov\MutableContent\Models\Field\Usage as ModelFieldUsage;

use Amarenkov\MutableContent\Models\Lov\Lov as ModelLov;
use Amarenkov\MutableContent\Models\Lov\Item as ModelLovItem;

use Amarenkov\MutableContent\Domain\Field\Lov\Type as DomainLovFieldType;

class MutableContentServiceProvider extends ServiceProvider
{
    // protected
    protected function registerMutableClasses()
    {
        $registry = $this->app->make(MutableClassRegistry::class);

        $registry->add(ModelField::class);
        $registry->add(ModelFieldUsage::class);
        $registry->add(ModelLov::class);
        $registry->add(ModelLovItem::class);
    }

    protected function registerLovs()
    {
        $lovRegistry = $this->app->make(LovRegistry::class);

        $lovRegistry->addClass(DomainLovFieldType::class);

        $lovRegistry->addSource($this->app->make(MutableClassRegistry::class));
    }

    protected function flushCachesInLongRunningProcesses()
    {
        $flush = function () {
            ModelWithFields::flushFieldDefinitions();

            $this->app->make(LovRegistry::class)->flush();

            $this->app->make(LogContext::class)->clear();
        };

        Event::listen(JobProcessing::class, $flush);
        Event::listen('Laravel\Octane\Events\RequestReceived', $flush);
    }

    protected function trackLogTransactions()
    {
        Event::listen([TransactionCommitted::class, TransactionRolledBack::class], function (TransactionCommitted|TransactionRolledBack $event) {
            if ($this->app->resolved(LogContext::class)) {
                $this->app->make(LogContext::class)->transactionFinished($event->connection);
            }
        });
    }

    protected function scheduleLogCompression()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $cron = config('mutable-content.log.compress.schedule');

            if (!$cron) {
                return;
            }

            $schedule->command(CompressLogCommand::class)->cron($cron)->withoutOverlapping(60);
        });
    }

    // public
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mutable-content.php', 'mutable-content');

        $this->app->singleton(MutableClassRegistry::class);

        $this->app->singleton(LovRegistry::class);

        $this->app->singleton(LogContext::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/mutable-content.php' => config_path('mutable-content.php'),
        ], 'mutable-content-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'mutable-content-migrations');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'mutable-content');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/mutable-content'),
        ], 'mutable-content-lang');

        $this->registerMutableClasses();
        $this->registerLovs();

        $this->flushCachesInLongRunningProcesses();
        $this->trackLogTransactions();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CompressLogCommand::class,
                PruneLogCommand::class,
            ]);

            $this->scheduleLogCompression();
        }

        SupportStrMacros::add();

        DatabaseSchemaBlueprintMacros::add();
        DatabaseSchemaBuilderMacros::add();
        DatabaseSchemaGrammarMacros::add();
    }
}