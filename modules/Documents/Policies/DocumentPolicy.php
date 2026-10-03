<?php

namespace Modules\Documents\Policies;

use Illuminate\Support\Facades\Gate;
use Modules\Documents\Enums\DocumentKind;
use Modules\Documents\Models\Document;
use Modules\Identity\Models\User;

final class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return Gate::forUser($user)->allows('documents.view')
            && Document::query()->visibleTo($user)->whereKey($document->getKey())->exists();
    }

    /**
     * Only stored files can be downloaded; links are opened at their URL.
     */
    public function download(User $user, Document $document): bool
    {
        return $document->kind === DocumentKind::File && $this->view($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    public function share(User $user, Document $document): bool
    {
        return $this->owns($user, $document);
    }

    private function owns(User $user, Document $document): bool
    {
        $gate = Gate::forUser($user);

        return $gate->allows('documents.manage')
            || ($document->uploaded_by === $user->id && $gate->allows('documents.upload'));
    }
}
