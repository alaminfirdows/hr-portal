<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Support\ModuleRegistry;
use Throwable;

/**
 * Role-aware dashboard: every module widget decides its own visibility for the viewer.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, ModuleRegistry $registry): Response
    {
        /** @var User $user */
        $user = $request->user();

        $widgets = collect($registry->widgets())
            ->map(fn (string $class) => app($class))
            ->filter(fn (DashboardWidget $w) => $w->visible($user))
            ->sortBy(fn (DashboardWidget $w) => $w->order())
            ->map(function (DashboardWidget $w) use ($user): ?array {
                try {
                    return [
                        'key' => $w->key(),
                        'title' => $w->title(),
                        'type' => $w->type(),
                        'size' => $w->size(),
                        'data' => $w->data($user),
                    ];
                } catch (Throwable $e) {
                    report($e);

                    return null; // one broken widget must not break the dashboard
                }
            })
            ->filter()
            ->values();

        return Inertia::render('dashboard/index', [
            'greeting' => $this->greeting(),
            'widgets' => $widgets,
        ]);
    }

    private function greeting(): string
    {
        $hour = now()->hour;

        return match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };
    }
}
