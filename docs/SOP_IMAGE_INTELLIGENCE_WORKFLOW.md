# SOP: Image Intelligence + Website Manifester Workflow

## Purpose

This SOP documents how to run the deterministic image distribution workflow in the LeadsForward Core Theme so uploaded Media Library images are automatically assigned to homepage and generated pages.

The process is fully server-side (PHP), deterministic, and does not require changing templates or n8n payload schema.

## Scope

This SOP covers:

- Uploading images in Website Manifester
- Naming files for deterministic matching
- Running Manifest generation
- Automatic assignment behavior
- Featured image + ALT text behavior
- Debug/verification steps
- Troubleshooting

## Preconditions

Before running this workflow, confirm:

1. Theme includes:
   - `inc/image-intelligence.php`
   - Manifester image upload step in `inc/ai-studio.php`
2. User has capability to access theme admin pages (`edit_theme_options`).
3. Website Manifester has required generation inputs (Airtable or Manifest JSON).
4. Media upload limits are sufficient for intended image set.

## Deterministic System Overview

The image pipeline uses these core functions:

- `lf_build_media_index()`:
  - Queries image attachments
  - Extracts attachment ID, filename, title, alt text, caption, parent post
  - Normalizes filename tokens
  - Caches index in transient `lf_media_index_cache`
- `lf_match_images_for_context(array $context)`:
  - Scores images for a page context using deterministic priority
  - Returns slot map:
    - `hero`
    - `content_image_a`
    - `image_content_b`
    - `content_image_c`
    - `featured`
- `lf_prime_image_distribution_for_site()`:
  - Pre-indexes media
  - Pre-assigns featured images where missing
- `lf_image_intelligence_maybe_set_alt_text()`:
  - Generates ALT only when missing
  - Format: `{Service Name} in {City} by {Business Name}`

No randomness is used. Tie-breaks are stable based on filename sorting and `variation_seed`.

## Matching Priority (Highest to Lowest)

For each image candidate, matching is evaluated in this order:

1. Exact service slug match in filename tokens
2. Exact city token match
3. Exact niche token match
4. Primary keyword token match
5. Secondary keyword token match
6. Generic fallback (`general` token)

When scores tie, deterministic sorting and seed hashing choose a stable winner.

## File Naming Convention (Required for Best Results)

Use meaningful, lowercase, dash-separated filenames with service and location intent.

Recommended examples:

- `roof-repair-kansas-city-1.jpg`
- `kitchen-remodel-sarasota-modern.jpg`
- `bathroom-remodel-before-after.jpg`
- `general-contractor-team.jpg`

Guidelines:

- Include core service phrase
- Include city where relevant
- Include niche terms where possible
- Use `general` for broad fallback assets
- Avoid generic names like `IMG_1234.jpg`

## Step-by-Step Procedure

### Step 1: Open Website Manifester

In WP Admin, open the Website Manifester screen.

### Step 2: Configure generation source

Choose one source:

- Airtable project selection, or
- Manifest JSON upload

Set generation scope as needed.

### Step 3: Upload required images

Use Manifester step:

- **Upload required images for auto-distribution**

Action:

1. Click file chooser
2. Select one or more site images
3. Click **Upload Images to Media Library**

Expected result:

- Success notice showing upload count
- Image index cache is invalidated and rebuilt

### Step 4: Optional branding logo

Upload/select logo if needed for brand palette. This is independent from image matching.

### Step 5: Run generation

Click **Manifest Your Website**.

During and after content updates:

- Empty image fields in supported sections are auto-filled
- Existing/manual image values are preserved (not overwritten)
- Featured image is set if missing
- ALT text is generated only when missing

## Auto-Assignment Rules

### Section image injection

In `lf_apply_orchestrator_updates()` image IDs are injected only when all are true:

1. Field exists in section schema/registry
2. Current field value is empty
3. Incoming update does not already set an image

### Supported section targets

Image fields are supported through section type/field introspection, including:

- `hero`
- `content_image_a`
- `image_content_b`
- `content_image_c`
- `service_details` (when image field exists)
- `map_nap` (when image field exists)

Notes:

- `testimonials` remains future-ready and will only assign if image fields exist in schema.
- No schema or rendering changes are required for matching to work.

### Featured image

If post has no featured image:

- Use `matched['featured']`
- Set with `set_post_thumbnail()`

### ALT text

If `_wp_attachment_image_alt` is empty:

- Generate deterministic text:
  - `{Service Name} in {City} by {Business Name}`
- Existing ALT is never overwritten

## Verification Checklist

After generation run:

1. Homepage and generated service/service-area/overview pages show non-placeholder images where eligible
2. Existing manually assigned images remain untouched
3. Featured images exist on pages/posts that previously lacked thumbnails
4. Attachment ALT values are populated only where previously blank
5. No template render changes are needed
6. Structured generation payloads remain unchanged

## Debug and Diagnostics

Optional debug page:

- **Tools > Image Intelligence Debug**

Use it to inspect:

- Cached media index
- Example context
- Example match output

## Cache Behavior

Transient cache key:

- `lf_media_index_cache`

Invalidation events:

- new attachment upload
- attachment edit
- attachment delete

## n8n Workflow Requirement

No n8n changes are required for this SOP.

Reason:

- Matching and assignment are performed in theme PHP after scaffold/apply
- n8n does not need new fields for image selection

Optional improvement:

- Keep manifest keywords, niche, and city data accurate to improve matcher quality.

## Troubleshooting

### Images not assigning

Check:

1. Filenames are descriptive (service/city/niche tokens)
2. Target section image field is actually empty
3. Target section has image field in registry schema
4. Media index contains uploaded items (debug page)

### Wrong images selected

Check:

1. Filenames are too generic
2. Missing city/service tokens
3. Primary/secondary keywords are weak or mismatched

Fix:

- Rename assets using strategy above and re-upload
- Re-run manifest/apply

### ALT text not generated

Expected when:

- ALT already exists (system does not overwrite existing ALT)

### Featured image missing

Check:

1. Match output has non-zero `featured`
2. Post does not already have thumbnail set

## Change Control / Safety Constraints

This SOP preserves:

- no template modifications
- no section registry structure changes
- no rendering architecture changes
- deterministic behavior
- server-side logic
- no impact to n8n structured output schema

## Related Docs

- `docs/AI_CONTEXT.md` (Image Naming Strategy)
- `docs/02_N8N_WORKFLOW_ARCHITECTURE.md`
- `docs/05_THEME_INTEGRATION.md`
