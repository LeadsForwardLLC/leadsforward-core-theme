<?php
/**
 * Global settings helpers (logo + header CTA).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_get_global_option(string $key, $default = null) {
	if (!function_exists('get_field')) {
		$opt = get_option('options_' . $key, $default);
		return $opt !== null ? $opt : $default;
	}
	foreach (['lf-global', 'options_lf_global', 'options_lf-global', 'option', 'options'] as $post_id) {
		$value = get_field($key, $post_id);
		if ($value !== null && $value !== false && $value !== '') {
			return $value;
		}
	}
	$opt = get_option('options_' . $key, $default);
	return $opt !== null ? $opt : $default;
}

function lf_maybe_hide_admin_bar(bool $show): bool {
	if (is_admin()) {
		return $show;
	}
	$hide = get_option('lf_hide_admin_bar', '0') === '1';
	return $hide ? false : $show;
}
add_filter('show_admin_bar', 'lf_maybe_hide_admin_bar', 20);
