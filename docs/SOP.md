LeadsForward SOP — Site Build Checklist (Append-Only)
====================================================

This SOP is the official, step-by-step process for building a complete, SEO-optimized,
high-converting home-service site with the LeadsForward theme.

Do not rewrite this SOP. Append new steps or notes at the bottom when the product changes.

---

1) What the theme is
-------------------
LeadsForward is a productized WordPress theme for local home‑service businesses.
It builds a full lead‑generation site with a conversion-focused homepage, service pages,
service-area pages, and a Quote Builder.

---

2) What it builds
----------------
The theme builds a complete local‑service website with:
- A premium homepage (controlled by the Homepage Builder)
- Service pages (for each service)
- Service‑area pages (for each location)
- Core pages (About, Contact, Reviews, Blog, Sitemap, Legal, Thank You)

All core pages use the same Page Builder Framework and shared section library.

---

3) What the setup wizard does
-----------------------------
When you run Setup:
- Creates all required pages for the selected niche
- Creates default services and service areas
- Seeds the Homepage Builder with a high‑converting layout
- Seeds the Page Builder for services, service areas, and core pages
- Creates header and footer menus
- Applies branding, CTA defaults, and schema settings

---

4) How to create a site from scratch (step by step)
---------------------------------------------------
Step 1: Install the theme and required plugins (ACF).
Step 2: Go to LeadsForward → Setup.
Step 3: Choose “General (Local Services)” and complete the wizard.
Step 4: Review the Homepage Builder and confirm section order and copy.
Step 5: Review Page Builder for:
        - Services (each service page)
        - Service Areas (each area page)
        - Core pages (About, Contact, Reviews, Blog, Sitemap, Legal, Thank You)
Step 6: Update business info, branding colors, and logo in Global Settings.
Step 7: Publish (no manual page building required).

---

5) Default install blueprint (General Local Services)
-----------------------------------------------------
When “General (Local Services)” is selected, the theme creates:

Required pages:
- Home
- Our Services (overview)
- Our Service Areas (overview)
- About Us
- Contact
- Reviews
- Blog
- Sitemap
- Privacy Policy
- Terms of Service
- Thank You

Each page uses the Page Builder Framework and pre‑configured sections:

Home (Homepage Builder)
- Hero
- Trust Bar
- Service Intro Boxes
- Benefits
- Content with Image (A)
- Image with Content (B)
- Content with Image (C)
- Service Details
- Process
- FAQ
- Related Links
- Map + Service Areas
- CTA (last)

Benefits cards use: icon + title + short explainer text (3 cards).
Explore More tiles (Related Links) can render image-backed cards with overlay text.

Services (single service page)
- Hero
- Trust Bar
- Benefits
- Content with Image (A)
- Image with Content (B)
- Service Details
- Process
- FAQ
- Related Links
- CTA (last)

Service Areas (single area page)
- Hero
- Trust Bar
- Benefits
- Content with Image (A)
- Image with Content (B)
- Services Offered Here
- FAQ
- Nearby Areas
- Map + Service Areas
- CTA (last)

Our Services (Overview page)
- Hero
- Trust Bar
- Centered Content
- Service Intro Boxes
- Content with Image (A)
- Process
- FAQ
- CTA (last)

Our Service Areas (Overview page)
- Hero
- Centered Content
- Nearby Areas
- Content with Image (A)
- FAQ
- CTA (last)

Contact (Page Builder)
- Hero
- Centered Content
- Map + Service Areas
- CTA (last)

Privacy Policy (Page Builder)
- Hero
- Content

Terms of Service (Page Builder)
- Hero
- Content

Thank You (Page Builder)
- Hero
- Content with Image
- CTA

---

6) What NOT to touch
-------------------
Do not edit:
- Theme PHP files
- CSS files
- Section templates
- Page templates

Use ONLY the structured settings in:
- LeadsForward → Homepage
- LeadsForward → Global Settings
- Page Builder meta box (on core pages, services, and service areas)
- LeadsForward → Quote Builder

This keeps the site consistent, fast, and safe.

---

7) How updates work safely
--------------------------
- Theme updates are safe because all content lives in settings and structured fields.
- Re‑running the Setup Wizard does NOT delete content; it only fills missing items.
- Use “Reset” only when you want a clean start.
- If you need a change, update settings — do not edit templates.

---

Append‑Only Change Log
----------------------
2026‑02‑08 — SOP created.

2026‑02‑08 — Niche page matrices added.

---

Niche Selection Addendum (Append‑Only)
-------------------------------------
When you select a niche, the wizard adds niche‑specific service pages in addition to the General pages.
These are created automatically and pre‑configured with the Page Builder Framework.

