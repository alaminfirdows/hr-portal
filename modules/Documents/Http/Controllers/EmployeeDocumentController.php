<?php

namespace Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Documents\Http\Resources\DocumentPresenter;
use Modules\Documents\Models\Document;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;

final class EmployeeDocumentController extends Controller
{
    /**
     * Documents attached to an employee (contracts, IDs…) that the viewer may open.
     */
    public function __invoke(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('documents.view');

        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'documents' => Document::query()
                ->with(['folder:id,name', 'uploader:id,name'])
                ->visibleTo($user)
                ->where('employee_id', $employee->id)
                ->latest()
                ->get()
                ->map(fn (Document $d) => [
                    ...DocumentPresenter::summary($d, $user),
                    'href' => route('documents.show', $d),
                ])
                ->all(),
        ]);
    }
}
