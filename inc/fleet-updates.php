<?php
/**
 * Fleet theme updates (pull-based private update channel).
 *
 * Stores controller connection info and integrates with WP update APIs + cron.
 *
 * @package LeadsForward_Core
 * @since 0.1.21
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_FLEET_OPT_API_BASE = 'lf_fleet_api_base';
const LF_FLEET_OPT_SITE_ID  = 'lf_fleet_site_id';
const LF_FLEET_OPT_TOKEN    = 'lf_fleet_client_token';
const LF_FLEET_OPT_PUBKEYS  = 'lf_fleet_controller_pubkeys'; // JSON map keyed by key_id
const LF_FLEET_OPT_LAST     = 'lf_fleet_last_result';        // JSON

const LF_FLEET_OFFER_TRANSIENT = 'lf_fleet_update_offer';
const LF_FLEET_CRON_HOOK = 'lf_fleet_updates_cron';

require_once LF_THEME_DIR . '/inc/fleet-updates/http.php';
require_once LF_THEME_DIR . '/inc/fleet-updates/crypto.php';
require_once LF_THEME_DIR . '/inc/fleet-updates/push-context.php';
require_once LF_THEME_DIR . '/inc/fleet-updates/push-auth.php';
require_once LF_THEME_DIR . '/inc/fleet-updates/push-endpoint.php';
require_once LF_THEME_DIR . '/inc/fleet-updates/helpers.php';
require_once LF_THEME_DIR . '/inc/fleet-updates/wp-updates.php';
require_once LF_THEME_DIR . '/inc/fleet-updates/admin.php';

function lf_fleet_is_connected(): bool {
	return (string) get_option(LF_FLEET_OPT_API_BASE, '') !== ''
		&& (string) get_option(LF_FLEET_OPT_SITE_ID, '') !== ''
		&& (string) get_option(LF_FLEET_OPT_TOKEN, '') !== '';
}

// Menu registration is handled by `inc/ops/menu.php` so the submenu is guaranteed
// to be part of the authorized LeadsForward menu tree (prevents "not allowed"/404-like behavior).

add_filter('cron_schedules', static function (array $schedules): array {
	$sec = (int) apply_filters('lf_fleet_updates_cron_interval', 15 * MINUTE_IN_SECONDS);
	$sec = max(5 * MINUTE_IN_SECONDS, min(60 * MINUTE_IN_SECONDS, $sec));
	$schedules['lf_15m'] = [
		'interval' => $sec,
		/* translators: %d: interval in minutes */
		'display' => sprintf(__('LeadsForward fleet check (%d min)', 'leadsforward-core'), (int) round($sec / 60)),
	];
	return $schedules;
}, 20);

/**
 * Ensure recurring fleet cron exists when connected.
 */
function lf_fleet_updates_maybe_schedule(): void {
	if (!lf_fleet_is_connected()) {
		return;
	}
	if (!wp_next_scheduled(LF_FLEET_CRON_HOOK)) {
		$jitter = function_exists('random_int') ? random_int(30, 10 * 60) : wp_rand(30, 10 * 60);
		wp_schedule_event(time() + (int) $jitter, 'lf_15m', LF_FLEET_CRON_HOOK);
	}
}
add_action('init', 'lf_fleet_updates_maybe_schedule');

/**
 * Remove all scheduled fleet cron jobs (disconnect or maintenance).
 */
function lf_fleet_clear_scheduled_events(): void {
	wp_unschedule_hook(LF_FLEET_CRON_HOOK);
}

/**
 * After connection settings change: ensure recurring event, queue a near-term run, nudge wp-cron.
 */
function lf_fleet_on_connection_updated(): void {
	if (!lf_fleet_is_connected()) {
		return;
	}
	lf_fleet_updates_maybe_schedule();
	if (!get_site_transient('lf_fleet_nearterm_ping')) {
		wp_schedule_single_event(time() + 20, LF_FLEET_CRON_HOOK);
		set_site_transient('lf_fleet_nearterm_ping', '1', 90);
	}
	if (function_exists('spawn_cron')) {
		spawn_cron();
	}
}

/**
 * Visiting Fleet Updates in wp-admin nudges WP-Cron so connected sites catch updates without waiting for front-end traffic.
 */
function lf_fleet_admin_nudge_cron(): void {
	if (!is_admin() || !function_exists('spawn_cron')) {
		return;
	}
	if (!isset($_GET['page']) || (string) wp_unslash($_GET['page']) !== 'lf-fleet-updates') {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!lf_fleet_is_connected()) {
		return;
	}
	spawn_cron();
}
add_action('admin_init', 'lf_fleet_admin_nudge_cron', 30);

