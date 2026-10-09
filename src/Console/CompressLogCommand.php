<?php

namespace Amarenkov\MutableContent\Console;

use Amarenkov\MutableContent\Helpers\LogHelper;

/**
 * Scheduled by the package with mutable-content.log.compress.schedule, null disables it.
 */
class CompressLogCommand extends LogCommand
{
    // protected
    protected $signature = 'mutable-content:compress-log
        {--limit= : Maximum entries of each class to process, mutable-content.log.compress.limit by default}
        {--class=* : Mutable classes, all registered by default}';

    protected $description = 'Replace old and new fields of change log entries with their changes (scheduled by the package, see mutable-content.log.compress.schedule)';

    // public
    public function handle(): int
    {
        $limit = $this->intOption('limit', 1, config('mutable-content.log.compress.limit'));

        if ($limit === null) {
            return self::FAILURE;
        }

        foreach ($this->classes() as $class) {
            $this->line($class.': '.LogHelper::compress($class, $limit).' entries processed');
        }

        return self::SUCCESS;
    }
}
