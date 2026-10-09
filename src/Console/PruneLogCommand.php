<?php

namespace Amarenkov\MutableContent\Console;

use Illuminate\Support\Carbon;

use Amarenkov\MutableContent\Helpers\LogHelper;

/**
 * Not scheduled by the package: the application decides whether and when to delete its log.
 */
class PruneLogCommand extends LogCommand
{
    // protected
    protected $signature = 'mutable-content:prune-log
        {--days= : Delete entries older than this number of days}
        {--class=* : Mutable classes, all registered by default}';

    protected $description = 'Delete old change log entries (not scheduled by the package)';

    // public
    public function handle(): int
    {
        $days = $this->intOption('days', 0);

        if ($days === null) {
            return self::FAILURE;
        }

        $before = Carbon::now()->subDays($days);

        foreach ($this->classes() as $class) {
            $this->line($class.': '.LogHelper::prune($class, $before).' entries deleted');
        }

        return self::SUCCESS;
    }
}
