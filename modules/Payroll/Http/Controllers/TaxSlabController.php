<?php

namespace Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Actions\DeletePayrollConfig;
use Modules\Payroll\Actions\SaveTaxSlab;
use Modules\Payroll\Http\Resources\PayrollPresenter;
use Modules\Payroll\Models\TaxSlab;
use Modules\Shared\ValueObjects\Money;

final class TaxSlabController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('payroll.manage');

        return Inertia::render('payroll/tax-slabs/index', [
            'slabs' => TaxSlab::query()->orderBy('min_amount')->get()->map(fn (TaxSlab $s) => [
                'id' => $s->id,
                'min_amount' => $s->min_amount,
                'max_amount' => $s->max_amount,
                'min_input' => PayrollPresenter::decimal($s->min_amount),
                'max_input' => PayrollPresenter::decimal($s->max_amount),
                'rate' => PayrollPresenter::decimal($s->rate_bp),
            ]),
            'currency' => PayrollPresenter::currency(),
        ]);
    }

    public function store(Request $request, SaveTaxSlab $save): RedirectResponse
    {
        return $this->persist($request, new TaxSlab, $save);
    }

    public function update(Request $request, TaxSlab $taxSlab, SaveTaxSlab $save): RedirectResponse
    {
        return $this->persist($request, $taxSlab, $save);
    }

    public function destroy(Request $request, TaxSlab $taxSlab, DeletePayrollConfig $delete): RedirectResponse
    {
        Gate::authorize('payroll.manage');

        $delete->handle($taxSlab, "#{$taxSlab->id}", $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax slab deleted.')]);

        return back();
    }

    private function persist(Request $request, TaxSlab $slab, SaveTaxSlab $save): RedirectResponse
    {
        Gate::authorize('payroll.manage');

        $data = $request->validate([
            'min_amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $save->handle($slab, [
            'min_amount' => Money::fromDecimal($data['min_amount'])->minor,
            'max_amount' => isset($data['max_amount']) ? Money::fromDecimal($data['max_amount'])->minor : null,
            'rate_bp' => Money::fromDecimal($data['rate'])->minor,
        ], $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax slab saved.')]);

        return back();
    }
}
