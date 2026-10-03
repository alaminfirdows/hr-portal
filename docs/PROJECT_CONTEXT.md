# HR Portal — Project Context

Single source of truth for **what** this system is, **how** it is built, and **why**.
Read this before you change code. Specs and phase plans live in `docs/superpowers/`.

---

## 1. Product

An **HR + Employee Operations platform** for one company (single tenant, < 500 employees).
It covers the classic HR core (people, org, leave, attendance, payroll) plus operations
(projects, documents, announcements) and cross-cutting services (notifications, audit, reports).

### 1.1 Goals

- One place for an employee to see their profile, leave, attendance, payslips, projects, documents, and company news.
- HR runs the full employee lifecycle and HR operations without spreadsheets.
- Managers see and act on their team (approvals, attendance, reports).
- Every important change is audited (who, what, when, where, before, after).
- Each business area is an isolated module that can grow on its own.

### 1.2 Non-goals (for now)

- Multi-tenancy / SaaS. One organization per install.
- Task / kanban management inside projects (use Jira/Linear).
- Country-specific statutory tax engines. Tax is a configurable slab table.
- Multi-currency. One currency from settings.
- Biometric / GPS / Slack integrations (driver seams exist, no drivers yet).
- Realtime websockets. Notification bell uses Inertia polling.
- SSO. Password + optional 2FA/passkeys only.

### 1.3 Actors and default roles

Roles are **data**, not code. Defaults are seeded; Admin can edit the role → permission matrix.

| Role              | Purpose                | Key default permissions (scope)                                                                                                                                                            |
| ----------------- | ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `admin`           | System owner           | `is_super` → bypasses every check                                                                                                                                                          |
| `hr`              | HR operations          | employees.* (all), organization.manage, leave.* (all), attendance.* (all), payroll.* (all), documents.manage (all), announcements.manage, reports.view (all), audit.view                   |
| `manager`         | Team / department lead | employees.view (team), leave.view/approve (team), attendance.view (team), projects.members.manage, reports.view (team)                                                                     |
| `project_manager` | Runs projects          | projects.create, projects.manage (own), projects.view                                                                                                                                      |
| `employee`        | Every staff member     | directory.view, leave.request (own), leave.view (own), attendance.check_in, attendance.view (own), payroll.view (own), documents.upload, documents.view, projects.view, announcements.view |
| `support`         | Limited ops            | employees.view (all, read only), documents.view                                                                                                                                            |

A user can hold several roles. Effective scope for a permission = the **widest** scope over all roles.

---

## 2. Technology

| Layer                   | Choice                                                                                                                      |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Runtime                 | PHP 8.5, Laravel 13, Node 24, pnpm                                                                                          |
| Frontend                | Inertia 3 + React 19 + TypeScript (strict) + Tailwind 4 + shadcn/ui (new-york)                                              |
| Routing in TS           | Laravel Wayfinder (`@/actions/...`, `@/routes/...`) — never hard-code URLs                                                  |
| Auth                    | Laravel Fortify (login, reset, email verify, 2FA TOTP, passkeys). Public registration **off**; invite-only                  |
| DB                      | MySQL 8 in prod (`.env.example`); SQLite in-memory for tests; SQLite file allowed for local dev                             |
| Cache / queue / session | Redis in prod; `database` drivers locally                                                                                   |
| Files                   | Private disk. Local `local` disk in dev, S3-compatible (`s3`) in prod. Downloads go through controllers that check policies |
| PDF                     | barryvdh/laravel-dompdf (payslips)                                                                                          |
| Formulas                | symfony/expression-language (payroll components). Never `eval`                                                              |
| Tests                   | Pest 5 (feature + unit). Larastan level 7+, Pint, vite-plus lint/format, `tsc --noEmit`                                     |

Quality gate before every commit: `composer test` (pint check + phpstan + pest) and `pnpm run types:check`.

---

## 3. Architecture

### 3.1 Modular monolith

All business code lives in `modules/<Name>/`. `app/` only holds framework glue
(bootstrap providers, base middleware, Fortify actions).

