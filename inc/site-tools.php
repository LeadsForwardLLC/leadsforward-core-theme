<?php
/**
 * Optional built-in site tools (no plugin required).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_site_tools_enabled(string $key, bool $default = false): bool {
	$val = get_option($key, $default ? '1' : '0');
	return $val === '1' || $val === 1 || $val === true;
}

// 1) Hide admin bar on frontend.
add_filter('show_admin_bar', static function ($show): bool {
	if (is_admin()) {
		return (bool) $show;
	}
	if (lf_site_tools_enabled('lf_tools_hide_admin_bar', false)) {
		return false;
	}
	return (bool) $show;
}, 20);

// 2) Classic editor (disable block editor) for all post types.
add_filter('use_block_editor_for_post_type', static function ($use, $post_type): bool {
	if (!lf_site_tools_enabled('lf_tools_classic_editor', false)) {
		return (bool) $use;
	}
	// Do not disable block editor in wp-admin for attachments.
	if ((string) $post_type === 'attachment') {
		return (bool) $use;
	}
	return false;
}, 20, 2);

