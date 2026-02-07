<?php
/**
 * Homepage controller: locked structure, config-driven sections, no Gutenberg.
 * Section order is fixed; content from structured config (option + niche defaults).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/** Option key for section config (keyed by section type). */
const LF_HOMEPAGE_CONFIG_OPTION = 'lf_homepage_section_config';

/** Option key for last applied niche (wizard source of truth). */
const LF_HOMEPAGE_NICHE_OPTION = 'lf_homepage_niche_slug';

/**
 * Canonical section order: Hero → Services → Service Areas → Social Proof → FAQs → Final CTA.
 * map_nap is available but off by default (no duplicate/redundant sections).
 *
 * @return string[]
 */
function lf_homepage_controller_order(): array {
	return [
		'hero',
		'service_grid',
		'service_areas',
		'trust_reviews',
		'faq_accordion',
		'cta',
		'map_nap',
	];
}

/**
 * Map section type to block template name.
 */
function lf_homepage_section_template_map(): array {
	return [
		'hero'           => 'hero',
		'trust_reviews'  => 'trust-reviews',
		'service_grid'  => 'service-grid',
		'service_areas' => 'service-areas',
		'cta'           => 'cta',
		'faq_accordion' => 'faq-accordion',
		'map_nap'       => 'map-nap',
	];
}

/**
 * Default config for one section (enabled, variant, and type-specific fields).
 *
 * @return array<string, mixed>
 */
function lf_homepage_default_section_config(string $section_type): array {
	$base = [
		'enabled' => true,
		'variant' => 'default',
	];
	switch ($section_type) {
		case 'hero':
			return array_merge($base, [
				'hero_headline'     => __('Professional Home Services in Your Area', 'leadsforward-core'),
				'hero_subheadline'  => __('Trusted by local homeowners. Get a free quote or schedule service today.', 'leadsforward-core'),
				'hero_cta_override' => '',
			]);
		case 'trust_reviews':
			return array_merge($base, [
				'trust_max_items' => 1,
				'trust_heading'   => __('What Our Customers Say', 'leadsforward-core'),
			]);
		case 'service_grid':
			return array_merge($base, [
				'section_heading' => __('Our Services', 'leadsforward-core'),
				'section_intro'   => __('Professional service you can trust. We handle the work so you can focus on what matters.', 'leadsforward-core'),
			]);
		case 'service_areas':
			return array_merge($base, [
				'section_heading' => __('Proudly Serving Our Community', 'leadsforward-core'),
				'section_intro'   => __('We’re your local team. These cities and the surrounding region—contact us to confirm we serve your area.', 'leadsforward-core'),
			]);
		case 'faq_accordion':
		case 'map_nap':
			return $base;
		case 'cta':
			return array_merge($base, [
				'cta_primary_override'   => '',
				'cta_secondary_override' => '',
				'cta_ghl_override'      => '',
			]);
		default:
			return $base;
	}
}

/**
 * Build full default config for all section types (optionally from niche).
 *
 * @return array<string, array<string, mixed>>
 */
function lf_homepage_default_config(?string $niche_slug = null): array {
	$order = lf_homepage_controller_order();
	$config = [];
	$niche = $niche_slug && function_exists('lf_get_niche') ? lf_get_niche($niche_slug) : null;
	$section_enabled = $niche['section_enabled'] ?? null;
	foreach ($order as $type) {
		$sec = lf_homepage_default_section_config($type);
		if (is_array($section_enabled) && array_key_exists($type, $section_enabled)) {
			$sec['enabled'] = (bool) $section_enabled[$type];
		}
		if ($niche && $type === 'hero') {
			if (!empty($niche['hero_headline_default'])) {
				$sec['hero_headline'] = $niche['hero_headline_default'];
			}
			if (!empty($niche['hero_subheadline_default'])) {
				$sec['hero_subheadline'] = $niche['hero_subheadline_default'];
			}
		}
		$config[$type] = $sec;
	}
	// Canonical layout: map_nap off by default (no duplicate sections).
	$config['map_nap']['enabled'] = false;
	return $config;
}

/**
 * Apply niche defaults to homepage config and save. Used by setup wizard.
 * Optionally substitutes [Your City] in hero headline with first service area name.
 *
 * @param string     $niche_slug Niche identifier from registry.
 * @param array|null $wizard_data Optional wizard payload (e.g. service_areas for city substitution).
 */
