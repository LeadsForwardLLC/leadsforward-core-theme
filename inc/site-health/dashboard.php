<?php
/**
 * Site Health admin page: dashboard, pre-launch report, run handler. No frontend scripts.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_init', 'lf_health_handle_prelaunch');
add_action('admin_menu', 'lf_health_menu', 11);

function lf_health_menu(): void {
	add_submenu_page(
		'lf-ops',
		__('Site Health', 'leadsforward-core'),
		__('Site Health', 'leadsforward-core'),
		LF_HEALTH_CAP,
		'lf-site-health',
		'lf_health_render_page'
	);
}

function lf_health_handle_prelaunch(): void {
	if (!isset($_POST['lf_health_prelaunch']) || !current_user_can(LF_HEALTH_CAP)) {
		return;
	}
	check_admin_referer('lf_health_prelaunch', 'lf_health_prelaunch_nonce');

	$groups = lf_health_prelaunch_checks();
	$all = [];
	$blockers = 0;
	$warnings = 0;
	foreach ($groups as $category => $checks) {
		foreach ($checks as $c) {
			$c['category'] = $category;
			$all[] = $c;
			if (($c['status'] ?? '') === lf_health_status_fail()) {
				$blockers++;
			} elseif (($c['status'] ?? '') === lf_health_status_warning()) {
				$warnings++;
			}
		}
	}
	$result = [
		'time'      => time(),
		'checks'    => $all,
		'blockers'  => $blockers,
		'warnings'  => $warnings,
	];
	lf_health_save_result($result);
	lf_health_log_run(
		['blockers' => $blockers, 'warnings' => $warnings, 'total' => count($all)],
		$blockers,
		$warnings
	);
	wp_safe_redirect(admin_url('admin.php?page=lf-site-health&report=1'));
	exit;
}

function lf_health_render_page(): void {
	if (!current_user_can(LF_HEALTH_CAP)) {
		return;
	}
	$report = isset($_GET['report']) && $_GET['report'] === '1';
	$last = lf_health_get_last_result();

	echo '<div class="wrap"><h1>' . esc_html__('Site Health', 'leadsforward-core') . '</h1>';

	// Dashboard: quick checks (green / yellow / red)
	$dashboard = lf_health_dashboard_checks();
	echo '<h2>' . esc_html__('Status', 'leadsforward-core') . '</h2>';
	echo '<ul class="lf-health-dashboard" style="list-style:none; margin-left:0;">';
	foreach ($dashboard as $c) {
		$status = $c['status'] ?? 'warning';
		$color = $status === lf_health_status_pass() ? '#00a32a' : ($status === lf_health_status_fail() ? '#d63638' : '#dba617');
		$dot = '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:' . esc_attr($color) . ';margin-right:8px;" aria-hidden="true"></span>';
		$msg = esc_html($c['message'] ?? '');
		$fix = '';
		if (!empty($c['fix_link'])) {
			$fix = ' <a href="' . esc_url($c['fix_link']) . '">' . esc_html__('Fix', 'leadsforward-core') . '</a>';
		}
		echo '<li style="margin-bottom:6px;">' . $dot . '<strong>' . esc_html($c['label'] ?? '') . '</strong>: ' . $msg . $fix . '</li>';
	}
	echo '</ul>';

	// Pre-launch button
	echo '<h2>' . esc_html__('Pre-Launch Check', 'leadsforward-core') . '</h2>';
	echo '<form method="post" action="">';
	wp_nonce_field('lf_health_prelaunch', 'lf_health_prelaunch_nonce');
	echo '<p><input type="submit" name="lf_health_prelaunch" class="button button-primary" value="' . esc_attr__('Run Pre-Launch Check', 'leadsforward-core') . '" /></p>';
	echo '</form>';
	echo '<p class="description">' . esc_html__('Runs all validations (SEO, performance, internal links). Nothing deploys automatically.', 'leadsforward-core') . '</p>';

	// Report (after run or from last result)
	if ($report && $last !== null) {
		lf_health_render_report($last);
	} elseif ($last !== null && !$report) {
		echo '<h2>' . esc_html__('Last pre-launch report', 'leadsforward-core') . '</h2>';
		echo '<p>' . esc_html__('Run again to refresh.', 'leadsforward-core') . '</p>';
		lf_health_render_report($last);
	}

	// Log
	$log = get_option(LF_HEALTH_LOG_OPTION, []);
	if (is_array($log) && !empty($log)) {
		echo '<h2>' . esc_html__('QA audit trail', 'leadsforward-core') . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Time', 'leadsforward-core') . '</th><th>' . esc_html__('User', 'leadsforward-core') . '</th><th>' . esc_html__('Blockers', 'leadsforward-core') . '</th><th>' . esc_html__('Warnings', 'leadsforward-core') . '</th></tr></thead><tbody>';
		foreach (array_slice($log, 0, 15) as $entry) {
			$time = isset($entry['time']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $entry['time']) : '';
			$uid = $entry['user_id'] ?? 0;
			$user = $uid ? get_userdata($uid) : null;
			$uname = $user ? $user->display_name : ('#' . $uid);
			echo '<tr><td>' . esc_html($time) . '</td><td>' . esc_html($uname) . '</td><td>' . (int) ($entry['blockers'] ?? 0) . '</td><td>' . (int) ($entry['warnings'] ?? 0) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}

function lf_health_render_report(array $result): void {
	$blockers = (int) ($result['blockers'] ?? 0);
	$warnings = (int) ($result['warnings'] ?? 0);
	$time = isset($result['time']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $result['time']) : '';

	echo '<h2>' . esc_html__('Pre-launch report', 'leadsforward-core') . '</h2>';
	echo '<p><strong>' . esc_html__('Summary', 'leadsforward-core') . '</strong>: ' . esc_html($time) . ' — ';
	if ($blockers > 0) {
		echo '<span style="color:#d63638;">' . sprintf(esc_html(_n('%d blocker', '%d blockers', $blockers, 'leadsforward-core')), $blockers) . '</span>';
	}
	if ($warnings > 0) {
		echo ($blockers > 0 ? ', ' : '') . '<span style="color:#dba617;">' . sprintf(esc_html(_n('%d warning', '%d warnings', $warnings, 'leadsforward-core')), $warnings) . '</span>';
	}
	if ($blockers === 0 && $warnings === 0) {
		echo '<span style="color:#00a32a;">' . esc_html__('All checks passed.', 'leadsforward-core') . '</span>';
	}
	echo '</p>';

	$checks = $result['checks'] ?? [];
	$by_cat = [];
	foreach ($checks as $c) {
		$cat = $c['category'] ?? 'other';
		$by_cat[$cat][] = $c;
	}
	$labels = [
		'dashboard'   => __('Dashboard', 'leadsforward-core'),
		'seo'         => __('SEO integrity', 'leadsforward-core'),
		'performance' => __('Performance', 'leadsforward-core'),
		'links'       => __('Internal links', 'leadsforward-core'),
	];
	foreach (['dashboard', 'seo', 'performance', 'links'] as $cat) {
		if (empty($by_cat[$cat])) {
			continue;
		}
		echo '<h3>' . esc_html($labels[$cat] ?? $cat) . '</h3>';
		echo '<ul class="lf-health-report" style="list-style:none; margin-left:0;">';
		foreach ($by_cat[$cat] as $c) {
			$status = $c['status'] ?? 'warning';
			$color = $status === lf_health_status_pass() ? '#00a32a' : ($status === lf_health_status_fail() ? '#d63638' : '#dba617');
			$dot = '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:' . esc_attr($color) . ';margin-right:8px;" aria-hidden="true"></span>';
			$msg = esc_html($c['message'] ?? '');
			$fix = '';
			if (!empty($c['fix_link'])) {
				$fix = ' <a href="' . esc_url($c['fix_link']) . '">' . esc_html__('Fix', 'leadsforward-core') . '</a>';
			}
			echo '<li style="margin-bottom:6px;">' . $dot . '<strong>' . esc_html($c['label'] ?? '') . '</strong>: ' . $msg . $fix . '</li>';
		}
		echo '</ul>';
	}
}
