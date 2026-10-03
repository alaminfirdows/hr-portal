# Phase 5 — Collaboration (Documents + Announcements) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Central document management with visibility + sharing + expiry, and targeted announcements with read tracking.

**Architecture:** `modules/Documents`, `modules/Announcements`. Visibility resolution reuses `OrgHierarchy` (dept/team of viewer). Project visibility checks via `ProjectMembership` contract (Shared, null impl returns false; Projects binds real).

**Spec:** `docs/superpowers/specs/2026-10-03-hr-portal-design.md`; rules `docs/PROJECT_CONTEXT.md` §5–6.

## Global Constraints

- Only touch `modules/Documents/**`, `modules/Announcements/**`.
- Files on `config('filesystems.default')` private; never public URLs.
- Permissions: `documents.view`, `documents.upload`, `documents.manage`, `announcements.view`, `announcements.manage`.

## Review Focus

1. Department-visibility document opened by a user in another department → 403 on show and download.
2. External URL document → no file stored; link opened in new tab.
3. Upload > 20 MB or disallowed mime → validation error.
4. Announcement scheduled in future → not visible, not notified until publish time.
5. Expired announcement disappears from dashboard but stays in HR admin list.

---

### Task 1: Documents

- Migrations folders, documents, document_shares; enums `DocumentKind`, `DocumentVisibility`; `Models\Document::scopeVisibleTo(User)`; policy; Actions `UploadDocument`, `CreateLinkDocument`, `UpdateDocument`, `DeleteDocument`, `ShareDocument`, `CreateFolder`; download controller (stream or temporary URL); command `documents:notify-expiring` (30/7 days); notifications `DocumentShared`, `DocumentExpiring`.
- Pages `documents/index.tsx` (folder tree + table + filters category/tag/visibility, upload dialog), `documents/show.tsx`. Employee documents tab data endpoint (documents with employee_id) used by `employees/show` via its own section listing link.
- Tests: visibility matrix (private/department/team/project/organization/users); share; download auth; validation; expiring notifications once.

### Task 2: Announcements

- Migrations announcements, announcement_targets, announcement_reads; enums `Audience`, `Priority`; `scopeVisibleTo(User)`, `scopePublished()`; Actions `CreateAnnouncement`, `UpdateAnnouncement`, `DeleteAnnouncement`, `MarkAnnouncementRead`; command `announcements:dispatch` (every minute) → `AnnouncementPublished` notification to audience.
- Pages `announcements/index.tsx` (feed for everyone with unread badges), `announcements/show.tsx` (marks read), `announcements/manage.tsx` (HR CRUD with audience picker).
- Tests: audience targeting; scheduled; expiry; read tracking; dispatch once.

### Task 3: Widgets + demo

- Widgets: recent announcements (everyone), recent documents (employee), expiring documents (HR). Demo: folders HR/Company/Projects, 20 documents, 6 announcements.
