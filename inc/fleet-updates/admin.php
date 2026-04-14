<?php
/**
 * Fleet updates: wp-admin UI.
 *
 * @package LeadsForward_Core
 * @since 0.1.23
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Map controller `reason` codes to short admin-facing text.
 */
function lf_fleet_updates_admin_reason_label(string $reason): string {
	if ($reason === '') {
		return '';
	}
	$labels = [
		'theme_slug_mismatch' => __('This site’s theme slug does not match the controller.', 'leadsforward-core'),
		'rollout_disabled' => __('Rollout is paused on the controller.', 'leadsforward-core'),
		'not_in_rollout_cohort' => __('This site is not checked for “selected sites” rollout.', 'leadsforward-core'),
		'not_in_rollout_tag' => __('This site does not have the rollout tag.', 'leadsforward-core'),
		'rollout_tag_unset' => __('Controller rollout tag is not set.', 'leadsforward-core'),
		'already_up_to_date' => __('Installed version is already current.', 'leadsforward-core'),
		'no_signing_keys' => __('Controller has no signing keys.', 'leadsforward-core'),
		'zip_build_failed' => __('Controller could not build the theme zip.', 'leadsforward-core'),
		'sign_failed' => __('Controller could not sign the release.', 'leadsforward-core'),
	];
	return $labels[$reason] ?? $reason;
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
		$bundle = isset($_POST['lf_fleet_bundle_json']) ? (string) wp_unslash($_POST['lf_fleet_bundle_json']) : '';
		if (trim($bundle) !== '' && function_exists('lf_fleet_import_connection_bundle')) {
			$imp = lf_fleet_import_connection_bundle($bundle);
			if (!$imp['ok']) {
				$did = $imp['message'] === 'invalid_json' ? 'bundle_bad_json' : 'bundle_bad_fields';
			} else {
				$did = 'bundle_imported';
				if (function_exists('lf_fleet_on_connection_updated')) {
					lf_fleet_on_connection_updated();
				}
			}
		} else {
			update_option(LF_FLEET_OPT_API_BASE, esc_url_raw((string) wp_unslash($_POST['api_base'] ?? '')));
			update_option(LF_FLEET_OPT_SITE_ID, sanitize_text_field((string) wp_unslash($_POST['site_id'] ?? '')));
			$tok_raw = (string) wp_unslash($_POST['token'] ?? '');
			$tok_raw = trim($tok_raw);
			$tok_raw = trim($tok_raw, " \t\n\r\0\x0B\"'");
			$tok_clean = preg_replace('/[^A-Za-z0-9\-\_\+\/=]/', '', $tok_raw);
			update_option(LF_FLEET_OPT_TOKEN, is_string($tok_clean) ? $tok_clean : $tok_raw);
			$decoded = json_decode((string) wp_unslash($_POST['pubkeys_json'] ?? ''), true);
			update_option(LF_FLEET_OPT_PUBKEYS, wp_json_encode(is_array($decoded) ? $decoded : []));
			$did = 'saved';
			if (function_exists('lf_fleet_is_connected') && lf_fleet_is_connected() && function_exists('lf_fleet_on_connection_updated')) {
				lf_fleet_on_connection_updated();
			}
		}
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
		$tags_in = isset($_POST['lf_fleet_new_site_tags']) ? (string) wp_unslash($_POST['lf_fleet_new_site_tags']) : '';
		$mint = lf_fleet_controller_mint_site($site_url, $label);
		$mint['tags'] = function_exists('lf_fleet_tags_from_string') ? lf_fleet_tags_from_string($tags_in) : [];
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
		$scope = in_array($scope_raw, ['off', 'all', 'selected', 'tag'], true) ? $scope_raw : 'off';
		update_option(LF_FLEET_CTRL_OPT_ROLLOUT_SCOPE, $scope);
		$rtag = isset($_POST['lf_fleet_rollout_tag']) ? sanitize_text_field((string) wp_unslash($_POST['lf_fleet_rollout_tag'])) : '';
		update_option(LF_FLEET_CTRL_OPT_ROLLOUT_TAG, strtolower(trim($rtag)));
		// Keep legacy option aligned for any external reads.
		update_option(LF_FLEET_CTRL_OPT_APPROVE_ALL, $scope === 'all' ? '1' : '0');

		$sites = lf_fleet_controller_sites();
		$posted = isset($_POST['lf_fleet_site_rollout']) && is_array($_POST['lf_fleet_site_rollout'])
			? wp_unslash($_POST['lf_fleet_site_rollout'])
			: [];
		$posted_tags = isset($_POST['lf_fleet_site_tags']) && is_array($_POST['lf_fleet_site_tags'])
			? wp_unslash($_POST['lf_fleet_site_tags'])
			: [];
		foreach ($sites as $sid => &$row) {
			if (!is_array($row)) {
				continue;
			}
			$key = (string) $sid;
			$row['rollout'] = isset($posted[$key]) && (string) $posted[$key] === '1';
			if (isset($posted_tags[$key]) && is_string($posted_tags[$key]) && function_exists('lf_fleet_tags_from_string')) {
				$row['tags'] = lf_fleet_tags_from_string($posted_tags[$key]);
			}
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
	if (isset($_POST['lf_fleet_controller_rotate_token']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce') && function_exists('lf_fleet_controller_sites') && function_exists('lf_fleet_controller_token_new')) {
		$rot = sanitize_text_field((string) wp_unslash((string) $_POST['lf_fleet_controller_rotate_token']));
		if ($rot !== '') {
			$sites = lf_fleet_controller_sites();
			if (isset($sites[$rot]) && is_array($sites[$rot])) {
				$sites[$rot]['token'] = lf_fleet_controller_token_new();
				if (function_exists('lf_fleet_controller_update_sites')) {
					lf_fleet_controller_update_sites($sites);
				}
				$keys_json = function_exists('lf_fleet_controller_pubkeys_json') ? lf_fleet_controller_pubkeys_json() : '{}';
				$bundle_out = wp_json_encode([
					'api_base' => home_url('/'),
					'site_id' => (string) $rot,
					'token' => (string) $sites[$rot]['token'],
					'public_keys_json' => json_decode((string) $keys_json, true) ?: new stdClass(),
				], JSON_PRETTY_PRINT);
				$controller_did = 'token_rotated';
			}
		}
	}
	if (isset($_POST['lf_fleet_disconnect']) && check_admin_referer('lf_fleet_updates_save', 'lf_fleet_nonce')) {
		if (function_exists('lf_fleet_clear_scheduled_events')) {
			lf_fleet_clear_scheduled_events();
		}
		delete_site_transient('lf_fleet_nearterm_ping');
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
		if (function_exists('lf_fleet_maybe_auto_update')) {
			lf_fleet_maybe_auto_update(true);
		}
		$did = 'checked';
	}

	$api_base = (string) get_option(LF_FLEET_OPT_API_BASE, '');
	$site_id = (string) get_option(LF_FLEET_OPT_SITE_ID, '');
	$token = (string) get_option(LF_FLEET_OPT_TOKEN, '');
	$pubkeys = (string) get_option(LF_FLEET_OPT_PUBKEYS, '[]');
	$connected = function_exists('lf_fleet_is_connected') ? lf_fleet_is_connected() : false;
	$is_controller = function_exists('lf_fleet_controller_enabled') ? lf_fleet_controller_enabled() : false;
	$summary = function_exists('lf_fleet_last_check_summary') ? lf_fleet_last_check_summary() : [
		'has_data' => false,
		'checked_at' => 0,
		'http_ok' => false,
		'status' => 0,
		'update_available' => false,
		'reason' => '',
		'controller_version' => '',
		'network_error' => '',
		'failures' => 0,
		'next_attempt_at' => 0,
		'last_upgrade_error' => '',
		'raw_json' => '',
	];
	$installed_ver = (string) wp_get_theme()->get('Version');

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Fleet Updates', 'leadsforward-core') . '</h1>';
	echo '<p class="description">' . esc_html__('Fleet sites pull updates from the controller on a schedule (about every 15 minutes when WordPress cron runs—usually when the site gets visits). Use Check now to contact the controller and, if an update is offered, install it immediately without waiting for cron.', 'leadsforward-core') . '</p>';

	if ($did === 'saved') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'leadsforward-core') . '</p></div>';
	} elseif ($did === 'bundle_imported') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Connection bundle imported. Settings were updated from the JSON you pasted.', 'leadsforward-core') . '</p></div>';
	} elseif ($did === 'bundle_bad_json') {
		echo '<div class="notice notice-error"><p>' . esc_html__('Could not parse the connection bundle as JSON.', 'leadsforward-core') . '</p></div>';
	} elseif ($did === 'bundle_bad_fields') {
		echo '<div class="notice notice-error"><p>' . esc_html__('Bundle is missing api_base, site_id, and token, or public_keys_json is not an object.', 'leadsforward-core') . '</p></div>';
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
	} elseif ($controller_did === 'token_rotated') {
		echo '<div class="notice notice-warning"><p>' . esc_html__('Token rotated. Paste the new bundle into the fleet site and save, or that site will stop authenticating.', 'leadsforward-core') . '</p></div>';
	} elseif ($controller_did === 'rollout_saved') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Rollout settings saved.', 'leadsforward-core') . '</p></div>';
	} elseif ($controller_did === 'site_removed') {
		echo '<div class="notice notice-success"><p>' . esc_html__('Site removed from controller.', 'leadsforward-core') . '</p></div>';
	}

	echo '<h2>' . esc_html__('This site (fleet client)', 'leadsforward-core') . '</h2>';
	echo '<div style="margin:0 0 16px; padding:14px; background:#fff; border:1px solid #dbe3ef; border-radius:10px; max-width:920px;">';
	echo '<p style="margin:0 0 8px;"><strong>' . esc_html__('Connection', 'leadsforward-core') . '</strong> ';
	echo $connected ? '<span style="color:#15803d;font-weight:700;">' . esc_html__('Connected', 'leadsforward-core') . '</span>' : '<span style="color:#b91c1c;font-weight:700;">' . esc_html__('Not connected', 'leadsforward-core') . '</span>';
	echo '</p>';
	if ($is_controller) {
		echo '<p style="margin:0 0 6px;color:#64748b;">' . esc_html__('Controller mode is enabled on this site. Client connection settings are not used here.', 'leadsforward-core') . '</p>';
	}
	echo '<p style="margin:0 0 6px;"><strong>' . esc_html__('Theme version on this site', 'leadsforward-core') . '</strong> <code>' . esc_html($installed_ver !== '' ? $installed_ver : '—') . '</code></p>';
	if ($summary['has_data'] && $summary['checked_at'] > 0) {
		echo '<p style="margin:0 0 6px;"><strong>' . esc_html__('Last controller check', 'leadsforward-core') . '</strong> ';
		echo esc_html(
			sprintf(
				/* translators: %s: human time difference */
				__('%s ago', 'leadsforward-core'),
				human_time_diff($summary['checked_at'], time())
			)
		);
		echo '</p>';
	} elseif ($connected) {
		echo '<p style="margin:0 0 6px;color:#64748b;">' . esc_html__('No check recorded yet. Use Check now.', 'leadsforward-core') . '</p>';
	}
	if ($connected && $summary['has_data']) {
		if (!$summary['http_ok'] && $summary['network_error'] !== '') {
			echo '<p style="margin:0 0 6px;color:#b91c1c;"><strong>' . esc_html__('Request error', 'leadsforward-core') . '</strong> ' . esc_html($summary['network_error']) . '</p>';
		} elseif ($summary['http_ok'] && $summary['update_available']) {
			echo '<p style="margin:0 0 6px;color:#15803d;"><strong>' . esc_html__('Update available', 'leadsforward-core') . '</strong> ';
			if ($summary['controller_version'] !== '') {
				echo esc_html(sprintf(/* translators: %s version */ __('Controller offers version %s.', 'leadsforward-core'), $summary['controller_version']));
			}
			echo '</p>';
		} elseif ($summary['http_ok'] && !$summary['update_available'] && $summary['reason'] !== '') {
			echo '<p style="margin:0 0 6px;"><strong>' . esc_html__('Controller response', 'leadsforward-core') . '</strong> ' . esc_html(lf_fleet_updates_admin_reason_label($summary['reason'])) . '</p>';
		}
		if ($summary['last_upgrade_error'] !== '') {
			echo '<p style="margin:0 0 6px;color:#b91c1c;"><strong>' . esc_html__('Auto-install issue', 'leadsforward-core') . '</strong> ' . esc_html($summary['last_upgrade_error']) . '</p>';
		}
		if ($summary['next_attempt_at'] > time()) {
			echo '<p style="margin:0;color:#64748b;">' . esc_html(
				sprintf(
					/* translators: %s: UTC datetime */
					__('Next auto-install retry no earlier than %s UTC.', 'leadsforward-core'),
					gmdate('Y-m-d H:i:s', $summary['next_attempt_at'])
				)
			) . '</p>';
		}
	}
	echo '</div>';

	echo '<form method="post">';
	wp_nonce_field('lf_fleet_updates_save', 'lf_fleet_nonce');
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="lf_fleet_bundle_json">' . esc_html__('Paste connection bundle', 'leadsforward-core') . '</label></th><td>';
	echo '<textarea id="lf_fleet_bundle_json" name="lf_fleet_bundle_json" class="large-text code" rows="5" placeholder="{ &quot;api_base&quot;: &quot;...&quot;, &quot;site_id&quot;: &quot;...&quot;, &quot;token&quot;: &quot;...&quot;, &quot;public_keys_json&quot;: {} }"></textarea>';
	echo '<p class="description">' . esc_html__('Optional: paste the full JSON from the controller. If this box is not empty when you save, it replaces the fields below.', 'leadsforward-core') . '</p></td></tr>';
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

	if ($summary['raw_json'] !== '') {
		echo '<details style="margin-top:12px;max-width:980px;"><summary style="cursor:pointer;">' . esc_html__('Technical details (raw last result)', 'leadsforward-core') . '</summary>';
		echo '<pre style="background:#0b1220;color:#e2e8f0;padding:12px;border-radius:10px;overflow:auto;margin-top:8px;">' . esc_html($summary['raw_json']) . '</pre>';
		echo '</details>';
	}

	echo '</form>';

	// Controller section (only shows when controller module is present).
	if (function_exists('lf_fleet_controller_enabled')) {
		echo '<hr style="margin:28px 0;" />';
		echo '<h2>' . esc_html__('Controller (use only on theme.leadsforward.com)', 'leadsforward-core') . '</h2>';
		echo '<p class="description">' . esc_html__('Mint Site IDs, signing keys, and rollout rules. Fleet sites use the section above; do not enable controller mode on normal fleet installs.', 'leadsforward-core') . '</p>';
		echo '<form method="post" style="margin-top:10px;">';
		wp_nonce_field('lf_fleet_updates_save', 'lf_fleet_nonce');
		echo '<label style="display:inline-flex;gap:8px;align-items:center;">';
		echo '<input type="checkbox" name="lf_fleet_controller_enabled" value="1" ' . checked($is_controller, true, false) . ' /> ';
		echo '<strong>' . esc_html__('Enable controller mode on this site', 'leadsforward-core') . '</strong>';
		echo '</label>';
		echo '<p style="margin-top:10px;"><button type="submit" class="button button-primary" name="lf_fleet_controller_save" value="1">' . esc_html__('Save controller settings', 'leadsforward-core') . '</button></p>';

		if ($is_controller) {
			$current_version = function_exists('lf_fleet_controller_current_version') ? lf_fleet_controller_current_version() : '';
			$approved_version = (string) get_option(LF_FLEET_CTRL_OPT_APPROVED_VERSION, '');
			$display_version = $current_version !== '' ? $current_version : $approved_version;
			$legacy_version = ($approved_version !== '' && $current_version !== '' && $approved_version !== $current_version) ? $approved_version : '';
			$rollout_scope = function_exists('lf_fleet_controller_rollout_scope') ? lf_fleet_controller_rollout_scope() : 'off';
			$rollout_tag_val = (string) get_option(LF_FLEET_CTRL_OPT_ROLLOUT_TAG, '');
			$sites = function_exists('lf_fleet_controller_sites') ? lf_fleet_controller_sites() : [];
			$n_reg = 0;
			$n_pick = 0;
			$n_tag_match = 0;
			$rt_for_count = function_exists('lf_fleet_controller_rollout_tag') ? lf_fleet_controller_rollout_tag() : '';
			foreach ($sites as $r) {
				if (!is_array($r)) {
					continue;
				}
				$n_reg++;
				if (!empty($r['rollout'])) {
					$n_pick++;
				}
				if ($rt_for_count !== '' && function_exists('lf_fleet_controller_site_matches_rollout_tag') && lf_fleet_controller_site_matches_rollout_tag($r, $rt_for_count)) {
					$n_tag_match++;
				}
			}
			if ($rollout_scope === 'off') {
				$sum_msg = sprintf(
					/* translators: %d: number of sites */
					__('Rollout is paused. %d site(s) registered.', 'leadsforward-core'),
					$n_reg
				);
			} elseif ($rollout_scope === 'all') {
				$sum_msg = sprintf(
					/* translators: 1: site count, 2: version */
					__('Rollout: all %1$d registered site(s) may receive version %2$s.', 'leadsforward-core'),
					$n_reg,
					$display_version
				);
			} elseif ($rollout_scope === 'selected') {
				$sum_msg = sprintf(
					/* translators: 1: checked count, 2: total, 3: version */
					__('Rollout: %1$d of %2$d site(s) checked for updates to version %3$s.', 'leadsforward-core'),
					$n_pick,
					$n_reg,
					$display_version
				);
			} elseif ($rollout_scope === 'tag') {
				$sum_msg = sprintf(
					/* translators: 1: tag, 2: matching count, 3: version */
					__('Rollout: tag “%1$s” matches %2$d site(s) for version %3$s.', 'leadsforward-core'),
					$rollout_tag_val !== '' ? $rollout_tag_val : '—',
					$n_tag_match,
					$display_version
				);
			} else {
				$sum_msg = __('Rollout scope is not recognized; save rollout settings again.', 'leadsforward-core');
			}
			echo '<div style="margin:16px 0; padding:12px 14px; background:#f0f6fc; border:1px solid #c3d9e8; border-radius:8px; max-width:920px;"><strong>' . esc_html__('Rollout summary', 'leadsforward-core') . '</strong> ';
			echo esc_html($sum_msg) . '</div>';

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
			echo '<tr><th scope="row"><label for="lf_fleet_new_site_tags">' . esc_html__('Tags (optional)', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_fleet_new_site_tags" name="lf_fleet_new_site_tags" class="regular-text" placeholder="beta, staging" /><p class="description">' . esc_html__('Comma or space separated. Used for tag-based rollout (e.g. stable, beta).', 'leadsforward-core') . '</p></td></tr>';
			echo '</table>';
			echo '<p><button type="submit" class="button button-primary" name="lf_fleet_controller_add_site" value="1">' . esc_html__('Create Site ID + Token bundle', 'leadsforward-core') . '</button></p>';

			echo '<h3 style="margin-top:18px;">' . esc_html__('Rollout (push)', 'leadsforward-core') . '</h3>';
			echo '<p class="description">' . esc_html__('The controller only serves the theme version it is currently running. Choose which registered sites may receive that update.', 'leadsforward-core') . '</p>';
			echo '<table class="form-table" role="presentation">';
			echo '<tr><th scope="row">' . esc_html__('Controller version (running)', 'leadsforward-core') . '</th><td><input type="text" class="regular-text" value="' . esc_attr($display_version) . '" readonly />';
			if ($legacy_version !== '') {
				echo '<p class="description" style="margin:6px 0 0;">' . esc_html(sprintf(__('Legacy approved version saved in options: %s (not used for update offers).', 'leadsforward-core'), $legacy_version)) . '</p>';
			}
			echo '</td></tr>';
			echo '<tr><th scope="row">' . esc_html__('Who gets updates', 'leadsforward-core') . '</th><td>';
			echo '<fieldset><label style="display:block;margin:0 0 6px;"><input type="radio" name="lf_fleet_rollout_scope" value="off" ' . checked($rollout_scope, 'off', false) . ' /> ' . esc_html__('Nobody (rollout paused)', 'leadsforward-core') . '</label>';
			echo '<label style="display:block;margin:0 0 6px;"><input type="radio" name="lf_fleet_rollout_scope" value="all" ' . checked($rollout_scope, 'all', false) . ' /> ' . esc_html__('All registered sites', 'leadsforward-core') . '</label>';
			echo '<label style="display:block;margin:0 0 6px;"><input type="radio" name="lf_fleet_rollout_scope" value="selected" ' . checked($rollout_scope, 'selected', false) . ' /> ' . esc_html__('Only sites checked below (“Include in rollout”)', 'leadsforward-core') . '</label>';
			echo '<label style="display:block;margin:0 0 6px;"><input type="radio" name="lf_fleet_rollout_scope" value="tag" ' . checked($rollout_scope, 'tag', false) . ' /> ' . esc_html__('Only sites whose tags include:', 'leadsforward-core') . ' ';
			echo '<input type="text" name="lf_fleet_rollout_tag" class="regular-text" value="' . esc_attr($rollout_tag_val) . '" placeholder="stable" style="max-width:220px;" /></label>';
			echo '</fieldset><p class="description">' . esc_html__('Tag matching is case-insensitive. Set tags per site in the table below, then save rollout settings.', 'leadsforward-core') . '</p></td></tr>';
			echo '</table>';
			echo '<p><button type="submit" class="button button-primary" name="lf_fleet_controller_rollout_save" value="1">' . esc_html__('Save rollout settings', 'leadsforward-core') . '</button></p>';

			if ($bundle_out !== '') {
				echo '<h4>' . esc_html__('Copy/paste bundle', 'leadsforward-core') . '</h4>';
				echo '<pre style="background:#0b1220;color:#e2e8f0;padding:12px;border-radius:10px;max-width:980px;overflow:auto;">' . esc_html($bundle_out) . '</pre>';
			}

			if ($sites !== []) {
				echo '<h3 style="margin-top:18px;">' . esc_html__('Connected sites (heartbeat)', 'leadsforward-core') . '</h3>';
				echo '<p class="description">' . esc_html__('For “selected” rollout, use the checkboxes. For “tag” rollout, edit Tags and save rollout settings. Rotate token invalidates the old token until the fleet site gets the new bundle.', 'leadsforward-core') . '</p>';
				echo '<table class="widefat striped" style="max-width:1200px;"><thead><tr>';
				echo '<th>' . esc_html__('Include', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('Tags', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('Label', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('URL', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('Site ID', 'leadsforward-core') . '</th>';
				echo '<th>' . esc_html__('Version', 'leadsforward-core') . '</th>';
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
					$tags_cell = '';
					if (function_exists('lf_fleet_controller_site_tags_list')) {
						$tags_cell = implode(', ', lf_fleet_controller_site_tags_list($row));
					}
					echo '<tr>';
					echo '<td><label><input type="checkbox" name="lf_fleet_site_rollout[' . esc_attr($sid_esc) . ']" value="1" ' . checked($in_rollout, true, false) . ' /></label></td>';
					echo '<td><input type="text" class="regular-text" name="lf_fleet_site_tags[' . esc_attr($sid_esc) . ']" value="' . esc_attr($tags_cell) . '" style="width:100%;max-width:160px;" placeholder="stable" /></td>';
					echo '<td>' . esc_html($lab !== '' ? $lab : '—') . '</td>';
					echo '<td>' . ($url !== '' ? '<code>' . esc_html($url) . '</code>' : '—') . '</td>';
					echo '<td><code>' . esc_html($sid_esc) . '</code></td>';
					echo '<td>' . esc_html($ver !== '' ? $ver : '—') . '</td>';
					echo '<td>' . esc_html($seen > 0 ? gmdate('Y-m-d H:i:s', $seen) . ' UTC' : '—') . '</td>';
					echo '<td>';
					echo '<button type="submit" class="button button-small" name="lf_fleet_controller_rotate_token" value="' . esc_attr($sid_esc) . '" onclick="return confirm(\'Rotate token? The fleet site must paste the new bundle.\');">' . esc_html__('Rotate token', 'leadsforward-core') . '</button> ';
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

