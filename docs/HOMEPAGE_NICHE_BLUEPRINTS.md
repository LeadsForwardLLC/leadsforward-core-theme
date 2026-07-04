# Homepage niche blueprints

Homepage layout and default copy are driven by **niche slug** from Airtable → `lf_homepage_niche_slug` → `inc/niches/homepage-blueprints.php`.

## How it works

1. **Airtable / site setup** calls `lf_homepage_apply_niche_config( $niche_slug, $wizard_data )`.
2. Blueprint resolves section **order**, **enabled flags**, and **copy defaults** for that niche.
3. Stored in `lf_homepage_section_order` and `lf_homepage_section_config`.
4. **Import Page Content** (`home` schema) uses the same order for writer `.docx` / `.txt` templates.
5. Hero inline form title/button copy comes from blueprint `inline_form` keys.

Unknown niches fall back to **`core-contractor`** (shorter generic layout). **`foundation-repair`** uses the full calm conversion layout.

## Foundation repair section map

| Spec section | Theme block | Notes |
|--------------|-------------|-------|
| Utility bar | Header topbar (`lf_header_topbar_*`) | Set on niche apply when empty |
| Hero + form | `hero` (conversion) + inline quote card | Form copy from blueprint |
| Trust metrics | `trust_bar` | Minimal strip |
| Problem signs | `service_details` | Checklist of warning signs |
| Mentor / authority | `image_content_b` | Photo left, copy right |
| Core services | `service_intro` | Pulls from Services CPT |
| Before/after | `project_gallery` | Projects CPT / JobCapturePro later |
| 3-step process | `process` | |
| Reviews | `trust_reviews` | Reviews CPT |
| Financing | `pricing` | `financing_enabled` on |
| Why choose us | `benefits` | |
| Service areas | `map_nap` | |
| FAQ | `faq_accordion` | |
| Final CTA | `cta` | |

Sections not in the default foundation order (comparison table, blog resources) remain available in the section library but are omitted for performance unless enabled manually.

## Writer template

`docs/templates/home-content-template.txt` — section headers map via PCI aliases (`=== MENTOR ===` → `image_content_b`, etc.).

## Code references

- `inc/niches/homepage-blueprints.php` — registry and apply helpers
- `inc/homepage.php` — `lf_homepage_default_order()`, `lf_homepage_apply_niche_config()`
- `inc/page-content-importer-schemas.php` — dynamic `home` order
- `templates/blocks/hero.php` — niche inline form args

## Adding a niche

1. Add registry entry in `inc/niches/registry.php` (services list, CTAs).
2. Either add a dedicated blueprint in `homepage-blueprints.php` or rely on `core-contractor` fallback.
3. Optionally add a writer template variant under `docs/templates/`.
