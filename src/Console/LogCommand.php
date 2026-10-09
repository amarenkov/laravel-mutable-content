<?php

namespace Amarenkov\MutableContent\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

use Amarenkov\MutableContent\Domain\MutableClassRegistry;
use Amarenkov\MutableContent\Helpers\LogHelper;
use Amarenkov\MutableContent\Models\ModelWithFields;

abstract class LogCommand extends Command
{
    // protected
    /**
     * Registered mutable classes with a log table, or the classes of the --class option.
     *
     * @return array<class-string<ModelWithFields>>
     */
    protected function classes(): array
    {
        $classes = $this->option('class') ?: array_map(fn ($mcd) => $mcd->mutableClass, app(MutableClassRegistry::class)->all());

        return array_values(array_filter($classes, function ($class) {
            if (!is_subclass_of($class, ModelWithFields::class)) {
                $this->warn($class.' is not a mutable class, skipped.');

                return false;
            }

            return Schema::connection(new $class()->getConnectionName())->hasTable(LogHelper::getLogsTable($class));
        }));
    }

    protected function intOption(string $name, int $min, mixed $default = null): ?int
    {
        $value = filter_var($this->option($name) ?? $default, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]);

        if ($value === false) {
            $this->error('The --'.$name.' option must be an integer not less than '.$min.'.');

            return null;
        }

        return $value;
    }
}
