## Manifest process audit (Airtable → Manifest → CPT sync → Payload → n8n)

### Data flow (source of truth)

- **Airtable record selection (admin UI)**:
  - Client JS (`assets/js/ai-studio-airtable.js`) searches + selects a record.
  - On select, it calls AJAX `lf_ai_airtable_preview_manifest` to preview services + service areas and populate optional filters.
  - Generation uses AJAX `lf_ai_airtable_generate`.

- **Airtable record → manifest**:
  - Server builds a manifest via `lf_ai_studio_airtable_record_to_manifest()` in `inc/ai-studio-airtable.php`.
  - Manifest is normalized via `lf_ai_studio_normalize_manifest()` in `inc/ai-studio.php`.
  - Stored as the site’s canonical manifest option: **`lf_site_manifest`**.

- **Manifest → CPT sync**:
  - `lf_ai_studio_sync_manifest_posts()` ensures CPTs exist:
    - Services as **`lf_service`**
    - Service areas as **`lf_service_area`**
  - Scheduling/publishing cadence must be enforced here for CPT creation.

- **Manifest/CPTs → orchestrator payload**:
  - `lf_ai_studio_build_full_site_payload()` creates a list of “blueprints” to send to n8n.
  - The payload contains `generation_scope` and `blueprints` (each blueprint includes `page`, `post_id`, `page_slug`, etc).

- **n8n orchestration and callbacks**:
  - WordPress sends payload to n8n (webhook + secret).
  - n8n generates content and calls back into WP to apply updates to post sections/fields.

### Invariants (must always hold)

- **Post types**:
  - Service detail pages must be **CPT `lf_service`**, never WP `page`.
  - Service area detail pages must be **CPT `lf_service_area`**, never WP `page`.

- **Placeholder services never persist**:
  - Placeholder “Main/Additional*” services may exist in Airtable (or legacy caches) but must not be persisted into `lf_site_manifest` or used to build CPTs/payloads.

- **Preview reflects selected record**:
  - The Airtable preview response `meta.record_id` must match the selected record.
  - The UI must refresh pickers on every selection (no “stuck” pickers).

- **Scope persistence is unambiguous**:
  - Smoke-test filters are **slug-only**.
  - Legacy “ID mode” options must be cleared on scope save to avoid mixing modes.

### Data sources that can populate services / areas (and priority)

- **Primary**: Airtable-derived manifest (`manifest['services']`, `manifest['service_areas']`)
- **Fallbacks**:
  - Niche/generic deterministic services (used when Airtable services are placeholder-only)
  - Sitemap cache (`lf_airtable_sitemap_cache`) as a *last resort* list source for pickers and/or service fallback

### Preflight checks (must-pass)

- **On Airtable generation**:
  - If Airtable services are placeholder-only and “Service pages” is enabled, replace them with deterministic fallbacks before saving `lf_site_manifest`.

- **On payload build (before sending to n8n)**:
  - If “Service pages” scope is enabled, payload must include at least 1 blueprint with `page === 'service'` and underlying post type `lf_service`.
  - If “Service area pages” scope is enabled, payload must include at least 1 blueprint with `page === 'service_area'` and underlying post type `lf_service_area`.
  - If any service/service-area blueprint points at a non-CPT post type, fail loudly.

### Operational notes

- **Sitemap Sync safety**:
  - Reconcile must not upsert WP `page` posts for `/services/*` or `/service-areas/*`. Those URLs are owned by CPTs.

- **UI expectations**:
  - Manifest Website is Airtable-only: no manual setup button, no deterministic manifest upload UI.
  - Status lines show theme version + preview results to avoid “unknown deployed version” ambiguity.

