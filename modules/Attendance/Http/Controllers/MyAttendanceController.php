<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Actions\CheckIn;
use Modules\Attendance\Actions\CheckOut;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Http\Resources\AttendancePresenter;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Services\ShiftResolver;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Exceptions\DomainException;

/**
 * The signed-in employee's own attendance: today's card, check-in/out, month history.
 */
final class MyAttendanceController extends Controller
{
    public function __construct(
        private readonly OrgHierarchy $org,
        private readonly ShiftResolver $shifts,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('attendance.check_in');

        $employeeId = $this->employeeId($request);
        $month = $this->month($request->string('month')->toString());
        $end = $month->endOfMonth();

        $records = AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->betweenDates($month->toDateString(), $end->toDateString())
            ->orderByDesc('date')
            ->get();

        $today = $records->first(fn (AttendanceRecord $r) => $r->date->isToday())
            ?? AttendanceRecord::query()->where('employee_id', $employeeId)->whereDate('date', now()->toDateString())->first();
        $shift = $this->shifts->forEmployee($employeeId);

        return Inertia::render('attendance/my', [
            'today' => $today === null ? null : AttendancePresenter::record($today),
            'shift' => AttendancePresenter::shift($shift),
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'previous' => $month->subMonth()->format('Y-m'),
            'next' => $month->addMonth()->format('Y-m'),
            'records' => $records->map(fn (AttendanceRecord $r) => AttendancePresenter::record($r))->values(),
            'stats' => [
                'present' => $records->where('status', AttendanceStatus::Present)->count(),
                'half_day' => $records->where('status', AttendanceStatus::HalfDay)->count(),
                'absent' => $records->where('status', AttendanceStatus::Absent)->count(),
                'on_leave' => $records->where('status', AttendanceStatus::OnLeave)->count(),
                'late' => $records->where('is_late', true)->count(),
                'overtime_minutes' => (int) $records->sum('overtime_minutes'),
            ],
        ]);
    }

    public function checkIn(Request $request, CheckIn $checkIn): RedirectResponse
    {
        Gate::authorize('attendance.check_in');

        $record = $checkIn->handle($this->employeeId($request), $request->ip());

        Inertia::flash('toast', ['type' => 'success', 'message' => $record->is_late ? __('Checked in (late).') : __('Checked in.')]);

        return back();
    }

    public function checkOut(Request $request, CheckOut $checkOut): RedirectResponse
    {
        Gate::authorize('attendance.check_in');

        $checkOut->handle($this->employeeId($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Checked out.')]);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function employeeId(Request $request): int
    {
        $id = $this->org->employeeIdForUser($this->user($request)->id);

        if ($id === null) {
            throw new DomainException(__('Your account has no employee profile.'), 'attendance');
        }

        return $id;
    }

    private function month(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('!Y-m', $value) ?: now()->toImmutable()->startOfMonth();
        }

        return now()->toImmutable()->startOfMonth();
    }
}
