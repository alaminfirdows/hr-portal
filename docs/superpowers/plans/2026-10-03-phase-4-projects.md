# Phase 4 — Projects Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Projects independent from org structure: members with roles + allocation, custom fields, visibility, activity feed.

**Architecture:** `modules/Projects`. Activity uses Audit (`AuditTrail` contract) with project as subject.

**Spec:** `docs/superpowers/specs/2026-10-03-hr-portal-design.md`; rules `docs/PROJECT_CONTEXT.md` §5 Projects.

## Global Constraints

- Only touch `modules/Projects/**`.
- Permissions: `projects.view`, `projects.create`, `projects.manage` (own = projects I manage / lead; all), `projects.members.manage`, `projects.fields.manage`.
- Important properties are real columns; custom fields only for extra metadata.

## Review Focus

1. Private project → hidden from non-members in list and show (404/403).
2. Restricted project → listed (name, status) but show page 403 for non-members.
3. Required custom field missing on save → validation error.
4. Member allocation total for an employee across active projects > 100% → warning (not block).
5. Removing the project manager from members is blocked.

---

### Task 1: Projects CRUD + visibility

- Migrations projects, project_members; enums `ProjectStatus`, `ProjectVisibility`, `ProjectPriority`, `MemberRole`; `Models\Project::scopeVisibleTo(User)`; policy; Actions `CreateProject`, `UpdateProject`, `ArchiveProject`; events Auditable.
- Pages `projects/index.tsx` (cards/table, filters status/visibility/mine), `projects/show.tsx` (tabs Overview, Members, Fields, Activity), `projects/create.tsx`, `projects/edit.tsx`.
- Tests: visibility matrix; PM own scope manage; employee cannot create.

### Task 2: Members

- Actions `AddProjectMember`, `UpdateProjectMember`, `RemoveProjectMember`; notifications `AddedToProject`; allocation warning.
- Tests: add/remove auth; duplicate member rejected; manager removal blocked; notification sent; "My projects".

### Task 3: Custom fields

- Migrations definitions + values; page `projects/fields/index.tsx`; dynamic inputs on project form; validation by type.
- Tests: types validation; required; select options enforced.

### Task 4: Activity, widgets, demo

- Activity tab from AuditTrail. Widgets: My projects (employee), Active projects count (HR/admin/PM). Demo: 8 projects, mixed visibility, members, field definitions (Client, Project Type, Technology).
