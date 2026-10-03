# Phase 1 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Module system + identity, RBAC, org structure, employees, settings, audit, notifications, dashboard shell, demo data.

**Architecture:** Modular monolith in `modules/`. Base `ModuleServiceProvider` + `ModuleRegistry`. Shared contracts with null impls. See `docs/PROJECT_CONTEXT.md` §3.

**Tech Stack:** Laravel 13, Fortify, Inertia 3, React 19, TS, shadcn, Pest 5, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-10-03-hr-portal-design.md`

## Global Constraints

- All business code in `modules/<Name>`; namespace `Modules\<Name>`.
- Money integer minor units. Dates `date` columns. Timestamps UTC.
- Route names `<slug>.<resource>.<action>`, page names `<slug>/<resource>/<view>`.
- Each task ends green: `php artisan test`, `vendor/bin/phpstan analyse`, `vendor/bin/pint --test`, `pnpm run types:check`.
- No public registration. Invite-only.

## Review Focus

1. Disabled (`is_active = false`) user with a live session → next request logs out.
2. Manager with `employees.view` scope `team` opens an employee outside team → 403, and list hides them.
3. Expired or reused invitation token → error page, no account change.
4. Terminating an employee → linked user disabled, history row written, audit row written.
5. Two employees cannot share `email` or `employee_code`; user can link to at most one employee.

---

### Task 1: Module loader + Shared kernel

**Files:**

- Modify: `composer.json` (psr-4 `"Modules\\": "modules/"`), `bootstrap/providers.php`, `phpunit.xml` (suite `modules/*/Tests`), `tests/Pest.php`, `phpstan.neon` (path `modules/`), `config/inertia.php`
- Create: `app/Providers/ModuleServiceProvider.php`, `modules/Shared/SharedServiceProvider.php`, `modules/Shared/Support/{ModuleServiceProvider,ModuleRegistry,ModuleManifest,NavItem}.php`, `modules/Shared/Contracts/*.php`, `modules/Shared/Null/*.php`, `modules/Shared/Exceptions/DomainException.php`, `modules/Shared/ValueObjects/Money.php`, `modules/Shared/Data/AuditEntry.php`, `modules/Shared/Enums/Scope.php`
- Test: `modules/Shared/Tests/Unit/MoneyTest.php`, `modules/Shared/Tests/Feature/ModuleRegistryTest.php`

**Interfaces — Produces:** `ModuleServiceProvider` hooks (spec §3), `ModuleRegistry`, `Money::of(int $minor)`, `Money::fromDecimal(string|float)`, `->add() ->subtract() ->multiply(float) ->format(string $currency)`, `Scope` enum (`own|team|department|all`, `covers(Scope): bool`, `widest(Scope...)`).

- [ ] Write failing tests: registry collects permissions/navigation from a fixture module; `Money` arithmetic rounds half-up; `Scope::All->covers(Scope::Team)` true, reverse false.
- [ ] Implement loader: glob `modules/*/*ServiceProvider.php`, register each.
- [ ] Base provider: load migrations, routes (middleware `web, auth, verified, active`), push pages path to `inertia.pages.paths`, register manifest, listeners, schedule, commands. Policy guesser `Modules\X\Models\Y` → `Modules\X\Policies\YPolicy`.
- [ ] Run tests → pass. Commit `feat(shared): module loader and kernel`.

### Task 2: Frontend kernel

**Files:** `resources/js/app.tsx` (custom resolve over both page roots), `tsconfig.json` (`@modules/*`), `vite.config.ts` (lint ignore none), `resources/js/components/portal/*`, `resources/js/types/portal.ts`, `resources/js/components/app-sidebar.tsx` (nav from shared prop), shadcn add: table, tabs, textarea, popover, calendar, switch, radio-group, scroll-area, pagination, command, alert-dialog, progress, sonner.

- [ ] Add shadcn components. Build `PageHeader`, `DataTable`, `Pagination`, `FilterBar`, `StatusBadge`, `EmptyState`, `ConfirmButton`, `FormField`, `StatCard`, `Can`, `DynamicIcon`.
- [ ] Sidebar groups from `usePage().props.navigation`.
- [ ] `pnpm run types:check && pnpm run build` pass. Commit.

### Task 3: Identity

**Files:** move `App\Models\User` → `modules/Identity/Models/User.php` (update `config/auth.php`, Fortify actions, factories, tests); `Database/Migrations/*_add_identity_columns_to_users.php` (`is_active`, `last_login_at`), `*_create_invitations_table.php`; `Models/Invitation.php`; `Actions/{InviteUser,AcceptInvitation,DeactivateUser,ActivateUser,RevokeOtherSessions}.php`; `Http/Controllers/{InvitationController,AcceptInvitationController,SessionController,UserController}.php`; `Http/Middleware/EnsureUserIsActive.php`; `Notifications/InvitationNotification.php`; pages `identity/invitations/accept.tsx`, `identity/users/index.tsx`, `settings/sessions.tsx`.

**Interfaces — Produces:** `InviteUser::handle(string $name, string $email, ?User $by): Invitation` (creates inactive-verified-pending user + token, sends mail); `AcceptInvitation::handle(string $token, string $password): User` (activates, verifies email, fires `InvitationAccepted(User)`); `User::isActive()`.

- [ ] Tests: registration route 404; invite creates user+invitation, mail sent; accept with valid token sets password, logs in, marks verified; expired token → error; reused token → error; inactive user cannot login; inactive user with session logged out on next request; sessions list shows current device; revoke others deletes other rows.
- [ ] Implement. Disable `Features::registration()`. Login pipeline rejects inactive users (Fortify `authenticateUsing`). Update `last_login_at` on `Login` event.
- [ ] Green. Commit.

### Task 4: AccessControl

**Files:** migrations `roles`, `permissions`, `permission_role`, `role_user`; `Models/{Role,Permission}.php`; `Concerns/HasRoles.php` (trait used by User); `Services/Access.php`; `Actions/{SyncPermissions,SaveRole,AssignRoles}.php`; `Console/SyncPermissionsCommand.php` (`access:sync`); `Database/Seeders/AccessControlSeeder.php` (default roles + matrix from context §1.3); controllers/pages `access/roles/{index,edit}.tsx` (matrix with scope selects), user role assignment in `identity/users/index`.

**Interfaces — Produces:** `Access::can(User, string $key, ?Employee|int $subject = null): bool`, `Access::scope(User, string): ?Scope`, `Access::visibleEmployeeIds(User, string): ?array`, Gate `before` super, Gate ability per permission key (`Gate::allows('leave.approve', $employee)`), `User::hasRole(string)`, `User::permissionKeys(): array` (cached per request).

- [ ] Tests (unit + feature): own/team/department/all coverage using OrgHierarchy fake; widest scope across multiple roles; super bypass; `access:sync` inserts registry keys and removes stale; role matrix update audited; non-admin cannot open roles page.
- [ ] Implement. Green. Commit.

### Task 5: Settings

**Files:** migration `settings`; `Models/Setting.php`; `Services/SettingsRepository.php` (`get(key, default)`, `set(key, value)`, cached forever, busted on save); `Data/OrganizationSettings.php` typed accessor; controller/page `settings/organization.tsx` (permission `settings.manage`); seeder defaults.

- [ ] Tests: defaults returned; update persists + cache busted; only `settings.manage` can update.
- [ ] Implement. Green. Commit.

### Task 6: Organization

**Files:** migrations departments, teams, positions, locations, holidays; models + factories + policies; Actions Save*/Delete* (delete blocked when employees attached → DomainException); controllers + pages `organization/{departments,teams,positions,locations,holidays}/index.tsx` (list + dialog forms); `Services/CompanyWorkCalendar.php` implements `WorkCalendar` (settings working_days + holidays).

