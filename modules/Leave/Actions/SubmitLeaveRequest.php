<?php

namespace Modules\Leave\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\DayPart;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Events\LeaveRequestSubmitted;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\ApprovalFlow;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeaveDayCalculator;
use Modules\Leave\Services\LeaveNotifier;
use Modules\Shared\Exceptions\DomainException;

/**
 * Validates business rules, reserves balance (pending += days) and starts the approval flow.
 */
final class SubmitLeaveRequest
{
    public function __construct(
        private readonly LeaveDayCalculator $calculator,
        private readonly LeaveBalanceService $balances,
        private readonly ApprovalFlow $flow,
        private readonly LeaveNotifier $notifier,
        private readonly ApproveLeaveRequest $approve,
    ) {}

    /**
     * @param  array{leave_type_id: int, start_date: string, end_date: string, day_part?: string|null, reason?: string|null, attachment?: UploadedFile|null}  $data
     */
    public function handle(Employee $employee, array $data, ?User $by = null): LeaveRequest
    {
        $type = LeaveType::query()->find($data['leave_type_id']);

        if ($type === null || ! $type->is_active) {
            throw new DomainException(__('This leave type is not available.'), 'leave_type_id');
        }

        $start = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($data['end_date'])->startOfDay();
        $part = DayPart::tryFrom($data['day_part'] ?? 'full') ?? DayPart::Full;
        $attachment = $data['attachment'] ?? null;

        $days = $this->validate($employee, $type, $start, $end, $part, $attachment !== null);

        $request = DB::transaction(function () use ($employee, $type, $start, $end, $part, $days, $data, $attachment): LeaveRequest {
            $balance = $this->balances->balance($employee, $type, $start->year);
            $locked = LeaveBalance::query()->lockForUpdate()->findOrFail($balance->id);

            if (! $type->isUnlimited() && $days > $locked->available()) {
                throw new DomainException(__('Insufficient balance: :available day(s) available, :days requested.', [
                    'available' => $locked->available(),
                    'days' => $days,
                ]), 'start_date');
            }

            $path = $attachment instanceof UploadedFile
                ? Storage::disk(config('filesystems.default'))->putFile('leave-attachments', $attachment)
                : null;

            $request = LeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start,
                'end_date' => $end,
                'day_part' => $part,
                'days' => $days,
                'reason' => $data['reason'] ?? null,
                'attachment_path' => $path ?: null,
                'status' => LeaveStatus::Pending,
                'steps' => $this->flow->resolveSteps($employee, $type),
                'current_step' => 0,
            ]);

            $locked->pending = round($locked->pending + $days, 2);
            $locked->save();

            return $request;
        });

        $request->setRelation('employee', $employee);
        $request->setRelation('type', $type);

        LeaveRequestSubmitted::dispatch($request, $by?->id);

        if ($request->steps === []) {
            return $this->approve->complete($request, null);
        }

        $this->notifier->approvalNeeded($request);

        return $request;
    }

    private function validate(Employee $employee, LeaveType $type, CarbonImmutable $start, CarbonImmutable $end, DayPart $part, bool $hasAttachment): float
    {
        if ($end->lt($start)) {
            throw new DomainException(__('The end date cannot be before the start date.'), 'end_date');
        }

        if ($start->lt(now()->startOfDay()->subDays(30))) {
            throw new DomainException(__('Leave cannot start more than 30 days in the past.'), 'start_date');
        }

        if ($start->year !== $end->year) {
            throw new DomainException(__('A request cannot span two calendar years. Submit one request per year.'), 'end_date');
        }

        if ($part->isHalf()) {
            if (! $type->allow_half_day) {
                throw new DomainException(__(':type does not allow half days.', ['type' => $type->name]), 'day_part');
            }

            if (! $start->isSameDay($end)) {
                throw new DomainException(__('A half day must be a single day.'), 'day_part');
            }
        }

        $days = $this->calculator->days($start, $end, $part);

        if ($days <= 0) {
            throw new DomainException(__('The selected dates contain no working days.'), 'start_date');
        }

        $overlap = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->active()
            ->overlapping($start, $end)
            ->first();

        if ($overlap !== null) {
            throw new DomainException(__('You already have a :status leave request from :from to :to that overlaps these dates.', [
                'status' => $overlap->status->value,
                'from' => $overlap->start_date->toFormattedDateString(),
                'to' => $overlap->end_date->toFormattedDateString(),
            ]), 'start_date');
        }

        if ($type->max_consecutive_days !== null && $start->diffInDays($end) + 1 > $type->max_consecutive_days) {
            throw new DomainException(__(':type allows at most :max consecutive days.', [
                'type' => $type->name,
                'max' => $type->max_consecutive_days,
            ]), 'end_date');
        }

        if ($type->requires_attachment && ! $hasAttachment) {
            throw new DomainException(__(':type requires a supporting document.', ['type' => $type->name]), 'attachment');
        }

        return $days;
    }
}
