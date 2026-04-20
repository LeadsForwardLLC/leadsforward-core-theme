# LeadsForward Docs Index

Use this page as the starting point for architecture, operations, and troubleshooting.

## Theme v0.1.46 (highlights)

- **Header controls that actually work:** Modern/Centered layouts now have real styling, and the promo top bar stays visible based on enabled + text (not layout).
- **Promo top bar color:** Add a global top bar background color (brand swatches + custom input) from the front-end Header panel.
- **Service Intro empty-state saving:** “+ Add service” remains available when empty, and an empty selection can be saved intentionally.
- **Revision History preview:** Preview a restore point non-destructively before you restore it.
- **AI assistant boot hardening:** Front-end assistant avoids wp-i18n dependency issues and prevents early i18n inlines from breaking boot.

## Start Here
- `00_PRODUCTION_READINESS.md` - pre-launch checklist, fleet/cron notes, version alignment.
- `01_SYSTEM_OVERVIEW.md` - high-level system map and execution phases.
- `05_THEME_INTEGRATION.md` - WordPress integration points, callback/apply path, repair safeguards.
- `02_N8N_WORKFLOW_ARCHITECTURE.md` - n8n node flow, quality gates, callback/progress contract.

## Data Contracts
- `03_MANIFEST_SCHEMA.md` - canonical manifest input structure.
- `04_SECTION_SCHEMA.md` - section definitions and allowed field behavior.
- `06_AI_PROMPT_ENGINE.md` - prompt and generation constraints.

## Frontend / UX
- `PERFORMANCE_SEO_CONVERSION_ROADMAP.md` - prioritized performance, technical SEO, and conversion backlog (instrumentation through fleet ops).
- `08_FRONTEND_EDITOR.md` - sidepanel/assistant editing, layout history, rich-text icon shortcodes, shortcuts.
- `09_PAGE_BUILDER_MAPS_NAV_AI.md` - Page Builder meta (`lf_pb_config`), map iframe vs optional Maps API key, header menu “add on save”, AI creation `page_builder` JSON contract.
- `07_ICON_SYSTEM.md` - icon picker and icon taxonomy usage.
- `TEAM_CHANGELOG.md` - internal changelog for the team (ops-focused summaries).

## Deploy / updates
- `05_THEME_INTEGRATION.md` - includes **Fleet theme updates** (private controller channel, HMAC, signed zips, WP-Cron behavior, optional **controller push** to `POST /wp-json/lf/v1/fleet/push` on each client).
- **Push trigger / force install:** Operators use **LeadsForward → Theme Docs** (playbook) for *Push update* steps and the optional **force install** control (sends `override: true`, same idea as rollout override on *Check now*—only when you mean to bypass gating). API/body details: `05_THEME_INTEGRATION.md`.

## Theme CPTs (quick reference)
- **`lf_faq`** — FAQ accordion selections (`faq_selected_ids`).
- **`lf_process_step`** — reusable process steps; homepage/Page Builder process section can reference IDs in `process_selected_ids` (see `inc/sections.php` and `04_SECTION_SCHEMA.md`).
The in-dashboard **LeadsForward → Theme Docs** playbook is the most up-to-date operator guide for admin URLs and workflows; repo markdown here is for developers and orchestration.

## Image Intelligence
- `SOP_IMAGE_INTELLIGENCE_WORKFLOW.md` - operational SOP for image upload/matching/assignment.
- `N8N_VISION_STEP_SPEC.md` - optional n8n vision annotation contract.
- `AI_CONTEXT.md` - image naming strategy and editor context notes.

## Operational Truths (Current)
- A clean run may execute once (`initial`) or twice (`initial` + single `repair`).
- More than one repair pass is intentionally blocked.
- n8n progress/callback payloads should include `run_phase` for easier debugging.
- WordPress callback binding is validated via `job_id` + `request_id` + payload idempotency checks.
- Production callback auth should use header/HMAC flows; query token auth is disabled by default in production.
- Builder niche UX is intentionally constrained to: foundation-repair (default), roofing, pressure-washing, tree-service, hvac, windows-doors, remodeling, paving.

## Quick Troubleshooting
1. If you see two executions: check whether second payload has `run_phase: repair` or `repair_only: true`.
2. If content did not apply: verify callback returned `success: true` and dry-run is disabled.
3. If repeated repair loops appear: inspect job meta for `lf_ai_job_parent`, `lf_ai_job_repair`, `lf_ai_job_requeue_count`.
4. If progress seems ambiguous: inspect `/progress` payload fields including `run_phase`, `step`, and `percent`.
