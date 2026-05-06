# SEO + AI workflow hardening (design + execution plan)

This document is the plan Trevor and the LeadsForward theme will follow to close the remaining quality gaps reported by the team (SEO targeting drift, “My WordPress” brand leaks, meta changing unexpectedly, and human-level on-page optimization).

Scope covers theme behavior, n8n workflow upgrades, and a feedback loop using a “gold standard” site Shannon polishes as the exemplar.

## Goals

1. **Never leak placeholder identity** in titles/meta (no “My WordPress”, no “Site Title” defaults, no incomplete truncations).
2. **Keep intent + topic per URL** stable (no “everything is foundation repair”, no cost-guide tone on Why/Company pages unless explicitly intended).
3. **City targeting never drifts** (no Raytown copy on Independence pages).
4. **Manual edits stay put** (if a human fixes meta, the system must not quietly overwrite it).
5. Raise baseline “human-level” quality by adding **deterministic quality gates** and **a guided improvement loop** rather than freeform generation.
6. **Local SERP suitability**: headings and hero copy on **service** and **service_area** URLs must visibly reflect the target **keyword + place** the page is meant to rank for (not only body keyword count).
7. **Trustworthy backend editing**: the WordPress **page / CPT** builder stays **responsive** and the in-app **preview matches the public front end** closely enough that editors trust what they save.

## Non-goals

1. Fully autonomous “publish forever” blog autopilot without review.
2. Rewriting slugs/URLs automatically.
3. Turning the theme into a monolithic AI product. This remains a **controlled manifest + apply system**.

## LF Website Builder Issues Checklist (canonical)

This section mirrors the master checklist Trevor maintains with the team. Technical workstreams in Phase 1–3 and the **n8n workflow** appendix map back to these bullets.

### Brand, meta, and keywords

- Stop “My WordPress” (and other defaults) showing in titles, descriptions, and media fields.
- Fix meta that changes or “reverts” after someone saves in the editor (locks + clear “regenerate” path).
- Fix title/description in the builder not matching the live site (cache, render path, conflicts).
- Strip Airtable junk like `recXXXXXXXX` from keywords, headings, and meta.
- One primary keyword per URL; stop every page inheriting the homepage keyword.

### Local SEO (ranking-facing)

- Enforce service-area pages: money phrase + that area in **H1** and **first big H2** + early in the copy—not buried.
- Service pages: when the URL/slug implies a city, put city + service in **H1** or **first H2**, not a generic hero.
- Wrong city on the wrong page (e.g. Youngstown copy on an Austintown page)—block in QA.
- Related-services tiles must use this page’s area or neutral wording, not HQ city.
- Homepage: main trade + market visible in headings, not a single weak mention.

### Navigation and layout

1. Duplicate menu items (e.g. Service Areas twice).
2. Duplicate Call / Free Estimate and messy stacked header.
3. Links that look like nav but don’t work (bad URL, overlay, etc.).
4. Service areas overview: text promises a map but map doesn’t show.
5. CTA above footer: buttons too dark on dark background (contrast).
6. Footer: logo too big; columns need more spacing.

### Page types and “wrong story”

1. Why Choose Us written like a cost guide (wrong template/intent).
2. Duplicate or irrelevant About / Why Choose Us pages; sitemap listing duplicates. **There needs to be exactly one of each.**
3. About FAQs aren’t relevant. Should **dynamically insert “our company”–related FAQs** for this brand.

### Process, benefits, FAQs

1. Process steps all sound the same (inspection/assessment repeated).
2. Mystery box under process (“60–90 minute walkthrough”): make it an obvious, clearable field **(`process_expectations`)** — or don’t auto-fill it.
3. Too many benefit cards; default to fewer intentional cards (**6 maximum**).
4. Benefit headlines cut off (ellipsis) and headline doesn’t match body (e.g. cracks vs “vetted team”).
5. Benefit / checklist lines that can’t be edited or duplicate other text.
6. FAQs from the wrong “bucket” (area questions on service pages, etc.).

### Images and media

1. Same image reused across sections/pages when others exist.
2. Image doesn’t match the section (wrong trade/scene).
3. File name / alt / title / description don’t match the page topic; “My WordPress” in attachment text.
4. Service-area pages: process for geo-authentic or geotagged photos where you care about local relevance.

