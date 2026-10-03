<?php

namespace Modules\Documents\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Modules\Documents\Models\Document;

final class UpdateDocumentRequest extends DocumentRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && Gate::allows('update', $document);
    }

    public function rules(): array
    {
        $document = $this->route('document');

        return [
            ...parent::rules(),
            // The link target may be edited; stored files keep their file.
            'url' => [$document instanceof Document && ! $document->isFile() ? 'required' : 'prohibited', 'url:http,https', 'max:2048'],
        ];
    }
}