function lf_fleet_send_heartbeat(): void {
	if (!lf_fleet_is_connected()) {
		return;
	}
	$theme = wp_get_theme();
	lf_fleet_controller_request('POST', '/api/v1/sites/heartbeat', [], [
		'site_id' => (string) get_option(LF_FLEET_OPT_SITE_ID, ''),
		'theme_slug' => (string) $theme->get_stylesheet(),
		'current_version' => (string) $theme->get('Version'),
		'wp_version' => (string) get_bloginfo('version'),
		'php_version' => PHP_VERSION,
	]);
}

function lf_fleet_check_for_update(bool $override = false): void {
	if (!lf_fleet_is_connected()) {
		return;
	}
	$theme = wp_get_theme();
	$slug = (string) $theme->get_stylesheet();
	$cur = (string) $theme->get('Version');
	// Use POST to avoid intermediary caching of GET responses.
	$res = lf_fleet_controller_request('POST', '/api/v1/updates/check', [], [
		'site_id' => (string) get_option(LF_FLEET_OPT_SITE_ID, ''),
		'theme_slug' => $slug,
		'current' => $cur,
		'override' => $override,
	]);

	update_option(LF_FLEET_OPT_LAST, wp_json_encode([
		'checked_at' => time(),
		'ok' => (bool) $res['ok'],
		'status' => (int) $res['status'],
		'data' => $res['data'],
		'error' => (string) $res['error'],
	]));

	if ($res['ok'] && is_array($res['data']) && !empty($res['data']['update'])) {
		set_site_transient(LF_FLEET_OFFER_TRANSIENT, $res['data'], 20 * MINUTE_IN_SECONDS);
	} else {
		delete_site_transient(LF_FLEET_OFFER_TRANSIENT);
	}
}

/**
 * Extract the most useful upgrade error details for admin display.
 *
 * @param mixed $result
 */
function lf_fleet_upgrade_error_message($result, Theme_Upgrader $upgrader): string {
	if (is_wp_error($result)) {
		$code = $result->get_error_code();
		$msg = $result->get_error_message();
		if ($code !== '' && $msg !== '') {
			return $msg . ' (' . $code . ')';
		}
		return $msg;
	}
	// Theme_Upgrader sometimes stores the WP_Error on the skin result.
	$skin = $upgrader->skin ?? null;
	if (is_object($skin) && property_exists($skin, 'result') && $skin->result instanceof WP_Error) {
		$code = $skin->result->get_error_code();
		$msg = $skin->result->get_error_message();
		if ($msg !== '') {
			return $code !== '' ? $msg . ' (' . $code . ')' : $msg;
		}
	}
	if (is_object($skin) && method_exists($skin, 'get_errors')) {
		$errors = $skin->get_errors();
		if ($errors instanceof WP_Error) {
			$code = $errors->get_error_code();
			$msg = $errors->get_error_message();
			if ($msg !== '') {
				if ($code !== '') {
					return $msg . ' (' . $code . ')';
				}
				return $msg;
			}
		}
	}
	if (is_object($skin) && property_exists($skin, 'lf_last_message')) {
		$last_msg = (string) ($skin->lf_last_message ?? '');
		if ($last_msg !== '') {
			return $last_msg;
		}
	}
	return '';
}

/**
 * Apply the pending fleet update offer via Theme_Upgrader when allowed.
 *
 * @param bool $from_trusted_admin When true, run in wp-admin for users who can edit theme options
 *                                (same capability as the Fleet Updates screen). Cron passes false.
 * @param bool $from_signed_push When true, request was verified by the signed fleet push endpoint (REST).
 */
