# Private Fleet Theme Updates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a secure, controller-approved, pull-based private update channel so fleet sites auto-install theme updates from `theme.leadsforward.com` without logging into individual sites.

**Architecture:** Fleet sites store a `site_id` + revocable token and periodically call a controller “check” endpoint. If eligible/approved, the controller returns metadata + a short-lived download URL. The site verifies checksum + Ed25519 signature, then hands off to WordPress’ upgrader/update pipeline for installation.

**Tech Stack:** WordPress theme (PHP), WP Cron, WP HTTP API, theme update filters (`pre_set_site_transient_update_themes`), `libsodium` for Ed25519 verification (with safe failure if unavailable).

---

## File map (new/modified)

**Create:**
- `inc/fleet-updates.php` — bootstrap: options, hooks, cron schedule, controller HTTP client
- `inc/fleet-updates/admin.php` — wp-admin UI for connect/disconnect/status
- `inc/fleet-updates/crypto.php` — Ed25519 verify + checksum helpers (pure functions)
- `inc/fleet-updates/http.php` — signed request builder (HMAC headers), controller request wrapper
- `inc/fleet-updates/wp-updates.php` — WP update hooks integration (transient injection, install flow)

**Modify:**
- `functions.php` — `lf_load_inc('fleet-updates.php');` in the appropriate place
- `inc/ops/menu.php` (or whichever file defines LeadsForward admin menus) — add “Fleet Updates” settings page under LeadsForward
- `README.md` or `docs/` — brief operator notes (optional; only if needed)

---

### Task 1: Add fleet update option keys + bootstrap include

**Files:**
- Create: `inc/fleet-updates.php`
- Modify: `functions.php`

- [ ] **Step 1: Create option constants + getters**

```php
<?php
// inc/fleet-updates.php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

const LF_FLEET_OPT_API_BASE = 'lf_fleet_api_base';
const LF_FLEET_OPT_SITE_ID  = 'lf_fleet_site_id';
const LF_FLEET_OPT_TOKEN    = 'lf_fleet_client_token';
const LF_FLEET_OPT_PUBKEYS  = 'lf_fleet_controller_pubkeys'; // JSON array keyed by key_id
const LF_FLEET_OPT_LAST     = 'lf_fleet_last_result';        // JSON

function lf_fleet_is_connected(): bool {
  return (string) get_option(LF_FLEET_OPT_API_BASE, '') !== ''
    && (string) get_option(LF_FLEET_OPT_SITE_ID, '') !== ''
    && (string) get_option(LF_FLEET_OPT_TOKEN, '') !== '';
}
```

- [ ] **Step 2: Add include in `functions.php`**

```php
// functions.php
lf_load_inc('fleet-updates.php');
```

- [ ] **Step 3: Verify PHP loads**

Run (locally, any page load): ensure no fatal errors in logs.

- [ ] **Step 4: Commit**

```bash
git add inc/fleet-updates.php functions.php
git commit -m "feat: bootstrap fleet updates module"
```

---

### Task 2: Implement controller request signing (HMAC) + HTTP wrapper

**Files:**
- Create: `inc/fleet-updates/http.php`
- Modify: `inc/fleet-updates.php`

- [ ] **Step 1: Add signing helper**

```php
<?php
// inc/fleet-updates/http.php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

function lf_fleet_body_sha256(string $body): string {
  return hash('sha256', $body);
}

function lf_fleet_sign_request(string $token, string $method, string $path, int $ts, string $nonce, string $bodySha): string {
  $payload = strtoupper($method) . "\n" . $path . "\n" . $ts . "\n" . $nonce . "\n" . $bodySha;
  return base64_encode(hash_hmac('sha256', $payload, $token, true));
}
```

- [ ] **Step 2: Add request wrapper**

