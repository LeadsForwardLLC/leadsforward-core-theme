<?php
/**
 * Config tools: Export + Import combined UI.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_ops_config_render(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
	$success = isset($_GET['success']);
	$preview = [];
	$will_overwrite = [];
	$will_ignore = [];

	if (isset($_POST['lf_ops_import_preview']) && check_admin_referer('lf_ops_import_preview', 'lf_ops_import_nonce', false)) {
		$upload = $_FILES['lf_ops_import_file'] ?? null;
		if ($upload && !empty($upload['tmp_name']) && is_uploaded_file($upload['tmp_name'])) {
			$raw = file_get_contents($upload['tmp_name']);
			$data = $raw ? json_decode($raw, true) : null;
			if (is_array($data) && !empty($data['config'])) {
				$allowed = array_flip(lf_ops_exportable_option_keys());
				$labels = lf_ops_option_labels();
				foreach ($data['config'] as $key => $value) {
					if (isset($allowed[$key])) {
						$will_overwrite[$key] = ['label' => $labels[$key] ?? $key, 'new' => $value];
					} else {
						$will_ignore[$key] = $labels[$key] ?? $key;
					}
				}
				$preview = $data['config'];
				set_transient(LF_OPS_IMPORT_TRANSIENT . get_current_user_id(), $data, 600);
			} else {
				$error = 'invalid';
			}
		} else {
			$error = 'no_file';
		}
	}

	echo '<div class="wrap"><h1>' . esc_html__('Config', 'leadsforward-core') . '</h1>';
	echo '<p>' . esc_html__('Export or import your theme configuration, including the Quote Builder. Config files never include URLs, slugs, post IDs, or user data.', 'leadsforward-core') . '</p>';

	if ($success) {
		echo '<div class="notice notice-success"><p>' . esc_html__('Configuration imported successfully.', 'leadsforward-core') . '</p></div>';
	}
	if ($error === 'confirm') {
		echo '<div class="notice notice-error"><p>' . esc_html__('You must confirm that you understand this will overwrite existing configuration.', 'leadsforward-core') . '</p></div>';
	}
	if ($error === 'no_file') {
		echo '<div class="notice notice-error"><p>' . esc_html__('Please choose a JSON file to upload.', 'leadsforward-core') . '</p></div>';
	}
	if ($error === 'invalid' || $error === 'no_data') {
		echo '<div class="notice notice-error"><p>' . esc_html__('Invalid or empty config file. Use a JSON file exported from this screen.', 'leadsforward-core') . '</p></div>';
	}

	echo '<div class="card" style="max-width:980px;padding:16px;margin:16px 0;">';
	echo '<h2 style="margin-top:0;">' . esc_html__('Export', 'leadsforward-core') . '</h2>';
	echo '<p>' . esc_html__('Download a JSON file of business info, CTAs, Quote Builder, variation profile, homepage sections, and schema toggles.', 'leadsforward-core') . '</p>';
	echo '<form method="post" action="">';
	wp_nonce_field('lf_ops_export', 'lf_ops_export_nonce');
	echo '<p><input type="submit" name="lf_ops_download" class="button button-primary" value="' . esc_attr__('Download Config', 'leadsforward-core') . '" /></p>';
	echo '</form></div>';

	echo '<div class="card" style="max-width:980px;padding:16px;margin:16px 0;">';
	echo '<h2 style="margin-top:0;">' . esc_html__('Import', 'leadsforward-core') . '</h2>';
	if (empty($preview)) {
		echo '<p>' . esc_html__('Upload a JSON config file exported from another site. You will see a preview before applying.', 'leadsforward-core') . '</p>';
		echo '<form method="post" action="" enctype="multipart/form-data">';
		wp_nonce_field('lf_ops_import_preview', 'lf_ops_import_nonce');
		echo '<p><input type="file" name="lf_ops_import_file" accept=".json" required /> <input type="submit" name="lf_ops_import_preview" class="button button-primary" value="' . esc_attr__('Preview import', 'leadsforward-core') . '" /></p>';
		echo '</form>';
	} else {
		echo '<h3>' . esc_html__('Preview', 'leadsforward-core') . '</h3>';
		echo '<p><strong>' . esc_html__('Fields to be overwritten:', 'leadsforward-core') . '</strong></p><ul>';
		foreach ($will_overwrite as $key => $info) {
			$preview_val = is_scalar($info['new']) ? $info['new'] : wp_json_encode($info['new']);
			echo '<li>' . esc_html($info['label']) . ': <code>' . esc_html((string) $preview_val) . '</code></li>';
		}
		echo '</ul>';
		if (!empty($will_ignore)) {
			echo '<p><strong>' . esc_html__('Fields in file that will be ignored (not in allowlist):', 'leadsforward-core') . '</strong></p><ul>';
			foreach ($will_ignore as $key => $label) {
				echo '<li>' . esc_html($label) . ' (' . esc_html($key) . ')</li>';
			}
			echo '</ul>';
		}
		echo '<form method="post" action="">';
		wp_nonce_field('lf_ops_import_apply', 'lf_ops_import_nonce');
		echo '<p><label><input type="checkbox" name="lf_ops_import_confirm" value="1" required /> ' . esc_html__('I understand this will overwrite existing configuration.', 'leadsforward-core') . '</label></p>';
		echo '<p><input type="submit" name="lf_ops_import_apply" class="button button-primary" value="' . esc_attr__('Apply import', 'leadsforward-core') . '" /> <a href="' . esc_url(admin_url('admin.php?page=lf-ops-config')) . '" class="button">' . esc_html__('Cancel', 'leadsforward-core') . '</a></p>';
		echo '</form>';
	}
	echo '</div></div>';
}
