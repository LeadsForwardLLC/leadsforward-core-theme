<?php
/**
 * Header layout + topbar options (global settings).
 *
 * @package LeadsForward_Core
 * @since 0.1.42
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_header_layout_sanitize(string $value): string {
	$allowed = ['modern', 'centered', 'topbar'];
	$value = sanitize_key($value);
	return in_array($value, $allowed, true) ? $value : 'modern';
}

function lf_header_layout(): string {
	$raw = function_exists('lf_get_global_option')
		? (string) lf_get_global_option('lf_header_layout', 'modern')
		: 'modern';
	return lf_header_layout_sanitize($raw);
}

function lf_header_topbar_enabled(): bool {
	$raw = function_exists('lf_get_global_option')
		? (string) lf_get_global_option('lf_header_topbar_enabled', '0')
		: '0';
	return $raw === '1';
}

function lf_header_topbar_text(): string {
	return function_exists('lf_get_global_option')
		? (string) lf_get_global_option('lf_header_topbar_text', '')
		: '';
}
