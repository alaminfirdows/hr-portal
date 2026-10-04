<?php

namespace Modules\Leave\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Actions\AccrueMonthlyBalances;
use Modules\Leave\Actions\AllocateYearlyBalances;
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
 * This year's balances and ~40 requests of mixed status with balances kept consistent.
 */
class LeaveDemoSeeder extends Seeder
{
    public function run(
        AllocateYearlyBalances $allocate,
        AccrueMonthlyBalances $accrue,
        LeaveBalanceService $balances,
        LeaveDayCalculator $calculator,
    ): void {
        if (LeaveRequest::query()->exists()) {
            return;
        }

        $this->call(LeaveTypeSeeder::class);

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
}