Roofing adds:
- Roof Repair
- Roof Replacement
- Storm Damage
- Emergency Roofing
- Commercial Roofing

Plumbing adds:
- Drain Cleaning
- Water Heater Repair
- Leak Detection
- Emergency Plumbing

HVAC adds:
- AC Repair
- AC Installation
- Heating Repair
- Maintenance Plans

Landscaping adds:
- Lawn Care
- Landscape Design
- Hardscaping
- Seasonal Cleanup

Operator customization rules:
- You MAY reorder sections, edit copy, and toggle sections ON/OFF.
- You MAY update branding, CTAs, and global business info.
- You MAY add/remove services and service areas through the structured UI.
- You MUST NOT edit theme templates, PHP, or CSS files.
- You MUST NOT change core page slugs or delete core pages.

Internal linking rules:
- Service pages link to service areas (via the Map + Areas section).
- Service areas link back to services (via Services Offered Here).
- Homepage links to top services and areas (Related Links + Map section).

---

Core Pages Editing & SEO Addendum (Append‑Only)
-----------------------------------------------
Editing core pages safely:
- Use the Page Builder panel on each core page.
- Adjust section order, headings, and copy only in the structured fields.
- Do NOT use Gutenberg blocks for layout or custom HTML.
- The standard WordPress editor is hidden for core pages to prevent conflicts.

SEO best practices (per core page):
- **About Us:** Keep the H1 clear (About {Business}); describe what makes you credible.
- **Services:** Use a short H1, then list services; keep the meta description concise.
- **Service Areas:** Mention the primary city/area in the hero subheadline.
- **Reviews:** Emphasize trust; keep descriptions factual and short.
- **Blog:** Focus on helpful, local advice; keep titles readable.
- **Contact:** Include phone and service area in the hero subheadline.
- **Privacy/Terms:** Use clear legal titles; keep these pages simple.
- **Thank You:** Confirm next steps; avoid aggressive sales copy.
 - **Meta title default:** Uses the Hero headline if set; otherwise the page title.
- **Meta description default:** Uses the Hero subheadline (homepage + core pages) or page excerpt.

Conversion best practices (per core page):
- Keep one primary CTA near the end of each core page.
- Use the Quote Builder CTA on About, Services, Areas, Reviews, and Blog.
- Include internal links to services and areas where relevant.

Final QA checklist before launch:
- All core pages exist and use the Page Builder.
- H1 appears only once per page (hero section).
- Meta titles/descriptions are set or have sensible defaults.
- CTAs open the Quote Builder or call the business phone.
- Service pages and service areas link to each other.
- Homepage links to top services and areas.

Content fields policy:
- Page Attributes and Featured Image are hidden for core pages.
- Featured Image remains available for blog posts only.

---

Entity‑First Local SEO Addendum (Append‑Only)
--------------------------------------------
Business Entity (single source of truth):
- Go to **LeadsForward → Global Settings → Business Entity**.
- Fill in business name (display + legal), primary phone, address, and category.
- Add service areas (one per line) and business hours.
- Upload a logo and a primary image.
- Add social profiles + Google Business Profile URL.
- Optional: sameAs links, founding year, license number, insurance statement.

Schema behavior (automatic):
- Global schema outputs: LocalBusiness + Organization + WebSite + BreadcrumbList.
- Service pages: Service schema + OfferCatalog.
- Service area pages: LocalBusiness addendum with area served.
- FAQ schema is added only when an FAQ section is enabled.
- Review schema outputs when testimonials exist.

Internal linking (deterministic):
- Homepage, service pages, and service area pages include hub‑and‑spoke modules.
- Anchors vary deterministically based on site seed + page ID.
- Each module caps at 8 links by default.

SEO Coverage validator:
- Run **LeadsForward → Site Health → Pre‑Launch Check**.
- Flags missing hubs, missing services/areas, orphaned areas, and thin pages.

---

Append‑Only Change Log (Recent)
-------------------------------
2026‑02‑09 — Setup Wizard merged with AI Studio for homepage‑only generation flow.

2026‑02‑09 — AI Studio REST endpoints added (blueprint + apply) with shared secret auth and job logging.

2026‑02‑09 — Hero controls now match across Homepage Builder and Page Builder (variant + CTA toggles/actions). CTA resolution is unified via `lf_resolve_cta()`. SEO overrides now apply to services and service areas. Sortable behavior is shared via `assets/js/lf-section-sortable.js`.

2026‑02‑06 — Heading rules enforced (single H1 + hierarchy validation). Content H1s are demoted on render; warnings appear in editor and Site Health.

2026‑02‑06 — Header navigation auto-generated after setup with a non-clickable “More” dropdown and Call Now / CTA actions.

