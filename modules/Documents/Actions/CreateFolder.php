<?php

namespace Modules\Documents\Actions;

use Modules\Documents\Models\Folder;

final class CreateFolder
{
    public function handle(string $name, ?int $parentId = null): Folder
    {
        return Folder::query()->create(['name' => $name, 'parent_id' => $parentId]);
    }
}
