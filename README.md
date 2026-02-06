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
- **ACF-driven options:** Global business info, CTAs, schema toggles, **homepage sections**
- **Server-rendered blocks:** Hero, Trust/Reviews, Service Grid, CTA, FAQ Accordion, Map+NAP
- **Modular homepage:** Section order and overrides via Theme Options → Homepage (no hardcoded layout)
- **SEO & schema:** JSON-LD (LocalBusiness, Service, FAQPage, Review), canonical, noindex, NAP/geo helpers
- **Controlled variation:** Site-wide profile (A–E), block variant registry, safe section ordering, style tokens, copy template slots (no randomness)
- **Safety:** CPT delete protection, admin notices for missing SEO-critical fields, graceful fallback when ACF is off

---

## Requirements

- **WordPress** 6.0+
- **PHP** 8.0+
- **Advanced Custom Fields (ACF)** — required for Theme Options, CPT fields, blocks, and homepage sections. The theme degrades without ACF (no options UI, blocks/sections not available).

---

## Directory Structure

```
leadsforward-core-theme/
├── assets/
│   ├── css/          # editor.css, variation-tokens.css, future front-end CSS
│   ├── js/
│   └── images/
├── inc/
│   ├── setup.php     # Theme support, menus, ACF options pages
│   ├── cleanup.php   # Emoji/oEmbed/dashicons removal, optional block CSS
│   ├── performance.php # Defer scripts, heartbeat, head cleanup, critical CSS hook
│   ├── seo.php       # Canonical, noindex, NAP/geo, breadcrumb helpers
│   ├── schema.php    # JSON-LD: LocalBusiness, Service, FAQPage, Review
│   ├── homepage.php  # Section registry, default sections, CTA resolution, section renderer
│   ├── guardrails.php # lf_get_option, CPT protect, admin notices
│   ├── acf/
│   │   ├── options-business.php   # Business Name, Phone, Email, NAP, Geo, Hours
│   │   ├── options-ctas.php      # Primary/secondary CTA, type (call/form/text), GHL embed
│   │   ├── options-schema.php    # Schema on/off toggles
│   │   ├── options-homepage.php  # Homepage CTA overrides + flexible content sections
│   │   ├── options-variation.php # Variation profile, auto-order, copy template selects
│   │   ├── field-group-service.php
│   │   ├── field-group-service-area.php
│   │   ├── field-group-testimonial.php
│   │   └── field-group-faq.php
│   ├── blocks/
│   │   ├── register.php  # ACF block registration, lf_render_block_template()
│   │   └── variants.php  # Block variant registry, lf_get_block_variant(), profile defaults
│   ├── variation-tokens.php # Body class, data-variation, CSS var enqueue
│   ├── variation-copy.php   # lf_copy_template(), copy template definitions
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

## Theme Options (ACF)

Under **Theme Options**:

| Page | Purpose |
|------|--------|
| **Business Info** | Business name, phone, email, address (NAP), geo coordinates, opening hours. Used for schema, NAP output, and call CTAs. |
| **CTAs** | Primary CTA text, primary type (text / call / form), secondary CTA text, default GHL form embed. Single source for embed; no duplication. |
| **Schema** | Toggles for Organization, LocalBusiness, FAQ, and Review schema output. |
| **Homepage** | Homepage primary/secondary CTA and GHL overrides; primary CTA type override; **Homepage sections** (flexible content). |
| **Variation** | **Variation profile** (A–E), **Auto-order sections** toggle, **Hero headline style**, **CTA microcopy style**, **Trust badge style** (dropdown templates). |

---

## Controlled variation (site-wide)

Set once per site in **Theme Options → Variation**. No runtime randomness; all changes are deterministic and admin-controlled.

- **Variation profile:** A = Clean + Minimal, B = Bold + High Contrast, C = Trust Heavy, D = Service Heavy, E = Offer/Promo Heavy. Drives block variant defaults and style tokens.
- **Block variant registry** (`inc/blocks/variants.php`): `lf_get_block_variant($block_name, $override_variant)` returns the variant to use (valid override > profile default > `default`). Trust Heavy pushes hero variant with review emphasis and trust block early; Service Heavy pushes service grid earlier.
- **Safe section ordering:** When **Auto-order homepage sections** is on, only the middle sections (trust, services, CTA, FAQ, map) are reordered by profile. Hero always first; last section always last. URL structure, H1, and schema are unchanged.
- **Token variation** (`assets/css/variation-tokens.css`): Body gets `data-variation="A"` (etc.) and class `variation-profile-a`. CSS variables: `--lf-spacing-density`, `--lf-button-radius`, `--lf-heading-weight`, `--lf-accent-*`. Profiles map to spacing/radius/weight presets.
- **Copy template slots:** Dropdown-selected templates for hero headline, CTA microcopy, trust badge label. `lf_copy_template($key, $fallback, $context)` returns the template string with placeholders replaced (e.g. `{business_name}`, `{service}`, `{city}`). Hero and CTA blocks use these when no manual override.
- **Footprint hygiene:** Body classes and `data-variant` on block sections so markup differs slightly per profile/variant without affecting schema or internal linking.

---

## Homepage System

- **Template:** `front-page.php` (used when a static page is set as the front page in Settings → Reading).
- **Sections:** Configured in **Theme Options → Homepage → Homepage sections**. Each row:
  - **Section type:** Hero, Trust/Reviews, Service Grid, CTA, FAQ Accordion, Map+NAP
  - **Layout variant:** Default, A, B, or C (structure only; styling later)
  - **Section-level overrides:** e.g. Hero headline/subheadline/CTA, Trust max items, CTA primary/secondary/GHL
- **Defaults:** If no sections are set, the theme renders a conversion-optimized order: Hero → Trust → Service Grid → CTA → FAQ → CTA (variant B) → Map+NAP.
- **CTA resolution:** Section overrides → Homepage overrides → Global (Theme Options → CTAs). GHL embed is resolved the same way; only one embed is output per CTA section.
- **Phone linking:** When primary CTA type is “Call”, the primary CTA text is wrapped in a `tel:` link using Business Info phone. Works on all devices; no duplicate embed.

---

## CTA Intelligence

- **Global default:** Theme Options → CTAs (primary text, type, secondary, GHL).
- **Homepage override:** Theme Options → Homepage (primary, secondary, GHL, primary type).
- **Section override:** Per-section in the flexible content (Hero CTA override; CTA section: primary, secondary, GHL).
- **Primary type:** `text` | `call` | `form`. `call` uses Business Info phone for `tel:` link; `form` shows GHL embed when set.
- **GHL:** Stored once per scope (global, homepage, or section); no duplicated embed code.

---

## Blocks (Server-Rendered)

All blocks are PHP-rendered via `templates/blocks/*.php`. Available in the block editor under category **LeadsForward**.

| Block | Template | Notes |
|-------|----------|--------|
| Hero | hero | Headline/subheadline/CTA; homepage section overrides; call link when type=call |
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
| Service | `lf_service` | `/services/service-name/`; SEO H1, short/long content, CTA override, related service areas |
| Service Area | `lf_service_area` | `/service-areas/city-name/`; state, geo, related services, map override |
| Testimonial | `lf_testimonial` | Private; reviewer name, rating, review text, source (Google/Facebook/etc.) |
| FAQ | `lf_faq` | `/faqs/`; question, answer, optional service/area association |

All use `show_in_rest => true` and clean rewrites.

---

## SEO & Schema

- **Schema (JSON-LD):** LocalBusiness (global), Service (single service), FAQPage (single/archive FAQ or filter), Review/AggregateRating (from testimonials). Toggleable in Theme Options → Schema; fails silently if data is incomplete.
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

## Extending

- **New section type:** Add to `lf_homepage_section_template_map()` in `inc/homepage.php` and add a layout/overrides in `inc/acf/options-homepage.php`.
- **New block:** Register in `inc/blocks/register.php` and add a template in `templates/blocks/`.
- **FAQ schema on custom pages:** Use filter `lf_faq_schema_items` to pass FAQ items.
- **Breadcrumbs:** Filter `lf_breadcrumb_items` to adjust or extend items.

---

## Changelog

- **0.1.0** — Foundation: CPTs, ACF options, blocks, SEO/schema, modular homepage, CTA resolution, documentation.
