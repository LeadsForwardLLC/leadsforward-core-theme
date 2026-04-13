<?php
/**
 * Fleet updates: controller HTTP client (HMAC-signed).
 *
 * @package LeadsForward_Core
 * @since 0.1.21
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_fleet_body_sha256(string $body): string {
	return hash('sha256', $body);
}

function lf_fleet_sign_request(string $token, string $method, string $path, int $ts, string $nonce, string $bodySha): string {
	$payload = strtoupper($method) . "\n" . $path . "\n" . $ts . "\n" . $nonce . "\n" . $bodySha;
	return base64_encode(hash_hmac('sha256', $payload, $token, true));
}

/**
 * @param array<string, scalar|null> $query
 * @param array<string, mixed>|null  $body
 * @return array{ok:bool,status:int,data:array<string,mixed>|null,error:string}
 */
function lf_fleet_controller_request(string $method, string $path, array $query = [], $body = null): array {
	$apiBase = rtrim((string) get_option(LF_FLEET_OPT_API_BASE, ''), '/');
	$siteId = (string) get_option(LF_FLEET_OPT_SITE_ID, '');
	$token = (string) get_option(LF_FLEET_OPT_TOKEN, '');
	if ($apiBase === '' || $siteId === '' || $token === '') {
		return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'not_connected'];
	}

	$url = $apiBase . $path;
	if ($query) {
		$url = add_query_arg($query, $url);
	}
	$nonce = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : (string) wp_rand();
	$ts = time();
	$bodyJson = $body === null ? '' : wp_json_encode($body);
	$bodySha = lf_fleet_body_sha256($bodyJson);
	$sig = lf_fleet_sign_request($token, $method, $path, $ts, (string) $nonce, $bodySha);

	$args = [
		'method' => strtoupper($method),
		'timeout' => 12,
		'headers' => [
			'Content-Type' => 'application/json',
			'X-LF-Site' => $siteId,
			'X-LF-Timestamp' => (string) $ts,
			'X-LF-Nonce' => (string) $nonce,
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
	return [
		'ok' => $status >= 200 && $status < 300,
		'status' => $status,
		'data' => is_array($data) ? $data : null,
		'error' => $status >= 200 && $status < 300 ? '' : $raw,
	];
}

