<?php

namespace Modules\Employees\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Employees\Models\Employee;
use Throwable;

final class UpdateEmployeePhoto
{
    public function handle(Employee $employee, ?UploadedFile $photo): Employee
    {
        $disk = Storage::disk(config('filesystems.default'));
        $old = $employee->photo_path;

        // Store the new file first so a failed upload never loses the current photo.
        $new = $photo?->store("employees/{$employee->id}/photo", ['disk' => config('filesystems.default'), 'visibility' => 'private']) ?: null;

        try {
            $employee->forceFill(['photo_path' => $new])->save();
        } catch (Throwable $e) {
            if ($new !== null) {
                $disk->delete($new);
            }

            throw $e;
        }

        if ($old && $old !== $new) {
            $disk->delete($old);
        }

        return $employee;
    }
}
