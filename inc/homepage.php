<?php
/**
 * Homepage controller: locked structure, config-driven sections, no Gutenberg.
 * Section order is configurable (hero fixed); content from structured config (option + niche defaults).
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

/** Option key for last applied niche (setup source of truth). */
const LF_HOMEPAGE_NICHE_OPTION = 'lf_homepage_niche_slug';

/** Option key for section order (drag-and-drop on Homepage admin). */
const LF_HOMEPAGE_ORDER_OPTION = 'lf_homepage_section_order';

/** Option key to track manual overrides (admin saves). */
const LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION = 'lf_homepage_manual_override';

/**
 * Recommended default order: Hero → Trust → Services → Projects → Benefits → Media → Process → FAQ → Links → Areas + Map → CTA.
 * Drag-and-drop order is stored in options and always respected.
 *
 * @return string[]
 */
function lf_homepage_default_order(): array {
	return lf_sections_default_order('homepage');
}

/**
 * Legacy order preserved for pre-existing sites without a stored order.
 *
 * @return string[]
 */
function lf_homepage_legacy_order(): array {
	return [
		'hero',
		'trust_bar',
		'service_intro',
		'service_details',
		'content_image',
		'image_content',
		'benefits',
		'process',
		'faq_accordion',
		'cta',
		'related_links',
		'map_nap',
	];
}

/**
 * Sanitize section order: keep hero first, drop unknowns, append missing.
 *
 * @param array $order
 * @return string[]
 */
function lf_homepage_sanitize_order(array $order, bool $append_missing = true): array {
	$canonical = lf_homepage_default_order();
	$clean = [];
	foreach ($order as $item) {
		if (!is_string($item)) {
			continue;
		}
		$item = trim($item);
		if ($item === '' || in_array($item, $clean, true)) {
			continue;
		}
		$base = lf_homepage_base_section_type($item);
		if ($base !== '' && in_array($base, $canonical, true)) {
			$clean[] = $item;
		}
	}
	if ($append_missing) {
		foreach ($canonical as $type) {
			if (!in_array($type, $clean, true)) {
				$clean[] = $type;
			}
		}
	}
	return $clean;
}

/**
 * Resolve canonical section type from a homepage section ID.
 *
 * Supports repeated IDs in the shape "{type}__{n}".
 */
function lf_homepage_base_section_type(string $section_id): string {
	$section_id = trim($section_id);
	if ($section_id === '') {
		return '';
	}
	$parts = explode('__', $section_id, 2);
	return sanitize_text_field((string) ($parts[0] ?? ''));
}

/**
 * Return section order (stored order if present; hero fixed).
 *
 * @return string[]
 */
function lf_homepage_controller_order(): array {
	$stored = get_option(LF_HOMEPAGE_ORDER_OPTION, null);
	if (is_array($stored) && !empty($stored)) {
		$order = lf_homepage_sanitize_order($stored, false);
		$defaults = lf_homepage_default_order();
		foreach ($defaults as $type) {
			if (!in_array($type, $order, true)) {
				$order[] = $type;
			}
		}
		if (in_array('trust_reviews', $order, true) && in_array('process', $order, true)) {
			$order = array_values(array_filter($order, function ($type) {
				return $type !== 'trust_reviews';
			}));
			$process_index = array_search('process', $order, true);
			if ($process_index === false) {
				$order[] = 'trust_reviews';
			} else {
				array_splice($order, $process_index, 0, ['trust_reviews']);
			}
		}
		return $order;
	}
	$stored_config = get_option(LF_HOMEPAGE_CONFIG_OPTION, null);
	if (is_array($stored_config) && !empty($stored_config)) {
		$has_new_instances = isset($stored_config['content_image_a']) || isset($stored_config['image_content_b']) || isset($stored_config['content_image_c']);
		if (!$has_new_instances) {
			return lf_homepage_legacy_order();
		}
	}
	return lf_homepage_default_order();
}

/**
 * Map section type to block template name.
 */
/**
 * Default config for one section (enabled, variant, and type-specific fields).
 *
 * @return array<string, mixed>
 */
