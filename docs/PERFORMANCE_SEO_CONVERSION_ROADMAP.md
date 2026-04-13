# Performance, SEO & conversion roadmap

This is the working plan to make LeadsForward Core measurably among the fastest WordPress themes for local lead-gen while preserving SEO depth and conversion clarity. Treat it as a **prioritized backlog**, not a promise of every item in one release.

## Principles

1. **Measure first** — baseline LCP, INP, CLS, TTFB, and conversion events (quote open, CTA click) per template; only optimize what moves those needles.
2. **Server truth** — most “fast themes” win on TTFB + HTML weight + critical path; avoid JS-heavy hydration for content that can be server-rendered (this theme already favors server render).
3. **SEO without bloat** — structured data and internal linking stay on; eliminate redundant meta, blocking scripts, and oversized hero assets.
4. **Conversion** — speed supports conversion; copy, CTA prominence, trust placement, and form friction are equally tracked.

---

## Phase A — Instrumentation & guardrails (do first)

| Item | Rationale |
|------|-----------|
| Real User Monitoring (RUM) or periodic Lighthouse CI on **home**, **service**, **contact** | Regression detection on deploy |
| Web Vitals beacon (optional small script or third-party) | Field data beats lab-only |
| Document current **critical request chain** (fonts, CSS, hero image, jQuery exceptions) | Know what defer broke |

---

## Phase B — Front-end performance (theme-owned)

### B1. Assets

- **CSS:** Audit `assets/css` + inline critical path; split “above the fold” tokens vs section-specific CSS if bundle grows; avoid duplicate rules across variation tokens.
- **JS:** Keep front-end **jQuery off** where possible (`inc/performance.php`); audit any enqueue that pulls jQuery back in; defer non-critical scripts (already filtered via `lf_defer_scripts`).
- **Fonts:** If webfonts load, use `font-display: swap`, subset weights, preload only WOFF2 used above the fold.
- **Icons:** Tabler SVGs are per-icon file reads server-side — cache `lf_icon()` parse results in object cache on high-traffic sites (optional plugin layer) or ensure opcode cache is warm.

### B2. Media

- Enforce **width/height** on content images (theme already checks in SEO vitals); add **fetchpriority="high"** only for true LCP image (hero).
- **Responsive images** — `srcset`/`sizes` for hero and large section images.
- Lazy-load **below-the-fold** images; never lazy LCP candidate.
- Video backgrounds: poster image, `preload="none"`, prefer static image on mobile via theme setting if needed.

### B3. WordPress / hosting

- **Object cache** (Redis/Memcached) at scale.
- **HTTP/2 or HTTP/3**, Brotli, CDN for static assets.
- **WP-Cron** off front request on production (`DISABLE_WP_CRON` + real cron) — helps **fleet** and general TTFB under load.
- OPcache, PHP 8.2+, adequate DB query cache.

### B4. Theme-specific hotspots to profile

- `inc/sections.php` render path for pages with **many sections** — reduce redundant `get_option` / meta reads (transient cache per request for section config already partial).
- Internal linking / keyword map builders on `wp_footer` — ensure they don’t run heavy work on every uncached request without need.
- AI assistant assets: already no-defer for stability; keep **logged-in only** (current pattern).

---

## Phase C — SEO (technical + on-page)

### C1. Technical

- **Single H1** + hierarchy (theme enforces warnings).
- **Canonical**, robots, `noindex` for thin/utility URLs — SEO module owns; audit edge cases (search, paginated archives).
- **XML sitemap** freshness after bulk publish.
- **JSON-LD** validity (LocalBusiness, Service, FAQ, Review) — Google Rich Results test on templates.
- **Core Web Vitals** as ranking input — Phase B directly supports.

### C2. On-page quality (orchestrator + editor)

- Title/meta/description templates with **intent** fields (already in SEO box).
- **Internal links** — hub/spoke integrity (Site Health + linking engine).
- **FAQ / HowTo** only where content truly matches (avoid rich-result spam).

---

## Phase D — Conversion optimization

### D1. Above the fold

- **One primary CTA** visible without scroll on key templates; secondary CTA lower visual weight.
- **Phone** tap target and `tel:` link in header (already pattern).
- **Hero:** headline + subhead + proof in first viewport; LCP image must not push CTA below fold on mobile.

### D2. Quote / lead flow

- Quote Builder: minimize steps, inline validation, **progress** clarity; track abandon per step.
- **Trust** near form (reviews count, guarantees) without clutter.

### D3. Experimentation

- A/B or sequential tests on headline + CTA label (GTM + dataLayer events).
- Heatmaps/session replay on high-traffic pages (privacy-compliant).

---

## Phase E — Fleet & operations (speed of **deploy**, not page load)

- Filter `lf_fleet_updates_cron_interval` (default 15 minutes, clamped 5–60) for aggressive rollouts.
- System cron hitting `wp-cron.php` so fleet checks run on quiet sites.
- Controller capacity: rate limits and zip build caching if many sites check at once.

---

## Success metrics (suggested targets)

| Metric | Direction |
|--------|-----------|
| LCP (mobile p75) | &lt; 2.5s field, &lt; 2.0s stretch |
| INP | &lt; 200ms |
| CLS | &lt; 0.1 |
| TTFB (cached edge) | &lt; 200ms |
| Total JS transferred (logged-out home) | minimize KB; no unnecessary libraries |
| Quote modal start → submit | track completion rate + time |

---

## What not to do

- Don’t strip semantic HTML or headings for a Lighthouse score.
- Don’t disable schema to “save bytes” without replacement strategy.
- Don’t defer jQuery on pages that **require** it without testing quote/modal flows.

---

## Related docs

- `docs/00_PRODUCTION_READINESS.md` — launch checklist.
- `docs/05_THEME_INTEGRATION.md` — fleet channel, cron behavior.
- `inc/performance.php` — defer, heartbeat, head cleanup hooks.
