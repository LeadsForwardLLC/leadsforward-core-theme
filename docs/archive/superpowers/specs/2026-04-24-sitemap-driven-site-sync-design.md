# Sitemap-driven site generation + sync (Airtable)

## Summary

Make the Airtable **`Sitemaps`** table (view: **`Primary View`**) the **single source of truth** for:

- Page inventory (what pages exist)
- Slugs (including `{city}` templating)
- Per-page primary keywords
- Menu grouping + hierarchy
- Publishing behavior (published vs scheduled/draft)
- Allowed internal link targets (no broken/unpublished/non-existent links)

This applies to both:

- **New site build** (wizard / setup runner)
- **Ongoing sync** (cron + manual “sync now”)

## Goals (from team call)

### AI content + SEO logic

- Primary keyword is **per page**, not homepage-driven guessing.
- Enforce structured placement rules:
  - H1 includes primary keyword
  - First paragraph includes keyword
  - 1–2 H2s include keyword
  - CTA includes keyword
  - FAQ includes keyword in at least one question
- Auto-pair **(primary keyword + service area city)** across:
  - title tag
  - meta description
  - headings
  - body content

### Internal linking system

Hard constraints:

- Never link to unpublished/non-existent pages.
- No orphan pages.
- Minimum per-page internal links (baseline):
  - Homepage
  - Relevant parent hub
  - Related service page
- Service Overview links to all service pages.
- Service Area pages link to related services.

### Site structure via Airtable

- Remove “AI inference” for structure:
  - page list, slugs, keywords, menu structure are all Airtable-driven.
- Slugs must be respected exactly (except `{city}` templating replacement).
- Future-ready for service areas expansion (city/state/zip).

### Automation & data sync

- Periodic sync from Airtable (cron).
- Triggered sync (manual “sync now” in WP admin; later webhooks).
- Reviews sync continues (already exists); add sitemap-driven content + structure sync.

## Non-goals (for this iteration)

- A full Airtable webhook pipeline (we’ll design for it, but ship cron + manual first).
- Perfect “related links” personalization per page (we’ll start with deterministic rules; add explicit Airtable mapping later).
- Zip/state-level service-area landing pages (design supports it; implementation later).

## Airtable inputs

### Table + view

- **Table**: `Sitemaps`
- **View**: `Primary View`

### Columns (current)

- `Page title | Niche`
- `Niche`
- `Page title (service)`
- `Priority`
- `Keyword`
- `menu group`
- `Menu hiearchy`
- `Slug`

### Proposed schema improvements (add soon)

To eliminate ambiguity and avoid inference:

- **`Page Type`**: enum (home, core, service_overview, service_detail, service_area_overview, service_area_detail, blog, reviews, faq, financing, projects, custom)
- **`Publish Mode`**: enum (publish, draft, schedule)
- **`Publish At (UTC)`**: datetime (optional, used when `schedule`)
- **`Related links (optional)`**: multi-select of slugs or record links (optional enhancement)

The initial implementation will infer `Page Type` only when it is unambiguous, but will prefer explicit fields when present.

## Canonical PageSpec model (normalized)

Each row becomes a `PageSpec`:

- `key`: stable identifier derived from `{niche}:{slug_template}` (sha256)
- `niche`: string
- `title`: string (from `Page title | Niche` or `Page title (service)` depending on type)
- `priority`: int
- `primary_keyword`: string (from `Keyword`)
- `slug_template`: string (from `Slug`, e.g. `/services/crack-repair-{city}/`)
- `slug_resolved`: string (after templating, for this site’s primary city)
- `menu_group`: string (e.g. Home, Services, Service Areas, More)
- `menu_hierarchy`: string (e.g. Parent / Child 1 / Child 2)
- `page_type`: string (explicit if field exists; otherwise inferred)
- `publish_mode`: string (explicit if field exists; otherwise derived rule)

## `{city}` templating rules (Slug)

- Supported tokens (initial): `{city}`
- Replacement:
  - Take **site primary city** from manifest/business entity (`primary_city`).
  - Normalize to slug via `sanitize_title`.
- Safety:
  - If `{city}` exists but primary city is missing, resolve slug by removing token and collapsing dashes, then mark the PageSpec as **invalid** (sync reports error) so we don’t create nonsense URLs.
