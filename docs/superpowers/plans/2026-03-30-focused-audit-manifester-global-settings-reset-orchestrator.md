# Focused Audit (Manifester + Global Settings + Reset + Orchestrator) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perform a focused wiring audit and fixes for Manifester, Global Settings/Business Entity, reset flow, orchestrator REST/apply path, and the n8n workflow without breaking behavior.

**Architecture:** Validate data flow end-to-end (Airtable/Manifester → REST/orchestrator → storage → frontend). Align n8n payload expectations with WP REST handlers. Apply only low-risk cleanups for unused/redundant code in this scope.

**Tech Stack:** WordPress PHP theme, n8n workflow JSON.

---

## File Map
**Modify:**
- `inc/ai-studio.php` (Manifester UI, manifest apply, orchestration logic)
- `inc/ai-studio-rest.php` (REST endpoints and auth)
- `inc/ops/menu.php` (Global Settings save/render)
- `inc/niches/reset-dev.php` (reset flow)
- `templates/parts/header.php` (logo/CTA render)
- `docs/n8n-workflow.json` (workflow assumptions)

**Reference/Read:**
- `inc/business-entity.php`
- `inc/guardrails.php`
- `docs/02_N8N_WORKFLOW_ARCHITECTURE.md`
- `docs/03_MANIFEST_SCHEMA.md`

**Create:**
- `docs/superpowers/audits/2026-03-30-focused-audit-report.md` (audit notes + findings)

---

### Task 1: Audit data flow wiring

**Files:**
- Read: `inc/ai-studio.php`, `inc/ai-studio-rest.php`, `inc/ops/menu.php`, `inc/business-entity.php`, `inc/guardrails.php`, `templates/parts/header.php`, `docs/n8n-workflow.json`

- [ ] **Step 1: Map Manifester → storage**
  - Identify save handlers for manifest inputs and Business Entity fields.
  - Confirm options keys used for logo/CTA and Business Entity values.

- [ ] **Step 2: Map REST/orchestrator → apply → storage**
  - Trace `/orchestrator` → `lf_apply_orchestrator_updates()` path and payload shape.
  - Trace `/apply` payload validation and apply handler.

- [ ] **Step 3: Map storage → frontend render**
  - Confirm header/logo/CTA reads from global options and business entity.

- [ ] **Step 4: Record findings**
  - Create audit notes doc with wiring map and mismatches (if any).

---

### Task 2: Reset behavior alignment

**Files:**
- Modify: `inc/niches/reset-dev.php`
- Read: `inc/guardrails.php`, `inc/ops/menu.php`, `inc/business-entity.php`

- [ ] **Step 1: Compare reset map to actual options**
  - Validate reset clears Business Entity fields + header CTA/logo.
  - Validate reset clears CTA options, branding palette options, and homepage config options per spec.
  - Confirm Manifester settings (webhook/Airtable/auth) are preserved.
  - Note runtime markers that are safe to reset (e.g., `lf_ai_airtable_reviews_last_sync`).
  - Verify ACF-disabled path still clears `options_*` via guardrails helpers.

- [ ] **Step 2: Implement minimal fix if mismatch found**
  - Only change clear/preserve lists if the current code diverges from spec.

- [ ] **Step 3: Update audit notes**
  - Log any reset changes and rationale.

- [ ] **Step 4: PHP lint**
  - Run: `php -l "inc/niches/reset-dev.php"`
  - Expected: `No syntax errors detected`

---

### Task 3: n8n workflow ↔ REST alignment

**Files:**
- Modify: `docs/n8n-workflow.json` (only if mismatch is confirmed)
- Read: `inc/ai-studio-rest.php`, `inc/ai-studio.php`, `docs/02_N8N_WORKFLOW_ARCHITECTURE.md`

- [ ] **Step 1: Compare required fields**
  - Verify `GET /leadsforward/v1/blueprint` returns `business_entity`, `niche_profile`, `pages`, `section_schema`.
  - Document auth requirements for each REST endpoint.
  - Ensure n8n callback payloads include `job_id`, `request_id`, and `updates[]` inside the `/orchestrator` apply payload (not `/apply`).
  - Verify `/orchestrator` supports `quality_warnings` and `media_annotations` in callback payloads.
  - Confirm progress payloads include required fields and optional `step`/`message`.
  - Confirm callback URL mapping and `job_id`/`request_id` binding.
  - Verify `/airtable-webhook` payload updates Business Entity + manifest inputs.
  - Confirm `/apply` does not accept `updates[]` (homepage/posts only).

- [ ] **Step 2: Adjust workflow if needed**
  - Apply the smallest possible change to fix mismatches.

- [ ] **Step 3: Update audit notes**
  - Document any workflow changes.

---

### Task 4: Remove unused/redundant code (safe only)

**Files:**
- Modify only when safe: `inc/ai-studio.php`, `inc/ops/menu.php`, `inc/ai-studio-rest.php`

- [ ] **Step 1: Identify candidates**
  - Search for functions/blocks with no references or hooks.

- [ ] **Step 2: Validate safety**
  - Ensure not referenced by `add_action`/`add_filter`, admin UI, or docs.

- [ ] **Step 3: Remove or keep**
  - Remove only when 100% safe; otherwise document as “kept.”

- [ ] **Step 4: Update audit notes**
  - Record removals or explicit keep rationale.

---

### Task 5: Verification + cleanup

- [ ] **Step 1: PHP lint touched files**
  - Run: `php -l "inc/ai-studio.php"`
  - Run: `php -l "inc/ai-studio-rest.php"`
  - Run: `php -l "inc/ops/menu.php"`
  - Run: `php -l "inc/niches/reset-dev.php"`

- [ ] **Step 2: Final audit summary**
  - Ensure audit report is complete and accurate.
  - Optional: dry Manifester apply to confirm Global Settings + header output changes.

---

### Task 6: Commit, push, PR

- [ ] **Step 1: Review git status/diff**
  - Run: `git status -sb`
  - Run: `git diff`

- [ ] **Step 2: Commit**
  - Stage relevant files and commit with message describing audit fixes.

- [ ] **Step 3: Push branch**
  - Run: `git push -u origin HEAD`

- [ ] **Step 4: Create PR**
  - Use `gh pr create` with Summary + Test Plan.

---

## Notes
- No automated test suite in this theme; rely on PHP lint + targeted functional reasoning.
- Avoid refactors outside the focused scope.
