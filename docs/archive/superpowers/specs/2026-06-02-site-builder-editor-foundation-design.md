# Site Builder + Editor Foundation (design)

**Status:** Approved (A then B)  
**Date:** 2026-06-02  
**Audience:** Engineering + PM (Shannon, manifesters, content writers)

## Goal

Ship a **writer-first site build path** that does not depend on n8n, plus an editor experience that beats generic builders (e.g. Oxygen) for LeadsForward’s **typed section model**. Full-site AI orchestration becomes an optional add-on later.

## Product split

| Layer | Responsibility |
|-------|----------------|
| **Core theme** | Section library, page builder, frontend editor, Airtable → templated site build, niche media packs, writer guidance placeholders, per-section AI (OpenAI via `lf_ai_completion`) |
| **Future add-on plugin** (`leadsforward-ai-orchestrator`) | n8n webhook, job CPT, research doc, full-site manifester generation |

This design covers **theme only** (Phases A + B).

## Phase A — Site Builder (no n8n)

### Operator flow

```
Manifest Website → select Airtable project → Build site (templates)
  → manifest stored (lf_site_manifest)
  → CPT/pages/menus scaffolded
  → niche section templates applied (homepage + page builder)
  → niche media pack imported + image slots filled
  → writer guidance copy seeded in every section field
  → writer opens frontend editor, fills section by section
```

### Does not

- POST to n8n webhook
- Create AI job CPT for orchestrator
- Run `lf_ai_studio_run_generation()`

### Does

- Reuse existing Airtable → manifest → `lf_ai_studio_sync_manifest_posts()` pipeline
- Call `lf_ai_studio_scaffold_manifest()` when needed
- Call `lf_ai_studio_ensure_core_page_sections($manifest, true)` to apply niche templates
- Import `assets/niche-packs/{niche}/pack.json` images into media library
- Assign attachment IDs to section image fields
- Seed **writer guidance** (visible placeholder copy + SEO hints), not finished marketing copy

### Writer guidance rules

- Visible on front end for logged-out users until replaced (styled as `.lf-writer-guidance`)
- Format: what the section is for, SEO placement rules, word-count hints from `length_targets`
- Never impersonate finished client copy; never use fake reviews or guarantees

### Niche media packs

Path: `assets/niche-packs/{niche_slug}/pack.json`

```json
{
  "niche": "foundation-repair",
  "images": {
    "hero": { "file": "placeholder.png", "alt": "Foundation repair work in {city}" }
  },
  "assignments": [
    { "target": "homepage", "section_type": "hero", "field": "hero_background_image_id", "image_key": "hero" }
  ]
}
```

MVP uses bundled `placeholder.png` (copy of theme placeholder) with niche-specific alt templates. Real photography added per niche over time.

## Phase B — Editor superiority

### B1 — Section inspector (frontend)

Per selected section panel:

- Section label + intent
- Writer guidance summary (from settings or derived)
- SEO checklist (keyword in H1/first paragraph, internal links, word targets)
- **Generate this section** (existing `lf_ai_generate` scoped to section; improve discoverability)

### B2 — Preview fidelity

- Frontend editor preview must match public render (address known gaps from hardening spec §15)

### B3 — Performance

- Reduce jank on section select; debounce saves

Phase B ships after Phase A is usable end-to-end.

## Manifester UI changes

- Primary CTA: **Build site (templates)** — no webhook required
- Secondary CTA: **Generate with AI (orchestrator)** — existing flow; label clarifies n8n dependency
- Steps copy updated: Airtable + Global Settings do not require n8n for template build

## Data options

| Option | Purpose |
|--------|---------|
| `lf_site_manifest` | Canonical manifest (unchanged) |
| `lf_site_builder_last_run` | Timestamp + summary of last template build |
| `lf_niche_pack_attachment_map` | image_key → attachment_id |

## Success criteria (Phase A)

1. Operator can build Foundation Repair site from Airtable without n8n configured
2. All scoped pages have niche section order + guidance copy
3. Hero and at least one content section have images from niche pack
4. Content writer can open homepage + service page and see clear guidance in each section

## Out of scope (this spec)

- Extracting orchestrator to separate plugin (follow-on)
- Full niche photo libraries (incremental)
- Airtable webhook auto-sync
