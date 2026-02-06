<?php
/**
 * Config export: ACF options, homepage sections, schema toggles, variation. JSON download. No URLs/slugs/IDs.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_init', 'lf_ops_export_handle_download');

function lf_ops_export_render(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html__('Export Config', 'leadsforward-core') . '</h1>';
	echo '<p>' . esc_html__('Download a JSON file of your theme configuration: business info, CTAs, variation profile, homepage sections, and schema toggles. URLs, slugs, post IDs, and user data are never included.', 'leadsforward-core') . '</p>';
	echo '<form method="post" action="">';
	wp_nonce_field('lf_ops_export', 'lf_ops_export_nonce');
	echo '<p><input type="submit" name="lf_ops_download" class="button button-primary" value="' . esc_attr__('Download Config', 'leadsforward-core') . '" /></p>';
	echo '</form></div>';
}

function lf_ops_export_handle_download(): void {
	if (!isset($_POST['lf_ops_download']) || !current_user_can(LF_OPS_CAP)) {
		return;
	}
	check_admin_referer('lf_ops_export', 'lf_ops_export_nonce');

	$keys = lf_ops_exportable_option_keys();
	$data = [
		'schema_version' => 1,
		'exported_at'   => gmdate('c'),
		'config'        => [],
	];
	$wp_option_keys = function_exists('lf_ops_wp_option_keys') ? lf_ops_wp_option_keys() : [];
	if (function_exists('get_field')) {
		foreach ($keys as $key) {
			if (in_array($key, $wp_option_keys, true)) {
				$value = get_option($key, null);
			} else {
				$value = get_field($key, 'option');
			}
			if ($value !== null) {
				$data['config'][$key] = $value;
			}
		}
	}
	// Niche/profile metadata (no IDs)
	$data['meta'] = [
		'variation_profile' => $data['config']['variation_profile'] ?? '',
	];

	$json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	$filename = 'leadsforward-config-' . gmdate('Y-m-d-His') . '.json';
	header('Content-Type: application/json');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Cache-Control: no-cache');
	echo $json;
	exit;
}
