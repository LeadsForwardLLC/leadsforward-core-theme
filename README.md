# LeadsForward Core Theme

Ultra-lightweight, SEO-first WordPress theme for local lead-gen sites. Built to scale across thousands of sites. No page builder; infrastructure and conversion-focused homepage only.

- **Version:** 0.1.0  
- **Text domain:** `leadsforward-core`  
- **Requires PHP:** 8.0+  
- **Requires:** WordPress 6.0+, Advanced Custom Fields (ACF) for full functionality  

---

## Overview

LeadsForward Core provides:

- **Custom post types:** Services, Service Areas, Testimonials, FAQs (all REST-ready)
- **Business Entity:** Single source of truth for NAP, phones, service areas, and schema
- **Global settings & branding:** Logo + color tokens mapped to CSS variables (core, surface, text)
- **Shared section registry:** Universal section definitions + defaults used by homepage and page builders
- **Image system:** Media Library–only images with Unsplash placeholder seeding
- **Icon system:** Heroicons inline SVGs with per-section controls + niche defaults
- **Homepage builder:** Drag/drop order, per-section toggles, backgrounds, and copy (Hero controls match Page Builder)
- **Page Builder Framework:** Instance-based sections for core pages, posts, services, and service areas
- **Navigation:** Header menu auto-built after setup with a non-clickable “More” dropdown plus Call Now and CTA actions
- **Quote Builder:** Full-screen modal with multi-step flow, GHL webhook delivery, and first-party analytics
- **AI Assistant (bounded):** Safe copy suggestions + field edits only (no layout/CSS changes)
- **AI Studio:** Orchestrator-driven site content generation (no OpenAI keys stored)
- **Server-rendered blocks:** Hero, Trust/Reviews, CTA, FAQ Accordion, Map+NAP
- **SEO & schema:** JSON-LD (LocalBusiness, Organization, WebSite, BreadcrumbList, Service, FAQPage, Review), canonical, noindex, NAP/geo helpers
- **Heading rules:** Enforced single H1 + heading hierarchy validation (warnings only)
- **Internal linking engine:** Deterministic anchors + hub-and-spoke modules (services/areas)
- **SEO coverage validator:** Site Health checks for missing hubs, thin pages, and orphans
- **Controlled variation:** Site-wide profile (A–E), block variant registry, safe section ordering, style tokens, copy template slots (no randomness)
- **Setup wizard:** Niche-aware init; seeds pages, CPTs, menus, page builder defaults, and copy templates
- **Safety:** CPT delete protection, admin notices for missing SEO-critical fields, graceful fallback when ACF is off
- **SOP:** Step-by-step build process in `docs/SOP.md`

---

## SOP

The full, non-technical build checklist lives here: `docs/SOP.md`.

---

## Requirements

- **WordPress** 6.0+
- **PHP** 8.0+
- **Advanced Custom Fields (ACF)** — required for options UI, CPT fields, and blocks. The theme degrades without ACF (options UI and custom fields unavailable).

---

## Directory Structure

