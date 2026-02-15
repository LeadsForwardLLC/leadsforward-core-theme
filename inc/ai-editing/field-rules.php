<?php
/**
 * AI editing: which fields are editable vs locked. Protects slugs, schema, identifiers.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Field keys that AI must never modify (slugs, schema, identifiers, relationships).
 */
function lf_ai_locked_field_keys(): array {
	$locked = [
		'post_title',           // Drives slug / H1 unless overridden
		'post_name',            // URL slug
		'lf_service_area_state',
		'lf_service_area_services',
		'lf_faq_associated_service',
		'lf_faq_associated_service_area',
		'lf_business_name',
		'lf_business_phone',
		'lf_business_email',
		'lf_business_address',
		'lf_business_geo',
		'lf_business_hours',
		'lf_schema_organization',
		'lf_schema_local_business',
		'lf_schema_faq',
		'lf_schema_review',
		'variation_profile',
		'section_type',
		'layout_variant',
		'lf_homepage_cta_primary_type',
		'homepage_sections',    // Structure not raw; section rows have locked keys
		'lf_testimonial_reviewer_name', // Could allow but keep stable for schema
		'lf_testimonial_rating',
		'lf_testimonial_source',
	];
	return array_fill_keys($locked, true);
}

/**
 * Field keys that AI is allowed to edit (copy, CTA labels, supporting content).
 */
function lf_ai_editable_field_keys(): array {
	return [
		'lf_service_area_map_override', // Supporting / embed copy only
		'lf_faq_question',
		'lf_faq_answer',
		'lf_testimonial_review_text',
		'lf_homepage_cta_primary',
		'lf_homepage_cta_secondary',
		'lf_cta_primary_text',
		'lf_cta_secondary_text',
		'hero_headline',
		'hero_subheadline',
		'cta_primary_override',
		'post_content',
	];
}

/**
 * Whether a field key may be edited by AI. Respects allow_ai_h1_edit filter.
 */
function lf_is_field_ai_editable(string $field_key): bool {
	$locked = lf_ai_locked_field_keys();
	if (!empty($locked[$field_key])) {
		return false;
	}
	$editable = array_flip(lf_ai_editable_field_keys());
	return isset($editable[$field_key]);
}

/**
 * Editable fields for a given context (post_id or 'homepage'). Returns [ field_key => label ] for prompt/UI.
 */
function lf_get_ai_editable_fields($post_id): array {
	$editable_keys = lf_ai_editable_field_keys();
	$labels = [
		'lf_service_area_map_override' => __('Map embed override', 'leadsforward-core'),
		'lf_faq_question'         => __('FAQ question', 'leadsforward-core'),
		'lf_faq_answer'            => __('FAQ answer', 'leadsforward-core'),
		'lf_testimonial_review_text' => __('Review text', 'leadsforward-core'),
		'lf_homepage_cta_primary'  => __('Homepage primary CTA', 'leadsforward-core'),
		'lf_homepage_cta_secondary' => __('Homepage secondary CTA', 'leadsforward-core'),
		'lf_cta_primary_text'      => __('Global primary CTA', 'leadsforward-core'),
		'lf_cta_secondary_text'    => __('Global secondary CTA', 'leadsforward-core'),
		'hero_headline'            => __('Hero headline', 'leadsforward-core'),
		'hero_subheadline'         => __('Hero subheadline', 'leadsforward-core'),
		'cta_primary_override'     => __('Primary CTA override', 'leadsforward-core'),
		'post_content'             => __('Page content', 'leadsforward-core'),
	];
	$out = [];
	$post = is_numeric($post_id) ? get_post((int) $post_id) : null;
	$context = $post ? $post->post_type : 'homepage';

	foreach ($editable_keys as $key) {
		$allowed = false;
		if ($context === 'lf_service') {
			$allowed = in_array($key, ['hero_headline', 'hero_subheadline', 'post_content'], true);
		} elseif ($context === 'lf_service_area') {
			$allowed = in_array($key, ['hero_headline', 'hero_subheadline', 'lf_service_area_map_override', 'post_content'], true);
		} elseif ($context === 'lf_faq') {
			$allowed = in_array($key, ['lf_faq_question', 'lf_faq_answer', 'post_content'], true);
		} elseif ($context === 'lf_testimonial') {
			$allowed = in_array($key, ['lf_testimonial_review_text'], true);
		} elseif ($context === 'page' && (int) $post_id === (int) get_option('page_on_front')) {
			$allowed = in_array($key, ['lf_homepage_cta_primary', 'lf_homepage_cta_secondary', 'hero_headline', 'hero_subheadline', 'cta_primary_override', 'post_content'], true);
		} elseif ($context === 'page') {
			$allowed = in_array($key, ['hero_headline', 'hero_subheadline', 'post_content'], true);
		} elseif ($context === 'post') {
			$allowed = in_array($key, ['hero_headline', 'hero_subheadline', 'post_content'], true);
		} elseif ($context === 'homepage') {
			$allowed = in_array($key, ['lf_homepage_cta_primary', 'lf_homepage_cta_secondary', 'hero_headline', 'hero_subheadline', 'cta_primary_override'], true);
		}
		if ($allowed && isset($labels[$key])) {
			$out[$key] = $labels[$key];
		}
	}
	return $out;
}
