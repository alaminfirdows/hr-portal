<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Models\User;
use Modules\Settings\Events\SettingsUpdated;
use Modules\Settings\Services\SettingsRepository;

final class OrganizationSettingsController extends Controller
{
    public function edit(SettingsRepository $settings): Response
    {
        Gate::authorize('settings.manage');

        return Inertia::render('settings/organization', [
            'settings' => $settings->organization()->toArray(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request, SettingsRepository $settings): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'leave_year_start_month' => ['required', 'integer', 'between:1,12'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
            'date_format' => ['required', 'string', 'max:20'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['working_days'] = array_values(array_map('intval', $data['working_days']));
        sort($data['working_days']);

        $before = $settings->organization()->toArray();
        $settings->setMany($data);

        /** @var User $by */
        $by = $request->user();
        SettingsUpdated::dispatch($before, $settings->organization()->toArray(), $by);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }
}