2026‑02‑06 — AI Studio added for orchestrator-driven site content generation (no OpenAI keys stored).

---

Heading Rules (Append‑Only)
---------------------------
- Exactly one H1 per page.
- Hero owns the H1 when present.
- Page titles are demoted to H2 when a hero is active.
- Editor H1s are demoted on render; fix them when warned.

---

Navigation Defaults (Append‑Only)
---------------------------------
- Header menu is auto-generated after setup.
- Top-level: Home, Services, Service Areas, Reviews, More, Call Now, Free Estimate.
- “More” contains About, Blog/Posts, Contact and is not a clickable link.

---

AI Studio (Append‑Only)
----------------------
- Configure in **LeadsForward → AI Studio**.
- Writing samples are controlled in n8n.
- Optional deterministic mode: upload a JSON manifest to bypass wizard inputs.
- Canonical schema: `docs/MANIFEST_SCHEMA.md`.
- Section schema reference: `docs/SECTION_SCHEMA.json` (update when section registry changes).
- Manifest upload shows a progress overlay during generation.
- One-click “Generate Site Content” posts a blueprint to the orchestrator webhook.
- Dev reset clears nearly all content (pages, posts, CPTs, manifest, keywords, generation logs) but preserves AI Studio settings (enable, webhook, shared secret).
- Dev reset also clears site title/description to remove business evidence.
- AI collaboration guide: `docs/AI_CONTEXT.md` (keep in sync for multi‑assistant work).
- Manifest generation also runs the setup scaffold (pages, menus, business entity) using manifest values only.
- Response payload is validated and applied to existing builder fields.
- REST endpoints require the shared secret in the `Authorization` header.
- Blueprint endpoint: `GET /wp-json/leadsforward/v1/blueprint`.
- Apply endpoint: `POST /wp-json/leadsforward/v1/apply`.

---

Homepage Generation Flow (Append‑Only)
--------------------------------------
- Run **LeadsForward → Setup** to enter business info, niche, city/region, and homepage keywords.
- Choose the homepage hero variant and a variation profile.
- Enable “Generate homepage now” to run the orchestrator immediately after setup.
- Homepage regeneration is available in **LeadsForward → AI Studio (Advanced)**.

---

Homepage Generation Flow (Builder‑aware)
---------------------------------------
- Blueprint reads the current Homepage Builder config (enabled sections + order).
- AI can only write to allowed copy fields; layout, order, and toggles are read‑only.
- Allowed fields are flattened as `section_id.field_key` for safe mapping.

---

AI Generation Quality Rules (Append-Only)
-----------------------------------------
- Headlines use sentence or title case with no dash or hyphen separators.
- Hero headline max 12 words; no trailing punctuation unless a question.
- Benefits: 15-35 words each, max 2 sentences, no dash separators in benefit titles.
- Never reuse sentences across page types; follow page-specific focus rules.
- FAQ strategy uses a global pool with per-page counts; reuse unless context requires variation.
- CTA strategy: homepage CTA is canonical; add one contextual sentence per page in `cta_subheadline_secondary`.
- Output normalization removes escaped apostrophes/backslashes before save.

---

Homepage Pre‑AI Configuration (Append‑Only)
------------------------------------------
- Defaults apply only on fresh setups or when resetting homepage defaults.
- Existing sites retain their saved homepage order and enabled sections.
- Default order (fresh setups): Hero → Trust Bar → Benefits → Service Intro Boxes → Service Details → Content with Image (A) → Image with Content (B) → Content with Image (C) → Process → FAQ → CTA → Related Links → Service Areas + Map.
- The default homepage order includes three media sections (A/B/C) with distinct intent metadata.
- Intent (`section_intent`, `section_purpose`) guides AI generation and is not shown on the frontend.

---

Deterministic Content Isolation Architecture
-------------------------------------------
- Homepage content is blueprint-controlled only; rendering uses section fields stored in `lf_homepage_section_config`.
- Homepage does not reuse Service CPT body or excerpts; service cards use `lf_service_short_desc` only.
- Service CPT body content remains isolated to Service pages (Page Builder or `post_content` fallback only).
- Service Details on the homepage renders stored section fields only (no CPT fallback).
- AI generation relies on explicit section field boundaries plus `section_intent` metadata.
- Isolation prevents duplication across homepage summaries and Service pages, and protects regeneration determinism.

---

Deterministic Template Rules
---------------------------
- Templates and blocks render only section fields stored in options/meta that exist in the section registry.
- Homepage sections never read Service/Service Area CPT body or excerpts.
- No implicit fallbacks to `post_content`, `get_the_content()`, or `get_the_excerpt()`.
- Add new fields in `lf_sections_registry()` and include them in `allowed_field_keys` for AI writes.

---

