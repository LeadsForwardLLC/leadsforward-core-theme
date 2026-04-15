---
title: "Frontend Editor + Header Reliability (v0.1.46) — design"
date: 2026-04-15
owner: LeadsForward Core
status: draft
---

## Context
The front-end editor and header controls are still failing in production:
- AI Assistant fails to open on the front end because `wp-i18n` inline runs before `wp`.
- `service_intro` selections disappear on refresh and the empty state removes the “Add service” control.
- Header layout selector does not change visuals; top bar only appears in the `topbar` layout.
- Phone icon alignment remains visually off.
- Layout history needs a safer preview flow for multi-user editing.
- Header/History/SEO/Structure floats should not appear in wp-admin.

## Goals
1. AI Assistant opens reliably on the front end even when scripts are reordered.
2. `service_intro` add/remove selections persist on front-end builder and keep the add control visible.
3. Header layouts visibly change; top bar is tied to the “Show promo top bar” toggle across layouts.
4. Add a top bar background color picker using the same brand swatches/custom picker UX as section backgrounds.
5. Phone icon aligns vertically with “Call Now” text.
6. Layout history gains a non-destructive preview mode.
7. No editor floats render in wp-admin.

## Non-goals
- Rewriting the editor system or header architecture.
- Full diff/merge tooling for revisions.
- New layout history data model.

## Proposed Changes

### 1) AI Assistant boot reliability (front end only)
- **Root cause:** `wp-i18n` inline script throws `wp is not defined` on the front end.
- **Plan:** Remove `wp-i18n`/`wp-hooks` dependencies on the front end and rely on `lfAiFloating.i18n` only.
- **Implementation:**
  - In `lf_ai_assistant_assets`, only enqueue `wp-i18n`/`wp-hooks` in `is_admin()` contexts.
  - Ensure `lf-ai-floating-assistant` registers without those deps on front end.
- **Outcome:** `wp-i18n-js-after` no longer executes on front end; assistant boots normally.

### 2) Service Intro persistence + empty state control
- **Root cause:** empty state template renders text but no editor controls; save pipeline misses the empty grid state.
- **Plan:**
  - Always render an editor control wrapper for `service_intro` when editor mode is on.
  - When empty, show “Add service” button beneath the empty text.
  - Ensure the selection save is executed on add/remove and when closing the picker.
- **Implementation:**
  - In `buildServiceIntroReorderControls`, inject controls even when no cards exist.
  - On `closeServicePicker`, re-save IDs if the section is empty.
- **Outcome:** users can re-add services after clearing; selections persist on refresh.

### 3) Header layouts + top bar behavior
- **Top bar:**
  - Show top bar when `Show promo top bar` is enabled on any layout.
  - Store top bar background color in a new global option `lf_header_topbar_color`.
- **Layouts:**
  - Implement actual CSS layout variants for `.site-header--modern` and `.site-header--centered`.
  - Treat `topbar` as a styling variant layered on top of the chosen layout.
- **Implementation:**
  - Update header render logic to compute `$show_topbar` based on `topbar_enabled` only.
  - Update `header-settings.php` helpers to include top bar color getter/sanitizer.
  - Add layout CSS blocks for `site-header--centered` and `site-header--modern`.

### 4) Top bar color picker (front-end header panel)
- Add a color picker row to the Header panel using the same UI as section background pickers:
  - Brand swatches.
  - Custom color input (hex/rgb).
- Persist to `lf_header_topbar_color`.
- Apply via inline style or CSS variable to `.site-header__topbar`.

### 5) Phone icon alignment
- Adjust `.site-header__phone-icon` to use `line-height: 0` + explicit `height`/`width` on the icon and align text baseline.
- Ensure SVG aligns to center with `display: block` and `vertical-align: middle`.

### 6) Layout history preview
- Add “Preview” button per revision.
- Preview loads the revision into a **temporary preview mode**:
  - Query param `?lf_preview_revision=<id>`.
  - On render, swap the section config with the preview snapshot for this request only.
  - Display a banner “Previewing revision — Restore to apply / Exit preview”.
- Restore still performs the persistent write.

### 7) Hide editor floats on wp-admin
- Remove `admin_enqueue_scripts` and `admin_footer` hooks for assistant floats.
- Keep admin-side functionality limited to front-end editor usage.

## Data Model / Options
- `lf_header_topbar_color` (string): top bar background color (brand slug or custom hex).

## Testing Plan
- Add CLI tests to assert:
  - `wp-i18n` not enqueued on front end.
  - Service intro controls render even in empty state.
  - Header top bar logic does not require layout `topbar`.
  - Preview mode swaps config when query param present.
  - Phone icon CSS rules exist.

## Rollout
- Bump theme version to `0.1.46`.
- Ship via standard `ship-to-live.sh` flow.
- Verify on front-end: AI assistant opens; service intro add persists; header layout + top bar visible; preview button works.
