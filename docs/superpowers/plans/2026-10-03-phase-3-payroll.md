# Phase 3 — Payroll Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Configurable payroll: components, compensation history, monthly runs with state machine, payslips + PDF, export.

**Architecture:** `modules/Payroll`. Engine `PayrollCalculator` pure service fed by `PayrollContext` (basic, days, attendance/leave data from `AttendanceSummary`, `LeaveCalendar`, `WorkCalendar`). Formulas via Symfony ExpressionLanguage.

**Spec:** `docs/superpowers/specs/2026-10-03-hr-portal-design.md`; rules `docs/PROJECT_CONTEXT.md` §6 Payroll.

## Global Constraints

- Only touch `modules/Payroll/**`.
- Money integer minor units (`Money` VO). Never floats in DB.
- Permissions: `payroll.view` (own → payslips; all → everything), `payroll.manage`, `payroll.approve`.
- Approver ≠ run creator.

## Review Focus

1. Employee joining mid-month → basic pro-rated by paid days.
2. Formula referencing unknown variable → run stays draft, error names component.
3. Employee viewing another employee's payslip → 403.
4. Re-calculating a calculated run replaces payslips (no duplicates).
5. Net below zero → clamped to 0 and flagged on payslip.

---

### Task 1: Components, tax slabs, compensation

- Migrations pay_components, employee_pay_components, tax_slabs, compensations, payroll_adjustments; enums `ComponentType`, `CalculationMethod`; CRUD pages `payroll/components/index.tsx`, `payroll/tax-slabs/index.tsx`, compensation on `payroll/employees/show.tsx` (salary history + assign components + adjustments). Event `CompensationChanged` (Auditable).
- Tests: CRUD auth; compensation effective_to closed on new compensation; formula validation on save (compile with allowed variables).

### Task 2: Engine

- `Services/FormulaEvaluator`, `Services/TaxCalculator` (progressive slabs), `Services/PayrollCalculator::calculate(PayrollContext): PayslipResult`, `Data/PayrollContext`, `Data/PayslipResult`.
- Unit tests: fixed, percent_of_basic, formula with variables, tax slabs progressive, unpaid days deduction, pro-rata, adjustments, net clamp.

### Task 3: Runs + payslips

- Migrations payroll_runs, payslips, payslip_lines; enum `PayrollRunStatus` with allowed transitions; Actions `CreatePayrollRun`, `CalculatePayrollRun`, `MarkReviewed`, `ApprovePayrollRun`, `ProcessPayrollRun` (publishes, notifies `PayslipAvailable`), events Auditable; CSV export; PDF payslip (dompdf view `payroll::payslip`).
- Pages `payroll/runs/{index,show}.tsx`, `payroll/payslips/{index,show}.tsx` (my payslips).
- Tests: state transitions valid/invalid; approver≠creator; recalc replaces; process notifies; employee sees only own published payslips; PDF download 200 + content type; CSV export.

### Task 4: Widgets + demo

- Widgets: latest payslip (employee), payroll status of current period (HR).
- Demo: components (House rent 50% basic, Medical fixed, Transport fixed, Overtime formula `overtime_hours * basic / 208 * 1.5`, Tax slab, Provident fund 10% basic), slabs, compensations for all, one processed run for last month.
