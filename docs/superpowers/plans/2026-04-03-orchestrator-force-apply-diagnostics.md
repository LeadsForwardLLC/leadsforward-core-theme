# Orchestrator Force-Apply Diagnostics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Force-apply orchestrator callbacks when requested and surface diagnostics in the response so n8n shows why writes were skipped.

**Architecture:** Add a pure helper to detect force-apply intent, bypass idempotent/dry-run gates in the orchestrator handler, and attach diagnostics to every orchestrator response. Keep changes scoped to the `/orchestrator` endpoint.

**Tech Stack:** PHP (WordPress theme), WP REST API, CLI PHP tests.

---

## File Structure

- **Create:** `inc/ai-studio-orchestrator-utils.php`  
  Pure helper for `force_apply` detection (no WP bootstrap required).
- **Modify:** `inc/ai-studio-rest.php`  
  Load the helper file and update `lf_ai_studio_rest_orchestrator` to:
  - bypass idempotent/dry-run when `force_apply` is true
  - include diagnostics in responses
- **Create:** `tests/orchestrator-force-apply.php`  
  CLI test for the helper logic (mirrors existing `tests/identity-guard.php` style).

---

### Task 1: Add force-apply helper + unit test

**Files:**
- Create: `tests/orchestrator-force-apply.php`
- Create: `inc/ai-studio-orchestrator-utils.php`
- Modify: `inc/ai-studio-rest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
require __DIR__ . '/../inc/ai-studio-orchestrator-utils.php';

function expect($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
        exit(1);
    }
}

// run_phase=repair should force apply
expect(
    lf_ai_studio_orchestrator_force_apply_enabled(['run_phase' => 'repair']) === true,
    'repair run_phase should force apply'
);

// explicit force_apply should force apply
expect(
    lf_ai_studio_orchestrator_force_apply_enabled(['force_apply' => true]) === true,
    'force_apply flag should force apply'
);

// missing signals should not force apply
expect(
    lf_ai_studio_orchestrator_force_apply_enabled([]) === false,
    'missing signals should not force apply'
);

// nested apply payload should still be detected
expect(
    lf_ai_studio_orchestrator_force_apply_enabled(['apply' => ['run_phase' => 'repair']]) === true,
    'nested apply run_phase should force apply'
);

echo "PASS\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/orchestrator-force-apply.php`  
Expected: FAIL with “undefined function lf_ai_studio_orchestrator_force_apply_enabled”

- [ ] **Step 3: Implement minimal helper**

Create `inc/ai-studio-orchestrator-utils.php`:

```php
<?php
declare(strict_types=1);

if (!function_exists('lf_ai_studio_orchestrator_force_apply_enabled')) {
    function lf_ai_studio_orchestrator_force_apply_enabled(array $payload): bool {
        $run_phase = '';
        if (isset($payload['run_phase'])) {
            $run_phase = (string) $payload['run_phase'];
        } elseif (isset($payload['apply']) && is_array($payload['apply']) && isset($payload['apply']['run_phase'])) {
            $run_phase = (string) $payload['apply']['run_phase'];
        }
        $run_phase = function_exists('sanitize_text_field')
            ? sanitize_text_field($run_phase)
            : trim($run_phase);
        if ($run_phase === 'repair') {
            return true;
        }
        return !empty($payload['force_apply']);
    }
}
```

- [ ] **Step 4: Load helper in REST file**

Add near the top of `inc/ai-studio-rest.php` (with other requires):

```php
require_once __DIR__ . '/ai-studio-orchestrator-utils.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/orchestrator-force-apply.php`  
Expected: `PASS`

- [ ] **Step 6: Commit**

```bash
git add tests/orchestrator-force-apply.php inc/ai-studio-orchestrator-utils.php inc/ai-studio-rest.php
git commit -m "test: cover orchestrator force-apply detection"
```

---

### Task 2: Apply force-apply + diagnostics in orchestrator handler

**Files:**
- Modify: `inc/ai-studio-rest.php`

- [ ] **Step 1: Add diagnostics scaffold (placement is critical)**

In `lf_ai_studio_rest_orchestrator`, insert **after** the block that unwraps
stringified payloads (`if (isset($payload['payload']) && is_string(...)) { ... }`)
and **after** `$job_id` / `$request_id` are computed:

```php
$force_apply = function_exists('lf_ai_studio_orchestrator_force_apply_enabled')
    ? lf_ai_studio_orchestrator_force_apply_enabled($payload)
    : false;

$dry_run = get_option('lf_ai_autonomy_dry_run', '0') === '1';

$diagnostics = [
    'force_apply' => $force_apply,
    'dry_run' => $dry_run,
    'idempotent' => false,
    'idempotent_would_have_been' => false,
    'errors' => [],
    'apply_counts' => [
        'homepage_updated' => false,
        'posts_updated' => 0,
        'faqs_updated' => 0,
        'service_meta_updated' => 0,
    ],
];
```

- [ ] **Step 2: Bypass idempotent when force-apply is true**

Right after `lf_ai_studio_rest_validate_callback_binding`:

```php
if (!empty($binding['idempotent'])) {
    if ($force_apply) {
        $diagnostics['idempotent'] = false;
        $diagnostics['idempotent_would_have_been'] = true;
    } else {
        $diagnostics['idempotent'] = true;
        return new \WP_REST_Response([
            'job_id' => $job_id,
            'success' => true,
            'idempotent' => true,
            'errors' => [],
        ] + $diagnostics, 200);
    }
}
```

- [ ] **Step 3: Bypass dry-run when force-apply is true**

Replace the existing dry-run block with:

```php
if ($dry_run && !$force_apply) {
    update_post_meta($job_id, 'lf_ai_job_status', 'done');
    update_post_meta($job_id, 'lf_ai_job_summary', 'Dry-run validation succeeded; no writes committed.');
    if (function_exists('lf_ai_autonomy_mark_generation_success')) {
        lf_ai_autonomy_mark_generation_success($job_id, ['dry_run' => true, 'request_id' => $request_id]);
    }
    return new \WP_REST_Response([
        'job_id' => $job_id,
        'success' => true,
        'dry_run' => true,
        'errors' => [],
    ] + $diagnostics, 200);
}
```

Ensure the dry-run response merges diagnostics.

- [ ] **Step 4: Attach diagnostics to all orchestrator responses**

Update each response in `lf_ai_studio_rest_orchestrator` (success + failure paths) to include `$diagnostics`.

For success after apply:

```php
$diagnostics['apply_counts'] = [
    'homepage_updated' => !empty($apply_result['changes']['homepage']),
    'posts_updated' => count($apply_result['changes']['posts'] ?? []),
    'faqs_updated' => count($apply_result['changes']['faqs'] ?? []),
    'service_meta_updated' => count(array_filter($apply_payload['updates'] ?? [], static function ($u) {
        return is_array($u) && (string) ($u['target'] ?? '') === 'service_meta';
    })),
];

return new \WP_REST_Response([
    'job_id' => $job_id,
    'success' => $apply_result['success'],
    'error' => $apply_result['errors'] ?? [],
    'errors' => $apply_result['errors'] ?? [],
] + $diagnostics, $apply_result['success'] ? 200 : 400);
```

Also merge `$diagnostics` into:
- validation failures
- orchestrator “no updates” ACK
- idempotent response
- dry-run response
- business identity mismatch response
- missing media annotations response

Tip: grep `return new \\WP_REST_Response` inside `lf_ai_studio_rest_orchestrator`
to ensure every path after payload parsing merges diagnostics.

Note: For `invalid_json` / auth failures that return early before payload parsing,
diagnostics are not available and are not required.

- [ ] **Step 5: Manual verification**

Run an orchestrator callback with `run_phase: "repair"` and confirm:
- n8n output contains `force_apply: true`
- `apply_counts.posts_updated > 0`
- content appears on `https://theme.leadsforward.com/`

Run a non-repair callback and confirm:
- `force_apply: false`
- diagnostics still present

- [ ] **Step 6: Commit**

```bash
git add inc/ai-studio-rest.php
git commit -m "fix(orchestrator): force-apply callbacks with diagnostics"
```

---

## Plan Review Loop
After writing the complete plan:

1. Dispatch a single plan-document-reviewer subagent with:
   - **Plan:** `docs/superpowers/plans/2026-04-03-orchestrator-force-apply-diagnostics.md`
   - **Spec:** `docs/superpowers/specs/2026-04-03-orchestrator-force-apply-diagnostics-design.md`
2. If ❌ Issues Found: fix the plan and re-dispatch reviewer
3. If ✅ Approved: proceed to execution handoff

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-03-orchestrator-force-apply-diagnostics.md`.

Two execution options:

1. **Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks
2. **Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Choose one approach when you're ready to execute.