- Canonicalization:
  - Always ensure leading `/` and trailing `/` (WordPress path semantics).

## Sync architecture (WP)

### Storage

- Store last fetched/normalized sitemap in an option:
  - `lf_airtable_sitemap_cache` (json)
  - `lf_airtable_sitemap_cache_at` (timestamp)
- Store a resolved “page index” for the site:
  - `lf_sitemap_page_index` (slug_resolved → post_id + status + type)
- Store per-post metadata:
  - `_lf_sitemap_key`
  - `_lf_sitemap_slug_template`
  - `_lf_seo_primary_keyword` (already used by SEO engine)

### Fetch

Reuse existing Airtable PAT/base settings (already in `inc/ai-studio-airtable.php`) and add:

- `lf_ai_airtable_sitemaps_table` default `Sitemaps`
- `lf_ai_airtable_sitemaps_view` default `Primary View`

### Reconcile

On sync:

1. Fetch Airtable rows → normalize to PageSpecs.
2. Resolve `{city}` templating.
3. Upsert WP posts:
   - Create missing pages/CPT posts according to `page_type`.
   - Update titles, slugs, primary keyword meta.
   - Apply publish_mode (publish/draft/schedule).
4. Build/refresh menu strictly from PageSpecs:
   - Respect menu group + hierarchy.
   - Only include **published** items.
   - Ensure **More** is rightmost and stable.
5. Compute allowed internal link targets:
   - From published PageSpecs only.
   - Persist allowlist so AI can only link to valid targets.

### Scheduling / publishing rule (until Airtable field exists)

Default behavior (configurable):

- Publish “core + hubs” always (home, services overview, service areas overview, contact, about, why, reviews).
- For service detail + area detail pages:
  - Publish top \(~50%\) by `priority` (lower number = higher priority).
  - Set the rest to `draft` or `future` (configurable).

Add settings to control this:

- `lf_sitemap_publish_ratio` default `0.5`
- `lf_sitemap_unpublished_mode` enum: `draft|future`

## AI integration

### Keyword placement enforcement

When generating or editing a page, AI receives:

- `primary_keyword`
- `city`
- Required placements checklist

After generation, run an automatic “SEO compliance pass” that checks for missing placements and requests a constrained patch if needed.

### Internal linking enforcement

AI can only link to:

- slugs present in the current sitemap AND
- posts that are published

Enforcement layers:

1. **Prompt allowlist**: AI receives list of allowed internal links (titles + URLs).
2. **Post-processor guardrail**: strip internal links that are not in allowlist (similar to existing broken-link stripping).

## Menus

Menu is generated from PageSpecs:

- Top-level ordering by `menu group` then `priority`.
- Nested structure from `Menu hiearchy`.
- Required structure:
  - Home, About, Services, Service Areas, More
  - More contains Reviews/FAQ/Financing/Blog/Projects as available
- More is always placed last.

## Reviews / GMB / contact info

- Keep existing reviews sync from Airtable (already implemented).
- When GBP URL is added in Airtable and sync runs, update the footer address link behavior (already supports fallback to Contact).
- Contact info fields must be sourced from Airtable mappings; avoid incorrect fallbacks.

## Rollout plan (implementation order)

1. Airtable fetch + normalize for `Sitemaps` table; store cache + debug view in WP admin.
2. Upsert pages + slugs with `{city}` templating; write `_lf_sitemap_key` and primary keyword meta.
3. Airtable-driven menu build using existing menu builder patterns.
4. Allowed-link allowlist + stricter link guardrails (no links outside sitemap/published set).
5. AI prompt changes: keyword placement rules + city pairing + link allowlist usage.
6. Publishing scheduling controls (publish ratio / schedule).

## Observability / debugging

Add an admin panel under LeadsForward:

- Last sitemap sync timestamp
- Number of PageSpecs
- Validation errors (missing slug/keyword, unresolved `{city}`)
- Diff summary: created/updated/skipped
- Menu build summary

## Success criteria

- A site can be built and later updated with **zero manual WordPress page/menu edits**.
- Slugs match Airtable including `{city}` templating.
- Each page has a correct stored primary keyword from Airtable.
- AI content reliably includes keyword+city per placement rules.
- Internal links never point to missing/unpublished pages and meet minimum link requirements.

