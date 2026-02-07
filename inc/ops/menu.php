<?php
/**
 * LeadsForward parent menu and submenu registration. Admin only.
 * Order: Setup → Homepage → Export/Import/Bulk/Audit → Reset (dev).
 * Site Health is added by inc/site-health/dashboard.php at priority 11.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'lf_ops_register_menu', 10);

function lf_ops_register_menu(): void {
	// Parent: first submenu below becomes the default when clicking "LeadsForward"
	add_menu_page(
		__('LeadsForward', 'leadsforward-core'),
		__('LeadsForward', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops',
		'lf_ops_default_render',
		'dashicons-admin-generic',
		59
	);

	// 1. Setup (one-time wizard) — default landing
	add_submenu_page(
		'lf-ops',
		__('Setup', 'leadsforward-core'),
		__('Setup', 'leadsforward-core'),
		'edit_theme_options',
		'lf-setup-wizard',
		'lf_wizard_render_page'
	);
	// 2. Homepage (sections, business info)
	add_submenu_page(
		'lf-ops',
		__('Homepage', 'leadsforward-core'),
		__('Homepage', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-homepage-settings',
		'lf_homepage_admin_render'
	);
	// 3. Export Config (duplicate slug so parent link works for existing bookmarks)
	add_submenu_page(
		'lf-ops',
		__('Export Config', 'leadsforward-core'),
		__('Export Config', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops',
		'lf_ops_export_render'
	);
	add_submenu_page(
		'lf-ops',
		__('Import Config', 'leadsforward-core'),
		__('Import Config', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-import',
		'lf_ops_import_render'
	);
	add_submenu_page(
		'lf-ops',
		__('Bulk Actions', 'leadsforward-core'),
		__('Bulk Actions', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-bulk',
		'lf_ops_bulk_render'
	);
	add_submenu_page(
		'lf-ops',
		__('Audit Log', 'leadsforward-core'),
		__('Audit Log', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-audit',
		'lf_ops_audit_render'
	);
	// 4. Reset site (dev only) — last, gated by lf_dev_reset_allowed()
	if (function_exists('lf_dev_reset_allowed') && lf_dev_reset_allowed()) {
		add_submenu_page(
			'lf-ops',
			__('Reset site (dev)', 'leadsforward-core'),
			__('Reset site (dev)', 'leadsforward-core'),
			'manage_options',
			'lf-dev-reset',
			'lf_dev_reset_render_page'
		);
	}
}

/**
 * Default page when clicking LeadsForward menu (redirect to Setup or Export).
 */
function lf_ops_default_render(): void {
	// Send to Setup if wizard not complete, else Export
	if (!get_option('lf_setup_wizard_complete', false)) {
		wp_safe_redirect(admin_url('admin.php?page=lf-setup-wizard'));
		exit;
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-ops'));
	exit;
}
