<?php
/**
 * Admin setup wizard UI. One-time flow: niche → NAP → services/areas → profile → generate.
 * Persists completion flag; never shows again after success.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_init', 'lf_wizard_handle_post');
add_action('admin_init', 'lf_wizard_handle_setup_settings');
add_action('admin_init', 'lf_wizard_handle_regen_legal');
add_action('admin_notices', 'lf_wizard_admin_notice');
add_action('after_switch_theme', 'lf_wizard_on_activation');

function lf_wizard_on_activation(): void {
	// Do not reset completion on re-activation. Only first install has no option.
	if (get_option('lf_setup_wizard_complete', null) === null) {
		update_option('lf_setup_wizard_complete', false);
	}
}

function lf_wizard_admin_notice(): void {
	if (get_option('lf_setup_wizard_complete', false)) {
		return;
	}
	$screen = get_current_screen();
	// Hide notice on any LeadsForward admin page (Setup lives under LeadsForward menu)
	if ($screen && strpos($screen->id, 'lf-ops') !== false) {
		return;
	}
	echo '<div class="notice notice-info"><p>' . sprintf(
		/* translators: %s: link to setup wizard */
		esc_html__('LeadsForward: Complete your site setup in one go. %s', 'leadsforward-core'),
		'<a href="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">' . esc_html__('Run setup wizard', 'leadsforward-core') . '</a>'
	) . '</p></div>';
}

