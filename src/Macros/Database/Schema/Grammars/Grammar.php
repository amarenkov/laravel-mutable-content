<?php

namespace Amarenkov\MutableContent\Macros\Database\Schema\Grammars;

use LogicException;

use Illuminate\Support\Fluent;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar as BaseGrammar;
use Illuminate\Database\Schema\Grammars\MariaDbGrammar as BaseMariaDbGrammar;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as BasePostgresGrammar;

/**
 * Schema grammar macros. Grammars share one macro list, so a macro dispatches by the grammar class.
 */
class Grammar
{
    public static function add()
    {
        BaseGrammar::macro('compileFieldExtract', function (Blueprint $blueprint, Fluent $command) {
            return match (true) {
                $this instanceof BasePostgresGrammar => PostgresGrammar::compileFieldExtract($this, $blueprint, $command),
                $this instanceof BaseMariaDbGrammar => MariaDbGrammar::compileFieldExtract($this, $blueprint, $command),
                default => throw new LogicException('fieldExtract() is supported on PostgreSQL and MariaDB only'),
            };
        });
    }
}
