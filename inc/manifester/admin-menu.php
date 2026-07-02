<?php
/**
 * Manifester admin menu (separate from theme-builder tools; future plugin entry).
 *
 * @package LeadsForward_Manifester
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'lf_manifester_register_admin_menu', 11);

function lf_manifester_register_admin_menu(): void {
	if (!function_exists('lf_ai_studio_render_page')) {
		return;
	}
	$cap = defined('LF_OPS_CAP') ? LF_OPS_CAP : 'edit_theme_options';
	$slug = defined('LF_MANIFEST_ADMIN_SLUG') ? LF_MANIFEST_ADMIN_SLUG : 'lf-manifest';
	add_submenu_page(
		'lf-ops',
		__('AI Manifester (n8n)', 'leadsforward-core'),
		__('AI Manifester (n8n)', 'leadsforward-core'),
		$cap,
		$slug,
		'lf_ai_studio_render_page'
	);
}