function lf_wizard_handle_post(): void {
	if (!isset($_POST['lf_wizard_step']) || !current_user_can('edit_theme_options')) {
		return;
	}
	$step = (int) $_POST['lf_wizard_step'];
	if ($step === 5 && isset($_POST['lf_wizard_generate']) && check_admin_referer('lf_wizard_generate', 'lf_wizard_nonce')) {
		$allowed_embed = lf_wizard_allowed_map_embed();
		$address_street = isset($_POST['lf_business_address_street']) ? sanitize_text_field($_POST['lf_business_address_street']) : '';
		$address_city = isset($_POST['lf_business_address_city']) ? sanitize_text_field($_POST['lf_business_address_city']) : '';
		$address_state = isset($_POST['lf_business_address_state']) ? sanitize_text_field($_POST['lf_business_address_state']) : '';
		$address_zip = isset($_POST['lf_business_address_zip']) ? sanitize_text_field($_POST['lf_business_address_zip']) : '';
		$address_line2 = trim(implode(' ', array_filter([$address_city, $address_state, $address_zip])));
		$address_full = trim(implode(', ', array_filter([$address_street, $address_line2])));
		$primary_phone = isset($_POST['lf_business_phone_primary']) ? sanitize_text_field($_POST['lf_business_phone_primary']) : '';
		if ($primary_phone === '' && isset($_POST['lf_business_phone'])) {
			$primary_phone = sanitize_text_field($_POST['lf_business_phone']);
		}
		$tracking_phone = isset($_POST['lf_business_phone_tracking']) ? sanitize_text_field($_POST['lf_business_phone_tracking']) : '';
		$phone_display = isset($_POST['lf_business_phone_display']) && $_POST['lf_business_phone_display'] === 'tracking' ? 'tracking' : 'primary';
		$display_phone = $phone_display === 'tracking' && $tracking_phone !== '' ? $tracking_phone : $primary_phone;
		$service_area_type = isset($_POST['lf_business_service_area_type']) && $_POST['lf_business_service_area_type'] === 'service_area' ? 'service_area' : 'address';
		$lat_raw = isset($_POST['lf_business_geo_lat']) ? trim((string) $_POST['lf_business_geo_lat']) : '';
		$lng_raw = isset($_POST['lf_business_geo_lng']) ? trim((string) $_POST['lf_business_geo_lng']) : '';
		$lat = $lat_raw !== '' ? (float) $lat_raw : '';
		$lng = $lng_raw !== '' ? (float) $lng_raw : '';
		$category = isset($_POST['lf_business_category']) ? sanitize_text_field($_POST['lf_business_category']) : 'HomeAndConstructionBusiness';
		$allowed_categories = ['HomeAndConstructionBusiness', 'GeneralContractor', 'RoofingContractor', 'Plumber', 'HVACBusiness', 'LandscapingBusiness', 'LocalBusiness'];
		if (!in_array($category, $allowed_categories, true)) {
			$category = 'HomeAndConstructionBusiness';
		}
		$homepage_city = isset($_POST['lf_homepage_city']) ? sanitize_text_field($_POST['lf_homepage_city']) : '';
		$homepage_keyword_primary = isset($_POST['lf_homepage_keyword_primary']) ? sanitize_text_field($_POST['lf_homepage_keyword_primary']) : '';
		$homepage_keyword_secondary_raw = isset($_POST['lf_homepage_keyword_secondary']) ? sanitize_textarea_field($_POST['lf_homepage_keyword_secondary']) : '';
		$homepage_keyword_secondary = array_filter(array_map('sanitize_text_field', preg_split('/\r\n|\r|\n|,/', $homepage_keyword_secondary_raw)));
		if (!empty($_FILES['lf_homepage_keywords_file']) && is_array($_FILES['lf_homepage_keywords_file'])) {
			$file = $_FILES['lf_homepage_keywords_file'];
			if (isset($file['error']) && (int) $file['error'] === UPLOAD_ERR_OK && !empty($file['tmp_name'])) {
				$contents = file_get_contents($file['tmp_name']);
				if (is_string($contents) && trim($contents) !== '') {
					$from_file = preg_split('/\r\n|\r|\n|,/', $contents);
					$from_file = array_filter(array_map('sanitize_text_field', $from_file));
					$homepage_keyword_secondary = array_values(array_unique(array_merge($homepage_keyword_secondary, $from_file)));
				}
			}
		}
		$hero_variant = isset($_POST['lf_homepage_hero_variant']) ? sanitize_text_field($_POST['lf_homepage_hero_variant']) : '';
		$generate_now = !empty($_POST['lf_homepage_generate_now']);

		$data = [
			'niche_slug'                 => sanitize_text_field($_POST['lf_niche'] ?? ''),
			'business_name'              => sanitize_text_field($_POST['lf_business_name'] ?? ''),
			'business_legal_name'        => isset($_POST['lf_business_legal_name']) ? sanitize_text_field($_POST['lf_business_legal_name']) : '',
			'business_phone'             => $display_phone,
			'business_phone_primary'     => $primary_phone,
			'business_phone_tracking'    => $tracking_phone,
			'business_phone_display'     => $phone_display,
			'business_email'             => sanitize_email($_POST['lf_business_email'] ?? ''),
			'business_address'           => $address_full !== '' ? $address_full : sanitize_textarea_field($_POST['lf_business_address'] ?? ''),
			'business_address_street'    => $address_street,
			'business_address_city'      => $address_city,
			'business_address_state'     => $address_state,
			'business_address_zip'       => $address_zip,
			'business_service_area_type' => $service_area_type,
			'business_geo'               => ['lat' => $lat, 'lng' => $lng],
			'business_hours'             => sanitize_textarea_field($_POST['lf_business_hours'] ?? ''),
			'business_category'          => $category,
			'business_short_description' => sanitize_textarea_field($_POST['lf_business_short_description'] ?? ''),
			'business_gbp_url'           => esc_url_raw($_POST['lf_business_gbp_url'] ?? ''),
			'business_social_facebook'   => esc_url_raw($_POST['lf_business_social_facebook'] ?? ''),
			'business_social_instagram'  => esc_url_raw($_POST['lf_business_social_instagram'] ?? ''),
			'business_social_youtube'    => esc_url_raw($_POST['lf_business_social_youtube'] ?? ''),
			'business_social_linkedin'   => esc_url_raw($_POST['lf_business_social_linkedin'] ?? ''),
			'business_social_tiktok'     => esc_url_raw($_POST['lf_business_social_tiktok'] ?? ''),
			'business_social_x'          => esc_url_raw($_POST['lf_business_social_x'] ?? ''),
			'business_same_as'           => sanitize_textarea_field($_POST['lf_business_same_as'] ?? ''),
			'business_founding_year'     => sanitize_text_field($_POST['lf_business_founding_year'] ?? ''),
			'business_license_number'    => sanitize_text_field($_POST['lf_business_license_number'] ?? ''),
			'business_insurance_statement' => sanitize_textarea_field($_POST['lf_business_insurance_statement'] ?? ''),
			'business_place_id'          => sanitize_text_field($_POST['lf_business_place_id'] ?? ''),
			'business_place_name'        => sanitize_text_field($_POST['lf_business_place_name'] ?? ''),
			'business_place_address'     => sanitize_text_field($_POST['lf_business_place_address'] ?? ''),
			'business_map_embed'         => isset($_POST['lf_business_map_embed']) ? wp_kses(wp_unslash($_POST['lf_business_map_embed']), $allowed_embed) : '',
			'service_areas'              => lf_wizard_sanitize_areas($_POST['lf_service_areas'] ?? ''),
			'variation_profile_override' => sanitize_text_field($_POST['lf_variation_profile'] ?? ''),
			'homepage_city'              => $homepage_city,
			'homepage_hero_variant'      => $hero_variant,
		];
		$errors = [];
		if ($homepage_keyword_primary === '') {
			$errors[] = __('Primary homepage keyword is required.', 'leadsforward-core');
		}
		if (!empty($errors)) {
			$redirect = add_query_arg([
				'page' => 'lf-ops',
				'step' => 4,
				'errors' => 1,
				'msg' => implode(' ', $errors),
			], admin_url('admin.php'));
			wp_safe_redirect($redirect);
			exit;
		}
		$result = lf_run_setup($data);
		if (!empty($result['success'])) {
			// Ensure business info is saved for Global Settings → Business Entity
			if (function_exists('lf_update_business_info_value')) {
				lf_update_business_info_value('lf_business_name', $data['business_name'] ?? '');
				lf_update_business_info_value('lf_business_legal_name', $data['business_legal_name'] ?? '');
				lf_update_business_info_value('lf_business_phone_primary', $data['business_phone_primary'] ?? '');
				lf_update_business_info_value('lf_business_phone_tracking', $data['business_phone_tracking'] ?? '');
				lf_update_business_info_value('lf_business_phone_display', $data['business_phone_display'] ?? 'primary');
				lf_update_business_info_value('lf_business_phone', $data['business_phone'] ?? '');
				lf_update_business_info_value('lf_business_email', $data['business_email'] ?? '');
				lf_update_business_info_value('lf_business_address_street', $data['business_address_street'] ?? '');
				lf_update_business_info_value('lf_business_address_city', $data['business_address_city'] ?? '');
				lf_update_business_info_value('lf_business_address_state', $data['business_address_state'] ?? '');
				lf_update_business_info_value('lf_business_address_zip', $data['business_address_zip'] ?? '');
				lf_update_business_info_value('lf_business_address', $data['business_address'] ?? '');
				lf_update_business_info_value('lf_business_service_area_type', $data['business_service_area_type'] ?? 'address');
				lf_update_business_info_value('lf_business_geo', $data['business_geo'] ?? ['lat' => '', 'lng' => '']);
				if (array_key_exists('business_hours', $data) && $data['business_hours'] !== '') {
					lf_update_business_info_value('lf_business_hours', $data['business_hours']);
				}
				lf_update_business_info_value('lf_business_category', $data['business_category'] ?? 'HomeAndConstructionBusiness');
				lf_update_business_info_value('lf_business_short_description', $data['business_short_description'] ?? '');
				lf_update_business_info_value('lf_business_gbp_url', $data['business_gbp_url'] ?? '');
				lf_update_business_info_value('lf_business_social_facebook', $data['business_social_facebook'] ?? '');
				lf_update_business_info_value('lf_business_social_instagram', $data['business_social_instagram'] ?? '');
				lf_update_business_info_value('lf_business_social_youtube', $data['business_social_youtube'] ?? '');
				lf_update_business_info_value('lf_business_social_linkedin', $data['business_social_linkedin'] ?? '');
				lf_update_business_info_value('lf_business_social_tiktok', $data['business_social_tiktok'] ?? '');
				lf_update_business_info_value('lf_business_social_x', $data['business_social_x'] ?? '');
				lf_update_business_info_value('lf_business_same_as', $data['business_same_as'] ?? '');
				lf_update_business_info_value('lf_business_founding_year', $data['business_founding_year'] ?? '');
				lf_update_business_info_value('lf_business_license_number', $data['business_license_number'] ?? '');
				lf_update_business_info_value('lf_business_insurance_statement', $data['business_insurance_statement'] ?? '');
				lf_update_business_info_value('lf_business_place_id', $data['business_place_id'] ?? '');
				lf_update_business_info_value('lf_business_place_name', $data['business_place_name'] ?? '');
				lf_update_business_info_value('lf_business_place_address', $data['business_place_address'] ?? '');
				lf_update_business_info_value('lf_business_map_embed', $data['business_map_embed'] ?? '');
			}
			// Homepage config is applied during setup runner.
			if ($hero_variant !== '' && function_exists('lf_sections_hero_variant_options')) {
				$variants = array_keys(lf_sections_hero_variant_options());
				if (in_array($hero_variant, $variants, true)) {
					$config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
					if (!empty($config['hero']) && is_array($config['hero'])) {
						$config['hero']['variant'] = $hero_variant;
						update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
					}
				}
			}
			update_option('lf_homepage_keywords', [
				'primary' => $homepage_keyword_primary,
				'secondary' => array_values($homepage_keyword_secondary),
			], true);
			if ($homepage_city !== '') {
				update_option('lf_homepage_city', $homepage_city, true);
			}
			update_option('lf_setup_wizard_complete', true);
			if (!empty($result['ids']) && is_array($result['ids'])) {
				update_option('lf_wizard_created_ids', $result['ids']);
			}
			$redirect = admin_url('admin.php?page=lf-ops&done=1');
			if ($generate_now && function_exists('lf_ai_studio_run_homepage_generation')) {
				$gen = lf_ai_studio_run_homepage_generation();
				if (!empty($gen['error'])) {
					$redirect = add_query_arg('ai_error', rawurlencode((string) $gen['error']), $redirect);
				} else {
					$redirect = admin_url('admin.php?page=lf-homepage-settings');
					if (!empty($gen['job_id'])) {
						$redirect = add_query_arg('ai_job', (string) $gen['job_id'], $redirect);
					}
				}
			}
			wp_redirect($redirect);
			exit;
		}
		wp_redirect(admin_url('admin.php?page=lf-ops&step=5&errors=1&msg=' . urlencode(implode('; ', $result['errors']))));
		exit;
	}
}

function lf_wizard_handle_setup_settings(): void {
	if (!isset($_POST['lf_setup_settings_nonce']) || !current_user_can('edit_theme_options')) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_setup_settings_nonce'], 'lf_setup_settings')) {
		return;
	}
	$maps_key = isset($_POST['lf_maps_api_key']) ? sanitize_text_field(wp_unslash($_POST['lf_maps_api_key'])) : '';
	$maps_clear = !empty($_POST['lf_maps_api_key_clear']);
	if ($maps_clear) {
		delete_option('lf_maps_api_key');
	} elseif ($maps_key !== '') {
		update_option('lf_maps_api_key', $maps_key);
	}

	$openai_key = isset($_POST['lf_openai_api_key']) ? sanitize_text_field(wp_unslash($_POST['lf_openai_api_key'])) : '';
	$openai_clear = !empty($_POST['lf_openai_api_key_clear']);
	if ($openai_clear) {
		delete_option('lf_openai_api_key');
	} elseif ($openai_key !== '') {
		update_option('lf_openai_api_key', $openai_key);
	}

	$hide_bar = !empty($_POST['lf_hide_admin_bar']) ? '1' : '0';
	update_option('lf_hide_admin_bar', $hide_bar);

	wp_safe_redirect(admin_url('admin.php?page=lf-ops&settings_saved=1'));
	exit;
}

