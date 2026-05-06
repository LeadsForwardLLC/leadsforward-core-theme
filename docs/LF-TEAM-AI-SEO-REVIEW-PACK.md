# LeadsForward website builder — AI, SEO, and quality (team review pack)

**Audience:** Shannon, Alex, manifesters, PM — **no code reading required.**  
**Purpose:** One place to understand *what* instructs the AI, *how* content gets generated, *where* optimization happens, and *what* we are fixing next.

**Related deep-dive:** `docs/superpowers/specs/2026-05-06-seo-ai-workflow-hardening-design.md` (engineering delivery plan and phases).

---

## 1. There are three different “AI / SEO” surfaces (do not mix them up)

| Surface | What it does | Where instructions live |
|--------|----------------|-------------------------|
| **A. n8n orchestrator** (“manifester” job) | Webhook receives a **big JSON job** from WordPress → optional “research” JSON → **one LLM call per page blueprint** → strict JSON **updates** sent back to WordPress to apply. | **n8n workflow** (exported as `docs/n8n-workflow.json`) **+** text built in WordPress (`system_message`, strategies, blueprints) |
| **B. Theme SEO engine** (after content is in WP) | Meta titles/descriptions, keywords, structured output, coverage dashboard, etc. **Not** the same as the n8n story writer. | PHP under `inc/seo/` (engineering); behavior described in Theme Documentation in wp-admin |
| **C. In-dashboard AI Assistant** | Separate prompts for **inline / draft help** inside the builder UI. Different JSON shape than the orchestrator. | `inc/ai-editing/` — documented in `docs/09_PAGE_BUILDER_MAPS_NAV_AI.md` |

This pack focuses on **A** (orchestrator) and how it interacts with **B**, plus **issues checklist** affecting everyone.

---

## 2. “Research” — what it really means today

There is **no Google browsing or live competitor scraping** inside the exported n8n workflow.

1. WordPress sends: business name, niche, primary city/region, services, keywords, optional service areas, etc.
2. If the payload already includes **`research_document`** (strategy JSON), n8n **skips** the research LLM and uses it.
3. Otherwise, node **Research Generator** asks the model to **imagine structured strategy** from that input only: positioning, competitor-style patterns (not verified URLs), FAQ angles, clusters, tone, imagery guidance — as **pure JSON**.
4. A subset becomes **`research_context`** on **every page item** (`brand_positioning`, `conversion_strategy`, `voice_guidelines`, `seo_strategy`, FAQ angles, content expansion hints).

So “research” = **structured LLM synthesis from your manifest data**, not automated web surveillance.

---

## 3. “Generate content” — order of instructions (what wins when rules conflict)

For **each page**, the orchestrator stacks rules in roughly this priority:

1. **n8n “Basic LLM Chain” prompt** — Long deterministic instructions: JSON-only output, only **`allowed_field_keys`** from blueprint, niche/city/business name locks, minimum word counts, benefits/process/CTA formats, uniqueness, FAQ targets when blueprint specifies counts, no placeholder tokens on the page, etc. **Retried** (`LLM Retry Chain`) when quality warnings qualify.
2. **WordPress theme `system_message`** — Produced by **`lf_ai_studio_llm_system_message()`** (see Section 4 verbatim rules). These are pasted into every job. Prompt text says **if both conflict, theme `system_message` wins**.
3. **Optional `research_context`** — Influences differentiation and tone when present.
4. **Per-blueprint hints** — `section_label`, `intent`, `purpose`, `field_labels`, `field_types`, service/service_area context.

The model fills **dot-notation fields** like `hero.hero_headline`, `faq_accordion.section_heading`, keyed to each section **`section_id`**.

Blog posts additionally get **`Blog Planner`** text injected (archetypes: pillar, cost, how-to, troubleshooting, local guide).

---

## 4. Theme system message (verbatim policy text sent on every orchestrator job)

The following lines are appended as **`system_message`** from WordPress (human-readable excerpt of production behavior):

