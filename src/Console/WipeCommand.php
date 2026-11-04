<?php

namespace Amarenkov\MutableContent\Console;

use Illuminate\Database\Console\WipeCommand as BaseWipeCommand;

use Amarenkov\MutableContent\Macros\Database\Schema\Builder;

class WipeCommand extends BaseWipeCommand
{
    // protected
    protected function dropAllTables($database)
    {
        parent::dropAllTables($database);

        Builder::dropCreatedSchemas($this->resolveDirectConnectionIfPossible($this->laravel['db'], $database));
    }
}
