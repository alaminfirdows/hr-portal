<?php

namespace Modules\Reports\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Reports\Contracts\Report;
use Throwable;

final class ReportFilters
{
    /**
     * Normalise raw input. Missing or invalid dates default to the current month; a reversed range is swapped.
     *
     * @param  array<string, mixed>  $input
     * @return array{from: string, to: string, department_id: int|null, year: int}
     */
    public function resolve(array $input): array
    {
        $from = $this->date($input['from'] ?? null);
        $to = $this->date($input['to'] ?? null);
        $from ??= $to?->startOfMonth() ?? now()->toImmutable()->startOfMonth();
        $to ??= $from->endOfMonth();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $department = $input['department_id'] ?? null;
        $year = $input['year'] ?? null;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'department_id' => is_numeric($department) && (int) $department > 0 ? (int) $department : null,
            'year' => is_numeric($year) && (int) $year >= 2000 && (int) $year <= 2100 ? (int) $year : (int) now()->format('Y'),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function departmentOptions(): array
    {
        return DB::table('departments')->orderBy('name')->get(['id', 'name'])
            ->map(fn (object $d) => ['value' => (int) $d->id, 'label' => (string) $d->name])
            ->values()
            ->all();
    }

    /**
     * Input keys the report declares; anything else in the query string is ignored.
     *
     * @return list<'from'|'to'|'department_id'|'year'>
     */
    public function keys(Report $report): array
    {
        $keys = [];

        foreach ($report->filters() as $filter) {
            $keys[] = match ($filter['type']) {
                'date' => $filter['key'] === 'to' ? 'to' : 'from',
                'department' => 'department_id',
                'year' => 'year',
            };
        }

        return $keys;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $date?->toDateString() === $value ? $date : null;
    }
}
