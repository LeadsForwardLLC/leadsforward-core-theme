<?php
/**
 * Shared security helpers used by public endpoints.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Resolve a best-effort client IP for anonymous throttling.
 */
function lf_security_client_ip(): string {
	$keys = [
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'REMOTE_ADDR',
	];
	foreach ($keys as $key) {
		if (empty($_SERVER[$key])) {
			continue;
		}
		$raw = (string) wp_unslash($_SERVER[$key]);
		$parts = array_filter(array_map('trim', explode(',', $raw)));
		$candidate = $parts[0] ?? '';
		if ($candidate !== '') {
			return sanitize_text_field($candidate);
		}
	}
	return 'unknown';
}

/**
 * Basic per-IP action throttle.
 *
 * Returns true when request should proceed, false when rate limit exceeded.
 */
function lf_security_rate_limit_allow(string $action, int $max_requests, int $window_seconds): bool {
	$max_requests = max(1, $max_requests);
	$window_seconds = max(5, $window_seconds);
	$ip = lf_security_client_ip();
	$key = 'lf_rl_' . md5($action . '|' . $ip);
	$count = (int) get_transient($key);
	if ($count >= $max_requests) {
		return false;
	}
	set_transient($key, $count + 1, $window_seconds);
	return true;
}
