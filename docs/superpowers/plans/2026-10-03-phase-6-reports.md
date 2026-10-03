# Phase 6 — Reports & Automation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Read-side reports for HR, attendance, leave, payroll, projects with filters and CSV export; role-aware dashboard polish.

**Architecture:** `modules/Reports` — each report is a class implementing `Report { key(); title(); permission(); filters(): array; columns(): array; query(array $filters, User $user): Builder|Collection; chart(): ?array }`. One controller renders any registered report; CSV streams via `StreamedResponse`. Reports may read other modules' tables (documented exception).

**Spec:** `docs/superpowers/specs/2026-10-03-hr-portal-design.md`.

## Global Constraints

- Only touch `modules/Reports/**`.
- Reports respect scope: `reports.view` scope limits employees via `Access::visibleEmployeeIds`.
- Payroll reports require `payroll.manage`.

## Review Focus

1. Manager with `reports.view` team → report rows limited to team.
2. Empty date range → sensible default (current month).
3. CSV with commas/quotes/unicode names → valid CSV.
4. Unknown report key → 404.
5. Payroll report for employee role → 403.

---

### Task 1: Report framework

- `Contracts/Report`, `ReportRegistry`, `ReportController@index|show|export`, pages `reports/index.tsx` (catalog grouped), `reports/show.tsx` (filters, table, optional bar chart).
- Tests: registry, auth, CSV escaping, 404.

### Task 2: Reports

- HR: headcount by department, joiners/leavers in period, turnover rate.
- Attendance: summary per employee (present/late/absent/overtime) for period; late list.
- Leave: usage per type per department; balances.
- Payroll: run summary by component; salary distribution by department.
- Projects: active projects with member counts; employee workload (allocation sum).
- Tests per report: correct aggregates on factory data; scope filter.

### Task 3: Automation polish

- Schedule audit: verify all scheduled commands registered (`schedule:list` test). Upcoming birthdays widget, leave reminder to approvers with pending > 2 days (`leave:remind-approvers` daily).
