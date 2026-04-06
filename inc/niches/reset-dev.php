<?php
/**
 * Development-only site reset. Rerun setup safely. No frontend, no AJAX, no production.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_DEV_RESET_OPTION_IDS   = 'lf_wizard_created_ids';
const LF_DEV_RESET_OPTION_LOG  = 'lf_dev_reset_log';
const LF_DEV_RESET_LOG_MAX     = 20;
const LF_DEV_RESET_MENU_NAMES  = ['Header Menu', 'Footer Menu'];
const LF_DEV_RESET_MENU_LOCATIONS = ['header_menu', 'footer_menu'];

function lf_dev_reset_delete_posts_by_type(string $post_type): void {
	$posts = get_posts([
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);
	foreach ($posts as $id) {
		wp_delete_post((int) $id, true);
	}
}

function lf_dev_reset_delete_all_attachments(): void {
	$attachment_ids = get_posts([
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	]);
	foreach ((array) $attachment_ids as $attachment_id) {
		wp_delete_attachment((int) $attachment_id, true);
	}
}

function lf_dev_reset_delete_all_terms(string $taxonomy): void {
	if (!taxonomy_exists($taxonomy)) {
		return;
	}
	$terms = get_terms([
		'taxonomy' => $taxonomy,
		'hide_empty' => false,
		'fields' => 'ids',
	]);
	if (is_wp_error($terms) || !is_array($terms)) {
		return;
	}
	foreach ($terms as $term_id) {
		wp_delete_term((int) $term_id, $taxonomy);
	}
}

/**
 * Whether the site URL looks like a dev machine (Local WP, .test, etc.).
 */
function lf_dev_reset_site_url_looks_local(): bool {
	$host = wp_parse_url((string) home_url(), PHP_URL_HOST);
	if (!is_string($host) || $host === '') {
		return false;
	}
	$host = strtolower($host);
	if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
		return true;
	}
	foreach (['.local', '.test', '.localhost'] as $suffix) {
		if (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) {
			return true;
		}
	}
	return false;
}

/**
 * True only on local/development environments or when LF_DEV_RESET_ENABLED is set.
 *
 * WP_DEBUG alone is not enough: staging/production sites often enable debug temporarily, which must
 * not unlock destructive reset (clears homepage config, deletes content).
 *
 * LF_DEV_RESET_ENABLED accepts boolean true, 1, or strings "1"/"true"/"yes"/"on" (wp-config often
 * uses 1 by mistake; strict === true would incorrectly block reset).
 */
function lf_dev_reset_allowed(): bool {
	if (defined('LF_DEV_RESET_ENABLED')) {
		$raw = LF_DEV_RESET_ENABLED;
		if (filter_var($raw, FILTER_VALIDATE_BOOLEAN)) {
			return (bool) apply_filters('lf_dev_reset_allowed', true);
		}
		return (bool) apply_filters('lf_dev_reset_allowed', false);
	}
	$env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : '';
	$allowed = in_array($env, ['local', 'development'], true);
	if (!$allowed && defined('WP_ENV')) {
		$wp_env = strtolower((string) WP_ENV);
		$allowed = in_array($wp_env, ['local', 'development'], true);
	}
	if (!$allowed) {
		$allowed = lf_dev_reset_site_url_looks_local();
	}
	return (bool) apply_filters('lf_dev_reset_allowed', $allowed);
}

add_action('admin_init', 'lf_dev_reset_handle_post', 5);

function lf_dev_reset_handle_post(): void {
	if (!isset($_POST['lf_dev_reset']) || $_POST['lf_dev_reset'] !== '1') {
		return;
	}
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!isset($_POST['lf_dev_reset_ack']) || $_POST['lf_dev_reset_ack'] !== '1') {
		wp_safe_redirect(admin_url('admin.php?page=lf-ops&reset_error=ack'));
		exit;
	}
	if (!isset($_POST['lf_dev_reset_confirm']) || trim($_POST['lf_dev_reset_confirm']) !== 'RESET') {
		wp_safe_redirect(admin_url('admin.php?page=lf-ops&reset_error=confirm'));
		exit;
	}
	check_admin_referer('lf_dev_reset', 'lf_dev_reset_nonce');

	if (!lf_dev_reset_allowed()) {
		wp_safe_redirect(admin_url('admin.php?page=lf-ops&reset_error=not_allowed'));
		exit;
	}

	lf_dev_reset_run();
	wp_safe_redirect(admin_url('admin.php?page=lf-ops&reset_done=1'));
	exit;
}

