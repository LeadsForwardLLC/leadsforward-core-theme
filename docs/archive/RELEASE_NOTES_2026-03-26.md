# Release Notes - 2026-03-26

## Goal
Production hardening + backend simplification for faster site launches, with Foundation Repair as the primary niche.

## Highlights

### 1) Foundation Repair as default niche
- Default niche is now `foundation-repair` for fresh installs and setup fallbacks.
- Foundation Repair copy defaults were strengthened (hero + CTA defaults).

### 2) Curated niche scope in builder UX
Builder-facing niche selectors are now intentionally limited to:
- `foundation-repair` (primary default)
- `roofing`
- `pressure-washing`
- `tree-service`
- `hvac`
- `windows-doors`
- `remodeling`
- `paving`

This reduces backend noise and keeps rollout focused on high-priority verticals.

### 3) Public endpoint hardening
- Added shared security helpers in `inc/security.php`.
- Contact form endpoint:
  - per-IP throttling
  - honeypot handling
- Quote Builder endpoints:
  - per-IP throttling
  - honeypot handling

### 4) Webhook reliability improvements
- Increased Quote Builder webhook timeout.
- Added retry queue processing for transient webhook failures (WP Cron-driven).

### 5) AI Studio auth hardening
- Query-token legacy auth is disabled by default in production.
- Header/HMAC flows remain the recommended production path.

## Files touched (high-level)
- `functions.php`
- `inc/security.php` (new)
- `inc/contact-form.php`
- `inc/quote-builder.php`
- `inc/ai-studio-rest.php`
- `inc/niches/registry.php`
- `inc/niches/setup-runner.php`
- `inc/niches/wizard.php`
- `inc/ops/menu.php`
- `inc/setup.php`

## Documentation updated
- `README.md`
- `docs/README.md`
- `docs/01_SYSTEM_OVERVIEW.md`
- `docs/02_N8N_WORKFLOW_ARCHITECTURE.md`
- `docs/03_MANIFEST_SCHEMA.md`
- `docs/05_THEME_INTEGRATION.md`

## Developer test checklist

### Core regression
- Setup wizard runs end-to-end with Foundation Repair as default.
- Global Settings niche dropdown shows only the curated niche list.
- Existing sites with a saved niche continue to function.

### Lead capture hardening
- Contact form:
  - valid submit works
  - honeypot-filled submit silently returns success
  - rapid repeated requests trigger throttle behavior
- Quote builder:
  - valid submit works
  - honeypot-filled submit silently returns success
  - rapid repeated requests trigger throttle behavior
  - analytics event endpoint remains stable under normal usage

### Webhook delivery
- Simulate GHL webhook failure and confirm retry queue behavior.
- Confirm successful delivery clears retry backlog.

### AI Studio auth behavior
- In local/staging compatibility mode: existing callback flow still works.
- In production-like env: query-token callbacks should not be accepted by default.

## Deployment notes
- Validate on local -> staging -> production (in that order).
- Keep `docs/n8n-workflow.json` versioned and back up production workflow before imports.
- Do not roll these changes to production without running the checklist above.