```php
function lf_fleet_controller_request(string $method, string $path, array $query = [], $body = null): array {
  $apiBase = rtrim((string) get_option(LF_FLEET_OPT_API_BASE, ''), '/');
  $siteId  = (string) get_option(LF_FLEET_OPT_SITE_ID, '');
  $token   = (string) get_option(LF_FLEET_OPT_TOKEN, '');
  if ($apiBase === '' || $siteId === '' || $token === '') {
    return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'not_connected'];
  }

  $url = $apiBase . $path;
  if ($query) { $url = add_query_arg($query, $url); }
  $nonce = wp_generate_uuid4();
  $ts = time();
  $bodyJson = $body === null ? '' : wp_json_encode($body);
  $bodySha = lf_fleet_body_sha256($bodyJson);
  $sig = lf_fleet_sign_request($token, $method, $path, $ts, $nonce, $bodySha);

  $args = [
    'method' => strtoupper($method),
    'timeout' => 12,
    'headers' => [
      'Content-Type' => 'application/json',
      'X-LF-Site' => $siteId,
      'X-LF-Timestamp' => (string) $ts,
      'X-LF-Nonce' => $nonce,
      'X-LF-Signature' => $sig,
    ],
    'body' => $bodyJson,
  ];
  $res = wp_remote_request($url, $args);
  if (is_wp_error($res)) {
    return ['ok' => false, 'status' => 0, 'data' => null, 'error' => $res->get_error_message()];
  }
  $status = (int) wp_remote_retrieve_response_code($res);
  $raw = (string) wp_remote_retrieve_body($res);
  $data = json_decode($raw, true);
  return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => is_array($data) ? $data : null, 'error' => $status >= 200 && $status < 300 ? '' : $raw];
}
```

- [ ] **Step 3: Wire includes**

```php
// inc/fleet-updates.php
require_once LF_THEME_DIR . '/inc/fleet-updates/http.php';
```

- [ ] **Step 4: Commit**

```bash
git add inc/fleet-updates/http.php inc/fleet-updates.php
git commit -m "feat: add HMAC-signed controller HTTP client"
```

---

### Task 3: Add Ed25519 + checksum verification utilities

**Files:**
- Create: `inc/fleet-updates/crypto.php`
- Modify: `inc/fleet-updates.php`

- [ ] **Step 1: Implement checksum + signature verify**

```php
<?php
// inc/fleet-updates/crypto.php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

function lf_fleet_sha256_file(string $path): string {
  return hash_file('sha256', $path);
}

/**
 * Verify Ed25519 detached signature.
 * - $message is the canonical string the controller signs (defined below in Task 5).
 * - $signatureB64 is base64 signature.
 * - $publicKeyB64 is base64 public key (32 bytes).
 */
function lf_fleet_verify_ed25519(string $message, string $signatureB64, string $publicKeyB64): bool {
  if (!function_exists('sodium_crypto_sign_verify_detached')) {
    return false;
  }
  $sig = base64_decode($signatureB64, true);
  $pk  = base64_decode($publicKeyB64, true);
  if ($sig === false || $pk === false || strlen($pk) !== 32) {
    return false;
  }
  return sodium_crypto_sign_verify_detached($sig, $message, $pk);
}
```

- [ ] **Step 2: Wire includes**

```php
// inc/fleet-updates.php
require_once LF_THEME_DIR . '/inc/fleet-updates/crypto.php';
```

- [ ] **Step 3: Commit**

```bash
git add inc/fleet-updates/crypto.php inc/fleet-updates.php
git commit -m "feat: add checksum + Ed25519 verification helpers"
```

---

### Task 4: Add wp-admin “Fleet Updates” connect/disconnect UI

**Files:**
- Create: `inc/fleet-updates/admin.php`
- Modify: `inc/fleet-updates.php`
- Modify: `inc/ops/menu.php` (or current admin menu file)

- [ ] **Step 1: Create admin page**

```php
<?php
// inc/fleet-updates/admin.php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

function lf_fleet_updates_admin_register_menu(): void {
  add_submenu_page(
    'lf-main', // adjust to existing LeadsForward top-level slug
    __('Fleet Updates', 'leadsforward-core'),
    __('Fleet Updates', 'leadsforward-core'),
    'manage_options',
    'lf-fleet-updates',
    'lf_fleet_updates_admin_render'
  );
}

function lf_fleet_updates_admin_render(): void {
  if (!current_user_can('manage_options')) { return; }
  // Render:
  // - Connected status
  // - Form fields: api_base, site_id, token, public keys JSON
  // - Buttons: Save, Disconnect
  // - “Check now” button that triggers a local check function (still pull-only)
}
```

- [ ] **Step 2: Handle form submissions (nonce + sanitize)**

