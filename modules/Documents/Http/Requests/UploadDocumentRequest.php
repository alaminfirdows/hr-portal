<?php

namespace Modules\Documents\Http\Requests;

final class UploadDocumentRequest extends DocumentRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'file' => ['required', 'file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::MIMES)],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => __('The file must not be larger than 20 MB.'),
            'file.mimes' => __('This file type is not allowed.'),
        ];
    }
}
