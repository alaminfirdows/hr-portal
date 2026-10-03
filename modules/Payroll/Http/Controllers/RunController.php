<?php

namespace Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Models\User;
use Modules\Payroll\Actions\ApprovePayrollRun;
use Modules\Payroll\Actions\CalculatePayrollRun;
use Modules\Payroll\Actions\CreatePayrollRun;
use Modules\Payroll\Actions\MarkReviewed;
use Modules\Payroll\Actions\ProcessPayrollRun;
use Modules\Payroll\Enums\PayrollRunStatus;
use Modules\Payroll\Http\Resources\PayrollPresenter;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Services\PayrollExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RunController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('payroll.manage');

        return Inertia::render('payroll/runs/index', [
            'runs' => PayrollRun::query()->with('creator:id,name')->orderByDesc('period')->get()
                ->map(fn (PayrollRun $r) => PayrollPresenter::run($r)),
            'currency' => PayrollPresenter::currency(),
            'defaultPeriod' => now()->subMonth()->format('Y-m'),
            'maxPeriod' => now()->format('Y-m'),
        ]);
    }

    public function store(Request $request, CreatePayrollRun $create): RedirectResponse
    {
        Gate::authorize('payroll.manage');

        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);

        /** @var User $user */
        $user = $request->user();
        $run = $create->handle($data['period'], $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll run created for :period.', ['period' => $run->period])]);

        return to_route('payroll.runs.show', $run);
    }

    public function show(Request $request, PayrollRun $run): Response
    {
        Gate::authorize('payroll.manage');

        /** @var User $user */
        $user = $request->user();
        $run->load('creator:id,name');

        return Inertia::render('payroll/runs/show', [
            'run' => PayrollPresenter::run($run),
            'payslips' => $run->payslips()->with(['employee', 'run'])->get()
                ->sortBy(fn (Payslip $p) => $p->employee->employee_code)->values()
                ->map(fn (Payslip $p) => PayrollPresenter::payslip($p)),
            'can' => [
                'calculate' => in_array($run->status, [PayrollRunStatus::Draft, PayrollRunStatus::Calculated, PayrollRunStatus::Reviewed], true),
                'review' => $run->status === PayrollRunStatus::Calculated,
                'approve' => $run->status === PayrollRunStatus::Reviewed && Gate::allows('payroll.approve') && $run->created_by !== $user->id,
                'process' => $run->status === PayrollRunStatus::Approved,
                'export' => $run->payslips()->exists(),
            ],
            'approvalBlocked' => $run->status === PayrollRunStatus::Reviewed && $run->created_by === $user->id,
            'currency' => PayrollPresenter::currency(),
        ]);
    }

    public function calculate(Request $request, PayrollRun $run, CalculatePayrollRun $calculate): RedirectResponse
    {
        Gate::authorize('payroll.manage');

        $calculate->handle($run, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll calculated for :count employees.', ['count' => $run->employee_count])]);

        return back();
    }

    public function review(Request $request, PayrollRun $run, MarkReviewed $review): RedirectResponse
    {
        Gate::authorize('payroll.manage');

        /** @var User $user */
        $user = $request->user();
        $review->handle($run, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Run marked as reviewed.')]);

        return back();
    }

    public function approve(Request $request, PayrollRun $run, ApprovePayrollRun $approve): RedirectResponse
    {
        Gate::authorize('payroll.approve');

        /** @var User $user */
        $user = $request->user();
        $approve->handle($run, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Run approved.')]);

        return back();
    }

    public function process(Request $request, PayrollRun $run, ProcessPayrollRun $process): RedirectResponse
    {
        Gate::authorize('payroll.manage');

        $process->handle($run, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payslips published and employees notified.')]);

        return back();
    }

    public function export(PayrollRun $run, PayrollExporter $exporter): StreamedResponse
    {
        Gate::authorize('payroll.manage');

        return $exporter->download($run);
    }
}
