# SEO + AI workflow hardening (design + execution plan)

This document is the plan Trevor and the LeadsForward theme will follow to close the remaining quality gaps reported by the team (SEO targeting drift, “My WordPress” brand leaks, meta changing unexpectedly, and human-level on-page optimization).

Scope covers theme behavior, n8n workflow upgrades, and a feedback loop using a “gold standard” site Shannon polishes as the exemplar.

## Goals

1. **Never leak placeholder identity** in titles/meta (no “My WordPress”, no “Site Title” defaults, no incomplete truncations).
2. **Keep intent + topic per URL** stable (no “everything is foundation repair”, no cost-guide tone on Why/Company pages unless explicitly intended).
3. **City targeting never drifts** (no Raytown copy on Independence pages).
4. **Manual edits stay put** (if a human fixes meta, the system must not quietly overwrite it).
5. Raise baseline “human-level” quality by adding **deterministic quality gates** and **a guided improvement loop** rather than freeform generation.

## Non-goals

1. Fully autonomous “publish forever” blog autopilot without review.
2. Rewriting slugs/URLs automatically.
3. Turning the theme into a monolithic AI product. This remains a **controlled manifest + apply system**.

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
| **Editor freezes** when selecting/highlighting text | Heavy inline scripts, hydration, conflict with overlays (needs profiling) | Builder app + theme audit |
| Homepage “only one weak mention” of core keyword | Hero/H2 prompts not enforcing primary topic + locality for home archetype | Workflow + checklist |

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

### 15) Builder / admin performance (“can’t highlight; freezes”)

**Requirement**

- Reproduce trace with theme-only vs builder plugin; throttle expensive listeners during text selection.

**Deliverable**

- Performance ticket: profiling selection/focus handlers; defer non-critical AI chrome while editing text.

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
- locality cues (when appropriate)

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

