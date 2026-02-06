<?php
/**
 * Config import: upload JSON, preview, confirm, apply. Safe mode; only allowed fields.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_OPS_IMPORT_TRANSIENT = 'lf_ops_import_payload_';

add_action('admin_init', 'lf_ops_import_handle');

function lf_ops_import_handle(): void {
	if (!isset($_POST['lf_ops_import_apply']) || !current_user_can(LF_OPS_CAP)) {
		return;
	}
	check_admin_referer('lf_ops_import_apply', 'lf_ops_import_nonce');
	if (empty($_POST['lf_ops_import_confirm']) || $_POST['lf_ops_import_confirm'] !== '1') {
		wp_safe_redirect(admin_url('admin.php?page=lf-ops-import&error=confirm'));
		exit;
	}
	$transient_key = LF_OPS_IMPORT_TRANSIENT . get_current_user_id();
	$data = get_transient($transient_key);
	if (!is_array($data) || empty($data['config'])) {
		wp_safe_redirect(admin_url('admin.php?page=lf-ops-import&error=invalid'));
		exit;
	}
	delete_transient($transient_key);
	$allowed = array_flip(lf_ops_exportable_option_keys());
	$previous = [];
	foreach ($data['config'] as $key => $value) {
		if (!isset($allowed[$key])) {
			continue;
		}
		if (function_exists('get_field')) {
			$previous[$key] = get_field($key, 'option');
		}
		if (function_exists('update_field')) {
			update_field($key, $value, 'option');
		}
	}
	lf_ops_audit_log('config_import', ['keys_updated' => array_keys($previous)], $previous);
	wp_safe_redirect(admin_url('admin.php?page=lf-ops-import&success=1'));
	exit;
}

function lf_ops_import_render(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
	$success = isset($_GET['success']);
	$preview = [];
	$will_overwrite = [];
	$will_ignore = [];
	$stored_json = '';

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

	echo '<div class="wrap"><h1>' . esc_html__('Import Config', 'leadsforward-core') . '</h1>';
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
		echo '<div class="notice notice-error"><p>' . esc_html__('Invalid or empty config file. Use a JSON file exported from Export Config.', 'leadsforward-core') . '</p></div>';
	}

	if (empty($preview)) {
		echo '<p>' . esc_html__('Upload a JSON config file exported from another site. You will see a preview before applying.', 'leadsforward-core') . '</p>';
		echo '<form method="post" action="" enctype="multipart/form-data">';
		wp_nonce_field('lf_ops_import_preview', 'lf_ops_import_nonce');
		echo '<p><input type="file" name="lf_ops_import_file" accept=".json" required /> <input type="submit" name="lf_ops_import_preview" class="button button-primary" value="' . esc_attr__('Preview import', 'leadsforward-core') . '" /></p>';
		echo '</form>';
	} else {
		echo '<h2>' . esc_html__('Preview', 'leadsforward-core') . '</h2>';
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
		echo '<p><input type="submit" name="lf_ops_import_apply" class="button button-primary" value="' . esc_attr__('Apply import', 'leadsforward-core') . '" /> <a href="' . esc_url(admin_url('admin.php?page=lf-ops-import')) . '" class="button">' . esc_html__('Cancel', 'leadsforward-core') . '</a></p>';
		echo '</form>';
	}
	echo '</div>';
}
