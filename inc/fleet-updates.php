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
require_once LF_THEME_DIR . '/inc/fleet-updates/wp-updates.php';
require_once LF_THEME_DIR . '/inc/fleet-updates/admin.php';

function lf_fleet_is_connected(): bool {
	return (string) get_option(LF_FLEET_OPT_API_BASE, '') !== ''
		&& (string) get_option(LF_FLEET_OPT_SITE_ID, '') !== ''
		&& (string) get_option(LF_FLEET_OPT_TOKEN, '') !== '';
}

add_action('admin_menu', 'lf_fleet_updates_admin_register_menu');

add_filter('cron_schedules', static function (array $schedules): array {
	if (!isset($schedules['lf_15m'])) {
		$schedules['lf_15m'] = [
			'interval' => 15 * 60,
			'display' => __('LeadsForward 15 minutes', 'leadsforward-core'),
		];
	}
	return $schedules;
});

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

function lf_fleet_check_for_update(): void {
	if (!lf_fleet_is_connected()) {
		return;
	}
	$theme = wp_get_theme();
	$slug = (string) $theme->get_stylesheet();
	$cur = (string) $theme->get('Version');
	$res = lf_fleet_controller_request('GET', '/api/v1/updates/check', [
		'site_id' => (string) get_option(LF_FLEET_OPT_SITE_ID, ''),
		'theme_slug' => $slug,
		'current' => $cur,
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

function lf_fleet_maybe_auto_update(): void {
	$offer = get_site_transient(LF_FLEET_OFFER_TRANSIENT);
	if (!is_array($offer) || empty($offer['update'])) {
		return;
	}
	// Only run on cron to avoid surprising admins during regular page loads.
	if (!defined('DOING_CRON') || !DOING_CRON) {
		return;
	}

	// Minimal backoff (stored in LF_FLEET_OPT_LAST JSON).
	$last_raw = (string) get_option(LF_FLEET_OPT_LAST, '');
	$last = json_decode($last_raw, true);
	$next_at = is_array($last) ? (int) ($last['next_attempt_at'] ?? 0) : 0;
	if ($next_at > time()) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	$upgrader = new Theme_Upgrader(new Automatic_Upgrader_Skin());
	$stylesheet = wp_get_theme()->get_stylesheet();
	$res = $upgrader->upgrade($stylesheet);
	if ($res === true) {
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
	$nu['last_upgrade_error'] = is_wp_error($res) ? $res->get_error_message() : (is_string($res) ? $res : '');
	update_option(LF_FLEET_OPT_LAST, wp_json_encode($nu));
}

add_action(LF_FLEET_CRON_HOOK, static function (): void {
	lf_fleet_send_heartbeat();
	lf_fleet_check_for_update();
	lf_fleet_maybe_auto_update();
});

