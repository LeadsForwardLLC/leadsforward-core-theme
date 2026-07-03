# Frontend Editor + Header Reliability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix AI assistant boot, service intro persistence, header layout/topbar behavior, phone icon alignment, revision preview, and hide editor floats in wp-admin.

**Architecture:** Scope changes to existing editor scripts, header helpers, and revision storage. Add small PHP CLI tests for regressions. All UI changes remain in `inc/ai-assistant.php`, header options in `inc/header-settings.php`, and revision preview in `inc/frontend-revisions.php`.

**Tech Stack:** WordPress PHP theme, jQuery, inline JS/CSS, PHP CLI tests.

---

## File Map
- Modify: `inc/ai-assistant.php` (front-end assets, header panel UI, history UI)
- Modify: `inc/header-settings.php` (top bar color option helpers)
- Modify: `inc/ai-editing/admin-ui.php` (header save AJAX, top bar color)
- Modify: `templates/parts/header.php` (top bar visibility + color)
- Modify: `assets/css/design-system.css` (header layout variants, phone alignment, topbar color)
- Modify: `inc/frontend-revisions.php` (preview mode)
- Create: `tests/assistant-assets.php`
- Create: `tests/service-intro-empty-controls.php`
- Create: `tests/header-topbar-color.php`
- Create: `tests/header-layout-css.php`
- Create: `tests/revision-preview.php`

---

### Task 1: Front-end assets + hide floats in wp-admin

**Files:**
- Modify: `inc/ai-assistant.php` (hooks + assets registration)
- Test: `tests/assistant-assets.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
$src = file_get_contents(__DIR__ . '/../inc/ai-assistant.php');
function expect($cond, $msg) { if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } }
expect(strpos($src, "add_action('wp_enqueue_scripts'") !== false, 'front-end assets hook exists');
expect(strpos($src, "add_action('admin_enqueue_scripts'") === false, 'admin assets hook removed');
expect(strpos($src, "add_action('wp_footer'") !== false, 'front-end footer render hook exists');
expect(strpos($src, "add_action('admin_footer'") === false, 'admin footer render removed');
expect(strpos($src, "is_admin()") !== false, 'front-end asset condition present');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/assistant-assets.php`  
Expected: FAIL with “admin assets hook removed”.

- [ ] **Step 3: Write minimal implementation**

Update `inc/ai-assistant.php`:

```php
// remove admin hooks
remove_action('admin_enqueue_scripts', 'lf_ai_assistant_assets');
remove_action('admin_footer', 'lf_ai_assistant_render_floating_widget');

// in lf_ai_assistant_assets()
if (is_admin()) {
    wp_enqueue_script('wp-hooks');
    wp_enqueue_script('wp-i18n');
    wp_register_script('lf-ai-floating-assistant', '', ['jquery', 'wp-hooks', 'wp-i18n'], LF_THEME_VERSION, true);
} else {
    wp_register_script('lf-ai-floating-assistant', '', ['jquery'], LF_THEME_VERSION, true);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/assistant-assets.php`  
Expected: PASS (no output).

- [ ] **Step 5: Commit**

```bash
git add inc/ai-assistant.php tests/assistant-assets.php
git commit -m "fix: front-end only assistant assets"
```

---

### Task 2: Service intro empty-state controls

**Files:**
- Modify: `inc/ai-assistant.php` (service intro controls)
- Test: `tests/service-intro-empty-controls.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
$src = file_get_contents(__DIR__ . '/../inc/ai-assistant.php');
function expect($cond, $msg) { if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } }
expect(strpos($src, 'buildServiceIntroReorderControls') !== false, 'service intro controls exist');
expect(strpos($src, 'lf-ai-service-intro-empty') !== false, 'empty state control hook exists');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/service-intro-empty-controls.php`  
Expected: FAIL with “empty state control hook exists”.

- [ ] **Step 3: Write minimal implementation**

In `buildServiceIntroReorderControls`, when no cards exist:

```js
var emptyCtl = document.createElement("div");
emptyCtl.className = "lf-ai-service-intro-empty lf-ai-inline-editor-ignore";
emptyCtl.setAttribute("data-lf-ai-service-intro-empty", "1");
emptyCtl.appendChild(addIntroButtonClone);
grid.parentNode.appendChild(emptyCtl);
```

