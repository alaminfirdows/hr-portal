# HR Portal — Agent Conventions

Laravel 13 · Inertia 3 · React 19 · TS strict · shadcn/ui · Pest · Larastan L7 · Wayfinder.
Domain rules: `docs/PROJECT_CONTEXT.md`. Design: `docs/superpowers/specs/`. Phase plans: `docs/superpowers/plans/`.

## Modules

- Code lives in `modules/<Name>/` (namespace `Modules\<Name>\`). One module = one bounded context.
- Provider `modules/<Name>/<Name>ServiceProvider.php` extends `Modules\Shared\Support\ModuleServiceProvider`. Auto-discovered by `app/Providers/ModuleServiceProvider.php`. No central registration.
- Provider declares: `name()`, `permissions()` (key => label), `navigation()` (`NavItem`), `dashboardWidgets()`, `seeders()`, `demoSeeders()`, `seedOrder()`, `notificationTypes()`, `listeners()`, `consoleCommands()`, `contracts()` (contract => impl singleton), `schedule()`.
- Auto-loaded: `Database/Migrations`, `Routes/web.php` (middleware web, auth, verified, active), `Routes/public.php` (web only), `resources/js/pages`.
- Layout: `Actions/ Enums/ Events/ Http/{Controllers,Requests} Listeners/ Models/ Notifications/ Policies/ Services/ Widgets/ Database/{Migrations,Factories,Seeders} Routes/ Tests/{Feature,Unit} resources/js/{pages,components}`.
- Factories: `Modules\X\Database\Factories\<Model>Factory` (auto-guessed).
- Cross-module access: only through `Modules\Shared\Contracts\*` (null impl in Shared, real impl bound by owner module) or events. Never import another module's internals, except Identity `User`, Employees `Employee`, AccessControl `Access`. Reports module may read other tables.
- After adding permissions: `php artisan access:sync`. Default role matrix: `modules/AccessControl/Database/Seeders/AccessControlSeeder.php`.

## Backend rules

- Authorize: `Gate::authorize('perm.key', $employee?)`. Scopes own/team/department/all resolved by `Access`. Lists: `->withinIds($access->visibleEmployeeIds($user, 'perm.key'))`.
- Business logic in single-purpose `Actions/*` (`handle()`), wrapped in `DB::transaction` when writing many rows.
- Rule violation: throw `Modules\Shared\Exceptions\DomainException($message, $field)` → validation error + toast.
- Toast: `Inertia::flash('toast', ['type' => 'success'|'error', 'message' => __('...')])`.
- Audit: events implement `Modules\Shared\Contracts\AuditableEvent` (`auditEntry(): AuditEntry`); wildcard listener persists.
- Notifications: extend `Modules\Shared\Notifications\PortalNotification` (`type()`, `title()`, `body()`, `url()`, `icon()`); add type to `notificationTypes()`.
- Do not shadow shared Inertia props (`auth`, `navigation`, `notifications`, `flash`, `name`, `sidebarOpen`) with page props.
- Money: `Modules\Shared\ValueObjects\Money` (integer minor units).
- Files: private disk `config('filesystems.default')`, stream via controller. Never public URLs.

## Frontend rules

- Pages: `modules/<Name>/resources/js/pages/<page>.tsx`, rendered as `Inertia::render('<page>')`. Set `Page.layout = { breadcrumbs: [...] }`.
- Shared kit: `@/components/portal/*` (PageHeader, PageBody, FilterBar, SearchInput, FilterSelect, OptionSelect, PaginationLinks, StatusBadge, EmptyState, ConfirmAction, FormField, StatCard, DynamicIcon), `@/hooks/use-can`, `@/lib/format`, types in `@/types/portal.ts`.
- Routes: Wayfinder only (`@/actions/Modules/...`, `@/routes/...`); regenerate with `php artisan wayfinder:generate --with-form`. No hard-coded URLs.
- Icons by name from server: add new names to `resources/js/components/portal/dynamic-icon.tsx`.
- Dashboard widget types: `stat`, `list`; add new via `registerWidgetType` in `modules/Dashboard/resources/js/components/widget-renderer.tsx`.
- Module-local components: relative imports.

## Testing

- Pest. Tests in `modules/<Name>/Tests/Feature`. Helpers (auto-loaded, `tests/Helpers.php`): `seedRoles()`, `userWithRole($role, $employeeAttrs)`, `userWithPermissions([...key => scope])`, `employeeOf($user)`, `forgetHierarchy()`.
- Inertia page assertions require the page file to exist.
- Local DB: sqlite. Prod: MySQL 8 + Redis.

## Gates (all must pass)

```bash
php artisan test --compact
vendor/bin/phpstan analyse --memory-limit=1G   # level 7, no ignores/baseline
vendor/bin/pint
pnpm types:check && pnpm run check && pnpm run build
```