```
leadsforward-core-theme/
├── assets/
│   ├── css/          # editor.css, variation-tokens.css, future front-end CSS
│   ├── js/           # quote-builder.js, lf-section-sortable.js
│   ├── icons/        # Local Heroicons SVGs (inline usage)
│   └── images/
├── docs/             # SOP.md and product documentation
├── inc/
│   ├── setup.php        # Theme support, menus, ACF options pages
│   ├── cleanup.php      # Emoji/oEmbed/dashicons removal, optional block CSS
│   ├── performance.php  # Defer scripts, heartbeat, head cleanup, critical CSS hook
│   ├── business-entity.php # Business entity single source of truth
│   ├── icons.php        # Icon helpers + niche defaults
│   ├── seo.php          # Canonical, noindex, NAP/geo, breadcrumbs, internal links
│   ├── schema.php       # JSON-LD: LocalBusiness, Organization, WebSite, BreadcrumbList, Service, FAQPage, Review
│   ├── images.php       # Placeholder images + media helpers
│   ├── sections.php     # Shared section registry + renderers
│   ├── homepage.php     # Homepage config + CTA resolution
│   ├── homepage-admin.php # Homepage builder UI
│   ├── page-builder.php # Service + service area page builder UI + renderer
│   ├── quote-builder.php # Quote modal + admin configuration
│   ├── ai-assistant.php # AI assistant settings + guardrails
│   ├── branding.php     # Branding tokens → CSS variables
│   ├── guardrails.php   # lf_get_option, CPT protect, admin notices, migrations
│   ├── acf/
│   │   ├── options-business.php   # Business Name, Phone, Email, NAP, Hours
│   │   ├── options-ctas.php       # Primary/secondary CTA defaults
│   │   ├── options-schema.php     # Schema on/off toggles
│   │   ├── options-branding.php   # Branding colors (CSS vars)
│   │   ├── options-variation.php  # Variation profile, copy template selects
│   │   ├── field-group-service-area.php
│   │   ├── field-group-testimonial.php
│   │   └── field-group-faq.php
│   ├── ai-editing/
│   │   ├── admin-ui.php  # AI Assistant UI
│   │   └── provider-openai.php # OpenAI provider + errors
│   ├── blocks/
│   │   ├── register.php  # ACF block registration, lf_render_block_template()
│   │   └── variants.php  # Block variant registry, profile defaults
│   ├── variation-tokens.php # Body class, data-variation, CSS var enqueue
│   ├── variation-copy.php   # lf_copy_template(), copy template definitions
│   ├── niches/
│   │   ├── registry.php     # Niche definitions (services, pages, CTA defaults)
│   │   ├── setup-runner.php # Pages/CPTs/menus + page builder defaults
│   │   └── wizard.php       # Admin wizard UI, completion flow
│   └── cpt/
│       ├── services.php
│       ├── service-areas.php
│       ├── testimonials.php
│       └── faqs.php
├── templates/
│   ├── parts/        # header, footer, cta, content, content-none, related-*
│   └── blocks/       # hero, trust-reviews, service-grid, cta, faq-accordion, map-nap
├── style.css
├── theme.json
├── functions.php     # Bootstrap only; loads inc/*
├── header.php
├── footer.php
├── index.php
├── page.php
├── front-page.php    # Homepage: sections from ACF or defaults
├── single-lf_*.php
├── archive-lf_*.php
└── README.md
```

---

## LeadsForward Admin (Settings)

Under **LeadsForward**:

| Page | Purpose |
|------|--------|
| **Setup** | Setup wizard, API keys (Google Maps/OpenAI), admin bar toggle, reset tools. |
| **Global Settings** | Business Entity + Logo + Branding colors (core/surface/text). |
| **Homepage** | Homepage builder: section order, toggles, backgrounds, copy, CTA actions. |
| **Quote Builder** | Builder config plus integrations + analytics panels. |
| **AI Assistant** | Bounded copy tools (text-only changes, confirmations). |
| **AI Studio** | Orchestrator-driven “Generate Site Content” with job logging. |
| **Config** | Export/Import config. |
| **Schema** | Schema toggles and outputs. |
| **Variation** | Variation profile and copy templates (A–E). |
| **Site Health** | SEO coverage + pre-launch checks. |

---

## Controlled variation (site-wide)

Set once per site in **LeadsForward → Variation**. No runtime randomness; all changes are deterministic and admin-controlled.

- **Variation profile:** A = Clean + Minimal, B = Bold + High Contrast, C = Trust Heavy, D = Service Heavy, E = Offer/Promo Heavy. Drives block variant defaults and style tokens.
- **Block variant registry** (`inc/blocks/variants.php`): `lf_get_block_variant($block_name, $override_variant)` returns the variant to use (valid override > profile default > `default`). Trust Heavy pushes hero variant with review emphasis and trust block early; Service Heavy pushes service grid earlier.
- **Safe section ordering:** When **Auto-order homepage sections** is on, only the middle sections (trust, services, CTA, FAQ, map) are reordered by profile. Hero always first; last section always last. URL structure, H1, and schema are unchanged.
- **Token variation** (`assets/css/variation-tokens.css`): Body gets `data-variation="A"` (etc.) and class `variation-profile-a`. CSS variables: `--lf-spacing-density`, `--lf-button-radius`, `--lf-heading-weight`, `--lf-accent-*`. Profiles map to spacing/radius/weight presets.
- **Copy template slots:** Dropdown-selected templates for hero headline, CTA microcopy, trust badge label. `lf_copy_template($key, $fallback, $context)` returns the template string with placeholders replaced (e.g. `{business_name}`, `{service}`, `{city}`). Hero and CTA blocks use these when no manual override.
- **Footprint hygiene:** Body classes and `data-variant` on block sections so markup differs slightly per profile/variant without affecting schema or internal linking.

---

## Homepage System

- **Template:** `front-page.php` (static front page).
- **Sections:** Configured in **LeadsForward → Homepage** via drag/drop.
  - Shared section registry in `inc/sections.php`.
  - Per-section toggle, background, and copy fields.