```text
modules/
├── Shared/          kernel: base classes, contracts, value objects, enums, UI kit
├── Identity/        users, invitations, login rules, sessions/devices
├── AccessControl/   roles, permissions, scoped authorization, role matrix UI
├── Organization/    departments, teams, positions, locations, holidays, work calendar
├── Employees/       employee records, directory, lifecycle, history
├── Settings/        organization settings (key/value, cached)
├── Audit/           audit log (listens to auditable domain events)
├── Notifications/   in-app + mail notifications, preferences, bell
├── Dashboard/       role-aware dashboard composed from module widgets
├── Leave/           leave types, policies, balances, requests, approvals
├── Attendance/      shifts, check-in/out, daily records, finalization job
├── Payroll/         pay components, compensation, payroll runs, payslips
├── Projects/        projects, members, custom fields, visibility, activity
├── Documents/       folders, documents, visibility, sharing, expiry
├── Announcements/   targeted announcements with read tracking
└── Reports/         read-side reporting + CSV export
```

### 3.2 Module anatomy

```text
modules/Leave/
├── LeaveServiceProvider.php        extends Modules\Shared\Support\ModuleServiceProvider
├── Actions/                        one class per use case: handle(...) — all writes go here
├── Contracts/                      interfaces this module EXPOSES to others (optional)
├── Data/                           typed DTOs passed between layers
├── Database/
│   ├── Factories/
│   ├── Migrations/
│   └── Seeders/                    <Name>Seeder (base data) + <Name>DemoSeeder (demo data)
├── Enums/                          backed enums (status, types) with label()
├── Events/                         domain events (public API of the module)
├── Http/
│   ├── Controllers/                thin: authorize → validate (FormRequest) → Action → redirect/render
│   ├── Requests/                   FormRequests (validation + authorize())
│   └── Resources/                  JsonResource / array presenters for Inertia props
├── Jobs/  Listeners/  Notifications/  Console/
├── Models/
├── Policies/                       auto-discovered: Models\Foo → Policies\FooPolicy
├── Routes/web.php                  loaded with ['web','auth','verified','active'] middleware
├── Services/                       domain services (calculators, resolvers)
├── Tests/{Feature,Unit}/           Pest tests for this module
└── resources/js/
    ├── pages/<slug>/...            Inertia pages. Page name = '<slug>/...'
    └── components/                 module-private React components
```

Namespace: `Modules\<Name>\...` (PSR-4 root `modules/`).

### 3.3 Module registration

- `App\Providers\ModuleServiceProvider` discovers every `modules/*/*ServiceProvider.php` and registers it. Adding a module = creating its folder. No central edits.
- `ModuleServiceProvider` (base) per module:
    - loads `Database/Migrations`, `Routes/web.php`
    - adds `resources/js/pages` to `inertia.pages.paths` (so `assertInertia` finds pages)
    - registers the module **manifest** into `ModuleRegistry`:
        - `permissions()` — `['leave.approve' => 'Approve leave requests', ...]`
        - `navigation()` — sidebar items `{label, route, icon, permission, group, order}`
        - `dashboardWidgets()` — classes implementing `DashboardWidget`
        - `listeners()` — `[Event::class => [Listener::class]]`
        - `schedule(Schedule $s)` — scheduled commands
        - `seeders()` / `demoSeeders()`
- Policies are auto-guessed: `Modules\X\Models\Foo` → `Modules\X\Policies\FooPolicy`.

### 3.4 Module boundaries (rules)

1. A module **writes only its own tables**. Writes into another module go through that module's Action or a Shared contract.
2. Cross-module **reads** go through Shared contracts (`OrgHierarchy`, `WorkCalendar`, `LeaveCalendar`, `AttendanceSummary`, `EmployeeDirectory`). Reading another module's Eloquent model directly is allowed only for **relations/display** (e.g. `belongsTo(Employee::class)`) and in `Reports` (read-side module).
3. Side effects across modules use **domain events**. Example: `LeaveRequestApproved` → Attendance marks days `on_leave`, Notifications notifies employee, Audit logs it.
4. Shared contracts have a **null implementation** bound in `Shared`; the owning module rebinds the real one. Modules stay bootable alone.
5. Frontend: a module imports only `@/components/**` (shared UI) and its own `components/`. Never another module's components.

### 3.5 Request flow (Inertia best practice)

```text
Route → Controller
          ├─ $this->authorize() / FormRequest::authorize()   (Policy + AccessControl)
          ├─ FormRequest::validated()                        (validation)
          ├─ Action::handle(...)                             (DB transaction, events)
          └─ redirect()->back()/route(...)->with('success', ...)   (POST/PUT/DELETE)
             Inertia::render('leave/requests/index', [...])          (GET)
```

- GET pages: paginated (`->paginate(15)->withQueryString()`), filters echoed back as `filters` prop.
- Heavy optional props use `Inertia::defer()` / `Inertia::optional()`.
- Mutations always redirect (PRG). Flash `success` / `error` → toast (`use-flash-toast`).
- Forms use Inertia `<Form>` / `useForm` with Wayfinder `.form()` helpers.
- Props are plain arrays from Resources; never pass whole models with hidden relations.

