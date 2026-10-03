<?php

namespace Modules\Employees\Services;

use BackedEnum;
use Carbon\CarbonInterface;
use Modules\Employees\Models\Employee;
use Modules\Employees\Models\EmployeeHistory;

/**
 * Writes one employee_histories row per tracked field change.
 */
final class HistoryRecorder
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(Employee $employee, array $before, array $after, ?int $actorId, ?string $reason = null, ?CarbonInterface $effectiveOn = null): void
    {
        foreach ($after as $field => $new) {
            if (! in_array($field, Employee::TRACKED, true)) {
                continue;
            }

            $old = $this->normalize($before[$field] ?? null);
            $new = $this->normalize($new);

            if ($old === $new) {
                continue;
            }

            EmployeeHistory::query()->create([
                'employee_id' => $employee->id,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'reason' => $reason,
                'effective_on' => ($effectiveOn ?? now())->toDateString(),
                'changed_by' => $actorId,
                'created_at' => now(),
            ]);
        }
    }

    private function normalize(mixed $value): ?string
    {
        return match (true) {
            $value === null, $value === '' => null,
            $value instanceof BackedEnum => (string) $value->value,
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }
}
