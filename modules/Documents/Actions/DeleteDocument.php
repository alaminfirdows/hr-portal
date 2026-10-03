<?php

namespace Modules\Documents\Actions;

use Illuminate\Support\Facades\Storage;
use Modules\Documents\Events\DocumentChanged;
use Modules\Documents\Models\Document;
use Modules\Identity\Models\User;

final class DeleteDocument
{
    public function handle(Document $document, User $actor): void
    {
        if ($document->file_path !== null) {
            Storage::disk((string) config('filesystems.default'))->delete($document->file_path);
        }

        $document->delete();

        DocumentChanged::dispatch($document, 'deleted', $actor->id, $document->only(['title', 'visibility']));
    }
}
