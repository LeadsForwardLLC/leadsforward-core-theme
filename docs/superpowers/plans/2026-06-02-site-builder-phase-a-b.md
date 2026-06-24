# Site Builder + Editor Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Phase A (Airtable → templated site + guidance + niche images, no n8n) then Phase B starter (section inspector + prominent per-section AI).

**Architecture:** New `inc/site-builder/` module called from Airtable AJAX `lf_ai_airtable_build_site`. Shared manifest prep extracted from `lf_ai_studio_airtable_generate_from_record_id`. Writer guidance replaces generic filler via dedicated fill pass. Niche packs are theme-bundled JSON + images under `assets/niche-packs/`.

**Tech Stack:** WordPress PHP theme, existing page builder (`lf_pb_config`), frontend editor (`inc/ai-assistant.php`), OpenAI via `lf_ai_completion` filter.

---

## Phase A — Site Builder

### Task 1: Site builder module skeleton

**Files:**
- Create: `inc/site-builder.php`
- Create: `inc/site-builder/writer-guidance.php`
- Create: `inc/site-builder/niche-media-pack.php`
- Modify: `functions.php` (load site-builder.php)
- Create: `assets/niche-packs/foundation-repair/pack.json`

- [ ] Add `lf_site_builder_run_from_manifest(array $manifest): array` orchestrating scaffold, ensure sections, media pack, guidance fill
- [ ] Add `lf_site_builder_fill_writer_guidance(): array` stats return
- [ ] Add foundation-repair pack.json (placeholder image assignments)

### Task 2: Extract Airtable manifest prep

**Files:**
- Modify: `inc/ai-studio-airtable.php`

- [ ] Add `lf_ai_studio_airtable_prepare_manifest_from_record_id(string $record_id): array`
- [ ] Refactor `lf_ai_studio_airtable_generate_from_record_id` to call prep + `lf_ai_studio_run_generation()`
- [ ] Add `lf_ai_studio_airtable_build_site_from_record_id()` calling prep + `lf_site_builder_run_from_manifest()`

### Task 3: AJAX + Manifester UI

**Files:**
- Modify: `inc/ai-studio-airtable.php` (`wp_ajax_lf_ai_airtable_build_site`)
- Modify: `inc/ai-studio.php` (Manifester steps copy + Build site button)
- Modify: `assets/js/ai-studio-airtable.js`

- [ ] New AJAX action `lf_ai_airtable_build_site`
- [ ] Primary button: Build site (templates)
- [ ] Keep secondary: Generate with AI (orchestrator)

### Task 4: Writer guidance styling

**Files:**
- Modify: `assets/css/` or inline in `inc/ai-assistant.php` / section render

- [ ] `.lf-writer-guidance` styles (subtle bordered callout, editor + front)

### Task 5: Verify + ship

- [ ] `php -l` on new files
- [ ] Manual: Airtable build without webhook returns success redirect
- [ ] Ship via PR

---

## Phase B — Editor (follow Phase A)

### Task 6: Section inspector panel

**Files:**
- Modify: `inc/ai-assistant.php`

- [ ] Inspector shows section type, intent, guidance excerpt, SEO bullets
- [ ] Prominent "Generate this section" on section hover toolbar

### Task 7: Preview parity audit

- [ ] Document gaps; fix highest-impact preview ≠ live mismatches

---

## Spec coverage check

| Spec requirement | Task |
|------------------|------|
| No n8n on template build | 2, 3 |
| Airtable manifest path | 2 |
| Niche templates | 1 (via ensure_core_page_sections) |
| Niche images | 1 |
| Writer guidance | 1, 4 |
| Manifester UI split | 3 |
| Section AI (B) | 6 |
