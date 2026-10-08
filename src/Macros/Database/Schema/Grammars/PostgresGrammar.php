<?php

namespace Amarenkov\MutableContent\Macros\Database\Schema\Grammars;

use Illuminate\Support\Fluent;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as BasePostgresGrammar;

class PostgresGrammar
{
    public static function compileFieldExtract(BasePostgresGrammar $grammar, Blueprint $blueprint, Fluent $command): string
    {
        $column = $command->get('column');
        $type = $command->get('type');

        $expression = $command->get('expression') ?: '(fields ->> \''.$column.'\')::'.$type;

        return "
            ALTER TABLE {$blueprint->getTable()} ADD COLUMN {$column} {$type} GENERATED ALWAYS AS (
                {$expression}
            ) STORED
        ";
    }
}
