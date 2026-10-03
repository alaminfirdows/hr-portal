# Phase agent brief (common)

You implement one phase of the HR portal in an isolated git worktree. Phase 1 (foundation) is done and committed.

## Read first
1. `CLAUDE.md` (conventions — mandatory).
2. Your phase plan in `docs/superpowers/plans/`.
3. `docs/PROJECT_CONTEXT.md` sections for your domain; spec `docs/superpowers/specs/2026-10-03-hr-portal-design.md`.
4. Reference module: `modules/Employees` (actions, policies, presenter, widgets, notifications, events, tests) and `modules/Organization` (CRUD + dialog page `organization/departments/index.tsx`).
5. Shared contracts: `modules/Shared/Contracts/*`, null impls in `modules/Shared/Null/*`. Bind the real impl in your provider `contracts()` when your module owns it.

## Worktree setup (do first)
```bash
cp /Users/alamin/www/projects/hr-portal/.env .env
composer install --no-interaction -q
pnpm install --prefer-offline
touch database/database.sqlite && php artisan migrate --force -q
php artisan wayfinder:generate --with-form
```
Do NOT symlink vendor/node_modules from the main repo.

## Rules
- Stay inside your module dirs. Shared-file edits (Shared contracts, dynamic-icon.tsx, widget-renderer.tsx, AccessControlSeeder matrix, portal components) only if unavoidable — keep minimal, list each in your report.
- Permission keys must match the AccessControlSeeder matrix (it already lists leave/attendance/payroll/projects/documents/announcements keys; hr has `x.*` wildcards). If you add a key that a default role needs and the matrix lacks, add it to the matrix (report it).
- `seedOrder()`: pick > 50 (Employees is lower).
- TDD with Pest feature tests per plan, incl. every Review Focus item.
- Every page uses `@/components/portal/*` kit + Wayfinder. TS strict. No `any`.
- No phpstan ignores/baseline; fix root causes.
- Demo seeder per plan via `demoSeeders()`; must run on `php artisan migrate:fresh --seed`.

## Done = all gates pass in your worktree
```bash
php artisan test --compact
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint
pnpm types:check && pnpm run check:fix && pnpm run check && pnpm run build
php artisan migrate:fresh --seed --force
```
Then commit everything in the worktree (one or more commits, message `feat(<module>): ...`, trailer `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`).

## Report (concise)
Branch name + commit SHA, test count, files outside your module touched, deviations from plan, known gaps.
