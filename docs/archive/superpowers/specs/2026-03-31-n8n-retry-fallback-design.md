---
title: N8N Retry Fallback for Blueprint Cache Misses
date: 2026-03-31
status: in_review
owner: LeadsForward
---

## Summary

When the LLM generates valid content, the retry path can still discard it because `blueprint_index` is missing in the parse output. The retry builder then cannot load the cached blueprint, produces empty updates, and the merge returns `ok:false` with no updates. This design makes retries **best-effort**: they run only when a cache hit exists and fall back to the original parsed updates otherwise.

## Goals

- Preserve successful LLM output even when retry cannot run.
- Keep quality warnings and retry logic intact for pages where cache lookup works.
- Avoid empty callbacks that cause default theme content to remain.

## Non-goals

- Rewriting the LLM prompts or changing overall content strategy.
- Changing WordPress apply logic or section schema rules.
- Removing the quality gate or retry system entirely.

## Current Flow (Relevant Segment)

1. **Split Blueprints + Deterministic Metadata** produces one item per blueprint.
2. **Basic LLM Chain** generates JSON for that item.
3. **Parse + Normalize + CTA Guard** validates and normalizes output.
4. **Quality Gate + SEO Enforcement** attaches warnings.
5. **Retry Decision** sets `needs_retry` if warnings are severe.
6. **Retry Gate** routes `needs_retry=true` to **Retry Input Builder** and then **LLM Retry Chain**.
7. **Retry Parse + Normalize + CTA Guard** and **Retry Quality Gate** pass into **Deterministic FAQ** merge.

## Root Cause

The parse output has `blueprint_index: null` (LLM output doesn’t preserve it).  
`Retry Input Builder` looks up cache only by `request_id:blueprint_index`.  
That lookup fails and the retry chain runs without a blueprint, producing empty updates.  
The merge then reports **“No updates produced”**, so WordPress applies nothing and defaults show.

## Proposed Changes (Best-Effort Retry)

### 1) Store cache aliases in **Store Blueprint Cache**
Add cache keys so a retry can locate the original blueprint even when `blueprint_index` is missing.

**Keys to add:**
- `request_id:blueprint_index` (existing)
- `request_id:page_type:primary_keyword` (normalized)
- `request_id:page_type:post:<post_id>` when `blueprint.post_id` is present

**Normalization rules (write + read):**
- trim, lowercase
- collapse whitespace to single spaces
- replace `:` with `-` to avoid key collisions

**Applied to:** `page_type`, `primary_keyword`, and any free-text key segments (not `request_id` or numeric `post_id`).

**Canonical cache model (new):**
- `blueprint_cache_by_id[canonical_id] = <full blueprint input>`
  - `canonical_id = request_id:blueprint_index` (always available at split stage)
- `blueprint_cache_aliases[alias_key] = canonical_id`
  - If an alias already exists and points to a different canonical id, record a collision:
    `blueprint_cache_alias_collisions[alias_key] = true`

### 2) Gate retries on cache hit in **Retry Decision**
Only set `needs_retry = true` when at least one cache key exists for that item.

**Logic:**
- Determine `primary_keyword` with precedence:
  1) `meta.primary_keyword`
  2) `primary_keyword`
- Build candidate alias keys in order:
  1) `request_id:blueprint_index` (only if `blueprint_index` is non-empty)
  2) `request_id:page_type:post:<post_id>` (only if `post_id` is non-empty)
  3) `request_id:page_type:primary_keyword` (only if `page_type` + `primary_keyword` are non-empty)
- Resolve each alias via `blueprint_cache_aliases`.
- If **any candidate alias is flagged in `blueprint_cache_alias_collisions`**, mark **ambiguous**, set `needs_retry=false`, append warning:
  `Retry skipped: alias collision for cache key.`
- If **multiple unique canonical ids** are found, mark **ambiguous**, set `needs_retry=false`, append warning:
  `Retry skipped: ambiguous cache key match.`
- If **no hit**, set `needs_retry=false` and append warning:
  `Retry skipped: missing cached blueprint for page_type + primary_keyword.`
- If a **single hit** exists, set:
  - `needs_retry = true`
  - `retry_cache_key = <matched alias key>`
  - `retry_cache_id = <canonical id>`

**Fallback cache storage (single source of truth):**
- Store the **full parsed item** in
  `retry_fallback_cache[retry_cache_id] = item.json`
  (only when a single canonical id exists).

### 3) Use alias key in **Retry Input Builder**
When `needs_retry=true`, select cached blueprint using the same key strategy:

**Order:**
1. `retry_cache_key` (set by Retry Decision)
2. `request_id:blueprint_index`
3. `request_id:page_type:post:<post_id>`
4. `request_id:page_type:primary_keyword`

This ensures retries have the blueprint even when index is missing.

**If no blueprint cache hit (defensive):**
- Set `retry_cache_miss=true`
- Return the **fallback original** from `retry_fallback_cache[retry_cache_id]`
- A new **Retry Cache Gate (IF)** routes `retry_cache_miss=true` directly to **Deterministic FAQ Enforcement** (skip retry chain).

### 4) Fallback on retry failure (new)
If the retry output is `ok:false` or has empty `updates`, return the **cached original parsed output** instead and append a warning:
`Retry failed; used original output for <page_type>.`

This can be implemented in **Retry Quality Gate + SEO Enforcement** by:
- Checking `json.ok` / `updates.length`
- Looking up `workflowStaticData.retry_fallback_cache[retry_cache_id]`
- Returning that cached original item if present

### 5) Cache invalidation (new)
To avoid cross-request contamination:
- Track `last_request_id` in workflow static data.
- If the current `request_id` differs, **clear**:
  - `blueprint_cache_by_id`
  - `blueprint_cache_aliases`
  - `retry_fallback_cache`
  - any alias collision tracking

## Data Flow After Change

- Valid parse output → warnings → Retry Decision
  - If cache hit: retry runs normally
  - If cache miss: **original updates continue**, retry skipped
  - If retry fails: **original updates are restored**
- Merge always receives at least the original successful updates.

## Risks / Mitigations

- **Ambiguous cache key** if multiple pages share the same primary keyword.
  - Mitigation: include `page_type` and `post_id` when available; skip retry if key is ambiguous or missing.
- **Retry still fails even with cache**.
  - Mitigation: explicit fallback to cached original output; warnings remain for visibility.
- **Stale cache** in workflow static data.
  - Mitigation: clear `blueprint_cache_by_id`, `blueprint_cache_aliases`, and `retry_fallback_cache` when `request_id` changes (track `last_request_id`), or store under a per-request namespace and prune old entries.

## Verification Plan

- Run a full manifester execution.
- Confirm **Retry Decision** shows `needs_retry=false` when cache miss, and content still reaches merge.
- Confirm **Merge Blueprint Results** returns `ok:true` and non-empty updates.
- Confirm homepage fields reflect generated content (not defaults).
- Force a retry failure (invalid JSON) and verify fallback to original updates.
- Run two pages with same `page_type` + `primary_keyword` to confirm no incorrect blueprint selection.