```php
if (isset($_POST['lf_fleet_save']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce')) {
  update_option(LF_FLEET_OPT_API_BASE, esc_url_raw((string) wp_unslash($_POST['api_base'] ?? '')));
  update_option(LF_FLEET_OPT_SITE_ID, sanitize_text_field((string) wp_unslash($_POST['site_id'] ?? '')));
  update_option(LF_FLEET_OPT_TOKEN, sanitize_text_field((string) wp_unslash($_POST['token'] ?? '')));
  update_option(LF_FLEET_OPT_PUBKEYS, wp_json_encode(json_decode((string) wp_unslash($_POST['pubkeys_json'] ?? ''), true) ?: []));
}
if (isset($_POST['lf_fleet_disconnect']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce')) {
  delete_option(LF_FLEET_OPT_API_BASE);
  delete_option(LF_FLEET_OPT_SITE_ID);
  delete_option(LF_FLEET_OPT_TOKEN);
  delete_option(LF_FLEET_OPT_PUBKEYS);
}
```

- [ ] **Step 3: Wire into menus + module bootstrap**

```php
// inc/fleet-updates.php
require_once LF_THEME_DIR . '/inc/fleet-updates/admin.php';
add_action('admin_menu', 'lf_fleet_updates_admin_register_menu');
```

- [ ] **Step 4: Commit**

```bash
git add inc/fleet-updates/admin.php inc/fleet-updates.php inc/ops/menu.php
git commit -m "feat: add Fleet Updates connect/disconnect admin UI"
```

---

### Task 5: Implement cron schedule (15 minutes + jitter) + heartbeat

**Files:**
- Modify: `inc/fleet-updates.php`

- [ ] **Step 1: Add 15-minute schedule**

```php
add_filter('cron_schedules', function(array $schedules): array {
  if (!isset($schedules['lf_15m'])) {
    $schedules['lf_15m'] = ['interval' => 15 * 60, 'display' => 'LeadsForward 15 minutes'];
  }
  return $schedules;
});
```

- [ ] **Step 2: Schedule event with jitter**

```php
const LF_FLEET_CRON_HOOK = 'lf_fleet_updates_cron';

function lf_fleet_updates_maybe_schedule(): void {
  if (!lf_fleet_is_connected()) { return; }
  if (!wp_next_scheduled(LF_FLEET_CRON_HOOK)) {
    $jitter = random_int(30, 10 * 60); // 30s..10m
    wp_schedule_event(time() + $jitter, 'lf_15m', LF_FLEET_CRON_HOOK);
  }
}
add_action('init', 'lf_fleet_updates_maybe_schedule');
```

- [ ] **Step 3: Cron handler**

```php
add_action(LF_FLEET_CRON_HOOK, function(): void {
  lf_fleet_send_heartbeat();
  lf_fleet_check_for_update(); // implemented in Task 6
});
```

- [ ] **Step 4: Heartbeat implementation**

```php
function lf_fleet_send_heartbeat(): void {
  $theme = wp_get_theme();
  lf_fleet_controller_request('POST', '/api/v1/sites/heartbeat', [], [
    'site_id' => (string) get_option(LF_FLEET_OPT_SITE_ID, ''),
    'theme_slug' => $theme->get_stylesheet(),
    'current_version' => $theme->get('Version'),
    'wp_version' => get_bloginfo('version'),
    'php_version' => PHP_VERSION,
  ]);
}
```

- [ ] **Step 5: Commit**

```bash
git add inc/fleet-updates.php
git commit -m "feat: add 15m cron + heartbeat for fleet updates"
```

---

### Task 6: Implement update check + WordPress update transient injection

**Files:**
- Create: `inc/fleet-updates/wp-updates.php`
- Modify: `inc/fleet-updates.php`

- [ ] **Step 1: Create canonical signature message format**

Canonical message the controller must sign (exact):

```php
function lf_fleet_release_message(string $themeSlug, string $version, string $sha256): string {
  // Keep stable for verification across languages/services.
  return $themeSlug . "\n" . $version . "\n" . strtolower($sha256);
}
```

- [ ] **Step 2: Implement `lf_fleet_check_for_update()`**

