# Internal Link Map + Templates Audit + Manifester Uniqueness (Design)

**Date:** 2026-04-09  
**Status:** Approved (Trevor)  
**Theme:** `leadsforward-core-theme`  

## Goal

1) Add a backend **Internal Link Map** view to visualize how pages connect and identify orphans/broken targets.  
2) Audit and perfect default **page templates** (starting with Service Area) so generated sites ship conversion-complete out of the box.  
3) Ensure the **Website Manifester** produces **unique, high-quality content** across all pages (homepage already strong), with deterministic allowances for content that may repeat.
4) Rework **Process Steps** to behave like FAQs: sourced from the `lf_process_step` CPT, selectable per section, and auto-picked by service type.

## Non-goals

- Rebuilding the entire editor UI from scratch.
- Changing the manifest schema version unless required.
- Making every string on the site unique (some reuse is allowed by design; see below).

---

## Definitions

- **Context**: where content is stored and applied (homepage options vs post meta for pages/services/service areas).
- **Section instance**: a particular section ID on a page (e.g. `benefits`, `content_image__2`).
- **Allowed-to-repeat content**: content that may be identical across multiple pages without failing the manifester gate.

---

## 1) Internal Link Map (Backend)

### User stories

- As an admin, I can see **which pages link to which pages**.
- As an admin, I can see **inbound link counts** and **orphan pages** (0 inbound).
- As an admin, I can see **broken internal links** (target cannot be resolved).
- As an admin, I can filter by post type (page/service/service area/post) and search by title/slug.

### Data sources to scan

We need links from all places we persist/emit HTML that can contain anchors:

1) **Inline DOM overrides** (selector → HTML/text):
   - Homepage: `lf_ai_inline_dom_overrides_homepage` (option)
   - Non-homepage: `_lf_ai_inline_dom_overrides` (post meta)

2) **Page Builder sections** (post meta `LF_PB_META_KEY`):
   - Any section settings fields that allow rich text / HTML and may include `<a href=...>`.

3) **Homepage section config** (`LF_HOMEPAGE_CONFIG_OPTION`):
   - Section settings that can contain rich text / HTML and may include `<a href=...>`.

4) (Optional/Phase 2) **Post content**:
   - `post_content` for posts/pages if theme uses it for longform content.

### Link extraction rules

- Parse HTML and extract anchors:
  - `href` values
  - (Optional) `target`/`rel` for diagnostics only
- Classify as:
  - **internal**: same host or relative URL, resolves to a WP post ID
  - **external**: different host
  - **broken internal**: appears internal but cannot resolve to a known post/page
- Normalize URL for deduping:
  - strip trailing slashes (consistent rule)
  - strip fragments for “page-to-page” map (keep fragments for detail view)
  - ignore querystring for map unless explicitly asked (default ignore)

### UI shape (admin screen)

- New admin menu item under LeadsForward (or AI Studio) named **Internal Link Map**
- Tabs or sections:
  - **Overview**: totals, orphan count, broken internal count
  - **By Page**: table view
  - **Graph** (optional v1.5): simple adjacency visualization (can be a later enhancement)

Table columns (By Page):
- Page title (click to edit / view)
- Post type
- Outbound internal links (count)
- Inbound internal links (count)
- Broken internal links (count)
- External links (count)

Detail drawer (or separate screen):
- For selected page:
  - list of internal targets with counts
  - list of inbound sources
  - list of broken internal hrefs + where found (source key)

### Performance considerations

- Cache computed map results for a short time (e.g. transient 5–15 minutes) or compute on demand with paging.
- Provide a “Rebuild map” button.
- For v1, scope to a reasonable number of posts and provide filters.

---

## 2) Template audit + “golden stacks”

### Objective

Default templates should be “conversion-complete” without manual rearranging:

- Service pages
- **Service Area pages (priority)**
- Core pages (About, Why Choose Us, etc.)
- Blog posts (secondary)

### Deliverables

- A documented “golden” default section stack per context.
- Ensure initial seeding (wizard/manifester) uses those stacks.
- Ensure the editor’s section library only shows sections valid for the current context.

### Validation

- Create a lightweight audit routine:
  - “Does this page have required sections?”
  - “Are required fields non-empty?”
  - “Are any sections disabled unexpectedly?”

This can be implemented as:
- a CLI script (optional), and/or
- an admin “Template Audit” screen or an AI Studio audit entry.

---

## 3) Manifester: uniqueness + completeness gate (mostly unique)

### Allowed-to-repeat content (approved)

The following may repeat across pages without failing the run:

- **Trust elements**: ratings, badges, certifications strip content
- **CTAs**: CTA blocks (headline/buttons/embeds) may repeat
- **FAQs**: library-driven content / selected IDs may repeat
- **Process steps**: CPT-driven content / selected IDs may repeat

### Must-be-unique content (examples)

These should be unique per page (or at least not identical across large sets of pages):

- Hero heading/subheading (page-specific)
- Primary “content” section bodies
- Benefits item title/body pairs
- Service Details bodies/checklists (unless later made library-driven)
- Service Area content sections describing the location

### Gate behavior

At the end of manifester apply:

- Compute a **completeness report**:
  - missing required pages
  - missing required sections for each page context
  - missing required fields (by section type)
- Compute a **uniqueness report**:
  - detect identical strings across pages for fields that must be unique
  - ignore/allow repeats for the allowed categories above

If the gate fails:
- mark the run as failed (but keep diagnostics)
- list exact pages/fields that failed and the duplicates they match

If the gate passes:
- record baseline “healthy” state for autonomy eligibility

### Determinism / avoiding false positives

- Compare normalized text (trim, collapse whitespace, strip HTML tags for comparisons) but store original.
- Allow short strings (e.g. very short headings) to repeat within reason:
  - example rule: ignore duplicates under N characters (configurable)
- Exclude global boilerplate tokens (e.g. business name) from uniqueness scoring where possible.

---

## 4) Process Steps: CPT + FAQ-like selection (Approach A + C)

### Current problem

Process steps are currently being generated statically, not driven by `lf_process_step` CPT and not curated like FAQs.

### Target behavior

Process sections should function like FAQ sections:

- Per-section selection UI (picker + reorder + remove)
- Persist selected IDs to section settings:
  - `process_selected_ids` (line list of CPT IDs)
- Renderer uses selected IDs if present.
- If empty, renderer auto-picks by service type taxonomy:
  - Service pages match the service slug/type term
  - Homepage uses a `homepage-primary` group term

### Manifester integration (Approach C)

During generation/apply:

- Ensure relevant `lf_process_step` CPT entries exist for each service type.
- For each Process section instance:
  - Populate `process_selected_ids` with the correct CPT IDs for that service/service area (or homepage group).
- Editors can override selections via the frontend editor (same persistence path).

### Parity with FAQs

Process Steps should mirror FAQ behavior:

- Library endpoint (search + list) returning CPT items
- Frontend editor controls to add/remove/reorder
- Saved in section settings
- Renderer uses selection, falls back to auto-pick logic

---

## Rollout / sequencing

1) **Internal Link Map** backend view (first deliverable)
2) Template audit + golden stacks (Service Area first)
3) Process Steps CPT + selection + manifester integration
4) Manifester uniqueness/completeness gate (using the new process-step selection and existing FAQ selection)

---

## Success criteria

- Internal Link Map provides accurate outbound/inbound link visibility and highlights orphans/broken targets.
- Service Area and other templates ship with strong defaults and pass audits.
- Manifester writes unique page-specific copy where expected, while allowing approved repeat categories.
- Process steps are reliably CPT-driven and curated like FAQs.

