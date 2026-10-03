<?php

namespace Modules\Leave\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Actions\AccrueMonthlyBalances;
use Modules\Leave\Actions\AllocateYearlyBalances;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Enums\ApprovalDecision;
use Modules\Leave\Enums\ApprovalStepRole;
use Modules\Leave\Enums\DayPart;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveApproval;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveDayCalculator;

/**
 * Leave types, this year's balances and ~40 requests of mixed status with balances kept consistent.
 */
class LeaveDemoSeeder extends Seeder
{
    public function run(
        AllocateYearlyBalances $allocate,
        AccrueMonthlyBalances $accrue,
        LeaveBalanceService $balances,
        LeaveDayCalculator $calculator,
    ): void {
        if (LeaveType::query()->exists()) {
            return;
        }

        $this->types();

        $now = CarbonImmutable::now();
        $year = $now->year;

        $allocate->handle($year);
        for ($m = 1; $m <= $now->month; $m++) {
            $accrue->handle(CarbonImmutable::create($year, $m, 1));
        }

        $employees = Employee::query()->employed()->orderBy('id')->get();
        $types = LeaveType::query()->whereIn('code', ['AL', 'SL', 'CL', 'UL'])->get()->keyBy('code');
        $hrUserId = User::query()->where('email', 'hr@example.com')->value('id');
        $plan = ['approved', 'approved', 'pending', 'approved', 'rejected', 'cancelled', 'pending', 'approved'];
        $made = 0;

        foreach ($employees as $i => $employee) {
            if ($made >= 40) {
                break;
            }

            $status = $plan[$i % count($plan)];
            $type = $types->get(['AL', 'SL', 'CL', 'AL', 'UL'][$i % 5]);

            if ($type === null) {
                continue;
            }

            $past = in_array($status, ['approved', 'cancelled'], true) && $i % 2 === 0;
            $start = $past
                ? $now->subDays(15 + ($i * 3) % 120)
                : $now->addDays(3 + ($i * 5) % 60);
            $length = 1 + $i % 3;
            $end = $start->addDays($length - 1);

            if ($start->year !== $year || $end->year !== $year || $start->lt($employee->joined_on)) {
                continue;
            }

            $days = $calculator->days($start, $end, DayPart::Full);

            if ($days <= 0) {
                continue;
            }

            $overlap = LeaveRequest::query()->where('employee_id', $employee->id)->overlapping($start, $end)->exists();
            $available = $balances->available($employee, $type, $year);

            if ($overlap || ($available !== null && $available < $days)) {
                continue;
            }

            $steps = array_map(fn (ApprovalStepRole $r) => $r->value, $type->configuredSteps());

            $request = LeaveRequest::factory()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
                'status' => match ($status) {
                    'approved' => LeaveStatus::Approved,
                    'rejected' => LeaveStatus::Rejected,
                    'cancelled' => LeaveStatus::Cancelled,
                    default => LeaveStatus::Pending,
                },
                'steps' => $steps,
                'current_step' => $status === 'approved' ? count($steps) : 0,
                'decided_at' => $status === 'pending' ? null : $now,
            ]);
            $request->load('type');

            match ($status) {
                'pending' => $balances->move($request, $days, 0),
                'approved' => $balances->move($request, 0, $days),
                default => null,
            };

            if ($status === 'approved') {
                foreach ($steps as $step => $role) {
                    LeaveApproval::query()->create([
                        'leave_request_id' => $request->id,
                        'step' => $step,
                        'step_role' => ApprovalStepRole::from($role),
                        'approver_id' => $hrUserId,
                        'decision' => ApprovalDecision::Approved,
                    ]);
                }
            } elseif ($status === 'rejected') {
                LeaveApproval::query()->create([
                    'leave_request_id' => $request->id,
                    'step' => 0,
                    'step_role' => ApprovalStepRole::from($steps[0] ?? 'hr'),
                    'approver_id' => $hrUserId,
                    'decision' => ApprovalDecision::Rejected,
                    'comment' => 'Team coverage needed in that period.',
                ]);
            }

            $made++;
        }
    }

    private function types(): void
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
            LeaveType::query()->create([
                'name' => $name,
                'code' => $code,
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
