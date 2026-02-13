<?php
/**
 * Shared allowlists and helpers for config export/import and bulk ops. No URLs, slugs, or IDs.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_OPS_CAP = 'edit_theme_options';
const LF_OPS_AUDIT_OPTION = 'lf_ops_audit_log';
const LF_OPS_AUDIT_MAX = 100;

function lf_design_presets(): array {
	return [
		'clean-precision' => __('Clean Precision', 'leadsforward-core'),
		'bold-authority' => __('Bold Authority', 'leadsforward-core'),
		'friendly-approachable' => __('Friendly & Approachable', 'leadsforward-core'),
		'high-contrast' => __('High-Contrast Conversion Engine', 'leadsforward-core'),
		'modern-edge' => __('Modern Edge', 'leadsforward-core'),
		'structured-modular' => __('Structured Modular', 'leadsforward-core'),
	];
}

function lf_variation_profile_labels(): array {
	return [
		'a' => __('A: Clean + Minimal', 'leadsforward-core'),
		'b' => __('B: Bold + High Contrast', 'leadsforward-core'),
		'c' => __('C: Trust Heavy', 'leadsforward-core'),
		'd' => __('D: Service Heavy', 'leadsforward-core'),
		'e' => __('E: Offer/Promo Heavy', 'leadsforward-core'),
	];
}

function lf_design_preset_to_variation_profile(string $preset): string {
	$map = [
		'clean-precision' => 'a',
		'bold-authority' => 'b',
		'friendly-approachable' => 'c',
		'high-contrast' => 'b',
		'modern-edge' => 'd',
		'structured-modular' => 'e',
	];
	return $map[$preset] ?? 'a';
}

/**
 * Option keys we allow in export/import. Explicit allowlist; no URLs, slugs, post IDs, user data.
 */
function lf_ops_exportable_option_keys(): array {
	return [
		'lf_business_name',
		'lf_business_legal_name',
		'lf_business_phone',
		'lf_business_phone_primary',
		'lf_business_phone_tracking',
		'lf_business_phone_display',
		'lf_business_email',
		'lf_business_address',
		'lf_business_address_street',
		'lf_business_address_city',
		'lf_business_address_state',
		'lf_business_address_zip',
		'lf_business_service_area_type',
		'lf_business_service_areas',
		'lf_business_geo',
		'lf_business_hours',
		'lf_business_category',
		'lf_business_short_description',
		'lf_business_primary_image',
		'lf_business_social_facebook',
		'lf_business_social_instagram',
		'lf_business_social_youtube',
		'lf_business_social_linkedin',
		'lf_business_social_tiktok',
		'lf_business_social_x',
		'lf_business_gbp_url',
		'lf_business_same_as',
		'lf_business_founding_year',
		'lf_business_license_number',
		'lf_business_insurance_statement',
		'lf_cta_primary_text',
		'lf_cta_secondary_text',
		'lf_cta_primary_type',
		'lf_cta_primary_action',
		'lf_cta_primary_url',
		'lf_cta_secondary_action',
		'lf_cta_secondary_url',
		'lf_cta_ghl_embed',
		'variation_profile',
		'auto_order_sections',
		'hero_headline_style',
		'cta_microcopy_style',
		'trust_badge_style',
		'lf_schema_organization',
		'lf_schema_local_business',
		'lf_schema_faq',
		'lf_schema_review',
		'lf_homepage_cta_primary',
		'lf_homepage_cta_secondary',
		'lf_homepage_cta_primary_type',
		'lf_homepage_cta_primary_action',
		'lf_homepage_cta_primary_url',
		'lf_homepage_cta_secondary_action',
		'lf_homepage_cta_secondary_url',
		'lf_homepage_cta_ghl',
		'homepage_sections',
		'lf_homepage_section_config',
		'lf_homepage_section_order',
		'lf_homepage_niche_slug',
		'lf_global_design_preset',
		'lf_design_overrides_enabled',
		'lf_design_heading_font',
		'lf_design_body_font',
		'lf_design_heading_weight',
		'lf_design_button_radius',
		'lf_design_card_radius',
		'lf_design_card_shadow',
		'lf_design_section_spacing',
		'lf_quote_builder_config',
	];
}

