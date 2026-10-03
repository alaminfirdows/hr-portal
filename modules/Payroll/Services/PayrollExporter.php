<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of a run (one row per payslip). Streamed, never stored.
 */
final class PayrollExporter
{
    public function download(PayrollRun $run): StreamedResponse
    {
        return response()->streamDownload(function () use ($run): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Employee code', 'Employee', 'Period', 'Working days', 'Paid days', 'Basic', 'Gross', 'Deductions', 'Net', 'Net clamped to zero'], ',', '"', '');

            $run->payslips()->with('employee')->get()
                ->sortBy(fn (Payslip $p) => $p->employee->employee_code)
                ->each(function (Payslip $p) use ($out, $run): void {
                    fputcsv($out, [
                        $this->safe((string) $p->employee->employee_code),
                        $this->safe($p->employee->full_name),
                        $run->period,
                        $p->working_days,
                        $p->paid_days,
                        $this->amount($p->basic),
                        $this->amount($p->gross),
                        $this->amount($p->total_deductions),
                        $this->amount($p->net),
                        $p->net_clamped ? 'yes' : 'no',
                    ], ',', '"', '');
                });

            fclose($out);
        }, "payroll-{$run->period}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function amount(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    /**
     * Neutralizes spreadsheet formula injection in text cells.
     */
    private function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