- [ ] Tests: CRUD per resource with `organization.manage`; employee without permission 403; deleting department with employees fails; team must belong to department; work calendar excludes weekend + holiday; workingDaysBetween inclusive.
- [ ] Implement. Green. Commit.

### Task 7: Employees

**Files:** migrations employees, employee_histories; `Models/{Employee,EmployeeHistory}.php`; enums `EmployeeStatus, EmploymentType, WorkType, Gender`; Actions `CreateEmployee` (optionally invites user), `UpdateEmployee` (writes history for tracked fields), `ChangeEmployeeStatus` (lifecycle guard, terminate disables user), `UpdateOwnProfile`, `UploadEmployeePhoto`; events `EmployeeCreated, EmployeeUpdated, EmployeeStatusChanged` (Auditable); `Services/EloquentOrgHierarchy` implements `OrgHierarchy`; listener on `InvitationAccepted` → status invited→active; controllers `EmployeeController` (index with filters dept/team/status/search, show with tabs profile/employment/history, create, edit), `DirectoryController` (cards for everyone with `directory.view`), `MyProfileController`; pages `employees/{index,show,create,edit}.tsx`, `employees/directory.tsx`, `employees/profile.tsx`; photo served by `EmployeePhotoController`.

- [ ] Tests: HR creates employee (+ invite) → user created, status invited; accepting invite → active; manager lists only team; manager cannot view out-of-team employee; update department writes history + audit; invalid transition terminated→active rejected; terminate disables user; employee updates own phone/emergency contact but not department; directory hides salary/personal fields; unique email/code.
- [ ] Implement. Green. Commit.

### Task 8: Audit

**Files:** migration audit_logs; `Models/AuditLog.php`; `Listeners/RecordAuditEntry.php` (listens `AuditableEvent`); `Services/AuditLogger.php` (`record(AuditEntry)`); controller/page `audit/index.tsx` (filters), `Contracts/AuditTrail` (Shared) `forSubject(Model, limit)` used by employee/project timelines.

- [ ] Tests: dispatching an auditable event writes row with actor, ip, before/after; list requires `audit.view`; filter by action.
- [ ] Implement. Green. Commit.

### Task 9: Notifications

**Files:** migration notifications (artisan `notifications:table`), notification_preferences; `Support/PortalNotification.php` (abstract: `type()`, `title()`, `body()`, `url()`, channels from preferences); `Http/Controllers/NotificationController` (index, markRead, markAllRead, preferences); pages `notifications/index.tsx`, `settings/notifications.tsx`; shared prop `notifications.unread_count` + `recent` (5); bell component in header with `usePoll(60000, {only:['notifications']})`.

- [ ] Tests: preference mail=false → only database channel; mark read; mark all; unread count shared prop.
- [ ] Implement. Green. Commit.

### Task 10: Dashboard + navigation + layout

**Files:** `modules/Dashboard/*` (`DashboardController` collects visible widgets), core widgets in their modules (Employees: headcount, new joiners, upcoming birthdays; Notifications: recent), page `dashboard.tsx` → `dashboard/index.tsx` with `WidgetGrid`.

- [ ] Tests: employee sees employee widgets only; HR sees headcount widget.
- [ ] Implement. Green. Commit.

### Task 11: Seeders + demo

**Files:** `database/seeders/DatabaseSeeder.php` (runs registry `seeders()`), `DemoSeeder.php` (runs registry `demoSeeders()`), per-module demo seeders; demo users from context §8.

- [ ] `php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder` succeeds; test asserts demo logins exist with roles.
- [ ] Commit.
