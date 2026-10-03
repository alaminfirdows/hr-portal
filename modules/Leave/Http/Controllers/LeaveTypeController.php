<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Http\Requests\LeaveTypeRequest;
use Modules\Leave\Http\Resources\LeavePresenter;
use Modules\Leave\Models\LeaveType;
use Modules\Shared\Exceptions\DomainException;

final class LeaveTypeController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('leave.manage');

        return Inertia::render('leave/types/index', [
            'types' => LeaveType::query()->orderBy('name')->get()->map(fn (LeaveType $t) => LeavePresenter::type($t)),
            'options' => [
                'accrual_methods' => AccrualMethod::options(),
                'colors' => collect(['gray', 'green', 'blue', 'amber', 'red', 'purple'])
                    ->map(fn (string $c) => ['value' => $c, 'label' => ucfirst($c)])->all(),
            ],
        ]);
    }

    public function store(LeaveTypeRequest $request): RedirectResponse
    {
        LeaveType::query()->create($request->attributesForType());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave type created.')]);

        return back();
    }

    public function update(LeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($request->attributesForType());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave type updated.')]);

        return back();
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        Gate::authorize('leave.manage');

        if ($leaveType->requests()->exists()) {
            throw new DomainException(__('This leave type has requests. Deactivate it instead of deleting.'), 'leave_type');
        }

        $leaveType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave type deleted.')]);

        return back();
    }
}
