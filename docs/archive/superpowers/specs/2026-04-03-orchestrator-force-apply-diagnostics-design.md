# Orchestrator Force-Apply + Diagnostics Design

## Context
The orchestrator callback is returning HTTP 200 with `success: true`, yet the live site still shows generic placeholders. The payload clearly contains `post_meta` updates, so the failure is likely within the WordPress callback handling (dry-run mode, idempotent short-circuit, or another pre-apply gate).

## Goals
- Ensure orchestrator callbacks **write content immediately** for the current incident.
- Add **clear diagnostics** to the callback response so n8n shows why writes were skipped.
- Keep the change **scoped to the orchestrator endpoint** only.

## Non-Goals
- Refactor AI generation architecture.
- Change how n8n builds payloads (unless needed later).
- Alter unrelated endpoints or admin flows.

## Prerequisites
- Current orchestrator callbacks already include `run_phase: "repair"` for this incident (as seen in the latest payload).
- Auth requirements remain unchanged; force-apply is only available after successful auth.

## Approach (Recommended)
Add a temporary **force-apply** path in `lf_ai_studio_rest_orchestrator`:

1. **Force-apply gate (endpoint-scoped)**
   - Compute `force_apply` when:
     - `run_phase === "repair"` in the incoming payload (primary trigger now), or
     - `force_apply === true` in the payload (optional toggle, implemented now).
   - If `force_apply` is true, bypass:
     - `dry_run` short-circuit
     - idempotent early return

2. **Diagnostics returned in response**
   Always include:
   - `force_apply` (bool)
   - `dry_run` (bool)
   - `idempotent` (bool)
   - `apply_counts`:
     - `homepage_updated` (bool)
     - `posts_updated` (count)
     - `faqs_updated` (count)
     - `service_meta_updated` (count)
   - `errors` (array)

3. **Safety / rollback**
   - Force-apply is **limited to the orchestrator endpoint**.
   - Once content is confirmed on the site, disable force-apply (remove the condition or require explicit flag).

## Data Flow (High Level)
1. n8n `Callback to WP` posts payload.
2. WordPress validates auth + payload.
3. **New**: if `force_apply`, skip idempotent/dry-run short-circuits.
4. Apply updates via `lf_apply_orchestrator_updates`.
5. Respond with diagnostic fields in the response body for n8n visibility.

## Error Handling
- Existing validation errors still fail the job.
- If `force_apply` is enabled but apply fails:
  - Response keeps the current contract: `success: false`, `errors: [...]`, HTTP 400.
  - Diagnostics are still returned alongside the failure fields.

## Response Contract
- `success`: true only when `lf_apply_orchestrator_updates` succeeds.
- `errors`: array of strings (same source as current apply errors).
- `idempotent`: true **only if** the idempotent short-circuit was taken (no apply).
- `idempotent_would_have_been`: true if the short-circuit would have applied but was bypassed due to `force_apply`.

## Testing Plan
- Trigger an orchestrator run with `run_phase: "repair"` and confirm:
  - response includes `force_apply: true`
  - `apply_counts` reflect updates
  - content appears on live site
- Trigger a non-repair run and confirm:
  - `force_apply: false`
  - diagnostics still present

## Rollback Plan
- Remove `force_apply` condition after verification.
- Keep diagnostics in responses for ongoing observability.
