<?php

namespace Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Models\AuditLog;
use Modules\Identity\Models\User;
use Modules\Shared\Support\Filters;

final class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('audit.view');

        $filters = Filters::only($request, ['search', 'action', 'actor_id', 'from', 'to']);

        return Inertia::render('audit/index', [
            'logs' => AuditLog::query()
                ->with('actor:id,name')
                ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('description', 'like', "%{$v}%"))
                ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', "{$v}%"))
                ->when($filters['actor_id'] ?? null, fn ($q, $v) => $q->where('actor_id', $v))
                ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
                ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<', now()->parse($v)->addDay()))
                ->latest('created_at')
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'actor' => $log->actor?->name,
                    'subject' => $log->subject_type ? class_basename($log->subject_type)." #{$log->subject_id}" : null,
                    'changes' => $log->changes(),
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            'filters' => $filters,
            'modules' => AuditLog::query()->selectRaw('distinct action')->pluck('action')
                ->map(fn (string $a) => explode('.', $a)[0])->unique()->sort()->values(),
            'actors' => User::query()->whereIn('id', AuditLog::query()->select('actor_id')->whereNotNull('actor_id')->distinct())
                ->orderBy('name')->get(['id', 'name'])->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name]),
        ]);
    }
}