- **Hero controls:** Variant selector + CTA toggles/actions (same options as Page Builder).
  - CTA actions: `quote`, `call`, `link`.
- **Media sections:** Content with Image + Image with Content use Media Library images with a placeholder fallback.
- **Defaults:** If no config exists, a conversion-optimized default order is seeded.
- **CTA resolution:** Section overrides → Homepage overrides → Global defaults.
- **Phone linking:** When CTA action is `call`, uses Business Info phone for a `tel:` link.

---

## CTA Intelligence

- **Global default:** LeadsForward → Global Settings (CTAs).
- **Homepage override:** LeadsForward → Homepage.
- **Section override:** Per-section in the homepage builder and page builder settings.
- **Canonical resolver:** `lf_resolve_cta()` (section > homepage > global).
- **Primary action:** `quote` | `call` | `link`. `quote` opens the modal.
- **Primary type:** `text` | `call` | `form`. `call` uses Business Info phone for `tel:` link; `form` shows GHL embed when set.
- **GHL:** Stored once per scope (global, homepage, or section); no duplicated embed code.

---

## AI Studio

- **Location:** LeadsForward → AI Studio.
- **Role:** Advanced homepage regeneration and debug.
- **Inputs:** Webhook URL + shared secret.
- **Samples:** Uses selected `docs/content-samples/*.md` from Setup Wizard plus optional admin samples.
- **Flow:** Build homepage blueprint → send to orchestrator → validate payload → apply to homepage fields.
- **Jobs:** Logged with status, user, time, and summary. Retry resends same payload.
- **REST endpoints (secret auth):**
  - `GET /wp-json/leadsforward/v1/blueprint`
  - `POST /wp-json/leadsforward/v1/apply`
  - Header: `Authorization: Bearer <shared_secret>`

---

## Homepage Generation Flow

- **Where:** LeadsForward → Setup (single guided flow).
- **Inputs:** Business info, niche, city/region, homepage keywords, hero variant, writing samples, variation profile.
- **Trigger:** “Generate homepage now” calls the orchestrator and applies homepage-only updates.
- **Storage:** `lf_homepage_keywords` + `lf_homepage_writing_samples` + homepage hero variant stored in homepage config.
- **Regenerate:** Use LeadsForward → AI Studio (Advanced) to re-run homepage generation.

---

## Page Builder Framework (Core Pages + Service + Service Area)

- **Meta key:** `lf_pb_config` stores instance-based sections and order.
- **Shared sections:** From `inc/sections.php` (hero, trust, benefits, process, FAQ, CTA, related, map, service grid, reviews, blog posts, etc).
- **Admin UI:** Right-side Section Library, add/remove sections, per-section settings, drag to reorder.
- **Renderer:** `lf_pb_render_sections()` respects section order and enabled state.
- **Media sections:** Content with Image + Image with Content (shared renderer, layout modifier).
- **SEO overrides:** Meta title/description + noindex for pages, posts, services, and service areas.

---

## Regression checks

- Existing saved homepage configs still render without edits.
- Page Builder sections render correctly after save/reload.

---

## Branding System

- **Global CSS tokens:** Core, surface, and text colors mapped to CSS variables.
- **Single source of truth:** `inc/branding.php` outputs variables; CSS + `theme.json` consume them.
- **Goal:** Safe, consistent theming without per-section overrides.

---

## Image System

- **Media Library only:** All section images are stored as attachment IDs (no external URLs).
- **Placeholder seeding:** `inc/images.php` seeds a default Unsplash image into the Media Library and stores `lf_placeholder_image_id`.
- **Fallback behavior:** If a section image is unset, it uses the placeholder attachment ID.

---

## Quote Builder

- **Modal:** Full-screen, multi-step quote flow (CTA action `quote`).
- **Admin:** Structured configuration in **LeadsForward → Quote Builder** (Builder, Integrations, Analytics panels).
- **Integrations:** GHL webhook delivery (toggle + URL + pipeline/tags/source).
- **Analytics:** First-party aggregated funnel metrics (no PII).

---

## AI Assistant

- **Scope:** Copy-only suggestions and edits (no layout, CSS, or slugs).
- **Safety:** Predefined actions with confirmation + reversal.

---

## Blocks (Server-Rendered)

All blocks are PHP-rendered via `templates/blocks/*.php`. Available in the block editor under category **LeadsForward**.

