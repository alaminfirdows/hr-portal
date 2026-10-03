<?php

namespace Modules\Documents\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Documents\Enums\DocumentKind;
use Modules\Documents\Events\DocumentChanged;
use Modules\Documents\Models\Document;
use Modules\Documents\Services\VisibilityTargets;
use Modules\Identity\Models\User;

/**
 * Registers an external URL as a document. Nothing is stored.
 */
final class CreateLinkDocument
{
    public function __construct(private readonly VisibilityTargets $targets, private readonly ShareDocument $share) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data): Document
    {
        $data = $this->targets->apply($actor, $data);
        /** @var list<int> $userIds */
        $userIds = array_map('intval', (array) ($data['user_ids'] ?? []));
        unset($data['user_ids']);

        return DB::transaction(function () use ($actor, $data, $userIds): Document {
            $document = Document::query()->create([
                ...$data,
                'kind' => DocumentKind::Link,
                'uploaded_by' => $actor->id,
            ]);

            if ($userIds !== []) {
                $this->share->handle($document, $actor, $userIds);
            }

            DocumentChanged::dispatch($document, 'created', $actor->id, [], $document->only(['title', 'visibility', 'kind']));

            return $document;
        });
    }
}
