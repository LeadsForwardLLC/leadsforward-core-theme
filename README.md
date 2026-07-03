# LeadsForward Core Theme

Ultra-lightweight, SEO-first WordPress theme for local lead-gen sites at fleet scale. Page layouts use a **theme-native Page Builder** (section registry + `lf_pb_config`) alongside the block editor where needed—not a third-party page builder plugin.

- **Version:** 0.1.177 (`LF_THEME_VERSION` in `functions.php`; keep `style.css` in sync)
- **Text domain:** `leadsforward-core`
- **Requires:** WordPress 6.0+, PHP 8.0+, Advanced Custom Fields (ACF)

---

## Where to read documentation

| Audience | Start here |
|----------|------------|
| **Writers & operators** | wp-admin **LeadsForward → Theme Docs** (live playbook) or `/theme-docs/` on the site |
| **Developers** | [`docs/README.md`](docs/README.md) and [`docs/DOCUMENTATION_MAP.md`](docs/DOCUMENTATION_MAP.md) |
| **Team AI/SEO review** | [`docs/LF-TEAM-AI-SEO-REVIEW-PACK.md`](docs/LF-TEAM-AI-SEO-REVIEW-PACK.md) |
| **n8n / manifester** | [`inc/manifester/docs/`](inc/manifester/docs/) (canonical orchestration docs) |
| **Contributing & ship** | [`CONTRIBUTING.md`](CONTRIBUTING.md) — branch → PR → squash-merge to `main` |

Do **not** treat this README as the full operator manual. The wp-admin playbook (`inc/docs-playbook.php`) is the source of truth for fleet pages, header nav, import workflow, and Airtable sync.

---

## What the theme provides (summary)

- **CPTs:** Services, service areas, projects, reviews (testimonials), FAQs, process steps
- **Fleet core pages:** Home, services, service areas, about, why choose us, FAQ, contact, reviews (gated), plus service CPT templates
- **Business entity & branding:** NAP, schema, logo, design tokens, global CTAs
- **Import Page Content:** Writer `.docx` / paste templates in `docs/templates/` (runtime dependency)
- **SEO engine:** Per-URL meta, sitemap, JSON-LD, coverage reports, internal link map
- **Airtable:** Sitemap sync, reviews sync, optional AI manifester (n8n)
- **Front-end editor:** Inline editing for admins on the live site
- **Fleet updates:** Optional private theme update channel

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- ACF or ACF Pro (theme degrades gracefully without it, but options and fields are limited)

---

## Repo layout (high level)

```
leadsforward-core-theme/
├── assets/           CSS, JS, Tabler icons, section default images
├── docs/             Developer markdown + writer import templates
├── inc/              Theme PHP (setup, SEO, page builder, fleet, manifester bootstrap)
│   └── manifester/   Orchestration package (future plugin boundary)
├── templates/        PHP templates and block partials
├── tests/            PHP regression tests (run from theme root)
├── scripts/          ship-to-live.sh (commit → PR → merge)
└── functions.php     Bootstrap
```

---

## Developer doc index (numbered)

Read in order for orchestration and integration:

1. [`docs/01_SYSTEM_OVERVIEW.md`](docs/01_SYSTEM_OVERVIEW.md)
2. [`docs/02_N8N_WORKFLOW_ARCHITECTURE.md`](docs/02_N8N_WORKFLOW_ARCHITECTURE.md) → stub; canonical copy in `inc/manifester/docs/`
3. [`docs/03_MANIFEST_SCHEMA.md`](docs/03_MANIFEST_SCHEMA.md) → stub; canonical copy in `inc/manifester/docs/`
4. [`docs/04_SECTION_SCHEMA.md`](docs/04_SECTION_SCHEMA.md)
5. [`docs/05_THEME_INTEGRATION.md`](docs/05_THEME_INTEGRATION.md)
6. [`docs/06_AI_PROMPT_ENGINE.md`](docs/06_AI_PROMPT_ENGINE.md)
7. [`docs/07_ICON_SYSTEM.md`](docs/07_ICON_SYSTEM.md)
8. [`docs/08_FRONTEND_EDITOR.md`](docs/08_FRONTEND_EDITOR.md)
9. [`docs/09_PAGE_BUILDER_MAPS_NAV_AI.md`](docs/09_PAGE_BUILDER_MAPS_NAV_AI.md)
10. [`docs/09_SITEMAP_SYNC.md`](docs/09_SITEMAP_SYNC.md)

Release notes for operators: [`docs/TEAM_CHANGELOG.md`](docs/TEAM_CHANGELOG.md).

Historical plans and audits: [`docs/archive/`](docs/archive/) (not kept in sync with shipped code).

---

## Ship workflow

From theme root (requires `gh` CLI):

```bash
./scripts/ship-to-live.sh "short description"
```

Merging to `main` triggers deploy to SiteGround staging via GitHub Actions.