function lf_homepage_apply_niche_config(string $niche_slug, ?array $wizard_data = null): void {
	$config = lf_homepage_default_config($niche_slug);
	$city_placeholder = '[Your City]';
	$first_area_name = '';
	if (!empty($wizard_data['service_areas']) && is_array($wizard_data['service_areas'])) {
		$first = reset($wizard_data['service_areas']);
		if (is_array($first)) {
			$first_area_name = $first['name'] ?? '';
		} else {
			$first_area_name = trim((string) $first);
			if (preg_match('/^(.+),\s*[A-Za-z]{2}$/', $first_area_name, $m)) {
				$first_area_name = trim($m[1]);
			}
		}
	}
	if ($first_area_name !== '' && isset($config['hero']['hero_headline'])) {
		$config['hero']['hero_headline'] = str_replace($city_placeholder, $first_area_name, $config['hero']['hero_headline']);
	}
	if ($first_area_name !== '' && isset($config['hero']['hero_subheadline'])) {
		$config['hero']['hero_subheadline'] = str_replace($city_placeholder, $first_area_name, $config['hero']['hero_subheadline']);
	}
	update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
	update_option(LF_HOMEPAGE_NICHE_OPTION, $niche_slug, true);
}

/**
 * Get stored homepage section config (option). Migrates from ACF flexible content once if empty.
 *
 * @return array<string, array<string, mixed>>
 */
function lf_get_homepage_section_config(): array {
	$stored = get_option(LF_HOMEPAGE_CONFIG_OPTION, null);
	if (is_array($stored) && !empty($stored)) {
		return lf_homepage_merge_config_with_defaults($stored);
	}
	$migrated = lf_homepage_migrate_from_acf();
	if (is_array($migrated) && !empty($migrated)) {
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $migrated, true);
		return lf_homepage_merge_config_with_defaults($migrated);
	}
	$niche = get_option(LF_HOMEPAGE_NICHE_OPTION, '');
	return lf_homepage_default_config($niche ?: null);
}

/**
 * Merge stored config with defaults so new section types and keys always exist.
 *
 * @param array<string, array<string, mixed>> $stored
 * @return array<string, array<string, mixed>>
 */
function lf_homepage_merge_config_with_defaults(array $stored): array {
	$order = lf_homepage_controller_order();
	$out = [];
	foreach ($order as $type) {
		$default = lf_homepage_default_section_config($type);
		$row = $stored[$type] ?? [];
		if (!is_array($row)) {
			$row = [];
		}
		$out[$type] = array_merge($default, $row);
	}
	return $out;
}

/**
 * Migrate from legacy ACF homepage_sections flexible content to option.
 *
 * @return array<string, array<string, mixed>>|null
 */
function lf_homepage_migrate_from_acf(): ?array {
	if (!function_exists('get_field')) {
		return null;
	}
	$raw = get_field('homepage_sections', 'option');
	if (empty($raw) || !is_array($raw)) {
		return null;
	}
	$by_type = [];
	foreach ($raw as $row) {
		$type = $row['section_type'] ?? '';
		if ($type === '') {
			continue;
		}
		$by_type[$type] = [
			'enabled' => true,
			'variant' => $row['layout_variant'] ?? 'default',
			'hero_headline'     => $row['hero_headline'] ?? '',
			'hero_subheadline'  => $row['hero_subheadline'] ?? '',
			'hero_cta_override' => $row['hero_cta_override'] ?? '',
			'trust_max_items'   => isset($row['trust_max_items']) ? (int) $row['trust_max_items'] : 1,
			'trust_heading'     => $row['trust_heading'] ?? '',
			'section_heading'   => $row['section_heading'] ?? '',
			'section_intro'     => $row['section_intro'] ?? '',
			'cta_primary_override'   => $row['cta_primary_override'] ?? '',
			'cta_secondary_override' => $row['cta_secondary_override'] ?? '',
			'cta_ghl_override'      => $row['cta_ghl_override'] ?? '',
		];
	}
	$order = lf_homepage_controller_order();
	$config = [];
	foreach ($order as $type) {
		$default = lf_homepage_default_section_config($type);
		$config[$type] = array_merge($default, $by_type[$type] ?? []);
	}
	return $config;
}

/**
 * Get homepage sections in fixed order, enabled only. Only runs on front; no query when not needed.
 *
 * @return array<int, array<string, mixed>>
 */
function lf_get_homepage_sections(): array {
	if (!is_front_page()) {
		return [];
	}
	$config = lf_get_homepage_section_config();
	$order = lf_homepage_controller_order();
	$out = [];
	$index = 0;
	foreach ($order as $type) {
		$sec = $config[$type] ?? null;
		if (!is_array($sec) || empty($sec['enabled'])) {
			continue;
		}
		$out[] = array_merge(
			['section_type' => $type, 'layout_variant' => $sec['variant'] ?? 'default'],
			$sec
		);
		$index++;
	}
	return $out;
}

/**
 * Resolved CTA: section > homepage > global. Returns primary_text, secondary_text, ghl_embed, primary_type.
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
 * Phone number for call CTA. From Business Info.
 */
function lf_get_cta_phone(): string {
	$phone = lf_get_option('lf_business_phone', 'option');
	return is_string($phone) ? preg_replace('/\s+/', '', $phone) : '';
}

/**
 * Render one homepage section. Maps section_type to template; variant from block registry.
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
		'context'    => [
			'homepage' => true,
			'section'  => $section,
			'index'    => $index,
		],
	];
	lf_render_block_template($template, $block, false, $block['context']);
}
