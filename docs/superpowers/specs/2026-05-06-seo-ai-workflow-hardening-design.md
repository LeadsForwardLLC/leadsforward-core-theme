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

### 5) Service cards improvements (already done)

- Unique per-card descriptions (section-level override, not global CPT mutation)
- Include unpublished services; link unpublished to `/services/` until published

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

- primary keyword present in opening paragraph
- keyword not stuffed
- wrong city references not present
- headings align with intent (Why/About not written as “Costs”)
- meta title/description are human-readable and non-redundant
- no placeholder brand strings

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