| Block | Template | Notes |
|-------|----------|--------|
| Hero | hero | Homepage-only variants: Authority Split, Conversion Stack, Form First, Visual Proof |
| Trust/Reviews | trust-reviews | Pulls from lf_testimonial; max items from section or attribute |
| Service Grid | service-grid | Links to lf_service posts |
| CTA | cta | Resolved CTA stack; optional GHL embed; call link when type=call |
| FAQ Accordion | faq-accordion | Pulls from lf_faq |
| Map+NAP | map-nap | NAP from Business Info; geo data for map placeholder |

Blocks receive optional `$block['context']` when rendered from the homepage (section overrides, index). Use `loading="lazy"` for any images added in block templates.

---

## Custom Post Types

| CPT | Slug | Use |
|-----|------|-----|
| Service | `lf_service` | `/services/service-name/`; rendered by the Page Builder framework |
| Service Area | `lf_service_area` | `/service-areas/city-name/`; rendered by the Page Builder framework |
| Testimonial | `lf_testimonial` | Private; reviewer name, rating, review text, source (Google/Facebook/etc.) |
| FAQ | `lf_faq` | `/faqs/`; question, answer, optional service/area association |

All use `show_in_rest => true` and clean rewrites.

---

## SEO & Schema

- **Schema (JSON-LD):** LocalBusiness (global), Service (single service), FAQPage (single/archive FAQ or filter), Review/AggregateRating (from testimonials). Toggleable in LeadsForward → Schema; fails silently if data is incomplete.
- **Canonical:** Output in `wp_head`. Use `add_filter('lf_output_canonical', '__return_false')` to let Rank Math (or another plugin) handle it.
- **Noindex:** Applied to search, 404, and testimonial single/archive.
- **NAP/Geo:** `lf_nap_data()`, `lf_nap_plain()`, `lf_nap_html()`, `lf_geo_data()`, `lf_geo_meta()`; internal linking helpers `lf_related_services_for_area()`, `lf_related_areas_for_service()`; `lf_breadcrumb_items()` for Rank Math–compatible breadcrumbs.

---

## Performance & Safety

- **Queries:** Block queries use `no_found_rows => true` where applicable; sections load only when used on the homepage.
- **No unnecessary JS:** No front-end JS unless required for a feature.
- **Images:** Use `loading="lazy"` for any images in block templates.
- **Guardrails:** Core CPTs cannot be permanently deleted (trash only). Admin notices warn when Business Name, Phone, or other SEO-critical fields are missing.

---

## Setup wizard

After theme activation, **Appearance → LeadsForward Setup** runs a one-time flow:

1. **Select niche** — Roofing, Plumbing, HVAC, or General (from `inc/niches/registry.php`). Each niche defines core services, default CTA copy, recommended variation profile, and homepage section order.
2. **Business info (NAP)** — Name, phone, email, address, opening hours. Saved to global business info options and editable in LeadsForward settings.
3. **Confirm services & service areas** — Services come from the niche; add service areas (one per line, optional `City, ST`). Creates `lf_service` and `lf_service_area` posts.
4. **Variation profile** — Pre-selected from niche; can override.
5. **Generate site** — Creates pages (Home, About Us, Our Services, Our Service Areas, Reviews, Blog, Sitemap, Contact, Privacy Policy, Terms of Service, Thank You), sets Home as front page, creates Header and Footer menus and assigns them, seeds service ↔ service area relationships, updates options (NAP, CTAs, variation, schema, homepage config), and seeds **page builder defaults** for core pages, services, and areas. Idempotent: existing pages/CPTs by slug are reused; no duplicates.

Completion is stored in option `lf_setup_wizard_complete`. The wizard does not show again unless you use “Show wizard again” (which clears the flag). No frontend JS, no cron; all actions are explicit and logged in the runner return value.

---

## Extending

- **New niche:** Add an entry to `lf_get_niche_registry()` in `inc/niches/registry.php` with `name`, `slug`, `services`, `required_pages` (optional), `homepage_section_order`, `variation_profile`, `cta_primary_default`, `cta_secondary_default`, `schema_review_enabled`. No change to wizard or runner logic required.
- **New section type:** Add to `lf_sections_registry()` in `inc/sections.php`, include defaults, and update homepage/page builder admin UI as needed for new fields.
- **New placeholders:** Update `LF_PLACEHOLDER_IMAGE_URL` in `inc/images.php` and re-seed.
- **New block:** Register in `inc/blocks/register.php` and add a template in `templates/blocks/`.
- **FAQ schema on custom pages:** Use filter `lf_faq_schema_items` to pass FAQ items.
- **Breadcrumbs:** Filter `lf_breadcrumb_items` to adjust or extend items.

---

## Changelog

- **0.1.0** — Foundation: CPTs, ACF options, blocks, SEO/schema, modular homepage, CTA resolution, documentation.
