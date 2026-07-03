# Focused Audit Report: Manifester + Global Settings + Orchestrator (Task 1)

Date: 2026-03-30  
Scope: Data flow wiring audit for Manifester, Global Settings/Business Entity, REST/orchestrator apply, and frontend render.

## Manifester → storage

### Manifest/Airtable ingestion
- `lf_ai_studio_handle_manifest()` validates + normalizes uploaded manifest and stores it in `lf_site_manifest`. It then runs generation (`lf_ai_studio_run_generation()`), which later scaffolds business data.  
- `lf_ai_studio_airtable_generate_from_record_id()` builds a manifest from Airtable, validates it, and writes `lf_site_manifest` before triggering generation.

### Logo + CTA options
- Manifester logo save (`lf_ai_studio_handle_save_logo()`) writes `lf_global_logo` via `lf_update_global_option_value()` or `update_option('options_lf_global_logo', ...)`.
- Global Settings save (`lf_ops_handle_global_settings_save()`) writes:
  - `options_lf_global_logo`
  - `options_lf_header_cta_label`
  - `options_lf_header_cta_url`
  - (and syncs ACF option fields when available)

### Business Entity fields
Business Entity values are stored via `lf_update_business_info_value()` (guardrails), which writes to all ACF Business Info option post IDs plus raw options `options_{selector}` and `options_{post_id}_{selector}`.

Primary save paths:
- `lf_ai_studio_scaffold_manifest()` (after manifest generation) writes:
  - `lf_business_name`, `lf_business_legal_name`
  - `lf_business_phone_primary`, `lf_business_phone_tracking`, `lf_business_phone_display`, `lf_business_phone`
  - `lf_business_email`
  - `lf_business_address_street`, `lf_business_address_city`, `lf_business_address_state`, `lf_business_address_zip`, `lf_business_address`
  - `lf_business_service_area_type`, `lf_business_geo`, `lf_business_hours`, `lf_business_category`, `lf_business_short_description`
  - `lf_business_gbp_url`, `lf_business_social_facebook`, `lf_business_social_instagram`, `lf_business_social_youtube`, `lf_business_social_linkedin`, `lf_business_social_tiktok`, `lf_business_social_x`
  - `lf_business_same_as`, `lf_business_founding_year`, `lf_business_license_number`, `lf_business_insurance_statement`
  - `lf_business_place_id`, `lf_business_place_name`, `lf_business_place_address`, `lf_business_map_embed`
- `lf_ops_handle_global_settings_save()` writes the same `lf_business_*` values when saved from the admin Global Settings page.

## REST/orchestrator → apply → storage

### `/leadsforward/v1/orchestrator`
- Route: `lf_ai_studio_rest_orchestrator()` with HMAC required.
- Payload: uses `$payload['apply'] ?? $payload`, validates with `lf_ai_studio_validate_payload()`, then calls `lf_apply_orchestrator_updates()`.
- `lf_apply_orchestrator_updates()` expects `updates[]` entries and applies:
  - `target=options`, `id=homepage` → updates `LF_HOMEPAGE_CONFIG_OPTION`.
  - `target=post_meta` → updates per-post builder meta (`LF_PB_META_KEY`), featured images.
  - `target=faq` → `lf_ai_studio_apply_faq_updates()`.
  - `target=service_meta` → `lf_service_short_desc` meta/ACF field.
- It **does not** update Business Entity (`lf_business_*`) or global header options (`lf_global_logo`, `lf_header_cta_*`).

### `/leadsforward/v1/apply`
- Route: `lf_ai_studio_rest_apply()` with auth.
- Validation: `lf_ai_studio_validate_apply_payload()` expects `homepage` and/or `posts` payload (no `updates[]` array).
- Apply: `lf_ai_studio_apply_payload_strict()` updates `LF_HOMEPAGE_CONFIG_OPTION` and per-post `LF_PB_META_KEY`.

## Storage → frontend render

- Header logo/CTA reads:
  - `lf_get_global_option('lf_global_logo')`
  - `lf_get_global_option('lf_header_cta_label')`
  - `lf_get_global_option('lf_header_cta_url')`
- Header logo text reads `lf_get_option('lf_business_name', 'option')`, which delegates to `lf_get_business_info_value()` and falls back to `options_lf_business_*` when ACF is disabled.
- Business Entity rendering uses `lf_business_entity_get()` (guardrails-based reads from the same `lf_business_*` option keys).

## Mismatches / notes

- Manifest schema includes `global.global_cta_override` + `custom_global_cta` (validated/normalized), but no code applies these to CTA option storage. Header CTA therefore remains driven by global CTA options (`lf_header_cta_*`) and CTA defaults, not the manifest’s global CTA block.
- Orchestrator apply path only writes homepage/post/FAQ/service meta content. Business Entity + header logo/CTA are **not** updated by `/orchestrator`. If the workflow expects those fields to change during callback, they will not persist.

## Reset behavior alignment (Task 2)

### Findings
- Reset clears Global Settings (logo + header CTA), branding palette, homepage config options, and core content as intended.
- Business Entity reset used `lf_update_business_info_value()` but was gated by `update_field()` and skipped `lf_business_logo`, `lf_business_social_tiktok`, and `lf_business_social_x`.
- CTA options reset used `update_field()` only, so ACF-disabled environments would not clear `options_*` CTA values.
- Manifester settings (`lf_ai_studio_*`, `lf_ai_airtable_*`) remain preserved; runtime markers like `lf_ai_airtable_reviews_last_sync`/`lf_ai_airtable_reviews_last_imported` are still preserved but safe to reset if desired.

### Changes
- Reset now clears all Business Entity fields (including logo + TikTok/X) via `lf_update_business_info_value()` without the ACF gate (media IDs reset to `0`).
- CTA options now clear through `lf_update_cta_option_value()` so `options_*` values reset even when ACF is disabled.

## n8n workflow ↔ REST alignment (Task 3)

### Findings
- `GET /leadsforward/v1/blueprint` returns `business_entity`, `niche_profile`, `pages`, and `section_schema` (plus `homepage` and `internal_linking`).
- Auth requirements:
  - `/blueprint` + `/apply` allow legacy bearer token or HMAC (HMAC optional unless strict).
  - `/orchestrator` + `/airtable-webhook` require HMAC (legacy bearer rejected even in compatibility).
  - `/progress` requires HMAC unless compatibility binding auth passes (`job_id` + `request_id`).
- `/orchestrator` expects `updates[]` in the callback payload (direct or under `apply`) and supports `quality_warnings` + `media_annotations`; binding enforced via `job_id` + `request_id` with payload hash idempotency.
- `/progress` requires `job_id` + `request_id`, with optional `status`, `percent`, `step`, `message` (and optional `run_phase`).
- `/airtable-webhook` enqueues Airtable generation; queue processing builds a manifest (business/global/homepage/services/service_areas) and writes `lf_site_manifest`, which later seeds Business Entity + manifest inputs.
- `/apply` validates homepage/posts only; `updates[]` is not accepted there.

### Changes
- `docs/n8n-workflow.json`: progress updates in "Store Research Document" and "Split Blueprints + Deterministic Metadata" now include `request_id` and `run_phase` to satisfy `/progress` requirements.

## Unused / redundant code (Task 4)

### Findings
- No code removed in this pass. All reviewed helpers in the focused scope are referenced by hooks, admin flows, or orchestrator paths. Removing any would carry risk.

## Verification (Task 5)

- `php -l inc/ai-studio.php` ✓
- `php -l inc/ai-studio-rest.php` ✓
- `php -l inc/ops/menu.php` ✓
- `php -l inc/niches/reset-dev.php` ✓
