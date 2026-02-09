# LeadsForward Theme Audit

## What exists
- Section registry with per-context availability and defaults (`inc/sections.php`)
- Homepage controller + admin UI for section ordering and settings (`inc/homepage.php`, `inc/homepage-admin.php`)
- Page Builder meta box for page/service/service area/posts with section library (`inc/page-builder.php`)
- Inline icon system with per-section icon controls (registry + admin UIs)
- Background selector with brand-aligned options (white/light/soft/primary/secondary/accent/dark/black/card)
- SEO foundation: canonical URLs, meta title/description fallbacks, robots rules (`inc/seo.php`)
- Schema foundation for organization/local business (`inc/schema.php`)
- Content block that pulls from main WP editor (`inc/sections.php`)
- Setup wizard seeds pages and business data (`inc/niches/wizard.php`, `inc/niches/setup-runner.php`)

## What’s missing
- Page Builder parity:
  - Hero variant selector only exists on Homepage admin, not Page Builder
  - Hero CTA enable/disable toggles exist on Homepage admin, not Page Builder
  - SEO overrides panel only for page/post, not service/service area
- Clear operator guidance:
  - Most fields lack `instructions` or examples (sections registry, page builder UI)
  - Many defaults are empty with no guidance (hero headline, service details body)
  - Placeholder conventions are inconsistent (`[City]` style vs plain text)
- SEO fundamentals:
  - No hard enforcement for exactly one H1 per page
  - No Open Graph / Twitter meta tags
  - No HTML breadcrumb output (schema-only breadcrumbs)
  - No heading hierarchy validation
- Internal linking coverage:
  - Internal link helpers exist but are only used in dedicated related sections
  - No automatic contextual linking in content blocks

## What’s redundant
- CTA resolution logic duplicated across homepage and section renderer (`inc/homepage.php` vs `inc/sections.php`)
- Hero CTA field naming split (`hero_cta_*` in homepage admin vs `cta_*` in registry)
- Section library drag-and-drop JS duplicated for Homepage and Page Builder
- Mixed rendering paths for the same section type (block templates vs inline renderers)

## What’s dangerous
- Arbitrary HTML/iframe inputs without clear warnings (map embed, GHL embed)
- Placeholder business data in wizard can ship if left unchanged
- H1 duplication risk (hero + page title + content headings)
- No validation for URL/phone/email inputs across admin UIs
- Context restrictions not documented (sections silently hidden by context)

## What must be fixed before scale
- Page Builder parity: hero variant + CTA toggles + SEO overrides must match Homepage controls
- H1 enforcement and heading hierarchy validation to prevent SEO regressions
- Add OG/Twitter meta tags and HTML breadcrumbs for baseline share/SEO parity
- Standardize CTA field naming and remove duplicate resolution paths
- Add operator guidance for all critical fields (placeholders, examples, “what happens if empty”)
- Add validation warnings for URLs, phones, emails, and embed fields
- Document section context rules in admin UI to avoid confusion across 500 sites