---

## 4. Authorization model

### 4.1 Tables

```text
roles            id, name, slug (unique), description, is_super, is_system
permissions      id, key (unique, e.g. leave.approve), module, description
permission_role  role_id, permission_id, scope (own|team|department|all)
role_user        role_id, user_id
```

### 4.2 Scopes

| Scope        | Subject employee is visible when                                   |
| ------------ | ------------------------------------------------------------------ |
| `own`        | subject is the actor's employee record                             |
| `team`       | own ∪ direct reports (`manager_id = me`) ∪ members of teams I lead |
| `department` | team ∪ employees in my department ∪ departments I head             |
| `all`        | any employee                                                       |

API (`Modules\AccessControl\Services\Access`, also via `Gate`):

```php
$access->can($user, 'leave.approve');                       // has permission at any scope
$access->can($user, 'leave.approve', $employee);            // scope covers subject
$access->scope($user, 'leave.view');                        // ?Scope enum
$access->visibleEmployeeIds($user, 'leave.view');           // null = all, else int[]
```

`Gate::before`: users with an `is_super` role pass everything.
Policies combine `Access` checks with domain rules (e.g. cannot approve own request).
Users must be `is_active`; middleware `active` logs out disabled users.

Frontend gets `auth.permissions: string[]` (keys only) for showing/hiding UI. The server is always the authority.

---

## 5. Data model (summary)

Money: integer **minor units** (`bigint`), `Modules\Shared\ValueObjects\Money`. Dates: `date`; timestamps UTC; display in settings timezone.

### Identity

- `users` id, name, email (unique), password, email_verified_at, is_active, last_login_at, 2FA cols
- `invitations` id, email, token (sha256), user_id, invited_by, expires_at, accepted_at
- `sessions` (Laravel database sessions; used for device list + revoke)

### Organization

- `departments` id, name, code (unique), description, head_employee_id?
- `teams` id, department_id, name, lead_employee_id?
- `positions` id, title, level?, department_id?
- `locations` id, name, address?, timezone?
- `holidays` id, name, date, is_optional

### Employees

- `employees` id, user_id? (unique), employee_code (unique), first_name, last_name, email (unique, official), personal_email?, phone?, photo_path?, date_of_birth?, gender?, department_id?, team_id?, position_id?, location_id?, manager_id? (self), joined_on, exited_on?, status, employment_type, work_type, address?, emergency_contact (json), skills (json), links (json), soft deletes
- `employee_histories` id, employee_id, field, old_value, new_value, reason?, effective_on, changed_by, created_at

Lifecycle: `invited → active ⇄ on_leave`, `active ⇄ suspended`, `* → terminated` (terminal; disables user).
Tracked history fields: department_id, team_id, manager_id, position_id, status, employment_type (+ salary from Payroll).

### Settings

- `settings` key (unique), value (json). Keys: company_name, timezone, currency, fiscal_year_start_month, working_days ([1..5] ISO), date_format, leave_year_start_month.

### Audit

- `audit_logs` id, actor_id?, action, subject_type?, subject_id?, description, before (json), after (json), ip, user_agent, created_at

### Notifications

- `notifications` (Laravel standard)
- `notification_preferences` user_id, type, mail (bool), database (bool)

### Leave

- `leave_types` id, name, code, color, is_paid, requires_attachment, allow_half_day, accrual_method (yearly|monthly|none), annual_quota (decimal?, null = unlimited), carry_forward_cap (decimal), prorate_new_joiners, approval_steps (json: ["manager","hr"]), max_consecutive_days?, is_active
- `leave_balances` id, employee_id, leave_type_id, year, entitled, carried_forward, adjustment, used, pending — unique(employee, type, year)
- `leave_requests` id, employee_id, leave_type_id, start_date, end_date, day_part (full|first_half|second_half), days, reason, attachment_path?, status (pending|approved|rejected|cancelled), current_step, decided_at?
- `leave_approvals` id, leave_request_id, step, step_role, approver_id (user), decision, comment?, created_at

### Attendance

- `shifts` id, name, start_time, end_time, grace_minutes, half_day_minutes, overtime_after_minutes, is_flexible, is_default
- `employee_shifts` employee_id (unique), shift_id
- `attendance_records` id, employee_id, date, check_in_at?, check_out_at?, status, worked_minutes, late_minutes, overtime_minutes, check_in_ip?, source (web|manual|system), note?, edited_by? — unique(employee, date)

