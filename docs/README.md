# LeadsForward Docs Index

Use this page as the starting point for architecture, operations, and troubleshooting.

## Start Here
- `01_SYSTEM_OVERVIEW.md` - high-level system map and execution phases.
- `05_THEME_INTEGRATION.md` - WordPress integration points, callback/apply path, repair safeguards.
- `02_N8N_WORKFLOW_ARCHITECTURE.md` - n8n node flow, quality gates, callback/progress contract.

## Data Contracts
- `03_MANIFEST_SCHEMA.md` - canonical manifest input structure.
- `04_SECTION_SCHEMA.md` - section definitions and allowed field behavior.
- `06_AI_PROMPT_ENGINE.md` - prompt and generation constraints.

## Frontend / UX
- `08_FRONTEND_EDITOR.md` - sidepanel/assistant editing behavior and controls.
- `07_ICON_SYSTEM.md` - icon picker and icon taxonomy usage.

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
