---
title: "Private Fleet Theme Updates (Pull-based) — design"
date: 2026-04-13
status: draft
---

## Goal

Operate a fleet of separate WordPress installs where **theme code updates** can be rolled out from a controller (**`theme.leadsforward.com`**) with **staged approvals and targeting**, without logging into individual sites.

Key requirements:
- **Controller-driven approvals**: controller decides which sites are eligible for which version.
- **Auto-install** on fleet sites once eligible/approved (no wp-admin login on those sites).
- **Targeting controls**:
  - All sites (broad)
  - By ring (alpha/beta/stable)
  - By tags/filters (niche, tier, environment, etc.)
  - Explicit site list
- **Security first**: no privileged inbound “install now” endpoint on fleet sites.
- **Scale**: designed for 500+ sites, eventually thousands.

Non-goals:
- Plugin/core WP updates.
- Content synchronization between sites.

---

## Recommended architecture (most secure): pull-only private update channel

### Why pull-based (not push-based)

The controller cannot reliably and safely “reach into” separate WordPress installs to install code on demand:
- Sites are often behind WAFs/firewalls/CDNs; inbound privileged endpoints are a high-risk target.
- A pull-only approach avoids exposing a remote code install surface area on every site.

Instead, the controller “pushes” by **approving eligibility**, and sites **pull** the update on a schedule and auto-install.

---

## Components

### 1) Controller: `theme.leadsforward.com`

Responsibilities:
- Maintain a **release registry** for the theme (versions, artifacts).
- Maintain a **fleet registry** (connected sites + metadata).
- Define **rollout rules** (who gets what, when).
- Serve a secure **update check** endpoint and **time-limited downloads**.
- Provide auditability: who approved what, and rollout status.

### 2) Fleet Site Agent (in theme, or small companion plugin)

Responsibilities:
- Store a **site identity** and **revocable token** for controller auth.
- Periodically **check** controller for approved updates.
- Verify update integrity (checksum + signature).
- Install updates using WordPress update/upgrader APIs.
- Report status (heartbeat + install result).

Note: this spec assumes **theme-embedded** agent is acceptable. A companion plugin remains an option if we need updates to work even when switching themes.

---

## Data model (controller)

Minimum tables/collections:

### `releases`
- `theme_slug` (string, e.g. `leadsforward-core-theme`)
- `version` (string, semver)
- `zip_storage_key` (string) or `zip_url` (string)
- `sha256` (hex string)
- `signature_ed25519` (string; signature over canonical payload)
- `public_key_id` (string; which signing key)
- `changelog` (text/markdown)
- `created_at`, `created_by`

### `sites`
- `site_id` (UUID)
- `site_url` (string; mask in UI)
- `site_label` (string, optional)
- `token_id` (string)
- `ring` (`alpha|beta|stable`)
- `tags` (json; niche, tier, environment, etc.)
- `current_version` (string)
- `last_seen_at` (timestamp)
- `last_result` (json; optional)
- `created_at`, `revoked_at` (optional)

### `rollout_rules`
- `rule_id` (UUID)
- `theme_slug`
- `target_version`
- selector:
  - `mode`: `all | query | list`
  - `query`: json (ring/tags filters) when mode=query
  - `site_ids`: list when mode=list
- `enabled` (bool)
- `created_at`, `created_by`

---

## API (controller)

All endpoints are **TLS only**.

### Registration

#### `POST /api/v1/sites/register`
Creates/returns a site identity and token.

Input:
- `site_url`
- `wp_version` (optional)
- `php_version` (optional)
- `theme_slug`
- `theme_version`
- `tags` (optional)

Output:
- `site_id`
- `client_token` (revocable bearer secret; stored on fleet site)
- `api_base`
- `controller_public_keys` (current key + key rotation info)

### Heartbeat

#### `POST /api/v1/sites/heartbeat`
Input:
- `site_id`
- `theme_slug`
- `current_version`
- `health` (optional: wp/php, last_error, etc.)

Output:
- `ok: true`

### Update eligibility check

#### `GET /api/v1/updates/check?site_id=...&theme_slug=...&current=...`
Output when no update:
- `{ "update": false }`

Output when eligible/approved:
- `{ "update": true, "version": "x.y.z", "download_url": "...", "sha256": "...", "signature": "...", "public_key_id": "..." }`

The `download_url` must be **short-lived** (signed URL) and bound to `site_id` when possible.

---

## Auth + replay protection (controller API)

### Per-site token
Each site receives a `client_token` that can be revoked at any time.

