<?php
/**
 * Niche definition registry. Centralized per-niche config for wizard and structure.
 * Add new niches here without rewriting core wizard logic.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * All required page slugs the wizard creates. Same for all niches; titles may vary.
 */
function lf_wizard_required_page_slugs(): array {
	return [
		'home',
		'about-us',
		'our-services',
		'our-service-areas',
		'contact',
		'privacy-policy',
		'terms-of-use',
		'thank-you',
	];
}

/**
 * Default page title key per slug. Niche can override via required_pages.
 */
function lf_wizard_default_page_titles(): array {
	return [
		'home'              => __('Home', 'leadsforward-core'),
		'about-us'          => __('About Us', 'leadsforward-core'),
		'our-services'      => __('Our Services', 'leadsforward-core'),
		'our-service-areas' => __('Our Service Areas', 'leadsforward-core'),
		'contact'           => __('Contact', 'leadsforward-core'),
		'privacy-policy'    => __('Privacy Policy', 'leadsforward-core'),
		'terms-of-use'      => __('Terms of Use', 'leadsforward-core'),
		'thank-you'         => __('Thank You', 'leadsforward-core'),
	];
}

/**
 * Niche registry. Each niche: name, slug, services, required_pages (optional overrides), homepage_section_order, variation_profile, cta defaults, schema.
 */
function lf_get_niche_registry(): array {
	return [
		'roofing' => [
			'name'                  => __('Roofing', 'leadsforward-core'),
			'slug'                  => 'roofing',
			'services'              => [
				__('Roof Repair', 'leadsforward-core'),
				__('Roof Replacement', 'leadsforward-core'),
				__('Emergency Roofing', 'leadsforward-core'),
			],
			'required_pages'        => [], // use default titles
			'homepage_section_order' => ['hero', 'trust_reviews', 'service_grid', 'cta', 'faq_accordion', 'cta', 'map_nap'],
			'variation_profile'     => 'c', // Trust Heavy
			'cta_primary_default'   => __('Get a Free Roof Inspection', 'leadsforward-core'),
			'cta_secondary_default' => __('Call for Emergency Service', 'leadsforward-core'),
			'schema_review_enabled'  => true,
		],
		'plumbing' => [
			'name'                  => __('Plumbing', 'leadsforward-core'),
			'slug'                  => 'plumbing',
			'services'              => [
				__('Drain Cleaning', 'leadsforward-core'),
				__('Water Heater Repair', 'leadsforward-core'),
				__('Pipe Repair', 'leadsforward-core'),
				__('Emergency Plumbing', 'leadsforward-core'),
			],
			'required_pages'        => [],
			'homepage_section_order' => ['hero', 'trust_reviews', 'service_grid', 'cta', 'faq_accordion', 'cta', 'map_nap'],
			'variation_profile'     => 'c',
			'cta_primary_default'   => __('Schedule a Plumber', 'leadsforward-core'),
			'cta_secondary_default' => __('24/7 Emergency Service', 'leadsforward-core'),
			'schema_review_enabled'  => true,
		],
		'hvac' => [
			'name'                  => __('HVAC', 'leadsforward-core'),
			'slug'                  => 'hvac',
			'services'              => [
				__('AC Repair', 'leadsforward-core'),
				__('Heating Repair', 'leadsforward-core'),
				__('HVAC Installation', 'leadsforward-core'),
				__('Emergency HVAC', 'leadsforward-core'),
			],
			'required_pages'        => [],
			'homepage_section_order' => ['hero', 'service_grid', 'trust_reviews', 'cta', 'faq_accordion', 'cta', 'map_nap'],
			'variation_profile'     => 'd', // Service Heavy
			'cta_primary_default'   => __('Request HVAC Service', 'leadsforward-core'),
			'cta_secondary_default' => __('Get a Free Estimate', 'leadsforward-core'),
			'schema_review_enabled'  => true,
		],
		'general' => [
			'name'                  => __('General (Local Services)', 'leadsforward-core'),
			'slug'                  => 'general',
			'services'              => [
				__('Main Service', 'leadsforward-core'),
				__('Additional Service', 'leadsforward-core'),
			],
			'required_pages'        => [],
			'homepage_section_order' => ['hero', 'trust_reviews', 'service_grid', 'cta', 'faq_accordion', 'cta', 'map_nap'],
			'variation_profile'     => 'a',
			'cta_primary_default'   => __('Contact Us', 'leadsforward-core'),
			'cta_secondary_default' => __('Get a Quote', 'leadsforward-core'),
			'schema_review_enabled'  => true,
		],
	];
}

/**
 * Get one niche by slug. Returns null if not found.
 */
function lf_get_niche(string $slug): ?array {
	$reg = lf_get_niche_registry();
	return $reg[$slug] ?? null;
}

/** Default section type order when niche has none. */
function lf_wizard_default_section_order(): array {
	return ['hero', 'trust_reviews', 'service_grid', 'cta', 'faq_accordion', 'cta', 'map_nap'];
}

/**
 * Homepage section order for niche (array of section_type). Used when seeding ACF or defaults.
 */
function lf_niche_homepage_section_order(string $niche_slug): array {
	$niche = lf_get_niche($niche_slug);
	if (!$niche || empty($niche['homepage_section_order'])) {
		return lf_wizard_default_section_order();
	}
	return $niche['homepage_section_order'];
}
