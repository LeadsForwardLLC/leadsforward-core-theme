<?php
/**
 * Bulk-safe operational tooling: export, import, bulk actions, audit log. Admin only.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

if (!is_admin()) {
	return;
}

lf_load_inc('ops/common.php');
lf_load_inc('ops/export.php');
lf_load_inc('ops/import.php');
lf_load_inc('ops/bulk-actions.php');
lf_load_inc('ops/audit-log.php');
lf_load_inc('ops/menu.php');