- Return JSON only. No markdown, no commentary.
- HTML allowed only in richtext fields (`<p>`, `<ul>`, `<li>`, `<a>`).
- Use only **`allowed_field_keys`**. Do not invent fields.
- Use section **intent**, **purpose**, **field_labels**, **field_types** when supplied.
- **Headlines:** no dash/hyphen separators; sentence or title case; no trailing punctuation except `?`; **hero headline max 12 words**.
- **Benefits:** 15–35 words each, max 2 sentences per benefit; no dashes in benefit titles.
- **Internal links:** 1–2 only in **richtext/body**, from **`internal_links` list** — never in headlines, intros, bullets, CTA buttons, etc.
- **Intent:** Service/service-area ↔ transactional/local; blogs ↔ informational. Align headings and opening paragraph accordingly.
- **Research:** Apply `research_context` strategically; never copy verbatim.
- **Separation by page type:** Homepage vs services overview vs service vs service-area overview vs area vs blog — each gets distinct rules so copy is not reused.
- Blogs: long-form, depth, homeowner takeaways; follow **`blog_post_type`** archetype when present.
- Never reuse sentences across page types.
- **Minimum:** ~1000 words of unique body copy per **page total** except **`service_details_body`** stays intentionally short (~220 words max typically; see blueprint **`length_targets`**).
- Never output **`PRIMARY_KEYWORD`**, **`BUSINESS_NAME`**, **`CITY_REGION`**, **`NICHE_TOKEN`**, or **`[Your City]`** literals in visible copy — always real values from payload.
- **FAQ:** Global evergreen pool concept (8–12); homepage ~5 visible; services 4–6; areas 3–5; overview 3–4; reuse unless context demands variation (**see Section 5 numeric table).
- **CTA:** Homepage is canonical global CTA; each page adds **exactly one** contextual sentence in **`cta_subheadline_secondary`** — no duplicates across pages. Button labels: 2–5 words, max 32 characters.
- **Service details:** thin overview in body; checklist lines only where specified; limits on micro-lines and proof fields.
- **Process:** CPT-backed steps **`process_selected_ids`** when IDs resolve; otherwise string lines with separators.
- Services: **`short_description`** 25–35 words with benefit + location.

---

## 5. FAQ / CTA strategy objects (numbers sent beside the prose)

### FAQ counts (intent from theme)

| Page type | Audience |
|-----------|----------|
| Global evergreen pool | 8–12 FAQs |
| Homepage | **5** |
| Service page | **4–6** |
| Service area page | **3–5** |
| Services overview / Service areas overview | **3–4** |

**Reuse policy:** “Reuse global pool whenever possible; only vary for context.”

### CTA strategy

- **Global CTA** (homepage): write once to **options** — headline, subheadline, primary/secondary button overrides.
- **Each other page:** one sentence in **`cta_subheadline_secondary`**; **no exact duplicates** across pages.

---

## 6. What WordPress sends in the webhook payload (plain English checklist)

Roughly includes:

- `request_id`, `variation_seed`, `job_id`, `callback_url`
- **`business_name`**, **`niche`**, **`city_region`**, **`keywords`** (primary + secondary)
- **`business_entity`** (structured business facts)
- **`writing_samples`** (tone reference)
- **`system_message`** (Section 4)
- **`faq_strategy`**, **`cta_strategy`** objects (Section 5)
- **`internal_links`** catalog + linking rules (max links per rich text)
- **`image_generation`** / **`media_library_candidates`** (plans for attaching/scoring library images — not taking new photo shoots)
- **`blueprints`**: array of **one blueprint per URL** — each lists **sections** with **`allowed_field_keys`**, intents, FAQs targets, **`post_id`**, **`page_type`** context, etc.
- Optionally **`research_document`** to bypass research LLM.

Engineering builds this in **`inc/ai-studio.php`** (function that assembles **`build_full_site` ** / manifest payload).

---

## 7. After the AI writes JSON — deterministic steps (still in n8n)

These are **code nodes**, not imagination:

| Step | What it does |
|------|----------------|
| **Parse + Normalize + CTA Guard** | Validates JSON from LLM; maps field keys onto allowed blueprint paths; substitutes leaked template tokens once; derives **`service_meta`** short descriptions for services |
| **Quality Gate + SEO Enforcement** | **Adds warnings** (missing primary keyword, duplicate sentences, duplicate identical fields, banned generic phrases, “in your area” on wrong page types, low word count, etc.). **Does not hard-fail** the run in most cases — it flags and may trigger **retry** |
| **Retry Decision / LLM Retry** | Up to **2** retries with “fix these warnings” instructions |
| **Deterministic FAQ Enforcement** | Wraps FAQ answers in `<p>` if needed; **forces unique** `cta_subheadline_secondary` lines using canned variants when duplicates slip through |
| **Merge Blueprint Results** | Merges all pages; **uniqueness guard** can append extra bits to force unique headings/body when collisions happen (can feel machine-made) |
| **Attach Callback Metadata** | Adds **`media_annotations`** (or fallbacks from filename/alt matching) |
| **Callback to WP** | POST payload to theme REST **orchestrator** to apply updates |

**Model (from architecture doc):** Page writer and research use **`gpt-5.2-chat-latest`** in the workflow export; token limits are set per node (see `docs/02_N8N_WORKFLOW_ARCHITECTURE.md`).

---

## 8. Theme SEO (separate from n8n “writer”)

Once content and meta fields exist in WordPress, the **theme SEO layer** can:

- Normalize polluted keywords (e.g. strip junk from Airtable)
- Compose **meta title / description** with structured rules
- Run **SEO quality** / **coverage** checks in admin
- Output tags for front-end / SERP

That is **not** the same instruction set as the n8n JSON writer. Bad **on-page** experience can be **LLM** or **theme** or **both**.

---

## 9. Known contradictions (why sites can feel “SEO wrong” even when rules exist)

| Topic | What happens |
|-------|----------------|
| **City in headings** | n8n writer prompt historically pushed **city in only one heading** type rules; the **team checklist** wants **money keyword + target place in H1 and first big H2** on service/service-area URLs. Until QA enforces locality in headings, outputs will oscillate. |
| **FAQ pools** | Theme strategy encourages **FAQ reuse across pages**; field issues show **area-flavored FAQs on services** unless routing is tightened in workflow + manifests. About pages need **company-grounded**, not recycled exemplar answers. |
| **Benefits count** | Writer prompt may emphasize **few lines** here; **`lf_ai_studio_section_length_targets`** mentions **benefit `min_items` 5** in theme code while checklist caps at **six** cards with preference for fewer. Numbers need reconciliation. |
| **`process_expectations`** | Prompts can imply multi-item text; frontend shows **shortened** output → looks like stray “ghost” copy; editors need it visible/clearable. |
| **`Merge` uniqueness guard** | Can mechanically alter strings to dodge duplicates → awkward phrasing (“This page focuses on…”) appearing on pages — feels un-human. |

---

## 10. LF Website Builder Issues Checklist (canonical — master list for remediation)

Copy this into Sheets/Notion; tag items Theme / n8n / Both.

### Brand, meta, and keywords

- Stop “My WordPress” (and other defaults) in titles, descriptions, and media fields.
- Fix meta that **reverts** after save (**locks + explicit regenerate**).
- Fix builder **title/description** mismatch vs live site (**cache / render parity**).
- Strip Airtable junk like `recXXXXXXXX` from keywords, headings, meta.
- **One primary keyword per URL**; stop inheriting homepage keyword everywhere.

### Local SEO

- Service-area pages: money phrase + that area in **H1** and **first big H2** + early in copy — not buried.
- Service pages with city in URL/slug: **city + service** in **H1** or **first H2**.
- Wrong city on wrong page → **blocked in QA** (Youngstown on Austintown, etc.).
- Related-services tiles use **this page’s area** or neutral language — not HQ city.
- Homepage: main trade + market in **headings**, not one weak mention.

### Navigation and layout