/**
 * Human labels for option keys (for preview/import UI).
 */
function lf_ops_option_labels(): array {
	return [
		'lf_business_name'           => __('Business name', 'leadsforward-core'),
		'lf_business_legal_name'     => __('Business legal name', 'leadsforward-core'),
		'lf_business_phone'         => __('Business phone', 'leadsforward-core'),
		'lf_business_phone_primary' => __('Primary phone', 'leadsforward-core'),
		'lf_business_phone_tracking' => __('Tracking phone', 'leadsforward-core'),
		'lf_business_phone_display' => __('Display phone preference', 'leadsforward-core'),
		'lf_business_email'         => __('Business email', 'leadsforward-core'),
		'lf_business_address'       => __('Business address', 'leadsforward-core'),
		'lf_business_address_street' => __('Street address', 'leadsforward-core'),
		'lf_business_address_city'  => __('Address city', 'leadsforward-core'),
		'lf_business_address_state' => __('Address state', 'leadsforward-core'),
		'lf_business_address_zip'   => __('Address ZIP', 'leadsforward-core'),
		'lf_business_service_area_type' => __('Service area type', 'leadsforward-core'),
		'lf_business_service_areas' => __('Service areas list', 'leadsforward-core'),
		'lf_business_geo'           => __('Geo coordinates', 'leadsforward-core'),
		'lf_business_hours'         => __('Opening hours', 'leadsforward-core'),
		'lf_business_category'      => __('Business category', 'leadsforward-core'),
		'lf_business_short_description' => __('Business short description', 'leadsforward-core'),
		'lf_business_primary_image' => __('Business primary image', 'leadsforward-core'),
		'lf_business_social_facebook' => __('Facebook URL', 'leadsforward-core'),
		'lf_business_social_instagram' => __('Instagram URL', 'leadsforward-core'),
		'lf_business_social_youtube' => __('YouTube URL', 'leadsforward-core'),
		'lf_business_social_linkedin' => __('LinkedIn URL', 'leadsforward-core'),
		'lf_business_social_tiktok' => __('TikTok URL', 'leadsforward-core'),
		'lf_business_social_x' => __('X (Twitter) URL', 'leadsforward-core'),
		'lf_business_gbp_url'       => __('Google Business Profile URL', 'leadsforward-core'),
		'lf_business_same_as'       => __('sameAs URLs', 'leadsforward-core'),
		'lf_business_founding_year' => __('Founding year', 'leadsforward-core'),
		'lf_business_license_number' => __('License number', 'leadsforward-core'),
		'lf_business_insurance_statement' => __('Insurance statement', 'leadsforward-core'),
		'lf_cta_primary_text'       => __('Primary CTA text', 'leadsforward-core'),
		'lf_cta_secondary_text'     => __('Secondary CTA text', 'leadsforward-core'),
		'lf_cta_primary_type'       => __('Primary CTA type', 'leadsforward-core'),
		'lf_cta_primary_action'     => __('Primary CTA action', 'leadsforward-core'),
		'lf_cta_primary_url'        => __('Primary CTA URL', 'leadsforward-core'),
		'lf_cta_secondary_action'   => __('Secondary CTA action', 'leadsforward-core'),
		'lf_cta_secondary_url'      => __('Secondary CTA URL', 'leadsforward-core'),
		'lf_cta_ghl_embed'          => __('Default GHL embed', 'leadsforward-core'),
		'variation_profile'         => __('Variation profile', 'leadsforward-core'),
		'auto_order_sections'       => __('Auto-order homepage sections', 'leadsforward-core'),
		'hero_headline_style'       => __('Hero headline style', 'leadsforward-core'),
		'cta_microcopy_style'       => __('CTA microcopy style', 'leadsforward-core'),
		'trust_badge_style'         => __('Trust badge style', 'leadsforward-core'),
		'lf_schema_organization'    => __('Schema: Organization', 'leadsforward-core'),
		'lf_schema_local_business'  => __('Schema: LocalBusiness', 'leadsforward-core'),
		'lf_schema_faq'             => __('Schema: FAQ', 'leadsforward-core'),
		'lf_schema_review'          => __('Schema: Review', 'leadsforward-core'),
		'lf_homepage_cta_primary'   => __('Homepage primary CTA', 'leadsforward-core'),
		'lf_homepage_cta_secondary' => __('Homepage secondary CTA', 'leadsforward-core'),
		'lf_homepage_cta_primary_type' => __('Homepage primary CTA type', 'leadsforward-core'),
		'lf_homepage_cta_primary_action' => __('Homepage primary CTA action', 'leadsforward-core'),
		'lf_homepage_cta_primary_url'    => __('Homepage primary CTA URL', 'leadsforward-core'),
		'lf_homepage_cta_secondary_action' => __('Homepage secondary CTA action', 'leadsforward-core'),
		'lf_homepage_cta_secondary_url'    => __('Homepage secondary CTA URL', 'leadsforward-core'),
		'lf_homepage_cta_ghl'       => __('Homepage GHL override', 'leadsforward-core'),
		'homepage_sections'         => __('Homepage sections', 'leadsforward-core'),
		'lf_homepage_section_config' => __('Homepage section config', 'leadsforward-core'),
		'lf_homepage_section_order'  => __('Homepage section order', 'leadsforward-core'),
		'lf_homepage_niche_slug'     => __('Homepage niche', 'leadsforward-core'),
		'lf_global_design_preset'    => __('Global design preset', 'leadsforward-core'),
		'lf_design_overrides_enabled' => __('Design overrides enabled', 'leadsforward-core'),
		'lf_design_heading_font'     => __('Design: Heading font', 'leadsforward-core'),
		'lf_design_body_font'        => __('Design: Body font', 'leadsforward-core'),
		'lf_design_heading_weight'   => __('Design: Heading weight', 'leadsforward-core'),
		'lf_design_button_radius'    => __('Design: Button radius', 'leadsforward-core'),
		'lf_design_card_radius'      => __('Design: Card radius', 'leadsforward-core'),
		'lf_design_card_shadow'      => __('Design: Card shadow', 'leadsforward-core'),
		'lf_design_section_spacing'  => __('Design: Section spacing', 'leadsforward-core'),
		'lf_quote_builder_config'    => __('Quote builder config', 'leadsforward-core'),
	];
}

