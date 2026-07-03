# N8N Retry Fallback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ensure retry logic never drops valid LLM output when cache keys are missing or retry fails, so callbacks always contain the original generated updates.

**Architecture:** Add canonical blueprint caching + alias keys during split, gate retries on cache hits, and fall back to cached original parsed output when retry fails or cache is missing. Maintain per-request cache isolation.

**Tech Stack:** n8n workflow JSON, JavaScript code nodes

---

## File Structure

- Modify: `docs/n8n-workflow.json`
  - Code nodes: `Split Blueprints + Deterministic Metadata`, `Store Blueprint Cache`, `Retry Decision`, `Retry Input Builder`, `Retry Quality Gate + SEO Enforcement`
  - Add: `Retry Cache Gate` (IF node) between `Retry Input Builder` and `LLM Retry Chain`

---

### Task 1: Add canonical + alias cache storage

**Files:**
- Modify: `docs/n8n-workflow.json` (node: `Store Blueprint Cache`)

- [ ] **Step 1: Write failing test (manual)**
  - Run a generation and confirm `blueprint_index` is null in Parse output and retry cache miss occurs.

- [ ] **Step 2: Add cache normalization helper**
  - Implement `normalizeKeySegment()` (trim, lowercase, collapse whitespace, replace `:` with `-`).
  - Apply normalization to `page_type` and `primary_keyword` only (not `request_id` or numeric `post_id`).

- [ ] **Step 3: Store canonical cache**
  - Add `blueprint_cache_by_id[request_id:blueprint_index] = $json`.
  - Add `blueprint_cache_aliases[request_id:blueprint_index] = canonical_id` so Retry Decision can resolve this key via aliases.
  - Add `blueprint_cache_aliases[alias_key] = canonical_id` for:
    - `request_id:page_type:post:<post_id>` (if post_id exists)
    - `request_id:page_type:primary_keyword` (if both exist)
  - Track collisions with `blueprint_cache_alias_collisions[alias_key] = true` when alias maps to a different canonical id.

- [ ] **Step 4: Cache invalidation per request**
  - If `last_request_id` differs, clear `blueprint_cache_by_id`, `blueprint_cache_aliases`, `blueprint_cache_alias_collisions`, `retry_fallback_cache`.

- [ ] **Step 5: Commit**
  - `git add docs/n8n-workflow.json && git commit -m "fix(n8n): add canonical + alias blueprint caches"`

---

### Task 2: Gate retries on cache hit + store fallback original

**Files:**
- Modify: `docs/n8n-workflow.json` (node: `Retry Decision`)

- [ ] **Step 1: Extend Retry Decision**
  - Determine `primary_keyword` with precedence: `meta.primary_keyword` then `primary_keyword`.
  - Build candidate alias keys in order (only when segments are non-empty; normalize `page_type` + `primary_keyword`):
    1) `request_id:blueprint_index`
    2) `request_id:page_type:post:<post_id>`
    3) `request_id:page_type:primary_keyword`
  - Resolve via `blueprint_cache_aliases`.
  - If any alias is in `blueprint_cache_alias_collisions`, set `needs_retry=false` and warning:
    `Retry skipped: alias collision for cache key.`
  - If multiple unique canonical ids are found, set `needs_retry=false` and warning:
    `Retry skipped: ambiguous cache key match.`
  - If no hit, set `needs_retry=false` and warning:
    `Retry skipped: missing cached blueprint for page_type + primary_keyword.`
  - On single hit: set `needs_retry=true`, `retry_cache_key`, `retry_cache_id`.
  - Store `retry_fallback_cache[retry_cache_id] = item.json`.

- [ ] **Step 2: Ensure warnings are preserved**
  - Append warning on cache miss or collision.

- [ ] **Step 3: Commit**
  - `git add docs/n8n-workflow.json && git commit -m "fix(n8n): gate retries on cache hit + store fallback"`

---

### Task 3: Defensive retry input builder + cache gate

**Files:**
- Modify: `docs/n8n-workflow.json` (node: `Retry Input Builder`)
- Add: `Retry Cache Gate` (IF node)

- [ ] **Step 1: Update Retry Input Builder**
  - Resolve blueprint from `retry_cache_key` first, then fallback keys in order:
    1) `request_id:blueprint_index`
    2) `request_id:page_type:post:<post_id>`
    3) `request_id:page_type:primary_keyword`
  - Normalize `page_type` + `primary_keyword` when building keys (same helper as write).
  - Resolve alias → canonical id → blueprint via `blueprint_cache_by_id`.
  - If no hit, set `retry_cache_miss=true` and return fallback original from `retry_fallback_cache[retry_cache_id]`.

- [ ] **Step 2: Add Retry Cache Gate**
  - IF `retry_cache_miss` is true: route to `Deterministic FAQ Enforcement`.
  - ELSE: route to `LLM Retry Chain`.

- [ ] **Step 3: Commit**
  - `git add docs/n8n-workflow.json && git commit -m "fix(n8n): skip retry when cache miss"`

---

### Task 4: Retry failure fallback

**Files:**
- Modify: `docs/n8n-workflow.json` (node: `Retry Quality Gate + SEO Enforcement`)

- [ ] **Step 1: Detect retry failures**
  - If `json.ok === false` OR `updates` empty, load fallback `retry_fallback_cache[retry_cache_id]`.
  - Append warning: `Retry failed; used original output for <page_type>.`

- [ ] **Step 2: Ensure retry_cache_id is preserved**
  - Pass `retry_cache_id` through retry chain (Retry Input Builder → Retry Parse → Retry Quality Gate).

- [ ] **Step 3: Commit**
  - `git add docs/n8n-workflow.json && git commit -m "fix(n8n): fallback to original output on retry failure"`

---

### Task 5: Verification

- [ ] **Step 1: Manual execution**
  - Run a full manifester execution.
  - Confirm Retry Decision sets `needs_retry=false` on cache miss.
  - Confirm Merge returns `ok:true` and non-empty updates.
  - Confirm homepage fields reflect generated content (not defaults).

- [ ] **Step 2: Forced retry failure**
  - Trigger retry with invalid JSON (simulate) and confirm fallback to original output.

- [ ] **Step 3: Collision scenario**
  - Run two pages with same `page_type` + `primary_keyword` and confirm retry is skipped with a collision warning.

- [ ] **Step 4: Commit notes**
  - If any changes required from verification, commit with clear message.

---

## Plan Review Loop

1. Dispatch a plan-document-reviewer subagent with this plan and the spec.
2. If issues found, update plan and re-review.
3. If approved, proceed to execution handoff.
