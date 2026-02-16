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

## No-AI / n8n-Down Fallback
The theme now includes a deterministic local fallback pass so the site can still launch with strong baseline content even without n8n:

- `lf_ai_studio_scaffold_manifest()` now also:
  - ensures AI blog shells are created/scheduled (`3 publish now + 2 future weekly`),
  - runs `lf_ai_studio_fill_site_content_without_ai()` to replace generic/empty section copy,
  - runs image distribution and SEO refresh passes.
- Placeholder/stock placeholder media assets are excluded from deterministic image matching and can be replaced automatically during assignment.
- `lf_apply_orchestrator_updates()` continues to overwrite weak generic copy with deterministic fallback if AI output is thin.

This keeps the theme operational as a standalone engine while still benefiting from n8n when available.

## Core Integration Points
- **Manifest scaffold**: `lf_ai_studio_scaffold_manifest()` creates/updates services and service areas, then seeds sample projects.
- **Payload builder**: `lf_ai_studio_build_full_site_payload()` assembles blueprints using:
  - `lf_ai_studio_build_homepage_blueprint()`
  - `lf_ai_studio_build_post_blueprint()`
- **Orchestrator callback**: `/wp-json/leadsforward/v1/orchestrator` handled by `lf_ai_studio_rest_orchestrator()`.
- **Apply (strict)**: `lf_apply_orchestrator_updates()` applies updates and logs a job outcome.
- **Callback auth/binding**:
  - compatibility bearer auth is currently used by n8n in this environment.
  - WP callback/progress handlers enforce request/job binding (`job_id`, `request_id`) and idempotent payload hashing.
  - HMAC verification remains supported on the WP side.
- **Autonomy launch gate**: optional autonomous mode remains disabled until a successful Manifester run completes and records a baseline health state.
- **Autonomy eligibility gate**: autonomous Airtable runs remain optional/off by default and only become enable-able after a successful manifester callback stores a fresh baseline audit/hash.
- **Repair safeguards**:
  - max one repair pass per root run.
  - repair-of-repair loops are blocked.
  - request-level dedupe lock prevents concurrent duplicate repair jobs.
  - phase tagging (`run_phase`, `repair_attempt`) is included in request payloads.

## Deterministic CTA + FAQ
- Homepage is the only page allowed to generate global CTA fields.
- FAQ content is generated on homepage and deterministically reused for service and service-area pages.
- n8n enforces uniqueness and FAQ slicing before WordPress applies updates.

## SEO Enforcement
Two layers are enforced:
1. **n8n quality/completeness gates** inject/fix keyword coverage and reject low-volume/generic output.
2. **WP SEO engine + metadata refresh** assigns keywords and rewrites weak title/description/canonical/OG fields during scaffold and apply.

Keywords are stored per page in `_lf_seo_primary_keyword` and the deterministic map in `lf_keyword_map`.