### Service cards / homepage vs services page

1. Homepage boxes vs `/services/` grid: different blurbs, both editable, all link to real service URLs (drafts fallback to `/services/` is already theme-side—generation should still seed good copy).
2. Stop stuffing the city into every card the same way.

### Coverage, depth, checklist quality

1. SEO scores green while content is garbage (tighten rules vs real quality).
2. Topic depth: required sections/H2s, internal links, FAQs that match intent.

### Builder experience (backend editor — **priority review**)

The in-admin **page / CPT editor** (manifester / theme builder) is a recurring pain: **laggy**, **sluggish**, **wonky UX**, and the canvas **often does not reflect the live front end** (layout, meta, or content), which erodes trust and slows manifests.

1. **Serious performance pass**: profiling main thread and long tasks; reduce work on selection, typing, drag, scroll, and section focus changes (especially for **custom post types**).
2. **Preview fidelity**: document and close gaps between builder preview iframe and logged-out frontend (critical CSS, `admin`/preview query args, header/nav state, caches, OG vs visible title).
3. Editor **slow / freezes** when selecting or highlighting text; fix stacking interactions (inline toolbar, overlays, SEO Health, AI Assistant chrome).
4. **`process_expectations`** and similar hidden-but-rendered fields: visible in sidebar / structure so editors can clear them deliberately.

### Workflow (n8n / Airtable / manifest)

1. Structured payload per page type (service vs area vs About vs Why, etc.).
2. Second-pass QA node enforcing the rules above before publish.
3. Reconcile duplicates when manifest creates conflicting pages.

## Appendix: How `docs/n8n-workflow.json` generates content (for the team)

**Workflow name:** `LeadsForward – AI Content Orchestrator`. **Entry:** POST webhook `leadsforward-website-manifester` (body is JSON with **`blueprints[]`**, business fields, callbacks, optional `research_document`, media hints).

**Important:** There is **no browsing / Google SERP step** inside this exported workflow. Both “research” and “writing” calls are **LLM completions** grounded in whatever the webhook sends (business name, niche, city, keywords, blueprint section allowlists, optional pasted `research_document`).

**High-level path**

1. **Research Document Gate** — If the payload already includes `research_document`, that object is stored. Otherwise **Research Generator** (OpenAI) runs using a strategist prompt asking for competitor-like analysis, FAQs, imagery guidance, voice, clusters, etc. **returned as JSON**. No live scraping.
2. **Split Blueprints + Deterministic Metadata** — Splits **`blueprints[]`** into **one item per page**; infers `page_type`; picks **`primary_keyword` / `secondary_keywords`** from blueprint or payload; injects **`research_context`** (subset of the research JSON); attaches **`style_profile`** (authority / warm_local / premium / direct) deterministically from a seed.
3. **Blog Planner (post-only)** — Adds blog archetypes and title suggestions for **`post`** page types only.
4. **Store Blueprint Cache** — Saves per-page inputs for retry cache keys.
5. **Basic LLM Chain** (**OpenAI `gpt-5.2-chat-latest`**) — The main author. System instructions require **ONLY valid JSON** output with **`updates`** that map fields to **`section_id.field`** keys allowed by the blueprint. Rules include niche/city lock, minimum word counts, uniqueness, benefits format (`Title || Body`), process steps expectations, FAQ targets when blueprint specifies counts, homepage → `target: options` / pages → `post_meta`, etc.
6. **Parse + Normalize + CTA Guard** — Parses LLM JSON, filters unknown fields, fixes targets, substitutes template tokens (`PRIMARY_KEYWORD` → real phrase if leaked), derives **`service_meta`** short description from hero copy for services.
7. **Quality Gate + SEO Enforcement** — Adds **warnings** (missing primary keyword, duplicate sentences, duplicate whole fields, “in your area” on wrong types, forbidden tokens, low word counts, banned generic phrases). **Does not strictly block publish** unless upstream failed.
8. **Retry Decision → optional LLM Retry Chain** — Second LLM pass with **rewrite instructions** tied to warnings.
9. **Deterministic FAQ Enforcement** — Ensures FAQ answers are wrapped in `<p>` when needed and **forces unique `cta_subheadline_secondary`** text across merged pages via canned variants when duplicates appear.
10. **Global Completeness + Blog Gate** — Final gate logic before merge.
11. **Merge Blueprint Results** — Concatenates all page updates into one **`updates`** array; **uniqueness guard** can tweak duplicate headings/strings across sections.
12. **Attach Callback Metadata** — Optionally attaches **`media_annotations`** / fallbacks keyed from `media_library_candidates` and `image_generation.targets` (**scoring filenames/alt**, not fetching new images).
13. **HTTP Callback to WP** — POSTs JSON back to **`callback_url`** (theme REST orchestrator applies updates).

