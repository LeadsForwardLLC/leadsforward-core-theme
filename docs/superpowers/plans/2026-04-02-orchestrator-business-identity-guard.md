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

- [ ] **Step 0: Create stub helper file**

Create a minimal stub so the test can require the path:
```php
<?php
// Stub for TDD red phase. Real implementation added in Step 3.
```
Step 3 replaces this stub with the real implementation.
If the repo enforces linting on PHP files, add minimal `function_exists` stubs instead of an empty file.

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

// 5) normalization should collapse spaces and replace &
expect(
    lf_ai_studio_identity_normalize_text('  Piano  &   Tuning ') === 'piano and tuning',
    'normalize spaces and &'
);

// 6) normalization should strip punctuation
expect(
    lf_ai_studio_identity_normalize_text('Bethesda, MD') === 'bethesda md',
    'normalize punctuation'
);

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
Also run: `wp eval-file tests/identity-guard.php` (required if WP CLI is available).
Treat the WP-loaded run as authoritative for niche slug behavior; if unavailable, record the limitation and do not treat test 26 as definitive.

- [ ] **Step 5: Commit**

```bash
git add inc/ai-studio-identity.php tests/identity-guard.php
git commit -m "feat(orchestrator): add identity guard helper"
```
Do not merge after Task 1 alone; Task 2 is required for spec-accurate niche rules.

---

### Task 2: Integrate guard into orchestrator callback

**Files:**
- Modify: `inc/ai-studio-rest.php`

- [ ] **Step 1: Write failing test**

Add to `tests/identity-guard.php`:
```php
// 7) helper should honor explicit niche_slug even when label differs
$expected = [
    'business_name' => 'Bethesda Piano Tuning',
    'city_region' => 'Bethesda',
    'niche' => 'Concert Piano Service',
    'niche_slug' => 'piano-tuning',
];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'slug/label match should pass');

// 8) helper should allow incoming to match either slug or label
$expected = [
    'business_name' => 'Bethesda Piano Tuning',
    'city_region' => 'Bethesda',
    'niche' => 'Piano Tuning Service',
    'niche_slug' => 'piano-tuning-service',
];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'Piano Tuning Service'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'slug OR label match should pass');

// 9) build_expected should honor per-field precedence
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

// 10) build_incoming should prefer apply payload over top-level
$incoming = lf_ai_studio_identity_build_incoming(
    ['business_name' => 'Apply Name', 'meta' => ['city_region' => 'Apply City', 'niche' => 'apply-niche']],
    ['business_name' => 'Payload Name', 'meta' => ['city_region' => 'Payload City', 'niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Apply Name', 'apply business_name precedence');
expect($incoming['city_region'] === 'Apply City', 'apply city precedence');
expect($incoming['niche'] === 'apply-niche', 'apply niche precedence');

// 11) build_expected should mix sources per field when job is missing values
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

// 12) build_incoming should honor meta fallbacks
$incoming = lf_ai_studio_identity_build_incoming(
    ['meta' => ['business_name' => 'Meta Name', 'city_region' => 'Meta City', 'niche' => 'meta-niche']],
    ['business_name' => 'Payload Name', 'meta' => ['city_region' => 'Payload City', 'niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Meta Name', 'meta business_name fallback');
expect($incoming['city_region'] === 'Meta City', 'meta city fallback');
expect($incoming['niche'] === 'meta-niche', 'meta niche fallback');

// 13) empty expected should still return no comparable fields
$expected = lf_ai_studio_identity_build_expected([], [], []);
$incoming = ['business_name' => '', 'city_region' => '', 'niche' => ''];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'empty expected should pass');
expect($result['reason'] === 'no_comparable_fields', 'empty expected no comparable');

// 14) manifest should fall back to business.niche when niche_slug missing
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business' => ['name' => 'Manifest Name', 'primary_city' => 'Manifest City', 'niche' => 'Piano Tuning']],
    []
);
expect($expected['niche'] === 'Piano Tuning', 'manifest niche label fallback');

// 15) incoming should fall back to payload when apply is missing
$incoming = lf_ai_studio_identity_build_incoming(
    ['meta' => []],
    ['business_name' => 'Payload Name', 'meta' => ['city_region' => 'Payload City', 'niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Payload Name', 'payload business_name fallback');
expect($incoming['city_region'] === 'Payload City', 'payload city fallback');
expect($incoming['niche'] === 'payload-niche', 'payload niche fallback');

// 16) guard decision should return mismatch response shape
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

// 17) incoming should fall back to payload if apply is missing fields
$incoming = lf_ai_studio_identity_build_incoming(
    ['meta' => ['city_region' => 'Apply City']],
    ['business_name' => 'Payload Name', 'meta' => ['niche' => 'payload-niche']]
);
expect($incoming['business_name'] === 'Payload Name', 'payload name fallback when apply missing');
expect($incoming['city_region'] === 'Apply City', 'apply city still wins');
expect($incoming['niche'] === 'payload-niche', 'payload niche fallback when apply missing');

// 18) guard decision should allow when identity matches
$decision = lf_ai_studio_identity_guard_decision(
    ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'],
    ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'],
    7
);
expect($decision['allow'] === true, 'guard should allow matching identity');

// 19) options city precedence should prefer lf_city_region
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    [],
    ['lf_city_region' => 'City A', 'lf_homepage_city' => 'City B']
);
expect($expected['city_region'] === 'City A', 'options city precedence');

// 20) build_expected should fall back to manifest business.name
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business' => ['name' => 'Manifest Name', 'primary_city' => '', 'niche_slug' => '']],
    ['lf_business_name' => 'Opt Name']
);
expect($expected['business_name'] === 'Manifest Name', 'manifest name fallback');

// 21) build_expected should fall back to options business name
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business' => ['name' => '', 'primary_city' => '', 'niche_slug' => '']],
    ['lf_business_name' => 'Opt Name']
);
expect($expected['business_name'] === 'Opt Name', 'options name fallback');

// 22) build_expected should fall back to business.address.city
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business' => ['primary_city' => '', 'address' => ['city' => 'Address City']]],
    []
);
expect($expected['city_region'] === 'Address City', 'address city fallback');

// 23) partial expected identity should ignore missing fields
$expected = ['business_name' => '', 'city_region' => 'Bethesda', 'niche' => ''];
$incoming = ['business_name' => 'Other Name', 'city_region' => 'Bethesda', 'niche' => ''];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'partial expected identity ignores missing name');

// 24) guard decision should allow when no comparable fields
$decision = lf_ai_studio_identity_guard_decision(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    9
);
expect($decision['allow'] === true, 'guard allows no comparable fields');

// 25) build_expected should use lf_homepage_niche_slug when other sources empty
$expected = lf_ai_studio_identity_build_expected(
    ['business_name' => '', 'city_region' => '', 'niche' => ''],
    [],
    ['lf_homepage_niche_slug' => 'opt-niche']
);
expect($expected['niche'] === 'opt-niche', 'options niche_slug fallback');

// 26) niche label should compare via slug (sanitize_title) not raw normalize
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'Piano & Tuning'];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'Piano Tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'label slug comparison should pass');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/identity-guard.php`  
Expected: FAIL (new functions or niche_slug support missing).

