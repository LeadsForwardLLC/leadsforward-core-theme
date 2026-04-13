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
	$bundle_out = '';
	$controller_did = '';
	if (isset($_POST['lf_fleet_save']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce')) {
		update_option(LF_FLEET_OPT_API_BASE, esc_url_raw((string) wp_unslash($_POST['api_base'] ?? '')));
		update_option(LF_FLEET_OPT_SITE_ID, sanitize_text_field((string) wp_unslash($_POST['site_id'] ?? '')));
		$tok_raw = (string) wp_unslash($_POST['token'] ?? '');
		$tok_raw = trim($tok_raw);
		// Allow URL-safe base64 or base64; strip only whitespace and quotes.
		$tok_raw = trim($tok_raw, " \t\n\r\0\x0B\"'");
		$tok_clean = preg_replace('/[^A-Za-z0-9\-\_\+\/=]/', '', $tok_raw);
		update_option(LF_FLEET_OPT_TOKEN, is_string($tok_clean) ? $tok_clean : $tok_raw);
		$decoded = json_decode((string) wp_unslash($_POST['pubkeys_json'] ?? ''), true);
		update_option(LF_FLEET_OPT_PUBKEYS, wp_json_encode(is_array($decoded) ? $decoded : []));
		$did = 'saved';
	}
	// Controller mode (theme.leadsforward.com): enable + mint site bundles + keys.
	if (isset($_POST['lf_fleet_controller_save']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce') && function_exists('lf_fleet_controller_enabled')) {
		$enable = isset($_POST['lf_fleet_controller_enabled']) ? '1' : '0';
		update_option(LF_FLEET_CTRL_OPT_ENABLED, $enable);
		if ($enable !== '1') {
			update_option(LF_FLEET_CTRL_OPT_REWRITE_FLUSHED, '0');
		}
		$controller_did = 'controller_saved';
	}
	if (isset($_POST['lf_fleet_controller_generate_key']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce') && function_exists('lf_fleet_controller_generate_ed25519_keypair')) {
		$kp = lf_fleet_controller_generate_ed25519_keypair();
		if (!empty($kp['ok'])) {
			$keys = function_exists('lf_fleet_controller_keys') ? lf_fleet_controller_keys() : [];
			$keys[(string) $kp['kid']] = ['public' => (string) $kp['public'], 'private' => (string) $kp['private'], 'created_at' => time()];
			if (function_exists('lf_fleet_controller_update_keys')) {
				lf_fleet_controller_update_keys($keys);
			}
			$controller_did = 'key_created';
		} else {
			$controller_did = 'key_failed';
		}
	}
	if (isset($_POST['lf_fleet_controller_add_site']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce') && function_exists('lf_fleet_controller_mint_site')) {
		$site_url = isset($_POST['lf_fleet_new_site_url']) ? (string) wp_unslash($_POST['lf_fleet_new_site_url']) : '';
		$label = isset($_POST['lf_fleet_new_site_label']) ? (string) wp_unslash($_POST['lf_fleet_new_site_label']) : '';
		$mint = lf_fleet_controller_mint_site($site_url, $label);
		$sites = function_exists('lf_fleet_controller_sites') ? lf_fleet_controller_sites() : [];
		$sites[(string) $mint['site_id']] = $mint;
		if (function_exists('lf_fleet_controller_update_sites')) {
			lf_fleet_controller_update_sites($sites);
		}
		$keys_json = function_exists('lf_fleet_controller_pubkeys_json') ? lf_fleet_controller_pubkeys_json() : '{}';
		$bundle_out = wp_json_encode([
			'api_base' => home_url('/'),
			'site_id' => (string) $mint['site_id'],
			'token' => (string) $mint['token'],
			'public_keys_json' => json_decode((string) $keys_json, true) ?: new stdClass(),
		], JSON_PRETTY_PRINT);
		$controller_did = 'site_created';
	}
	if (isset($_POST['lf_fleet_controller_rollout_save']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce') && function_exists('lf_fleet_controller_sites')) {
		$scope_raw = isset($_POST['lf_fleet_rollout_scope']) ? sanitize_text_field((string) wp_unslash($_POST['lf_fleet_rollout_scope'])) : 'off';
		$scope = in_array($scope_raw, ['off', 'all', 'selected'], true) ? $scope_raw : 'off';
		update_option(LF_FLEET_CTRL_OPT_ROLLOUT_SCOPE, $scope);
		// Keep legacy option aligned for any external reads.
		update_option(LF_FLEET_CTRL_OPT_APPROVE_ALL, $scope === 'all' ? '1' : '0');

		$sites = lf_fleet_controller_sites();
		$posted = isset($_POST['lf_fleet_site_rollout']) && is_array($_POST['lf_fleet_site_rollout'])
			? wp_unslash($_POST['lf_fleet_site_rollout'])
			: [];
		foreach ($sites as $sid => &$row) {
			if (!is_array($row)) {
				continue;
			}
			$key = (string) $sid;
			$row['rollout'] = isset($posted[$key]) && (string) $posted[$key] === '1';
		}
		unset($row);
		if (function_exists('lf_fleet_controller_update_sites')) {
			lf_fleet_controller_update_sites($sites);
		}
		$controller_did = 'rollout_saved';
	}
	if (isset($_POST['lf_fleet_controller_remove_site']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce') && function_exists('lf_fleet_controller_sites')) {
		$rm = sanitize_text_field((string) wp_unslash((string) $_POST['lf_fleet_controller_remove_site']));
		if ($rm !== '') {
			$sites = lf_fleet_controller_sites();
			if (isset($sites[$rm])) {
				unset($sites[$rm]);
				if (function_exists('lf_fleet_controller_update_sites')) {
					lf_fleet_controller_update_sites($sites);
				}
				$controller_did = 'site_removed';
			}
		}
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
		if (function_exists('lf_fleet_send_heartbeat')) {
			lf_fleet_send_heartbeat();
		}
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
	if ($controller_did === 'controller_saved') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Controller settings saved.', 'leadsforward-core') . '</p></div>';
	} elseif ($controller_did === 'key_created') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Signing key created.', 'leadsforward-core') . '</p></div>';
	} elseif ($controller_did === 'key_failed') {
		echo '<div class="notice notice-error"><p>' . esc_html__('Signing key creation failed (libsodium unavailable).', 'leadsforward-core') . '</p></div>';
	} elseif ($controller_did === 'site_created') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Site credentials created. Copy the bundle below into the fleet site.', 'leadsforward-core') . '</p></div>';
	} elseif ($controller_did === 'rollout_saved') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Rollout settings saved.', 'leadsforward-core') . '</p></div>';
	} elseif ($controller_did === 'site_removed') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Site removed from controller.', 'leadsforward-core') . '</p></div>';
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

	// Controller section (only shows when controller module is present).
	if (function_exists('lf_fleet_controller_enabled')) {
		$is_controller = lf_fleet_controller_enabled();
		echo '<hr style="margin:24px 0;" />';
		echo '<h2>' . esc_html__('Controller mode (theme.leadsforward.com)', 'leadsforward-core') . '</h2>';
		echo '<p>' . esc_html__('Enable this only on the controller. It mints Site IDs + tokens and receives heartbeat/update checks at /api/v1/.', 'leadsforward-core') . '</p>';
		echo '<form method="post" style="margin-top:10px;">';
		wp_nonce_field('lf_fleet_updates_save', 'lf_fleet_nonce');
		echo '<label style="display:inline-flex;gap:8px;align-items:center;">';
		echo '<input type="checkbox" name="lf_fleet_controller_enabled" value="1" ' . checked($is_controller, true, false) . ' /> ';
		echo '<strong>' . esc_html__('Enable controller mode on this site', 'leadsforward-core') . '</strong>';
		echo '</label>';
		echo '<p style="margin-top:10px;"><button type="submit" class="button button-primary" name="lf_fleet_controller_save" value="1">' . esc_html__('Save controller settings', 'leadsforward-core') . '</button></p>';

		if ($is_controller) {
			$rollout_scope = function_exists('lf_fleet_controller_rollout_scope') ? lf_fleet_controller_rollout_scope() : 'off';
			$approved_version = (string) get_option(LF_FLEET_CTRL_OPT_APPROVED_VERSION, '');
			if ($approved_version === '' && function_exists('lf_fleet_controller_current_version')) {
				$approved_version = lf_fleet_controller_current_version();
			}
			$keys_json = function_exists('lf_fleet_controller_pubkeys_json') ? lf_fleet_controller_pubkeys_json() : '{}';
			echo '<h3 style="margin-top:18px;">' . esc_html__('Signing keys', 'leadsforward-core') . '</h3>';
			echo '<p><button type="submit" class="button" name="lf_fleet_controller_generate_key" value="1">' . esc_html__('Generate new Ed25519 keypair', 'leadsforward-core') . '</button></p>';
			echo '<p class="description">' . esc_html__('Public keys are shared to fleet sites. Private keys stay on controller to sign release artifacts.', 'leadsforward-core') . '</p>';
			echo '<pre style="background:#0b1220;color:#e2e8f0;padding:12px;border-radius:10px;max-width:980px;overflow:auto;">' . esc_html((string) $keys_json) . '</pre>';

			echo '<h3 style="margin-top:18px;">' . esc_html__('Register a fleet site (Site ID + Token)', 'leadsforward-core') . '</h3>';
			echo '<p class="description">' . esc_html__('This creates a unique Site ID + Token bundle you paste into the fleet site. This is separate from signing keys (used for release verification).', 'leadsforward-core') . '</p>';
			echo '<table class="form-table" role="presentation">';
			echo '<tr><th scope="row"><label for="lf_fleet_new_site_url">' . esc_html__('Fleet site URL (optional)', 'leadsforward-core') . '</label></th><td><input type="url" id="lf_fleet_new_site_url" name="lf_fleet_new_site_url" class="regular-text" placeholder="https://example.com" /></td></tr>';
			echo '<tr><th scope="row"><label for="lf_fleet_new_site_label">' . esc_html__('Label (optional)', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_fleet_new_site_label" name="lf_fleet_new_site_label" class="regular-text" placeholder="My Site" /></td></tr>';
			echo '</table>';
			echo '<p><button type="submit" class="button button-primary" name="lf_fleet_controller_add_site" value="1">' . esc_html__('Create Site ID + Token bundle', 'leadsforward-core') . '</button></p>';

			echo '<h3 style="margin-top:18px;">' . esc_html__('Rollout (push)', 'leadsforward-core') . '</h3>';
			echo '<p class="description">' . esc_html__('The controller only serves the theme version it is currently running. Choose which registered sites may receive that update.', 'leadsforward-core') . '</p>';
			echo '<table class="form-table" role="presentation">';
			echo '<tr><th scope="row">' . esc_html__('Controller version', 'leadsforward-core') . '</th><td><input type="text" class="regular-text" value="' . esc_attr($approved_version) . '" readonly /></td></tr>';
			echo '<tr><th scope="row">' . esc_html__('Who gets updates', 'leadsforward-core') . '</th><td>';
			echo '<fieldset><label style="display:block;margin:0 0 6px;"><input type="radio" name="lf_fleet_rollout_scope" value="off" ' . checked($rollout_scope, 'off', false) . ' /> ' . esc_html__('Nobody (rollout paused)', 'leadsforward-core') . '</label>';
			echo '<label style="display:block;margin:0 0 6px;"><input type="radio" name="lf_fleet_rollout_scope" value="all" ' . checked($rollout_scope, 'all', false) . ' /> ' . esc_html__('All registered sites', 'leadsforward-core') . '</label>';
			echo '<label style="display:block;margin:0 0 6px;"><input type="radio" name="lf_fleet_rollout_scope" value="selected" ' . checked($rollout_scope, 'selected', false) . ' /> ' . esc_html__('Only sites checked below (“Include in rollout”)', 'leadsforward-core') . '</label>';
			echo '</fieldset></td></tr>';
			echo '</table>';
			echo '<p><button type="submit" class="button button-primary" name="lf_fleet_controller_rollout_save" value="1">' . esc_html__('Save rollout settings', 'leadsforward-core') . '</button></p>';

			if ($bundle_out !== '') {
				echo '<h4>' . esc_html__('Copy/paste bundle', 'leadsforward-core') . '</h4>';
				echo '<pre style="background:#0b1220;color:#e2e8f0;padding:12px;border-radius:10px;max-width:980px;overflow:auto;">' . esc_html($bundle_out) . '</pre>';
			}

			$sites = function_exists('lf_fleet_controller_sites') ? lf_fleet_controller_sites() : [];
			if ($sites !== []) {
				echo '<h3 style="margin-top:18px;">' . esc_html__('Connected sites (heartbeat)', 'leadsforward-core') . '</h3>';
				echo '<p class="description">' . esc_html__('When rollout scope is “selected”, only checked sites receive the update. Save rollout settings after changing checkboxes.', 'leadsforward-core') . '</p>';
				echo '<table class="widefat striped" style="max-width:1100px;"><thead><tr>';
				echo '<th>' . esc_html__('Include in rollout', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('Label', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('URL', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('Site ID', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('Current version', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('Last seen', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('Actions', 'leadsforward-core') . '</th>';
				echo '</tr></thead><tbody>';
				foreach ($sites as $sid => $row) {
					if (!is_array($row)) {
						continue;
					}
					$lab = (string) ($row['label'] ?? '');
					$url = (string) ($row['site_url'] ?? '');
					$ver = (string) ($row['current_version'] ?? '');
					$seen = (int) ($row['last_seen_at'] ?? 0);
					$in_rollout = !empty($row['rollout']);
					$sid_esc = (string) $sid;
					echo '<tr>';
					echo '<td><label><input type="checkbox" name="lf_fleet_site_rollout[' . esc_attr($sid_esc) . ']" value="1" ' . checked($in_rollout, true, false) . ' /> ' . esc_html__('Yes', 'leadsforward-core') . '</label></td>';
					echo '<td>' . esc_html($lab !== '' ? $lab : '—') . '</td>';
					echo '<td>' . ($url !== '' ? '<code>' . esc_html($url) . '</code>' : '—') . '</td>';
					echo '<td><code>' . esc_html($sid_esc) . '</code></td>';
					echo '<td>' . esc_html($ver !== '' ? $ver : '—') . '</td>';
					echo '<td>' . esc_html($seen > 0 ? gmdate('Y-m-d H:i:s', $seen) . ' UTC' : '—') . '</td>';
					echo '<td>';
					echo '<button type="submit" class="button button-small" name="lf_fleet_controller_remove_site" value="' . esc_attr($sid_esc) . '" onclick="return confirm(\'Remove this site from controller?\');">' . esc_html__('Remove', 'leadsforward-core') . '</button>';
					echo '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}
		}
		echo '</form>';
	}

	echo '</div>';
}

