<?php
/**
 * Global heading casing controls (CSS-only).
 *
 * @package LeadsForward_Core
 * @since 0.1.73
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_heading_case_mode(): string {
	$mode = function_exists('lf_get_global_option') ? (string) lf_get_global_option('lf_heading_case_mode', 'normal') : 'normal';
	$mode = sanitize_key($mode);
	return in_array($mode, ['normal', 'capitalize', 'upper', 'lower'], true) ? $mode : 'normal';
}

function lf_heading_case_body_class(array $classes): array {
	$classes[] = 'lf-heading-case-' . lf_heading_case_mode();
	return $classes;
}
add_filter('body_class', 'lf_heading_case_body_class', 20);

function lf_heading_case_css(): string {
	$mode = lf_heading_case_mode();
	switch ($mode) {
		case 'capitalize':
			$transform = 'capitalize';
			break;
		case 'upper':
			$transform = 'uppercase';
			break;
		case 'lower':
			$transform = 'lowercase';
			break;
		default:
			$transform = '';
			break;
	}
	if ($transform === '') {
		return '';
	}
	// Apply to headings + common section titles. Keep buttons/menus untouched.
	return '.lf-heading-case-' . $mode . ' h1,'
		. '.lf-heading-case-' . $mode . ' h2,'
		. '.lf-heading-case-' . $mode . ' h3,'
		. '.lf-heading-case-' . $mode . ' h4,'
		. '.lf-heading-case-' . $mode . ' h5,'
		. '.lf-heading-case-' . $mode . ' h6,'
		. '.lf-heading-case-' . $mode . ' .lf-section__title{'
		. 'text-transform:' . $transform . ';'
		. '}';
}

function lf_enqueue_heading_case_tokens(): void {
	$css = lf_heading_case_css();
	if ($css === '') {
		return;
	}
	if (wp_style_is('lf-design-system', 'enqueued')) {
		wp_add_inline_style('lf-design-system', $css);
		return;
	}
	wp_register_style('lf-heading-case', false, [], defined('LF_THEME_VERSION') ? (string) LF_THEME_VERSION : null);
	wp_enqueue_style('lf-heading-case');
	wp_add_inline_style('lf-heading-case', $css);
}
add_action('wp_enqueue_scripts', 'lf_enqueue_heading_case_tokens', 7);
add_action('enqueue_block_editor_assets', 'lf_enqueue_heading_case_tokens', 7);