- Note: This is a batch red step (not strict one-test TDD); implement helpers in the order tests fail.
- Note: Test 26 is non-authoritative under plain `php` (no `sanitize_title`); rely on the WP-loaded run for that case.

- [ ] **Step 3: Implement guard integration**

Update `inc/ai-studio-rest.php`:
- `require_once __DIR__ . '/ai-studio-identity.php';` near the top.
- After `$apply_payload = $payload['apply'] ?? $payload;`, insert **guard block** that runs only after binding/idempotent checks (the existing early return stays before the guard):
  - Place the guard immediately after `$apply_payload = ...` and **before** `$media_annotations = ...` (before any vision/media side effects).
  - Review `lf_ai_studio_rest_orchestrator()` ordering (idempotent return → apply payload → media annotations) before inserting the guard.
  - Enumerate side effects between `$apply_payload` and the guard; ensure none run before the guard except the existing job status/response meta writes.
  - Confirm no alternate orchestrator entry points bypass this file (quick search for `lf_ai_studio_rest_orchestrator` usage / includes).
  - Build expected identity with per-field precedence using:
    - `get_post_meta($job_id, 'lf_ai_job_request', true)`
    - `lf_ai_studio_get_manifest()` if available (`business.primary_city` fallback to `business.address.city`)
    - `get_option()` fallbacks (`lf_business_name`, `lf_city_region`, `lf_homepage_city`, `lf_homepage_niche_slug`)
    - If `lf_ai_job_request` or manifest are not arrays, treat as empty arrays.
    - If `lf_ai_studio_get_manifest()` is missing or returns non-array, treat manifest as empty (still use job/options).
    - Expected niche: prefer `business.niche_slug`, then `business.niche`.
    - Confirm `lf_ai_studio_get_manifest()` reads `lf_site_manifest` (matches spec).
  - Build incoming identity using explicit order:
    - `apply.business_name` → `apply.meta.business_name` → `payload.business_name` → `payload.meta.business_name`
    - `apply.city_region` → `apply.meta.city_region` → `payload.city_region` → `payload.meta.city_region`
    - `apply.niche` → `apply.meta.niche` → `payload.niche` → `payload.meta.niche`
    - Payload fallbacks for city/niche are intentional (aligns with observed n8n payloads).
  - Call `lf_ai_studio_identity_guard_decision()` to decide allow/block + response.
  - If `allow` is false: update job meta, call `lf_ai_autonomy_mark_generation_failed` if available, log mismatch (full fields under `WP_DEBUG`), and return HTTP 200 with `success:false`.
  - If `reason === no_comparable_fields`: log a warning (under `WP_DEBUG`) and continue.
