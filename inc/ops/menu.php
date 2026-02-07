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
	// Parent: slug lf-ops so first submenu with same slug (Setup) is the default — no redirect, avoids "headers already sent"
	add_menu_page(
		__('LeadsForward', 'leadsforward-core'),
		__('LeadsForward', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops',
		'lf_wizard_render_page',
		'dashicons-admin-generic',
		59
	);

	// 1. Setup — same slug as parent so clicking "LeadsForward" shows this; only this item highlights when on page=lf-ops
	add_submenu_page(
		'lf-ops',
		__('Setup', 'leadsforward-core'),
		__('Setup', 'leadsforward-core'),
		'edit_theme_options',
		'lf-ops',
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
	// 3. Export Config (own slug so it doesn’t highlight when parent is clicked)
	add_submenu_page(
		'lf-ops',
		__('Export Config', 'leadsforward-core'),
		__('Export Config', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-export',
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
