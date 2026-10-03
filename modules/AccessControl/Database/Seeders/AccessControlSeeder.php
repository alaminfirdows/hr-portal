<?php

namespace Modules\AccessControl\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\AccessControl\Actions\SyncPermissions;
use Modules\AccessControl\Models\Permission;
use Modules\AccessControl\Models\Role;

/**
 * Default roles and their permission matrix (docs/PROJECT_CONTEXT.md §1.3).
 * Patterns support a trailing `*`. Keys a module does not declare are ignored.
 */
class AccessControlSeeder extends Seeder
{
    /**
     * @return array<string, array{name: string, description: string, super?: bool, permissions: array<string, string>}>
     */
    public static function matrix(): array
    {
        $self = [
            'directory.view' => 'all',
            'leave.request' => 'own',
            'leave.view' => 'own',
            'attendance.check_in' => 'own',
            'attendance.view' => 'own',
            'payroll.view' => 'own',
            'documents.view' => 'own',
            'documents.upload' => 'own',
            'projects.view' => 'all',
            'announcements.view' => 'all',
        ];

        return [
            'admin' => [
                'name' => 'Administrator',
                'description' => 'System owner. Bypasses every permission check.',
                'super' => true,
                'permissions' => [],
            ],
            'hr' => [
                'name' => 'HR',
                'description' => 'HR operations across the whole organization.',
                'permissions' => [
                    ...$self,
                    'users.manage' => 'all',
                    'employees.*' => 'all',
                    'organization.manage' => 'all',
                    'settings.manage' => 'all',
                    'leave.*' => 'all',
                    'attendance.*' => 'all',
                    'shifts.manage' => 'all',
                    'payroll.*' => 'all',
                    'documents.*' => 'all',
                    'projects.*' => 'all',
                    'announcements.*' => 'all',
                    'reports.view' => 'all',
                    'audit.view' => 'all',
                ],
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Team or department lead.',
                'permissions' => [
                    ...$self,
                    'employees.view' => 'team',
                    'leave.view' => 'team',
                    'leave.approve' => 'team',
                    'attendance.view' => 'team',
                    'projects.members.manage' => 'team',
                    'reports.view' => 'team',
                ],
            ],
            'project_manager' => [
                'name' => 'Project Manager',
                'description' => 'Creates and runs projects.',
                'permissions' => [
                    ...$self,
                    'projects.create' => 'all',
                    'projects.manage' => 'own',
                    'projects.members.manage' => 'own',
                ],
            ],
            'employee' => [
                'name' => 'Employee',
                'description' => 'Every staff member.',
                'permissions' => $self,
            ],
            'support' => [
                'name' => 'Support',
                'description' => 'Limited operational access.',
                'permissions' => [
                    'directory.view' => 'all',
                    'employees.view' => 'all',
                    'documents.view' => 'all',
                    'announcements.view' => 'all',
                ],
            ],
        ];
    }

    public function run(SyncPermissions $sync): void
    {
        $sync->handle();

        $keys = Permission::query()->pluck('id', 'key');

        foreach (self::matrix() as $slug => $definition) {
            $role = Role::query()->updateOrCreate(['slug' => $slug], [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_super' => $definition['super'] ?? false,
                'is_system' => true,
            ]);

            $sync = [];
            foreach ($definition['permissions'] as $pattern => $scope) {
                foreach ($keys as $key => $id) {
                    if (Str::is($pattern, $key)) {
                        $sync[$id] = ['scope' => $scope];
                    }
                }
            }

            $role->permissions()->sync($sync);
        }
    }
}