/** WP options (not ACF) included in export/import. */
function lf_ops_wp_option_keys(): array {
	return [
		'lf_homepage_section_config',
		'lf_homepage_section_order',
		'lf_homepage_niche_slug',
		'lf_global_design_preset',
		'lf_design_overrides_enabled',
		'lf_design_heading_font',
		'lf_design_body_font',
		'lf_design_heading_weight',
		'lf_design_button_radius',
		'lf_design_card_radius',
		'lf_design_card_shadow',
		'lf_design_section_spacing',
		'lf_quote_builder_config',
	];
}

/**
 * Append an audit log entry. Admin-only; no frontend.
 */
function lf_ops_audit_log(string $action, array $details = [], array $previous = []): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$log = get_option(LF_OPS_AUDIT_OPTION, []);
	if (!is_array($log)) {
		$log = [];
	}
	array_unshift($log, [
		'user_id'  => get_current_user_id(),
		'action'   => $action,
		'time'     => time(),
		'details'  => $details,
		'previous' => $previous,
	]);
	$log = array_slice($log, 0, LF_OPS_AUDIT_MAX);
	update_option(LF_OPS_AUDIT_OPTION, $log);
}

/**
 * Get audit log entries (newest first).
 */
function lf_ops_get_audit_log(): array {
	$log = get_option(LF_OPS_AUDIT_OPTION, []);
	return is_array($log) ? $log : [];
}
