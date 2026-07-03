# LeadsForward Core Theme — Team Changelog
2026-04-20

What changed

* Menu controls: added a Global Settings toggle to auto-build the primary menu (core pages) and never link unpublished pages; added a “Menu: include these Services” picker to include selected service pages under Services.
* Services menu support: Services CPT is now exposed to WordPress nav menus so teams can add individual services manually when needed.
* Heading case controls: added a global heading case mode (normal/title/upper/lower) plus a front-end editor switcher (saved globally; page reload applies).
* Link hover styling: added a Branding setting for internal link hover color (tokenized as `--lf-link-hover`).
* Footer CID linking: added Business settings to auto-link the footer address to the GBP URL (CID-friendly) with an explicit URL override.
* Internal link safety: AI-applied HTML now strips broken internal links to missing/unpublished internal pages before saving (keeps the text, removes the dead link).
* Reviews page resilience: when there are no testimonials yet, the Reviews page now shows a helpful placeholder instead of looking broken.
* Projects link hygiene: Projects links are hidden unless there are published Projects, reducing “surprise links” in menus/footer on new sites.

Why it matters

* Fewer 404s from menus, more control over Services navigation, and fewer broken AI-inserted links.
* Cleaner launch experience: Reviews/Projects no longer look “missing” on new sites.

Where to look / how to verify

* Menu autobuild: Global Settings → enable **Auto-build primary menu** → refresh → confirm header menu includes core pages; confirm draft pages (e.g., Financing) are not linked.
* Services in menu: Global Settings → pick a few Services in **Menu: include these Services** → refresh → confirm they appear under Services.
* Heading case: Front-end editor → Header panel → change **Heading case** → save → reload → confirm headings reflect the selected casing.
* Link hover: Branding → set **Link hover color** → refresh → hover internal links in content and confirm color updates.
* Footer address CID: Business Info → set GBP URL (or Address Link override) → refresh → confirm footer address is clickable.
* Broken link guardrail: Use AI to insert a link to a missing page → apply → confirm the link does not persist as a clickable anchor.

Version

* Shipped up to **0.1.73** (PR #393).

---

2026-04-22

What changed

* Airtable **Sitemaps** is now first-class: added Airtable Sitemaps table/view settings + **Test Sitemaps Fetch** (validates and reports normalization errors; no content changes).
* `{city}` slug templating shipped with strict validation/canonicalization (safe URLs, stable spec keys).
* Sitemap-driven page sync (single source of truth):
  * Reconcile engine: Airtable Sitemaps → normalize PageSpec → resolve `{city}` → upsert WP Pages → store cache + slug→post index.
  * Publish controls: always-publish core hubs + publish top ratio by priority (defaults to 50%), rest set to draft/pending/private (configurable).
  * Writes per-page primary keyword meta (`_lf_seo_primary_keyword`) for SEO/AI tooling.
* Sitemap-driven Header Menu:
  * Builds Header Menu from Airtable menu group + hierarchy + priority.
  * Only includes published pages; keeps “More” at the right edge of sitemap links while preserving existing header CTA items.
  * Deterministic nesting: parents always created before children.
* Sitemap Sync admin + cron:
  * Added LeadsForward → Sitemap Sync screen with Sync now + “last run” summary.
  * Added hourly cron for reconcile + menu build.
* Internal link allowlist enforcement:
  * When the sitemap index exists, internal links not in the **published** index are stripped (anchor text preserved).
* AI keyword plumbing improved:
  * AI Studio payload building now prefers `_lf_seo_primary_keyword` for services and service areas.
* Docs added:
  * `docs/09_SITEMAP_SYNC.md` + cross-link in theme integration docs.

Version

* Shipped up to **0.1.87**.

---

2026-04-24

What changed

* Menu safety hardening: sitemap menu builder will **not clear/rebuild** the Header Menu if there are **zero published sitemap menu nodes** (prevents nav wipe when Airtable rows are invalid/unpublished).
* Homepage template cleanup (defaults only):
  * Removed **Explore More Services** section from the homepage default section order.
  * Removed the extra “process expectations” box from the homepage process section.
* Services Overview page reliability:
  * Prevents duplicated “secondary body” rendering when it matches the primary body (dedupe at save + render).
  * Ensures the Services Overview blueprint key maps correctly to `/services/` (legacy alias retained).
* Sitemap Sync is now **niche-aware**:
  * Airtable rows must include `Niche`.
  * Sync filters Sitemaps rows to the site’s niche (one table can safely contain multiple niche templates).
* Fleet PUSH reliability:
  * Controller-side PUSH requests allow longer install time so the client can download/unzip/install before the request times out (more reliable push outcomes).

Docs updated

* Cleaned up `docs/09_SITEMAP_SYNC.md` (removed duplicate content block; single canonical reference).

Version

* Shipped up to **0.1.92**.

---

2026-07-03

What changed

* **Fleet page templates:** All core marketing pages (home, about-us, why-choose-us, services, service-areas, faq, contact, reviews) share finalized Page Builder blueprints; writer templates in `docs/templates/`.
* **Header navigation:** Fleet contract enforced (Home → Services → Service Areas → About → Call → Free Estimate → More); FAQ under More; Contact never top-level.
* **Duplicate pages:** Canonical slugs (`about-us`, `why-choose-us`, `services`) with alias dedupe from Airtable sitemap paths.
* **Reviews gating:** `/reviews/` publishes only when ≥1 published `lf_testimonial`; immediate sync on testimonial save/trash.
* **wp-admin docs:** Playbook expanded (import workflow, fleet templates, header nav, Airtable live sync, troubleshooting).
* **Documentation cleanup:** Slim `README.md`; `docs/DOCUMENTATION_MAP.md` added; `docs/superpowers/` archived; broken paths fixed (`inc/manifester/docs/n8n-workflow.json`, `inc/manifester/ai-studio.php`); orphan `templates/lf-docs.php` removed.

Version

* Shipped up to **0.1.177** (PRs #596–#601 area).

---

2026-07-03 (documentation audit pass 2)

What changed

* Renamed `docs/09_SITEMAP_SYNC.md` → **`docs/10_SITEMAP_SYNC.md`** (resolves duplicate `09_` numbering with Page Builder doc).
* Fixed `docs/05_THEME_INTEGRATION.md` heading structure (fleet vs sitemap sections).
* Standardized wp-admin menu label **Theme Documentation** across README, playbook, and site health.
* Fixed stale cross-links in LF-TEAM pack, SOP, icon doc, front-end editor doc.
* Expanded playbook developer-reference and dev-tab recent changes.

Version

* Shipped up to **0.1.178**.

---

2026-07-03 (performance + SEO audit)

What changed

* **LCP:** Preload homepage hero background; `lf-hero-l` image size; logo/hero images eager + `fetchpriority="high"` (no lazy above fold).
* **Performance:** Font preconnect; minimal critical CSS for header/hero shell; contact form JS only on map_nap pages; image-intelligence media stack editors-only on front.
* **SEO:** Default `noindex` for `lf_faq` and `lf_process_step` singles/archives (fragment CPTs).
* **Cleanup:** Wired `inc/ai-studio-*.php` stubs; removed unused `templates/parts/cta.php`.
* **Docs:** `docs/THEME_AUDIT_2026-07-03.md` — full audit + recommendations.

Version

* Shipped up to **0.1.179**.

