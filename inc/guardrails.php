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

/** ACF options page slug for Global Business Info (NAP, geo, hours). */
define('LF_OPTIONS_PAGE_BUSINESS', 'lf-business-info');

/** Field names stored on the Business Info options page. */
function lf_business_option_selectors(): array {
	return [
		'lf_business_name',
		'lf_business_phone',
		'lf_business_email',
		'lf_business_address',
		'lf_business_geo',
		'lf_business_hours',
		'lf_business_place_id',
		'lf_business_place_name',
		'lf_business_place_address',
		'lf_business_map_embed',
	];
}

/**
 * Possible ACF post_id values for Business Info options page.
 * ACF varies between "option", "options", and "options_{slug}" for subpages.
 *
 * @return array<int, string>
 */
function lf_business_info_post_ids(): array {
	$slug = defined('LF_OPTIONS_PAGE_BUSINESS') ? LF_OPTIONS_PAGE_BUSINESS : 'lf-business-info';
	$base = str_starts_with($slug, 'options_') ? substr($slug, 8) : $slug;
	$base_us = str_replace('-', '_', $base);
	$ids = [$slug, 'options_' . $base, 'options_' . $base_us, 'option', 'options'];
	return array_values(array_unique(array_filter($ids)));
}

/**
 * Get Business Info field from any valid options storage.
 *
 * @param string $selector
 * @param mixed  $default
 * @return mixed
 */
function lf_get_business_info_value(string $selector, $default = null) {
	if (function_exists('get_field')) {
		foreach (lf_business_info_post_ids() as $post_id) {
			$value = get_field($selector, $post_id);
			if ($value !== null && $value !== false && $value !== '') {
				return $value;
			}
		}
	}
	// Fallback to raw options in case ACF field resolution failed.
	$option_keys = ['options_' . $selector];
	foreach (lf_business_info_post_ids() as $post_id) {
		if (str_starts_with($post_id, 'options_')) {
			$option_keys[] = $post_id . '_' . $selector;
		}
	}
	foreach (array_unique($option_keys) as $key) {
		$value = get_option($key, null);
		if ($value !== null && $value !== false && $value !== '') {
			return $value;
		}
	}
	return $default;
}

/**
 * Update Business Info field in all valid options storages.
 *
 * @param string $selector
 * @param mixed  $value
 */
function lf_update_business_info_value(string $selector, $value): void {
	$post_ids = lf_business_info_post_ids();
	$has_acf = function_exists('update_field');
	$option_keys = ['options_' . $selector];
	foreach ($post_ids as $post_id) {
		if ($has_acf) {
			update_field($selector, $value, $post_id);
		}
		if ($post_id === 'option' || $post_id === 'options') {
			$option_keys[] = 'options_' . $selector;
		}
		if (str_starts_with($post_id, 'options_')) {
			$option_keys[] = $post_id . '_' . $selector;
		}
	}
	foreach (array_unique($option_keys) as $key) {
		update_option($key, $value);
	}
}

/**
 * Get ACF option. Works when ACF is disabled (returns default). Use for global Business/CTA/Schema.
 * Business Info fields are stored on the lf-business-info options page; others use the passed slug (default 'option').
 */
function lf_get_option(string $selector, string $options_page_slug = 'option', $default = null) {
	if (function_exists('get_field')) {
		if (in_array($selector, lf_business_option_selectors(), true)) {
			return lf_get_business_info_value($selector, $default);
		}
		$value = get_field($selector, $options_page_slug);
		return $value !== null && $value !== false && $value !== '' ? $value : $default;
	}
	return $default;
}

function lf_update_cta_option_value(string $key, string $value): void {
	$post_ids = ['lf-ctas', 'options_lf_ctas', 'options_lf-ctas', 'option', 'options'];
	if (function_exists('update_field')) {
		foreach ($post_ids as $post_id) {
			update_field($key, $value, $post_id);
		}
	}
	update_option('options_' . $key, $value);
}

function lf_update_global_option_value(string $key, string $value): void {
	$post_ids = ['lf-global', 'options_lf_global', 'options_lf-global', 'option', 'options'];
	if (function_exists('update_field')) {
		foreach ($post_ids as $post_id) {
			update_field($key, $value, $post_id);
		}
	}
	update_option('options_' . $key, $value);
}

function lf_maybe_migrate_cta_defaults(): void {
	if (get_option('lf_cta_defaults_migrated', '0') === '1') {
		return;
	}
	$primary = lf_get_option('lf_cta_primary_text', 'option', '');
	$secondary = lf_get_option('lf_cta_secondary_text', 'option', '');
	$header_label = function_exists('lf_get_global_option') ? (string) lf_get_global_option('lf_header_cta_label', '') : '';

	$new_primary = __('Get a free estimate', 'leadsforward-core');
	$new_secondary = __('Call now', 'leadsforward-core');
	$new_header = __('Free Estimate', 'leadsforward-core');
	$did = false;

	if ($primary === '' || strcasecmp($primary, 'Contact Us') === 0 || strcasecmp($primary, 'Get a Quote') === 0) {
		lf_update_cta_option_value('lf_cta_primary_text', $new_primary);
		$did = true;
	}
	if ($secondary === '' || strcasecmp($secondary, 'Contact Us') === 0 || strcasecmp($secondary, 'Get a Quote') === 0) {
		lf_update_cta_option_value('lf_cta_secondary_text', $new_secondary);
		$did = true;
	}
	if ($header_label === '' || strcasecmp($header_label, 'Contact Us') === 0 || strcasecmp($header_label, 'Get a Quote') === 0) {
		lf_update_global_option_value('lf_header_cta_label', $new_header);
		$did = true;
	}

	if ($did) {
		update_option('lf_cta_defaults_migrated', '1');
	}
}
add_action('init', 'lf_maybe_migrate_cta_defaults', 20);

/**
 * Hide deprecated Service Content ACF group if it exists in DB.
 */
add_filter('acf/get_field_groups', function (array $groups): array {
	return array_values(array_filter($groups, function ($group) {
		$key = is_array($group) ? ($group['key'] ?? '') : '';
		return $key !== 'group_lf_service';
	}));
});

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
