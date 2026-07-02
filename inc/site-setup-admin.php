<?php
/**
 * Site Setup admin — Airtable sync and template build (writer-first path, no n8n).
 *
 * @package LeadsForward_Core
 * @since 0.1.171
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'lf_site_setup_register_admin_menu', 11);

function lf_site_setup_register_admin_menu(): void {
	if (!function_exists('lf_ai_studio_render_site_setup_page')) {
		return;
	}
	$cap = defined('LF_OPS_CAP') ? LF_OPS_CAP : 'edit_theme_options';
	$slug = defined('LF_SITE_SETUP_ADMIN_SLUG') ? LF_SITE_SETUP_ADMIN_SLUG : 'lf-site-setup';
	add_submenu_page(
		'lf-ops',
		__('Site Setup', 'leadsforward-core'),
		__('Site Setup', 'leadsforward-core'),
		$cap,
		$slug,
		'lf_ai_studio_render_site_setup_page'
	);
}
