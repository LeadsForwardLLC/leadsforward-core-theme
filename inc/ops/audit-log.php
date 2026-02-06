<?php
/**
 * Audit log viewer. Admin-only. Who ran what, when, previous values.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_ops_audit_render(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$log = lf_ops_get_audit_log();
	echo '<div class="wrap"><h1>' . esc_html__('Audit Log', 'leadsforward-core') . '</h1>';
	echo '<p>' . esc_html__('Recent bulk and config operations. Admin-visible only.', 'leadsforward-core') . '</p>';
	if (empty($log)) {
		echo '<p>' . esc_html__('No entries yet.', 'leadsforward-core') . '</p></div>';
		return;
	}
	echo '<table class="widefat striped fixed"><thead><tr><th>' . esc_html__('Time', 'leadsforward-core') . '</th><th>' . esc_html__('User', 'leadsforward-core') . '</th><th>' . esc_html__('Action', 'leadsforward-core') . '</th><th>' . esc_html__('Details', 'leadsforward-core') . '</th><th>' . esc_html__('Previous values', 'leadsforward-core') . '</th></tr></thead><tbody>';
	$action_labels = [
		'config_import'       => __('Config import', 'leadsforward-core'),
		'bulk_variation_profile' => __('Variation profile', 'leadsforward-core'),
		'bulk_cta_sitewide'   => __('CTA site-wide', 'leadsforward-core'),
		'bulk_schema_toggles' => __('Schema toggles', 'leadsforward-core'),
		'bulk_rebuild_linking' => __('Rebuild linking', 'leadsforward-core'),
	];
	foreach ($log as $entry) {
		$time = isset($entry['time']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $entry['time']) : '';
		$user_id = $entry['user_id'] ?? 0;
		$user = $user_id ? get_userdata($user_id) : null;
		$user_name = $user ? $user->display_name : ('#' . $user_id);
		$action = $entry['action'] ?? '';
		$action_display = $action_labels[$action] ?? $action;
		$details = $entry['details'] ?? [];
		$previous = $entry['previous'] ?? [];
		$details_str = is_array($details) ? wp_json_encode($details, JSON_UNESCAPED_SLASHES) : (string) $details;
		$previous_str = is_array($previous) ? wp_json_encode($previous, JSON_UNESCAPED_SLASHES) : (string) $previous;
		echo '<tr><td>' . esc_html($time) . '</td><td>' . esc_html($user_name) . '</td><td>' . esc_html($action_display) . '</td><td><pre style="margin:0;white-space:pre-wrap;max-width:240px;">' . esc_html($details_str) . '</pre></td><td><pre style="margin:0;white-space:pre-wrap;max-width:240px;">' . esc_html($previous_str) . '</pre></td></tr>';
	}
	echo '</tbody></table></div>';
}
