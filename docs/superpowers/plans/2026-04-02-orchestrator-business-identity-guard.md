# Orchestrator Business Identity Guard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Block n8n callbacks that contain the wrong business identity before any media or content apply side effects.

**Architecture:** Add a small, testable identity guard helper (pure PHP) and integrate it early in `lf_ai_studio_rest_orchestrator()` to fail closed on mismatches while returning HTTP 200 to avoid n8n retries. Update docs to describe the guard.

**Tech Stack:** WordPress (PHP), theme `inc/` helpers, simple PHP test script.

---

### Task 1: Add identity guard helper + test

**Files:**
- Create: `inc/ai-studio-identity.php`
- Create: `tests/identity-guard.php`

- [ ] **Step 1: Write the failing test**

Create `tests/identity-guard.php`:
```php
<?php
require __DIR__ . '/../inc/ai-studio-identity.php';

function expect($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
        exit(1);
    }
}

// 1) mismatch should fail
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$incoming = ['business_name' => 'Fort Collins Roofing', 'city_region' => 'Fort Collins', 'niche' => 'roofing'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === false, 'mismatch should fail');

// 2) missing incoming name with expected name should fail
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => '', 'niche' => 'piano-tuning'];
$incoming = ['business_name' => '', 'city_region' => '', 'niche' => 'piano-tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === false, 'missing incoming name should fail');

// 3) no comparable fields should pass with warning reason
$expected = ['business_name' => '', 'city_region' => '', 'niche' => ''];
$incoming = ['business_name' => '', 'city_region' => '', 'niche' => ''];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'no comparable fields should pass');
expect($result['reason'] === 'no_comparable_fields', 'no comparable reason');

// 4) match should pass (exact slug)
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'expected match should pass');

echo "PASS\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/identity-guard.php`  
Expected: FAIL (function not defined).

- [ ] **Step 3: Write minimal implementation**

Create `inc/ai-studio-identity.php` with:
```php
<?php
declare(strict_types=1);

if (!function_exists('lf_ai_studio_identity_normalize_text')) {
    function lf_ai_studio_identity_normalize_text($value): string {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        $text = str_replace('&', ' and ', $text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }
}

if (!function_exists('lf_ai_studio_identity_slug')) {
    function lf_ai_studio_identity_slug($value): string {
        $raw = (string) $value;
        if ($raw === '') {
            return '';
        }
        if (function_exists('sanitize_title')) {
            return (string) sanitize_title($raw);
        }
        $text = function_exists('mb_strtolower') ? mb_strtolower($raw) : strtolower($raw);
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text);
        $text = preg_replace('/\s+/u', '-', $text);
        return trim((string) $text, '-');
    }
}

if (!function_exists('lf_ai_studio_identity_compare')) {
    function lf_ai_studio_identity_compare(array $expected, array $incoming): array {
        $expected_name = lf_ai_studio_identity_normalize_text($expected['business_name'] ?? '');
        $incoming_name = lf_ai_studio_identity_normalize_text($incoming['business_name'] ?? '');
        $expected_city = lf_ai_studio_identity_normalize_text($expected['city_region'] ?? '');
        $incoming_city = lf_ai_studio_identity_normalize_text($incoming['city_region'] ?? '');
        $expected_niche_label = lf_ai_studio_identity_normalize_text($expected['niche'] ?? '');
        $expected_niche_slug = lf_ai_studio_identity_slug($expected['niche'] ?? '');
        $incoming_niche_slug = lf_ai_studio_identity_slug($incoming['niche'] ?? '');

        if ($expected_name !== '' && $incoming_name === '') {
            return ['match' => false, 'reason' => 'missing_business_name'];
        }

        $comparables = [];
        if ($expected_name !== '' && $incoming_name !== '') {
            $comparables['business_name'] = [$expected_name, $incoming_name];
        }
        if ($expected_city !== '' && $incoming_city !== '') {
            $comparables['city_region'] = [$expected_city, $incoming_city];
        }
        if (($expected_niche_slug !== '' || $expected_niche_label !== '') && $incoming_niche_slug !== '') {
            $comparables['niche'] = [$expected_niche_slug ?: $expected_niche_label, $incoming_niche_slug];
        }

        if (empty($comparables)) {
            return ['match' => true, 'reason' => 'no_comparable_fields'];
        }

        foreach ($comparables as $key => $pair) {
            [$left, $right] = $pair;
            if ($left !== $right) {
                return ['match' => false, 'reason' => 'mismatch_' . $key];
            }
        }
        return ['match' => true, 'reason' => 'match'];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/identity-guard.php`  
Expected: `PASS`.

- [ ] **Step 5: Commit**

```bash
git add inc/ai-studio-identity.php tests/identity-guard.php
git commit -m "feat(orchestrator): add identity guard helper"
```

---

### Task 2: Integrate guard into orchestrator callback

**Files:**
- Modify: `inc/ai-studio-rest.php`

- [ ] **Step 1: Write failing test**