```php
function lf_fleet_check_for_update(): void {
  if (!lf_fleet_is_connected()) { return; }
  $theme = wp_get_theme();
  $slug = $theme->get_stylesheet();
  $cur  = (string) $theme->get('Version');
  $res = lf_fleet_controller_request('GET', '/api/v1/updates/check', [
    'site_id' => (string) get_option(LF_FLEET_OPT_SITE_ID, ''),
    'theme_slug' => $slug,
    'current' => $cur,
  ]);
  update_option(LF_FLEET_OPT_LAST, wp_json_encode([
    'checked_at' => time(),
    'ok' => $res['ok'],
    'status' => $res['status'],
    'data' => $res['data'],
    'error' => $res['error'],
  ]));
  // Store eligible update metadata in a transient for WP update hooks to consume.
  if ($res['ok'] && is_array($res['data']) && !empty($res['data']['update'])) {
    set_site_transient('lf_fleet_update_offer', $res['data'], 20 * MINUTE_IN_SECONDS);
  } else {
    delete_site_transient('lf_fleet_update_offer');
  }
}
```

- [ ] **Step 3: Inject update into `pre_set_site_transient_update_themes`**

```php
add_filter('pre_set_site_transient_update_themes', function($transient) {
  if (!is_object($transient)) { return $transient; }
  $offer = get_site_transient('lf_fleet_update_offer');
  if (!is_array($offer) || empty($offer['update'])) { return $transient; }

  $theme = wp_get_theme();
  $slug = $theme->get_stylesheet();
  if (empty($offer['version']) || empty($offer['download_url']) || empty($offer['sha256']) || empty($offer['signature']) || empty($offer['public_key_id'])) {
    return $transient;
  }

  // WordPress expects: response[stylesheet] => array(...)
  $transient->response[$slug] = [
    'theme' => $slug,
    'new_version' => (string) $offer['version'],
    'url' => 'https://theme.leadsforward.com', // info link
    'package' => (string) $offer['download_url'],
  ];
  return $transient;
});
```

- [ ] **Step 4: Wire includes**

```php
// inc/fleet-updates.php
require_once LF_THEME_DIR . '/inc/fleet-updates/wp-updates.php';
```

- [ ] **Step 5: Commit**

```bash
git add inc/fleet-updates/wp-updates.php inc/fleet-updates.php
git commit -m "feat: add controller update check + WP theme update injection"
```

---

### Task 7: Enforce signature verification before install

**Files:**
- Modify: `inc/fleet-updates/wp-updates.php`

- [ ] **Step 1: Add `upgrader_pre_download` filter**

This hook lets us intercept the package before WordPress installs it.

```php
add_filter('upgrader_pre_download', function($reply, $package, $upgrader) {
  $offer = get_site_transient('lf_fleet_update_offer');
  if (!is_array($offer) || empty($offer['update'])) { return $reply; }
  if ((string) $package !== (string) ($offer['download_url'] ?? '')) { return $reply; }

  $theme = wp_get_theme();
  $themeSlug = $theme->get_stylesheet();
  $targetVersion = (string) ($offer['version'] ?? '');
  $sha = strtolower((string) ($offer['sha256'] ?? ''));

  // Fetch public key from stored key set.
  $keysRaw = (string) get_option(LF_FLEET_OPT_PUBKEYS, '[]');
  $keys = json_decode($keysRaw, true);
  $kid = (string) ($offer['public_key_id'] ?? '');
  $pk = is_array($keys) && isset($keys[$kid]) ? (string) $keys[$kid] : '';
  if ($pk === '') {
    return new WP_Error('lf_fleet_no_pubkey', 'Fleet update public key missing; refusing update.');
  }

  $msg = lf_fleet_release_message($themeSlug, $targetVersion, $sha);
  if (!lf_fleet_verify_ed25519($msg, (string) ($offer['signature'] ?? ''), $pk)) {
    return new WP_Error('lf_fleet_bad_signature', 'Fleet update signature verification failed; refusing update.');
  }

  // Allow WordPress to download and proceed. We will verify checksum after download in Step 2.
  return $reply;
}, 10, 3);
```

- [ ] **Step 2: Verify checksum after download**

Use `upgrader_post_install` to validate the downloaded zip file if accessible (implementation detail depends on WP temp file path availability). If temp path isn’t accessible, fall back to verifying the extracted theme directory contents hash is NOT feasible; in that case, rely on signature verification + HTTPS + signed URL, and implement checksum validation by downloading to a temp file ourselves (preferred).

Preferred approach (explicit temp download we control):

