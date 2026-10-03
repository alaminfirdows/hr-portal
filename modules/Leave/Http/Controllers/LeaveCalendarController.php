<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Organization\Models\Holiday;
use Modules\Shared\Contracts\WorkCalendar;

/**
 * Month grid of approved leave inside the viewer's leave.view scope, plus holidays and weekends.
 */
final class LeaveCalendarController extends Controller
{
    public function __construct(
        private readonly Access $access,
        private readonly WorkCalendar $calendar,
    ) {}

    public function __invoke(Request $request): Response
    {
        Gate::authorize('leave.view');

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $month = $this->month($request->string('month')->toString());
        $end = $month->endOfMonth();

        $holidays = Holiday::query()->whereBetween('date', [$month->toDateString(), $end->toDateString()])->get()->keyBy(fn (Holiday $h) => $h->date->toDateString());

        $days = [];
        for ($day = $month; $day->lte($end); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'weekday' => $day->isoWeekday(),
                'is_weekend' => $this->calendar->isWeekend($day),
                'holiday' => $holidays->get($day->toDateString())?->name,
            ];
        }

        $entries = LeaveRequest::query()
            ->with(['type', 'employee'])
            ->withinIds($this->access->visibleEmployeeIds($user, 'leave.view'))
            ->where('status', LeaveStatus::Approved->value)
            ->overlapping($month, $end)
            ->orderBy('start_date')
            ->get()
            ->map(fn (LeaveRequest $r) => [
                'id' => $r->id,
                'employee' => $r->employee->full_name,
                'type' => $r->type->name,
                'color' => $r->type->color,
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date->toDateString(),
                'half' => $r->day_part->isHalf(),
            ]);

        return Inertia::render('leave/calendar', [
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'previous' => $month->subMonth()->format('Y-m'),
            'next' => $month->addMonth()->format('Y-m'),
            'days' => $days,
            'entries' => $entries,
        ]);
    }

    private function month(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('!Y-m', $value) ?: now()->toImmutable()->startOfMonth();
        }

        return now()->toImmutable()->startOfMonth();
    }
}
