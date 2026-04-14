<?php
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}

if (!function_exists('add_filter')) {
	function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool {
		return true;
	}
}
if (!function_exists('is_admin')) {
	function is_admin(): bool {
		return false;
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

// Stub option getters used by lf_get_global_option.
function get_option($key, $default = null) {
	if ($key === 'options_lf_header_layout') {
		return 'centered';
	}
	if ($key === 'options_lf_header_topbar_enabled') {
		return '1';
	}
	if ($key === 'options_lf_header_topbar_text') {
		return '  Promo <strong>with</strong> tags  ';
	}
	return $default;
}
function get_field($key, $post_id = null) {
	return null;
}

require __DIR__ . '/../inc/global-settings.php';
require __DIR__ . '/../inc/header-settings.php';

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(lf_header_layout() === 'centered', 'layout reads option');
expect(lf_header_layout_sanitize('modern') === 'modern', 'sanitize allows modern');
expect(lf_header_layout_sanitize('bad') === 'modern', 'sanitize defaults');
expect(lf_header_topbar_enabled() === true, 'topbar enabled reads option');
expect(lf_header_topbar_text() === 'Promo with tags', 'topbar text is sanitized');
