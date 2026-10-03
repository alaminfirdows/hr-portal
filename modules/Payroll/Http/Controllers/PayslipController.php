<?php

namespace Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Employees\Models\Employee;
use Modules\Payroll\Http\Resources\PayrollPresenter;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Settings\Services\SettingsRepository;
use Modules\Shared\ValueObjects\Money;

final class PayslipController extends Controller
{
    /**
     * My published payslips.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('payroll.view');

        $employee = Employee::query()->where('user_id', $request->user()?->id)->first();

        $payslips = $employee === null ? collect() : Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('published_at')
            ->with(['employee', 'run'])
            ->get()
            ->sortByDesc(fn (Payslip $p) => $p->run->period)
            ->values();

        return Inertia::render('payroll/payslips/index', [
            'payslips' => $payslips->map(fn (Payslip $p) => PayrollPresenter::payslip($p)),
            'currency' => PayrollPresenter::currency(),
        ]);
    }

    public function show(Payslip $payslip): Response
    {
        $this->authorizeView($payslip);

        $payslip->load(['employee', 'run', 'lines']);

        return Inertia::render('payroll/payslips/show', [
            'payslip' => PayrollPresenter::payslip($payslip),
            'lines' => $payslip->lines->map(fn (PayslipLine $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'name' => $l->name,
                'type' => $l->type->value,
                'amount' => $l->amount,
            ]),
            'run' => ['id' => $payslip->run->id, 'status' => $payslip->run->status->value],
            'currency' => PayrollPresenter::currency(),
            'can' => ['run' => Gate::allows('payroll.manage')],
        ]);
    }

    public function pdf(Payslip $payslip): HttpResponse
    {
        $this->authorizeView($payslip);

        $payslip->load(['employee', 'run', 'lines']);
        $settings = app(SettingsRepository::class)->organization();

        return Pdf::loadView('payroll::payslip', [
            'payslip' => $payslip,
            'company' => $settings->companyName,
            'currency' => $settings->currency,
            'money' => fn (int $minor): string => Money::of($minor)->format(),
        ])->download("payslip-{$payslip->employee->employee_code}-{$payslip->run->period}.pdf");
    }

    /**
     * Own payslips need payroll.view (own); anyone with payroll.view over the employee may read it,
     * but unpublished payslips are visible to payroll managers only.
     */
    private function authorizeView(Payslip $payslip): void
    {
        Gate::authorize('payroll.view', $payslip->employee_id);

        if ($payslip->published_at === null && Gate::denies('payroll.manage')) {
            abort(404);
        }
    }
}