### Payroll

- `pay_components` id, name, code, type (earning|deduction), calculation (fixed|percent_of_basic|formula|tax_slab), value?, formula?, is_taxable, applies_to_all, is_active, sort
- `employee_pay_components` employee_id, pay_component_id, amount? (override)
- `compensations` id, employee_id, basic_salary (minor), effective_from, effective_to?, reason?, created_by
- `tax_slabs` id, min_amount, max_amount?, rate (percent) — monthly taxable income
- `payroll_adjustments` id, employee_id, period (YYYY-MM), type (earning|deduction), name, amount, note?, payroll_run_id?
- `payroll_runs` id, period (unique), status (draft|calculated|reviewed|approved|processed), totals, created_by, approved_by?, processed_at?
- `payslips` id, payroll_run_id, employee_id, basic, gross, total_deductions, net, working_days, paid_days, published_at?
- `payslip_lines` id, payslip_id, code, name, type, amount

### Projects

- `projects` id, name, code (unique), description?, status, visibility (public|private|restricted), manager_employee_id?, client?, priority, budget?, start_date?, end_date?
- `project_members` project_id, employee_id, role (lead|member|viewer), allocation_percent, joined_on
- `project_field_definitions` id, key, label, type (text|number|date|select|url), options (json), is_required, sort
- `project_field_values` project_id, field_definition_id, value
- Activity = `audit_logs` where subject = project.

Visibility: **public** → anyone with projects.view sees everything. **restricted** → listed for everyone, details only for members/manager. **private** → only members/manager (and projects.manage at scope all).

### Documents

- `document_folders` id, name, parent_id?, owner_id, visibility, visibility_target_id?
- `documents` id, folder_id?, name, kind (file|url), disk?, path?, mime?, size?, url?, category?, purpose?, tags (json), owner_id, employee_id?, project_id?, visibility (private|department|team|project|organization|users), visibility_target_id?, expires_on?, metadata (json), expiry_notified_at?
- `document_shares` document_id, user_id

### Announcements

- `announcements` id, title, body, author_id, audience (everyone|department|team|employees), priority (normal|important|urgent), is_pinned, publish_at, expires_at?, attachment_path?, notified_at?
- `announcement_targets` announcement_id, target_type, target_id
- `announcement_reads` announcement_id, user_id, read_at

---

## 6. Key business rules

### Leave

- Days = working days in range (settings `working_days`, minus holidays). Single-day half → 0.5. Half day only when `allow_half_day` and start = end.
- Request rejected when: overlaps a pending/approved request; days = 0; exceeds available balance (unless quota null); exceeds `max_consecutive_days`; attachment required but missing; start in past more than 30 days.
- Available = entitled + carried_forward + adjustment − used − pending.
- Approval steps from `leave_types.approval_steps`. Step `manager`: employee's manager (skipped if none or manager is requester). Step `hr`: any user with `leave.approve` scope `all`. HR (scope all) may also act on a manager step. Nobody approves their own request. Reject at any step ends flow.
- Balance moves: submit → pending += days; approve (final) → pending −= days, used += days; reject/cancel pending → pending −= days; cancel approved (future only) → used −= days.
- Accrual: `yearly` → full quota at leave-year start (`leave:allocate {year}`), pro-rated by join month when `prorate_new_joiners`. `monthly` → quota/12 added each month (`leave:accrue`). Carry forward at allocation = min(previous available, carry_forward_cap).

### Attendance

- Web check-in once per day; check-out after check-in. IP recorded.
- Late if check-in > shift start + grace. Half day if worked < `half_day_minutes`. Overtime = worked − (shift length + overtime_after_minutes) when positive.
- `attendance:finalize {date?}` (daily, 23:55): for each active employee without record → `holiday` | `weekend` | `on_leave` | `absent`.
- `LeaveRequestApproved` → upsert `on_leave` for each working day in range (does not overwrite a present check-in). `LeaveRequestCancelled` → remove system `on_leave` rows.
- HR edits are `source = manual`, audited.

### Payroll

- Run per period `YYYY-MM`, one run per period. Draft → Calculate (computes payslips) → Review → Approve → Process (publishes payslips, notifies). Recalculate allowed until approved. Approver ≠ creator.
- Per employee: basic = compensation effective in period (pro-rated by paid days if joined/exited mid period). Components evaluated in `sort` order. Variables for formulas: `basic`, `gross`, `working_days`, `paid_days`, `unpaid_leave_days`, `absent_days`, `overtime_hours`, `years_of_service`. Functions: `min`, `max`, `round`.
- `tax_slab` component: progressive tax on taxable earnings using `tax_slabs`.
- Unpaid days = unpaid leave days + absent days; deducted as `basic / working_days * unpaid_days`.
- Adjustments (bonus, one-off deduction) for the period are added as lines.
- Net = gross − deductions; never below 0 (flag).

