<?php

namespace Modules\Attendance\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Attendance\Actions\FinalizeAttendanceDay;

class FinalizeAttendanceCommand extends Command
{
    protected $signature = 'attendance:finalize {date? : Date as YYYY-MM-DD, default today}';

    protected $description = 'Write weekend, holiday, leave and absent rows for a day (safe to re-run)';

    public function handle(FinalizeAttendanceDay $finalize): int
    {
        $value = (string) ($this->argument('date') ?: now()->toDateString());
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : false;

        if ($date === false || $date->toDateString() !== $value) {
            $this->error('Date must be YYYY-MM-DD.');

            return self::FAILURE;
        }

        $this->info("Created {$finalize->handle($date)} records for {$value}.");

        return self::SUCCESS;
    }
}