Add to `tests/identity-guard.php`:
```php
// 5) helper should honor explicit niche_slug even when label differs
$expected = [
    'business_name' => 'Bethesda Piano Tuning',
    'city_region' => 'Bethesda',
    'niche' => 'Concert Piano Service',
    'niche_slug' => 'piano-tuning',
];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'slug/label match should pass');

// 6) helper should allow incoming to match either slug or label
$expected = [
    'business_name' => 'Bethesda Piano Tuning',
    'city_region' => 'Bethesda',
    'niche' => 'Piano Tuning Service',
    'niche_slug' => 'piano-tuning',
];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'Piano Tuning Service'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'slug OR label match should pass');

// 7) build_expected should honor per-field precedence
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => 'Job Name', 'city_region' => 'Job City', 'niche' => 'job-niche'],
    ['business' => ['name' => 'Manifest Name', 'primary_city' => 'Manifest City', 'niche_slug' => 'manifest-niche']],
    [
        'lf_business_name' => 'Opt Name',
        'lf_city_region' => 'Opt City',
        'lf_homepage_city' => 'Opt City 2',
        'lf_homepage_niche_slug' => 'opt-niche',
    ]
);
expect($expected['business_name'] === 'Job Name', 'job name precedence');
expect($expected['city_region'] === 'Job City', 'job city precedence');
expect($expected['niche'] === 'job-niche', 'job niche precedence');

// 8) build_incoming should prefer apply payload over top-level
$incoming = lf_ai_studio_identity_build_incoming(
    ['business_name' => 'Apply Name', 'meta' => ['city_region' => 'Apply City', 'niche' => 'apply-niche']],
    ['business_name' => 'Payload Name', 'meta' => ['city_region' => 'Payload City', 'niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Apply Name', 'apply business_name precedence');
expect($incoming['city_region'] === 'Apply City', 'apply city precedence');
expect($incoming['niche'] === 'apply-niche', 'apply niche precedence');

// 9) build_expected should mix sources per field when job is missing values
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => 'Job Name', 'city_region' => '', 'niche' => ''],
    ['business' => ['name' => 'Manifest Name', 'primary_city' => 'Manifest City', 'niche_slug' => 'manifest-niche']],
    [
        'lf_business_name' => 'Opt Name',
        'lf_city_region' => 'Opt City',
        'lf_homepage_city' => 'Opt City 2',
        'lf_homepage_niche_slug' => 'opt-niche',
    ]
);
expect($expected['business_name'] === 'Job Name', 'job name still wins');
expect($expected['city_region'] === 'Manifest City', 'manifest city fallback');
expect($expected['niche'] === 'manifest-niche', 'manifest niche fallback');

// 10) build_incoming should honor meta fallbacks
$incoming = lf_ai_studio_identity_build_incoming(
    ['meta' => ['business_name' => 'Meta Name', 'city_region' => 'Meta City', 'niche' => 'meta-niche']],
    ['business_name' => 'Payload Name', 'meta' => ['city_region' => 'Payload City', 'niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Meta Name', 'meta business_name fallback');
expect($incoming['city_region'] === 'Meta City', 'meta city fallback');
expect($incoming['niche'] === 'meta-niche', 'meta niche fallback');

// 11) empty expected should still return no comparable fields
$expected = lf_ai_studio_identity_build_expected([], [], []);
$incoming = ['business_name' => '', 'city_region' => '', 'niche' => ''];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'empty expected should pass');
expect($result['reason'] === 'no_comparable_fields', 'empty expected no comparable');

// 12) manifest should fall back to business.niche when niche_slug missing
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business' => ['name' => 'Manifest Name', 'primary_city' => 'Manifest City', 'niche' => 'Piano Tuning']],
    []
);
expect($expected['niche'] === 'Piano Tuning', 'manifest niche label fallback');

// 13) incoming should fall back to payload when apply is missing
$incoming = lf_ai_studio_identity_build_incoming(
    ['meta' => []],
    ['business_name' => 'Payload Name', 'meta' => ['city_region' => 'Payload City', 'niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Payload Name', 'payload business_name fallback');
expect($incoming['city_region'] === 'Payload City', 'payload city fallback');
expect($incoming['niche'] === 'payload-niche', 'payload niche fallback');

// 14) guard decision should return mismatch response shape
$decision = lf_ai_studio_identity_guard_decision(
    ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'],
    ['business_name' => 'Fort Collins Roofing', 'city_region' => 'Fort Collins', 'niche' => 'roofing'],
    42
);
expect($decision['allow'] === false, 'guard should block mismatch');
expect($decision['response']['success'] === false, 'response success false');
expect($decision['response']['error'][0] === 'business_identity_mismatch', 'response error code');
expect($decision['response']['job_id'] === 42, 'response job_id');
expect($decision['response']['acknowledged'] === true, 'response acknowledged');

// 15) incoming should fall back to payload if apply is missing fields
$incoming = lf_ai_studio_identity_build_incoming(
    ['meta' => ['city_region' => 'Apply City']],
    ['business_name' => 'Payload Name', 'meta' => ['niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Payload Name', 'payload name fallback when apply missing');
expect($incoming['city_region'] === 'Apply City', 'apply city still wins');
expect($incoming['niche'] === 'payload-niche', 'payload niche fallback when apply missing');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/identity-guard.php`  
Expected: FAIL (new functions or niche_slug support missing).

