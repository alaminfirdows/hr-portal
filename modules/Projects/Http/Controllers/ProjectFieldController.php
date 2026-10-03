<?php

namespace Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Projects\Actions\DeleteProjectField;
use Modules\Projects\Actions\SaveProjectField;
use Modules\Projects\Enums\FieldType;
use Modules\Projects\Http\Requests\ProjectFieldRequest;
use Modules\Projects\Models\ProjectFieldDefinition;

final class ProjectFieldController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('projects.fields.manage');

        return Inertia::render('projects/fields/index', [
            'fields' => ProjectFieldDefinition::query()->withCount('values')->orderBy('sort')->orderBy('id')->get()
                ->map(fn (ProjectFieldDefinition $d) => [
                    'id' => $d->id,
                    'key' => $d->key,
                    'label' => $d->label,
                    'type' => $d->type->value,
                    'type_label' => $d->type->label(),
                    'options' => $d->options ?? [],
                    'is_required' => $d->is_required,
                    'sort' => $d->sort,
                    'values_count' => (int) $d->getAttribute('values_count'),
                ]),
            'types' => FieldType::options(),
        ]);
    }

    public function store(ProjectFieldRequest $request, SaveProjectField $save): RedirectResponse
    {
        $save->handle(new ProjectFieldDefinition, $request->attributesForField(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field created.')]);

        return back();
    }

    public function update(ProjectFieldRequest $request, ProjectFieldDefinition $definition, SaveProjectField $save): RedirectResponse
    {
        $save->handle($definition, $request->attributesForField(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field updated.')]);

        return back();
    }

    public function destroy(Request $request, ProjectFieldDefinition $definition, DeleteProjectField $delete): RedirectResponse
    {
        Gate::authorize('projects.fields.manage');

        $delete->handle($definition, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Field deleted.')]);

        return back();
    }
}
