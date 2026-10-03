<?php

namespace Modules\Projects\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;

final class ProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $member = $this->route('member');

        if (! $project instanceof Project) {
            return false;
        }

        $employeeId = $member instanceof ProjectMember ? $member->employee_id : $this->integer('employee_id');

        return Gate::allows('projects.view')
            && Gate::allows('view', $project)
            && Gate::allows('manageMembers', [$project, $employeeId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => [$this->route('member') ? 'prohibited' : 'required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'role' => ['required', Rule::enum(MemberRole::class)],
            'allocation_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
