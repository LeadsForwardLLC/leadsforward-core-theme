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
add_action('admin_menu', 'lf_health_register_legacy_redirect', 12);

/**
 * Old bookmarks to admin.php?page=lf-site-health still work (hidden submenu).
 */
function lf_health_register_legacy_redirect(): void {
	add_submenu_page(
		null,
		__('Site Health', 'leadsforward-core'),
		'',
		LF_HEALTH_CAP,
		'lf-site-health',
		'lf_health_legacy_redirect'
	);
}

function lf_health_legacy_redirect(): void {
	wp_safe_redirect(admin_url('admin.php?page=lf-seo&tab=health'));
	exit;
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
	wp_safe_redirect(admin_url('admin.php?page=lf-seo&tab=health&report=1'));
	exit;
}

/**
 * Site Health body for the SEO & Site Health admin screen (no outer .wrap).
 */
function lf_health_render_embedded_ui(): void {
	if (!current_user_can(LF_HEALTH_CAP)) {
		return;
	}
	$report = isset($_GET['report']) && $_GET['report'] === '1';
	$last = lf_health_get_last_result();

	if (function_exists('lf_admin_render_quality_summary_strip')) {
		lf_admin_render_quality_summary_strip('health');
	}

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

	// Pre-launch button (explicit admin.php target so POST works from every screen)
	echo '<h2>' . esc_html__('Pre-launch automated check', 'leadsforward-core') . '</h2>';
	echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '">';
	echo '<input type="hidden" name="page" value="lf-seo" />';
	echo '<input type="hidden" name="tab" value="health" />';
	wp_nonce_field('lf_health_prelaunch', 'lf_health_prelaunch_nonce');
	echo '<p><input type="submit" name="lf_health_prelaunch" class="button button-primary" value="' . esc_attr__('Run pre-launch check', 'leadsforward-core') . '" /></p>';
	echo '</form>';
	echo '<p class="description">' . esc_html__('Runs dashboard, SEO, on-page score scan, performance hints, and internal link checks. Results appear below and update “Last site health run” in the snapshot. Nothing deploys automatically.', 'leadsforward-core') . '</p>';

	if (function_exists('lf_health_render_manual_qa_checklist')) {
		lf_health_render_manual_qa_checklist();
	}

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
		'dashboard'   => __('Dashboard & integrations', 'leadsforward-core'),
		'seo'         => __('SEO integrity', 'leadsforward-core'),
		'onpage'      => __('On-page SEO depth', 'leadsforward-core'),
		'performance' => __('Performance', 'leadsforward-core'),
		'links'       => __('Internal links', 'leadsforward-core'),
	];
	foreach (['dashboard', 'seo', 'onpage', 'performance', 'links'] as $cat) {
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

/**
 * Human checklist for launch (not scored by automation). Print / work through before go-live.
 */
function lf_health_render_manual_qa_checklist(): void {
	$docs = admin_url('admin.php?page=lf-theme-docs');
	$seo = admin_url('admin.php?page=lf-seo&tab=settings');
	echo '<div class="lf-health-qa-checklist" style="max-width:920px;margin:24px 0;padding:16px 20px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:10px;">';
	echo '<h2 style="margin-top:0;">' . esc_html__('Focused pre-launch QA checklist', 'leadsforward-core') . '</h2>';
	echo '<p class="description">' . esc_html__('Work through this list after the automated check passes or when you are close to launch. Check items off in your own tracker.', 'leadsforward-core') . '</p>';
	echo '<ol style="margin:12px 0 0 1.25rem;line-height:1.65;">';
	$items = [
		__('Homepage: one clear H1, hero CTA works (quote / phone / form as configured), trust section shows real reviews.', 'leadsforward-core'),
		__('Contact: form delivers to the right inbox; phone click-to-call; address/map match Google Business Profile.', 'leadsforward-core'),
		__('Every money page (services, service areas, key landing pages) has a unique meta title (≈30–60 chars) and meta description (≈120–160 chars) in the SEO meta box.', 'leadsforward-core'),
		__('Primary keyword set per page; at least one contextual internal link to a related service or area in body or Page Builder content.', 'leadsforward-core'),
		__('Images: no decorative-only alts; team and project photos describe what they show; logo marked appropriately.', 'leadsforward-core'),
		__('Legal: Privacy Policy and Terms match your business; footer links resolve.', 'leadsforward-core'),
		__('404 page branded; sitemap.xml loads; robots / noindex only where intended (archives, search if configured).', 'leadsforward-core'),
		__('Mobile: tap targets, sticky header/CTA, and Core Web Vitals spot-check on 3G throttling.', 'leadsforward-core'),
		__('Analytics: GTM or gtag fires on key templates (use Tag Assistant or network filter).', 'leadsforward-core'),
		__('Staging vs production: correct domain in schema, canonicals, and Search Console property.', 'leadsforward-core'),
	];
	foreach ($items as $text) {
		echo '<li>' . esc_html($text) . '</li>';
	}
	echo '</ol>';
	echo '<p style="margin:16px 0 0;"><a class="button" href="' . esc_url($docs) . '">' . esc_html__('Open Theme Docs', 'leadsforward-core') . '</a> ';
	echo '<a class="button" href="' . esc_url($seo) . '">' . esc_html__('SEO settings', 'leadsforward-core') . '</a></p>';
	echo '</div>';
}