**Contradictions to resolve (why sites feel “optimized wrong”)**

- Prompt rule **17d** encourages **city in at most one heading** globally, while the checklist demands **money phrase + locality in multiple visible headings** on service/service-area URLs. QA does **not** yet enforce headline-level local prominence or forbid **wrong-city** mentions.
- **`process_expectations`** is instructed as **“3–4 items”** in the prompt; theme only shows **first sentence**. That aligns with Shannon’s stray “undeletable” timing blurb unless we change prompt + QA.
- **Benefits**: prompt mandates **exactly “3 lines”** in **`benefits_items`**; checklist wants **fewer-by-default but up to six cards**—generation and QA need to match the blueprint (max six, sane default three or four).

## Current observed problems (from team feedback)

1. **Brand name leaks** into meta and copy as “My WordPress” when business entity values are missing or fallback paths are hit.
2. **Meta changes unexpectedly** after someone edits it (auto-regeneration rules can override).
3. **Topic drift**: overhead pages (Why, About, etc.) written like cost guides; primary keyword not incorporated naturally; multiple pages targeting the same phrase.
4. **Location drift**: copy references the wrong city/service area relative to the page.
5. **Service cards**: needed per-card unique descriptions, and lists should include unpublished services with safe linking.

### Extended findings (May 2026 Shannon review: Loom + screens)

Evidence came from Youngstown Foundation Repair Pros (and similar manifests). Map each symptom to ownership: **theme** (render/CSS/PHP), **workflow** (n8n/Airtable/payload), **both**.

