# n8n Workflow Architecture

This workflow is the deterministic content orchestrator. It never writes directly to WordPress; it always returns a strict JSON payload for the WP callback to apply.

## Flow Diagram
```
Webhook
-> Research Document Gate
   -> Use Provided Research
   -> Research Generator (LLM)
-> Store Research Document
-> Split Blueprints + Deterministic Metadata
-> Basic LLM Chain (per page)
-> Parse + Normalize + CTA Guard
-> Quality Gate + SEO Enforcement
-> Deterministic FAQ Enforcement
-> Global Completeness + Blog Gate
-> Merge Blueprint Results
-> Attach Callback Metadata
-> Callback to WP
```

## Step-by-Step
1. **Webhook entry** receives the full payload from WordPress.
2. **Research Document Gate** checks for `research_document`.
3. **Research Generator (LLM)** runs only if no research was provided.
4. **Store Research Document** saves research to workflow static data.
5. **Split Blueprints** creates one item per page and injects:
   - `research_context` (subset of research_document)
   - deterministic `variation_seed`
   - a single `style_profile`
   - `primary_keyword` and `secondary_keywords` for that page
6. **Basic LLM Chain** generates JSON for one page blueprint.
7. **Parse + Normalize + CTA Guard** enforces JSON validity and preserves CTA fields for all pages.
8. **Quality Gate + SEO Enforcement** is a soft gate that repairs missing keywords/phrases and appends warnings (never fails the run).
9. **Deterministic FAQ Enforcement**:
   - Preserves per-page FAQ output (no cross-page injection).
   - Normalizes FAQ answers to valid `<p>` HTML when needed.
10. **Global Completeness + Blog Gate** (run once for all generated items):
   - soft gate: emits scope/quality warnings (no hard failures).
   - emits `quality_warnings` for observability.
11. **Merge Blueprint Results** collects all page updates.
12. **Callback to WP** posts the merged updates to the WP orchestrator endpoint.

## Model Settings
- **Page generation LLM**: `gpt-5.2-chat-latest`, `maxTokens=3500`, `temperature=0.5`
- **Research generation LLM**: `gpt-5.2-chat-latest`, `maxTokens=3000`, `temperature=0.5`

## Progress Reporting
Progress updates are sent to the WP `/progress` endpoint at key milestones (research ready, generation, merge/finalize).

Current progress body fields:
- `job_id`
- `request_id`
- `status`
- `percent`
- `step`
- `message`
- `run_phase` (`initial` or `repair`)

## Callback/Auth Contract (Current)
- n8n callback/progress currently use compatibility auth (`Authorization: Bearer ...`) in this environment.
- WordPress validates callback binding using:
  - `job_id`
  - `request_id`
  - payload hash idempotency checks
- HMAC mode remains supported on WP side.
- Query-token auth is disabled by default in production; use Authorization header and/or HMAC headers for production callbacks.

## Execution Phases
- `initial`: primary full generation run.
- `repair`: targeted requeue pass from WP audit when needed.
- The same `request_id` is preserved across phases for traceability.

## Why This Is Layered
- n8n is the first quality gate and catches low-quality output before callback.
- WordPress is still authoritative and applies deterministic fallback logic server-side.
- If n8n is unavailable, theme-side scaffold + fallback copy/image/SEO systems still populate the site without AI.
