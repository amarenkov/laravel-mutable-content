<?php

namespace Amarenkov\MutableContent;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

use Illuminate\Queue\Events\JobProcessing;

use Amarenkov\MutableContent\Macros\Database\Schema\Blueprint as DatabaseSchemaBlueprintMacros;
use Amarenkov\MutableContent\Macros\Database\Schema\Builder as DatabaseSchemaBuilderMacros;
use Amarenkov\MutableContent\Macros\Database\Schema\Grammars\Grammar as DatabaseSchemaGrammarMacros;
use Amarenkov\MutableContent\Macros\Support\Str as SupportStrMacros;

use Amarenkov\MutableContent\Domain\MutableClassRegistry;

use Amarenkov\MutableContent\Domain\LovRegistry;

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
        };

        Event::listen(JobProcessing::class, $flush);
        Event::listen('Laravel\Octane\Events\RequestReceived', $flush);
    }

    // public
    public function register(): void
    {
        $this->app->singleton(MutableClassRegistry::class);

        $this->app->singleton(LovRegistry::class);
    }

    public function boot(): void
    {
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

        SupportStrMacros::add();

        DatabaseSchemaBlueprintMacros::add();
        DatabaseSchemaBuilderMacros::add();
        DatabaseSchemaGrammarMacros::add();
    }
}