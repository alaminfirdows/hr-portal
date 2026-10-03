<?php

namespace Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Documents\Actions\CreateLinkDocument;
use Modules\Documents\Actions\DeleteDocument;
use Modules\Documents\Actions\ShareDocument;
use Modules\Documents\Actions\UpdateDocument;
use Modules\Documents\Actions\UploadDocument;
use Modules\Documents\Enums\DocumentVisibility;
use Modules\Documents\Http\Requests\LinkDocumentRequest;
use Modules\Documents\Http\Requests\UpdateDocumentRequest;
use Modules\Documents\Http\Requests\UploadDocumentRequest;
use Modules\Documents\Http\Resources\DocumentPresenter;
use Modules\Documents\Models\Document;
use Modules\Documents\Models\Folder;
use Modules\Documents\Services\DocumentManagers;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;
use Modules\Shared\Contracts\ProjectMembership;
use Modules\Shared\Support\Filters;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('documents.view');

        /** @var User $user */
        $user = $request->user();
        $filters = Filters::only($request, ['search', 'folder_id', 'category', 'tag', 'visibility']);

        $documents = Document::query()
            ->with(['folder:id,name', 'uploader:id,name', 'employee:id,first_name,last_name'])
            ->visibleTo($user)
            ->when($filters['search'] ?? null, function ($q, string $s) {
                $like = '%'.Filters::escapeLike($s).'%';

                $q->where(fn ($w) => $w->whereRaw("title like ? escape '!'", [$like])->orWhereRaw("description like ? escape '!'", [$like]));
            })
            ->when($filters['folder_id'] ?? null, fn ($q, $v) => $q->where('folder_id', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['tag'] ?? null, fn ($q, string $v) => $q->whereJsonContains('tags', $v))
            ->when($filters['visibility'] ?? null, fn ($q, $v) => $q->where('visibility', $v))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Document $d) => DocumentPresenter::summary($d, $user));

        return Inertia::render('documents/index', [
            'documents' => $documents,
            'filters' => $filters,
            'folders' => Folder::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'options' => [
                'categories' => Document::query()->visibleTo($user)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')
                    ->map(fn (string $c) => ['value' => $c, 'label' => $c])->values(),
                'visibilities' => DocumentVisibility::options(),
                ...$this->formOptions(),
            ],
            'can' => [
                'upload' => Gate::allows('documents.upload'),
                'manage' => DocumentManagers::managesAll($user),
            ],
        ]);
    }

    public function store(UploadDocumentRequest $request, UploadDocument $upload): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $request->file('file');
        abort_if($file === null || is_array($file), 422);

        $document = $upload->handle($user, $request->payload(), $file);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded.')]);

        return to_route('documents.show', $document);
    }

    public function storeLink(LinkDocumentRequest $request, CreateLinkDocument $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $document = $create->handle($user, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link added.')]);

        return to_route('documents.show', $document);
    }

    public function show(Request $request, Document $document): Response
    {
        Gate::authorize('view', $document);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('documents/show', [
            'document' => DocumentPresenter::detail($document, $user),
            'folders' => Folder::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'options' => [
                'visibilities' => DocumentVisibility::options(),
                ...$this->formOptions(),
            ],
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document, UpdateDocument $update): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $update->handle($document, $user, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document updated.')]);

        return back();
    }

    public function destroy(Request $request, Document $document, DeleteDocument $delete): RedirectResponse
    {
        Gate::authorize('delete', $document);

        /** @var User $user */
        $user = $request->user();
        $delete->handle($document, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document deleted.')]);

        return to_route('documents.index');
    }

    public function share(Request $request, Document $document, ShareDocument $share): RedirectResponse
    {
        Gate::authorize('share', $document);

        /** @var array{user_ids?: list<int>} $data */
        $data = $request->validate([
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $share->handle($document, $user, array_map('intval', $data['user_ids'] ?? []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sharing updated.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        /** @var User $user */
        $user = request()->user();

        return [
            'projects' => app(ProjectMembership::class)->optionsFor($user),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map(fn (Department $d) => ['value' => $d->id, 'label' => $d->name])->all(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name'])->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'users' => Employee::query()->employed()->whereNotNull('user_id')->orderBy('first_name')->get(['user_id', 'first_name', 'last_name'])
                ->map(fn (Employee $e) => ['value' => $e->user_id, 'label' => $e->full_name])->all(),
            'employees' => DocumentManagers::managesAll($user)
                ? Employee::query()->employed()->orderBy('first_name')->get(['id', 'first_name', 'last_name'])->map(fn (Employee $e) => ['value' => $e->id, 'label' => $e->full_name])->all()
                : [],
        ];
    }
}
