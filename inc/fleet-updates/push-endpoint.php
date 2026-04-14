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

if (!defined('ABSPATH')) {
	return;
}

function lf_fleet_push_rest_handler(WP_REST_Request $request): WP_REST_Response {
	if (!lf_fleet_is_connected()) {
		return new WP_REST_Response(lf_fleet_push_build_response(false, 'not_connected', '', 'not_connected'), 401);
	}
	$site_id = (string) get_option(LF_FLEET_OPT_SITE_ID, '');
	$token = (string) get_option(LF_FLEET_OPT_TOKEN, '');
	$headers = [];
	foreach ($request->get_headers() as $key => $vals) {
		$headers['X-' . str_replace(' ', '-', ucwords(str_replace('-', ' ', $key)))] = (string) ($vals[0] ?? '');
	}
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

	lf_fleet_maybe_auto_update(false, true);
	$theme = wp_get_theme();
	return new WP_REST_Response(lf_fleet_push_build_response(true, 'updated', (string) $theme->get('Version')), 200);
}

add_action('rest_api_init', static function (): void {
	register_rest_route('lf/v1', '/fleet/push', [
		'methods' => 'POST',
		'permission_callback' => '__return_true',
		'callback' => 'lf_fleet_push_rest_handler',
	]);
});
