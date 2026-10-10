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

        $expression = $command->get('expression') ?: '('.$grammar->wrap('fields').' ->> \''.$column.'\')::'.$type;

        return "
            ALTER TABLE {$grammar->wrapTable($blueprint)} ADD COLUMN {$grammar->wrap($column)} {$type} GENERATED ALWAYS AS (
                {$expression}
            ) STORED
        ";
    }
}
