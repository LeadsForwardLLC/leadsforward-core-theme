---
title: Orchestrator Business Identity Guard
date: 2026-04-02
status: draft
---

# Orchestrator Business Identity Guard

## Context
We observed n8n callbacks applying content from the wrong business (e.g., Fort Collins roofing) even though the request/blueprint was for Bethesda Piano Tuning. The apply path (`/wp-json/leadsforward/v1/orchestrator` → `lf_apply_orchestrator_updates()`) currently accepts any valid update payload as long as the job binding checks pass. We need a lightweight guard to block cross-site contamination before apply.

## Goals
- Prevent updates from being applied when the incoming payload identity does not match the active manifest identity.
- Fail safely without causing n8n HTTP retries or breaking the webhook call.
- Log clear diagnostics to aid support and future debugging.

## Non-Goals
- Overhauling n8n identity propagation or adding new n8n nodes.
- Retrofitting all historical jobs or automatically repairing misapplied content.
- Enforcing strict matching when the manifest lacks identity data.

## Approach (Recommended)
**Match on `business.name + primary_city + niche`** with normalization. This balances correctness and flexibility and is aligned with deterministic manifest inputs.

### Identity Sources
**Expected (from WordPress):**
- Primary: `lf_site_manifest` → `business.name`, `business.primary_city`, `business.niche`
- Fallback: options (`lf_business_name`, `lf_city_region`, `lf_homepage_city`, `lf_homepage_niche_slug` or equivalent)

**Incoming (from callback payload):**
- Prefer: `apply_payload.business_name`
- Fallbacks:
  - `apply_payload.meta.business_name`
  - `payload.business_name`
  - `payload.meta.business_name`
  - `apply_payload.city_region` / `apply_payload.meta.city_region`
  - `apply_payload.niche` / `apply_payload.meta.niche`

If a specific expected field is empty, the guard should ignore that field rather than fail.

### Normalization
Use a common normalizer for comparisons:
- trim whitespace
- lowercase
- collapse multiple spaces to single
- optionally strip punctuation (only if needed to reduce false mismatches)

### Guard Decision
Compute match result on the **intersection** of fields where both expected and incoming values are present:
- If **any provided field mismatches**, fail the callback.
- If **no comparable fields are present**, skip the guard (allow apply).

### Failure Behavior
On mismatch:
- Mark job as failed (`lf_ai_job_status = failed`)
- Set `lf_ai_job_error = 'business_identity_mismatch'` plus a summary
- Log a structured error:
  - `LF ORCH DEBUG: business_mismatch` with expected vs incoming identity fields
- Return HTTP **200** with `{ success:false, error:"business_identity_mismatch" }`
  - Rationale: avoid n8n retry loops while still recording failure in WP.

## Logging
Add explicit `LF ORCH DEBUG` log lines:
- `business_expected` (sanitized identity)
- `business_incoming` (sanitized identity)
- `business_match` (true/false + reason)

## Testing/Verification
- Create a unit-style test or minimal harness that feeds:
  - matching identity → applies normally
  - mismatched identity → marks failed and skips apply
  - missing incoming identity → guard skipped
  - missing expected identity → guard skipped
- Manual verification:
  - Trigger manifester with correct manifest and confirm no guard logs.
  - Trigger with a wrong business payload and confirm `business_mismatch` log and no apply.

## Rollout Notes
- Safe to deploy to production; guard only blocks when clear mismatch exists.
- Keep logging enabled for initial validation; if noisy, gate via `WP_DEBUG`.

## Open Questions
- If incoming identity fields are consistently missing, do we want to enforce n8n to include them? (Not required for this guard.)
