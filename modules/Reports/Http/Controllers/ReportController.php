<?php

namespace Modules\Reports\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Models\User;
use Modules\Reports\Contracts\Report;
use Modules\Reports\Services\CsvExporter;
use Modules\Reports\Services\ReportFilters;
use Modules\Reports\Services\ReportRegistry;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController
{
    /** Rows rendered in the page; CSV export is never truncated. */
    private const PAGE_LIMIT = 500;

    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportFilters $filters,
        private readonly CsvExporter $csv,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');

        $user = $this->user($request);

        $groups = collect($this->registry->all())
            ->filter(fn (Report $r) => $r->accessibleBy($user))
            ->groupBy(fn (Report $r) => $r->group())
            ->map(fn ($reports, string $group) => [
                'group' => $group,
                'reports' => $reports->map(fn (Report $r) => [
                    'key' => $r->key(),
                    'title' => $r->title(),
                    'description' => $r->description(),
                    'href' => route('reports.show', $r->key()),
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('reports/index', ['groups' => $groups]);
    }

    public function show(Request $request, string $key): Response
    {
        $report = $this->authorizedReport($key);
        $filters = $this->resolveFilters($request, $report);
        $rows = collect($report->query($filters, $this->user($request)));

        $declared = Arr::only($filters, $this->filters->keys($report));

        return Inertia::render('reports/show', [
            'report' => [
                'key' => $report->key(),
                'title' => $report->title(),
                'description' => $report->description(),
                'group' => $report->group(),
                'filters' => $report->filters(),
                'columns' => $report->columns(),
                'chart' => $report->chart(),
            ],
            'values' => $declared,
            'departments' => in_array('department_id', array_keys($declared), true) ? $this->filters->departmentOptions() : [],
            'rows' => $rows->take(self::PAGE_LIMIT)->all(),
            'total_rows' => $rows->count(),
            'export_url' => route('reports.export', ['key' => $report->key(), ...$declared]),
        ]);
    }

    public function export(Request $request, string $key): StreamedResponse
    {
        $report = $this->authorizedReport($key);
        $rows = $report->query($this->resolveFilters($request, $report), $this->user($request));

        return $this->csv->stream($report, $rows);
    }

    private function authorizedReport(string $key): Report
    {
        Gate::authorize('reports.view');

        $report = $this->registry->find($key) ?? abort(404);

        $user = request()->user();
        abort_unless($user instanceof User && $report->accessibleBy($user), 403);

        return $report;
    }

    /**
     * @return array{from: string, to: string, department_id: int|null, year: int}
     */
    private function resolveFilters(Request $request, Report $report): array
    {
        /** @var array<string, mixed> $input */
        $input = Arr::only($request->query(), $this->filters->keys($report));

        return $this->filters->resolve($input);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
