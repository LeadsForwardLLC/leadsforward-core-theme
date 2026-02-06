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

/**
 * Option keys we allow in export/import. Explicit allowlist; no URLs, slugs, post IDs, user data.
 */
function lf_ops_exportable_option_keys(): array {
	return [
		'lf_business_name',
		'lf_business_phone',
		'lf_business_email',
		'lf_business_address',
		'lf_business_geo',
		'lf_business_hours',
		'lf_cta_primary_text',
		'lf_cta_secondary_text',
		'lf_cta_primary_type',
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
		'lf_homepage_cta_ghl',
		'homepage_sections',
		'lf_homepage_section_config',
		'lf_homepage_niche_slug',
	];
}

/**
 * Human labels for option keys (for preview/import UI).
 */
function lf_ops_option_labels(): array {
	return [
		'lf_business_name'           => __('Business name', 'leadsforward-core'),
		'lf_business_phone'         => __('Business phone', 'leadsforward-core'),
		'lf_business_email'         => __('Business email', 'leadsforward-core'),
		'lf_business_address'       => __('Business address', 'leadsforward-core'),
		'lf_business_geo'           => __('Geo coordinates', 'leadsforward-core'),
		'lf_business_hours'         => __('Opening hours', 'leadsforward-core'),
		'lf_cta_primary_text'       => __('Primary CTA text', 'leadsforward-core'),
		'lf_cta_secondary_text'     => __('Secondary CTA text', 'leadsforward-core'),
		'lf_cta_primary_type'       => __('Primary CTA type', 'leadsforward-core'),
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
		'lf_homepage_cta_ghl'       => __('Homepage GHL override', 'leadsforward-core'),
		'homepage_sections'         => __('Homepage sections', 'leadsforward-core'),
		'lf_homepage_section_config' => __('Homepage section config', 'leadsforward-core'),
		'lf_homepage_niche_slug'     => __('Homepage niche', 'leadsforward-core'),
	];
}

/** WP options (not ACF) included in export/import. */
function lf_ops_wp_option_keys(): array {
	return ['lf_homepage_section_config', 'lf_homepage_niche_slug'];
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
