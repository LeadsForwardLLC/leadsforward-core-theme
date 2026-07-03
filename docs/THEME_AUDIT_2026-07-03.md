# Theme audit — performance, SEO, security, structure

**Date:** 2026-07-03  
**Site tested:** https://theme.leadsforward.com/ (Lighthouse mobile, score 96/100)  
**Theme version at audit:** 0.1.179  

This document records findings from a full theme backend + front-end editor audit. **Safe fixes were applied in the same release**; risky items are recommendations only.

---

## Executive summary

| Area | Before | After (theme changes) | Still needs attention |
|------|--------|----------------------|------------------------|
| **Performance** | Hero LCP delayed; logo/hero images lazy-loaded; contact JS global | Preload + `fetchpriority` for hero BG; eager logo/hero media; conditional contact JS; font preconnect; minimal critical CSS | SiteGround combined CSS (~240ms blocking); 18KB unused CSS in SG bundle |
| **SEO** | Fragment CPT singles/archives indexable | Default `noindex` for `lf_faq` / `lf_process_step` singles + archives | Dual sitemap (`/sitemap.xml` + `wp-sitemap.xml`); third-party SEO plugins |
| **Security** | Broad script injection cap; AI REST bearer mode | Documented; image-intelligence scoped to editors on front | Restrict header scripts to `manage_options`; production HMAC-only |
| **Structure** | Orphan stubs/files; 8k-line CSS monolith | Stubs wired; unused `cta.php` removed | Split `design-system.css`; extract AI assistant JS |

---

## 1. Performance

### 1.1 Lighthouse report (external — SiteGround)

| Issue | Cause | Theme fix? | Action |
|-------|-------|------------|--------|
| Render-blocking combined CSS ~240ms | `siteground-optimizer-combined-*.css` | **No** (hosting plugin) | SG Optimizer: critical CSS, defer non-critical, audit combined bundle |
| ~18.4 KiB unused CSS of 25 KiB | Global theme + plugin CSS on homepage | **Partial** | Theme still ships full `design-system.css` (~8k lines) on every page — see recommendations |
| LCP delay 1.4s resource load | Hero BG via CSS custom property; lazy above-fold images | **Yes** | Preload hero BG; `lf-hero-l` size; eager + `fetchpriority` on logo/hero `<img>` |
| Critical request chain 1,394ms | HTML → combined CSS | **Partial** | Minimal inline critical CSS for header + hero shell |
| One long main-thread task | Low TBT (0ms) | N/A | Monitor after SG CSS changes |

### 1.2 Theme changes shipped (0.1.179)

| Change | File(s) |
|--------|---------|
| Preload homepage hero background (`fetchpriority="high"`) | `inc/performance.php`, `inc/images.php` |
| Hero BG uses `lf-hero-l` (1920×1080) instead of `full` | `inc/setup.php`, `templates/blocks/hero.php` |
| Logo + hero column images: `loading="eager"` + `fetchpriority="high"` | `templates/parts/header.php`, `templates/blocks/hero.php` |
| Trust strip icon: removed lazy | `templates/blocks/hero.php` |
| Hero video: `preload="none"` | `templates/blocks/hero.php` |
| Google Fonts `preconnect` | `inc/performance.php` |
| Minimal critical CSS (header + hero shell) | `inc/performance.php` |
| Contact form JS only when `map_nap` section present | `inc/contact-form.php` |
| Image intelligence media stack: front-end only for `edit_theme_options` | `inc/image-intelligence.php` |

### 1.3 Performance — recommend only (not applied)

| Item | Risk | Recommendation |
|------|------|----------------|
| Split / minify `design-system.css` | Layout regression if split wrong | Build step or section-scoped CSS; test all templates |
| Load only active preset font family | Typography change if wrong family | Replace `design-presets.css` mega-`@import` with per-preset enqueue |
| Self-host WOFF2 fonts | Deploy path | Remove `@import` from CSS; add `assets/fonts/` |
| Conditional quote-builder JS | Broken CTAs if gate too aggressive | Enqueue only when quote steps enabled or `data-lf-quote-trigger` pages |
| Extract AI assistant to external cached JS | Editor regression | Move `inc/ai-assistant.php` inline JS to `assets/js/` |
| Cache hero testimonial queries | Stale review counts | Transient in `templates/blocks/hero.php` get_posts calls |
| SiteGround Optimizer settings | Server config | Enable critical CSS; exclude admin/editor assets from combine |

---

## 2. SEO

### 2.1 Shipped

| Change | Detail |
|--------|--------|
| **Fragment CPT noindex** | `lf_faq` and `lf_process_step` singles and archives emit `noindex, follow` via `lf_seo_get_robots_content()` — prevents thin duplicate URLs from ranking; FAQs still appear in accordions and FAQ schema on hub pages |

### 2.2 Healthy (no change)

