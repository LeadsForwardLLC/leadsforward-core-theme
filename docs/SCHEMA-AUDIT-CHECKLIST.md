# Schema.org audit checklist (LeadsForward core theme)

Use this after Site Setup, content import, or before launch. Schema is output as JSON-LD in `wp_head` via `inc/schema.php` and SEO settings.

## Quick verification

1. Open a live page → **View Source** → search for `application/ld+json`.
2. Paste URL into [Google Rich Results Test](https://search.google.com/test/rich-results) (optional but recommended).
3. Confirm toggles under **LeadsForward → SEO & Performance → SEO settings → Schema** match intent.

## Site-wide (every page)

| Type | When emitted | Data source | Toggle |
|------|----------------|-------------|--------|
| **LocalBusiness** | All pages (if complete) | Global Settings / business entity (NAP, hours, geo, logo) | SEO → Enable LocalBusiness |
| **Organization** | All pages | Business name, logo, `sameAs` social URLs | ACF Schema options |
| **WebSite** + SearchAction | All pages | Site name + search URL | Always on (filterable) |
| **BreadcrumbList** | Pages with breadcrumbs | Theme breadcrumb trail | Always on (filterable) |

**Check:** Business name is non-empty. Address or geo present for local businesses. Logo URL resolves.

## Page-type specific

| Page type | Schema | Check |
|-----------|--------|-------|
| **Homepage** | LocalBusiness + FAQPage (if FAQ section enabled) | FAQ section shows questions; JSON-LD `FAQPage` matches visible FAQs |
| **About / overview pages** | FAQPage when `faq_accordion` enabled | Same as above |
| **Services overview** | FAQPage if FAQ section enabled | — |
| **Single service (`lf_service`)** | Service + provider (LocalBusiness) | Service name/description match page; provider links to business |
| **Service area (`lf_service_area`)** | LocalBusiness + `areaServed` | Area name matches page title |
| **FAQ archive** | FAQPage (all published `lf_faq`) | — |
| **Any page with testimonials** | Review + AggregateRating on business | Ratings 1–5; max 5 reviews in graph |

## Section-level FAQ rule

FAQ schema is tied to the **FAQ accordion section**, not a separate FAQ page template only.

- Homepage: `faq_accordion` in homepage config
- Other pages: enabled `faq_accordion` in Page Builder
- Respects `faq_max_items` when set

**Check:** View page → confirm FAQ accordion visible → view source for `FAQPage`.

## What we do *not* emit (by design)

- Article schema on blog posts (unless added later)
- Duplicate FAQ on pages without an FAQ section
- Service schema on overview pages (only single service CPT)

## Common issues

| Symptom | Fix |
|---------|-----|
| No LocalBusiness | Fill Global Settings NAP; enable LocalBusiness in SEO |
| FAQ rich result missing | Enable FAQ section + publish `lf_faq` posts + FAQ schema toggle |
| Wrong business type | SEO → Organization type dropdown |
| Reviews missing | Publish `lf_testimonial` posts; enable Review schema toggle |
| Stale data after NAP change | Hard refresh; clear cache; re-check source |

## Filters (developers)

- `lf_schema_local_business`, `lf_schema_organization`, `lf_schema_service`
- `lf_faq_schema_items` — adjust FAQ entities before output
- `lf_schema_output_*` — disable specific types per request

## Launch sign-off

- [ ] Homepage JSON-LD includes LocalBusiness
- [ ] At least one service page shows Service schema
- [ ] FAQ section pages show FAQPage
- [ ] Contact/NAP in schema matches visible footer and map block
- [ ] Rich Results Test passes for homepage + one service page (if applicable)
