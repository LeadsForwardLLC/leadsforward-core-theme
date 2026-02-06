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
 * Recommended order for "middle" sections (safe to reorder) per variation profile.
 * Hero never moves off first; final section (last CTA or map) never moves off last.
 */
function lf_get_profile_section_order(string $profile): array {
	$orders = [
		'a' => ['trust_reviews', 'service_grid', 'cta', 'faq_accordion', 'map_nap'],
		'b' => ['service_grid', 'trust_reviews', 'cta', 'faq_accordion', 'map_nap'],
		'c' => ['trust_reviews', 'cta', 'service_grid', 'faq_accordion', 'map_nap'], // Trust Heavy: trust early
		'd' => ['service_grid', 'trust_reviews', 'cta', 'faq_accordion', 'map_nap'], // Service Heavy: services early
		'e' => ['cta', 'trust_reviews', 'service_grid', 'faq_accordion', 'map_nap'], // Offer/Promo: CTA early
	];
	return $orders[$profile] ?? $orders['a'];
}

/**
 * Reorder sections safely: first stays first, last stays last, middle reordered by profile.
 */
function lf_apply_profile_section_order(array $sections): array {
	if (count($sections) <= 2) {
		return $sections;
	}
	$profile = function_exists('lf_get_variation_profile') ? lf_get_variation_profile() : 'a';
	$order = lf_get_profile_section_order($profile);
	$first = array_shift($sections);
	$last  = array_pop($sections);
	$middle = $sections;
	$by_type = [];
	foreach ($middle as $sec) {
		$t = $sec['section_type'] ?? '';
		if ($t !== '') {
			$by_type[$t] = $by_type[$t] ?? [];
			$by_type[$t][] = $sec;
		}
	}
	$reordered = [];
	foreach ($order as $type) {
		if (isset($by_type[$type])) {
			foreach ($by_type[$type] as $sec) {
				$reordered[] = $sec;
			}
			unset($by_type[$type]);
		}
	}
	foreach ($by_type as $rest) {
		foreach ($rest as $sec) {
			$reordered[] = $sec;
		}
	}
	return array_merge([$first], $reordered, [$last]);
}

/**
 * Get homepage sections from ACF option or default. Only runs when on front; no query if not needed.
 * When auto_order_sections is on, middle sections are reordered by variation profile (Hero first, last section last).
 */
function lf_get_homepage_sections(): array {
	if (!is_front_page()) {
		return [];
	}
	$raw = function_exists('get_field') ? get_field('homepage_sections', 'option') : null;
	$out = [];
	if (!empty($raw) && is_array($raw)) {
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
	}
	if (empty($out)) {
		$out = lf_get_default_homepage_sections();
	}
	$auto_order = function_exists('get_field') ? get_field('auto_order_sections', 'option') : false;
	if ($auto_order && count($out) > 2) {
		$out = lf_apply_profile_section_order($out);
	}
	return $out;
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
 * Render one homepage section. Maps section_type to template; variant from registry (profile + override).
 */
function lf_render_homepage_section(array $section, int $index): void {
	$map = lf_homepage_section_template_map();
	$type = $section['section_type'] ?? '';
	if (!isset($map[$type])) {
		return;
	}
	$template = $map[$type];
	$override_variant = $section['layout_variant'] ?? 'default';
	$variant = function_exists('lf_get_block_variant') ? lf_get_block_variant($template, $override_variant) : $override_variant;
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
