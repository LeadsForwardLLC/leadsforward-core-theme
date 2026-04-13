<?php
/**
 * Fleet updates: wp-admin UI.
 *
 * @package LeadsForward_Core
 * @since 0.1.21
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_fleet_updates_admin_register_menu(): void {
	add_submenu_page(
		'lf-ops',
		__('Fleet Updates', 'leadsforward-core'),
		__('Fleet Updates', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-fleet-updates',
		'lf_fleet_updates_admin_render'
	);
}

function lf_fleet_updates_admin_render(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}

	$did = '';
	if (isset($_POST['lf_fleet_save']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce')) {
		update_option(LF_FLEET_OPT_API_BASE, esc_url_raw((string) wp_unslash($_POST['api_base'] ?? '')));
		update_option(LF_FLEET_OPT_SITE_ID, sanitize_text_field((string) wp_unslash($_POST['site_id'] ?? '')));
		update_option(LF_FLEET_OPT_TOKEN, sanitize_text_field((string) wp_unslash($_POST['token'] ?? '')));
		$decoded = json_decode((string) wp_unslash($_POST['pubkeys_json'] ?? ''), true);
		update_option(LF_FLEET_OPT_PUBKEYS, wp_json_encode(is_array($decoded) ? $decoded : []));
		$did = 'saved';
	}
	if (isset($_POST['lf_fleet_disconnect']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce')) {
		delete_option(LF_FLEET_OPT_API_BASE);
		delete_option(LF_FLEET_OPT_SITE_ID);
		delete_option(LF_FLEET_OPT_TOKEN);
		delete_option(LF_FLEET_OPT_PUBKEYS);
		delete_option(LF_FLEET_OPT_LAST);
		delete_site_transient(LF_FLEET_OFFER_TRANSIENT);
		$did = 'disconnected';
	}
	if (isset($_POST['lf_fleet_check_now']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce')) {
		if (function_exists('lf_fleet_check_for_update')) {
			lf_fleet_check_for_update();
		}
		$did = 'checked';
	}

	$api_base = (string) get_option(LF_FLEET_OPT_API_BASE, '');
	$site_id = (string) get_option(LF_FLEET_OPT_SITE_ID, '');
	$token = (string) get_option(LF_FLEET_OPT_TOKEN, '');
	$pubkeys = (string) get_option(LF_FLEET_OPT_PUBKEYS, '[]');
	$last_raw = (string) get_option(LF_FLEET_OPT_LAST, '');
	$connected = function_exists('lf_fleet_is_connected') ? lf_fleet_is_connected() : false;

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Fleet Updates', 'leadsforward-core') . '</h1>';
	echo '<p>' . esc_html__('Connect this site to the LeadsForward controller for secure, approved, automatic theme updates.', 'leadsforward-core') . '</p>';

	if ($did === 'saved') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'leadsforward-core') . '</p></div>';
	} elseif ($did === 'disconnected') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Disconnected.', 'leadsforward-core') . '</p></div>';
	} elseif ($did === 'checked') {
		echo '<div class="notice notice-info"><p>' . esc_html__('Checked for updates.', 'leadsforward-core') . '</p></div>';
	}

	echo '<div style="margin:14px 0; padding:12px; background:#fff; border:1px solid #dbe3ef; border-radius:10px;">';
	echo '<strong>' . esc_html__('Status:', 'leadsforward-core') . '</strong> ';
	echo $connected ? '<span style="color:#15803d;font-weight:700;">' . esc_html__('Connected', 'leadsforward-core') . '</span>' : '<span style="color:#b91c1c;font-weight:700;">' . esc_html__('Not connected', 'leadsforward-core') . '</span>';
	echo '</div>';

	echo '<form method="post">';
	wp_nonce_field('lf_fleet_updates_save', 'lf_fleet_nonce');
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="lf_fleet_api_base">' . esc_html__('Controller API base', 'leadsforward-core') . '</label></th><td><input type="url" id="lf_fleet_api_base" name="api_base" class="regular-text" value="' . esc_attr($api_base) . '" placeholder="https://theme.leadsforward.com" /></td></tr>';
	echo '<tr><th scope="row"><label for="lf_fleet_site_id">' . esc_html__('Site ID', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_fleet_site_id" name="site_id" class="regular-text" value="' . esc_attr($site_id) . '" /></td></tr>';
	echo '<tr><th scope="row"><label for="lf_fleet_token">' . esc_html__('Token', 'leadsforward-core') . '</label></th><td><input type="password" id="lf_fleet_token" name="token" class="regular-text" value="' . esc_attr($token) . '" autocomplete="off" /></td></tr>';
	echo '<tr><th scope="row"><label for="lf_fleet_pubkeys">' . esc_html__('Controller public keys (JSON)', 'leadsforward-core') . '</label></th><td><textarea id="lf_fleet_pubkeys" name="pubkeys_json" class="large-text code" rows="6" placeholder="{\"key_2026_01\":\"BASE64...\"}">' . esc_textarea($pubkeys) . '</textarea><p class="description">' . esc_html__('Map of key_id to base64 public key (32 bytes). Required for signature verification.', 'leadsforward-core') . '</p></td></tr>';
	echo '</table>';

	echo '<p>';
	echo '<button type="submit" class="button button-primary" name="lf_fleet_save" value="1">' . esc_html__('Save', 'leadsforward-core') . '</button> ';
	echo '<button type="submit" class="button" name="lf_fleet_check_now" value="1"' . ($connected ? '' : ' disabled') . '>' . esc_html__('Check now', 'leadsforward-core') . '</button> ';
	echo '<button type="submit" class="button button-link-delete" name="lf_fleet_disconnect" value="1"' . ($connected ? '' : ' disabled') . ' onclick="return confirm(\'Disconnect this site from fleet updates?\');">' . esc_html__('Disconnect', 'leadsforward-core') . '</button>';
	echo '</p>';

	if ($last_raw !== '') {
		echo '<h2 style="margin-top:18px;">' . esc_html__('Last result', 'leadsforward-core') . '</h2>';
		echo '<pre style="background:#0b1220;color:#e2e8f0;padding:12px;border-radius:10px;max-width:980px;overflow:auto;">' . esc_html($last_raw) . '</pre>';
	}

	echo '</form>';
	echo '</div>';
}

