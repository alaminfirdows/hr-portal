<?php

namespace Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Modules\Documents\Actions\CreateFolder;

final class FolderController extends Controller
{
    public function store(Request $request, CreateFolder $create): RedirectResponse
    {
        Gate::authorize('documents.manage');

        /** @var array{name: string, parent_id?: int|null} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        $create->handle($data['name'], $data['parent_id'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder created.')]);

        return back();
    }
}
