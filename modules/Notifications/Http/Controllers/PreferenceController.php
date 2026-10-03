<?php

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Models\User;
use Modules\Notifications\Models\NotificationPreference;
use Modules\Shared\Support\ModuleRegistry;

final class PreferenceController extends Controller
{
    public function edit(Request $request, ModuleRegistry $registry): Response
    {
        /** @var User $user */
        $user = $request->user();
        $saved = NotificationPreference::query()->where('user_id', $user->id)->get()->keyBy('type');

        return Inertia::render('settings/notifications', [
            'types' => collect($registry->notificationTypes())->map(fn (array $meta, string $type) => [
                'type' => $type,
                'module' => $meta['module'],
                'description' => $meta['description'],
                'in_app' => $saved[$type]->in_app ?? true,
                'email' => $saved[$type]->email ?? true,
            ])->values(),
        ]);
    }

    public function update(Request $request, ModuleRegistry $registry): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', 'string', Rule::in(array_keys($registry->notificationTypes()))],
            'preferences.*.in_app' => ['required', 'boolean'],
            'preferences.*.email' => ['required', 'boolean'],
        ]);

        foreach ($data['preferences'] as $preference) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'type' => $preference['type']],
                ['in_app' => $preference['in_app'], 'email' => $preference['email']],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification preferences saved.')]);

        return back();
    }
}