function lf_wizard_handle_regen_legal(): void {
	if (!isset($_POST['lf_regen_legal_nonce']) || !current_user_can('edit_theme_options')) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_regen_legal_nonce'], 'lf_regen_legal')) {
		return;
	}
	if (!function_exists('lf_wizard_regenerate_legal_pages')) {
		return;
	}
	$data = function_exists('lf_wizard_data_from_entity') ? lf_wizard_data_from_entity() : [];
	$result = lf_wizard_regenerate_legal_pages($data);
	$ok = !empty($result['success']);
	wp_safe_redirect(admin_url('admin.php?page=lf-ops&legal_regen=' . ($ok ? '1' : '0')));
	exit;
}

function lf_wizard_sanitize_areas($input): array {
	if (is_array($input)) {
		return array_filter(array_map('sanitize_text_field', $input));
	}
	$lines = array_filter(array_map('trim', explode("\n", (string) $input)));
	return array_map('sanitize_text_field', $lines);
}

function lf_wizard_allowed_map_embed(): array {
	return [
		'iframe' => [
			'src' => true,
			'width' => true,
			'height' => true,
			'style' => true,
			'loading' => true,
			'referrerpolicy' => true,
			'allowfullscreen' => true,
			'title' => true,
		],
	];
}

function lf_wizard_render_page(): void {
	$complete = (bool) get_option('lf_setup_wizard_complete', false);
	$settings_saved = isset($_GET['settings_saved']) && $_GET['settings_saved'] === '1';
	$reset_done = isset($_GET['reset_done']) && $_GET['reset_done'] === '1';
	$reset_error = isset($_GET['reset_error']) ? sanitize_text_field($_GET['reset_error']) : '';
	$legal_regen = isset($_GET['legal_regen']) ? sanitize_text_field($_GET['legal_regen']) : '';
	if ($complete && !isset($_GET['done'])) {
		echo '<div class="wrap"><h1>' . esc_html__('LeadsForward Setup', 'leadsforward-core') . '</h1>';
		if ($settings_saved) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'leadsforward-core') . '</p></div>';
		}
		if ($legal_regen === '1') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Legal pages regenerated.', 'leadsforward-core') . '</p></div>';
		} elseif ($legal_regen === '0') {
			echo '<div class="notice notice-error"><p>' . esc_html__('Legal pages could not be regenerated.', 'leadsforward-core') . '</p></div>';
		}
		if ($reset_done) {
			echo '<div class="notice notice-success"><p>' . esc_html__('Site reset complete. You can run the setup wizard again.', 'leadsforward-core') . '</p></div>';
		}
		if ($reset_error === 'confirm') {
			echo '<div class="notice notice-error"><p>' . esc_html__('You must type RESET exactly to confirm.', 'leadsforward-core') . '</p></div>';
		}
		lf_wizard_render_setup_settings_panel();
		echo '<p>' . esc_html__('Setup is already complete. Your site has the required pages, menus, and structure.', 'leadsforward-core') . '</p>';
		echo '<p><a href="' . esc_url(admin_url('admin.php?page=lf-ops&reset=1')) . '" class="button">' . esc_html__('Show wizard again', 'leadsforward-core') . '</a></p></div>';
		return;
	}
	if (isset($_GET['reset']) && current_user_can('edit_theme_options')) {
		delete_option('lf_setup_wizard_complete');
		wp_redirect(admin_url('admin.php?page=lf-ops'));
		exit;
	}
	if (isset($_GET['done'])) {
		echo '<div class="wrap"><h1>' . esc_html__('LeadsForward Setup', 'leadsforward-core') . '</h1>';
		$ai_error = isset($_GET['ai_error']) ? sanitize_text_field($_GET['ai_error']) : '';
		if ($settings_saved) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'leadsforward-core') . '</p></div>';
		}
		if ($legal_regen === '1') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Legal pages regenerated.', 'leadsforward-core') . '</p></div>';
		} elseif ($legal_regen === '0') {
			echo '<div class="notice notice-error"><p>' . esc_html__('Legal pages could not be regenerated.', 'leadsforward-core') . '</p></div>';
		}
		if ($reset_done) {
			echo '<div class="notice notice-success"><p>' . esc_html__('Site reset complete. You can run the setup wizard again.', 'leadsforward-core') . '</p></div>';
		}
		if ($reset_error === 'confirm') {
			echo '<div class="notice notice-error"><p>' . esc_html__('You must type RESET exactly to confirm.', 'leadsforward-core') . '</p></div>';
		}
		if ($ai_error) {
			echo '<div class="notice notice-error"><p>' . esc_html($ai_error) . '</p></div>';
		}
		lf_wizard_render_setup_settings_panel();
		echo '<p class="notice notice-success">' . esc_html__('Site setup complete. You can now customize Theme Options and edit pages.', 'leadsforward-core') . '</p>';
		echo '<p><a href="' . esc_url(get_permalink(get_option('page_on_front'))) . '" class="button button-primary">' . esc_html__('View site', 'leadsforward-core') . '</a> ';
		echo '<a href="' . esc_url(admin_url('admin.php?page=lf-global')) . '" class="button">' . esc_html__('Global Settings', 'leadsforward-core') . '</a></p></div>';
		return;
	}

	echo '<div class="wrap"><h1>' . esc_html__('LeadsForward Setup', 'leadsforward-core') . '</h1>';
	echo '<style>
		.wrap > h1 { margin-bottom: 6px; }
		.lf-setup-progress { display:flex; align-items:center; gap:12px; margin: 8px 0 16px; }
		.lf-setup-progress__bar { flex: 1; height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
		.lf-setup-progress__bar span { display:block; height: 100%; background: #3b82f6; }
		.lf-setup-step-title { margin: 12px 0 6px; }
		.lf-setup-help { color: #475569; margin: 0 0 12px; }
		.lf-setup-card { max-width: 980px; padding: 14px; margin: 10px 0; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; }
		.lf-setup-card--top { padding: 12px; margin: 6px 0 10px; border-left: 3px solid #3b82f6; }
		.lf-setup-progress { margin: 6px 0 12px; }
		.lf-setup-card h2 { margin-top: 0; }
	</style>';
	$step = isset($_GET['step']) ? max(1, min(5, (int) $_GET['step'])) : 1;
	$errors = isset($_GET['errors']) ? sanitize_text_field($_GET['msg'] ?? '') : '';
	if ($settings_saved) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'leadsforward-core') . '</p></div>';
	}
	if ($legal_regen === '1') {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Legal pages regenerated.', 'leadsforward-core') . '</p></div>';
	} elseif ($legal_regen === '0') {
		echo '<div class="notice notice-error"><p>' . esc_html__('Legal pages could not be regenerated.', 'leadsforward-core') . '</p></div>';
	}
	if ($reset_done) {
		echo '<div class="notice notice-success"><p>' . esc_html__('Site reset complete. You can run the setup wizard again.', 'leadsforward-core') . '</p></div>';
	}
	if ($reset_error === 'confirm') {
		echo '<div class="notice notice-error"><p>' . esc_html__('You must type RESET exactly to confirm.', 'leadsforward-core') . '</p></div>';
	}
	echo '<div class="lf-setup-card lf-setup-card--top">';
	echo '<h2 style="margin-top:0;">' . esc_html__('Setup Wizard + AI Studio', 'leadsforward-core') . '</h2>';
	echo '<p class="description">' . esc_html__('Complete the wizard to store business info and keywords. AI Studio uses these inputs for regeneration.', 'leadsforward-core') . '</p>';
	echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=lf-ops&step=' . $step)) . '#lf-setup-wizard">' . esc_html__('Continue setup wizard', 'leadsforward-core') . '</a> ';
	echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=lf-ai-studio')) . '">' . esc_html__('Open AI Studio (Advanced)', 'leadsforward-core') . '</a></p>';
	echo '</div>';
	if ($errors) {
		echo '<div class="notice notice-error"><p>' . esc_html($errors) . '</p></div>';
	}
	echo '<div id="lf-setup-wizard">';
	echo '<div class="lf-setup-progress"><strong>' . sprintf(esc_html__('Step %d of 5', 'leadsforward-core'), $step) . '</strong><div class="lf-setup-progress__bar"><span style="width:' . esc_attr((string) ($step * 20)) . '%;"></span></div></div>';

	$method = 'post';
	$action = admin_url('admin.php?page=lf-ops');
	$niche = isset($_GET['niche']) ? sanitize_text_field($_GET['niche']) : '';
	$profiles = ['a' => __('Clean + Minimal', 'leadsforward-core'), 'b' => __('Bold + High Contrast', 'leadsforward-core'), 'c' => __('Trust Heavy', 'leadsforward-core'), 'd' => __('Service Heavy', 'leadsforward-core'), 'e' => __('Offer/Promo Heavy', 'leadsforward-core')];

	if ($step === 1) {
		echo '<div class="lf-setup-card">';
		echo '<h2 class="lf-setup-step-title">' . esc_html__('Choose your industry', 'leadsforward-core') . '</h2>';
		echo '<p class="lf-setup-help">' . esc_html__('This sets your default services and homepage structure.', 'leadsforward-core') . '</p>';
		echo '<div class="lf-setup-card">';
		echo '<h2 class="lf-setup-step-title">' . esc_html__('Service areas', 'leadsforward-core') . '</h2>';
		echo '<p class="lf-setup-help">' . esc_html__('These locations power Service Area pages and the map section.', 'leadsforward-core') . '</p>';
		echo '<div class="lf-setup-card">';
		echo '<h2 class="lf-setup-step-title">' . esc_html__('Homepage & AI content', 'leadsforward-core') . '</h2>';
		echo '<p class="lf-setup-help">' . esc_html__('These inputs are sent to AI Studio to generate your homepage copy.', 'leadsforward-core') . '</p>';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">';
		echo '<input type="hidden" name="page" value="lf-ops" />';
		echo '<input type="hidden" name="step" value="2" />';
		echo '<table class="form-table"><tr><th scope="row">' . esc_html__('Industry', 'leadsforward-core') . '</th><td><select name="niche" required>';
		foreach (lf_get_niche_registry() as $slug => $n) {
			echo '<option value="' . esc_attr($slug) . '"' . selected($niche, $slug, false) . '>' . esc_html($n['name']) . '</option>';
		}
		echo '</select></td></tr></table>';
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Next', 'leadsforward-core') . '" /></p></form>';
		echo '</div>';
		echo '</div>';
	} elseif ($step === 2) {
		$niche = $niche ?: array_key_first(lf_get_niche_registry());
		// Defaults: schema-friendly format examples (pre-fill when empty so user sees how to format)
		$default_name = __('Quality Roofing of Sarasota', 'leadsforward-core');
		$default_phone = __('(941) 555-0123', 'leadsforward-core');
		$default_email = __('contact@yourbusiness.com', 'leadsforward-core');
		$default_street = __('123 Main Street', 'leadsforward-core');
		$default_city = __('Sarasota', 'leadsforward-core');
		$default_state = __('FL', 'leadsforward-core');
		$default_zip = __('34232', 'leadsforward-core');
		$default_hours = __("Mon–Fri 8am–6pm\nSat 9am–2pm", 'leadsforward-core');

		$allowed_embed = lf_wizard_allowed_map_embed();
		$bn = sanitize_text_field($_GET['lf_business_name'] ?? '');
		$bl = sanitize_text_field($_GET['lf_business_legal_name'] ?? '');
		$bp_primary = sanitize_text_field($_GET['lf_business_phone_primary'] ?? '');
		$bp_tracking = sanitize_text_field($_GET['lf_business_phone_tracking'] ?? '');
		$phone_display = sanitize_text_field($_GET['lf_business_phone_display'] ?? 'primary');
		$be = sanitize_email($_GET['lf_business_email'] ?? '');
		$street = sanitize_text_field($_GET['lf_business_address_street'] ?? '');
		$city = sanitize_text_field($_GET['lf_business_address_city'] ?? '');
		$home_city = sanitize_text_field($_GET['lf_homepage_city'] ?? '');
		$state = sanitize_text_field($_GET['lf_business_address_state'] ?? '');
		$zip = sanitize_text_field($_GET['lf_business_address_zip'] ?? '');
		$bh = sanitize_textarea_field($_GET['lf_business_hours'] ?? '');
		$category = sanitize_text_field($_GET['lf_business_category'] ?? 'HomeAndConstructionBusiness');
		$short_desc = sanitize_textarea_field($_GET['lf_business_short_description'] ?? '');
		$geo_lat = sanitize_text_field($_GET['lf_business_geo_lat'] ?? '');
		$geo_lng = sanitize_text_field($_GET['lf_business_geo_lng'] ?? '');
		$gbp_url = esc_url_raw($_GET['lf_business_gbp_url'] ?? '');
		$social_facebook = esc_url_raw($_GET['lf_business_social_facebook'] ?? '');
		$social_instagram = esc_url_raw($_GET['lf_business_social_instagram'] ?? '');
		$social_youtube = esc_url_raw($_GET['lf_business_social_youtube'] ?? '');
		$social_linkedin = esc_url_raw($_GET['lf_business_social_linkedin'] ?? '');
		$social_tiktok = esc_url_raw($_GET['lf_business_social_tiktok'] ?? '');
		$social_x = esc_url_raw($_GET['lf_business_social_x'] ?? '');
		$same_as = sanitize_textarea_field($_GET['lf_business_same_as'] ?? '');
		$founding_year = sanitize_text_field($_GET['lf_business_founding_year'] ?? '');
		$license_number = sanitize_text_field($_GET['lf_business_license_number'] ?? '');
		$insurance_statement = sanitize_textarea_field($_GET['lf_business_insurance_statement'] ?? '');
		$place_id = sanitize_text_field($_GET['lf_business_place_id'] ?? '');
		$place_name = sanitize_text_field($_GET['lf_business_place_name'] ?? '');
		$place_address = sanitize_text_field($_GET['lf_business_place_address'] ?? '');
		$map_embed = isset($_GET['lf_business_map_embed']) ? wp_kses(wp_unslash($_GET['lf_business_map_embed']), $allowed_embed) : '';
		$maps_api_key = get_option('lf_maps_api_key', '');
		$maps_api_key = is_string($maps_api_key) ? $maps_api_key : '';

		if ($bn === '') { $bn = $default_name; }
		if ($bp_primary === '') { $bp_primary = $default_phone; }
		if ($be === '') { $be = $default_email; }
		if ($street === '') { $street = $default_street; }
		if ($city === '') { $city = $default_city; }
		if ($home_city === '') { $home_city = $city; }
		if ($state === '') { $state = $default_state; }
		if ($zip === '') { $zip = $default_zip; }
		if ($bh === '') { $bh = $default_hours; }
		if ($phone_display !== 'tracking') { $phone_display = 'primary'; }
		$allowed_categories = ['HomeAndConstructionBusiness', 'GeneralContractor', 'RoofingContractor', 'Plumber', 'HVACBusiness', 'LandscapingBusiness', 'LocalBusiness'];
		if (!in_array($category, $allowed_categories, true)) {
			$category = 'HomeAndConstructionBusiness';
		}

		echo '<div class="lf-setup-card">';
		echo '<h2 class="lf-setup-step-title">' . esc_html__('Business details', 'leadsforward-core') . '</h2>';
		echo '<p class="lf-setup-help">' . esc_html__('Used for schema, contact info, and map display. You can edit later in Global Settings.', 'leadsforward-core') . '</p>';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '" data-maps-key="' . esc_attr($maps_api_key) . '">';
		echo '<input type="hidden" name="page" value="lf-ops" />';
		echo '<input type="hidden" name="step" value="3" />';
		echo '<input type="hidden" name="niche" value="' . esc_attr($niche) . '" />';
		echo '<p class="description">' . esc_html__('Used for schema, footer, and Map + NAP. Replace with your real business info—these show the recommended format.', 'leadsforward-core') . '</p>';
		echo '<table class="form-table">';
		echo '<tr><th scope="row"><label for="lf_business_name">' . esc_html__('Business name', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_business_name" name="lf_business_name" class="regular-text" value="' . esc_attr($bn) . '" required placeholder="' . esc_attr($default_name) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_legal_name">' . esc_html__('Legal business name (optional)', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_business_legal_name" name="lf_business_legal_name" class="regular-text" value="' . esc_attr($bl) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_phone_primary">' . esc_html__('Primary phone', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_business_phone_primary" name="lf_business_phone_primary" class="regular-text" value="' . esc_attr($bp_primary) . '" required placeholder="(XXX) XXX-XXXX" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_phone_tracking">' . esc_html__('Tracking phone (optional)', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_business_phone_tracking" name="lf_business_phone_tracking" class="regular-text" value="' . esc_attr($bp_tracking) . '" /></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Display phone', 'leadsforward-core') . '</th><td><select name="lf_business_phone_display"><option value="primary"' . selected($phone_display !== 'tracking', true, false) . '>' . esc_html__('Primary phone', 'leadsforward-core') . '</option><option value="tracking"' . selected($phone_display === 'tracking', true, false) . '>' . esc_html__('Tracking phone', 'leadsforward-core') . '</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_email">' . esc_html__('Email', 'leadsforward-core') . '</label></th><td><input type="email" id="lf_business_email" name="lf_business_email" class="regular-text" value="' . esc_attr($be) . '" placeholder="contact@yourbusiness.com" /></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Address (NAP)', 'leadsforward-core') . '</th><td><input type="text" class="large-text" name="lf_business_address_street" placeholder="' . esc_attr($default_street) . '" value="' . esc_attr($street) . '" /><div style="display:flex;gap:10px;margin-top:6px;flex-wrap:wrap;"><input type="text" class="regular-text" name="lf_business_address_city" placeholder="' . esc_attr($default_city) . '" value="' . esc_attr($city) . '" /><input type="text" class="regular-text" name="lf_business_address_state" placeholder="' . esc_attr($default_state) . '" value="' . esc_attr($state) . '" /><input type="text" class="regular-text" name="lf_business_address_zip" placeholder="' . esc_attr($default_zip) . '" value="' . esc_attr($zip) . '" /></div></td></tr>';
		echo '<tr><th scope="row"><label for="lf_homepage_city">' . esc_html__('Primary city or service region', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_homepage_city" name="lf_homepage_city" class="regular-text" value="' . esc_attr($home_city) . '" placeholder="' . esc_attr($default_city) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_hours">' . esc_html__('Opening hours', 'leadsforward-core') . '</label></th><td><textarea id="lf_business_hours" name="lf_business_hours" rows="3" class="large-text" placeholder="Mon–Fri 8am–6pm">' . esc_textarea($bh) . '</textarea><br /><span class="description">' . esc_html__('Human-readable hours for schema (e.g. Mon–Fri 8am–6pm). One line per rule.', 'leadsforward-core') . '</span></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_category">' . esc_html__('Primary category', 'leadsforward-core') . '</label></th><td><select name="lf_business_category" id="lf_business_category">';
		foreach ($allowed_categories as $cat) {
			$label = $cat;
			switch ($cat) {
				case 'HomeAndConstructionBusiness': $label = __('Home & Construction Business', 'leadsforward-core'); break;
				case 'GeneralContractor': $label = __('General Contractor', 'leadsforward-core'); break;
				case 'RoofingContractor': $label = __('Roofing Contractor', 'leadsforward-core'); break;
				case 'Plumber': $label = __('Plumber', 'leadsforward-core'); break;
				case 'HVACBusiness': $label = __('HVAC Business', 'leadsforward-core'); break;
				case 'LandscapingBusiness': $label = __('Landscaping Business', 'leadsforward-core'); break;
				case 'LocalBusiness': $label = __('Local Business (generic)', 'leadsforward-core'); break;
			}
			echo '<option value="' . esc_attr($cat) . '"' . selected($category === $cat, true, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_short_description">' . esc_html__('Short business description', 'leadsforward-core') . '</label></th><td><textarea id="lf_business_short_description" name="lf_business_short_description" rows="3" class="large-text">' . esc_textarea($short_desc) . '</textarea></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Google Maps API key', 'leadsforward-core') . '</th><td>';
		if ($maps_api_key) {
			echo '<p class="description">' . esc_html__('Key is set in LeadsForward -> Setup.', 'leadsforward-core') . '</p>';
		} else {
			echo '<p class="description">' . esc_html__('Add your Google Maps API key in LeadsForward -> Setup to enable place search + embeds.', 'leadsforward-core') . '</p>';
		}
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_place_search">' . esc_html__('Search business on Google Maps', 'leadsforward-core') . '</label></th><td>';
		echo '<input type="text" class="large-text" id="lf_business_place_search" placeholder="' . esc_attr__('Start typing your business name...', 'leadsforward-core') . '" value="' . esc_attr($place_name) . '" />';
		echo '<input type="hidden" name="lf_business_place_id" id="lf_business_place_id" value="' . esc_attr($place_id) . '" />';
		echo '<input type="hidden" name="lf_business_place_name" id="lf_business_place_name" value="' . esc_attr($place_name) . '" />';
		echo '<input type="hidden" name="lf_business_place_address" id="lf_business_place_address" value="' . esc_attr($place_address) . '" />';
		echo '<p class="description" id="lf_place_selected">' . ($place_name !== '' ? esc_html(sprintf(__('Selected: %1$s (%2$s)', 'leadsforward-core'), $place_name, $place_address)) : esc_html__('No place selected yet.', 'leadsforward-core')) . '</p>';
		echo '<p class="description" id="lf_maps_status" style="color:#b45309;"></p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_map_embed">' . esc_html__('Map embed override (optional)', 'leadsforward-core') . '</label></th><td><textarea class="large-text" name="lf_business_map_embed" id="lf_business_map_embed" rows="3">' . esc_textarea($map_embed) . '</textarea><p class="description">' . esc_html__('Paste a custom iframe embed if you prefer. If empty, the selected Google Maps place will be used.', 'leadsforward-core') . '</p></td></tr>';
		echo '</table>';

		echo '<details style="margin-top:12px;">';
		echo '<summary style="cursor:pointer;">' . esc_html__('Advanced business details (optional)', 'leadsforward-core') . '</summary>';
		echo '<table class="form-table">';
		echo '<tr><th scope="row">' . esc_html__('Latitude / Longitude', 'leadsforward-core') . '</th><td><div style="display:flex;gap:10px;flex-wrap:wrap;"><input type="text" class="regular-text" name="lf_business_geo_lat" placeholder="' . esc_attr__('Latitude', 'leadsforward-core') . '" value="' . esc_attr($geo_lat) . '" /><input type="text" class="regular-text" name="lf_business_geo_lng" placeholder="' . esc_attr__('Longitude', 'leadsforward-core') . '" value="' . esc_attr($geo_lng) . '" /></div></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_gbp_url">' . esc_html__('Google Business Profile URL', 'leadsforward-core') . '</label></th><td><input type="url" class="large-text" name="lf_business_gbp_url" id="lf_business_gbp_url" value="' . esc_attr($gbp_url) . '" /></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Social profiles', 'leadsforward-core') . '</th><td>';
		echo '<input type="url" class="large-text" name="lf_business_social_facebook" placeholder="' . esc_attr__('Facebook URL', 'leadsforward-core') . '" value="' . esc_attr($social_facebook) . '" />';
		echo '<input type="url" class="large-text" name="lf_business_social_instagram" placeholder="' . esc_attr__('Instagram URL', 'leadsforward-core') . '" value="' . esc_attr($social_instagram) . '" style="margin-top:6px;" />';
		echo '<input type="url" class="large-text" name="lf_business_social_youtube" placeholder="' . esc_attr__('YouTube URL', 'leadsforward-core') . '" value="' . esc_attr($social_youtube) . '" style="margin-top:6px;" />';
		echo '<input type="url" class="large-text" name="lf_business_social_linkedin" placeholder="' . esc_attr__('LinkedIn URL', 'leadsforward-core') . '" value="' . esc_attr($social_linkedin) . '" style="margin-top:6px;" />';
		echo '<input type="url" class="large-text" name="lf_business_social_tiktok" placeholder="' . esc_attr__('TikTok URL', 'leadsforward-core') . '" value="' . esc_attr($social_tiktok) . '" style="margin-top:6px;" />';
		echo '<input type="url" class="large-text" name="lf_business_social_x" placeholder="' . esc_attr__('X (Twitter) URL', 'leadsforward-core') . '" value="' . esc_attr($social_x) . '" style="margin-top:6px;" />';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_same_as">' . esc_html__('sameAs links (optional)', 'leadsforward-core') . '</label></th><td><textarea class="large-text" id="lf_business_same_as" name="lf_business_same_as" rows="3" placeholder="' . esc_attr__('One URL per line', 'leadsforward-core') . '">' . esc_textarea($same_as) . '</textarea></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_founding_year">' . esc_html__('Founding year (optional)', 'leadsforward-core') . '</label></th><td><input type="text" class="regular-text" id="lf_business_founding_year" name="lf_business_founding_year" value="' . esc_attr($founding_year) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_license_number">' . esc_html__('License number (optional)', 'leadsforward-core') . '</label></th><td><input type="text" class="regular-text" id="lf_business_license_number" name="lf_business_license_number" value="' . esc_attr($license_number) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_business_insurance_statement">' . esc_html__('Insurance statement (optional)', 'leadsforward-core') . '</label></th><td><textarea class="large-text" id="lf_business_insurance_statement" name="lf_business_insurance_statement" rows="2">' . esc_textarea($insurance_statement) . '</textarea></td></tr>';
		echo '</table>';
		echo '</details>';

		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Next', 'leadsforward-core') . '" /></p></form>';
		echo '<script>(function(){function loadPlacesApi(key, callback){var status=document.getElementById("lf_maps_status");if(window.google&&window.google.maps&&window.google.maps.places){callback();return;}if(!key){if(status){status.textContent="Add your Google Maps API key in LeadsForward -> Setup to enable search.";}return;}var scriptId="lf-maps-places";if(document.getElementById(scriptId)){return;}var script=document.createElement("script");script.id=scriptId;script.src="https://maps.googleapis.com/maps/api/js?key="+encodeURIComponent(key)+"&libraries=places";script.async=true;script.onerror=function(){if(status){status.textContent="Failed to load Google Maps. Check API key restrictions and billing.";}};script.onload=callback;document.head.appendChild(script);}function initPlacesSearch(){var input=document.getElementById("lf_business_place_search");var placeId=document.getElementById("lf_business_place_id");var placeName=document.getElementById("lf_business_place_name");var placeAddress=document.getElementById("lf_business_place_address");var selected=document.getElementById("lf_place_selected");var status=document.getElementById("lf_maps_status");if(!input){return;}var form=input.closest("form");var key=form?(form.getAttribute("data-maps-key")||""):"";key=key.trim();if(!key){if(selected){selected.textContent="Add your Google Maps API key in LeadsForward -> Setup to enable search.";}if(status){status.textContent="";}return;}if(status){status.textContent="Loading Google Maps...";}window.gm_authFailure=function(){if(status){status.textContent="Google Maps auth failed. Check key restrictions and billing.";}};loadPlacesApi(key,function(){if(!window.google||!google.maps||!google.maps.places){if(status){status.textContent="Google Maps loaded without Places library. Check API settings.";}return;}if(status){status.textContent="";}var ac=new google.maps.places.Autocomplete(input,{fields:["place_id","name","formatted_address"]});ac.addListener("place_changed",function(){var place=ac.getPlace();if(!place||!place.place_id){return;}if(placeId)placeId.value=place.place_id||"";if(placeName)placeName.value=place.name||"";if(placeAddress)placeAddress.value=place.formatted_address||"";if(selected){selected.textContent="Selected: "+(place.name||"")+(place.formatted_address?" ("+place.formatted_address+")":"");}});});}initPlacesSearch();})();</script>';
	} elseif ($step === 3) {
		$niche = $niche ?: array_key_first(lf_get_niche_registry());
		$n = lf_get_niche($niche);
		$services_list = $n ? implode(', ', $n['services']) : '';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">';
		echo '<input type="hidden" name="page" value="lf-ops" />';
		echo '<input type="hidden" name="step" value="4" />';
		echo '<input type="hidden" name="niche" value="' . esc_attr($niche) . '" />';
		$allowed_embed = lf_wizard_allowed_map_embed();
		$carry_fields = [
			'lf_business_name',
			'lf_business_legal_name',
			'lf_business_phone_primary',
			'lf_business_phone_tracking',
			'lf_business_phone_display',
			'lf_business_email',
			'lf_business_address_street',
			'lf_business_address_city',
			'lf_business_address_state',
			'lf_business_address_zip',
			'lf_business_hours',
			'lf_business_category',
			'lf_business_short_description',
			'lf_business_geo_lat',
			'lf_business_geo_lng',
			'lf_business_gbp_url',
			'lf_business_social_facebook',
			'lf_business_social_instagram',
			'lf_business_social_youtube',
			'lf_business_social_linkedin',
			'lf_business_social_tiktok',
			'lf_business_social_x',
			'lf_business_social_tiktok',
			'lf_business_social_x',
			'lf_business_social_tiktok',
			'lf_business_social_x',
			'lf_business_same_as',
			'lf_business_founding_year',
			'lf_business_license_number',
			'lf_business_insurance_statement',
			'lf_business_place_id',
			'lf_business_place_name',
			'lf_business_place_address',
			'lf_business_map_embed',
			'lf_homepage_city',
		];
		foreach ($carry_fields as $field) {
			$value = $_GET[$field] ?? '';
			if (!is_string($value)) {
				$value = '';
			}
			$value = wp_unslash($value);
			if ($field === 'lf_business_email') {
				$value = sanitize_email($value);
			} elseif ($field === 'lf_business_gbp_url' || str_starts_with($field, 'lf_business_social_')) {
				$value = esc_url_raw($value);
			} elseif ($field === 'lf_business_short_description' || $field === 'lf_business_same_as' || $field === 'lf_business_insurance_statement' || $field === 'lf_business_hours') {
				$value = sanitize_textarea_field($value);
			} elseif ($field === 'lf_business_map_embed') {
				$value = wp_kses($value, $allowed_embed);
			} else {
				$value = sanitize_text_field($value);
			}
			echo '<input type="hidden" name="' . esc_attr($field) . '" value="' . esc_attr($value) . '" />';
		}
		echo '<p>' . esc_html__('Services to create:', 'leadsforward-core') . ' ' . esc_html($services_list) . '</p>';
		$service_area_type = isset($_GET['lf_business_service_area_type']) ? sanitize_text_field($_GET['lf_business_service_area_type']) : 'address';
		if ($service_area_type !== 'service_area') {
			$service_area_type = 'address';
		}
		$areas_value = isset($_GET['lf_service_areas']) ? implode("\n", lf_wizard_sanitize_areas($_GET['lf_service_areas'])) : '';
		echo '<table class="form-table">';
		echo '<tr><th scope="row">' . esc_html__('Service area type', 'leadsforward-core') . '</th><td><select name="lf_business_service_area_type"><option value="address"' . selected($service_area_type !== 'service_area', true, false) . '>' . esc_html__('Address-based business', 'leadsforward-core') . '</option><option value="service_area"' . selected($service_area_type === 'service_area', true, false) . '>' . esc_html__('Service-area business (SAB)', 'leadsforward-core') . '</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="lf_service_areas">' . esc_html__('Service areas (one per line; optional "City, ST")', 'leadsforward-core') . '</label></th><td><textarea id="lf_service_areas" name="lf_service_areas" rows="5" class="large-text" placeholder="City One&#10;City Two, CA">' . esc_textarea($areas_value) . '</textarea></td></tr>';
		echo '</table>';
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Next', 'leadsforward-core') . '" /></p></form>';
		echo '</div>';
	} elseif ($step === 4) {
		$niche = $niche ?: array_key_first(lf_get_niche_registry());
		$n = lf_get_niche($niche);
		$rec = $n['variation_profile'] ?? 'a';
		echo '<form method="get" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '">';
		echo '<input type="hidden" name="page" value="lf-ops" />';
		echo '<input type="hidden" name="step" value="5" />';
		echo '<input type="hidden" name="niche" value="' . esc_attr($niche) . '" />';
		$allowed_embed = lf_wizard_allowed_map_embed();
		$carry_fields = [
			'lf_business_name',
			'lf_business_legal_name',
			'lf_business_phone_primary',
			'lf_business_phone_tracking',
			'lf_business_phone_display',
			'lf_business_email',
			'lf_business_address_street',
			'lf_business_address_city',
			'lf_business_address_state',
			'lf_business_address_zip',
			'lf_business_hours',
			'lf_business_category',
			'lf_business_short_description',
			'lf_business_geo_lat',
			'lf_business_geo_lng',
			'lf_business_gbp_url',
			'lf_business_social_facebook',
			'lf_business_social_instagram',
			'lf_business_social_youtube',
			'lf_business_social_linkedin',
			'lf_business_same_as',
			'lf_business_founding_year',
			'lf_business_license_number',
			'lf_business_insurance_statement',
			'lf_business_place_id',
			'lf_business_place_name',
			'lf_business_place_address',
			'lf_business_map_embed',
			'lf_business_service_area_type',
			'lf_homepage_city',
		];
		foreach ($carry_fields as $field) {
			$value = $_GET[$field] ?? '';
			if (!is_string($value)) {
				$value = '';
			}
			$value = wp_unslash($value);
			if ($field === 'lf_business_email') {
				$value = sanitize_email($value);
			} elseif ($field === 'lf_business_gbp_url' || str_starts_with($field, 'lf_business_social_')) {
				$value = esc_url_raw($value);
			} elseif ($field === 'lf_business_short_description' || $field === 'lf_business_same_as' || $field === 'lf_business_insurance_statement' || $field === 'lf_business_hours') {
				$value = sanitize_textarea_field($value);
			} elseif ($field === 'lf_business_map_embed') {
				$value = wp_kses($value, $allowed_embed);
			} else {
				$value = sanitize_text_field($value);
			}
			echo '<input type="hidden" name="' . esc_attr($field) . '" value="' . esc_attr($value) . '" />';
		}
		$areas = isset($_GET['lf_service_areas']) ? lf_wizard_sanitize_areas($_GET['lf_service_areas']) : [];
		echo '<input type="hidden" name="lf_service_areas_raw" value="' . esc_attr(implode("\n", $areas)) . '" />';
		$hero_variants = function_exists('lf_sections_hero_variant_options') ? lf_sections_hero_variant_options() : ['default' => __('Default', 'leadsforward-core')];
		$hero_variant = sanitize_text_field($_GET['lf_homepage_hero_variant'] ?? 'default');
		if (!array_key_exists($hero_variant, $hero_variants)) {
			$hero_variant = 'default';
		}
		$keyword_primary = sanitize_text_field($_GET['lf_homepage_keyword_primary'] ?? '');
		$keyword_secondary = sanitize_textarea_field($_GET['lf_homepage_keyword_secondary'] ?? '');
		$generate_now = !empty($_GET['lf_homepage_generate_now']);

		echo '<table class="form-table"><tr><th scope="row">' . esc_html__('Site style', 'leadsforward-core') . '</th><td><select name="lf_variation_profile">';
		foreach ($profiles as $key => $label) {
			echo '<option value="' . esc_attr($key) . '"' . selected($rec, $key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="lf_homepage_hero_variant">' . esc_html__('Homepage hero layout', 'leadsforward-core') . '</label></th><td><select id="lf_homepage_hero_variant" name="lf_homepage_hero_variant">';
		foreach ($hero_variants as $variant_key => $label) {
			echo '<option value="' . esc_attr($variant_key) . '"' . selected($hero_variant, $variant_key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="lf_homepage_keyword_primary">' . esc_html__('Primary homepage keyword (SEO)', 'leadsforward-core') . '</label></th><td><input type="text" id="lf_homepage_keyword_primary" name="lf_homepage_keyword_primary" class="large-text" value="' . esc_attr($keyword_primary) . '" required placeholder="' . esc_attr__('e.g. Roofing contractor Sarasota', 'leadsforward-core') . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="lf_homepage_keyword_secondary">' . esc_html__('Secondary homepage keywords (optional)', 'leadsforward-core') . '</label></th><td><textarea id="lf_homepage_keyword_secondary" name="lf_homepage_keyword_secondary" rows="3" class="large-text" placeholder="' . esc_attr__('One per line', 'leadsforward-core') . '">' . esc_textarea($keyword_secondary) . '</textarea><p class="description">' . esc_html__('These keywords are stored for AI Studio regeneration.', 'leadsforward-core') . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__('Generate homepage now', 'leadsforward-core') . '</th><td><label><input type="checkbox" name="lf_homepage_generate_now" value="1"' . checked($generate_now, true, false) . ' /> ' . esc_html__('Generate homepage content after setup completes', 'leadsforward-core') . '</label><p class="description">' . esc_html__('Runs AI generation immediately after the setup completes.', 'leadsforward-core') . '</p></td></tr>';
		echo '</table>';
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Next', 'leadsforward-core') . '" /></p></form>';
		echo '</div>';
	} else {
		$step = 5;
		$niche = $niche ?: array_key_first(lf_get_niche_registry());
		echo '<div class="lf-setup-card">';
		echo '<h2 class="lf-setup-step-title">' . esc_html__('Generate your site', 'leadsforward-core') . '</h2>';
		echo '<p class="lf-setup-help">' . esc_html__('Creates pages, services, service areas, menus, and applies homepage settings.', 'leadsforward-core') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=lf-ops')) . '" enctype="multipart/form-data">';
		wp_nonce_field('lf_wizard_generate', 'lf_wizard_nonce');
		echo '<input type="hidden" name="lf_wizard_step" value="5" />';
		echo '<input type="hidden" name="lf_wizard_generate" value="1" />';
		echo '<input type="hidden" name="lf_niche" value="' . esc_attr($niche) . '" />';
		$allowed_embed = lf_wizard_allowed_map_embed();
		$carry_fields = [
			'lf_business_name',
			'lf_business_legal_name',
			'lf_business_phone_primary',
			'lf_business_phone_tracking',
			'lf_business_phone_display',
			'lf_business_email',
			'lf_business_address_street',
			'lf_business_address_city',
			'lf_business_address_state',
			'lf_business_address_zip',
			'lf_business_hours',
			'lf_business_category',
			'lf_business_short_description',
			'lf_business_geo_lat',
			'lf_business_geo_lng',
			'lf_business_gbp_url',
			'lf_business_social_facebook',
			'lf_business_social_instagram',
			'lf_business_social_youtube',
			'lf_business_social_linkedin',
			'lf_business_same_as',
			'lf_business_founding_year',
			'lf_business_license_number',
			'lf_business_insurance_statement',
			'lf_business_place_id',
			'lf_business_place_name',
			'lf_business_place_address',
			'lf_business_map_embed',
			'lf_business_service_area_type',
			'lf_variation_profile',
			'lf_homepage_city',
			'lf_homepage_keyword_primary',
			'lf_homepage_keyword_secondary',
			'lf_homepage_hero_variant',
			'lf_homepage_generate_now',
		];
		foreach ($carry_fields as $field) {
			$value = $_GET[$field] ?? '';
			if (!is_string($value)) {
				$value = '';
			}
			$value = wp_unslash($value);
			if ($field === 'lf_business_email') {
				$value = sanitize_email($value);
			} elseif ($field === 'lf_business_gbp_url' || str_starts_with($field, 'lf_business_social_')) {
				$value = esc_url_raw($value);
			} elseif ($field === 'lf_business_short_description' || $field === 'lf_business_same_as' || $field === 'lf_business_insurance_statement' || $field === 'lf_business_hours') {
				$value = sanitize_textarea_field($value);
			} elseif ($field === 'lf_business_map_embed') {
				$value = wp_kses($value, $allowed_embed);
			} else {
				$value = sanitize_text_field($value);
			}
			echo '<input type="hidden" name="' . esc_attr($field) . '" value="' . esc_attr($value) . '" />';
		}
		$areas_raw = isset($_GET['lf_service_areas_raw']) ? $_GET['lf_service_areas_raw'] : (isset($_GET['lf_service_areas']) ? $_GET['lf_service_areas'] : '');
		$areas_str = is_string($areas_raw) ? $areas_raw : implode("\n", (array) $areas_raw);
		echo '<input type="hidden" name="lf_service_areas" value="' . esc_attr($areas_str) . '" />';
		echo '<p>' . esc_html__('Click Generate to create pages, services, service areas, menus, and set Theme Options. This will not duplicate existing pages.', 'leadsforward-core') . '</p>';
		echo '<p><label for="lf_homepage_keywords_file">' . esc_html__('Upload keyword list (optional)', 'leadsforward-core') . '</label><br />';
		echo '<input type="file" id="lf_homepage_keywords_file" name="lf_homepage_keywords_file" accept=".txt,.csv" /> ';
		echo '<span class="description">' . esc_html__('One keyword per line or comma-separated. Added to secondary keywords.', 'leadsforward-core') . '</span></p>';
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Generate site', 'leadsforward-core') . '" /></p></form>';
		echo '</div>';
	}
	echo '</div>';
	echo '<details class="lf-setup-card" style="max-width: 980px;"><summary style="cursor:pointer;font-weight:600;">' . esc_html__('Advanced settings (API keys, legal pages, reset)', 'leadsforward-core') . '</summary>';
	lf_wizard_render_setup_settings_panel();
	echo '</details>';
	echo '</div>';
}

function lf_wizard_render_setup_settings_panel(): void {
	$maps_key = (string) get_option('lf_maps_api_key', '');
	$openai_key_set = get_option('lf_openai_api_key', '') !== '';
	$hide_bar = get_option('lf_hide_admin_bar', '0') === '1';
	?>
	<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0;">
		<h2 style="margin-top:0;"><?php esc_html_e('Global API & Admin Settings', 'leadsforward-core'); ?></h2>
		<p class="description"><?php esc_html_e('These settings apply site‑wide and are required for maps and AI copy suggestions.', 'leadsforward-core'); ?></p>
		<form method="post">
			<?php wp_nonce_field('lf_setup_settings', 'lf_setup_settings_nonce'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_maps_api_key"><?php esc_html_e('Google Maps API key', 'leadsforward-core'); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="lf_maps_api_key" name="lf_maps_api_key" value="<?php echo esc_attr($maps_key); ?>" />
						<label style="margin-left:8px;"><input type="checkbox" name="lf_maps_api_key_clear" value="1" /> <?php esc_html_e('Clear', 'leadsforward-core'); ?></label>
						<p class="description"><?php esc_html_e('Required for Google Maps search + embed. Enable Places and Maps Embed APIs.', 'leadsforward-core'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_openai_api_key"><?php esc_html_e('OpenAI API key', 'leadsforward-core'); ?></label></th>
					<td>
						<input type="password" class="regular-text" id="lf_openai_api_key" name="lf_openai_api_key" value="" placeholder="<?php echo $openai_key_set ? esc_attr__('Saved (hidden)', 'leadsforward-core') : esc_attr__('sk-...', 'leadsforward-core'); ?>" />
						<label style="margin-left:8px;"><input type="checkbox" name="lf_openai_api_key_clear" value="1" /> <?php esc_html_e('Clear', 'leadsforward-core'); ?></label>
						<p class="description"><?php esc_html_e('Required for AI Assistant suggestions. Key is stored securely in options.', 'leadsforward-core'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Hide admin bar on front end', 'leadsforward-core'); ?></th>
					<td>
						<label><input type="checkbox" name="lf_hide_admin_bar" value="1" <?php checked($hide_bar); ?> /> <?php esc_html_e('Enable', 'leadsforward-core'); ?></label>
					</td>
				</tr>
			</table>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'leadsforward-core'); ?></button></p>
		</form>
	</div>
	<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0;">
		<h2 style="margin-top:0;"><?php esc_html_e('Legal Pages', 'leadsforward-core'); ?></h2>
		<p class="description"><?php esc_html_e('Regenerate Privacy Policy and Terms of Service using current Business Entity data.', 'leadsforward-core'); ?></p>
		<form method="post">
			<?php wp_nonce_field('lf_regen_legal', 'lf_regen_legal_nonce'); ?>
			<p class="submit"><button type="submit" class="button"><?php esc_html_e('Regenerate legal pages', 'leadsforward-core'); ?></button></p>
		</form>
	</div>
	<?php if (function_exists('lf_dev_reset_allowed') && lf_dev_reset_allowed() && current_user_can('manage_options')) : ?>
		<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0; border-left: 4px solid #b32d2e;">
			<h2 style="margin-top:0;"><?php esc_html_e('Reset site (dev only)', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Deletes content, menus, and options created by the setup wizard. Available only in local/dev environments.', 'leadsforward-core'); ?></p>
			<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=lf-ops')); ?>">
				<?php wp_nonce_field('lf_dev_reset', 'lf_dev_reset_nonce'); ?>
				<input type="hidden" name="lf_dev_reset" value="1" />
				<p><label for="lf_dev_reset_confirm"><?php esc_html_e('Type RESET to confirm:', 'leadsforward-core'); ?></label><br />
					<input type="text" id="lf_dev_reset_confirm" name="lf_dev_reset_confirm" value="" autocomplete="off" style="text-transform:uppercase;" /></p>
				<p><input type="submit" class="button" value="<?php esc_attr_e('RESET SITE (DEV ONLY)', 'leadsforward-core'); ?>" style="background:#b32d2e;border-color:#b32d2e;color:#fff;" /></p>
			</form>
		</div>
	<?php endif; ?>
	<?php
}