function lf_dev_reset_render_page(): void {
	if (!lf_dev_reset_allowed() || !current_user_can('manage_options')) {
		return;
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-ops'));
	exit;
}

/**
 * Delete setup content, menus, reset ACF options, clear setup flag, log.
 * Uses stored IDs when present; otherwise finds core pages by slug and all services/service areas.
 */
function lf_dev_reset_run(): void {
	if (!lf_dev_reset_allowed()) {
		return;
	}
	$preserve_pages = [];
	$privacy = get_page_by_path('privacy-policy', OBJECT, 'page');
	if ($privacy instanceof \WP_Post) {
		$preserve_pages[] = (int) $privacy->ID;
	}
	$terms = get_page_by_path('terms-of-service', OBJECT, 'page');
	if ($terms instanceof \WP_Post) {
		$preserve_pages[] = (int) $terms->ID;
	}

	$ids = get_option(LF_DEV_RESET_OPTION_IDS, []);
	$ids = is_array($ids) ? $ids : [];

	$page_ids    = $ids['page_ids'] ?? [];
	$service_ids = $ids['service_ids'] ?? [];
	$area_ids    = $ids['service_area_ids'] ?? [];

	// Fallback when no IDs were ever stored (e.g. site set up before tracking): delete by convention.
	if (empty($page_ids) && empty($service_ids) && empty($area_ids)) {
		$slugs = function_exists('lf_wizard_required_page_slugs') ? lf_wizard_required_page_slugs() : [];
		foreach ($slugs as $slug) {
			$page = get_page_by_path($slug, OBJECT, 'page');
			if ($page && $page->ID) {
				wp_delete_post($page->ID, true);
			}
		}
	} else {
		foreach ($page_ids as $id) {
			if (is_numeric($id)) {
				wp_delete_post((int) $id, true);
			}
		}
		foreach ($service_ids as $id) {
			if (is_numeric($id)) {
				wp_delete_post((int) $id, true);
			}
		}
		foreach ($area_ids as $id) {
			if (is_numeric($id)) {
				wp_delete_post((int) $id, true);
			}
		}
	}

	$pages = get_posts([
		'post_type'      => 'page',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]);
	foreach ($pages as $id) {
		if (in_array((int) $id, $preserve_pages, true)) {
			continue;
		}
		wp_delete_post((int) $id, true);
	}
	lf_dev_reset_delete_posts_by_type('post');
	lf_dev_reset_delete_posts_by_type('lf_service');
	lf_dev_reset_delete_posts_by_type('lf_service_area');
	lf_dev_reset_delete_posts_by_type('lf_project');
	lf_dev_reset_delete_posts_by_type('lf_faq');
	lf_dev_reset_delete_posts_by_type('lf_testimonial');
	lf_dev_reset_delete_posts_by_type('lf_ai_job');
	lf_dev_reset_delete_all_attachments();
	lf_dev_reset_delete_all_terms('lf_project_type');

	$menus = wp_get_nav_menus();
	foreach ($menus as $menu) {
		if (in_array($menu->name, LF_DEV_RESET_MENU_NAMES, true)) {
			wp_delete_nav_menu($menu->term_id);
		}
	}
	$locations = get_theme_mod('nav_menu_locations') ?: [];
	foreach (LF_DEV_RESET_MENU_LOCATIONS as $loc) {
		unset($locations[$loc]);
	}
	set_theme_mod('nav_menu_locations', $locations);

	if (function_exists('lf_update_business_info_value')) {
		lf_update_business_info_value('lf_business_name', '');
		lf_update_business_info_value('lf_business_legal_name', '');
		lf_update_business_info_value('lf_business_phone', '');
		lf_update_business_info_value('lf_business_phone_primary', '');
		lf_update_business_info_value('lf_business_phone_tracking', '');
		lf_update_business_info_value('lf_business_phone_display', '');
		lf_update_business_info_value('lf_business_email', '');
		lf_update_business_info_value('lf_business_address', '');
		lf_update_business_info_value('lf_business_address_street', '');
		lf_update_business_info_value('lf_business_address_city', '');
		lf_update_business_info_value('lf_business_address_state', '');
		lf_update_business_info_value('lf_business_address_zip', '');
		lf_update_business_info_value('lf_business_service_area_type', '');
		lf_update_business_info_value('lf_business_service_areas', '');
		lf_update_business_info_value('lf_business_hours', '');
		lf_update_business_info_value('lf_business_geo', ['lat' => '', 'lng' => '']);
		lf_update_business_info_value('lf_business_category', '');
		lf_update_business_info_value('lf_business_short_description', '');
		lf_update_business_info_value('lf_business_logo', 0);
		lf_update_business_info_value('lf_business_primary_image', 0);
		lf_update_business_info_value('lf_business_social_facebook', '');
		lf_update_business_info_value('lf_business_social_instagram', '');
		lf_update_business_info_value('lf_business_social_youtube', '');
		lf_update_business_info_value('lf_business_social_linkedin', '');
		lf_update_business_info_value('lf_business_social_tiktok', '');
		lf_update_business_info_value('lf_business_social_x', '');
		lf_update_business_info_value('lf_business_gbp_url', '');
		lf_update_business_info_value('lf_business_same_as', '');
		lf_update_business_info_value('lf_business_founding_year', '');
		lf_update_business_info_value('lf_business_license_number', '');
		lf_update_business_info_value('lf_business_insurance_statement', '');
		lf_update_business_info_value('lf_business_place_id', '');
		lf_update_business_info_value('lf_business_place_name', '');
		lf_update_business_info_value('lf_business_place_address', '');
		lf_update_business_info_value('lf_business_map_embed', '');
	}
	if (function_exists('lf_update_cta_option_value')) {
		lf_update_cta_option_value('lf_cta_primary_text', '');
		lf_update_cta_option_value('lf_cta_secondary_text', '');
		lf_update_cta_option_value('lf_cta_primary_action', '');
		lf_update_cta_option_value('lf_cta_primary_url', '');
		lf_update_cta_option_value('lf_cta_secondary_action', '');
		lf_update_cta_option_value('lf_cta_secondary_url', '');
	} elseif (function_exists('update_field')) {
		update_field('lf_cta_primary_text', '', 'option');
		update_field('lf_cta_secondary_text', '', 'option');
		update_field('lf_cta_primary_action', '', 'option');
		update_field('lf_cta_primary_url', '', 'option');
		update_field('lf_cta_secondary_action', '', 'option');
		update_field('lf_cta_secondary_url', '', 'option');
	}
	if (function_exists('update_field')) {
		update_field('variation_profile', 'a', 'option');
		update_field('lf_schema_review', false, 'option');
		update_field('homepage_sections', [], 'option');
		update_field('lf_homepage_cta_primary', '', 'option');
		update_field('lf_homepage_cta_secondary', '', 'option');
		update_field('lf_homepage_cta_primary_action', '', 'option');
		update_field('lf_homepage_cta_primary_url', '', 'option');
		update_field('lf_homepage_cta_secondary_action', '', 'option');
		update_field('lf_homepage_cta_secondary_url', '', 'option');
		update_field('lf_homepage_cta_ghl', '', 'option');
		update_field('lf_homepage_cta_primary_type', '', 'option');
	}
	delete_option('lf_site_seed');
	delete_option('lf_site_manifest');
	delete_option('lf_ai_last_generation_log');
	delete_option('lf_ai_studio_manifest_errors');
	delete_option('lf_ai_studio_keywords');
	delete_option('lf_ai_edit_log');
	delete_option('lf_ai_inline_dom_overrides_homepage');
	delete_option('lf_ai_inline_image_overrides_homepage');
	delete_option('lf_image_upload_counter');
	delete_option('lf_media_vision_annotations');
	delete_option('lf_homepage_city');
	delete_option('lf_homepage_keywords');
	delete_option('lf_homepage_variation_seed');
	update_option('blogname', '');
	update_option('blogdescription', '');
	// Clear global settings (logo + header CTA).
	$global_keys = ['lf_global_logo', 'lf_header_cta_label', 'lf_header_cta_url'];
	if (function_exists('update_field')) {
		foreach ($global_keys as $key) {
			update_field($key, '', 'lf-global');
			update_field($key, '', 'options_lf_global');
			update_field($key, '', 'options_lf-global');
		}
	}
	foreach ($global_keys as $key) {
		delete_option('options_' . $key);
		delete_option('options_lf_global_' . $key);
		delete_option('options_lf-global_' . $key);
	}
	// Clear branding options (ACF + raw options).
	$branding_keys = [
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
	if (function_exists('update_field')) {
		foreach ($branding_keys as $key) {
			update_field($key, '', 'lf-branding');
			update_field($key, '', 'options_lf_branding');
			update_field($key, '', 'options_lf-branding');
		}
	}
	foreach ($branding_keys as $key) {
		delete_option('options_' . $key);
		delete_option('options_lf_branding_' . $key);
		delete_option('options_lf-branding_' . $key);
	}

	// Clear homepage section config so LeadsForward → Homepage shows empty (all sections off, no copy)
	if (function_exists('lf_homepage_empty_config')) {
		$empty_config = lf_homepage_empty_config();
		if (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
			update_option(LF_HOMEPAGE_CONFIG_OPTION, $empty_config, true);
		}
	} elseif (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
		delete_option(LF_HOMEPAGE_CONFIG_OPTION);
	}
	if (defined('LF_HOMEPAGE_NICHE_OPTION')) {
		delete_option(LF_HOMEPAGE_NICHE_OPTION);
	}
	if (defined('LF_HOMEPAGE_ORDER_OPTION')) {
		delete_option(LF_HOMEPAGE_ORDER_OPTION);
	}
	if (defined('LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION')) {
		delete_option(LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION);
	}
	if (defined('LF_HOMEPAGE_SECTION_ID_MIGRATED_OPTION')) {
		delete_option(LF_HOMEPAGE_SECTION_ID_MIGRATED_OPTION);
	}
	if (defined('LF_MEDIA_VISION_OPTION')) {
		delete_option(LF_MEDIA_VISION_OPTION);
	}
	if (defined('LF_QUOTE_BUILDER_OPTION')) {
		delete_option(LF_QUOTE_BUILDER_OPTION);
	}
	if (defined('LF_QUOTE_BUILDER_MANUAL_OPTION')) {
		delete_option(LF_QUOTE_BUILDER_MANUAL_OPTION);
	}
	if (defined('LF_QUOTE_BUILDER_SUBMISSIONS')) {
		delete_option(LF_QUOTE_BUILDER_SUBMISSIONS);
	}
	$post_ids_for_inline_clear = get_posts([
		'post_type' => ['page', 'post', 'lf_service', 'lf_service_area'],
		'post_status' => 'any',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	foreach ((array) $post_ids_for_inline_clear as $inline_post_id) {
		delete_post_meta((int) $inline_post_id, '_lf_ai_inline_dom_overrides');
		delete_post_meta((int) $inline_post_id, '_lf_ai_inline_image_overrides');
	}

	update_option('show_on_front', 'posts');
	update_option('page_on_front', 0);
	delete_option('lf_setup_wizard_complete');
	delete_option(LF_DEV_RESET_OPTION_IDS);

	$log = get_option(LF_DEV_RESET_OPTION_LOG, []);
	if (!is_array($log)) {
		$log = [];
	}
	array_unshift($log, [
		'time'     => time(),
		'user_id'  => get_current_user_id(),
	]);
	$log = array_slice($log, 0, LF_DEV_RESET_LOG_MAX);
	update_option(LF_DEV_RESET_OPTION_LOG, $log);

}
