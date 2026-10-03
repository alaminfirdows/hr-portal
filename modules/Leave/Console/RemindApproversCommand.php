<?php

namespace Modules\Leave\Console;

use Illuminate\Console\Command;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Notifications\ApprovalReminderNotification;
use Modules\Leave\Services\ApprovalFlow;

class RemindApproversCommand extends Command
{
    protected $signature = 'leave:remind-approvers {--days=2 : Pending for more than this many days}';

    protected $description = 'Remind approvers of leave requests pending for more than N days';

    public function handle(ApprovalFlow $flow): int
    {
        $cutoff = now()->subDays(max(0, (int) $this->option('days')));

        /** @var array<int, int> $perApprover user id => pending count */
        $perApprover = [];

        LeaveRequest::query()
            ->where('status', LeaveStatus::Pending->value)
            ->where('created_at', '<=', $cutoff)
            ->each(function (LeaveRequest $request) use ($flow, &$perApprover) {
                foreach ($flow->approverUserIds($request) as $userId) {
                    $perApprover[$userId] = ($perApprover[$userId] ?? 0) + 1;
                }
            });

        User::query()->whereIn('id', array_keys($perApprover))->get()
            ->each(fn (User $user) => $user->notify(new ApprovalReminderNotification($perApprover[$user->id])));

        $this->info('Reminded '.count($perApprover).' approver(s).');

        return self::SUCCESS;
    }
}
