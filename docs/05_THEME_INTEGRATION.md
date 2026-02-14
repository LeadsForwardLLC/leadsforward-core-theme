# WordPress Theme Integration

This theme is the authoritative source for manifests, blueprints, and final content application.

## Key Flow (WP Side)
```
Manifest/Airtable -> lf_ai_studio_scaffold_manifest()
-> lf_ai_studio_build_full_site_payload()
-> n8n webhook
-> /wp-json/leadsforward/v1/orchestrator (callback)
-> lf_apply_orchestrator_updates()
```

## Core Integration Points
- **Manifest scaffold**: `lf_ai_studio_scaffold_manifest()` creates/updates services and service areas, then seeds sample projects.
- **Payload builder**: `lf_ai_studio_build_full_site_payload()` assembles blueprints using:
  - `lf_ai_studio_build_homepage_blueprint()`
  - `lf_ai_studio_build_post_blueprint()`
- **Orchestrator callback**: `/wp-json/leadsforward/v1/orchestrator` handled by `lf_ai_studio_rest_orchestrator()`.
- **Apply (strict)**: `lf_apply_orchestrator_updates()` applies updates and logs a job outcome.

## Deterministic CTA + FAQ
- Homepage is the only page allowed to generate global CTA fields.
- FAQ content is generated on homepage and deterministically reused for service and service-area pages.
- n8n enforces uniqueness and FAQ slicing before WordPress applies updates.

## SEO Enforcement
Two layers are enforced:
1. **n8n Quality Gate** injects missing primary/secondary keywords and enforces minimum word counts.
2. **WP SEO engine** assigns keywords during manifest scaffold when `lf_seo_settings.ai_keyword_engine` is enabled.

Keywords are stored per page in `_lf_seo_primary_keyword` and the deterministic map in `lf_keyword_map`.