### Documents

- Download only through `DocumentController@download` after `DocumentPolicy::view`. S3 → temporary URL (5 min); local → stream.
- Expiry reminders 30 and 7 days before `expires_on` to owner + HR (`documents:notify-expiring`, daily).

### Announcements

- Visible when `publish_at <= now` and not expired and the viewer is in audience.
- `announcements:dispatch` (every minute) notifies audience once (`notified_at`).

---

## 7. Cross-cutting

### Audit

- Domain events that implement `Modules\Shared\Contracts\AuditableEvent` are logged automatically by the Audit module (`auditEntry(): AuditEntry`). IP / user agent from current request.
- Audit list (admin/HR) with filters by actor, action, subject, date. Subject timelines reuse it (employee history tab, project activity).

### Notifications

- Each module defines Notification classes extending `Modules\Notifications\Support\PortalNotification` (channels decided by user preferences + defaults per type).
- Bell: `notifications.unread_count` shared prop, refreshed with `usePoll(60s, { only: ['notifications'] })`.
- Event → listener → `$user->notify(...)`. Modules never send mail directly.

### Dashboard

- Widgets implement `DashboardWidget { key(); title(); visible(User): bool; data(User): array; type(): 'stat'|'list'|'table'; size(); order() }`. Frontend renders generic widget types → no cross-module imports.

### Scheduled commands

| Command                     | When                       |
| --------------------------- | -------------------------- |
| `attendance:finalize`       | daily 23:55                |
| `leave:accrue`              | monthly, 1st 00:10         |
| `leave:allocate`            | yearly at leave-year start |
| `documents:notify-expiring` | daily 08:00                |
| `announcements:dispatch`    | every minute               |
| `invitations:prune`         | daily                      |

---

## 8. Conventions

- PHP: `declare(strict_types=1)` not used (match Laravel skeleton). Constructor property promotion, typed everything, `final` for Actions/Controllers.
- Actions: `final class ApproveLeaveRequest { public function handle(LeaveRequest $r, User $by, ?string $comment): LeaveRequest }`. Wrap writes in `DB::transaction`. Dispatch events **after commit** (`ShouldDispatchAfterCommit`).
- Enums: string-backed, `label(): string`, `options(): array` for selects.
- Route names: `<module-slug>.<resource>.<action>` e.g. `leave.requests.store`. URLs kebab-case.
- Page names: `<module-slug>/<resource>/<view>` e.g. `leave/requests/index`.
- Tests: every Action + Policy + Controller endpoint has Pest coverage. Helpers in `tests/Pest.php`: `userWithRole('hr')`, `employeeUser()`, `actingAsRole()`.
- Seeders: `php artisan db:seed` → roles/permissions/settings/leave types/pay components. `php artisan db:seed --class=DemoSeeder` → demo org (6 departments, ~50 employees, history).
- Demo logins (password `password`): `admin@example.com`, `hr@example.com`, `manager@example.com`, `pm@example.com`, `employee@example.com`.

---

## 9. Phases

| Phase           | Content                                                                                                                                        | Plan                                        |
| --------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| 1 Foundation    | Shared kernel, module loader, Identity, AccessControl, Organization, Employees, Settings, Audit, Notifications, Dashboard, layout/nav, seeders | `plans/2026-10-03-phase-1-foundation.md`    |
| 2 HR            | Leave, holidays, shifts, Attendance, lifecycle, HR dashboard widgets                                                                           | `plans/2026-10-03-phase-2-hr.md`            |
| 3 Payroll       | Components, compensation, runs, payslips, PDF, export                                                                                          | `plans/2026-10-03-phase-3-payroll.md`       |
| 4 Operations    | Projects, members, custom fields, visibility, activity                                                                                         | `plans/2026-10-03-phase-4-projects.md`      |
| 5 Collaboration | Documents, Announcements                                                                                                                       | `plans/2026-10-03-phase-5-collaboration.md` |
| 6 Reporting     | Reports + CSV, reminders, role dashboards polish                                                                                               | `plans/2026-10-03-phase-6-reports.md`       |

Design spec: `docs/superpowers/specs/2026-10-03-hr-portal-design.md`.