### Request signing (HMAC)
Each request includes:
- `X-LF-Site: <site_id>`
- `X-LF-Timestamp: <unix seconds>`
- `X-LF-Nonce: <uuid>`
- `X-LF-Signature: <base64>` where signature is:

\[
\text{HMAC-SHA256}(\text{client\_token}, \text{method} + "\n" + \text{path} + "\n" + \text{timestamp} + "\n" + \text{nonce} + "\n" + \text{body\_sha256})
\]

Controller validates:
- Timestamp within TTL (e.g. 5 minutes)
- Nonce not reused for that site within TTL window
- Signature matches

This prevents tampering/replay even if TLS terminates upstream.

---

## Signed artifacts (supply chain protection)

Each release zip has:
- `sha256` checksum
- **Ed25519 signature** over a canonical payload, e.g.:
  - `theme_slug`
  - `version`
  - `sha256`
  - `issued_at`

Fleet site verifies signature using controller’s public key before installing.

Key rotation:
- Controller returns `controller_public_keys` on registration and can include multiple active keys.
- Fleet site trusts a pinned key set and can accept new keys only if signed by an existing trusted key (or via manual re-connect).

---

## Fleet site behavior (agent)

### Storage (WP options)
- `lf_fleet_api_base`
- `lf_fleet_site_id`
- `lf_fleet_client_token`
- `lf_fleet_connected_at`
- `lf_fleet_last_check_at`
- `lf_fleet_last_result`

### Scheduling
- WP cron event every **15 minutes**, with **jitter** per site (random offset) to prevent burst load.

Load estimate at 500 sites:
- 500 / 15 minutes ≈ 33 checks/minute ≈ 0.55 req/sec on average (small JSON), typically acceptable.

### Update integration (WordPress-native)
- Hook `pre_set_site_transient_update_themes` to inject update availability based on controller response.
- Provide `themes_api` (optional) for changelog display.
- Use WordPress upgrader APIs for background installation when eligible.

### Reporting
- Heartbeat: include current version, last seen, optional environment metadata.
- Install result callback (optional): controller sees success/fail per site.

---

## Controller UI requirements

### Fleet dashboard

- **Sites table**
  - Columns: site label, masked URL, ring, tags, current version, last seen, last result
  - Filters: ring, tags, “out of date”, “failed last update”, “offline”
  - Bulk actions:
    - Assign ring/tags
    - Enable/disable update eligibility (pause)
    - Pin to version (optional)

- **Releases**
  - Create/upload a release: version, changelog, zip artifact, checksum/signature
  - Mark as available for targeting

- **Rollouts**
  - Create rollout rule:
    - All sites
    - Query (ring + tag filters)
    - Explicit site list
  - Select target version per rule
  - Progress view: eligible count, updated count, failures
  - Audit: who created/modified rule, timestamp

---

## Rollout rules (selection semantics)

For a given site + theme_slug:
- Determine candidate rule(s) where `enabled=true` and selector matches site.
- Choose **highest priority** rule (priority is explicit or “most specific wins”):
  - list > query > all
  - then newest rule wins (or explicit `priority` integer)
- The selected rule’s `target_version` is the only approved version for that site.

If no rule matches: no update offered.

---

## Failure handling

On fleet site:
- If check endpoint fails: keep current version, retry next cron.
- If download fails: mark last result, retry with backoff (e.g. next cron + jitter).
- If signature verification fails: **do not install**, report error immediately; controller should treat this as a security incident.
- If install fails: report WP upgrader error string; controller shows in dashboard.

On controller:
- Rate limit by site_id.
- Track install error frequencies; allow pause rule for a cohort if needed.

---

## Privacy / operational constraints

- Do not expose fleet connectivity publicly (no front-end artifacts).
- Fleet connection UI exists only in wp-admin for authorized users.
- Controller UI masks URLs by default; show full URL only to privileged roles.

---

## Open questions (deferred to implementation plan)

- Whether to implement the agent inside the theme vs companion plugin (defaults to theme for now).
- Controller storage: WordPress plugin vs separate service; both work with these APIs.
- CDN/storage choice for zips (S3/R2/etc) for short-lived signed download URLs.

---

## Acceptance criteria

- Controller can approve a release and target it to:
  - all sites
  - a ring
  - a tag-filtered subset
  - an explicit site list
- Fleet sites auto-install approved update within 15 minutes (on average) without manual login.
- All controller API calls are authenticated and replay-protected.
- Fleet site refuses to install tampered zips (checksum/signature enforced).

