<?php
/**
 * Site health: result structure, logging. Admin only. No frontend, no cron, no remote APIs.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_HEALTH_CAP = 'edit_theme_options';
const LF_HEALTH_LOG_OPTION = 'lf_site_health_log';
const LF_HEALTH_LOG_MAX = 50;
const LF_HEALTH_RESULT_OPTION = 'lf_site_health_last_result';

/** Status: pass (green), warning (yellow), fail (red) */
function lf_health_status_pass(): string {
	return 'pass';
}
function lf_health_status_warning(): string {
	return 'warning';
}
function lf_health_status_fail(): string {
	return 'fail';
}

/**
 * Log a pre-launch / health check run.
 */
function lf_health_log_run(array $summary, int $blockers = 0, int $warnings = 0): void {
	if (!current_user_can(LF_HEALTH_CAP)) {
		return;
	}
	$log = get_option(LF_HEALTH_LOG_OPTION, []);
	if (!is_array($log)) {
		$log = [];
	}
	array_unshift($log, [
		'user_id'   => get_current_user_id(),
		'time'      => time(),
		'blockers'  => $blockers,
		'warnings'  => $warnings,
		'summary'   => $summary,
	]);
	$log = array_slice($log, 0, LF_HEALTH_LOG_MAX);
	update_option(LF_HEALTH_LOG_OPTION, $log);
}

/**
 * Store last full result for dashboard display.
 */
function lf_health_save_result(array $result): void {
	update_option(LF_HEALTH_RESULT_OPTION, $result);
}

function lf_health_get_last_result(): ?array {
	$r = get_option(LF_HEALTH_RESULT_OPTION, null);
	return is_array($r) ? $r : null;
}
