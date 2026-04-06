# Section Schema

Sections are the atomic content units that the LLM is allowed to write. The canonical section registry lives in `inc/sections.php` and the renderers live in `templates/blocks/`.

## Section Object Shape (Blueprint)
Each blueprint contains `sections[]`, where each section includes:
- `section_id` (string) — unique instance key, e.g. `hero` or `hero-1`
- `section_type` (string) — canonical type key
- `allowed_field_keys` (array) — the only writable fields for this section

## Update Shape (LLM Output)
The LLM outputs updates using dot-notation fields:
```json
{
  "target": "post_meta",
  "id": 123,
  "fields": {
    "hero.hero_headline": "Clear, local headline",
    "hero.hero_subheadline": "Supporting copy"
  }
}
```

## Field Rules
- Only keys listed in `allowed_field_keys` are valid.
- All `allowed_field_keys` must be filled (no empty values).
- List fields are newline-delimited strings (not arrays).
- Richtext fields may include HTML (`<p>`, `<ul>`, `<li>`, `<a>`).
- All other fields are plain text.

## Where the Schema Comes From
The schema is derived from:
- Section registry: `inc/sections.php`
- Page builder config: `inc/page-builder.php`
- Homepage config: `inc/homepage.php`

If a field or section changes, update the registry and keep this doc aligned.

## Notable Section Keys
- **Process** (`process`):
  - `process_selected_ids` (newline list of `lf_process_step` post IDs) drives the section when at least one ID resolves.
  - If `process_selected_ids` is empty, the renderer may auto-pick steps by taxonomy (`lf_process_group`) based on page context:
    - Service pages: matches the service slug (e.g. `commercial-roofing`)
    - Homepage: `homepage-primary`
  - Fallback: `process_steps` (line-based) when no IDs resolve and no auto-pick matches.
- **Hero**: `hero_proof_bullets` powers the Authority Split right-hand checklist; `hero_chip_bullets` is separate (left pills).
- `service_areas` now supports map/filter UX copy keys for overview pages:
  - `map_heading`
  - `map_intro`
  - `search_placeholder`
  - `filter_label`
  - `filter_all_label`
  - `no_results_text`
