<?php

namespace Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Employees\Actions\UpdateOwnProfile;
use Modules\Employees\Http\Requests\OwnProfileRequest;
use Modules\Employees\Http\Resources\EmployeePresenter;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;

final class OwnProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $employee = $this->employee($request);

        return Inertia::render('employees/profile', [
            'employee' => $employee ? EmployeePresenter::full($employee->load([
                'department:id,name', 'team:id,name', 'position:id,title', 'location:id,name', 'manager:id,first_name,last_name',
            ])) : null,
        ]);
    }

    public function update(OwnProfileRequest $request, UpdateOwnProfile $update): RedirectResponse
    {
        $employee = $this->employee($request);
        abort_if($employee === null, 404);

        $update->handle($employee, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile saved.')]);

        return back();
    }

    private function employee(Request $request): ?Employee
    {
        /** @var User $user */
        $user = $request->user();

        return Employee::query()->where('user_id', $user->id)->first();
    }
}
