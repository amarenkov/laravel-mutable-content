<?php

namespace Amarenkov\MutableContent\Console;

use InvalidArgumentException;

use Illuminate\Console\Command;

use Amarenkov\MutableContent\Helpers\DefinitionsHelper;

class ImportDefinitionsCommand extends Command
{
    // protected
    protected $signature = 'mutable-content:import-definitions
        {file : JSON file written by mutable-content:export-definitions}
        {--comment=Import of definitions : Change log comment}
        {--dry-run : Show what would change and roll back}';

    protected $description = 'Create or update fields, usages, LOVs and items by their codes from a JSON export';

    // public
    public function handle(): int
    {
        $file = $this->argument('file');
        $data = is_readable($file) ? json_decode((string)file_get_contents($file), true) : null;

        if (!is_array($data)) {
            $this->error('Cannot read JSON from '.$file);

            return self::FAILURE;
        }

        try {
            $result = DefinitionsHelper::import($data, $this->option('comment'), null, (bool)$this->option('dry-run'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ([DefinitionsHelper::LOVS, DefinitionsHelper::ITEMS, DefinitionsHelper::FIELDS, DefinitionsHelper::USAGES] as $section) {
            $counts = $result[$section] ?? [];
            $this->line($section.': '.($counts ? implode(', ', array_map(fn ($outcome, $count) => $count.' '.$outcome, array_keys($counts), $counts)) : 'none'));
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was saved');
        }

        return self::SUCCESS;
    }
}