| Symptom (transcript/screens) | Likely cause | Owner |
|---|---|---|
| Header shows duplicate “Service Areas”, doubled “Call” and “Free Estimate”, overlapping rows | Menu autofill appends duplicates; CTAs/actions rendered alongside full nav instead of condensed; missing dedupe | Theme + manifest menu rules |
| Some nav items look present but **do not click** (# / missing href / JS overlay) | Placeholder menu rows, unpublished targets, or z-index/stacking trapping clicks | Theme + workflow |
| Homepage service boxes: editing copy should **not** change `/services/` (and vice versa) | Override keys are **separate** (`service_intro_card_desc_overrides` vs `service_grid_card_desc_overrides`); inline save supports both; n8n must still seed distinct copy | Theme + workflow |
| Same “Youngstown …” wording in **every** service card intro | Boilerplate prompts reusing locality in each card instead of alternating focus (service noun vs proof vs scope) | Workflow |
| **Process** rows repeat (“Inspection”, “Assessment”, variants) four times | No LLM QA + no theme-side dedupe of near-duplicate headings | Workflow + optional theme guard |
| **FAQ** mixes service-area intents on service or overhead pages | FAQ pool not keyed by archetype/page_type | Workflow |
| **Service areas overview**: copy references a map but **no map renders** despite global embed | `service_areas` block not injecting global map iframe/shortcode, or manifest omits coords | Theme + globals |
| CTA strip above footer: **dark buttons on dark background** | Section uses primary button variant on navy without automatic contrast pairing | Theme (tokens/CSS) |
| Footer: “logo column too loud; link columns need more breathing room” | Footer grid column basis + logo width / gap tokens | Theme |
| **Why Choose Us** is a **basement cost guide**; **About** vs **Why** duplicated; sitemap lists About twice | Wrong page template / keyword mapping in manifest; multiple seeds for same intent | Workflow + reconcile rules |
| **Service area CPT**: headings + SEO fields show **`recXXXXXXXX` gibberish** + “from **My WordPress**” in meta | Airtable Record ID pasted into Keyword/Title fields; placeholder brand fallback | Workflow sanitation + Phase 1 brand hardening |
| Meta edited in WP **reverts** on front after update | Meta regeneration overwriting without locks; conflicting sync timing | Phase 1 meta locking |
| **Same stock image reused** everywhere on a page site-wide | Attachment picker round-robin not scoped per-section or lacks de-duplication memory | Workflow + Phase 3 image allocator |
| **Backend editor** sluggish, “wonky,” preview ≠ live site for **pages / CPTs** | Too much JS on selection/input; iframe preview path ≠ public; cache/host | **Phase 1 §15** (major); `inc/ai-editing/` audit |
| **Editor freezes** when selecting/highlighting text | Same stack; competing listeners | Builder + theme audit |
| Homepage “only one weak mention” of core keyword | Hero/H2 prompts not enforcing primary topic + locality for home archetype | Workflow + checklist |

### Part 2 Loom (timestamped) — service area + service page deep dive

Same site family (Youngstown / Austintown examples). Use timestamps to match the attached video frames.

| Time | What she reported (transcript) | What the screens show | Owner |
|------|-------------------------------|------------------------|-------|
| ~0:01–0:16 | Airtable **record id** “coming through” and “duplicating” | Hero/sidebar/benefits titles like `rec… Austintown OH in Austintown, OH`; repeated location phrasing | Sync sanitize + strip `rec…` (Phase 1 §7) |
| ~0:16–0:36 | Should rank for **foundation repair + Austintown** in headings; **Youngstown** in a tile on **Austintown** page is wrong | Related-services tiles / copy use HQ city instead of **page locality** | Workflow QA + §4 disallowed-city tokens scoped per URL |
| ~0:47 | **Same image** reused on one page; prefers **geotagged** imagery for area pages | Identical excavation/foundation shots in stacked sections | Phase 3 allocator + ops note (manual geo EXIF workflow) |
| ~1:00–1:29 | Something **adds itself**; **frontend meta ≠ backend**; editor **slow** | SEO overlay still shows gibberish title + “My WordPress” in description; perceived lag switching panels | §2 locks + cache/render parity audit; §15 perf |
| ~1:38–1:51 | Service URL is crawl space repair **Youngstown** but locality missing from **H1 / first H2** | H1 generic (“Protects Your Home”) while URL has city | Workflow: **required local modifier** rule for service archetype |
| ~1:45–1:54 | **Image ≠ service**; duplicates; filenames / library **title ≠ topic** | Cracked-wall image on crawl space section; Media Library metadata | Phase 3 relevance + ingestion rules |
| ~1:57–2:07 | WP auto-fills **attachment title/description** inconsistent with purpose (mentions **different** job wording) | “My WordPress — …” in description field in Media modal | WP upload hook / never use raw `blogname` for captions |
| ~2:16 | **Benefits micro-lines** cannot be edited/removed; duplicate of bullets above | Boilerplate trio (“Delivered by…”) under benefit cards tied to CPT/checklist glue | Theme: ensure benefit card bodies are editable; suppress duplicate boilerplate when redundant (see prior `service_details` filtering pattern) |
| ~2:32–2:55 | **Ellipsis** truncation; **heading says cosmetic cracks**, body talks about **workers/insurance** | CSS or max-length trim without paired rewrite | QA: headline/body **semantic alignment** + max headline length |
| ~3:02–3:13 | Six benefit cards → **prefer fewer**; mystery **box below process steps** cannot be deleted | Grey note: “walkthrough lasting 60–90 minutes” — theme renders **`process_expectations`** (`lf_sections_render_process`) after `<ol>` on non-homepage | Theme: expose **Expectations text** clearly in manifester PB config; empty = hide `<p class="lf-process__expectations">`; document |
| ~3:22 | **FAQs are service-area flavoured** on a **service** page | “How far outside **Youngstown**…” on foundation inspection service URL | FAQ pool routing (Phase 1 §12) |
| ~3:31–3:44 | **About** FAQs say “questions about our company” but **answers unrelated to this company** | Wrong exemplar/manifest bleed (Independence-style copy) | n8n: company-scoped FAQ set + forbid generic filler |

**Google / local ranking lens (explicit product goal)**

- **Service area CPT**: primary phrase (`{trade} + {area name}` or approved variant) belongs in **H1 or first visible H2** and at least once in opening copy without stuffing HQ city.
- **Service CPT**: locality in **hero H1 or first section H2** when URL/slug implies city (either always or via configurable rule).
- **Entity consistency**: attachment **alt/title/description** must match **page topic** and never inject default site title placeholders.

## Strategy (high-level)

We will implement a layered system:

1. **Source-of-truth layer**: unambiguous Brand + City + Primary Keyword resolution per URL.
2. **Locking layer**: explicit controls that prevent silent overwrites when humans edit.
3. **Quality gate layer**: deterministic checks that block or flag bad outputs before they go live.
4. **Exemplar loop**: use Shannon’s polished site as “gold standard” to improve prompts, templates, and QA rules without guessing.

## Phase 1 (this week): deterministic stability upgrades

### 1) Brand and city source-of-truth hardening

**Requirement**

Brand used in meta and templates must always resolve to a non-placeholder business name.

**Rules**

- Brand resolution order (proposed):
  - Business entity name (Global Settings)
  - Manifest business name
  - WordPress site title only if it is clearly non-default (never “My WordPress” or similar)
- City resolution order (proposed):
  - Business entity city
  - `lf_homepage_city`
  - Manifest primary city

**Deliverable**

Theme functions that return `brand`, `brand_short`, `city` must never return placeholder values and must be consistent across front-end + admin + SEO generation.

### 2) Meta locking

**Requirement**

If a human edits meta title/description, the theme must not overwrite it unless explicitly told to.

**Approach**

Add per-post meta flags:

- `_lf_seo_meta_title_locked` (bool)
- `_lf_seo_meta_description_locked` (bool)

**Behavior**

- When the user manually edits and saves a non-empty custom meta title/description, auto-set the matching lock to true.
- Add a “Regenerate meta” button (nonce-protected) that:
  - clears the lock(s)
  - regenerates from templates/structured composer
  - re-locks only if user toggles it back on (or keeps it unlocked)

### 3) Keyword bleed prevention (per URL targeting)

**Requirement**

Each URL must have a clear primary keyword and must not inherit the homepage keyword unless explicitly configured as a fallback for non-detail utility pages.

**Approach**

- Enforce that sitemap-driven pages store `_lf_seo_primary_keyword` from their sitemap row.
- For utility pages where Airtable keyword is blank, allow fallback to niche/home keyword, but:
  - label them as “utility”
  - do not force cost-guide tone

### 4) Location drift prevention (page must match its target place)

**Requirement**

When the page’s primary city or service-area target is known, generated copy must not reference other cities.

**Approach**

Deterministic QA: scan generated copy for disallowed location tokens and block or flag.

Inputs:

- Primary city from business entity/manifest
- Service-area pages: the service area title or explicit location field
- Disallowed: other service areas in the manifest list (or within N nearest pages) unless in a “service area list” section

### 5) Service cards: homepage vs `/services/` independence

**Theme status (already shipped)**

- **Homepage block** (`service_intro`): section-level **`service_intro_card_desc_overrides`**; inline edits from the builder persist into overrides (not `lf_service` CPT mutation).
- **`/services/` grid** (`service_grid`): **`service_grid_card_desc_overrides`** is a distinct field → copy can diverge by design once populated.
- Unpublished services still list; unpublished cards resolve link to **`/services/`** until publish.

**Gaps tied to Shannon’s transcript**

1. **Inline UX parity**: inline saves for **`service_grid`** now use **`service_grid_card_desc_overrides`** the same way as `service_intro` (theme shipped together with this spec update).
2. **Manifest / n8n**: payloads must populate **both** sections with **distinct** summaries (homepage = broad value prop; overview page = tighter “choose this service because …”), not copy-paste duplicates.
3. **Remove redundant locality stuffing** in prompts: avoid repeating `{city}` in every card unless the card is explicitly local proof.

### 6) Navigation and header layout hygiene

**Requirements**

- No duplicate top-level items (same label + same target) in autobuilt menus.
- When a full `header_menu` exists, **do not** double-render phone/CTA in a way that stacks duplicate controls (match design spec: either in-bar actions or in-menu, not both repeated).
- Every visible link resolves to a real URL; drop or hide items whose targets are missing.

**Deliverables**

- Audit `lf_menu_maybe_autobuild_header_menu` and sitemap menu sync for append/dedupe rules.
- CSS: header flex/wrap rules so actions + utility row do not overlap (including desktop “broken header” in recordings).

### 7) Strip Airtable system IDs and junk from public text + SEO

**Requirement**

- Strings matching Airtable record ids (`rec` + alphanumeric, case-insensitive) must **never** appear in titles, H1s, meta, or keyword fields on the front or in admin-facing SEO boxes.

**Approach**

- **Sync path**: when applying keyword/title from Airtable, reject or strip id-like tokens and fall back to clean area/service name + state.
- **Theme guard**: optional last-chance sanitizer on render for `lf_service_area` titles (log + strip) so already-polluted rows do not stay live.

### 8) Service areas map embed (global settings → section)

**Requirement**

- If a map URL/embed is configured in Global Settings, the **Communities we serve** / `service_areas` section must render it (or a clear empty state + admin notice), not “ghost” copy about an interactive map.

### 9) CTA band contrast (dark section + buttons)

**Requirement**

- On dark backgrounds, primary/secondary buttons must meet contrast (use outline/light secondary automatically, or section-level button style variant).

**Deliverable**

- Section token or class for `cta` that forces `lf-btn--outline-on-dark` / light fill pairings when background is `dark|navy`.

### 10) Footer density and column spacing

**Requirement**

- Reduce oversized logo lockup where it steals space; increase horizontal gap between columns at desktop breakpoints (matches “smaller here, space these out” feedback).

### 11) Process steps: de-duplication and archetypes

**Requirement**

- Enforce **distinct** step titles per page in generation (no four “inspection” variants).
- Optional theme guard: collapse adjacent steps whose normalized titles are ≥80% similar (last resort; prefer fixing prompts).

### 12) FAQ routing by page archetype

**Requirement**

- FAQ JSON must be selected from pools tagged `service` | `service_area` | `home` | `why` | `about` | `utility` so area-only questions never land on a service page unless explicitly local.
- **About** pages: use a **company-focused** pool (`about_company` or equivalent) loaded from this site’s **manifest / business_entity** facts so Q&As truly reference **our team, territory, and services** — not unrelated exemplars.

### 13) Canonical overhead pages (About, Why Choose Us)

**Requirement**

- Exactly **one** published About intent and **one** Why intent per site (manifest reconciliation).
- Align templates: Why ≠ cost guide unless `page_type=cost_guide`. If duplicate seeds exist, winner is explicit in sitemap precedence; orphans merge or unpublish.

**Deliverables**

- Reconcile routine: duplicate slug/title detection + suppress second insert.
- n8n: separate prompts for About (trust/story/crew) vs Why (criteria/proof) vs cost-guide blog.

### 14) Images: uniqueness, relevance, and optimization

**Requirements**

- Per page generation pass, maintain an **allocation set** so the same attachment ID is not reused across unrelated sections unless the curated pool is exhausted (then degrade gracefully).
- Ranking: section intent keywords ∩ image alt/caption ∩ service terms; penalize mismatches (e.g. laundry room sump image on encapsulation hero).
- Outputs should prefer responsive sizes and reasonable compression (theme image sizes + uploads discipline).

### 15) Backend builder editor: performance + preview parity (**major initiative**)

**Problem statement (team)**

- Editing **pages** and **`lf_*` CPTs** in the backend feels **slow, janky, and unreliable**.
- Inline editing and structure panels **fight the user** (selection freezes, sluggish typing, overlays).
- What you see **does not reliably match** the **public front end**, so people overwrite good content or distrust saves.

**Workstreams**

1. **Instrumentation** — Record traces (Chrome Performance + React/Vue/other if applicable): identify handlers tied to `selectionchange`, `input`, scroll, ResizeObserver, and PB config sync.
2. **Theme vs host** — Split findings: regressions attributable to **`inc/ai-editing/`**, **`inc/page-builder.php`**, enqueue weight, iframe preview bootstrap; vs plugins (optimization, stale full-page cache in preview).
3. **Preview parity runbook** — Same URL: compare builder preview URL/query, **View site** logged-out, and **SEO overlays** (`<title>`, rendered H1 stack). Align cache busting (`?nocache`, `LF_*` constants) where documented.
4. **Progressive degradation** — Option to defer **SEO Health / AI Assistant** panels until idle or explicit open on heavy posts.
5. **CPT matrix** — Explicit QA pass per editor surface: **`page`** (homepage + inner), **`lf_service`**, **`lf_service_area`**, FAQs, optional others.

**Exit criteria**

- No multi-second freezes during normal **select/type** cycles on median hardware on a representative 10-section manifest.
- Documented parity checklist passes for **homepage + service + area** on staging.

### 16) Local SEO prominence rules (hard requirements for key archetypes)

**Service area (`lf_service_area`)**

- Primary money phrase + **area name** (e.g. foundation repair + Austintown) must appear in **H1 or the first section H2** and in the **first ~120 words** unless a human overrides with lock.
- **Forbidden**: repeating the **HQ / parent market** name in customer-facing tiles or intros when it conflicts with the page’s target area (e.g. “Youngstown” on Austintown page except NAP/legal brand line where appropriate).

**Service (`lf_service`)**

- If slug or manifest encodes **city**, **H1 or first content H2** must include that locality (configurable template: “{Service} in {City}” vs brand voice variants).

**Deliverable**

- n8n structured contract fields: `locality_display`, `forbidden_location_tokens[]`, `required_heading_patterns[]`.
- Checklist / coverage dashboard flags when missing.

### 17) Process “expectations” footnote (the undeletable box)

**Finding**

- The dashed box under numbered steps is **`process_expectations`** rendered in `lf_sections_render_process` as `lf-process__expectations` (non-homepage only). If the manifester injects text but does not surface the field, editors experience it as **not deletable**.

**Requirement**

- Builder UI must list **Expectations text** with the process block; **clearing the field** removes the paragraph entirely.
- n8n: default empty unless a site wants a single timing note; avoid auto-injecting generic walkthrough copy.

### 18) Benefits grid: count, length, and title/body integrity

**Requirements** (aligned with **LF Website Builder Issues Checklist**)

- Hard cap **6** benefit cards; default generation should prefer **fewer intentional** cards (e.g. 3–4) unless blueprint explicitly asks for density.
- Enforce max **headline length** (characters or words) to prevent CSS ellipsis that ruins perceived quality.
- QA: each card’s body must **entail** the headline topic (no “cosmetic cracks” headline with “vetted team” body).

**Deliverable**

- Second-pass validator in n8n; optional theme warning in SEO Health for “orphan” boilerplate lines under benefits.

### 19) WordPress media metadata hygiene (“My WordPress” in description)

**Requirement**

- On upload or manifest attach, **description / caption / title** must use **business entity name** + **image intent** (service/area), never raw `get_bloginfo('name')` when it is still a WP default.
- Filename and library title should stay **consistent** with the section’s primary topic (avoid “leveling” asset on “crawl space repair” without reason).

### 20) Meta rendered on front vs values in editor (debug track)

**Hypotheses to eliminate**

- Object/cache (SG Optimizer, full-page) serving stale `<title>`; alternate path outputting OG vs `<title>`; SEO plugin conflict; auto-regeneration after save.

**Deliverable**

- Short runbook: compare `wp_head` source, post meta `_lf_seo_*`, and builder “SEO Health” payload; add cache-bust or disable conflicting plugin on builder preview if needed.

### 21) Service area “related services” tiles: locality substitution

**Requirement**

- Tile labels like “Crawl space repair in **Youngstown**” on an **Austintown** page are a ranking/UX defect. Use **current area** or neutral “Get crawl space repair” without wrong city.

### 22) About-page FAQ: company alignment

**Requirement**

- If section heading promises “about **our company**”, every Q/A must reference **this site’s** brand, service mix, territory, or proof — not a generic or wrong-market exemplar.

## Phase 2 (this week): workflow quality improvements in n8n

### 1) Introduce “structured output contract” for each page type

Instead of “write a page”, require a structured payload:

- `page_type`: utility | service | service_area | blog | about | why | contact | reviews
- `primary_keyword`: must be the page keyword
- `target_city`: must match page target
- `meta`: title/description generated from rules
- `sections`: per section intent + constraints

### 2) Add a second-pass QA node (cheap, deterministic)

QA checks:

- primary keyword present in opening paragraph once in **natural** casing (avoid all-lowercase stubs in H1/H2 unless brand style dictates)
- keyword not stuffed
- wrong city references not present
- headings align with intent (Why/About not written as “Costs”)
- meta title/description are human-readable and non-redundant
- no placeholder brand strings
- no `rec…` fragments, no `#` placeholders, no “My WordPress”
- **Headline/body pairs** in benefits (and similar card grids): cosine or keyword overlap check; fail if body is generic trust line under a technical headline
- **Headline length** within UI limits (no reliance on ellipsis for meaning)
- **Service + service_area**: required local phrase present in designated heading slots (`h1_required`, `first_h2_required`)

Output:

- `qa_pass: true/false`
- `qa_issues: []`
- If fail: either rewrite specific sections or route to “needs human review”

### 3) Exemplar loop using Shannon’s polished site

Once Shannon finishes one site, capture:

- several high-quality service pages
- a clean homepage
- two overhead pages (Why/About)
- one blog post that matches your standard

Use those as:

- Prompt examples (“this is the tone, structure, density, and SEO style”)
- Style constraints for headings and intros
- A/B templates for meta and openings

## Phase 3 (next week): image intelligence + content depth upgrades

### 1) Image selection improvements

Upgrade the selection rules to prefer:

- service-specific relevance (match service terms)
- trust and authenticity (real jobs, team, equipment)
- locality cues (when appropriate); for **service area** pages prefer **geo-authentic** or manually geotagged assets when ops supplies them

Add a “do not use” guard for:

- unrelated generic stock
- wrong trade imagery

### 2) Human-level on-page SEO depth

Implement a “topic coverage planner”:

- required H2s per service page archetype
- internal links map (parent area, sibling services)
- FAQ intent match (questions actually asked)

Tie this to:

- SEO checklist scoring
- Coverage dashboard “issues only” filters

## Team feedback loop (operational)

1. **Tomorrow** the team tests staging sites and sends:
   - URL
   - what looks wrong
   - what the correct intent/keyword/city should be
2. Trevor collects and tags issues into:
   - “Theme bug” (rendering / persistence / nav)
   - “SEO/meta system” (locks, generation, templating)
   - “n8n generation quality” (prompt + QA node)
   - “Image intelligence”
   - **“Backend builder”** (sluggish editor, preview ≠ front end, CPT-specific glitches)
3. **Tuesday** review meeting:
   - confirm changes shipped
   - decide what becomes “hard gate” before full-scale launch

## Acceptance criteria

We are ready to roll full-scale when:

- No pages show “My WordPress” or incomplete brand fragments
- Manual meta edits never revert unless explicitly regenerated
- Service and service-area pages reference the correct city/area
- Overhead pages are aligned to their purpose (Why/About not cost guides)
- Service cards show all services, allow per-card unique descriptions, and link safely
- Homepage `service_intro` and `/services/` `service_grid` can carry **different** card copy without touching CPT short descriptions; builder supports editing both.
- Headers do not duplicate items or CTAs at common breakpoints; clickable surface matches expectations.
- No Airtable record ids leak into headings or SEO fields.
- Service areas overview renders map/embed when globals are set; CTA buttons remain legible on dark bands.
- Process and FAQ content match page archetypes; images do not obviously repeat across sections when alternatives exist.
- Service and service-area pages meet **heading-level local prominence** checks (money keyword + correct place visible above the fold, not buried only in body repetition).
- Benefits cards pass **title/body integrity** QA; optional process **expectations** footnote is either intentional or absent (never “mystery” fixed copy).
- Media attachment metadata carries **correct brand** and **topic**, not WordPress placeholders.
- **Backend builder** remains usable on **pages and primary CPTs**: no sustained **freeze on selection/typing** during normal edits; **logged-out front end** matches builder preview for layout and content within the **documented parity scope**; panels (SEO Health / AI) do not block core editing.

