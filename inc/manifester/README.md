# LeadsForward AI Manifester (theme package → future plugin)

This folder isolates **orchestrated site generation** (Airtable → manifest → n8n → WordPress REST apply) from the **theme builder** (Page Builder, niche library, paste import, front-end editor).

## Contents

| File | Role |
|------|------|
| `bootstrap.php` | Loads all manifester modules |
| `ai-studio.php` | Manifest Website admin UI, jobs, manifest apply, payload build |
| `ai-studio-airtable.php` | Airtable project import, reviews sync, generation queue |
| `ai-studio-rest.php` | REST: `/orchestrator`, `/apply`, `/blueprint`, Airtable webhook |
| `ai-studio-identity.php` | Business identity guard on callbacks |
| `ai-studio-orchestrator-utils.php` | Split apply, force_apply, repair scopes |
| `ai-studio-wiring.php` | Dev wiring checks |
| `assets/` | Manifest Website admin CSS/JS |
| `docs/` | n8n workflow + manifest schema references (canonical — see `docs/DOCUMENTATION_MAP.md`) |

## Theme builder (stays in theme `inc/`)

- `page-builder.php`, `sections.php`, `page-content-importer.php`
- `niches/content-library.php`, `site-builder.php`
- `business-entity.php`, Global Settings (NAP, branding)

## Extracting to a plugin later

1. Copy this folder into `leadsforward-manifester/` plugin.
2. Register REST routes and admin menu from plugin bootstrap.
3. Theme exposes hooks: `lf_manifest_applied`, `lf_get_site_manifest()`.
4. Theme keeps Page Builder apply targets; plugin owns outbound webhook + inbound auth.
