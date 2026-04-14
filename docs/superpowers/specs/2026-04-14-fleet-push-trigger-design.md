---
title: "Fleet Push Trigger (Controller-Initiated) — design"
date: 2026-04-14
status: draft
---

## Goal

Allow the controller (`theme.leadsforward.com`) to **initiate** a fleet update on demand while keeping the existing **pull-based security model**. The push trigger should be **fast, user-friendly, and secure**, and should respect rollout rules by default.

## Non-goals

- No remote zip upload or arbitrary code execution on fleet sites.
- No automatic updates for plugins or WordPress core.
- No bypass of file-mod restrictions (`DISALLOW_FILE_MODS` or `wp_is_file_mod_allowed`).

## Recommended approach

Add a **signed push endpoint** on fleet sites that triggers the **existing check + install flow**. The endpoint is protected by HMAC signatures and replay protection. The controller UI adds per-site and bulk push actions.

## Components

### 1) Fleet site push endpoint

- Route: `POST /wp-json/lf/v1/fleet/push`
- Auth: HMAC signature using the site token.
- Payload:
  - `action`: `"check_install"`
  - `override`: `true|false`
  - `request_id`: UUID
- Security:
  - `X-LF-Site`, `X-LF-Timestamp`, `X-LF-Nonce`, `X-LF-Signature`
  - TTL: 5 minutes
  - Nonce stored per-site to prevent replay
- Behavior:
  - If `override=false`, verify rollout eligibility before installing.
  - Run `lf_fleet_check_for_update()` then `lf_fleet_maybe_auto_update(true)`.
  - Return `{ ok, message, updated_to, error_code }`.

### 2) Controller push action

- Per-site button in Connected Sites table.
- Bulk actions: selected sites and tag-filtered.
- Optional “Force install” (override) toggle with confirmation.
- Shows **Last push result** per site (timestamp + message).

## Rollout rules

- Default: **respect rollout eligibility** (selected/tag/all).
- Override: allowed only when admin explicitly confirms in UI and request includes `override=true`.

## Data flow

1. Controller user selects site(s) and clicks “Push update”.
2. Controller signs request and posts to each fleet site endpoint.
3. Fleet site validates signature/TTL/nonce.
4. Fleet site triggers existing update check + install.
5. Fleet site responds; controller logs result and updates UI.

## Error handling

- Return explicit error codes for:
  - `bad_sig`, `expired`, `nonce_replay`
  - `not_eligible`, `file_mods_disallowed`
  - `no_update_available`, `install_failed`
- Controller stores failure message per site for visibility.

## Testing

- Unit test: signature validation and replay protection.
- Integration test: controller push request to a test fleet site.
- Manual verification: per-site push, bulk push, and override behavior.