function lf_homepage_default_section_config(string $section_type, string $niche_slug = ''): array {
	$base = [
		'enabled' => true,
		'variant' => 'default',
	];
	$defaults = lf_sections_defaults_for($section_type, $niche_slug);
	if (!empty($defaults)) {
		$config = array_merge($base, $defaults);
		if (in_array($section_type, ['content_image', 'image_content'], true)) {
			$config['enabled'] = false;
		}
		if ($section_type === 'project_gallery') {
			$config['enabled'] = false;
		}
		if ($section_type === 'hero') {
			$config['hero_headline'] = __('Trusted Local Home Services in [Your City]', 'leadsforward-core');
			$config['hero_subheadline'] = __('Fast response times, clear pricing, and workmanship backed by warranty. Get expert help from a local team you can rely on.', 'leadsforward-core');
			$config['cta_primary_override'] = '';
			$config['cta_secondary_override'] = '';
			$config['cta_primary_action'] = '';
			$config['cta_primary_url'] = '';
			$config['cta_secondary_action'] = '';
			$config['cta_secondary_url'] = '';
		}
		if ($section_type === 'cta' && !array_key_exists('cta_ghl_override', $config)) {
			$config['cta_ghl_override'] = '';
		}
		if ($section_type === 'cta' && !array_key_exists('cta_subheadline', $config)) {
			$config['cta_subheadline'] = '';
		}
		return $config;
	}
	switch ($section_type) {
		case 'hero':
			return array_merge($base, [
				'hero_headline'     => __('Trusted Local Home Services in [Your City]', 'leadsforward-core'),
				'hero_subheadline'  => __('Fast response times, clear pricing, and workmanship backed by warranty. Get expert help from a local team you can rely on.', 'leadsforward-core'),
				'cta_primary_override' => '',
				'cta_secondary_override' => '',
				'cta_primary_action'   => '',
				'cta_primary_url'      => '',
				'cta_secondary_action' => '',
				'cta_secondary_url'    => '',
			]);
		case 'trust_reviews':
			return array_merge($base, [
				'trust_max_items' => 3,
				'trust_heading'   => __('What Our Customers Say', 'leadsforward-core'),
			]);
		case 'service_grid':
			return array_merge($base, [
				'section_heading' => __('Services Built for Local Homeowners', 'leadsforward-core'),
				'section_intro'   => __('From quick fixes to full projects, we handle the work start-to-finish with clear scopes and professional crews.', 'leadsforward-core'),
			]);
		case 'service_areas':
			return array_merge($base, [
				'section_heading' => __('Areas We Serve', 'leadsforward-core'),
				'section_intro'   => __('Local, responsive, and nearby. If you’re close, chances are we already serve your neighborhood.', 'leadsforward-core'),
			]);
		case 'faq_accordion':
			return array_merge($base, [
				'section_heading' => __('Frequently Asked Questions', 'leadsforward-core'),
				'section_intro'   => __('Straight answers to common questions. If you need details for your project, we can help fast.', 'leadsforward-core'),
			]);
		case 'map_nap':
			return array_merge($base, [
				'section_heading' => __('Areas We Serve', 'leadsforward-core'),
				'section_intro'   => __('Find us on the map and explore the neighborhoods we serve every day.', 'leadsforward-core'),
			]);
		case 'cta':
			return array_merge($base, [
				'cta_headline'          => __('Get Your Fast, No-Obligation Estimate', 'leadsforward-core'),
				'cta_primary_override'  => '',
				'cta_secondary_override' => '',
				'cta_ghl_override'      => '',
				'cta_primary_action'    => '',
				'cta_primary_url'       => '',
				'cta_secondary_action'  => '',
				'cta_secondary_url'     => '',
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
		$sec = lf_homepage_default_section_config($type, $niche_slug ?? '');
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
	// Ensure content/image A/B/C and FAQ are enabled by default for density.
	foreach (['content_image_a', 'image_content_b', 'content_image_c', 'faq_accordion'] as $media_type) {
		if (isset($config[$media_type]) && is_array($config[$media_type])) {
			$config[$media_type]['enabled'] = true;
		}
	}
	if (isset($config['map_nap'])) {
		$config['map_nap']['enabled'] = true;
	}
	return $config;
}

/**
 * Empty config for post-reset: all sections disabled, all copy empty. Used by Reset site (dev).
 *
 * @return array<string, array<string, mixed>>
 */
function lf_homepage_empty_config(): array {
	$order = lf_homepage_controller_order();
	$config = [];
	foreach ($order as $type) {
		$default = lf_homepage_default_section_config($type);
		$empty = [
			'enabled' => false,
			'variant' => 'default',
		];
		foreach ($default as $key => $value) {
			if ($key === 'enabled' || $key === 'variant') {
				continue;
			}
			// Keep numeric fields at safe minimum (e.g. trust_max_items has min 1 in admin)
			$empty[$key] = is_int($value) ? 1 : '';
		}
		$config[$type] = $empty;
	}
	return $config;
}

/**
 * Apply niche defaults to homepage config and save. Used by site setup flow.
 * Optionally substitutes [Your City] in hero headline with first service area name.
 *
 * @param string     $niche_slug Niche identifier from registry.
 * @param array|null $wizard_data Optional setup payload (e.g. service_areas for city substitution).
 */
function lf_homepage_apply_niche_config(string $niche_slug, ?array $wizard_data = null): void {
	$config = lf_homepage_default_config($niche_slug);
	$city_placeholder = '[Your City]';
	$first_area_name = '';
	if (!empty($wizard_data['homepage_city'])) {
		$first_area_name = sanitize_text_field((string) $wizard_data['homepage_city']);
	} elseif (!empty($wizard_data['service_areas']) && is_array($wizard_data['service_areas'])) {
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
	update_option(LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION, false, true);
}

/**
 * Get stored homepage section config (option). Migrates from ACF flexible content once if empty.
 *
 * @return array<string, array<string, mixed>>
 */
function lf_get_homepage_section_config(): array {
	$stored = get_option(LF_HOMEPAGE_CONFIG_OPTION, null);
	if (is_array($stored)) {
		$stored = wp_unslash($stored);
	}
	if (is_array($stored) && !empty($stored)) {
		$config = lf_homepage_merge_config_with_defaults($stored);
		$manual = (bool) get_option(LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION, false);
		$wizard_done = (bool) get_option('lf_setup_wizard_complete', false);
		$has_enabled = false;
		foreach (lf_homepage_default_order() as $type) {
			if (!empty($config[$type]['enabled'])) {
				$has_enabled = true;
				break;
			}
		}
		if (!$has_enabled && !$manual && $wizard_done) {
			$niche = get_option(LF_HOMEPAGE_NICHE_OPTION, '');
			$config = lf_homepage_default_config($niche ?: null);
			update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		}
		return $config;
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
	$stored = lf_homepage_upgrade_legacy_config($stored);
	$order = lf_homepage_controller_order();
	$out = [];
	foreach ($order as $section_id) {
		$type = lf_homepage_base_section_type((string) $section_id);
		$default = lf_homepage_default_section_config($type);
		$row = $stored[$section_id] ?? [];
		if (!is_array($row)) {
			$row = [];
		}
		if ($type === 'hero') {
			$row = lf_homepage_normalize_hero_cta_keys($row);
		}
		if (function_exists('lf_sections_normalize_service_details_settings')) {
			$row = lf_sections_normalize_service_details_settings($type, $row);
		}
		$out[$section_id] = array_merge($default, $row);
	}
	return $out;
}

function lf_homepage_normalize_hero_cta_keys(array $row): array {
	if (!empty($row['hero_cta_override']) && empty($row['cta_primary_override'])) {
		$row['cta_primary_override'] = $row['hero_cta_override'];
	}
	if (!empty($row['hero_cta_secondary_override']) && empty($row['cta_secondary_override'])) {
		$row['cta_secondary_override'] = $row['hero_cta_secondary_override'];
	}
	if (!empty($row['hero_cta_action']) && empty($row['cta_primary_action'])) {
		$row['cta_primary_action'] = $row['hero_cta_action'];
	}
	if (!empty($row['hero_cta_url']) && empty($row['cta_primary_url'])) {
		$row['cta_primary_url'] = $row['hero_cta_url'];
	}
	if (!empty($row['hero_cta_secondary_action']) && empty($row['cta_secondary_action'])) {
		$row['cta_secondary_action'] = $row['hero_cta_secondary_action'];
	}
	if (!empty($row['hero_cta_secondary_url']) && empty($row['cta_secondary_url'])) {
		$row['cta_secondary_url'] = $row['hero_cta_secondary_url'];
	}
	unset(
		$row['hero_cta_override'],
		$row['hero_cta_secondary_override'],
		$row['hero_cta_action'],
		$row['hero_cta_url'],
		$row['hero_cta_secondary_action'],
		$row['hero_cta_secondary_url']
	);
	return $row;
}

/**
 * Upgrade legacy section IDs into the shared section library IDs.
 *
 * @param array<string, array<string, mixed>> $stored
 * @return array<string, array<string, mixed>>
 */
function lf_homepage_upgrade_legacy_config(array $stored): array {
	if (!isset($stored['trust_bar']) && isset($stored['trust_reviews']) && is_array($stored['trust_reviews'])) {
		$legacy = $stored['trust_reviews'];
		$stored['trust_bar'] = [
			'enabled' => !empty($legacy['enabled']),
			'variant' => $legacy['variant'] ?? 'default',
			'trust_heading' => $legacy['trust_heading'] ?? '',
			'trust_badges' => '',
			'trust_rating' => '',
			'trust_review_count' => '',
		];
	}
	if (!isset($stored['benefits']) && isset($stored['service_grid']) && is_array($stored['service_grid'])) {
		$legacy = $stored['service_grid'];
		$stored['benefits'] = [
			'enabled' => !empty($legacy['enabled']),
			'variant' => $legacy['variant'] ?? 'default',
			'section_heading' => $legacy['section_heading'] ?? '',
			'section_intro' => $legacy['section_intro'] ?? '',
			'benefits_items' => '',
		];
	}
	if (!isset($stored['map_nap']) && isset($stored['service_areas']) && is_array($stored['service_areas'])) {
		$legacy = $stored['service_areas'];
		$stored['map_nap'] = [
			'enabled' => !empty($legacy['enabled']),
			'variant' => $legacy['variant'] ?? 'default',
			'section_heading' => $legacy['section_heading'] ?? '',
			'section_intro' => $legacy['section_intro'] ?? '',
		];
	}
	return $stored;
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
			'cta_primary_override' => $row['cta_primary_override'] ?? ($row['hero_cta_override'] ?? ''),
			'cta_secondary_override' => $row['cta_secondary_override'] ?? ($row['hero_cta_secondary_override'] ?? ''),
			'cta_primary_action'   => $row['cta_primary_action'] ?? ($row['hero_cta_action'] ?? ''),
			'cta_primary_url'      => $row['cta_primary_url'] ?? ($row['hero_cta_url'] ?? ''),
			'cta_secondary_action' => $row['cta_secondary_action'] ?? ($row['hero_cta_secondary_action'] ?? ''),
			'cta_secondary_url'    => $row['cta_secondary_url'] ?? ($row['hero_cta_secondary_url'] ?? ''),
			'trust_max_items'   => isset($row['trust_max_items']) ? (int) $row['trust_max_items'] : 1,
			'trust_heading'     => $row['trust_heading'] ?? '',
			'section_heading'   => $row['section_heading'] ?? '',
			'section_intro'     => $row['section_intro'] ?? '',
			'cta_primary_override'   => $row['cta_primary_override'] ?? '',
			'cta_secondary_override' => $row['cta_secondary_override'] ?? '',
			'cta_ghl_override'      => $row['cta_ghl_override'] ?? '',
			'cta_primary_action'     => $row['cta_primary_action'] ?? '',
			'cta_primary_url'        => $row['cta_primary_url'] ?? '',
			'cta_secondary_action'   => $row['cta_secondary_action'] ?? '',
			'cta_secondary_url'      => $row['cta_secondary_url'] ?? '',
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
	foreach ($order as $section_id) {
		$type = lf_homepage_base_section_type((string) $section_id);
		$sec = $config[$section_id] ?? null;
		if (!is_array($sec) || empty($sec['enabled'])) {
			continue;
		}
		$out[] = array_merge(
			['section_id' => (string) $section_id, 'section_type' => $type, 'layout_variant' => $sec['variant'] ?? 'default'],
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
	$section = is_array($context['section'] ?? null) ? $context['section'] : [];
	return function_exists('lf_resolve_cta') ? lf_resolve_cta($context, $section, []) : [];
}

/**
 * Phone number for call CTA. From Business Info.
 */
function lf_get_cta_phone(): string {
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$phone = $entity['phone_display'] ?? '';
		return is_string($phone) ? preg_replace('/\s+/', '', $phone) : '';
	}
	$phone = lf_get_option('lf_business_phone', 'option');
	return is_string($phone) ? preg_replace('/\s+/', '', $phone) : '';
}

/**
 * Render one homepage section. Maps section_type to template; variant from block registry.
 */
function lf_render_homepage_section(array $section, int $index): void {
	$type = $section['section_type'] ?? '';
	$section_id = $section['section_id'] ?? $type;
	if ($type === '') {
		return;
	}
	$post = get_post();
	if (!$post instanceof \WP_Post) {
		return;
	}
	if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
		$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
		$allowed = (isset($registry[$type]) && function_exists('lf_ai_studio_homepage_allowed_field_keys'))
			? lf_ai_studio_homepage_allowed_field_keys($type, $registry[$type])
			: [];
		$rendered = [];
		foreach ($allowed as $key) {
			$value = $section[$key] ?? null;
			if (is_array($value)) {
				if (!empty($value)) {
					$rendered[] = $key;
				}
			} elseif (is_string($value)) {
				if (trim($value) !== '') {
					$rendered[] = $key;
				}
			} elseif ($value !== null && $value !== '') {
				$rendered[] = $key;
			}
		}
		error_log(sprintf(
			'LF DEBUG: Homepage section=%s allowed=[%s] rendered=[%s]',
			$type,
			implode(', ', $allowed),
			implode(', ', $rendered)
		));
	}
	echo '<div class="lf-inline-section-wrap" data-lf-section-wrap="1" data-lf-section-id="' . esc_attr((string) $section_id) . '" data-lf-section-type="' . esc_attr((string) $type) . '">';
	lf_sections_render_section($type, 'homepage', $section, $post);
	echo '</div>';
}
