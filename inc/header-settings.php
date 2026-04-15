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
	$raw = function_exists('lf_get_global_option')
		? (string) lf_get_global_option('lf_header_topbar_text', '')
		: '';
	return sanitize_text_field($raw);
}

function lf_header_topbar_color_sanitize(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}
	if (function_exists('lf_sections_sanitize_custom_background')) {
		return lf_sections_sanitize_custom_background($raw);
	}
	if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})\z/i', $raw)) {
		return strtolower($raw);
	}
	if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(\s*,\s*(0|1|0?\.\d+)\s*)?\)\s*$/i', $raw)) {
		return $raw;
	}
	return '';
}

function lf_header_topbar_color(): string {
	$raw = function_exists('lf_get_global_option')
		? (string) lf_get_global_option('lf_header_topbar_color', '')
		: '';
	return lf_header_topbar_color_sanitize($raw);
}
