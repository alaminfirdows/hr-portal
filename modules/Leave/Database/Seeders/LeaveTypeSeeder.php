<?php

namespace Modules\Leave\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Models\LeaveType;

/**
 * Default leave types. Idempotent by code: existing types (and HR edits to them) are never overwritten.
 */
class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            ['Annual leave', 'AL', 'blue', true, false, true, AccrualMethod::Yearly, 18, 5, ['manager', 'hr'], null],
            ['Sick leave', 'SL', 'red', true, false, true, AccrualMethod::Yearly, 10, 0, ['manager'], null],
            ['Casual leave', 'CL', 'amber', true, false, true, AccrualMethod::Monthly, 8, 0, ['manager'], 3],
            ['Maternity leave', 'ML', 'purple', true, true, false, AccrualMethod::Yearly, 112, 0, ['manager', 'hr'], null],
            ['Paternity leave', 'PL', 'green', true, false, false, AccrualMethod::Yearly, 10, 0, ['manager', 'hr'], null],
            ['Unpaid leave', 'UL', 'gray', false, false, true, AccrualMethod::None, null, 0, ['manager', 'hr'], null],
        ];

        foreach ($defs as [$name, $code, $color, $paid, $attachment, $half, $method, $quota, $cap, $steps, $max]) {
            LeaveType::query()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'color' => $color,
                'is_paid' => $paid,
                'requires_attachment' => $attachment,
                'allow_half_day' => $half,
                'accrual_method' => $method,
                'annual_quota' => $quota,
                'carry_forward_cap' => $cap,
                'prorate_new_joiners' => true,
                'approval_steps' => $steps,
                'max_consecutive_days' => $max,
                'is_active' => true,
            ]);
        }
    }
}
