<?php
/**
 * Site health: dashboard, SEO/performance/link checks, pre-launch report, QA log. Admin only.
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

lf_load_inc('site-health/common.php');
lf_load_inc('site-health/checks.php');
lf_load_inc('site-health/dashboard.php');
