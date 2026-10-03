<?php

namespace Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Models\Document;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentDownloadController extends Controller
{
    /**
     * Streams the stored file through the app; the private disk is never exposed by URL.
     */
    public function __invoke(Document $document): StreamedResponse
    {
        Gate::authorize('download', $document);
        abort_unless($document->file_path !== null && $document->file_name !== null, 404);

        $disk = Storage::disk((string) config('filesystems.default'));
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->file_name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
