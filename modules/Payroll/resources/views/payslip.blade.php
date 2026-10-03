<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $payslip->run->period }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f3f3f3; }
        .num { text-align: right; }
        .total td { font-weight: bold; border-top: 2px solid #111; }
        .flag { margin-top: 12px; padding: 6px 8px; border: 1px solid #c00; color: #c00; }
    </style>
</head>
<body>
    <h1>{{ $company }}</h1>
    <div class="muted">Payslip for {{ $payslip->run->label() }}</div>

    <table>
        <tr><th>Employee</th><td>{{ $payslip->employee->full_name }}</td><th>Code</th><td>{{ $payslip->employee->employee_code }}</td></tr>
        <tr><th>Working days</th><td>{{ $payslip->working_days }}</td><th>Paid days</th><td>{{ rtrim(rtrim((string) $payslip->paid_days, '0'), '.') }}</td></tr>
    </table>

    @foreach (['earning' => 'Earnings', 'deduction' => 'Deductions'] as $type => $heading)
        <table>
            <thead><tr><th>{{ $heading }}</th><th class="num">Amount ({{ $currency }})</th></tr></thead>
            <tbody>
                @foreach ($payslip->lines->filter(fn ($l) => $l->type->value === $type) as $line)
                    <tr><td>{{ $line->name }}</td><td class="num">{{ $money($line->amount) }}</td></tr>
                @endforeach
                <tr class="total">
                    <td>Total {{ strtolower($heading) }}</td>
                    <td class="num">{{ $money($type === 'earning' ? $payslip->gross : $payslip->total_deductions) }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <table>
        <tr class="total"><td>Net pay</td><td class="num">{{ $currency }} {{ $money($payslip->net) }}</td></tr>
    </table>

    @if ($payslip->net_clamped)
        <div class="flag">Deductions exceeded earnings; net pay was set to zero.</div>
    @endif
</body>
</html>
