<?php

namespace Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Actions\DeletePayrollConfig;
use Modules\Payroll\Actions\SavePayComponent;
use Modules\Payroll\Enums\CalculationMethod;
use Modules\Payroll\Enums\ComponentType;
use Modules\Payroll\Http\Resources\PayrollPresenter;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Services\FormulaEvaluator;
use Modules\Shared\ValueObjects\Money;

final class ComponentController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('payroll.manage');

        return Inertia::render('payroll/components/index', [
            'components' => PayComponent::query()->orderBy('sort')->orderBy('id')->get()
                ->map(fn (PayComponent $c) => PayrollPresenter::component($c)),
            'options' => [
                'types' => ComponentType::options(),
                'calculations' => CalculationMethod::options(),
            ],
            'variables' => FormulaEvaluator::VARIABLES,
        ]);
    }

    public function store(Request $request, SavePayComponent $save): RedirectResponse
    {
        return $this->persist($request, new PayComponent, $save);
    }

    public function update(Request $request, PayComponent $component, SavePayComponent $save): RedirectResponse
    {
        return $this->persist($request, $component, $save);
    }

    public function destroy(Request $request, PayComponent $component, DeletePayrollConfig $delete): RedirectResponse
    {
        Gate::authorize('payroll.manage');

        $delete->handle($component, $component->name, $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Component deleted.')]);

        return back();
    }

    private function persist(Request $request, PayComponent $component, SavePayComponent $save): RedirectResponse
    {
        Gate::authorize('payroll.manage');

        $creating = ! $component->exists;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('pay_components', 'code')->ignore($creating ? null : $component)],
            'type' => ['required', Rule::enum(ComponentType::class)],
            'calculation' => ['required', Rule::enum(CalculationMethod::class)],
            'value' => [
                'nullable', 'numeric', 'min:0', 'max:9999999999',
                Rule::requiredIf(fn () => in_array($request->input('calculation'), ['fixed', 'percent_of_basic'], true)),
                Rule::when($request->input('calculation') === 'percent_of_basic', ['max:100']),
            ],
            'formula' => ['nullable', 'string', 'max:500', Rule::requiredIf(fn () => $request->input('calculation') === 'formula')],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $method = CalculationMethod::from($data['calculation']);
        $usesValue = in_array($method, [CalculationMethod::Fixed, CalculationMethod::PercentOfBasic], true);

        $save->handle($component, [
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'type' => $data['type'],
            'calculation' => $data['calculation'],
            // Fixed: minor units. Percent: basis points. Both are the decimal input times 100.
            'value' => $usesValue ? Money::fromDecimal($data['value'])->minor : null,
            'formula' => $method === CalculationMethod::Formula ? $data['formula'] : null,
            'is_taxable' => $request->boolean('is_taxable'),
            'applies_to_all' => $request->boolean('applies_to_all', true),
            'is_active' => $request->boolean('is_active', true),
            'sort' => (int) ($data['sort'] ?? 100),
        ], $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => $creating ? __('Component created.') : __('Component updated.')]);

        return back();
    }
}
