<?php

namespace Amarenkov\MutableContent\Macros\Database\Schema\Grammars;

use Illuminate\Support\Fluent;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as BasePostgresGrammar;

class PostgresGrammar
{
    public static function add()
    {
        BasePostgresGrammar::macro('compileFieldExtract', function (Blueprint $blueprint, Fluent $command) {
            if (!$command->expression)
                $command->expression = '(fields ->> \''.$command->column.'\')::'.$command->type;

            return "
                ALTER TABLE {$blueprint->getTable()} ADD COLUMN {$command->column} {$command->type} GENERATED ALWAYS AS (
                    {$command->expression}
                ) STORED
            ";
        });
    }
}