- Title, meta description, canonical, OG/Twitter, JSON-LD via `inc/seo/seo-render.php`
- Per-post SEO meta box + coverage report
- Internal link map (admin) + AI broken-link guardrails
- Heading H1 policy via `inc/headings.php` + hero coordination
- Theme `/sitemap.xml` excludes noindexed posts

### 2.3 Recommend only

| Item | Recommendation |
|------|----------------|
| Dual sitemaps | Disable WP core sitemaps **or** theme sitemap — pick one canonical |
| Third-party SEO plugins | Do not run Yoast/RankMath alongside theme SEO (duplicate meta) |
| `lf_faq` `has_archive` | Disable if `/faqs/` archive unused |
| Runtime broken internal link filter | Optional `the_content` scrub beyond AI apply path |
| Explicit `robots` on all indexable pages | Consider omitting when default is index |

---

## 3. Security

### 3.1 Shipped

- Image intelligence upload feedback no longer loads `wp_enqueue_media()` for all `upload_files` users browsing the public site — only `edit_theme_options` on front-end

### 3.2 Healthy

- Contact + quote forms: nonces, honeypots, rate limits (`inc/security.php`)
- Fleet push: HMAC + nonce replay protection
- AI editing AJAX: capability + nonce checks
- No hardcoded API keys in theme source

### 3.3 Recommend only (admin / server)

| Item | Owner |
|------|-------|
| SEO header/footer scripts limited to `manage_options` | Theme |
| AI REST `strict_hmac` in production | DevOps |
| Quote submission PII in `wp_options` — retention policy | Ops |
| Rate limit IP from trusted proxy headers only | Server |
| Fleet controller enabled only on controller host | Deploy |
| File permissions audit (`644` files, `755` dirs) | Server |
| `lf_dev_reset` — already gated; keep off production | Deploy |

---

## 4. Theme structure & front-end editor

### 4.1 Shipped

| Change | Detail |
|--------|--------|
| AI Studio back-compat stubs | `inc/ai-studio-*.php` now `require_once` manifester counterparts |
| Removed `templates/parts/cta.php` | Unused placeholder; CTAs live in `templates/blocks/cta.php` + `inc/sections.php` |

### 4.2 Architecture (documented, not refactored)

| Layer | Role |
|-------|------|
| **Operator docs** | `inc/docs-playbook.php` + `/theme-docs/` |
| **Developer docs** | `docs/` + `docs/DOCUMENTATION_MAP.md` |
| **Manifester** | `inc/manifester/` (future plugin) |
| **Page Builder** | `lf_pb_config` + `inc/sections.php` registry |
| **Front-end editor** | `inc/ai-assistant.php` (editors only, `edit_theme_options`) |
| **Homepage** | `lf_homepage_section_config` option + `front-page.php` |

### 4.3 Recommend only

| Item | Notes |
|------|-------|
| Split `inc/sections.php` (~4k+ lines) | Maintainability |
| Split `inc/ai-assistant.php` (~11k lines inline JS) | Performance for editors |
| Separate capability for SEO scripts vs team editors | Security |
| Conditional fleet-controller load | Client sites only need fleet-updates |
| Consolidate `content_image` / `image_content` section aliases | When manifest schema stable |

---

## 5. Asset loading reference (anonymous visitor)

**Always:** `variation-tokens.css`, `design-system.css`, `design-presets.css`, `header-call-link.css`, branding inline, quote-builder JS (if steps enabled).

**Conditional:** contact-form JS (map_nap pages), section-sliders, mega menu JS, projects gallery, wp-embed.

**Editors only:** jQuery, AI floating assistant (~10k inline JS), wp media library.

---

## 6. Verification checklist (post-deploy)

1. **Homepage LCP:** DevTools → Network → hero image preloaded; `.lf-block-hero__bg` paints early.
2. **Logo:** No `loading="lazy"` on header logo; `fetchpriority="high"` present.
3. **Contact form:** Works on Contact page and pages with Map+NAP; no console errors on blog posts without form.
4. **FAQ single URL:** View source → `noindex, follow` on `/faqs/*` if linked.
5. **Editor:** Front-end assistant still loads for admins; image upload feedback works.
6. **Lighthouse mobile:** Re-run after SiteGround CSS optimization for score delta.

---

## 7. SiteGround Optimizer (hosting — not theme code)

These items from the July 2 report require **wp-admin → SG Optimizer** or hosting support:

1. Enable **Critical CSS** or exclude above-fold theme handles from deferral incorrectly applied.
2. Review **Combine CSS** — ensure combined file does not block first paint; exclude editor-only CSS.
3. **Exclude from optimization:** `lf-ai-floating-assistant`, admin bundles, `docs-page.css`.
4. **WebP / image compression** for hero attachments (complements theme `lf-hero-l` size).

---

*Maintained with theme releases. Update this file when applying new audit passes or closing recommendation items.*
