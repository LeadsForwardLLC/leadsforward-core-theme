<?php
/**
 * Safety & guardrails: CPT protect, admin notices for missing options, ACF-off fallbacks.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Get ACF option. Works when ACF is disabled (returns default). Use for global Business/CTA/Schema.
 * ACF stores all options under 'option'; slug is for field group location only.
 */
function lf_get_option(string $selector, string $options_page_slug = 'option', $default = null) {
	if (function_exists('get_field')) {
		$value = get_field($selector, 'option');
		return $value !== null && $value !== false && $value !== '' ? $value : $default;
	}
	return $default;
}

/**
 * Core CPTs that must not be permanently deleted. Trash allowed; permanent delete blocked.
 */
function lf_protected_post_types(): array {
	return ['lf_service', 'lf_service_area', 'lf_testimonial', 'lf_faq'];
}

/**
 * Deny delete_post capability for our CPTs when attempting permanent delete (post already in trash).
 */
function lf_map_meta_cap_for_cpt_delete(array $caps, string $cap, int $user_id, array $args): array {
	if ($cap !== 'delete_post' || empty($args[0])) {
		return $caps;
	}
	$post = get_post($args[0]);
	if (!$post || !in_array($post->post_type, lf_protected_post_types(), true)) {
		return $caps;
	}
	// Post is in trash = permanent delete. Block it so core CPTs are only trashed, not removed.
	if ($post->post_status === 'trash') {
		$caps = ['do_not_allow'];
	}
	return $caps;
}
add_filter('map_meta_cap', 'lf_map_meta_cap_for_cpt_delete', 10, 4);

/**
 * Admin notice when required global fields are missing (Business Name, Phone, etc.).
 */
function lf_admin_notice_missing_options(): void {
	$screen = get_current_screen();
	if (!$screen || (strpos($screen->id, 'lf-theme-options') === false && strpos($screen->id, 'lf-business-info') === false)) {
		return;
	}
	if (!function_exists('get_field')) {
		echo '<div class="notice notice-warning"><p>' . esc_html__('LeadsForward Core: ACF is required for Theme Options. Install and activate Advanced Custom Fields.', 'leadsforward-core') . '</p></div>';
		return;
	}
	$business_name = lf_get_option('lf_business_name', 'option');
	$phone = lf_get_option('lf_business_phone', 'option');
	$missing = [];
	if (empty($business_name)) {
		$missing[] = __('Business Name', 'leadsforward-core');
	}
	if (empty($phone)) {
		$missing[] = __('Phone', 'leadsforward-core');
	}
	if (empty($missing)) {
		return;
	}
	echo '<div class="notice notice-info"><p>' . esc_html__('LeadsForward Core: For best SEO and schema, fill in:', 'leadsforward-core') . ' ' . esc_html(implode(', ', $missing)) . '</p></div>';
}
add_action('admin_notices', 'lf_admin_notice_missing_options');

/**
 * Admin warnings for SEO-critical fields: global NAP + service/area titles.
 * Shown on Theme Options and on edit screens for Service / Service Area.
 */
function lf_admin_notice_seo_critical(): void {
	if (!function_exists('get_field')) {
		return;
	}
	$screen = get_current_screen();
	if (!$screen) {
		return;
	}
	$warnings = [];

	// Global: NAP and address for LocalBusiness schema.
	if (strpos($screen->id, 'lf-theme-options') !== false || strpos($screen->id, 'lf-business-info') !== false) {
		if (empty(lf_get_option('lf_business_name', 'option'))) {
			$warnings[] = __('Business Name is required for LocalBusiness schema.', 'leadsforward-core');
		}
		if (empty(lf_get_option('lf_business_address', 'option'))) {
			$warnings[] = __('Address (NAP) improves local SEO and schema.', 'leadsforward-core');
		}
		if (empty(lf_get_option('lf_business_phone', 'option'))) {
			$warnings[] = __('Phone is recommended for local SEO.', 'leadsforward-core');
		}
	}

	// Service: title used as H1/SEO if SEO H1 empty.
	if ($screen->id === 'lf_service' && isset($_GET['post'])) {
		$post_id = (int) $_GET['post'];
		if ($post_id && empty(get_post($post_id)->post_title)) {
			$warnings[] = __('Service title is used for URL and H1; add a title.', 'leadsforward-core');
		}
	}

	// Service Area: title = city name.
	if ($screen->id === 'lf_service_area' && isset($_GET['post'])) {
		$post_id = (int) $_GET['post'];
		if ($post_id && empty(get_post($post_id)->post_title)) {
			$warnings[] = __('Service area title is the city name (used for URL and H1).', 'leadsforward-core');
		}
	}

	if (empty($warnings)) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__('LeadsForward SEO:', 'leadsforward-core') . '</strong> ' . esc_html(implode(' ', $warnings)) . '</p></div>';
}
add_action('admin_notices', 'lf_admin_notice_seo_critical');
