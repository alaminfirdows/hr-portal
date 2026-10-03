<?php

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Models\User;

final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = $request->string('filter')->toString() === 'unread' ? 'unread' : 'all';

        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        return Inertia::render('notifications/index', [
            'items' => $query->paginate(20)->withQueryString()->through(fn (DatabaseNotification $n) => self::present($n)),
            'filter' => $filter,
        ]);
    }

    /**
     * Mark read and follow the notification's link.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $this->find($request, $id);
        $notification->markAsRead();

        /** @var string|null $url */
        $url = $notification->data['url'] ?? null;

        return $url ? redirect()->to($url) : to_route('notifications.index');
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $this->find($request, $id)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All notifications marked as read.')]);

        return back();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->find($request, $id)->delete();

        return back();
    }

    private function find(Request $request, string $id): DatabaseNotification
    {
        /** @var User $user */
        $user = $request->user();

        /** @var DatabaseNotification */
        return $user->notifications()->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->data['type'] ?? $n->type,
            'title' => $n->data['title'] ?? '',
            'body' => $n->data['body'] ?? '',
            'url' => $n->data['url'] ?? null,
            'icon' => $n->data['icon'] ?? 'bell',
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
