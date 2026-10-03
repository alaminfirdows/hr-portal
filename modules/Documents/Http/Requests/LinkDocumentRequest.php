<?php

namespace Modules\Documents\Http\Requests;

final class LinkDocumentRequest extends DocumentRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'url' => ['required', 'url:http,https', 'max:2048'],
        ];
    }
}
