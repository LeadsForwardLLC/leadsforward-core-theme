<?php
declare(strict_types=1);

function lf_fleet_push_build_response(bool $ok, string $message, string $updated_to = '', string $error_code = ''): array {
	return [
		'ok' => $ok,
		'message' => $message,
		'updated_to' => $updated_to,
		'error_code' => $error_code,
	];
}

function lf_fleet_push_build_update_response(string $before, string $after, array $offer, string $last_error): array {
	$target = isset($offer['version']) ? (string) $offer['version'] : '';
	if ($target !== '' && $after !== '' && version_compare($after, $target, '>=')) {
		return lf_fleet_push_build_response(true, 'updated', $after);
	}
	if ($last_error !== '') {
		return lf_fleet_push_build_response(false, $last_error, $after, 'install_failed');
	}
	return lf_fleet_push_build_response(false, 'install_failed', $after, 'install_failed');
}

if (!defined('ABSPATH')) {
	return;
}

function lf_fleet_push_rest_handler(WP_REST_Request $request): WP_REST_Response {
	if (!lf_fleet_is_connected()) {
		return new WP_REST_Response(lf_fleet_push_build_response(false, 'not_connected', '', 'not_connected'), 401);
	}
	$site_id = (string) get_option(LF_FLEET_OPT_SITE_ID, '');
	$token = (string) get_option(LF_FLEET_OPT_TOKEN, '');
	$headers = [
		'X-LF-Site' => (string) $request->get_header('X-LF-Site'),
		'X-LF-Timestamp' => (string) $request->get_header('X-LF-Timestamp'),
		'X-LF-Nonce' => (string) $request->get_header('X-LF-Nonce'),
		'X-LF-Signature' => (string) $request->get_header('X-LF-Signature'),
	];
	$body = (string) $request->get_body();
	$path = '/wp-json/lf/v1/fleet/push';
	$nonce_seen = static function (string $key): bool {
		return (bool) get_transient('lf_fleet_push_' . md5($key));
	};
	$nonce_store = static function (string $key): void {
		set_transient('lf_fleet_push_' . md5($key), '1', 10 * MINUTE_IN_SECONDS);
	};
	$verify = lf_fleet_push_validate_request($headers, $body, $path, $site_id, $token, time(), $nonce_seen, $nonce_store);
	if (empty($verify['ok'])) {
		return new WP_REST_Response(lf_fleet_push_build_response(false, $verify['error'], '', $verify['error']), 401);
	}

	$payload = json_decode($body, true);
	$payload = is_array($payload) ? $payload : [];
	$override = !empty($payload['override']);

	lf_fleet_check_for_update($override);
	$offer = get_site_transient(LF_FLEET_OFFER_TRANSIENT);
	if (!is_array($offer) || empty($offer['update'])) {
		return new WP_REST_Response(lf_fleet_push_build_response(false, 'no_update_available', '', 'no_update'), 200);
	}

	$before = (string) wp_get_theme()->get('Version');
	lf_fleet_maybe_auto_update(false, true);
	$after = (string) wp_get_theme()->get('Version');
	$last = json_decode((string) get_option(LF_FLEET_OPT_LAST, ''), true);
	$last_error = is_array($last) ? (string) ($last['last_upgrade_error'] ?? '') : '';
	return new WP_REST_Response(lf_fleet_push_build_update_response($before, $after, $offer, $last_error), 200);
}

add_action('rest_api_init', static function (): void {
	register_rest_route('lf/v1', '/fleet/push', [
		'methods' => 'POST',
		'permission_callback' => '__return_true',
		'callback' => 'lf_fleet_push_rest_handler',
	]);
});
