<?php

namespace Modules\Employees\Widgets;

use Carbon\CarbonImmutable;
use Modules\AccessControl\Services\Access;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;

/**
 * Birthdays in the next 30 days (day and month only; the year of birth is never shown).
 */
final class UpcomingBirthdaysWidget implements DashboardWidget
{
    private const DAYS = 30;

    public function __construct(private readonly Access $access) {}

    public function key(): string
    {
        return 'employees.upcoming_birthdays';
    }

    public function title(): string
    {
        return __('Upcoming birthdays');
    }

    public function type(): string
    {
        return 'list';
    }

    public function size(): int
    {
        return 1;
    }

    public function order(): int
    {
        return 70;
    }

    public function visible(User $user): bool
    {
        return $this->access->can($user, 'directory.view');
    }

    public function data(User $user): array
    {
        $today = CarbonImmutable::today();
        $rows = [];

        $employees = Employee::query()
            ->whereNotNull('date_of_birth')
            ->whereNotIn('status', ['terminated', 'invited'])
            ->get(['id', 'first_name', 'last_name', 'date_of_birth']);

        foreach ($employees as $employee) {
            $dob = $employee->date_of_birth;

            if ($dob === null) {
                continue;
            }

            $next = $dob->setYear($today->year);

            if ($next->lessThan($today)) {
                $next = $dob->setYear($today->year + 1);
            }

            $in = (int) $today->diffInDays($next);

            if ($in <= self::DAYS) {
                $rows[] = ['employee' => $employee, 'next' => $next, 'in' => $in];
            }
        }

        usort($rows, fn (array $a, array $b) => $a['in'] <=> $b['in']);

        return [
            'items' => array_map(fn (array $row) => [
                'id' => $row['employee']->id,
                'title' => $row['employee']->full_name,
                'subtitle' => $row['in'] === 0 ? __('Today') : $row['next']->format('M j'),
                'meta' => null,
                'href' => route('employees.directory.show', $row['employee']),
            ], array_slice($rows, 0, 5)),
            'empty' => __('No birthdays in the next 30 days.'),
        ];
    }
}
