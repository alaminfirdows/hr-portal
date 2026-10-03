<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Actions\RevokeOtherSessions;
use Modules\Identity\Models\User;

final class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        $currentId = $request->session()->getId();

        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')
                ->where('user_id', $request->user()?->getAuthIdentifier())
                ->orderByDesc('last_activity')
                ->get()
                ->map(fn (object $s) => [
                    'id' => substr((string) $s->id, 0, 8),
                    'ip_address' => $s->ip_address,
                    'agent' => self::describeAgent((string) $s->user_agent),
                    'is_current' => $s->id === $currentId,
                    'last_active' => Carbon::createFromTimestamp((int) $s->last_activity)->diffForHumans(),
                ])->all()
            : [];

        return Inertia::render('settings/sessions', [
            'sessions' => $sessions,
            'supported' => config('session.driver') === 'database',
        ]);
    }

    public function destroy(Request $request, RevokeOtherSessions $revoke): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $count = $revoke->handle($user, $request->session()->getId());

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count other session signed out.|:count other sessions signed out.', $count)]);

        return back();
    }

    public static function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };

        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return "{$browser} on {$os}";
    }
}
