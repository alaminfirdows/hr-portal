# HR Portal — Design Spec

Date: 2026-10-03 · Status: approved by user ("write full plan and implement")
Context doc (data model, rules, conventions): `docs/PROJECT_CONTEXT.md` — this spec does not repeat it; it records decisions, interfaces, and failure handling.

## 1. Decisions (from clarification)

| Topic            | Decision                                                                                      |
| ---------------- | --------------------------------------------------------------------------------------------- |
| Scope            | Master context + all 6 phase plans; implement all phases                                      |
| Frontend         | Inertia 3 + React + TS + shadcn (existing starter kit)                                        |
| Modularity       | Custom app-level modules in `modules/<Name>`, no package                                      |
| Tenancy          | Single company                                                                                |
| DB / infra       | MySQL 8 + Redis in prod; SQLite for tests/local                                               |
| Auth             | Fortify (existing) + custom RBAC tables with scopes                                           |
| Onboarding       | Invite-only, any email domain                                                                 |
| Identity         | Separate `users` (login) and `employees` (HR record), `employees.user_id` nullable unique     |
| Leave approval   | Configurable steps per leave type (`manager`, `hr`)                                           |
| Leave accrual    | yearly, monthly, carry-forward cap, pro-rata — per leave type                                 |
| Attendance       | Web check-in/out + nightly finalize + HR edits                                                |
| Payroll          | Rule-based components (fixed / % of basic / formula / tax slab), single currency, minor units |
| Projects         | Members + roles + allocation, custom fields, visibility. No tasks                             |
| Permission scope | Scope on role-permission pivot: own/team/department/all                                       |
| Storage          | Private disk, S3-compatible in prod, policy-checked downloads                                 |
| Realtime         | Inertia polling (60 s)                                                                        |
| Audit            | Custom module, event-driven                                                                   |
| Quality          | Pest per action/policy/endpoint, Larastan, Pint, TS strict, demo seeders                      |

## 2. Approaches considered

1. **Package-based modules (nwidart)** — generators and enable/disable, but own conventions and Inertia friction. Rejected.
2. **Domain folders under `app/`** — simple, but frontend separated from backend and weak isolation. Rejected.
3. **Custom modules with a thin base provider + registry (chosen)** — full control, zero central edits per module, frontend colocated, contracts + events for boundaries.

## 3. Shared kernel interfaces

```php
namespace Modules\Shared\Support;
abstract class ModuleServiceProvider extends ServiceProvider {
    abstract protected function name(): string;          // 'Leave'
    protected function slug(): string;                   // 'leave'
    protected function permissions(): array { return []; }   // key => description
    protected function navigation(): array { return []; }    // NavItem[]
    protected function dashboardWidgets(): array { return []; } // class-string<DashboardWidget>[]
    protected function listeners(): array { return []; }     // event => listener[]
    protected function schedule(Schedule $schedule): void {}
    protected function commands(): array { return []; }
    public function seeders(): array { return []; }          // base data
    public function demoSeeders(): array { return []; }      // demo data
}

final class ModuleRegistry {                 // singleton
    public function register(ModuleManifest $m): void;
    /** @return array<string,string> */ public function permissions(): array;
    /** @return list<NavItem> */ public function navigation(): array;
    /** @return list<class-string<DashboardWidget>> */ public function widgets(): array;
    /** @return list<ModuleManifest> */ public function modules(): array;
}

namespace Modules\Shared\Contracts;
interface OrgHierarchy {                       // impl: Employees
    public function employeeIdForUser(int $userId): ?int;
    /** @return list<int> */ public function teamEmployeeIds(int $employeeId): array;       // reports + led teams (+ self)
    /** @return list<int> */ public function departmentEmployeeIds(int $employeeId): array; // own dept + headed depts (+ team)
    public function managerUserId(int $employeeId): ?int;
}
interface WorkCalendar {                       // impl: Organization
    public function isWorkingDay(CarbonInterface $date): bool;
    public function isHoliday(CarbonInterface $date): bool;
    public function workingDaysBetween(CarbonInterface $from, CarbonInterface $to): int;
}
interface LeaveCalendar {                      // impl: Leave
    public function isOnLeave(int $employeeId, CarbonInterface $date): bool;
    public function unpaidLeaveDays(int $employeeId, CarbonInterface $from, CarbonInterface $to): float;
}
interface AttendanceSummary {                  // impl: Attendance
    /** @return array{absent_days:int, overtime_minutes:int, late_days:int, present_days:int} */
    public function forPeriod(int $employeeId, CarbonInterface $from, CarbonInterface $to): array;
}
interface AuditableEvent { public function auditEntry(): AuditEntry; }
interface DashboardWidget { /* key,title,type,size,order,visible(User),data(User) */ }
```

Shared binds null implementations (`NullLeaveCalendar`, `NullAttendanceSummary`, `WeekdayWorkCalendar`, `NullOrgHierarchy`); owning modules rebind.

## 4. Frontend architecture

- `resources/js/app.tsx` uses a custom `resolve` that globs `./pages/**/*.tsx` and `../../modules/*/resources/js/pages/**/*.tsx`, keyed by path after `/pages/`.
- Shared UI kit in `resources/js/components/portal/`: `PageHeader`, `DataTable` (server pagination), `FilterBar`, `StatusBadge`, `EmptyState`, `ConfirmButton`, `FormField`, `StatCard`, `WidgetGrid`, `Can`.
- Sidebar built from shared prop `navigation` (server filtered by permission) grouped by `group`.
- Types: `resources/js/types/portal.ts` — `Paginated<T>`, `Option`, `NavItem`, `SharedProps`.
- Tsconfig path `@modules/*` → `modules/*` for module-local imports.

## 5. Error handling

| Case                                                                            | Behavior                                                                                                            |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Validation error                                                                | FormRequest → 422 → Inertia shows field errors                                                                      |
| Business rule violation in Action (e.g. insufficient balance)                   | throw `Modules\Shared\Exceptions\DomainException` → rendered as validation error on `domain` key + toast; never 500 |
| Unauthorized                                                                    | Policy → 403 page (Inertia error page)                                                                              |
| Invalid state transition (approve rejected request, process unapproved payroll) | `InvalidStateTransition` (DomainException)                                                                          |
| Disabled user mid-session                                                       | `EnsureUserIsActive` middleware logs out → login with message                                                       |
| Missing file on download                                                        | 404                                                                                                                 |
| Formula error in payroll component                                              | calculation stops for run, error names component + employee; run stays `draft`                                      |
| Scheduled job partial failure                                                   | per-employee try/catch, logged, continue others                                                                     |

## 6. Testing strategy

- Pest Feature tests per module in `modules/<Name>/Tests/Feature`; Unit tests for calculators (leave days, payroll engine, attendance status, scopes).
- `RefreshDatabase` on SQLite in-memory. Factories for every model.
- Authorization matrix tests: each sensitive endpoint tested for allowed role and denied role.
- Date-sensitive tests freeze time (`$this->travelTo`).
- Frontend: `tsc --noEmit` + vite-plus lint. (No JS unit tests in scope.)

## 7. Security

- All files private; no public URLs for documents/payslips/attachments.
- Formula evaluation sandboxed (ExpressionLanguage, whitelist variables/functions).
- Invite tokens stored hashed, single use, 7-day expiry.
- Mass assignment guarded by FormRequest `validated()` only.
- Rate limits on login, check-in.
- Audit of permission/role changes, payroll actions, employee changes, document access changes.
