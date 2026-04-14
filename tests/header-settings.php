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

// Stub option getters used by lf_get_global_option.
function get_option($key, $default = null) {
	if ($key === 'options_lf_header_layout') {
		return 'centered';
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