Full Site Generation Architecture
--------------------------------
- Setup Wizard and AI Studio regenerate send one unified payload to n8n.
- Payload includes homepage + each Service + each Service Area + About page blueprints.
- Each blueprint contains `sections`, `order`, `page_intent`, and `allowed_field_keys`.
- All updates route through `lf_apply_orchestrator_updates()` with per-section allowed field validation.
- Deterministic enforcement: no implicit fallbacks or cross-context content reuse.
- Payload includes `writing_samples` (empty by default; orchestration can supply samples in n8n).

---

Content Density: Template Field Coverage
---------------------------------------
- Section templates render every field included in `allowed_field_keys`.
- Field coverage uses existing schema only; no new keys or section types.
- Admin debug logging outputs section/instance id, allowed keys, and rendered keys when `WP_DEBUG` is enabled.
- Deterministic compliance: no post content or excerpt fallbacks are used to increase density.
- List fields are newline-delimited and parsed via `lf_sections_parse_lines()` before rendering.

---

Content Density: Schema Expansion
--------------------------------
- Added optional long-form fields to section registry only (no template changes).
- New fields are non-required defaults; existing content remains valid.
- New fields are included in `allowed_field_keys` automatically.
- Determinism preserved: no fallback logic added or registry structure changes.

---

Homepage Default Order (Conversion)
----------------------------------
- Order: hero → trust_bar → service_intro → benefits → content_image_a → image_content_b → content_image_c → service_details → process → faq_accordion → related_links → map_nap → cta.
- CTA appears once and is last.
- Content/image variants A/B/C are enabled by default.

---

Content Density: Long-Form Utilization – Step 3
----------------------------------------------
- Length targets are added to blueprints as metadata; templates remain unchanged.
- `section_intent` metadata is included to guide long-form writing.
- Targets: Hero 20–40 words combined; Benefits 5 items at 40–80 words each; Process 4 steps at 40–80 words each; Service Details 600–1200 words; Content/Image 300–600 words; FAQ 5–8 answers at 80–150 words.
- Deterministic isolation preserved: no CPT body reuse; only allowed fields are writable.

---

Content QA & Auto-Repair (Append‑Only)
--------------------------------------
- After AI generation, a content audit checks every page section for empty or default content.
- The audit records missing fields and CTA duplication across all pages.
- If issues are found, the system automatically requeues **one** repair pass targeting missing fields only.
- Operators can run the audit manually from **Website Manifester → Content QA Report**; if issues remain, a repair job is queued.

Quote Builder Dynamic Fields (Append‑Only)
------------------------------------------
- Quote Builder service options auto‑populate from created Service pages.
- When no Services exist, options fall back to niche defaults.
- Additional niche‑specific fields are inserted into the “Project details” step.

Service Overview Cards (Append‑Only)
-----------------------------------
- Homepage Service Intro cards render `lf_service_short_desc` only.
- If that field is empty after AI generation, it is auto‑backfilled from the service page’s AI content.

Content Section Rendering (Append‑Only)
--------------------------------------
- The `content` section now renders `section_heading`, `section_intro`, and `section_body` fields.
- Core/internal pages seeded with `content` sections now display AI-written copy instead of empty blocks.

Benefits + Process Enhancements (Append‑Only)
---------------------------------------------
- Benefits cards enforce concise titles and uniform body lengths for clean layout.
- Process now supports expectations list + trust block when provided by AI.

Service Details Media (Append‑Only)
-----------------------------------
- Service Details includes an optional media column (video embed default, image optional).
- When no embed is set, the layout still renders with a styled media placeholder.

Footer Structure (Append‑Only)
------------------------------
- Footer now includes NAP, license (if present), and organized link columns.
- Privacy and Terms are separated in the legal row for clarity.

Blog Post Generation (Append‑Only)
----------------------------------
- When core pages are generated, the system creates up to 3 AI blog posts if none exist.
- Blog posts are filled via Page Builder sections and auto-backfill post titles/excerpts from the hero copy.

Manifester UX (Append‑Only)
---------------------------
- Logo upload sits in the Manifester and applies brand colors before generation.
- Manifest Upload and Airtable Projects live in one combined panel for quick selection.
- QA panel includes a “Regenerate AI Blog Posts” action for blog-only refreshes.

Append‑Only Change Log (Append‑Only)
-----------------------------------
2026‑02‑11 — Added Content QA audit + one‑pass auto‑repair and dynamic Quote Builder fields.
2026‑02‑12 — Added content section rendering, benefits/process layout polish, service details media, and footer upgrade.
2026‑02‑12 — Added AI blog post generation + title/excerpt backfill.
2026‑02‑12 — Added Manifester logo upload, combined generate panel, and blog regen action.