Ensure the add button persists even when `.lf-block-service-intro__grid` is empty.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/service-intro-empty-controls.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add inc/ai-assistant.php tests/service-intro-empty-controls.php
git commit -m "fix: service intro empty add controls"
```

---

### Task 3: Header top bar color + top bar behavior across layouts

**Files:**
- Modify: `inc/header-settings.php`
- Modify: `inc/ai-editing/admin-ui.php`
- Modify: `templates/parts/header.php`
- Modify: `assets/css/design-system.css`
- Test: `tests/header-topbar-color.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
$src = file_get_contents(__DIR__ . '/../inc/header-settings.php');
function expect($cond, $msg) { if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } }
expect(strpos($src, 'lf_header_topbar_color') !== false, 'topbar color helper exists');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/header-topbar-color.php`  
Expected: FAIL with “topbar color helper exists”.

- [ ] **Step 3: Write minimal implementation**

Add helper to `inc/header-settings.php`:

```php
function lf_header_topbar_color(): string {
    $raw = function_exists('lf_get_global_option')
        ? (string) lf_get_global_option('lf_header_topbar_color', '')
        : '';
    return sanitize_text_field($raw);
}
```

Update `lf_ai_ajax_update_header_settings` to accept `header_topbar_color` and save option.

In `templates/parts/header.php`, compute:

```php
$topbar_color = function_exists('lf_header_topbar_color') ? lf_header_topbar_color() : '';
$show_topbar = ($topbar_enabled && $topbar_text !== '');
```

Render inline style:

```php
$topbar_style = $topbar_color !== '' ? ' style="background:' . esc_attr($topbar_color) . ';"' : '';
```

and apply to `.site-header__topbar`.

In CSS, use fallback var for topbar background.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/header-topbar-color.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add inc/header-settings.php inc/ai-editing/admin-ui.php templates/parts/header.php assets/css/design-system.css tests/header-topbar-color.php
git commit -m "feat: header topbar color"
```

---

### Task 4: Header layout CSS variants + phone icon alignment

**Files:**
- Modify: `assets/css/design-system.css`
- Test: `tests/header-layout-css.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
$css = file_get_contents(__DIR__ . '/../assets/css/design-system.css');
function expect($cond, $msg) { if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } }
expect(strpos($css, '.site-header--centered') !== false, 'centered layout styles exist');
expect(strpos($css, '.site-header__phone-icon') !== false && strpos($css, 'line-height: 0') !== false, 'phone icon alignment applied');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/header-layout-css.php`  
Expected: FAIL with “centered layout styles exist”.

- [ ] **Step 3: Write minimal implementation**

Add layout CSS blocks for `.site-header--centered`:

```css
.site-header--centered .site-header__inner { justify-content: center; }
.site-header--centered .site-header__logo { margin-right: auto; margin-left: auto; }
.site-header--centered .site-header__panel { flex: 1; justify-content: center; }
```

Update phone icon alignment:

```css
.site-header__phone-icon { line-height: 0; }
.site-header__phone-icon .lf-icon { width: 0.9em; height: 0.9em; display:block; }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/header-layout-css.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add assets/css/design-system.css tests/header-layout-css.php
git commit -m "fix: header layout css + phone alignment"
```

---

### Task 5: Revision history preview

**Files:**
- Modify: `inc/frontend-revisions.php`
- Modify: `inc/ai-assistant.php`
- Test: `tests/revision-preview.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
$src = file_get_contents(__DIR__ . '/../inc/frontend-revisions.php');
function expect($cond, $msg) { if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } }
expect(strpos($src, 'lf_preview_revision') !== false, 'preview query param handled');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/revision-preview.php`  
Expected: FAIL with “preview query param handled”.

- [ ] **Step 3: Write minimal implementation**

Add preview guard in `inc/frontend-revisions.php`:

```php
function lf_fe_revision_preview_id(): string {
    return isset($_GET['lf_preview_revision']) ? sanitize_text_field((string) $_GET['lf_preview_revision']) : '';
}
```

If preview id present, swap layout config for render only (no save).
In `inc/ai-assistant.php`, add preview button in history rows and banner in UI when previewing.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/revision-preview.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add inc/frontend-revisions.php inc/ai-assistant.php tests/revision-preview.php
git commit -m "feat: revision preview mode"
```

---

### Task 6: Version bump + docs

**Files:**
- Modify: `functions.php`
- Modify: `style.css`
- Modify: `docs/README.md`
- Modify: `inc/docs-dev.php`

- [ ] **Step 1: Write failing test**

```php
<?php
$style = file_get_contents(__DIR__ . '/../style.css');
function expect($cond, $msg) { if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } }
expect(strpos($style, 'Version: 0.1.46') !== false, 'theme version bumped');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/version-bump.php`  
Expected: FAIL with “theme version bumped”.

- [ ] **Step 3: Write minimal implementation**

Bump `LF_THEME_VERSION` and `style.css` version to `0.1.46`, and update docs highlights.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/version-bump.php`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add functions.php style.css docs/README.md inc/docs-dev.php tests/version-bump.php
git commit -m "chore: bump theme version to 0.1.46"
```

---

## Self-Review Checklist
- Spec coverage: Tasks 1–6 map to all goals.
- Placeholder scan: no TODO/TBD.
- Type consistency: option names `lf_header_topbar_color`, preview param `lf_preview_revision`.

---

Plan complete and saved to `docs/superpowers/plans/2026-04-15-editor-header-reliability.md`. Two execution options:

1. **Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration  
2. **Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