1. Duplicate menu items (e.g. Service Areas twice).
2. Duplicate Call / Free Estimate; messy header stack.
3. Nav links that don’t work.
4. Service areas overview: **map promised but missing**.
5. CTA bands: buttons too dark on dark backgrounds.
6. Footer spacing / oversized logo column.

### Page types (“wrong story”)

1. Why Choose Us reads like a **cost guide** (wrong intent).
2. **Exactly one About** + **exactly one Why**; dedupe duplicates in sitemap.
3. About FAQs must be **actually about our company**, dynamically grounded in manifest/business_entity.

### Process, benefits, FAQs

1. Process steps repetitive (same “inspection/assessment” loop).
2. **`process_expectations`** “mystery” box → must be editable/clearable (or unset in generation).
3. Benefit cards: **fewer by default**, **six maximum**; tighten headline length and **headline/body match**.
4. Remove/fix **uneditable duplicated** checklist/boilerplate micro-lines where present.
5. FAQs from correct **intent bucket** (no area-only FAQs on unrelated service pages).

### Images

1. No duplicate attachments across unrelated sections where alternatives exist.
2. Image relevance to section topic.
3. Alt/title/filename/description match topic; kill “My WordPress” in captions.
4. Service-area imagery: geo-authentic or geotagged when ops cares.

### Service cards / homepage vs `/services`

1. Different blurbs intro vs grid; all link correctly (draft fallback to `/services/` already theme-supported).
2. Stop repeating city in **every** card the same way.

### Coverage / grading

1. Stop **green grades** while content is objectively bad (**tighter checks** vs human bar).
2. Topic depth rules: H2s, internal linking, FAQs that match searcher intent.

### Backend builder (**priority review**)

1. **Performance** profiling (typing, selection, scroll, CPTs).
2. **Preview = logged-out frontend** parity (document gaps: cache, iframe, admin chrome).
3. Stop freezes from stacked overlays (**SEO Health / AI / toolbar**).
4. Expose **`process_expectations`** and comparable fields in sidebar.

### Workflow (n8n / manifest)

1. **Structured contract** per `page_type` (fields for locality + forbidden cities + required headings).
2. Real **Second-pass QA** before publish (**fail or route** to human).
3. **Reconcile duplicates** before creating competing About/Why pages.

---

## 11. Field guide for bug reports

When something is broken, tagging helps:

| Tag | Examples |
|-----|----------|
| **Theme bug** | Nav layout, footer, duplicate menu items, rendering, CPT fields, boilerplate stripping, preview iframe |
| **SEO/meta system** | Meta composition, normalization, leaks, checklist scoring, `_lf_seo_*` behavior |
| **n8n generation quality** | Bad copy, FAQs, contradiction with checklist, blueprint instructions |
| **Image intelligence** | Wrong or repeated attachments, captions |
| **Backend builder** | Sluggish editing, freezes, mismatch vs live |

---

## 12. Where engineers look first (reference only — team can skip)

| Asset | Contents |
|-------|----------|
| `docs/n8n-workflow.json` | Full workflow + **embedded LLM prompt strings** (Research Generator, Basic LLM Chain, Retry chain) |
| `inc/ai-studio.php` | Payload assembly + `lf_ai_studio_llm_system_message`, FAQ/CTA strategy, blueprint audit |
| `docs/06_AI_PROMPT_ENGINE.md` | Narrative explanation of blueprint + prompts |
| `docs/02_N8N_WORKFLOW_ARCHITECTURE.md` | Pipeline diagram |
| `inc/seo/*.php` | Post-publish meta / SEO tooling |
| `docs/superpowers/specs/2026-05-06-seo-ai-workflow-hardening-design.md` | Consolidated remediation plan + phased delivery |

---

## 13. What we intend to ship next (orientation only)

Briefly: brand/city **source of truth**, **meta locks**, **strip `rec` IDs**, **strict FAQ routing** (esp. About/company pools), **headline locality QA**, media caption hygiene, **builder performance + parity**, reconcile duplicate overhead pages — see the **spec** for Phase 1–3 detail.

---

*Document generated from theme sources. When workflow export or theme messages change, update this pack in the same PR or note drift in team channel.*
