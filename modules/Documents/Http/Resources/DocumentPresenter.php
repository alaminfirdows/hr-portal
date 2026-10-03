<?php

namespace Modules\Documents\Http\Resources;

use Illuminate\Support\Facades\Gate;
use Modules\Documents\Models\Document;
use Modules\Identity\Models\User;

final class DocumentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Document $d, User $viewer): array
    {
        $days = $d->expires_on === null ? null : (int) now()->startOfDay()->diffInDays($d->expires_on->startOfDay(), false);

        return [
            'id' => $d->id,
            'title' => $d->title,
            'description' => $d->description,
            'category' => $d->category,
            'tags' => $d->tags ?? [],
            'kind' => $d->kind->value,
            'visibility' => $d->visibility->value,
            'visibility_label' => $d->visibility->label(),
            'visibility_color' => $d->visibility->color(),
            'folder_id' => $d->folder_id,
            'folder' => $d->folder?->name,
            'file_name' => $d->file_name,
            'size' => $d->size,
            'url' => $d->isFile() ? null : $d->url,
            'expires_on' => $d->expires_on?->toDateString(),
            'expires_in_days' => $days,
            'uploader' => $d->uploader?->name,
            'employee' => $d->employee?->full_name,
            'created_at' => $d->created_at->toDateString(),
            'can' => [
                'update' => Gate::forUser($viewer)->allows('update', $d),
                'delete' => Gate::forUser($viewer)->allows('delete', $d),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Document $d, User $viewer): array
    {
        $d->loadMissing(['folder', 'uploader', 'employee', 'department', 'team', 'sharedWith']);

        return [
            ...self::summary($d, $viewer),
            'mime_type' => $d->mime_type,
            'department' => $d->department?->name,
            'team' => $d->team?->name,
            'project_id' => $d->project_id,
            'department_id' => $d->department_id,
            'team_id' => $d->team_id,
            'employee_id' => $d->employee_id,
            'shared_with' => $d->sharedWith->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->values()->all(),
            'can' => [
                ...self::summary($d, $viewer)['can'],
                'share' => Gate::forUser($viewer)->allows('share', $d),
            ],
        ];
    }
}
