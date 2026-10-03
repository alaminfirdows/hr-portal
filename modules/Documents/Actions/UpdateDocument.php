<?php

namespace Modules\Documents\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Documents\Events\DocumentChanged;
use Modules\Documents\Models\Document;
use Modules\Documents\Services\VisibilityTargets;
use Modules\Identity\Models\User;

final class UpdateDocument
{
    public function __construct(private readonly VisibilityTargets $targets, private readonly ShareDocument $share) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Document $document, User $actor, array $data): Document
    {
        $data = $this->targets->apply($actor, $data);
        /** @var list<int>|null $userIds */
        $userIds = array_key_exists('user_ids', $data) ? array_map('intval', (array) $data['user_ids']) : null;
        unset($data['user_ids']);

        return DB::transaction(function () use ($document, $actor, $data, $userIds): Document {
            $document->fill($data);

            if ($document->isDirty('expires_on')) {
                $document->notified_30_at = null;
                $document->notified_7_at = null;
            }

            $changes = $document->getDirty();
            $before = array_intersect_key($document->getOriginal(), $changes);
            $document->save();

            if ($userIds !== null) {
                $this->share->handle($document, $actor, $userIds);
            }

            if ($changes !== []) {
                DocumentChanged::dispatch($document, 'updated', $actor->id, $before, $changes);
            }

            return $document;
        });
    }
}
