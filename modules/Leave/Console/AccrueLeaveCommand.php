<?php

namespace Modules\Leave\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Leave\Actions\AccrueMonthlyBalances;

class AccrueLeaveCommand extends Command
{
    protected $signature = 'leave:accrue {month? : Month as YYYY-MM, default current}';

    protected $description = 'Credit monthly-accrual leave balances (safe to re-run)';

    public function handle(AccrueMonthlyBalances $accrue): int
    {
        $value = (string) ($this->argument('month') ?: now()->format('Y-m'));
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1 ? CarbonImmutable::createFromFormat('!Y-m', $value) : false;

        if ($month === false) {
            $this->error('Month must be YYYY-MM.');

            return self::FAILURE;
        }

        $this->info("Credited {$accrue->handle($month)} balances for {$value}.");

        return self::SUCCESS;
    }
}
