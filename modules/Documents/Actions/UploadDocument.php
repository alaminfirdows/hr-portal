<?php

namespace Modules\Documents\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Enums\DocumentKind;
use Modules\Documents\Events\DocumentChanged;
use Modules\Documents\Models\Document;
use Modules\Documents\Services\VisibilityTargets;
use Modules\Identity\Models\User;

/**
 * Stores a file on the private disk and creates its document record.
 */
final class UploadDocument
{
    public function __construct(private readonly VisibilityTargets $targets, private readonly ShareDocument $share) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data, UploadedFile $file): Document
    {
        $data = $this->targets->apply($actor, $data);
        $userIds = $this->userIds($data);
        unset($data['user_ids']);

        $disk = Storage::disk((string) config('filesystems.default'));
        $path = $disk->putFile('documents/'.now()->format('Y/m'), $file);

        if ($path === false) {
            throw new \RuntimeException('Unable to store the uploaded document.');
        }

        try {
            return DB::transaction(function () use ($actor, $data, $file, $path, $userIds): Document {
                $document = Document::query()->create([
                    ...$data,
                    'kind' => DocumentKind::File,
                    'uploaded_by' => $actor->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);

                if ($userIds !== []) {
                    $this->share->handle($document, $actor, $userIds);
                }

                DocumentChanged::dispatch($document, 'created', $actor->id, [], $document->only(['title', 'visibility', 'kind']));

                return $document;
            });
        } catch (\Throwable $e) {
            $disk->delete($path);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function userIds(array $data): array
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', (array) ($data['user_ids'] ?? []));

        return $ids;
    }
}
