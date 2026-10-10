<?php

namespace Amarenkov\MutableContent\Console;

use Illuminate\Console\Command;

use Amarenkov\MutableContent\Helpers\DefinitionsHelper;

class ExportDefinitionsCommand extends Command
{
    // protected
    protected $signature = 'mutable-content:export-definitions
        {file? : JSON file to write, the output by default}';

    protected $description = 'Export fields, usages, LOVs and items created in the admin panel as JSON';

    // public
    public function handle(): int
    {
        $json = json_encode(DefinitionsHelper::export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($file = $this->argument('file')) {
            file_put_contents($file, $json.PHP_EOL);
            $this->info('Written to '.$file);
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
