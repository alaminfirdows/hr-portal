<?php

namespace Modules\Documents\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Documents\Events\DocumentChanged;
use Modules\Documents\Models\Document;
use Modules\Documents\Notifications\DocumentShared;
use Modules\Identity\Models\User;

/**
 * Sets the explicit viewers of a document; only newly added people are notified.
 */
final class ShareDocument
{
    /**
     * @param  list<int>  $userIds
     */
    public function handle(Document $document, User $actor, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter($userIds, fn (int $id) => $id !== $document->uploaded_by)));

        $added = DB::transaction(function () use ($document, $actor, $userIds): array {
            $existing = $document->sharedWith()->pluck('users.id')->all();
            $added = array_values(array_diff($userIds, $existing));
            $removed = array_values(array_diff($existing, $userIds));

            $document->sharedWith()->detach($removed);
            $document->sharedWith()->attach(array_fill_keys($added, ['shared_by' => $actor->id]));

            return $added;
        });

        if ($added === []) {
            return;
        }

        User::query()->whereIn('id', $added)->get()
            ->each(fn (User $user) => $user->notify(new DocumentShared($document, $actor->name)));

        DocumentChanged::dispatch($document, 'shared', $actor->id, [], ['user_ids' => $added]);
    }
}
