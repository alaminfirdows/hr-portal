<?php

namespace Modules\Leave\Console;

use Illuminate\Console\Command;
use Modules\Leave\Actions\AllocateYearlyBalances;

class AllocateLeaveCommand extends Command
{
    protected $signature = 'leave:allocate {year? : Calendar year, default current}';

    protected $description = 'Create leave balance rows for a year (quota and carry forward)';

    public function handle(AllocateYearlyBalances $allocate): int
    {
        $year = (int) ($this->argument('year') ?: now()->year);

        $this->info("Created {$allocate->handle($year)} balance rows for {$year}.");

        return self::SUCCESS;
    }
}
