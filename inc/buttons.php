<?php
/**
 * Global button tokens (text case, default size).
 *
 * @package LeadsForward_Core
 * @since 0.1.190
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_button_text_case(): string {
	$raw = function_exists('lf_get_option') ? (string) lf_get_option('lf_button_text_case', 'option', 'uppercase') : 'uppercase';
	return $raw === 'normal' ? 'normal' : 'uppercase';
}

function lf_button_default_size(): string {
	$raw = function_exists('lf_get_option') ? (string) lf_get_option('lf_button_default_size', 'option', 'lg') : 'lg';
	return in_array($raw, ['lg', 'md', 'sm'], true) ? $raw : 'lg';
}

function lf_btn_size_class(string $size = ''): string {
	if ($size === '') {
		$size = lf_button_default_size();
	}
	$size = in_array($size, ['lg', 'md', 'sm'], true) ? $size : 'md';
	return 'lf-btn--' . $size;
}

/**
 * @param array<int, string> $classes
 * @return array<int, string>
 */
function lf_button_body_class(array $classes): array {
	if (lf_button_text_case() === 'normal') {
		$classes[] = 'lf-btn-text-normal';
	}
	return $classes;
}
add_filter('body_class', 'lf_button_body_class');

function lf_button_branding_css(): string {
	$normal = lf_button_text_case() === 'normal';
	return ':root{'
		. '--lf-btn-text-transform:' . ($normal ? 'none' : 'uppercase') . ';'
		. '--lf-btn-letter-spacing:' . ($normal ? 'normal' : '0.04em') . ';'
		. '}';
}

function lf_enqueue_button_tokens(): void {
	$css = lf_button_branding_css();
	if ($css === '') {
		return;
	}
	if (wp_style_is('lf-design-system', 'enqueued')) {
		wp_add_inline_style('lf-design-system', $css);
		return;
	}
	wp_register_style('lf-button-tokens', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-button-tokens');
	wp_add_inline_style('lf-button-tokens', $css);
}
add_action('wp_enqueue_scripts', 'lf_enqueue_button_tokens', 7);
add_action('enqueue_block_editor_assets', 'lf_enqueue_button_tokens', 7);
