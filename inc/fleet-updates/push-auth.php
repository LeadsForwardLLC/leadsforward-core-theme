<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_fleet_push_expected_sig(string $token, string $method, string $path, int $ts, string $nonce, string $bodySha): string {
	$payload = strtoupper($method) . "\n" . $path . "\n" . $ts . "\n" . $nonce . "\n" . $bodySha;
	return base64_encode(hash_hmac('sha256', $payload, $token, true));
}

function lf_fleet_push_validate_request(
	array $headers,
	string $body,
	string $path,
	string $expected_site_id,
	string $token,
	int $now,
	callable $nonce_seen,
	callable $nonce_store
): array {
	$site_id = trim((string) ($headers['X-LF-Site'] ?? ''));
	$ts_raw = trim((string) ($headers['X-LF-Timestamp'] ?? ''));
	$nonce = trim((string) ($headers['X-LF-Nonce'] ?? ''));
	$sig = trim((string) ($headers['X-LF-Signature'] ?? ''));
	$ts = (int) $ts_raw;

	if ($site_id === '' || $nonce === '' || $sig === '' || $ts <= 0) {
		return ['ok' => false, 'error' => 'missing_headers', 'site_id' => ''];
	}
	if ($site_id !== $expected_site_id) {
		return ['ok' => false, 'error' => 'site_mismatch', 'site_id' => ''];
	}
	if (abs($now - $ts) > 300) {
		return ['ok' => false, 'error' => 'expired', 'site_id' => ''];
	}
	$nonce_key = $site_id . '|' . $nonce;
	if ($nonce_seen($nonce_key)) {
		return ['ok' => false, 'error' => 'replay', 'site_id' => ''];
	}
	$body_sha = hash('sha256', $body);
	$expected = lf_fleet_push_expected_sig($token, 'POST', $path, $ts, $nonce, $body_sha);
	if (!hash_equals($expected, $sig)) {
		return ['ok' => false, 'error' => 'bad_sig', 'site_id' => ''];
	}
	$nonce_store($nonce_key);
	return ['ok' => true, 'error' => '', 'site_id' => $site_id];
}
