# LeadsForward Docs Index

Starting point for **developers** and orchestration engineers. Operators should use wp-admin **LeadsForward → Theme Docs** (or `/theme-docs/`) — that playbook is more current for day-to-day workflows.

**Documentation map:** [`DOCUMENTATION_MAP.md`](DOCUMENTATION_MAP.md) — which file is canonical for each topic.

**Theme version:** 0.1.177 (see `LF_THEME_VERSION` in `functions.php`).

## Recent fleet highlights (0.1.176 area)

- Fleet page templates finalized for all core marketing URLs; writer templates in `docs/templates/`.
- Header nav fleet contract enforced (Home → Services → Service Areas → About → Call → Free Estimate → More).
- Canonical page slugs (`about-us`, `why-choose-us`, `services`) prevent duplicate pages from setup vs Airtable.
- Reviews page publishes only when ≥1 published `lf_testimonial`; immediate sync on testimonial save/trash.
- Operator playbook expanded: import workflow, fleet templates, header nav, Airtable live sync.

Full release notes: [`TEAM_CHANGELOG.md`](TEAM_CHANGELOG.md).

## Start here

| Doc | Purpose |
|-----|---------|
| [`00_PRODUCTION_READINESS.md`](00_PRODUCTION_READINESS.md) | Pre-launch checklist, version alignment, cron |
| [`01_SYSTEM_OVERVIEW.md`](01_SYSTEM_OVERVIEW.md) | System map, orchestrator phases, storage keys |
| [`05_THEME_INTEGRATION.md`](05_THEME_INTEGRATION.md) | WP apply path, fleet updates, repair safeguards |
| [`LF-TEAM-AI-SEO-REVIEW-PACK.md`](LF-TEAM-AI-SEO-REVIEW-PACK.md) | Team-facing AI/SEO review (no code required) |
| [`SEO_AI_WORKFLOW_HARDENING.md`](SEO_AI_WORKFLOW_HARDENING.md) | SEO/AI remediation phases (engineering) |

## Data contracts

| Doc | Purpose |
|-----|---------|
| [`03_MANIFEST_SCHEMA.md`](03_MANIFEST_SCHEMA.md) | Stub → **`inc/manifester/docs/03_MANIFEST_SCHEMA.md`** |
| [`04_SECTION_SCHEMA.md`](04_SECTION_SCHEMA.md) | Section registry and field behavior |
| [`06_AI_PROMPT_ENGINE.md`](06_AI_PROMPT_ENGINE.md) | Orchestrator prompt constraints |

## n8n / manifester (canonical paths)

| Asset | Path |
|-------|------|
| Workflow export | **`inc/manifester/docs/n8n-workflow.json`** |
| Architecture | **`inc/manifester/docs/02_N8N_WORKFLOW_ARCHITECTURE.md`** |
| Manifest schema | **`inc/manifester/docs/03_MANIFEST_SCHEMA.md`** |
| Vision step spec | **`inc/manifester/docs/N8N_VISION_STEP_SPEC.md`** |
| PHP implementation | **`inc/manifester/ai-studio.php`** (package: `inc/manifester/`) |

`docs/02_*` and `docs/03_*` are redirect stubs only — do not duplicate content there.

## Frontend / UX

| Doc | Purpose |
|-----|---------|
| [`08_FRONTEND_EDITOR.md`](08_FRONTEND_EDITOR.md) | Front-end editor, shortcuts, history |
| [`09_PAGE_BUILDER_MAPS_NAV_AI.md`](09_PAGE_BUILDER_MAPS_NAV_AI.md) | `lf_pb_config`, maps, menu assist, AI `page_builder` JSON |
| [`09_SITEMAP_SYNC.md`](09_SITEMAP_SYNC.md) | Airtable sitemap → pages, keywords, menu |
| [`07_ICON_SYSTEM.md`](07_ICON_SYSTEM.md) | Tabler icon runtime |
| [`PERFORMANCE_SEO_CONVERSION_ROADMAP.md`](PERFORMANCE_SEO_CONVERSION_ROADMAP.md) | Backlog (not shipped spec) |

## Image intelligence

| Doc | Purpose |
|-----|---------|
| [`SOP_IMAGE_INTELLIGENCE_WORKFLOW.md`](SOP_IMAGE_INTELLIGENCE_WORKFLOW.md) | Operational SOP |
| [`inc/manifester/docs/N8N_VISION_STEP_SPEC.md`](../inc/manifester/docs/N8N_VISION_STEP_SPEC.md) | Vision annotation contract |
| [`AI_CONTEXT.md`](AI_CONTEXT.md) | Naming and editor context |

## Writer templates (runtime)

`docs/templates/*-content-template.txt` — loaded by Import Page Content (`inc/page-content-importer.php`). Not optional copies; keep in sync with page blueprints.

## Archive

[`archive/`](archive/) — superseded release notes and historical superpowers plans/audits. **Do not use for current behavior.**

## Operational truths (orchestrator)

- A clean run may execute once (`initial`) or twice (`initial` + single `repair`); more than one repair pass is blocked.
- n8n progress/callback payloads should include `run_phase` for debugging.
- Production callback auth should use header/HMAC; query token auth is disabled by default in production.
- Builder niche UX is limited to: foundation-repair (default), roofing, pressure-washing, tree-service, hvac, windows-doors, remodeling, paving.

## Quick troubleshooting (orchestrator)

1. Two executions: check whether the second payload has `run_phase: repair` or `repair_only: true`.
2. Content did not apply: verify callback `success: true` and dry-run is off.
3. Repair loops: inspect job meta for `lf_ai_job_parent`, `lf_ai_job_repair`, `lf_ai_job_requeue_count`.
4. Ambiguous progress: inspect `/progress` for `run_phase`, `step`, `percent`.

For site ops (nav, duplicate pages, reviews gating), use the wp-admin playbook troubleshooting section.
