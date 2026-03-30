---
title: Focused Audit: Manifester + Global Settings + Reset + Orchestrator
date: 2026-03-30
scope: focused
status: ready
---

## Overview
Perform a focused wiring audit of the Website Manifester, Global Settings/Business Entity, reset flow, orchestrator REST/apply path, and the n8n workflow. Remove only clearly unused or redundant code in this scope, without breaking behavior.

## Goals
- Verify data flow from Airtable/Manifester inputs through orchestrator apply into stored options and frontend render.
- Confirm reset clears Airtable-provided Business Entity data while preserving Manifester settings (API/webhook/Airtable).
- Ensure Global Settings, Manifester logo, and header CTA are consistently wired.
- Align n8n workflow assumptions with WP REST endpoints and apply behavior.
- Remove only dead or redundant code with high confidence.

## Non-Goals
- Broad refactors outside the focused scope.
- Behavioral changes to generation logic or schema.
- Large UI changes to Manifester or Global Settings.

## In-Scope Components
- `inc/ai-studio.php` (Manifester UI, manifest apply, orchestration)
- `inc/ai-studio-rest.php` (REST endpoints and auth)
- `inc/ops/menu.php` (Global Settings save/render)
- `inc/business-entity.php` + `inc/guardrails.php` (Business Entity storage)
- `inc/niches/reset-dev.php` (reset flow)
- `templates/parts/header.php` (logo/CTA render)
- `docs/n8n-workflow.json` (workflow structure/assumptions)

## Data Flow Map
1. Airtable/Manifest → Manifester form → `lf_ai_studio_manifest_to_setup_data()` → `lf_run_setup()`
2. Business Entity values → `lf_update_business_info_value()` → options storage
3. Frontend render uses `lf_business_entity_get()` and global options for logo/CTA
4. Orchestrator REST `/orchestrator` → apply → updates/field mapping → post meta/options
5. Reset flow clears site content and Business Entity data while preserving Manifester settings

## Audit Checklist
- Manifester fields saved correctly; no drift between UI and stored options.
- Global Settings save maps to same options used by render.
- Reset clears Airtable-provided Business Entity fields and global logo/CTA; Manifester settings preserved.
- Orchestrator apply path handles callbacks and updates correctly.
- n8n workflow node assumptions match REST payloads and callback endpoints.
- Verify no unused helpers or duplicate code in the focused scope.

## Removal Criteria
Only remove code if ALL are true:
- No references (search across theme).
- Not hooked via `add_action` / `add_filter`.
- Not referenced by docs in this scope.
- Not used in Manifester/Global Settings/Orchestrator paths.

## Risks
- Removing code used by hidden hooks or admin-only flows.
- Reset behavior differences when ACF is disabled.

## Assumptions / Open Questions
- Airtable-to-Manifester import is the source of Business Entity + logo/CTA defaults; reset should clear those.
- Orchestrator payload schema aligns with `docs/n8n-workflow.json` nodes and callback routing.
- ACF may be disabled in some environments; options fallbacks must still reset correctly.

## Verification
- PHP lint on touched files.
- Inspect REST endpoints and payloads:
  - `GET /leadsforward/v1/blueprint`
  - `POST /leadsforward/v1/orchestrator`
  - `POST /leadsforward/v1/progress`
  - `POST /leadsforward/v1/apply`
  - `POST /leadsforward/v1/airtable-webhook`
- Confirm callback URL mapping and request_id/job_id binding between n8n and WP.
- Optional: run a dry Manifester manifest apply and observe Global Settings + header changes.

## Deliverables
- Small code changes limited to scope.
- Audit notes (issues + removals).
- Commit, push, and PR for squash/merge.
