<?php

namespace Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Announcements\Actions\CreateAnnouncement;
use Modules\Announcements\Actions\DeleteAnnouncement;
use Modules\Announcements\Actions\MarkAnnouncementRead;
use Modules\Announcements\Actions\UpdateAnnouncement;
use Modules\Announcements\Enums\Audience;
use Modules\Announcements\Enums\Priority;
use Modules\Announcements\Http\Requests\AnnouncementRequest;
use Modules\Announcements\Models\Announcement;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;

final class AnnouncementController extends Controller
{
    /**
     * The feed: live announcements addressed to the viewer.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('announcements.view');

        /** @var User $user */
        $user = $request->user();

        $base = Announcement::query()->published()->visibleTo($user);

        $announcements = (clone $base)
            ->withReadState($user)
            ->orderByDesc('publish_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (Announcement $a) => $this->summary($a));

        $unread = (clone $base)->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))->count();

        return Inertia::render('announcements/index', [
            'announcements' => $announcements,
            'unread' => $unread,
            'can' => ['manage' => Gate::allows('announcements.manage')],
        ]);
    }

    public function show(Request $request, Announcement $announcement, MarkAnnouncementRead $markRead): Response
    {
        /** @var User $user */
        $user = $request->user();
        $manager = Gate::allows('announcements.manage');

        $addressed = Gate::allows('announcements.view')
            && $announcement->isLive()
            && Announcement::query()->visibleTo($user)->whereKey($announcement->id)->exists();

        abort_unless($addressed || $manager, 403);

        // HR previewing a scheduled or expired announcement is not a "read".
        if ($addressed) {
            $markRead->handle($announcement, $user);
        }

        return Inertia::render('announcements/show', [
            'announcement' => [
                ...$this->summary($announcement),
                'body' => $announcement->body,
                'author' => $announcement->author?->name,
            ],
            'can' => ['manage' => $manager],
        ]);
    }

    /**
     * HR admin list: every announcement including scheduled and expired.
     */
    public function manage(): Response
    {
        Gate::authorize('announcements.manage');

        return Inertia::render('announcements/manage', [
            'announcements' => Announcement::query()
                ->with(['author:id,name', 'targets'])
                ->withCount('reads')
                ->orderByDesc('publish_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->through(fn (Announcement $a) => [
                    ...$this->summary($a),
                    'body' => $a->body,
                    'author' => $a->author?->name,
                    'target_ids' => $a->targets->pluck('target_id')->map(fn ($id) => (int) $id)->all(),
                    'publish_at_input' => $a->publish_at->format('Y-m-d\TH:i'),
                    'expires_at_input' => $a->expires_at?->format('Y-m-d\TH:i'),
                    'reads_count' => $a->reads_count ?? 0,
                ]),
            'options' => [
                'priorities' => Priority::options(),
                'audiences' => Audience::options(),
                'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map(fn (Department $d) => ['value' => $d->id, 'label' => $d->name])->all(),
                'teams' => Team::query()->orderBy('name')->get(['id', 'name'])->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
                'employees' => Employee::query()->employed()->orderBy('first_name')->get(['id', 'first_name', 'last_name'])->map(fn (Employee $e) => ['value' => $e->id, 'label' => $e->full_name])->all(),
            ],
        ]);
    }

    public function store(AnnouncementRequest $request, CreateAnnouncement $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $create->handle($user, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement saved.')]);

        return back();
    }

    public function update(AnnouncementRequest $request, Announcement $announcement, UpdateAnnouncement $update): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $update->handle($announcement, $user, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement updated.')]);

        return back();
    }

    public function destroy(Request $request, Announcement $announcement, DeleteAnnouncement $delete): RedirectResponse
    {
        Gate::authorize('announcements.manage');

        /** @var User $user */
        $user = $request->user();
        $delete->handle($announcement, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'excerpt' => str($a->body)->stripTags()->limit(180)->toString(),
            'priority' => $a->priority->value,
            'priority_label' => $a->priority->label(),
            'priority_color' => $a->priority->color(),
            'audience' => $a->audience->value,
            'audience_label' => $a->audience->label(),
            'publish_at' => $a->publish_at->toIso8601String(),
            'expires_at' => $a->expires_at?->toIso8601String(),
            'status' => $a->publish_at->isFuture() ? 'scheduled' : ($a->expires_at !== null && $a->expires_at->isPast() ? 'expired' : 'live'),
            'is_read' => (bool) $a->is_read,
        ];
    }
}
