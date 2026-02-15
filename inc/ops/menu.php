<?php
/**
 * LeadsForward parent menu and submenu registration. Admin only.
 * Order: Website Manifester → Global Settings → Homepage → Quote Builder → Ops (bulk/audit/config).
 * Site Health is added by inc/site-health/dashboard.php at priority 11.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'lf_ops_register_menu', 10);
add_action('admin_menu', 'lf_ops_remove_theme_options_menu', 999);
add_action('admin_init', 'lf_ops_handle_global_settings_save');
add_action('admin_enqueue_scripts', 'lf_ops_settings_assets');
add_action('admin_enqueue_scripts', 'lf_ops_brand_admin_assets');
add_action('admin_post_lf_reviews_sync', 'lf_ops_handle_reviews_sync');

function lf_ops_register_menu(): void {
	// Parent: slug lf-ops so first submenu with same slug (Manifester) is the default — no redirect, avoids "headers already sent"
	add_menu_page(
		__('LeadsForward', 'leadsforward-core'),
		__('LeadsForward', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops',
		'lf_ai_studio_render_page',
		'dashicons-admin-generic',
		59
	);

	// 1. Website Manifester — same slug as parent so clicking "LeadsForward" shows this.
	add_submenu_page(
		'lf-ops',
		__('Website Manifester', 'leadsforward-core'),
		__('Website Manifester', 'leadsforward-core'),
		'edit_theme_options',
		'lf-ops',
		'lf_ai_studio_render_page'
	);
	// 2. Global Settings (includes Branding).
	add_submenu_page(
		'lf-ops',
		__('Global Settings', 'leadsforward-core'),
		__('Global Settings', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-global',
		'lf_ops_render_global_settings_page'
	);
	// 3. Homepage (sections)
	add_submenu_page(
		'lf-ops',
		__('Homepage', 'leadsforward-core'),
		__('Homepage', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-homepage-settings',
		'lf_homepage_admin_render'
	);
	// 4. Quote Builder
	add_submenu_page(
		'lf-ops',
		__('Quote Builder', 'leadsforward-core'),
		__('Quote Builder', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-quote-builder',
		'lf_quote_builder_render_admin'
	);
	// 4b. Contact Form
	add_submenu_page(
		'lf-ops',
		__('Contact Form', 'leadsforward-core'),
		__('Contact Form', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-contact-form',
		'lf_contact_form_render_admin'
	);
	$has_acf_options = function_exists('acf_options_page_html');
	// Remaining ACF option pages (only render if ACF options pages exist).
	if ($has_acf_options) {
		add_submenu_page('lf-ops', __('CTAs', 'leadsforward-core'), __('CTAs', 'leadsforward-core'), LF_OPS_CAP, 'lf-ctas', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Schema', 'leadsforward-core'), __('Schema', 'leadsforward-core'), LF_OPS_CAP, 'lf-schema', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Variation', 'leadsforward-core'), __('Variation', 'leadsforward-core'), LF_OPS_CAP, 'lf-variation', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Homepage Options', 'leadsforward-core'), __('Homepage Options', 'leadsforward-core'), LF_OPS_CAP, 'lf-homepage', 'lf_ops_render_acf_options_page');
		add_submenu_page('lf-ops', __('Business Info', 'leadsforward-core'), __('Business Info', 'leadsforward-core'), LF_OPS_CAP, 'lf-business-info', 'lf_ops_render_acf_options_page');
	}
	// Ops utilities.
	add_submenu_page(
		'lf-ops',
		__('Bulk Actions', 'leadsforward-core'),
		__('Bulk Actions', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-bulk',
		'lf_ops_bulk_render'
	);
	add_submenu_page(
		'lf-ops',
		__('Audit Log', 'leadsforward-core'),
		__('Audit Log', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-audit',
		'lf_ops_audit_render'
	);
	// Config (Export + Import) — keep at the bottom.
	add_submenu_page(
		'lf-ops',
		__('Config', 'leadsforward-core'),
		__('Config', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-ops-config',
		'lf_ops_config_render'
	);
}

function lf_ops_render_acf_options_page(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	if (function_exists('acf_options_page_html')) {
		acf_options_page_html();
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html__('LeadsForward Settings', 'leadsforward-core') . '</h1>';
	echo '<p>' . esc_html__('This page requires Advanced Custom Fields (ACF).', 'leadsforward-core') . '</p></div>';
}

function lf_ops_settings_assets(string $hook): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	if (!in_array($hook, ['leadsforward_page_lf-global'], true)) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style('wp-color-picker');
	wp_enqueue_script('wp-color-picker');
	wp_enqueue_style(
		'lf-ai-studio-airtable',
		LF_THEME_URI . '/assets/css/ai-studio-airtable.css',
		[],
		LF_THEME_VERSION
	);
}

function lf_ops_brand_admin_assets(string $hook): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$is_leadsforward_page = $hook === 'toplevel_page_lf-ops' || str_starts_with($hook, 'leadsforward_page_lf-');
	if (!$is_leadsforward_page) {
		return;
	}
	wp_enqueue_style(
		'lf-admin-brand',
		LF_THEME_URI . '/assets/css/admin-brand.css',
		[],
		LF_THEME_VERSION
	);
}

function lf_ops_handle_reviews_sync(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		wp_die(esc_html__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_reviews_sync', 'lf_reviews_sync_nonce');
	$settings = function_exists('lf_ai_studio_airtable_get_settings')
		? lf_ai_studio_airtable_get_settings()
		: [];
	$reviews_table = trim((string) (($settings['reviews']['table'] ?? '') ?: ''));
	if (empty($settings['enabled']) || $reviews_table === '') {
		wp_safe_redirect(admin_url('admin.php?page=lf-global&reviews_sync=error&reviews_error=disabled'));
		exit;
	}
	$project_name = function_exists('lf_ai_studio_airtable_get_project_name_for_reviews')
		? lf_ai_studio_airtable_get_project_name_for_reviews($settings)
		: '';
	if ($project_name === '') {
		wp_safe_redirect(admin_url('admin.php?page=lf-global&reviews_sync=error&reviews_error=project'));
		exit;
	}
	$result = function_exists('lf_ai_studio_airtable_import_reviews_by_project')
		? lf_ai_studio_airtable_import_reviews_by_project($project_name, $settings)
		: ['error' => __('Reviews import is unavailable.', 'leadsforward-core')];
	if (!empty($result['error'])) {
		$error = rawurlencode((string) $result['error']);
		wp_safe_redirect(admin_url('admin.php?page=lf-global&reviews_sync=error&reviews_error=' . $error));
		exit;
	}
	update_option('lf_ai_airtable_reviews_last_sync', time(), false);
	update_option('lf_ai_airtable_reviews_last_imported', (int) ($result['imported'] ?? 0), false);
	wp_safe_redirect(admin_url('admin.php?page=lf-global&reviews_sync=1&reviews_imported=' . (int) ($result['imported'] ?? 0)));
	exit;
}

function lf_ops_handle_global_settings_save(): void {
	if (!isset($_POST['lf_global_settings_nonce'])) {
		return;
	}
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	if (!wp_verify_nonce($_POST['lf_global_settings_nonce'], 'lf_global_settings')) {
		return;
	}
	$prev_logo_id = (int) lf_get_global_option('lf_global_logo', 0);
	$logo_id = isset($_POST['lf_global_logo']) ? (int) $_POST['lf_global_logo'] : 0;
	update_option('options_lf_global_logo', $logo_id);
	update_option('options_lf_header_cta_label', isset($_POST['lf_header_cta_label']) ? sanitize_text_field(wp_unslash($_POST['lf_header_cta_label'])) : '');
	update_option('options_lf_header_cta_url', isset($_POST['lf_header_cta_url']) ? esc_url_raw(wp_unslash($_POST['lf_header_cta_url'])) : '');
	update_option('lf_ai_studio_enabled', isset($_POST['lf_ai_studio_enabled']) ? '1' : '0');
	update_option('lf_ai_studio_webhook', isset($_POST['lf_ai_studio_webhook']) ? esc_url_raw(wp_unslash($_POST['lf_ai_studio_webhook'])) : '');
	update_option('lf_ai_studio_secret', isset($_POST['lf_ai_studio_secret']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_studio_secret'])) : '');
	update_option('lf_ai_studio_callback_url', isset($_POST['lf_ai_studio_callback_url']) ? esc_url_raw(wp_unslash($_POST['lf_ai_studio_callback_url'])) : '');
	update_option('lf_ai_airtable_enabled', isset($_POST['lf_ai_airtable_enabled']) ? '1' : '0');
	update_option('lf_ai_airtable_pat', isset($_POST['lf_ai_airtable_pat']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_pat'])) : '');
	update_option('lf_ai_airtable_base', isset($_POST['lf_ai_airtable_base']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_base'])) : '');
	update_option('lf_ai_airtable_table', isset($_POST['lf_ai_airtable_table']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_table'])) : '');
	update_option('lf_ai_airtable_view', isset($_POST['lf_ai_airtable_view']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_view'])) : '');
	update_option('lf_ai_airtable_reviews_table', isset($_POST['lf_ai_airtable_reviews_table']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_reviews_table'])) : '');
	update_option('lf_ai_airtable_reviews_view', isset($_POST['lf_ai_airtable_reviews_view']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_reviews_view'])) : '');
	update_option('lf_maps_api_key', isset($_POST['lf_maps_api_key']) ? sanitize_text_field(wp_unslash($_POST['lf_maps_api_key'])) : '');
	$design_preset = isset($_POST['lf_global_design_preset']) ? sanitize_text_field(wp_unslash($_POST['lf_global_design_preset'])) : 'clean-precision';
	$design_presets = function_exists('lf_design_presets') ? lf_design_presets() : [];
	if (empty($design_presets)) {
		$design_presets = [
			'clean-precision' => __('Clean Precision', 'leadsforward-core'),
			'bold-authority' => __('Bold Authority', 'leadsforward-core'),
			'friendly-approachable' => __('Friendly & Approachable', 'leadsforward-core'),
			'high-contrast' => __('High-Contrast Conversion Engine', 'leadsforward-core'),
			'modern-edge' => __('Modern Edge', 'leadsforward-core'),
			'structured-modular' => __('Structured Modular', 'leadsforward-core'),
		];
	}
	if (!isset($design_presets[$design_preset])) {
		$design_preset = 'clean-precision';
	}
	update_option('lf_global_design_preset', $design_preset);
	$variation_profile = function_exists('lf_design_preset_to_variation_profile')
		? lf_design_preset_to_variation_profile($design_preset)
		: 'a';
	if (function_exists('update_field')) {
		update_field('variation_profile', $variation_profile, 'option');
	} else {
		update_option('options_variation_profile', $variation_profile);
	}
	update_option('lf_design_overrides_enabled', isset($_POST['lf_design_overrides_enabled']) ? '1' : '0');
	$font_choices = function_exists('lf_design_font_choices') ? lf_design_font_choices() : [];
	$heading_font = isset($_POST['lf_design_heading_font']) ? sanitize_text_field(wp_unslash($_POST['lf_design_heading_font'])) : '';
	if (!isset($font_choices[$heading_font])) {
		$heading_font = '';
	}
	update_option('lf_design_heading_font', $heading_font);
	$body_font = isset($_POST['lf_design_body_font']) ? sanitize_text_field(wp_unslash($_POST['lf_design_body_font'])) : '';
	if (!isset($font_choices[$body_font])) {
		$body_font = '';
	}
	update_option('lf_design_body_font', $body_font);
	$heading_weight = isset($_POST['lf_design_heading_weight']) ? sanitize_text_field(wp_unslash($_POST['lf_design_heading_weight'])) : '';
	if (!in_array($heading_weight, ['600', '700', '800'], true)) {
		$heading_weight = '';
	}
	update_option('lf_design_heading_weight', $heading_weight);
	$button_radius = isset($_POST['lf_design_button_radius']) ? sanitize_text_field(wp_unslash($_POST['lf_design_button_radius'])) : '';
	if (!in_array($button_radius, ['sharp', 'soft', 'pill'], true)) {
		$button_radius = '';
	}
	update_option('lf_design_button_radius', $button_radius);
	$card_radius = isset($_POST['lf_design_card_radius']) ? sanitize_text_field(wp_unslash($_POST['lf_design_card_radius'])) : '';
	if (!in_array($card_radius, ['tight', 'medium', 'round'], true)) {
		$card_radius = '';
	}
	update_option('lf_design_card_radius', $card_radius);
	$card_shadow = isset($_POST['lf_design_card_shadow']) ? sanitize_text_field(wp_unslash($_POST['lf_design_card_shadow'])) : '';
	if (!in_array($card_shadow, ['none', 'soft', 'bold'], true)) {
		$card_shadow = '';
	}
	update_option('lf_design_card_shadow', $card_shadow);
	$section_spacing = isset($_POST['lf_design_section_spacing']) ? sanitize_text_field(wp_unslash($_POST['lf_design_section_spacing'])) : '';
	if (!in_array($section_spacing, ['compact', 'normal', 'airy'], true)) {
		$section_spacing = '';
	}
	update_option('lf_design_section_spacing', $section_spacing);
	$niche_slug = isset($_POST['lf_homepage_niche_slug']) ? sanitize_text_field(wp_unslash($_POST['lf_homepage_niche_slug'])) : 'general';
	$allowed_niches = function_exists('lf_get_niche_registry') ? array_keys(lf_get_niche_registry()) : ['general'];
	if (!in_array($niche_slug, $allowed_niches, true)) {
		$niche_slug = 'general';
	}
	update_option('lf_homepage_niche_slug', $niche_slug);
	$field_defaults = function_exists('lf_ai_studio_airtable_default_field_map') ? lf_ai_studio_airtable_default_field_map() : [];
	$field_input = isset($_POST['lf_ai_airtable_field_map']) && is_array($_POST['lf_ai_airtable_field_map'])
		? $_POST['lf_ai_airtable_field_map']
		: [];
	$sanitized_map = [];
	foreach ($field_defaults as $key => $label) {
		$value = isset($field_input[$key]) ? sanitize_text_field(wp_unslash((string) $field_input[$key])) : '';
		$sanitized_map[$key] = $value !== '' ? $value : $label;
	}
	if (!empty($sanitized_map)) {
		update_option('lf_ai_airtable_field_map', $sanitized_map);
	}
	$review_defaults = function_exists('lf_ai_studio_airtable_reviews_default_field_map') ? lf_ai_studio_airtable_reviews_default_field_map() : [];
	$review_input = isset($_POST['lf_ai_airtable_reviews_field_map']) && is_array($_POST['lf_ai_airtable_reviews_field_map'])
		? $_POST['lf_ai_airtable_reviews_field_map']
		: [];
	$review_map = [];
	foreach ($review_defaults as $key => $label) {
		$value = isset($review_input[$key]) ? sanitize_text_field(wp_unslash((string) $review_input[$key])) : '';
		$review_map[$key] = $value !== '' ? $value : $label;
	}
	if (!empty($review_map)) {
		update_option('lf_ai_airtable_reviews_field_map', $review_map);
	}
	if (function_exists('lf_update_business_info_value')) {
		$display_name = isset($_POST['lf_business_name']) ? sanitize_text_field(wp_unslash($_POST['lf_business_name'])) : '';
		$legal_name = isset($_POST['lf_business_legal_name']) ? sanitize_text_field(wp_unslash($_POST['lf_business_legal_name'])) : '';
		$primary_phone = isset($_POST['lf_business_phone_primary']) ? sanitize_text_field(wp_unslash($_POST['lf_business_phone_primary'])) : '';
		$tracking_phone = isset($_POST['lf_business_phone_tracking']) ? sanitize_text_field(wp_unslash($_POST['lf_business_phone_tracking'])) : '';
		$phone_display = isset($_POST['lf_business_phone_display']) && $_POST['lf_business_phone_display'] === 'tracking' ? 'tracking' : 'primary';
		$display_phone = $phone_display === 'tracking' && $tracking_phone !== '' ? $tracking_phone : $primary_phone;
		$address_street = isset($_POST['lf_business_address_street']) ? sanitize_text_field(wp_unslash($_POST['lf_business_address_street'])) : '';
		$address_city = isset($_POST['lf_business_address_city']) ? sanitize_text_field(wp_unslash($_POST['lf_business_address_city'])) : '';
		$address_state = isset($_POST['lf_business_address_state']) ? sanitize_text_field(wp_unslash($_POST['lf_business_address_state'])) : '';
		$address_zip = isset($_POST['lf_business_address_zip']) ? sanitize_text_field(wp_unslash($_POST['lf_business_address_zip'])) : '';
		$line2 = trim(implode(' ', array_filter([$address_city, $address_state, $address_zip])));
		$address = trim(implode(', ', array_filter([$address_street, $line2])));
		$service_area_type = isset($_POST['lf_business_service_area_type']) && $_POST['lf_business_service_area_type'] === 'service_area' ? 'service_area' : 'address';
		$lat_raw = isset($_POST['lf_business_geo_lat']) ? trim((string) $_POST['lf_business_geo_lat']) : '';
		$lng_raw = isset($_POST['lf_business_geo_lng']) ? trim((string) $_POST['lf_business_geo_lng']) : '';
		$lat = $lat_raw !== '' ? (float) $lat_raw : '';
		$lng = $lng_raw !== '' ? (float) $lng_raw : '';
		$category = isset($_POST['lf_business_category']) ? sanitize_text_field(wp_unslash($_POST['lf_business_category'])) : 'HomeAndConstructionBusiness';
		$allowed_categories = ['HomeAndConstructionBusiness', 'GeneralContractor', 'RoofingContractor', 'Plumber', 'HVACBusiness', 'LandscapingBusiness', 'LocalBusiness'];
		if (!in_array($category, $allowed_categories, true)) {
			$category = 'HomeAndConstructionBusiness';
		}
		$short_desc = isset($_POST['lf_business_short_description']) ? sanitize_textarea_field(wp_unslash($_POST['lf_business_short_description'])) : '';
		$primary_image = isset($_POST['lf_business_primary_image']) ? (int) $_POST['lf_business_primary_image'] : 0;
		$social_facebook = isset($_POST['lf_business_social_facebook']) ? esc_url_raw(wp_unslash($_POST['lf_business_social_facebook'])) : '';
		$social_instagram = isset($_POST['lf_business_social_instagram']) ? esc_url_raw(wp_unslash($_POST['lf_business_social_instagram'])) : '';
		$social_youtube = isset($_POST['lf_business_social_youtube']) ? esc_url_raw(wp_unslash($_POST['lf_business_social_youtube'])) : '';
		$social_linkedin = isset($_POST['lf_business_social_linkedin']) ? esc_url_raw(wp_unslash($_POST['lf_business_social_linkedin'])) : '';
		$social_tiktok = isset($_POST['lf_business_social_tiktok']) ? esc_url_raw(wp_unslash($_POST['lf_business_social_tiktok'])) : '';
		$social_x = isset($_POST['lf_business_social_x']) ? esc_url_raw(wp_unslash($_POST['lf_business_social_x'])) : '';
		$gbp_url = isset($_POST['lf_business_gbp_url']) ? esc_url_raw(wp_unslash($_POST['lf_business_gbp_url'])) : '';
		$same_as = isset($_POST['lf_business_same_as']) ? sanitize_textarea_field(wp_unslash($_POST['lf_business_same_as'])) : '';
		$founding_year = isset($_POST['lf_business_founding_year']) ? sanitize_text_field(wp_unslash($_POST['lf_business_founding_year'])) : '';
		$license_number = isset($_POST['lf_business_license_number']) ? sanitize_text_field(wp_unslash($_POST['lf_business_license_number'])) : '';
		$insurance_statement = isset($_POST['lf_business_insurance_statement']) ? sanitize_textarea_field(wp_unslash($_POST['lf_business_insurance_statement'])) : '';
		$place_id = isset($_POST['lf_business_place_id']) ? sanitize_text_field(wp_unslash($_POST['lf_business_place_id'])) : '';
		$place_name = isset($_POST['lf_business_place_name']) ? sanitize_text_field(wp_unslash($_POST['lf_business_place_name'])) : '';
		$place_address = isset($_POST['lf_business_place_address']) ? sanitize_text_field(wp_unslash($_POST['lf_business_place_address'])) : '';
		$allowed_embed = [
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
		$map_embed = isset($_POST['lf_business_map_embed']) ? wp_kses(wp_unslash($_POST['lf_business_map_embed']), $allowed_embed) : '';

		lf_update_business_info_value('lf_business_name', $display_name);
		lf_update_business_info_value('lf_business_legal_name', $legal_name);
		lf_update_business_info_value('lf_business_phone_primary', $primary_phone);
		lf_update_business_info_value('lf_business_phone_tracking', $tracking_phone);
		lf_update_business_info_value('lf_business_phone_display', $phone_display);
		lf_update_business_info_value('lf_business_phone', $display_phone);
		lf_update_business_info_value('lf_business_email', isset($_POST['lf_business_email']) ? sanitize_email(wp_unslash($_POST['lf_business_email'])) : '');
		lf_update_business_info_value('lf_business_address_street', $address_street);
		lf_update_business_info_value('lf_business_address_city', $address_city);
		lf_update_business_info_value('lf_business_address_state', $address_state);
		lf_update_business_info_value('lf_business_address_zip', $address_zip);
		lf_update_business_info_value('lf_business_address', $address);
		lf_update_business_info_value('lf_business_service_area_type', $service_area_type);
		lf_update_business_info_value('lf_business_geo', ['lat' => $lat, 'lng' => $lng]);
		lf_update_business_info_value('lf_business_hours', isset($_POST['lf_business_hours']) ? sanitize_textarea_field(wp_unslash($_POST['lf_business_hours'])) : '');
		lf_update_business_info_value('lf_business_category', $category);
		lf_update_business_info_value('lf_business_short_description', $short_desc);
		lf_update_business_info_value('lf_business_primary_image', $primary_image);
		lf_update_business_info_value('lf_business_social_facebook', $social_facebook);
		lf_update_business_info_value('lf_business_social_instagram', $social_instagram);
		lf_update_business_info_value('lf_business_social_youtube', $social_youtube);
		lf_update_business_info_value('lf_business_social_linkedin', $social_linkedin);
		lf_update_business_info_value('lf_business_social_tiktok', $social_tiktok);
		lf_update_business_info_value('lf_business_social_x', $social_x);
		lf_update_business_info_value('lf_business_gbp_url', $gbp_url);
		lf_update_business_info_value('lf_business_same_as', $same_as);
		lf_update_business_info_value('lf_business_founding_year', $founding_year);
		lf_update_business_info_value('lf_business_license_number', $license_number);
		lf_update_business_info_value('lf_business_insurance_statement', $insurance_statement);
		lf_update_business_info_value('lf_business_place_id', $place_id);
		lf_update_business_info_value('lf_business_place_name', $place_name);
		lf_update_business_info_value('lf_business_place_address', $place_address);
		lf_update_business_info_value('lf_business_map_embed', $map_embed);
	}
	$keys = [
		'lf_brand_primary',
		'lf_brand_secondary',
		'lf_brand_tertiary',
		'lf_surface_light',
		'lf_surface_soft',
		'lf_surface_dark',
		'lf_surface_card',
		'lf_text_primary',
		'lf_text_muted',
		'lf_text_inverse',
	];
	foreach ($keys as $key) {
		$val = isset($_POST[$key]) ? sanitize_hex_color(wp_unslash($_POST[$key])) : '';
		update_option('options_' . $key, $val ?: '');
	}
	if (function_exists('update_field')) {
		$global_post_ids = ['lf-global', 'options_lf_global', 'options_lf-global', 'option', 'options'];
		foreach ($global_post_ids as $post_id) {
			update_field('lf_global_logo', $logo_id, $post_id);
			update_field('lf_header_cta_label', isset($_POST['lf_header_cta_label']) ? sanitize_text_field(wp_unslash($_POST['lf_header_cta_label'])) : '', $post_id);
			update_field('lf_header_cta_url', isset($_POST['lf_header_cta_url']) ? esc_url_raw(wp_unslash($_POST['lf_header_cta_url'])) : '', $post_id);
		}
		foreach ($keys as $key) {
			$val = isset($_POST[$key]) ? sanitize_hex_color(wp_unslash($_POST[$key])) : '';
			if ($val) {
				foreach (['lf-branding', 'options_lf_branding', 'options_lf-branding', 'option', 'options'] as $post_id) {
					update_field($key, $val, $post_id);
				}
			}
		}
	}
	if ($logo_id > 0 && $logo_id !== $prev_logo_id && function_exists('lf_branding_auto_from_logo')) {
		lf_branding_auto_from_logo($logo_id);
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-global&saved=1'));
	exit;
}

function lf_ops_render_global_settings_page(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$logo_id = (int) lf_get_global_option('lf_global_logo', 0);
	$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
	$cta_label = (string) lf_get_global_option('lf_header_cta_label', '');
	$cta_url = (string) lf_get_global_option('lf_header_cta_url', '');
	$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
	$entity_name = (string) ($entity['name'] ?? '');
	$entity_legal = (string) ($entity['legal_name'] ?? '');
	$entity_phone_primary = (string) ($entity['phone_primary'] ?? '');
	$entity_phone_tracking = (string) ($entity['phone_tracking'] ?? '');
	$entity_phone_display = (string) ($entity['phone_display_pref'] ?? '');
	$entity_email = (string) ($entity['email'] ?? '');
	$entity_address_parts = $entity['address_parts'] ?? ['street' => '', 'city' => '', 'state' => '', 'zip' => ''];
	$entity_address_street = (string) ($entity_address_parts['street'] ?? '');
	$entity_address_city = (string) ($entity_address_parts['city'] ?? '');
	$entity_address_state = (string) ($entity_address_parts['state'] ?? '');
	$entity_address_zip = (string) ($entity_address_parts['zip'] ?? '');
	$entity_service_area_type = (string) ($entity['service_area_type'] ?? 'address');
	$service_area_titles = [];
	if (post_type_exists('lf_service_area')) {
		$service_area_titles = get_posts([
			'post_type' => 'lf_service_area',
			'post_status' => 'publish',
			'posts_per_page' => 50,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
			'no_found_rows' => true,
		]);
		$service_area_titles = array_values(array_filter(array_map(function ($post) {
			return $post ? get_the_title($post) : '';
		}, $service_area_titles)));
	}
	$service_area_text = $service_area_titles ? implode("\n", $service_area_titles) : __('No service areas yet.', 'leadsforward-core');
	$entity_geo = $entity['geo'] ?? ['lat' => '', 'lng' => ''];
	$entity_hours = (string) ($entity['hours'] ?? '');
	$entity_category = (string) ($entity['category'] ?? 'HomeAndConstructionBusiness');
	$entity_desc = (string) ($entity['description'] ?? '');
	$entity_primary_image_id = (int) ($entity['primary_image_id'] ?? 0);
	$entity_primary_image_url = $entity_primary_image_id ? wp_get_attachment_image_url($entity_primary_image_id, 'medium') : '';
	$entity_social = $entity['social'] ?? [
		'facebook' => '',
		'instagram' => '',
		'youtube' => '',
		'linkedin' => '',
		'tiktok' => '',
		'x' => '',
	];
	$entity_gbp = (string) ($entity['gbp_url'] ?? '');
	$entity_same_as = '';
	if (!empty($entity['same_as']) && is_array($entity['same_as'])) {
		$entity_same_as = implode("\n", $entity['same_as']);
	}
	$entity_founding_year = (string) ($entity['founding_year'] ?? '');
	$entity_license = (string) ($entity['license_number'] ?? '');
	$entity_insurance = (string) ($entity['insurance_statement'] ?? '');
	$maps_api_key = get_option('lf_maps_api_key', '');
	$maps_api_key = is_string($maps_api_key) ? $maps_api_key : '';
	$homepage_niche_slug = (string) get_option('lf_homepage_niche_slug', 'general');
	$design_preset = (string) get_option('lf_global_design_preset', 'clean-precision');
	$niche_registry = function_exists('lf_get_niche_registry') ? lf_get_niche_registry() : [];
	if (empty($niche_registry)) {
		$niche_registry = [
			'general' => ['name' => __('General', 'leadsforward-core')],
		];
	}
	if (!isset($niche_registry[$homepage_niche_slug])) {
		$homepage_niche_slug = 'general';
	}
	$design_presets = function_exists('lf_design_presets') ? lf_design_presets() : [];
	if (empty($design_presets)) {
		$design_presets = [
			'clean-precision' => __('Clean Precision', 'leadsforward-core'),
			'bold-authority' => __('Bold Authority', 'leadsforward-core'),
			'friendly-approachable' => __('Friendly & Approachable', 'leadsforward-core'),
			'high-contrast' => __('High-Contrast Conversion Engine', 'leadsforward-core'),
			'modern-edge' => __('Modern Edge', 'leadsforward-core'),
			'structured-modular' => __('Structured Modular', 'leadsforward-core'),
		];
	}
	if (!isset($design_presets[$design_preset])) {
		$design_preset = 'clean-precision';
	}
	$profile_labels = function_exists('lf_variation_profile_labels') ? lf_variation_profile_labels() : [];
	$design_profile = function_exists('lf_design_preset_to_variation_profile')
		? lf_design_preset_to_variation_profile($design_preset)
		: 'a';
	$design_profile_label = $profile_labels[$design_profile] ?? strtoupper($design_profile);
	$design_overrides_enabled = get_option('lf_design_overrides_enabled', '0') === '1';
	$font_choices = function_exists('lf_design_font_choices') ? lf_design_font_choices() : [];
	$heading_font = (string) get_option('lf_design_heading_font', '');
	$body_font = (string) get_option('lf_design_body_font', '');
	$heading_weight = (string) get_option('lf_design_heading_weight', '');
	$button_radius = (string) get_option('lf_design_button_radius', '');
	$card_radius = (string) get_option('lf_design_card_radius', '');
	$card_shadow = (string) get_option('lf_design_card_shadow', '');
	$section_spacing = (string) get_option('lf_design_section_spacing', '');
	$heading_weights = function_exists('lf_design_heading_weight_choices') ? lf_design_heading_weight_choices() : ['600' => '600', '700' => '700', '800' => '800'];
	$button_radii = function_exists('lf_design_button_radius_choices') ? lf_design_button_radius_choices() : ['sharp' => 'Sharp', 'soft' => 'Soft', 'pill' => 'Pill'];
	$card_radii = function_exists('lf_design_card_radius_choices') ? lf_design_card_radius_choices() : ['tight' => 'Tight', 'medium' => 'Medium', 'round' => 'Round'];
	$card_shadows = function_exists('lf_design_card_shadow_choices') ? lf_design_card_shadow_choices() : ['none' => 'None', 'soft' => 'Soft', 'bold' => 'Bold'];
	$section_spacings = function_exists('lf_design_section_spacing_choices') ? lf_design_section_spacing_choices() : ['compact' => 'Compact', 'normal' => 'Normal', 'airy' => 'Airy'];
	$place_id = function_exists('lf_get_business_info_value') ? (string) lf_get_business_info_value('lf_business_place_id', '') : '';
	$place_name = function_exists('lf_get_business_info_value') ? (string) lf_get_business_info_value('lf_business_place_name', '') : '';
	$place_address = function_exists('lf_get_business_info_value') ? (string) lf_get_business_info_value('lf_business_place_address', '') : '';
	$map_embed = function_exists('lf_get_business_info_value') ? (string) lf_get_business_info_value('lf_business_map_embed', '') : '';
	$get_brand = function (string $key, string $default): string {
		if (function_exists('lf_branding_get_value')) {
			return lf_branding_get_value($key, $default);
		}
		$val = get_option('options_' . $key, $default);
		return is_string($val) && $val !== '' ? $val : $default;
	};
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	$manifester_enabled = get_option('lf_ai_studio_enabled', '0') === '1';
	$manifester_webhook = (string) get_option('lf_ai_studio_webhook', '');
	$manifester_secret = (string) get_option('lf_ai_studio_secret', '');
	$manifester_callback = (string) get_option('lf_ai_studio_callback_url', '');
	$airtable_settings = function_exists('lf_ai_studio_airtable_get_settings')
		? lf_ai_studio_airtable_get_settings()
		: [];
	$airtable_fields = is_array($airtable_settings['fields'] ?? null) ? $airtable_settings['fields'] : [];
	$airtable_field_defaults = function_exists('lf_ai_studio_airtable_default_field_map')
		? lf_ai_studio_airtable_default_field_map()
		: [];
	$airtable_reviews = is_array($airtable_settings['reviews'] ?? null) ? $airtable_settings['reviews'] : [];
	$airtable_review_fields = is_array($airtable_reviews['fields'] ?? null) ? $airtable_reviews['fields'] : [];
	$airtable_review_defaults = function_exists('lf_ai_studio_airtable_reviews_default_field_map')
		? lf_ai_studio_airtable_reviews_default_field_map()
		: [];
	$reviews_sync = isset($_GET['reviews_sync']) ? sanitize_text_field(wp_unslash((string) $_GET['reviews_sync'])) : '';
	$reviews_imported = isset($_GET['reviews_imported']) ? (int) $_GET['reviews_imported'] : 0;
	$reviews_error = isset($_GET['reviews_error']) ? sanitize_text_field(wp_unslash((string) $_GET['reviews_error'])) : '';
	$reviews_project_name = function_exists('lf_ai_studio_airtable_get_project_name_for_reviews')
		? lf_ai_studio_airtable_get_project_name_for_reviews($airtable_settings)
		: '';
	$reviews_last_sync = (int) get_option('lf_ai_airtable_reviews_last_sync', 0);
	$reviews_last_imported = (int) get_option('lf_ai_airtable_reviews_last_imported', 0);
	$seo_settings = get_option('lf_seo_settings', []);
	$seo_header_scripts = is_array($seo_settings)
		? (string) ($seo_settings['scripts']['header'] ?? '')
		: '';
	$show_gtm_header_reminder = trim($seo_header_scripts) === '';
	$seo_settings_url = admin_url('admin.php?page=lf-seo');
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></h1>
		<?php if ($show_gtm_header_reminder) : ?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						/* translators: %s: SEO settings admin URL. */
						wp_kses_post(__('Reminder: add your Google Tag Manager script in <a href="%s">SEO → Header scripts</a>.', 'leadsforward-core')),
						esc_url($seo_settings_url)
					);
					?>
				</p>
			</div>
		<?php endif; ?>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($reviews_sync === '1') : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html(sprintf(__('Reviews sync complete. Imported %d reviews.', 'leadsforward-core'), $reviews_imported)); ?></p>
			</div>
		<?php elseif ($reviews_sync === 'error') : ?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					if ($reviews_error === 'disabled') {
						esc_html_e('Reviews sync is disabled or the reviews table is empty.', 'leadsforward-core');
					} elseif ($reviews_error === 'project') {
						esc_html_e('Reviews sync needs a project name from the manifest or stored project context.', 'leadsforward-core');
					} else {
						echo esc_html($reviews_error !== '' ? $reviews_error : __('Reviews sync failed. Check Airtable settings.', 'leadsforward-core'));
					}
					?>
				</p>
			</div>
		<?php endif; ?>
		<style>
			.lf-settings-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; margin: 1.25rem 0; }
			.lf-settings-panel-header { display: flex; align-items: center; gap: 0.75rem; }
			.lf-settings-panel-header h2 { margin: 0; }
			.lf-settings-toggle { margin-left: auto; font-size: 12px; text-decoration: none; padding: 0.35rem 0.65rem; border-radius: 999px; border: 1px solid #e2e8f0; background: #f8fafc; color: #0f172a; }
			.lf-settings-toggle:hover { background: #e2e8f0; }
			.lf-settings-fields--collapsed { display: none; }
			.lf-settings-panel--collapsed .lf-settings-toggle { background: #0f172a; color: #fff; border-color: #0f172a; }
		</style>
		<form method="post" data-maps-key="<?php echo esc_attr($maps_api_key); ?>">
			<?php wp_nonce_field('lf_global_settings', 'lf_global_settings_nonce'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lf_header_cta_label"><?php esc_html_e('Header CTA label', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="regular-text" id="lf_header_cta_label" name="lf_header_cta_label" value="<?php echo esc_attr($cta_label); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_header_cta_url"><?php esc_html_e('Header CTA URL', 'leadsforward-core'); ?></label></th>
					<td><input type="url" class="large-text" id="lf_header_cta_url" name="lf_header_cta_url" value="<?php echo esc_attr($cta_url); ?>" /></td>
				</tr>
			</table>
			<div class="lf-settings-panel" data-section="manifester_settings">
				<div class="lf-settings-panel-header">
					<h2><?php esc_html_e('Website Manifester Settings', 'leadsforward-core'); ?></h2>
					<button type="button" class="lf-settings-toggle" data-target="manifester_settings" aria-expanded="true">
						<span class="lf-settings-toggle-icon">▾</span>
						<span class="lf-settings-toggle-label"><?php esc_html_e('Collapse', 'leadsforward-core'); ?></span>
					</button>
				</div>
				<div class="lf-settings-panel-body" data-parent="manifester_settings">
					<p class="description"><?php esc_html_e('Configure the orchestrator and Airtable import settings. Manifest uploads and Airtable generation use these values.', 'leadsforward-core'); ?></p>
					<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e('Enable AI', 'leadsforward-core'); ?></th>
								<td><label><input type="checkbox" name="lf_ai_studio_enabled" value="1" <?php checked($manifester_enabled); ?> /> <?php esc_html_e('Allow Manifester runs', 'leadsforward-core'); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><label for="lf_ai_studio_webhook_global"><?php esc_html_e('Orchestrator Webhook URL', 'leadsforward-core'); ?></label></th>
								<td><input type="url" class="large-text" name="lf_ai_studio_webhook" id="lf_ai_studio_webhook_global" value="<?php echo esc_attr($manifester_webhook); ?>" placeholder="https://n8n.example.com/webhook/..." required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="lf_ai_studio_secret_global"><?php esc_html_e('Orchestrator Shared Secret', 'leadsforward-core'); ?></label></th>
								<td><input type="text" class="large-text" name="lf_ai_studio_secret" id="lf_ai_studio_secret_global" value="<?php echo esc_attr($manifester_secret); ?>" required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="lf_ai_studio_callback_url_global"><?php esc_html_e('Callback URL (WordPress)', 'leadsforward-core'); ?></label></th>
								<td>
									<input type="url" class="large-text" name="lf_ai_studio_callback_url" id="lf_ai_studio_callback_url_global" value="<?php echo esc_attr($manifester_callback); ?>" placeholder="https://your-site.com/wp-json/leadsforward/v1/orchestrator" />
									<p class="description"><?php esc_html_e('Use this if n8n cannot reach localhost. For Docker: http://host.docker.internal:10008/wp-json/leadsforward/v1/orchestrator', 'leadsforward-core'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="lf_maps_api_key"><?php esc_html_e('Google Maps API key', 'leadsforward-core'); ?></label></th>
								<td>
									<input type="text" class="large-text" name="lf_maps_api_key" id="lf_maps_api_key" value="<?php echo esc_attr($maps_api_key); ?>" />
									<p class="description"><?php esc_html_e('Used for business place search and map embeds in Business Entity settings.', 'leadsforward-core'); ?></p>
								</td>
							</tr>
							<tr>
								<th colspan="2" style="padding-top: 16px;"><?php esc_html_e('Airtable Connection', 'leadsforward-core'); ?></th>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Enable Airtable', 'leadsforward-core'); ?></th>
								<td><label><input type="checkbox" name="lf_ai_airtable_enabled" value="1" <?php checked(!empty($airtable_settings['enabled'])); ?> /> <?php esc_html_e('Allow Airtable project imports', 'leadsforward-core'); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><label for="lf_ai_airtable_pat_global"><?php esc_html_e('Airtable Personal Access Token', 'leadsforward-core'); ?></label></th>
								<td>
									<input type="password" class="large-text" name="lf_ai_airtable_pat" id="lf_ai_airtable_pat_global" value="<?php echo esc_attr((string) ($airtable_settings['pat'] ?? '')); ?>" autocomplete="new-password" />
									<label style="display:inline-block;margin-top:6px;">
										<input type="checkbox" id="lf-airtable-token-toggle-global" />
										<?php esc_html_e('Show token', 'leadsforward-core'); ?>
									</label>
									<p class="description"><?php esc_html_e('Required scopes: data.records:read and schema.bases:read.', 'leadsforward-core'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="lf_ai_airtable_base_global"><?php esc_html_e('Airtable Base ID', 'leadsforward-core'); ?></label></th>
								<td><input type="text" class="regular-text" name="lf_ai_airtable_base" id="lf_ai_airtable_base_global" value="<?php echo esc_attr((string) ($airtable_settings['base_id'] ?? '')); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="lf_ai_airtable_table_global"><?php esc_html_e('Table (left sidebar)', 'leadsforward-core'); ?></label></th>
								<td><input type="text" class="regular-text" name="lf_ai_airtable_table" id="lf_ai_airtable_table_global" value="<?php echo esc_attr((string) ($airtable_settings['table'] ?? 'Business Info')); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="lf_ai_airtable_view_global"><?php esc_html_e('View (top dropdown)', 'leadsforward-core'); ?></label></th>
								<td>
									<input type="text" class="regular-text" name="lf_ai_airtable_view" id="lf_ai_airtable_view_global" value="<?php echo esc_attr((string) ($airtable_settings['view'] ?? 'Global Sync View (ACTIVE)')); ?>" />
									<p class="description"><?php esc_html_e('Optional. Leave blank to use the table default.', 'leadsforward-core'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Field mapping overrides', 'leadsforward-core'); ?></th>
								<td>
									<details class="lf-airtable-field-map">
										<summary><?php esc_html_e('Override Airtable field names', 'leadsforward-core'); ?></summary>
										<div class="lf-airtable-field-map-grid">
											<?php foreach ($airtable_field_defaults as $key => $label) :
												$value = (string) ($airtable_fields[$key] ?? $label);
												?>
												<label>
													<span><?php echo esc_html($label); ?></span>
													<input type="text" name="lf_ai_airtable_field_map[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" />
												</label>
											<?php endforeach; ?>
										</div>
									</details>
								</td>
							</tr>
							<tr>
								<th colspan="2" style="padding-top: 16px;"><?php esc_html_e('Reviews Sync', 'leadsforward-core'); ?></th>
							</tr>
							<tr>
								<th scope="row"><label for="lf_ai_airtable_reviews_table"><?php esc_html_e('Reviews table', 'leadsforward-core'); ?></label></th>
								<td>
									<input type="text" class="regular-text" name="lf_ai_airtable_reviews_table" id="lf_ai_airtable_reviews_table" value="<?php echo esc_attr((string) ($airtable_reviews['table'] ?? 'Reviews')); ?>" />
									<p class="description"><?php esc_html_e('Optional. Leave blank to skip review imports.', 'leadsforward-core'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="lf_ai_airtable_reviews_view"><?php esc_html_e('Reviews view', 'leadsforward-core'); ?></label></th>
								<td>
									<input type="text" class="regular-text" name="lf_ai_airtable_reviews_view" id="lf_ai_airtable_reviews_view" value="<?php echo esc_attr((string) ($airtable_reviews['view'] ?? '')); ?>" />
									<p class="description"><?php esc_html_e('Optional. Airtable view name only (not a field/column). Leave blank to use the table default.', 'leadsforward-core'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Review field mapping', 'leadsforward-core'); ?></th>
								<td>
									<details class="lf-airtable-field-map">
										<summary><?php esc_html_e('Override review field names', 'leadsforward-core'); ?></summary>
										<div class="lf-airtable-field-map-grid">
											<?php foreach ($airtable_review_defaults as $key => $label) :
												$value = (string) ($airtable_review_fields[$key] ?? $label);
												?>
												<label>
													<span><?php echo esc_html($label); ?></span>
													<input type="text" name="lf_ai_airtable_reviews_field_map[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" />
												</label>
											<?php endforeach; ?>
										</div>
									</details>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Manual sync', 'leadsforward-core'); ?></th>
								<td>
									<p class="description">
										<?php
										if ($reviews_project_name !== '') {
											echo esc_html(sprintf(__('Project filter: %s', 'leadsforward-core'), $reviews_project_name));
										} else {
											esc_html_e('Project filter: not set yet (upload a manifest or generate from Airtable).', 'leadsforward-core');
										}
										?>
									</p>
									<?php if ($reviews_last_sync > 0) : ?>
										<p class="description">
											<?php
											echo esc_html(
												sprintf(
													__('Last sync: %s (%d imported)', 'leadsforward-core'),
													date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $reviews_last_sync),
													$reviews_last_imported
												)
											);
											?>
										</p>
									<?php endif; ?>
									<button type="submit" class="button" form="lf-reviews-sync-form"><?php esc_html_e('Sync Reviews Now', 'leadsforward-core'); ?></button>
								</td>
							</tr>
					</table>
					<p><button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'leadsforward-core'); ?></button></p>
					<script>
						(function() {
							var toggle = document.getElementById('lf-airtable-token-toggle-global');
							var input = document.getElementById('lf_ai_airtable_pat_global');
							if (!toggle || !input) {
								return;
							}
							toggle.addEventListener('change', function() {
								input.type = toggle.checked ? 'text' : 'password';
							});
						})();
					</script>
				</div>
			</div>
			<div class="lf-settings-panel" data-section="business_entity">
				<div class="lf-settings-panel-header">
					<h2><?php esc_html_e('Business Entity', 'leadsforward-core'); ?></h2>
					<button type="button" class="lf-settings-toggle" data-target="business_entity" aria-expanded="true">
						<span class="lf-settings-toggle-icon">▾</span>
						<span class="lf-settings-toggle-label"><?php esc_html_e('Collapse', 'leadsforward-core'); ?></span>
					</button>
				</div>
				<div class="lf-settings-panel-body" data-parent="business_entity">
					<p class="description"><?php esc_html_e('Single source of truth for NAP, schema, and local SEO. This data is used across the site.', 'leadsforward-core'); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e('Logo', 'leadsforward-core'); ?></th>
							<td>
								<div style="display:flex;align-items:center;gap:1rem;">
									<div>
										<img id="lf-global-logo-preview" src="<?php echo esc_url($logo_url); ?>" style="max-height:60px;<?php echo $logo_url ? '' : 'display:none;'; ?>" alt="" />
									</div>
									<input type="hidden" name="lf_global_logo" id="lf_global_logo" value="<?php echo esc_attr((string) $logo_id); ?>" />
									<button type="button" class="button" id="lf-global-logo-select"><?php esc_html_e('Select Logo', 'leadsforward-core'); ?></button>
									<button type="button" class="button" id="lf-global-logo-clear"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_name"><?php esc_html_e('Business name (display)', 'leadsforward-core'); ?></label></th>
							<td><input type="text" class="regular-text" id="lf_business_name" name="lf_business_name" value="<?php echo esc_attr($entity_name); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_legal_name"><?php esc_html_e('Business name (legal)', 'leadsforward-core'); ?></label></th>
							<td><input type="text" class="regular-text" id="lf_business_legal_name" name="lf_business_legal_name" value="<?php echo esc_attr($entity_legal); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_homepage_niche_slug"><?php esc_html_e('Niche', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_homepage_niche_slug" id="lf_homepage_niche_slug">
									<?php foreach ($niche_registry as $slug => $niche) :
										if (!empty($niche['hidden'])) {
											continue;
										}
										$name = is_array($niche) ? (string) ($niche['name'] ?? $slug) : (string) $slug;
										?>
										<option value="<?php echo esc_attr((string) $slug); ?>" <?php selected($homepage_niche_slug === (string) $slug); ?>>
											<?php echo esc_html($name); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e('Sets the default service blueprint and AI defaults for this site.', 'leadsforward-core'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_phone_primary"><?php esc_html_e('Primary phone', 'leadsforward-core'); ?></label></th>
							<td><input type="text" class="regular-text" id="lf_business_phone_primary" name="lf_business_phone_primary" value="<?php echo esc_attr($entity_phone_primary); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_phone_tracking"><?php esc_html_e('Tracking phone (optional)', 'leadsforward-core'); ?></label></th>
							<td><input type="text" class="regular-text" id="lf_business_phone_tracking" name="lf_business_phone_tracking" value="<?php echo esc_attr($entity_phone_tracking); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Display phone', 'leadsforward-core'); ?></th>
							<td>
								<select name="lf_business_phone_display">
									<option value="primary" <?php selected($entity_phone_display !== 'tracking'); ?>><?php esc_html_e('Primary phone', 'leadsforward-core'); ?></option>
									<option value="tracking" <?php selected($entity_phone_display === 'tracking'); ?>><?php esc_html_e('Tracking phone', 'leadsforward-core'); ?></option>
								</select>
								<p class="description"><?php esc_html_e('Controls which phone displays across the site.', 'leadsforward-core'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_email"><?php esc_html_e('Email', 'leadsforward-core'); ?></label></th>
							<td><input type="email" class="regular-text" id="lf_business_email" name="lf_business_email" value="<?php echo esc_attr($entity_email); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Address (NAP)', 'leadsforward-core'); ?></th>
							<td>
								<input type="text" class="large-text" name="lf_business_address_street" placeholder="<?php esc_attr_e('Street address', 'leadsforward-core'); ?>" value="<?php echo esc_attr($entity_address_street); ?>" />
								<div style="display:flex;gap:10px;margin-top:6px;flex-wrap:wrap;">
									<input type="text" class="regular-text" name="lf_business_address_city" placeholder="<?php esc_attr_e('City', 'leadsforward-core'); ?>" value="<?php echo esc_attr($entity_address_city); ?>" />
									<input type="text" class="regular-text" name="lf_business_address_state" placeholder="<?php esc_attr_e('State', 'leadsforward-core'); ?>" value="<?php echo esc_attr($entity_address_state); ?>" />
									<input type="text" class="regular-text" name="lf_business_address_zip" placeholder="<?php esc_attr_e('ZIP', 'leadsforward-core'); ?>" value="<?php echo esc_attr($entity_address_zip); ?>" />
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_place_search"><?php esc_html_e('Search business on Google Maps', 'leadsforward-core'); ?></label></th>
							<td>
								<input type="text" class="large-text" id="lf_business_place_search" placeholder="<?php esc_attr_e('Start typing your business name...', 'leadsforward-core'); ?>" value="<?php echo esc_attr($place_name); ?>" />
								<input type="hidden" name="lf_business_place_id" id="lf_business_place_id" value="<?php echo esc_attr($place_id); ?>" />
								<input type="hidden" name="lf_business_place_name" id="lf_business_place_name" value="<?php echo esc_attr($place_name); ?>" />
								<input type="hidden" name="lf_business_place_address" id="lf_business_place_address" value="<?php echo esc_attr($place_address); ?>" />
								<p class="description" id="lf_place_selected">
									<?php echo $place_name !== '' ? esc_html(sprintf(__('Selected: %1$s (%2$s)', 'leadsforward-core'), $place_name, $place_address)) : esc_html__('No place selected yet.', 'leadsforward-core'); ?>
								</p>
								<p class="description" id="lf_maps_status" style="color:#b45309;"></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_map_embed"><?php esc_html_e('Map embed override (optional)', 'leadsforward-core'); ?></label></th>
							<td>
								<textarea class="large-text" name="lf_business_map_embed" id="lf_business_map_embed" rows="3"><?php echo esc_textarea($map_embed); ?></textarea>
								<p class="description"><?php esc_html_e('Paste a custom iframe embed if you prefer. If empty, the selected Google Maps place will be used.', 'leadsforward-core'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Service area type', 'leadsforward-core'); ?></th>
							<td>
								<select name="lf_business_service_area_type">
									<option value="address" <?php selected($entity_service_area_type !== 'service_area'); ?>><?php esc_html_e('Address-based business', 'leadsforward-core'); ?></option>
									<option value="service_area" <?php selected($entity_service_area_type === 'service_area'); ?>><?php esc_html_e('Service-area business (SAB)', 'leadsforward-core'); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Service areas list', 'leadsforward-core'); ?></th>
							<td>
								<textarea class="large-text" rows="4" readonly><?php echo esc_textarea($service_area_text); ?></textarea>
								<p class="description">
									<?php
									printf(
										'%s <a href="%s">%s</a>.',
										esc_html__('To edit service areas, manage the Service Areas pages.', 'leadsforward-core'),
										esc_url(admin_url('edit.php?post_type=lf_service_area')),
										esc_html__('Go to Service Areas', 'leadsforward-core')
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Latitude / Longitude', 'leadsforward-core'); ?></th>
							<td>
								<div style="display:flex;gap:10px;flex-wrap:wrap;">
									<input type="number" step="any" class="regular-text" name="lf_business_geo_lat" placeholder="<?php esc_attr_e('Latitude', 'leadsforward-core'); ?>" value="<?php echo esc_attr((string) ($entity_geo['lat'] ?? '')); ?>" />
									<input type="number" step="any" class="regular-text" name="lf_business_geo_lng" placeholder="<?php esc_attr_e('Longitude', 'leadsforward-core'); ?>" value="<?php echo esc_attr((string) ($entity_geo['lng'] ?? '')); ?>" />
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_hours"><?php esc_html_e('Hours', 'leadsforward-core'); ?></label></th>
							<td><textarea class="large-text" id="lf_business_hours" name="lf_business_hours" rows="3"><?php echo esc_textarea($entity_hours); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_category"><?php esc_html_e('Primary category', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_business_category" id="lf_business_category">
									<?php
									$categories = [
										'HomeAndConstructionBusiness' => __('Home & Construction Business', 'leadsforward-core'),
										'GeneralContractor' => __('General Contractor', 'leadsforward-core'),
										'RoofingContractor' => __('Roofing Contractor', 'leadsforward-core'),
										'Plumber' => __('Plumber', 'leadsforward-core'),
										'HVACBusiness' => __('HVAC Business', 'leadsforward-core'),
										'LandscapingBusiness' => __('Landscaping Business', 'leadsforward-core'),
										'LocalBusiness' => __('Local Business (generic)', 'leadsforward-core'),
									];
									foreach ($categories as $value => $label) {
										echo '<option value="' . esc_attr($value) . '"' . selected($entity_category === $value, true, false) . '>' . esc_html($label) . '</option>';
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_short_description"><?php esc_html_e('Short description', 'leadsforward-core'); ?></label></th>
							<td><textarea class="large-text" id="lf_business_short_description" name="lf_business_short_description" rows="3"><?php echo esc_textarea($entity_desc); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Primary image', 'leadsforward-core'); ?></th>
							<td>
								<div style="display:flex;align-items:center;gap:1rem;">
									<div>
										<img id="lf-entity-primary-image-preview" src="<?php echo esc_url($entity_primary_image_url); ?>" style="max-height:80px;<?php echo $entity_primary_image_url ? '' : 'display:none;'; ?>" alt="" />
									</div>
									<input type="hidden" name="lf_business_primary_image" id="lf_business_primary_image" value="<?php echo esc_attr((string) $entity_primary_image_id); ?>" />
									<button type="button" class="button" id="lf-entity-primary-image-select"><?php esc_html_e('Select Image', 'leadsforward-core'); ?></button>
									<button type="button" class="button" id="lf-entity-primary-image-clear"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
								</div>
								<p class="description"><?php esc_html_e('Used in LocalBusiness schema as the primary business image.', 'leadsforward-core'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_gbp_url"><?php esc_html_e('Google Business Profile URL', 'leadsforward-core'); ?></label></th>
							<td><input type="url" class="large-text" id="lf_business_gbp_url" name="lf_business_gbp_url" value="<?php echo esc_attr($entity_gbp); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Social profiles', 'leadsforward-core'); ?></th>
							<td>
								<input type="url" class="large-text" name="lf_business_social_facebook" placeholder="<?php esc_attr_e('Facebook URL', 'leadsforward-core'); ?>" value="<?php echo esc_attr((string) ($entity_social['facebook'] ?? '')); ?>" />
								<input type="url" class="large-text" name="lf_business_social_instagram" placeholder="<?php esc_attr_e('Instagram URL', 'leadsforward-core'); ?>" value="<?php echo esc_attr((string) ($entity_social['instagram'] ?? '')); ?>" style="margin-top:6px;" />
								<input type="url" class="large-text" name="lf_business_social_youtube" placeholder="<?php esc_attr_e('YouTube URL', 'leadsforward-core'); ?>" value="<?php echo esc_attr((string) ($entity_social['youtube'] ?? '')); ?>" style="margin-top:6px;" />
								<input type="url" class="large-text" name="lf_business_social_linkedin" placeholder="<?php esc_attr_e('LinkedIn URL', 'leadsforward-core'); ?>" value="<?php echo esc_attr((string) ($entity_social['linkedin'] ?? '')); ?>" style="margin-top:6px;" />
								<input type="url" class="large-text" name="lf_business_social_tiktok" placeholder="<?php esc_attr_e('TikTok URL', 'leadsforward-core'); ?>" value="<?php echo esc_attr((string) ($entity_social['tiktok'] ?? '')); ?>" style="margin-top:6px;" />
								<input type="url" class="large-text" name="lf_business_social_x" placeholder="<?php esc_attr_e('X (Twitter) URL', 'leadsforward-core'); ?>" value="<?php echo esc_attr((string) ($entity_social['x'] ?? '')); ?>" style="margin-top:6px;" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_same_as"><?php esc_html_e('sameAs links (optional)', 'leadsforward-core'); ?></label></th>
							<td>
								<textarea class="large-text" id="lf_business_same_as" name="lf_business_same_as" rows="3" placeholder="<?php esc_attr_e("One URL per line", 'leadsforward-core'); ?>"><?php echo esc_textarea($entity_same_as); ?></textarea>
								<p class="description"><?php esc_html_e('Used in schema to point to official profiles and directory listings.', 'leadsforward-core'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_founding_year"><?php esc_html_e('Founding year (optional)', 'leadsforward-core'); ?></label></th>
							<td><input type="text" class="regular-text" id="lf_business_founding_year" name="lf_business_founding_year" value="<?php echo esc_attr($entity_founding_year); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_license_number"><?php esc_html_e('License number (optional)', 'leadsforward-core'); ?></label></th>
							<td><input type="text" class="regular-text" id="lf_business_license_number" name="lf_business_license_number" value="<?php echo esc_attr($entity_license); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_business_insurance_statement"><?php esc_html_e('Insurance statement (optional)', 'leadsforward-core'); ?></label></th>
							<td><textarea class="large-text" id="lf_business_insurance_statement" name="lf_business_insurance_statement" rows="2"><?php echo esc_textarea($entity_insurance); ?></textarea></td>
						</tr>
					</table>
				</div>
			</div>
			<div class="lf-settings-panel" data-section="global_design">
				<div class="lf-settings-panel-header">
					<h2><?php esc_html_e('Global Design', 'leadsforward-core'); ?></h2>
					<button type="button" class="lf-settings-toggle" data-target="global_design" aria-expanded="true">
						<span class="lf-settings-toggle-icon">▾</span>
						<span class="lf-settings-toggle-label"><?php esc_html_e('Collapse', 'leadsforward-core'); ?></span>
					</button>
				</div>
				<div class="lf-settings-panel-body" data-parent="global_design">
					<p class="description"><?php esc_html_e('Pick a design system that changes the site-wide look and feel.', 'leadsforward-core'); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="lf_global_design_preset"><?php esc_html_e('Design preset', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_global_design_preset" id="lf_global_design_preset">
									<?php foreach ($design_presets as $slug => $label) : ?>
										<option value="<?php echo esc_attr($slug); ?>" <?php selected($design_preset === $slug); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e('Applies global styles to typography, surfaces, buttons, and section rhythm.', 'leadsforward-core'); ?>
									<?php echo ' ' . esc_html(sprintf(__('Synced variation profile: %s.', 'leadsforward-core'), $design_profile_label)); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Enable custom overrides', 'leadsforward-core'); ?></th>
							<td>
								<label>
									<input type="checkbox" name="lf_design_overrides_enabled" value="1" <?php checked($design_overrides_enabled); ?> />
									<?php esc_html_e('Allow custom typography, buttons, and spacing overrides.', 'leadsforward-core'); ?>
								</label>
								<p class="description"><?php esc_html_e('Overrides apply on top of the preset. Disable to return to preset-only styles.', 'leadsforward-core'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_design_heading_font"><?php esc_html_e('Heading font', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_design_heading_font" id="lf_design_heading_font">
									<option value=""><?php esc_html_e('Use preset default', 'leadsforward-core'); ?></option>
									<?php foreach ($font_choices as $key => $label) : ?>
										<option value="<?php echo esc_attr($key); ?>" <?php selected($heading_font === $key); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_design_body_font"><?php esc_html_e('Body font', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_design_body_font" id="lf_design_body_font">
									<option value=""><?php esc_html_e('Use preset default', 'leadsforward-core'); ?></option>
									<?php foreach ($font_choices as $key => $label) : ?>
										<option value="<?php echo esc_attr($key); ?>" <?php selected($body_font === $key); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_design_heading_weight"><?php esc_html_e('Heading weight', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_design_heading_weight" id="lf_design_heading_weight">
									<option value=""><?php esc_html_e('Use preset default', 'leadsforward-core'); ?></option>
									<?php foreach ($heading_weights as $key => $label) : ?>
										<option value="<?php echo esc_attr($key); ?>" <?php selected($heading_weight === (string) $key); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_design_button_radius"><?php esc_html_e('Button shape', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_design_button_radius" id="lf_design_button_radius">
									<option value=""><?php esc_html_e('Use preset default', 'leadsforward-core'); ?></option>
									<?php foreach ($button_radii as $key => $label) : ?>
										<option value="<?php echo esc_attr($key); ?>" <?php selected($button_radius === (string) $key); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_design_card_radius"><?php esc_html_e('Card radius', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_design_card_radius" id="lf_design_card_radius">
									<option value=""><?php esc_html_e('Use preset default', 'leadsforward-core'); ?></option>
									<?php foreach ($card_radii as $key => $label) : ?>
										<option value="<?php echo esc_attr($key); ?>" <?php selected($card_radius === (string) $key); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_design_card_shadow"><?php esc_html_e('Card shadow', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_design_card_shadow" id="lf_design_card_shadow">
									<option value=""><?php esc_html_e('Use preset default', 'leadsforward-core'); ?></option>
									<?php foreach ($card_shadows as $key => $label) : ?>
										<option value="<?php echo esc_attr($key); ?>" <?php selected($card_shadow === (string) $key); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="lf_design_section_spacing"><?php esc_html_e('Section spacing', 'leadsforward-core'); ?></label></th>
							<td>
								<select name="lf_design_section_spacing" id="lf_design_section_spacing">
									<option value=""><?php esc_html_e('Use preset default', 'leadsforward-core'); ?></option>
									<?php foreach ($section_spacings as $key => $label) : ?>
										<option value="<?php echo esc_attr($key); ?>" <?php selected($section_spacing === (string) $key); ?>>
											<?php echo esc_html($label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>
				</div>
			</div>
			<div class="lf-settings-panel" data-section="branding">
				<div class="lf-settings-panel-header">
					<h2><?php esc_html_e('Branding', 'leadsforward-core'); ?></h2>
					<button type="button" class="lf-settings-toggle" data-target="branding" aria-expanded="true">
						<span class="lf-settings-toggle-icon">▾</span>
						<span class="lf-settings-toggle-label"><?php esc_html_e('Collapse', 'leadsforward-core'); ?></span>
					</button>
				</div>
				<div class="lf-settings-panel-body" data-parent="branding">
					<p class="description"><?php esc_html_e('When you upload a logo, the primary colors sync automatically. You can adjust these anytime.', 'leadsforward-core'); ?></p>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e('Primary', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_brand_primary" value="<?php echo esc_attr($get_brand('lf_brand_primary', '#2563eb')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Secondary', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_brand_secondary" value="<?php echo esc_attr($get_brand('lf_brand_secondary', '#0ea5e9')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Tertiary', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_brand_tertiary" value="<?php echo esc_attr($get_brand('lf_brand_tertiary', '#f97316')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Light background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_light" value="<?php echo esc_attr($get_brand('lf_surface_light', '#ffffff')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Soft background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_soft" value="<?php echo esc_attr($get_brand('lf_surface_soft', '#f8fafc')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Dark background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_dark" value="<?php echo esc_attr($get_brand('lf_surface_dark', '#0f172a')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Card background', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_surface_card" value="<?php echo esc_attr($get_brand('lf_surface_card', '#ffffff')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Primary text', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_text_primary" value="<?php echo esc_attr($get_brand('lf_text_primary', '#0f172a')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Muted text', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_text_muted" value="<?php echo esc_attr($get_brand('lf_text_muted', '#64748b')); ?>" /></td></tr>
						<tr><th scope="row"><?php esc_html_e('Inverse text', 'leadsforward-core'); ?></th><td><input type="text" class="lf-color" name="lf_text_inverse" value="<?php echo esc_attr($get_brand('lf_text_inverse', '#ffffff')); ?>" /></td></tr>
					</table>
				</div>
			</div>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Global Settings', 'leadsforward-core'); ?></button></p>
		</form>
		<form id="lf-reviews-sync-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('lf_reviews_sync', 'lf_reviews_sync_nonce'); ?>
			<input type="hidden" name="action" value="lf_reviews_sync" />
		</form>
	</div>
	<script>
		(function () {
			var storageKey = 'lf_global_settings_collapsed';
			var collapsed = {};
			try {
				collapsed = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
			} catch (e) {
				collapsed = {};
			}
			function applyCollapse(type) {
				var isCollapsed = !!collapsed[type];
				var panel = document.querySelector('.lf-settings-panel[data-section="' + type + '"]');
				var body = document.querySelector('.lf-settings-panel-body[data-parent="' + type + '"]');
				if (panel && body) {
					panel.classList.toggle('lf-settings-panel--collapsed', isCollapsed);
					body.classList.toggle('lf-settings-fields--collapsed', isCollapsed);
					var toggle = panel.querySelector('.lf-settings-toggle');
					if (toggle) {
						toggle.setAttribute('aria-expanded', (!isCollapsed).toString());
						var icon = toggle.querySelector('.lf-settings-toggle-icon');
						var label = toggle.querySelector('.lf-settings-toggle-label');
						if (icon) icon.textContent = isCollapsed ? '▸' : '▾';
						if (label) label.textContent = isCollapsed ? 'Expand' : 'Collapse';
					}
				}
			}
			var frame;
			var selectBtn = document.getElementById('lf-global-logo-select');
			var clearBtn = document.getElementById('lf-global-logo-clear');
			var input = document.getElementById('lf_global_logo');
			var preview = document.getElementById('lf-global-logo-preview');
			if (selectBtn) {
				selectBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (frame) { frame.open(); return; }
					frame = wp.media({ title: 'Select Logo', button: { text: 'Use logo' }, multiple: false });
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						if (input) input.value = attachment.id;
						if (preview) { preview.src = attachment.url; preview.style.display = 'block'; }
					});
					frame.open();
				});
			}
			if (clearBtn) {
				clearBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (input) input.value = '';
					if (preview) { preview.src = ''; preview.style.display = 'none'; }
				});
			}
			var toggles = document.querySelectorAll('.lf-settings-toggle');
			toggles.forEach(function (toggle) {
				var type = toggle.getAttribute('data-target');
				applyCollapse(type);
				toggle.addEventListener('click', function () {
					collapsed[type] = !collapsed[type];
					try { window.localStorage.setItem(storageKey, JSON.stringify(collapsed)); } catch (e) {}
					applyCollapse(type);
				});
			});
			var imageFrame;
			var imageSelectBtn = document.getElementById('lf-entity-primary-image-select');
			var imageClearBtn = document.getElementById('lf-entity-primary-image-clear');
			var imageInput = document.getElementById('lf_business_primary_image');
			var imagePreview = document.getElementById('lf-entity-primary-image-preview');
			if (imageSelectBtn) {
				imageSelectBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (imageFrame) { imageFrame.open(); return; }
					imageFrame = wp.media({ title: 'Select Image', button: { text: 'Use image' }, multiple: false });
					imageFrame.on('select', function () {
						var attachment = imageFrame.state().get('selection').first().toJSON();
						if (imageInput) imageInput.value = attachment.id;
						if (imagePreview) { imagePreview.src = attachment.url; imagePreview.style.display = 'block'; }
					});
					imageFrame.open();
				});
			}
			if (imageClearBtn) {
				imageClearBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (imageInput) imageInput.value = '';
					if (imagePreview) { imagePreview.src = ''; imagePreview.style.display = 'none'; }
				});
			}
			function loadPlacesApi(key, callback) {
				var status = document.getElementById('lf_maps_status');
				if (window.google && window.google.maps && window.google.maps.places) {
					callback();
					return;
				}
				if (!key) {
					if (status) {
						status.textContent = 'Add your Google Maps API key in LeadsForward -> Global Settings -> Website Manifester Settings to enable search.';
					}
					return;
				}
				var scriptId = 'lf-maps-places';
				if (document.getElementById(scriptId)) {
					return;
				}
				var script = document.createElement('script');
				script.id = scriptId;
				script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&libraries=places';
				script.async = true;
				script.onerror = function () {
					if (status) {
						status.textContent = 'Failed to load Google Maps. Check API key restrictions and billing.';
					}
				};
				script.onload = callback;
				document.head.appendChild(script);
			}

			function initPlacesSearch() {
				var input = document.getElementById('lf_business_place_search');
				var placeId = document.getElementById('lf_business_place_id');
				var placeName = document.getElementById('lf_business_place_name');
				var placeAddress = document.getElementById('lf_business_place_address');
				var selected = document.getElementById('lf_place_selected');
				var status = document.getElementById('lf_maps_status');
				if (!input) {
					return;
				}
				var form = input.closest('form');
				var key = form ? (form.getAttribute('data-maps-key') || '') : '';
				key = key.trim();
				if (!key) {
					if (selected) {
						selected.textContent = 'Add your Google Maps API key in LeadsForward -> Global Settings -> Website Manifester Settings to enable search.';
					}
					if (status) {
						status.textContent = '';
					}
					return;
				}
				if (status) {
					status.textContent = 'Loading Google Maps...';
				}
				window.gm_authFailure = function () {
					if (status) {
						status.textContent = 'Google Maps auth failed. Check key restrictions and billing.';
					}
				};
				loadPlacesApi(key, function () {
					if (!window.google || !google.maps || !google.maps.places) {
						if (status) {
							status.textContent = 'Google Maps loaded without Places library. Check API settings.';
						}
						return;
					}
					if (status) {
						status.textContent = '';
					}
					var ac = new google.maps.places.Autocomplete(input, {
						fields: ['place_id', 'name', 'formatted_address']
					});
					ac.addListener('place_changed', function () {
						var place = ac.getPlace();
						if (!place || !place.place_id) {
							return;
						}
						if (placeId) placeId.value = place.place_id || '';
						if (placeName) placeName.value = place.name || '';
						if (placeAddress) placeAddress.value = place.formatted_address || '';
						if (selected) {
							selected.textContent = 'Selected: ' + (place.name || '') + (place.formatted_address ? ' (' + place.formatted_address + ')' : '');
						}
					});
				});
			}

			initPlacesSearch();
		})();
		jQuery(function ($) {
			if ($.fn.wpColorPicker) {
				$('.lf-color').wpColorPicker();
			}
		});
	</script>
	<?php
}

function lf_ops_remove_theme_options_menu(): void {
	// Remove Theme Options submenus under Appearance to keep everything under LeadsForward.
	foreach (['lf-theme-options', 'lf-global', 'lf-ctas', 'lf-schema', 'lf-homepage', 'lf-variation', 'lf-business-info'] as $slug) {
		remove_submenu_page('themes.php', $slug);
	}
}
