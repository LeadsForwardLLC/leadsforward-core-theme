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
  - query-token auth is disabled by default in production.
- **Autonomy launch gate**: optional autonomous mode remains disabled until a successful Manifester run completes and records a baseline health state.
- **Autonomy eligibility gate**: autonomous Airtable runs remain optional/off by default and only become enable-able after a successful manifester callback stores a fresh baseline audit/hash.
- **Repair safeguards**:
  - max one repair pass per root run.
  - repair-of-repair loops are blocked.
  - request-level dedupe lock prevents concurrent duplicate repair jobs.
  - phase tagging (`run_phase`, `repair_attempt`) is included in request payloads.

## Orchestrator identity guard
Before apply runs, the orchestrator compares **expected** identity (from manifest / site context) to **incoming** identity from the callback payload on `business_name`, `city_region`, and `niche` (label/slug normalization). On mismatch, WordPress **does not apply** updates; the job is marked **failed** while the REST handler still returns **HTTP 200** so n8n does not treat the response as a transport error.

**n8n should branch on the JSON body**, not on HTTP status: read `success` and `acknowledged`. A blocked callback responds with `success: false`, `acknowledged: true`, `job_id` set, and `error: ["business_identity_mismatch"]`.

Server logs for the check use keys `business_expected`, `business_incoming`, and `business_match`. Stored `lf_ai_job_response` may still be written for observability even when apply is blocked—treat **job status** and the job **summary** as the source of truth for whether content was applied.

**Tests:** from the theme root, run `php tests/identity-guard.php`. When WP CLI is available, prefer `wp eval-file tests/identity-guard.php` so WordPress helpers (e.g. `sanitize_title`) match production.

## Deterministic CTA + FAQ
- Homepage is the only page allowed to generate global CTA fields.
- FAQ content is generated on homepage and deterministically reused for service and service-area pages.
- n8n enforces uniqueness and FAQ slicing before WordPress applies updates.

## Lead Endpoint Hardening
- Public lead endpoints (Quote Builder + Contact Form) now include:
  - lightweight per-IP throttling
  - honeypot bot filtering with silent-success behavior
- Quote Builder webhook delivery now includes retry queue processing for transient remote failures.

## Fleet theme updates (private channel)

Fleet sites can connect to `theme.leadsforward.com` to receive **controller-approved automatic theme updates** without logging into each site.

## Sitemap Sync (Airtable Sitemaps → WordPress)

The theme can also sync an Airtable-driven sitemap into WordPress Pages, generate the Header Menu from that sitemap, and enforce internal-link allowlists so AI/content never links to unpublished pages.

See: `docs/09_SITEMAP_SYNC.md`.

### Connect a site

In wp-admin:
- LeadsForward → Fleet Updates
- Paste:
  - **Controller API base** (example: `https://theme.leadsforward.com`)
  - **Site ID** (UUID from the controller)
  - **Token** (revocable per-site secret from the controller)
  - **Controller public keys JSON** (map of `key_id` → base64 public key)

### How it works
- On a **~15 minute** WordPress cron schedule (default; tunable), the site calls the controller **when WP-Cron runs** (typically when the site gets traffic, or when system cron hits `wp-cron.php`). Low-traffic fleet sites should use real server cron or rely on **Check now** in Fleet Updates after a release.
- **Interval filter:** `lf_fleet_updates_cron_interval` (seconds) adjusts how often the recurring check runs; the theme clamps values between **5 and 60 minutes** so hosts can speed up rollouts without starving the server.
- **After connect or bundle import:** the theme schedules a **one-off** fleet run about 20 seconds ahead (guarded by a short-lived transient) and calls `spawn_cron()` when available so new connections do not wait a full interval.
- **Fleet Updates screen:** an authorized visit to **LeadsForward → Fleet Updates** nudges `spawn_cron()` while connected, helping quiet sites pick up updates soon after you open wp-admin.
- **Disconnect:** clears scheduled fleet cron events and the near-term ping transient so orphaned jobs do not keep firing.
- Each run:
  - **Heartbeat**: reports current version + environment
  - **Update check**: controller returns an update only if the site is eligible + the version is approved
- If approved:
  - The site verifies **Ed25519 signature** + **SHA-256 checksum**
  - Then installs via WordPress upgrader APIs
- **Check now** (wp-admin): contacts the controller immediately and, for authorized users, attempts install without waiting for cron.
- **Controller push** (optional): the controller WordPress can `POST` each connected site’s REST route so the site checks the controller and installs without waiting for cron (see below).
- **Manual update** from Appearance → Themes uses the same verified zip path; controller download URLs are **one-time tokens**—the theme caches the verified zip within a single upgrade request so WordPress does not request the URL twice.

