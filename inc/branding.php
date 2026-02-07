<?php
/**
 * Branding tokens: CSS variables sourced from Theme Options > Branding.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_branding_get_value(string $key, string $default): string {
	$val = '';
	if (function_exists('get_field')) {
		foreach (['lf-branding', 'options_lf_branding', 'options_lf-branding', 'option', 'options'] as $post_id) {
			$tmp = get_field($key, $post_id);
			if (is_string($tmp) && $tmp !== '') {
				$val = $tmp;
				break;
			}
		}
	}
	if ($val === '') {
		$opt = get_option('options_' . $key, '');
		if (is_string($opt) && $opt !== '') {
			$val = $opt;
		}
	}
	$val = is_string($val) ? $val : '';
	$val = $val !== '' ? $val : $default;
	$val = function_exists('sanitize_hex_color') ? (sanitize_hex_color($val) ?: $default) : $val;
	return $val;
}

function lf_branding_css(): string {
	$primary   = lf_branding_get_value('lf_brand_primary', '#2563eb');
	$secondary = lf_branding_get_value('lf_brand_secondary', '#0ea5e9');
	$tertiary  = lf_branding_get_value('lf_brand_tertiary', '#f97316');
	$light     = lf_branding_get_value('lf_surface_light', '#ffffff');
	$soft      = lf_branding_get_value('lf_surface_soft', '#f8fafc');
	$dark      = lf_branding_get_value('lf_surface_dark', '#0f172a');
	$card      = lf_branding_get_value('lf_surface_card', '#ffffff');
	$text      = lf_branding_get_value('lf_text_primary', '#0f172a');
	$muted     = lf_branding_get_value('lf_text_muted', '#64748b');
	$inverse   = lf_branding_get_value('lf_text_inverse', '#ffffff');

	return ':root{'
		. '--lf-color-primary:' . $primary . ';'
		. '--lf-color-secondary:' . $secondary . ';'
		. '--lf-color-tertiary:' . $tertiary . ';'
		. '--lf-surface-light:' . $light . ';'
		. '--lf-surface-soft:' . $soft . ';'
		. '--lf-surface-dark:' . $dark . ';'
		. '--lf-surface-card:' . $card . ';'
		. '--lf-text-primary:' . $text . ';'
		. '--lf-text-muted:' . $muted . ';'
		. '--lf-text-inverse:' . $inverse . ';'
		. '--lf-primary:var(--lf-color-primary);'
		. '--lf-secondary:var(--lf-color-secondary);'
		. '--lf-tertiary:var(--lf-color-tertiary);'
		. '--lf-muted:var(--lf-text-muted);'
		. '--lf-body-bg:var(--lf-surface-light);'
		. '}';
}

function lf_enqueue_branding_tokens(): void {
	$css = lf_branding_css();
	if ($css === '') {
		return;
	}
	if (wp_style_is('lf-design-system', 'enqueued')) {
		wp_add_inline_style('lf-design-system', $css);
		return;
	}
	wp_register_style('lf-branding-tokens', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-branding-tokens');
	wp_add_inline_style('lf-branding-tokens', $css);
}
add_action('wp_enqueue_scripts', 'lf_enqueue_branding_tokens', 6);
add_action('enqueue_block_editor_assets', 'lf_enqueue_branding_tokens', 6);
