<?php
/**
 * Ensure the header topbar color helper is available.
 */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}

if (!function_exists('add_filter')) {
	function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool {
		return true;
	}
}
if (!function_exists('sanitize_key')) {
	function sanitize_key(string $key): string {
		return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $key));
	}
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field(string $str): string {
		$str = strip_tags($str);
		return trim(preg_replace('/\s+/u', ' ', $str));
	}
}

require __DIR__ . '/../inc/global-settings.php';
require __DIR__ . '/../inc/header-settings.php';

if (!function_exists('lf_header_topbar_color')) {
	fwrite(STDERR, "FAIL: expected lf_header_topbar_color helper\n");
	exit(1);
}

fwrite(STDOUT, "PASS: header-topbar-color\n");