function lf_fleet_maybe_auto_update(bool $from_trusted_admin = false, bool $from_signed_push = false, bool $force = false): void {
	$offer = get_site_transient(LF_FLEET_OFFER_TRANSIENT);
	if (!is_array($offer) || empty($offer['update'])) {
		return;
	}
	$via_cron = defined('DOING_CRON') && DOING_CRON;
	$cap = defined('LF_OPS_CAP') ? LF_OPS_CAP : 'edit_theme_options';
	$via_admin = $from_trusted_admin && function_exists('is_admin') && is_admin() && function_exists('current_user_can') && current_user_can($cap);
	$via_push = $from_signed_push;
	if (!lf_fleet_should_run_auto_update($via_cron, $via_admin, $via_push)) {
		return;
	}

	// If WP file modifications are disabled, bail early with a clear message.
	if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
		$last = json_decode((string) get_option(LF_FLEET_OPT_LAST, ''), true);
		$nu = is_array($last) ? $last : [];
		$nu['failures'] = (int) ($nu['failures'] ?? 0) + 1;
		$nu['next_attempt_at'] = time() + (15 * MINUTE_IN_SECONDS);
		$nu['last_upgrade_error'] = __('Theme updates are disabled by DISALLOW_FILE_MODS.', 'leadsforward-core');
		update_option(LF_FLEET_OPT_LAST, wp_json_encode($nu));
		return;
	}
	if (function_exists('wp_is_file_mod_allowed') && !wp_is_file_mod_allowed('theme')) {
		$last = json_decode((string) get_option(LF_FLEET_OPT_LAST, ''), true);
		$nu = is_array($last) ? $last : [];
		$nu['failures'] = (int) ($nu['failures'] ?? 0) + 1;
		$nu['next_attempt_at'] = time() + (15 * MINUTE_IN_SECONDS);
		$nu['last_upgrade_error'] = __('Theme updates are blocked by wp_is_file_mod_allowed().', 'leadsforward-core');
		update_option(LF_FLEET_OPT_LAST, wp_json_encode($nu));
		return;
	}

	// Minimal backoff (stored in LF_FLEET_OPT_LAST JSON).
	$last_raw = (string) get_option(LF_FLEET_OPT_LAST, '');
	$last = json_decode($last_raw, true);
	$next_at = is_array($last) ? (int) ($last['next_attempt_at'] ?? 0) : 0;
	if (!$force && $next_at > time()) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$fs_method = function_exists('get_filesystem_method') ? (string) get_filesystem_method([], WP_CONTENT_DIR) : '';
	$fs_ok = function_exists('WP_Filesystem') ? (bool) WP_Filesystem() : false;
	if (!$fs_ok) {
		$last = json_decode((string) get_option(LF_FLEET_OPT_LAST, ''), true);
		$failures = is_array($last) ? (int) ($last['failures'] ?? 0) : 0;
		$failures++;
		$delay = min(120 * MINUTE_IN_SECONDS, (int) (15 * MINUTE_IN_SECONDS * (2 ** max(0, $failures - 1))));
		$nu = is_array($last) ? $last : [];
		$nu['failures'] = $failures;
		$nu['next_attempt_at'] = time() + $delay;
		$nu['last_upgrade_error'] = sprintf(
			/* translators: %s: filesystem method */
			__('Theme upgrade failed: WP_Filesystem could not initialize (method: %s). This usually means the server requires filesystem credentials or blocks direct writes/unzips.', 'leadsforward-core'),
			$fs_method !== '' ? $fs_method : 'unknown'
		);
		update_option(LF_FLEET_OPT_LAST, wp_json_encode($nu));
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	// Ensure WP's theme update transient is fresh so Theme_Upgrader sees our injected offer immediately.
	if (function_exists('wp_update_themes')) {
		wp_update_themes();
	}
	if (!class_exists('LF_Fleet_Auto_Upgrader_Skin')) {
		class LF_Fleet_Auto_Upgrader_Skin extends Automatic_Upgrader_Skin {
			/** @var string */
			public $lf_last_message = '';
			public function feedback($string, ...$args) {
				$msg = is_string($string) ? $string : '';
				if ($msg !== '' && !empty($args)) {
					$msg = vsprintf($msg, $args);
				}
				$this->lf_last_message = sanitize_text_field((string) $msg);
				return parent::feedback($string, ...$args);
			}
			public function error($errors) {
				if ($errors instanceof WP_Error) {
					$code = $errors->get_error_code();
					$msg = $errors->get_error_message();
					$this->lf_last_message = sanitize_text_field(trim($msg . ($code !== '' ? ' (' . $code . ')' : '')));
				}
				return parent::error($errors);
			}
		}
	}
	$upgrader = new Theme_Upgrader(new LF_Fleet_Auto_Upgrader_Skin());
	$stylesheet = wp_get_theme()->get_stylesheet();
	$res = $upgrader->upgrade($stylesheet);
	if ($res === true) {
		delete_site_transient(LF_FLEET_OFFER_TRANSIENT);
		// Reset backoff on success.
		if (is_array($last)) {
			$last['failures'] = 0;
			$last['next_attempt_at'] = 0;
			update_option(LF_FLEET_OPT_LAST, wp_json_encode($last));
		}
		return;
	}

	// Backoff: 15m, 30m, 60m, 120m (cap).
	$failures = is_array($last) ? (int) ($last['failures'] ?? 0) : 0;
	$failures++;
	$delay = min(120 * MINUTE_IN_SECONDS, (int) (15 * MINUTE_IN_SECONDS * (2 ** max(0, $failures - 1))));
	$nu = is_array($last) ? $last : [];
	$nu['failures'] = $failures;
	$nu['next_attempt_at'] = time() + $delay;
	$err = lf_fleet_upgrade_error_message($res, $upgrader);
	if ($err === '') {
		$err = is_string($res) ? $res : __('Theme upgrade failed (no error message). Check filesystem permissions or hosting security rules.', 'leadsforward-core');
	}
	$nu['last_upgrade_error'] = $err;
	update_option(LF_FLEET_OPT_LAST, wp_json_encode($nu));
}

add_action(LF_FLEET_CRON_HOOK, static function (): void {
	lf_fleet_send_heartbeat();
	lf_fleet_check_for_update();
	lf_fleet_maybe_auto_update();
});

