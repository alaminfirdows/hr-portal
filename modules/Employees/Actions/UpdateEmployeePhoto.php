<?php

namespace Modules\Employees\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Employees\Models\Employee;

final class UpdateEmployeePhoto
{
    public function handle(Employee $employee, ?UploadedFile $photo): Employee
    {
        $disk = Storage::disk(config('filesystems.default'));

        if ($employee->photo_path) {
            $disk->delete($employee->photo_path);
        }

        $employee->forceFill([
            'photo_path' => $photo?->store("employees/{$employee->id}/photo", ['disk' => config('filesystems.default'), 'visibility' => 'private']) ?: null,
        ])->save();

        return $employee;
    }
}
