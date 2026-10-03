<?php

namespace Modules\Reports\Contracts;

use Modules\Identity\Models\User;

/**
 * A read-only report. Reports may read other modules' tables (documented exception).
 */
interface Report
{
    /**
     * URL-safe unique key, e.g. "hr.headcount".
     */
    public function key(): string;

    public function title(): string;

    public function description(): string;

    /**
     * Catalog group, e.g. "HR".
     */
    public function group(): string;

    /**
     * Permission required on top of reports.view.
     */
    public function permission(): string;

    /**
     * Whether the user may open this report: reports.view plus {@see permission()}, at the scope the report needs.
     */
    public function accessibleBy(User $user): bool;

    /**
     * @return list<array{key: string, label: string, type: 'date'|'department'|'year'}>
     */
    public function filters(): array;

    /**
     * @return list<array{key: string, label: string, type: 'text'|'number'|'decimal'|'money'|'percent'}>
     */
    public function columns(): array;

    /**
     * Rows keyed by column key. Money columns hold decimal major units.
     *
     * @param  array{from: string, to: string, department_id: int|null, year: int}  $filters
     * @return array<int, array<string, scalar|null>>
     */
    public function query(array $filters, User $user): array;

    /**
     * Optional bar chart: which columns supply bar label and value.
     *
     * @return array{label: string, value: string}|null
     */
    public function chart(): ?array;
}
