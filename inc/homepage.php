<?php
/**
 * Homepage section registry, default sections, CTA resolution, section renderer.
 * No hardcoded layout; sections driven by ACF flexible content or defaults.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Map section type (ACF value) to block template name. Used for front-page and block render.
 */
function lf_homepage_section_template_map(): array {
	return [
		'hero'          => 'hero',
		'trust_reviews' => 'trust-reviews',
		'service_grid'  => 'service-grid',
		'cta'           => 'cta',
		'faq_accordion' => 'faq-accordion',
		'map_nap'       => 'map-nap',
	];
}

/**
 * Conversion-optimized default sections when none are set. Order: hero, trust, services, CTA, FAQ, CTA, map.
 */
function lf_get_default_homepage_sections(): array {
	return [
		['section_type' => 'hero',          'layout_variant' => 'default'],
		['section_type' => 'trust_reviews', 'layout_variant' => 'default'],
		['section_type' => 'service_grid',  'layout_variant' => 'default'],
		['section_type' => 'cta',           'layout_variant' => 'default'],
		['section_type' => 'faq_accordion', 'layout_variant' => 'default'],
		['section_type' => 'cta',           'layout_variant' => 'b'],
		['section_type' => 'map_nap',       'layout_variant' => 'default'],
	];
}

/**
 * Get homepage sections from ACF option or default. Only runs when on front; no query if not needed.
 */
function lf_get_homepage_sections(): array {
	if (!is_front_page()) {
		return [];
	}
	$raw = function_exists('get_field') ? get_field('homepage_sections', 'option') : null;
	if (!empty($raw) && is_array($raw)) {
		$out = [];
		foreach ($raw as $row) {
			if (empty($row['section_type'])) {
				continue;
			}
			$out[] = [
				'section_type'            => $row['section_type'],
				'layout_variant'          => $row['layout_variant'] ?? 'default',
				'hero_headline'           => $row['hero_headline'] ?? '',
				'hero_subheadline'        => $row['hero_subheadline'] ?? '',
				'hero_cta_override'       => $row['hero_cta_override'] ?? '',
				'trust_max_items'         => isset($row['trust_max_items']) ? (int) $row['trust_max_items'] : 3,
				'cta_primary_override'    => $row['cta_primary_override'] ?? '',
				'cta_secondary_override'  => $row['cta_secondary_override'] ?? '',
				'cta_ghl_override'       => $row['cta_ghl_override'] ?? '',
			];
		}
		return $out;
	}
	return lf_get_default_homepage_sections();
}

/**
 * Resolved CTA: section > homepage > global. Returns primary_text, secondary_text, ghl_embed, primary_type.
 * Single source for GHL; no duplicated embed.
 */
function lf_get_resolved_cta(array $context = []): array {
	$section = $context['section'] ?? null;
	$is_homepage = $context['homepage'] ?? is_front_page();

	$primary   = lf_get_option('lf_cta_primary_text', 'option');
	$secondary = lf_get_option('lf_cta_secondary_text', 'option');
	$ghl       = lf_get_option('lf_cta_ghl_embed', 'option');
	$type      = lf_get_option('lf_cta_primary_type', 'option') ?: 'text';

	if ($is_homepage && function_exists('get_field')) {
		$hp_primary = get_field('lf_homepage_cta_primary', 'option');
		$hp_secondary = get_field('lf_homepage_cta_secondary', 'option');
		$hp_ghl = get_field('lf_homepage_cta_ghl', 'option');
		$hp_type = get_field('lf_homepage_cta_primary_type', 'option');
		if ($hp_primary !== null && $hp_primary !== '') {
			$primary = $hp_primary;
		}
		if ($hp_secondary !== null && $hp_secondary !== '') {
			$secondary = $hp_secondary;
		}
		if ($hp_ghl !== null && $hp_ghl !== '') {
			$ghl = $hp_ghl;
		}
		if ($hp_type !== null && $hp_type !== '') {
			$type = $hp_type;
		}
	}

	if (is_array($section)) {
		if (!empty($section['cta_primary_override'])) {
			$primary = $section['cta_primary_override'];
		}
		if (!empty($section['cta_secondary_override'])) {
			$secondary = $section['cta_secondary_override'];
		}
		if (!empty($section['cta_ghl_override'])) {
			$ghl = $section['cta_ghl_override'];
		}
		if (!empty($section['hero_cta_override'])) {
			$primary = $section['hero_cta_override'];
		}
	}

	return [
		'primary_text'   => is_string($primary) ? $primary : '',
		'secondary_text' => is_string($secondary) ? $secondary : '',
		'ghl_embed'      => is_string($ghl) ? $ghl : '',
		'primary_type'   => in_array($type, ['call', 'form', 'text'], true) ? $type : 'text',
	];
}

/**
 * Phone number for call CTA. From Business Info; no duplication.
 */
function lf_get_cta_phone(): string {
	$phone = lf_get_option('lf_business_phone', 'option');
	return is_string($phone) ? preg_replace('/\s+/', '', $phone) : '';
}

/**
 * Render one homepage section. Maps section_type to template, passes variant + overrides as context.
 */
function lf_render_homepage_section(array $section, int $index): void {
	$map = lf_homepage_section_template_map();
	$type = $section['section_type'] ?? '';
	if (!isset($map[$type])) {
		return;
	}
	$template = $map[$type];
	$variant = $section['layout_variant'] ?? 'default';
	$block = [
		'id'         => 'homepage-section-' . $index,
		'variant'    => $variant,
		'attributes' => ['variant' => $variant, 'layout' => $variant],
		'context'   => [
			'homepage' => true,
			'section'  => $section,
			'index'    => $index,
		],
	];
	lf_render_block_template($template, $block, false, $block['context']);
}
