# Documentation map (source of truth)

Use this page to find the **one** place to read or edit for each topic. Avoid duplicating content across README, markdown, and the wp-admin playbook unless you update all layers in the same PR.

## Three layers

| Layer | Location | Audience | Update when |
|-------|----------|----------|-------------|
| **Operator playbook (live)** | `inc/docs-playbook.php`, sidebar in `inc/docs-content.php` | Writers, PMs, fleet ops | Any user-facing workflow, nav contract, import, troubleshooting |
| **Developer markdown** | `docs/*.md` | Engineers | Architecture, contracts, integration, SEO system |
| **Manifester / n8n** | `inc/manifester/docs/` | Orchestration engineers | Webhook payload, n8n JSON, manifest schema |

Public route: `/theme-docs/` renders the same playbook as wp-admin **LeadsForward → Theme Documentation**.

## Topic → canonical source

| Topic | Read / edit |
|-------|-------------|
| Writer import workflow | Playbook `#import-page-content` + `docs/templates/*.txt` |
| Fleet page templates & publish rules | Playbook `#fleet-page-templates` + `inc/page-template-defaults.php` |
| Header nav fleet contract | `.cursor/rules/header-nav-fleet-contract.mdc` + playbook `#header-navigation` + `inc/header-nav-policy.php` |
| Airtable cron & living site | Playbook `#airtable-live-sync` + `docs/10_SITEMAP_SYNC.md` |
| Reviews page gating | Playbook `#projects-reviews` + `inc/fleet-pages.php` |
| Section fields & rendering | `docs/04_SECTION_SCHEMA.md` + `inc/sections.php` |
| Homepage niche layouts | `docs/HOMEPAGE_NICHE_BLUEPRINTS.md` + `inc/niches/homepage-blueprints.php` |
| Page Builder meta & AI creation JSON | `docs/09_PAGE_BUILDER_MAPS_NAV_AI.md` |
| WordPress apply path & fleet updates | `docs/05_THEME_INTEGRATION.md` |
| n8n workflow export | **`inc/manifester/docs/n8n-workflow.json`** (only copy in repo) |
| Manifest JSON schema | **`inc/manifester/docs/03_MANIFEST_SCHEMA.md`** |
| n8n architecture | **`inc/manifester/docs/02_N8N_WORKFLOW_ARCHITECTURE.md`** |
| Vision step (images) | **`inc/manifester/docs/N8N_VISION_STEP_SPEC.md`** |
| Manifester PHP code | **`inc/manifester/ai-studio.php`** (not `inc/ai-studio.php` stub) |
| Team AI/SEO orientation | `docs/LF-TEAM-AI-SEO-REVIEW-PACK.md` |
| SEO/AI hardening roadmap | `docs/SEO_AI_WORKFLOW_HARDENING.md` |
| Release notes (ops) | `docs/TEAM_CHANGELOG.md` |
| GitHub onboarding | `README.md` (short) + `CONTRIBUTING.md` |
| Cursor ship workflow | `.cursor/rules/theme-ship-workflow.mdc` |

## Stubs and deprecated paths

| Path | Status |
|------|--------|
| `docs/02_N8N_WORKFLOW_ARCHITECTURE.md` | Redirect stub → edit `inc/manifester/docs/` copy only |
| `docs/03_MANIFEST_SCHEMA.md` | Redirect stub → edit `inc/manifester/docs/` copy only |
| `inc/ai-studio.php` | Deprecated re-export → use `inc/manifester/` |
| `docs/archive/` | Historical plans; do not update for current behavior |
| `templates/lf-docs.php` | **Removed** — use `templates/lf-docs-standalone.php` |
| `docs/09_SITEMAP_SYNC.md` | **Renamed** → `docs/10_SITEMAP_SYNC.md` |

## Version alignment

On each release, keep in sync:

- `functions.php` → `LF_THEME_VERSION`
- `style.css` → `Version:` header
- `README.md` → version line
- `docs/TEAM_CHANGELOG.md` → new entry
- Optional: `inc/docs-dev.php` → recent highlights pointer

See `docs/00_PRODUCTION_READINESS.md` for the pre-launch checklist.

## Intentional overlap (keep in sync)

These triples should agree after nav or fleet page changes:

1. `.cursor/rules/header-nav-fleet-contract.mdc`
2. Playbook `#header-navigation`
3. `LF_HEADER_MENU_STRUCTURE_VERSION` in code

Writer templates (`docs/templates/`) must match Import Page Content UI and playbook import section.

## What not to merge

- Do **not** expand `README.md` into a second playbook.
- Do **not** copy n8n JSON into `docs/` — single file in `inc/manifester/docs/`.
- Do **not** revive `docs/archive/superpowers/` for active work; open a new spec in `docs/` or manifester docs instead.