```php
function lf_fleet_download_to_temp(string $url): array {
  $tmp = wp_tempnam($url);
  if (!$tmp) { return ['ok' => false, 'path' => '', 'error' => 'tempnam_failed']; }
  $res = wp_remote_get($url, ['timeout' => 60, 'stream' => true, 'filename' => $tmp]);
  if (is_wp_error($res)) { @unlink($tmp); return ['ok' => false, 'path' => '', 'error' => $res->get_error_message()]; }
  $code = (int) wp_remote_retrieve_response_code($res);
  if ($code < 200 || $code >= 300) { @unlink($tmp); return ['ok' => false, 'path' => '', 'error' => 'http_' . $code]; }
  return ['ok' => true, 'path' => $tmp, 'error' => ''];
}
```

Then override install flow in Task 8 (so we always install from our verified temp file), keeping WP’s upgrader behavior.

- [ ] **Step 3: Commit**

```bash
git add inc/fleet-updates/wp-updates.php
git commit -m "feat: verify fleet update signature before install"
```

---

### Task 8: Auto-install behavior (no login) and safe retry/backoff

**Files:**
- Modify: `inc/fleet-updates/wp-updates.php`
- Modify: `inc/fleet-updates.php`

- [ ] **Step 1: Trigger background update when offer exists**

Use WP’s background updater when available; otherwise call upgrader directly from cron.

```php
function lf_fleet_maybe_auto_update(): void {
  $offer = get_site_transient('lf_fleet_update_offer');
  if (!is_array($offer) || empty($offer['update'])) { return; }
  // Guard: only run on cron to avoid surprising admins during page loads.
  if (!defined('DOING_CRON') || !DOING_CRON) { return; }

  // Option A: WP_Automatic_Updater
  if (class_exists('WP_Automatic_Updater')) {
    // Minimal approach: rely on WP core update process after transient injection.
    // Force refresh + run updater is possible but varies by WP version.
  }
  // Option B: Theme_Upgrader (explicit install)
  require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
  $upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
  $upgrader->upgrade(wp_get_theme()->get_stylesheet());
}
```

Wire it into cron:

```php
add_action(LF_FLEET_CRON_HOOK, function(): void {
  lf_fleet_send_heartbeat();
  lf_fleet_check_for_update();
  lf_fleet_maybe_auto_update();
});
```

- [ ] **Step 2: Add backoff on repeated failures**

Store failure count + next allowed attempt in `LF_FLEET_OPT_LAST` JSON and skip auto-update until after `next_attempt_at`.

- [ ] **Step 3: Commit**

```bash
git add inc/fleet-updates.php inc/fleet-updates/wp-updates.php
git commit -m "feat: auto-install approved fleet updates via cron with backoff"
```

---

### Task 9: Manual verification checklist (operator testing)

**Files:**
- Test: manual (wp-admin + logs)

- [ ] **Step 1: Connect a site**
  - Go to LeadsForward → Fleet Updates
  - Enter `api_base`, `site_id`, `token`, `pubkeys_json`
  - Save, confirm “Connected”.

- [ ] **Step 2: Confirm cron schedule exists**
  - Install WP Crontrol (optional) or check `wp cron event list | rg lf_fleet_updates_cron` if WP-CLI available.

- [ ] **Step 3: Force a check**
  - Click “Check now” (admin UI button) which calls `lf_fleet_check_for_update()`.
  - Confirm `lf_fleet_last_result` updated.

- [ ] **Step 4: Confirm update appears in WP updates UI**
  - Dashboard → Updates: the theme shows an update when controller returns `update:true`.

- [ ] **Step 5: Confirm signature enforcement**
  - Temporarily change `signature` in stored transient (dev) and verify update refuses with error logged and no install.

- [ ] **Step 6: Confirm auto-install**
  - With an eligible offer, wait for cron run (or run cron now) and verify theme updates to target version.

---

### Task 10: Documentation + ship

**Files:**
- Modify: `docs/05_THEME_INTEGRATION.md` (or add a new doc) with “How to connect a fleet site”

- [ ] **Step 1: Add operator notes**
  - Required controller fields
  - How to rotate token
  - How to rotate signing keys safely

- [ ] **Step 2: Commit**

```bash
git add docs/05_THEME_INTEGRATION.md
git commit -m "docs: add fleet updates connect and operations notes"
```

- [ ] **Step 3: Ship**
  - Use `./scripts/ship-to-live.sh "Fleet updates: site-side agent"`

---

## Plan self-review checklist (run before execution)

- Spec coverage: does each spec requirement map to a task above?
- No placeholders: search for “TODO/TBD/optional” and replace with decisions or remove.
- Signature message format is fixed and documented for controller implementers.
- Update install is gated behind signature verification and runs only via cron.

