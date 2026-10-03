<?php

namespace Modules\Payroll\Notifications;

use Modules\Payroll\Models\Payslip;
use Modules\Shared\Notifications\PortalNotification;

final class PayslipAvailable extends PortalNotification
{
    public function __construct(public readonly Payslip $payslip) {}

    public function type(): string
    {
        return 'payroll.payslip_available';
    }

    public function title(): string
    {
        return __('Your payslip is available');
    }

    public function body(): string
    {
        return __('Your payslip for :period is ready.', ['period' => $this->payslip->run->label()]);
    }

    public function url(): string
    {
        return route('payroll.payslips.show', $this->payslip);
    }

    public function icon(): string
    {
        return 'banknote';
    }
}
