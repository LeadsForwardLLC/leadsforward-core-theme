# LeadsForward Theme Audit

## What exists
- Section registry with per-context availability and defaults (`inc/sections.php`)
- Homepage controller + admin UI for section ordering and settings (`inc/homepage.php`, `inc/homepage-admin.php`)
- Page Builder meta box for page/service/service area/posts with section library (`inc/page-builder.php`)
- Inline icon system with per-section icon controls (registry + admin UIs)
- Background selector with brand-aligned options (white/light/soft/primary/secondary/accent/dark/black/card)
- SEO foundation: canonical URLs, meta title/description fallbacks, robots rules (`inc/seo.php`)
- Schema foundation for organization/local business (`inc/schema.php`)
- Content section renders heading/intro/body fields (`inc/sections.php`)
- Setup wizard seeds pages and business data (`inc/niches/wizard.php`, `inc/niches/setup-runner.php`)
- Hero controls parity across Homepage + Page Builder (variant + CTA toggles/actions)
- Canonical CTA resolver used across builders (`lf_resolve_cta`)
- SEO overrides for pages/posts/services/service areas (title/description/noindex)
- Shared section drag-and-drop module (`assets/js/lf-section-sortable.js`)
- Post-generation content QA audit + one-pass auto-repair (`inc/ai-studio.php`)
- Quote Builder dynamic service options + niche fields (`inc/quote-builder.php`)
- Service Details media column + process expectations/trust rendering (`inc/sections.php`)

## What’s missing
- Page Builder parity:
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
- Mixed rendering paths for the same section type (block templates vs inline renderers)

## What’s dangerous
- Arbitrary HTML/iframe inputs without clear warnings (map embed, GHL embed)
- Placeholder business data in wizard can ship if left unchanged
- H1 duplication risk (hero + page title + content headings)
- No validation for URL/phone/email inputs across admin UIs
- Context restrictions not documented (sections silently hidden by context)

## What must be fixed before scale
- H1 enforcement and heading hierarchy validation to prevent SEO regressions
- Add OG/Twitter meta tags and HTML breadcrumbs for baseline share/SEO parity
- Add operator guidance for all critical fields (placeholders, examples, “what happens if empty”)
- Add validation warnings for URLs, phones, emails, and embed fields
- Document section context rules in admin UI to avoid confusion across 500 sites
