# n8n Vision Step Spec (Image Intelligence)

## Goal

Add a vision stage in n8n so uploaded images are analyzed by content, then returned to WordPress for deterministic rename, ALT enrichment, and placement-aware distribution.

## Theme Support Added

The theme now sends this to orchestrator requests:

- `media_library_candidates` (array)

Each candidate item:

```json
{
  "attachment_id": 123,
  "url": "https://example.com/wp-content/uploads/...",
  "filename": "raw-upload-name.jpg",
  "title": "Raw Upload Name",
  "alt": "",
  "caption": ""
}
```

The theme now accepts this from n8n callback payload:

- `media_annotations` (array)

## Required Callback Shape

Return your normal payload plus:

```json
{
  "ok": true,
  "request_id": "same-id",
  "updates": [],
  "media_annotations": [
    {
      "attachment_id": 123,
      "description": "Technician pressure washing a driveway",
      "alt_text": "Driveway pressure washing in Sarasota by Acme Home Services",
      "recommended_filename": "driveway-pressure-washing-sarasota",
      "keywords": ["driveway pressure washing", "sarasota", "concrete cleaning"],
      "service_slugs": ["driveway-cleaning"],
      "area_slugs": ["sarasota-fl"],
      "page_types": ["service", "service_area", "homepage"],
      "slots": ["hero", "content_image_a", "featured"]
    }
  ]
}
```

## Field Meanings

- `attachment_id`: Required. WordPress Media Library attachment ID.
- `description`: Short visual description.
- `alt_text`: Recommended ALT using keyword + true image description + location/business context.
- `recommended_filename`: SEO-safe base name (no extension required). Theme sanitizes.
- `keywords`: Vision-derived semantic tags for matching.
- `service_slugs`: Optional exact service targets.
- `area_slugs`: Optional exact service area targets.
- `page_types`: Optional page context guidance (`homepage`, `service`, `service_area`, `overview`, `post`).
- `slots`: Optional preferred slots (`hero`, `content_image_a`, `image_content_b`, `content_image_c`, `featured`).

## n8n Node Flow (Recommended)

1. Receive webhook payload.
2. Read `media_library_candidates`.
3. For each candidate:
   - Run vision model on `url`.
   - Produce annotation record using schema above.
4. Merge annotations into final callback payload as `media_annotations`.
5. Keep normal `updates` output unchanged.

## Hybrid AI Image Generation Mode

The theme now sends `image_generation` instructions in payload:

```json
{
  "image_generation": {
    "mode": "hybrid",
    "generate_only_missing": true,
    "limit": 12,
    "targets": [
      {
        "target": "post_meta",
        "post_id": 456,
        "section_instance": "hero-1",
        "section_type": "hero",
        "field_key": "hero_image_id",
        "slot": "hero",
        "context": { "page_type": "service", "service_slug": "roof-repair", "city": "Kansas City" }
      }
    ],
    "preferred_model": "flux-schnell",
    "hq_hero_model": "flux-dev"
  }
}
```

Use this plan to:

1. Generate images only for listed `targets`.
2. Enforce `limit`.
3. Prefer cheap/fast model for most slots, optional HQ model for hero slots.
4. Return generated asset annotations in `media_annotations`.

## Deterministic Safety

- Theme remains deterministic even with vision:
  - annotation targets become weighted signals
  - stable seeded tie-break still applies
- If `media_annotations` is missing/empty:
  - system falls back to filename + keyword deterministic matcher

## Rename Behavior

- Theme can rename the **physical attached file** using `recommended_filename`.
- Rename is sanitized and deduplicated with WordPress unique filename logic.
- ALT is only written when currently empty (no overwrite of existing ALT).
