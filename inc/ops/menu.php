<?php
/**
 * LeadsForward parent menu and submenu registration. Admin only.
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
	add_menu_page(
		__('LeadsForward', 'leadsforward-core'),
		__('LeadsForward', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops',
		'lf_ops_export_render', // Default first page
		'dashicons-admin-generic',
		59
	);
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
	add_submenu_page(
		'lf-ops',
		__('Homepage', 'leadsforward-core'),
		__('Homepage', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-homepage-settings',
		'lf_homepage_admin_render'
	);
}