- [ ] **Step 3: Implement guard integration**

Update `inc/ai-studio-rest.php`:
- `require_once __DIR__ . '/ai-studio-identity.php';` near the top.
- After `$apply_payload = $payload['apply'] ?? $payload;`, insert **guard block** that runs only after binding/idempotent checks (the existing early return stays before the guard):
  - Build expected identity with per-field precedence using:
    - `get_post_meta($job_id, 'lf_ai_job_request', true)`
    - `lf_ai_studio_get_manifest()` if available (`business.primary_city` fallback to `business.address.city`)
    - `get_option()` fallbacks (`lf_business_name`, `lf_city_region`, `lf_homepage_city`, `lf_homepage_niche_slug`)
    - If `lf_ai_job_request` or manifest are not arrays, treat as empty arrays.
    - Expected niche: prefer `business.niche_slug`, then `business.niche`.
  - Build incoming identity using explicit order:
    - `apply.business_name` → `apply.meta.business_name` → `payload.business_name` → `payload.meta.business_name`
    - `apply.city_region` → `apply.meta.city_region` → `payload.city_region` → `payload.meta.city_region`
    - `apply.niche` → `apply.meta.niche` → `payload.niche` → `payload.meta.niche`
  - Call `lf_ai_studio_identity_compare()`.
  - If mismatch: update job meta, call `lf_ai_autonomy_mark_generation_failed` if available, log mismatch (full fields under `WP_DEBUG`), and return HTTP 200 with `success:false`.
- If `reason === no_comparable_fields`: log a warning (under `WP_DEBUG`) and continue.
- Ensure guard runs **before** media annotations and **before** dry-run branch.
- **Mismatch response shape:** `{ success:false, error:["business_identity_mismatch"], job_id, acknowledged:true }` (HTTP 200).
- **Mismatch meta:** `lf_ai_job_status = failed`, `lf_ai_job_error = business_identity_mismatch`, `lf_ai_job_summary` set to a short readable message (e.g. `Orchestrator blocked: business identity mismatch.`).
- **Mismatch logs:** `LF ORCH DEBUG: business_expected`, `business_incoming`, `business_match`; truncate each field to 120 chars and strip HTML; always log a mismatch summary; full expected/incoming only when `WP_DEBUG`.
- Update helper: accept `niche_slug` on expected identity and compare incoming slug against **either** expected slug **or** label slug (disjunctive match).
- Add `lf_ai_studio_identity_build_expected()` helper with per-field precedence.
- Add `lf_ai_studio_identity_build_incoming()` helper to merge apply + payload sources.
- Add `lf_ai_studio_identity_guard_decision($expected, $incoming, $job_id)` helper returning `allow`, `reason`, and response payload for mismatches.
 - Implement helper changes first, get `php tests/identity-guard.php` green, then wire `lf_ai_studio_rest_orchestrator()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/identity-guard.php`  
Expected: `PASS`.

- [ ] **Step 5: Manual verification checklist (post-deploy)**

- Note: REST/orchestrator behavior is verified manually (no WP harness in this repo).
- Explicitly accept that end-to-end apply/no-apply behavior is manual-only (helper tests do not assert REST side effects).
- If available, re-run tests in a WP-loaded context to validate `sanitize_title` parity.
- Confirm guard is placed **after** the idempotent early return in `lf_ai_studio_rest_orchestrator()` (code review).
- Replay the same callback twice and confirm the second (idempotent) call does not log `business_match`.
- Trigger manifester with the correct manifest and confirm no mismatch logs.
- Trigger with a wrong-business payload and confirm:
  - `business_identity_mismatch` error
  - HTTP 200 response body includes `acknowledged: true`
  - no content applied
  - `lf_ai_job_changes` is empty and `lf_ai_job_status` is `failed`
- Cross-check one real `lf_ai_job_request` and `lf_site_manifest` sample to confirm key shapes match helper expectations.

- [ ] **Step 6: Commit**

```bash
git add inc/ai-studio-rest.php tests/identity-guard.php
git commit -m "feat(orchestrator): block mismatched business callbacks"
```

---

### Task 3: Update docs

**Files:**
- Modify: `docs/05_THEME_INTEGRATION.md`

- [ ] **Step 1: Update docs**

Add a short section noting the identity guard, including:
- mismatch failure behavior (HTTP 200 + job failed)
- comparison fields
- log keys (`business_expected`, `business_incoming`, `business_match`)
- response body shape and error code

- [ ] **Step 2: Commit**

```bash
git add docs/05_THEME_INTEGRATION.md
git commit -m "docs: add orchestrator identity guard note"
```

---

## Plan Review Loop
After completing this plan, dispatch a plan review subagent to review the plan against the spec.

## Execution Handoff
Plan complete and saved to `docs/superpowers/plans/2026-04-02-orchestrator-business-identity-guard.md`.

Two execution options:
1. **Subagent-Driven (recommended)** - dispatch a fresh subagent per task, review between tasks
2. **Inline Execution** - execute tasks in this session using executing-plans with checkpoints

At execution time, ask the user which approach they prefer.