- Ensure guard runs **before** media annotations and **before** dry-run branch.
- **Mismatch response shape:** `{ success:false, error:["business_identity_mismatch"], job_id, acknowledged:true }` (HTTP 200).
- **Mismatch meta:** `lf_ai_job_status = failed`, `lf_ai_job_error = business_identity_mismatch`, `lf_ai_job_summary` set to a short readable message (e.g. `Orchestrator blocked: business identity mismatch.`).
- Confirm UI/ops pages read `lf_ai_job_summary` (not only `lf_ai_job_error`) for human-readable failure text.
- Keep the machine code in `lf_ai_job_error` and the human summary in `lf_ai_job_summary`.
- **Mismatch logs:** `LF ORCH DEBUG: business_expected`, `business_incoming`, `business_match`; truncate each field to 120 chars and strip HTML; always log a mismatch summary; full expected/incoming only when `WP_DEBUG`.
- **Allow-path logs:** emit `business_expected` / `business_incoming` / `business_match` only under `WP_DEBUG` to avoid production noise.
- Update helper: accept `niche_slug` on expected identity and compare incoming slug against **either** expected slug **or** `sanitize_title(expected label)` (disjunctive match).
- Add `lf_ai_studio_identity_build_expected()` helper with per-field precedence.
- Add `lf_ai_studio_identity_build_incoming()` helper to merge apply + payload sources.
- Add `lf_ai_studio_identity_guard_decision($expected, $incoming, $job_id)` helper returning `allow`, `reason`, and response payload for mismatches.
 - Implement helper changes first, get `php tests/identity-guard.php` green, then wire `lf_ai_studio_rest_orchestrator()`.
 - Suggested order: update `compare` niche_slug logic → `build_expected` → `build_incoming` → `guard_decision` → REST wiring; re-run tests after each.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/identity-guard.php`  
Expected: `PASS`.

- [ ] **Step 5: Manual verification checklist (post-deploy)**

- Note: REST/orchestrator behavior is verified manually (no WP harness in this repo).
- Explicitly accept that end-to-end apply/no-apply behavior is manual-only (helper tests do not assert REST side effects).
- Spec testing bullets for “matching applies normally” and “mismatch skips apply” are satisfied via this manual checklist.
- Matching “applies normally” is manual-only by design unless a WP test harness is added.
- Explicitly verify the guard returns **before** any apply/media side effects on mismatch.
- Note: `lf_ai_job_response` is stored before the guard; mismatches will still capture payload for forensics.
- Run tests in a WP-loaded context (e.g. `wp eval-file tests/identity-guard.php`) to validate `sanitize_title` parity; if unavailable, record the limitation.
- Confirm guard is placed **after** the idempotent early return in `lf_ai_studio_rest_orchestrator()` (code review).
- Replay the same callback twice and confirm the second (idempotent) call does not log `business_match`.
- Trigger manifester with the correct manifest and confirm no mismatch logs.
- Confirm `LF MANIFEST: updates applied` and content changes are present on the happy path.
- Trigger with a wrong-business payload and confirm:
  - `business_identity_mismatch` error
  - HTTP 200 response body includes `acknowledged: true`
  - no content applied
  - `lf_ai_job_changes` is empty and `lf_ai_job_status` is `failed`
- Cross-check one real `lf_ai_job_request` and `lf_site_manifest` sample to confirm key shapes match helper expectations.
- Open WP admin → AI Studio → Jobs (or the job detail screen) and confirm the summary text is visible for a failed job.
- Confirm no UI path displays only `lf_ai_job_error` without the summary text.

- [ ] **Step 6: Commit**

```bash
git add inc/ai-studio-identity.php inc/ai-studio-rest.php tests/identity-guard.php
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
- note that n8n should rely on the response body (`success`/`acknowledged`), not HTTP status
- how to run `php tests/identity-guard.php` for helper verification
- recommend `wp eval-file tests/identity-guard.php` when WP CLI is available
- mention that `lf_ai_job_response` may be stored even when apply is blocked; use job status + summary for truth

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