### Controller-initiated push (`POST /wp-json/lf/v1/fleet/push`)

When a fleet site is connected, the controller (same codebase, controller mode) can trigger an immediate **check → offer → install** cycle by posting to that site’s public REST URL. Only requests that pass **HMAC verification** run the flow; there is no unauthenticated “install now.”

Note: the controller’s push request is **blocking** and may take time while the fleet site downloads/unzips/installs a theme update. The controller uses a longer timeout so successful installs can return a definitive result instead of timing out mid-install.

**Headers** (same signing scheme as outbound fleet API calls):

| Header | Purpose |
|--------|---------|
| `X-LF-Site` | Must match the site’s stored fleet **site ID**. |
| `X-LF-Timestamp` | Unix time; must be within **±5 minutes** of the server clock. |
| `X-LF-Nonce` | Unique per request; stored briefly to block **replay** (about 10 minutes). |
| `X-LF-Signature` | Base64 **HMAC-SHA256** over `METHOD\nPATH\nTIMESTAMP\nNONCE\nSHA256(body)` using the per-site token. Path must be exactly `/wp-json/lf/v1/fleet/push` (method `POST`). |

**Body** (JSON): `action` = `check_install`, optional `override` (boolean), `request_id` (echoed as nonce material; use a UUID). When `override` is true, the site asks the controller for an update the same way as **Check now** with rollout override—use only when the controller operator intends to bypass normal rollout gating.

**Responses** (JSON body always includes `ok`, `message`, `updated_to`, `error_code`):

- **401** + `ok: false`: not connected, missing/bad headers, wrong site, expired timestamp, replay, or bad signature (`error_code` mirrors the failure reason).
- **200** + `ok: false`, `no_update`: controller had nothing to offer (still a normal HTTP 200 so transports do not treat it as a hard failure).
- **200** + `ok: true`, `message: updated`, `updated_to`: theme version after a successful install.
- **200** + `ok: false`, `install_failed`: an offer existed but the upgrader did not reach the target version; check `message` / site **Fleet Updates** last result.

Cron and **Check now** remain the primary paths for sites that never receive a push; push does not replace heartbeat or recurring pull unless you always push from the controller UI.

### Security notes
- Outbound fleet API requests and inbound push requests are **HMAC-signed** with the per-site token; timestamp + nonce limit replay.
- Theme zips are verified before install:
  - If signature or checksum fails, the update is refused.
- The only inbound fleet trigger is **`POST /wp-json/lf/v1/fleet/push`**, and it requires a valid signature for that site’s token.

## Site header layout (global)

Header structure is driven by global options read in `templates/parts/header.php` via `inc/header-settings.php`:

- **`lf_header_layout`**: `modern` (logo left, nav right), `centered` (centered logo and menu), or `topbar` (promo strip above the main bar). Invalid values fall back to `modern`.
- **`lf_header_topbar_enabled`** / **`lf_header_topbar_text`**: optional thin promo line when the layout supports it.

**Operators:** while logged in on the public site, open the front-end editor’s **Header** floater (next to History / SEO). Pick the layout, optionally enable the promo top bar and enter its text, then **Save header**. Settings persist in Global Settings options and the page reloads so the new markup applies site-wide. Header CTA label/URL remain in **LeadsForward → Global Settings** as before.

## Front-end editor saves (hero + service checklists)

- **Hero Authority Split — proof checklist:** Inline edits to the right-hand proof bullets use field `hero_proof_bullets` on the **homepage** section payload. Saves from the static front page always target the homepage record even if the assistant context was another URL, so checklist lines persist after refresh.
- **Service details — checklist columns:** The service story section exposes two newline lists (`service_details_checklist` and optional `service_details_checklist_secondary`). Front-end inline saves map each column to the correct field (including the secondary column when present). Values are capped server-side for consistent layout; see `lf_sections_cap_checklist_lines_string()` in `inc/sections.php` and persistence in `inc/ai-editing/admin-ui.php`.

## Rich text — icon insertion

Rich Text toolbars offer **Insert icon**, which inserts `[lf_icon name="slug"]` at the **text caret**. If nothing is focused, the UI may show an error—click inside the paragraph first. On render, shortcodes expand to inline Tabler SVG via `lf_icon()` (including after KSES), so previews and the public site match.

## SEO Enforcement
Two layers are enforced:
1. **n8n quality/completeness gates** inject/fix keyword coverage and reject low-volume/generic output.
2. **WP SEO engine + metadata refresh** assigns keywords and rewrites weak title/description/canonical/OG fields during scaffold and apply.

Keywords are stored per page in `_lf_seo_primary_keyword` and the deterministic map in `lf_keyword_map`.
