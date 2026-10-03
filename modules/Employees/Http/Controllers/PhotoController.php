<?php

namespace Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Modules\Employees\Actions\UpdateEmployeePhoto;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PhotoController extends Controller
{
    /**
     * Photos are private; any signed-in user who can see the directory may load them.
     */
    public function show(Request $request, Employee $employee): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($employee->photo_path && (Gate::allows('directory.view') || $employee->user_id === $user->id), 404);

        return Storage::disk(config('filesystems.default'))->response($employee->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function update(Request $request, Employee $employee, UpdateEmployeePhoto $update): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($employee->user_id !== $user->id) {
            Gate::authorize('employees.update', $employee);
        }

        $request->validate(['photo' => ['nullable', 'image', 'max:2048', 'dimensions:max_width=4000,max_height=4000']]);

        $update->handle($employee, $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo updated.')]);

        return back();
    }
}
