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

// 4) match should pass
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano tuning'];
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
// 5) helper should allow matching slug/label
$expected = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'piano-tuning'];
$incoming = ['business_name' => 'Bethesda Piano Tuning', 'city_region' => 'Bethesda', 'niche' => 'Piano Tuning'];
$result = lf_ai_studio_identity_compare($expected, $incoming);
expect($result['match'] === true, 'slug/label match should pass');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/identity-guard.php`  
Expected: FAIL (slug/label mismatch).

- [ ] **Step 3: Implement guard integration**

Update `inc/ai-studio-rest.php`:
- `require_once __DIR__ . '/ai-studio-identity.php';` near the top.
- After `$apply_payload = $payload['apply'] ?? $payload;`, insert:
  - Build expected identity with per-field precedence using:
    - `get_post_meta($job_id, 'lf_ai_job_request', true)`
    - `lf_ai_studio_get_manifest()` if available
    - `get_option()` fallbacks
  - Build incoming identity from `$apply_payload` first, then `$payload` (including `.meta` fallbacks).
  - Call `lf_ai_studio_identity_compare()`.
  - If mismatch: update job meta, call `lf_ai_autonomy_mark_generation_failed` if available, log mismatch (full fields under `WP_DEBUG`), and return HTTP 200 with `success:false`.
  - If `reason === no_comparable_fields`: log a warning (under `WP_DEBUG`) and continue.
- Ensure guard runs **before** media annotations and **before** dry-run branch.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/identity-guard.php`  
Expected: `PASS`.

- [ ] **Step 5: Commit**

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
- log keys

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

Which approach?
