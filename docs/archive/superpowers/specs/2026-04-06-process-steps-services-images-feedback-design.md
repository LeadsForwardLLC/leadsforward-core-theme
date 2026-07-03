---
title: "Process Steps + Service-page AI writing + Image optimization + Feedback system"
date: 2026-04-06
status: approved
---

## Goals

- **Process Steps** behave like **FAQs**: selectable, ordered, and page-relevant (homepage + per-service).
- **Service pages** receive real AI-written content (not just homepage), primarily into the **section builder**, plus SEO excerpts/short descriptions.
- **Images** imported/assigned by AI get **relevant metadata** (alt/title/caption/description) and **newly imported images are compressed/optimized**.
- **Header logo** is sized appropriately (larger, responsive).
- Provide a **user-testing feedback workflow** for internal testers to submit feedback tied to their WP user account, with admin approval/rejection and a log for iteration.

## Non-goals

- Bulk recompressing the entire existing Media Library.
- Introducing an external dependency/service for image compression (use WP’s built-in pipeline).

## Process Steps (CPT) — data model and behavior

### Data model

- Keep existing CPT: `process_steps`.
- Add taxonomy: `process_group` (or similar) used for targeting.
  - Terms represent **service slugs** and/or shared groupings (e.g. `roof-repair`, `commercial-roofing`, `homepage-primary`).
- Add per-page manual selection storage:
  - Service pages: store selected step IDs in post meta (ordered array).
  - Homepage: store selected step IDs in homepage section config.

### Selection rules

- If a page has **manual picks**, render those in order.
- If not, **auto-pick** by taxonomy terms that match the service/page context.
- Provide a max count setting consistent with the section’s design.

### Admin UX

- Add a selector UI similar to FAQs:
  - Searchable list of Process Steps, multi-select, supports ordering.
  - Works on Service edit screens and Homepage Builder section.

### Frontend

- Process section renders from selected Process Steps:
  - Title/body pulled from the Process Step CPT fields.
  - Keeps existing section heading/intro fields in the section config.

## Service pages — AI writing target

### Where AI writes

- Primary: Service page **section builder meta** (same approach as homepage sections).
- Secondary: update **excerpt/short description** fields for SEO.

### Orchestrator apply behavior

- Accept legacy/unknown section IDs/fields without failing the callback:
  - Drop unknown section/field updates with debug logging.
  - Never fail the whole apply for a single legacy key.

## Images — metadata + compression on import

### Metadata updates

For AI-imported (and AI-assigned) images, automatically set:

- Alt text
- Attachment title
- Caption
- Description

Values should be derived from the page/section context and the business/location tokens (already available in orchestration payloads).

### Compression/optimization

- Only optimize **newly imported** images (not existing library items).
- Use WordPress image editor / generated sizes pipeline so it works in SiteGround and standard WP environments.

## Header logo sizing

- Increase header logo max height/width in theme styles.
- Ensure responsive behavior (doesn’t overflow, remains crisp).

## User-testing feedback system

### Data model

- CPT: `lf_feedback`
- Fields stored as post meta:
  - `user_id` (required)
  - `page_url` / `context` (where the feedback happened)
  - `category` (bug/ux/content/other)
  - `severity` (low/med/high)
  - `expected`, `actual`, `repro_steps`
  - optional attachments (media IDs)
  - `admin_note`
- Status taxonomy or post meta status:
  - `new`, `approved`, `rejected`

### Submission UX

- An admin-area page for testers with a clean form.
- Submission automatically ties to the current WP user.
- After submission, user sees confirmation and can view their own submissions.

### Admin moderation UX

- Admin list table supports filtering by status.
- Admin can approve/reject and add a note.

## Rollout/verification

- Add minimal logging for drops and apply counts.
- Verify:
  - Process Steps selectable and render on homepage and services.
  - Orchestrator writes to service pages.
  - New AI-import images get metadata + are optimized.
  - Header logo is larger.
  - Feedback submissions are stored, tied to users, and moderatable.

