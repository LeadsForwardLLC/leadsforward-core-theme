# Sitemap Sync (Airtable Sitemaps → WordPress)

This theme can treat Airtable’s **Sitemaps** table as the **single source of truth** for the site’s page inventory, slugs (including `{city}` templating), per-page primary keywords, and header navigation structure.

## What it does

When Sitemap Sync runs it:

- Fetches records from Airtable **Sitemaps table/view** (configured in LeadsForward → Global Settings).
- Normalizes rows into `PageSpec` objects (strict validation with observable errors).
- Filters rows to the current site’s **niche** using the Airtable `Niche` column (one Sitemaps table can hold multiple niche templates).
- Resolves `{city}` in slugs using the site manifest’s primary city.
- Upserts WordPress **Pages** (safe scope: `post_type=page` only).
- Stores a sitemap cache + a `slug → post_id/status` index for fast lookups.
- Builds/updates the **Header Menu** from Airtable menu group + hierarchy using only **published** pages.

## Admin controls

### Global Settings

LeadsForward → **Global Settings**

- **Sitemaps table** / **Sitemaps view**
- **Test Sitemaps Fetch**: fetch + normalize only (no content changes). Shows validation errors.
- **Run Sitemap Sync**: runs reconcile and then rebuilds Header Menu.

### Sitemap Sync screen

LeadsForward → **Sitemap Sync**

- **Sync now**: runs reconcile + header menu build and stores a “last result” summary.
- Shows:
  - last run timestamp + mode (manual/cron)
  - reconcile counts (created/updated/invalid/index size)
  - header menu build counts (added/preserved)
  - errors sample (if any)

## Key data stored in WordPress

### Options

- `lf_airtable_sitemap_cache`: JSON array of normalized specs (plus enriched fields from reconcile).
- `lf_airtable_sitemap_cache_at`: Unix timestamp when the cache was last written.
- `lf_sitemap_page_index`: JSON map of resolved slug → `{ post_id, status, type }` used by menus + internal link allowlist.
- `lf_sitemap_publish_ratio` (default `0.5`): ratio of non-core pages to publish by priority.
- `lf_sitemap_unpublished_mode` (default `draft`): status for pages not selected to publish (`draft|pending|private`).
- `lf_sitemap_menu_enable` (default on): enables sitemap-driven header menu build.
- `lf_sitemap_sync_last_result`: JSON summary of the last run (shown on the admin screen).
- `lf_sitemap_sync_cron_enabled` (default on): enables the hourly cron.

### Post meta

Each reconciled Page is tagged with:

- `_lf_sitemap_key`: stable spec identity hash.
- `_lf_sitemap_slug_template`: slug template from Airtable (may include `{city}`).
- `_lf_seo_primary_keyword`: primary keyword for AI/SEO tooling.

## Slug templating

- The only token allowed in a slug template is `{city}`.
- Resolved slugs are canonicalized to either `/` (homepage) or `/path/with-trailing-slash/`.
- Templates that are empty, include invalid characters, contain `..`, or include unsupported tokens are rejected and surfaced as errors via Test/Sync results.

## Publish strategy

- A small set of “core hubs” are always forced to `publish` (homepage, services, service areas, contact, about, why, reviews).
- Everything else is published by priority based on `lf_sitemap_publish_ratio`; the remainder are put into `lf_sitemap_unpublished_mode`.

## Menu rules (Header Menu)

- Menu groups and hierarchy are driven by Airtable fields.
- Only published pages are included.
- Ordering is stable (group order, then hierarchy depth, then priority).
- “More” is kept at the right edge of the sitemap-driven links; preserved CTA button items remain at the far right.

## Internal link guardrails

When `lf_sitemap_page_index` is available, internal links are additionally constrained:

- If a link points to an internal URL that is **not** in the published sitemap index, it is stripped (anchor text preserved).
- External links are never affected.
- If the index is missing/empty, behavior falls back to “strip broken internal links only.”

## Where to look in code

- Airtable fetch/normalize: `inc/airtable/sitemaps.php`
- Slug templating + stable keys: `inc/sitemap-sync/types.php`
- Reconcile (upsert pages + cache/index): `inc/sitemap-sync/reconcile.php`
- Header menu builder: `inc/sitemap-sync/menus.php`
- Admin UI + cron: `inc/sitemap-sync/admin.php`
- Internal link allowlist enforcement: `inc/seo/internal-link-guardrails.php`

