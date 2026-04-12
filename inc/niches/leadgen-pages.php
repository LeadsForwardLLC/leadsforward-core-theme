<?php
/**
 * Per-niche and cross-niche lead generation page slugs for setup / menus / sitemap HTML.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Core pages every lead-gen site gets (health checks and guardrails use the same list).
 *
 * @return list<string>
 */
function lf_wizard_core_page_slugs(): array {
	return [
		'home',
		'about-us',
		'why-choose-us',
		'our-services',
		'service-areas',
		'reviews',
		'blog',
		'sitemap',
		'contact',
		'privacy-policy',
		'terms-of-service',
		'thank-you',
	];
}

/**
 * Optional pages added for every niche (financing, FAQ hub, project gallery landing).
 *
 * @return list<string>
 */
function lf_leadgen_cross_niche_page_slugs(): array {
	return [
		'financing',
		'faq',
		'our-work',
	];
}

/**
 * Extra landing pages keyed by niche slug (1–2 high-intent URLs each).
 *
 * @return array<string, list<string>>
 */
function lf_niche_extra_leadgen_page_slugs_map(): array {
	return [
		'foundation-repair' => [
			'foundation-warning-signs',
		],
		'roofing' => [
			'storm-damage-roofing',
		],
		'pressure-washing' => [
			'commercial-pressure-washing',
		],
		'tree-service' => [
			'emergency-tree-service',
		],
		'hvac' => [
			'hvac-maintenance-plan',
		],
		'windows-doors' => [
			'energy-efficient-windows',
		],
		'waterproofing' => [
			'basement-waterproofing-guide',
		],
		'paving' => [
			'driveway-sealcoating',
		],
		'solar' => [
			'solar-battery-storage',
		],
		'water-damage' => [
			'emergency-water-removal',
		],
		'general' => [],
		'carpet-cleaning' => [
			'pet-stain-treatment',
		],
		'stamped-concrete' => [
			'decorative-concrete-options',
		],
		'pool-service' => [
			'pool-opening-closing',
		],
		'remodeling' => [
			'whole-home-remodeling',
		],
		'landscaping' => [
			'seasonal-lawn-care',
		],
		'kitchen-remodeling' => [
			'kitchen-design-ideas',
		],
	];
}

/**
 * Default titles for extended / niche-specific slugs (merged after lf_wizard_default_page_titles()).
 *
 * @return array<string, string>
 */
function lf_wizard_extended_page_titles(): array {
	return [
		'financing' => __('Financing', 'leadsforward-core'),
		'faq' => __('FAQ', 'leadsforward-core'),
		'our-work' => __('Our Work', 'leadsforward-core'),
		'foundation-warning-signs' => __('Signs of Foundation Problems', 'leadsforward-core'),
		'storm-damage-roofing' => __('Storm & Insurance Claims', 'leadsforward-core'),
		'commercial-pressure-washing' => __('Commercial Pressure Washing', 'leadsforward-core'),
		'emergency-tree-service' => __('Emergency Tree Service', 'leadsforward-core'),
		'hvac-maintenance-plan' => __('Maintenance Plans', 'leadsforward-core'),
		'energy-efficient-windows' => __('Energy-Efficient Windows', 'leadsforward-core'),
		'basement-waterproofing-guide' => __('Basement & Crawl Space Waterproofing', 'leadsforward-core'),
		'driveway-sealcoating' => __('Sealcoating & Maintenance', 'leadsforward-core'),
		'solar-battery-storage' => __('Solar & Battery Storage', 'leadsforward-core'),
		'emergency-water-removal' => __('Emergency Water Removal', 'leadsforward-core'),
		'pet-stain-treatment' => __('Pet Stains & Odor', 'leadsforward-core'),
		'decorative-concrete-options' => __('Patterns & Decorative Concrete', 'leadsforward-core'),
		'pool-opening-closing' => __('Pool Opening & Closing', 'leadsforward-core'),
		'whole-home-remodeling' => __('Whole-Home Remodeling', 'leadsforward-core'),
		'seasonal-lawn-care' => __('Seasonal Lawn Care', 'leadsforward-core'),
		'kitchen-design-ideas' => __('Kitchen Design Ideas', 'leadsforward-core'),
	];
}

/**
 * Full ordered slug list for setup: core + cross-niche optionals + niche-specific extras.
 *
 * Result is passed through `lf_leadgen_page_slugs` so manifests or integrations can adjust slugs.
 *
 * @param array<string, mixed>|null $niche Row from lf_get_niche_registry().
 * @return list<string>
 */
function lf_wizard_page_slugs_for_niche(?array $niche): array {
	$core = lf_wizard_core_page_slugs();
	$cross = lf_leadgen_cross_niche_page_slugs();
	$slug = is_array($niche) ? sanitize_title((string) ( $niche['slug'] ?? '' )) : '';
	$map = lf_niche_extra_leadgen_page_slugs_map();
	$extras = ( $slug !== '' && isset($map[ $slug ]) && is_array($map[ $slug ]) ) ? $map[ $slug ] : [];
	$merged = array_merge($core, $cross, $extras);
	$out = [];
	foreach ($merged as $s) {
		$s = sanitize_title((string) $s);
		if ($s !== '' && ! in_array($s, $out, true)) {
			$out[] = $s;
		}
	}
	return apply_filters('lf_leadgen_page_slugs', $out, $slug !== '' ? $slug : null);
}

/**
 * Preferred order for labels on the HTML sitemap page (only outputs links for pages that exist in $created_pages).
 *
 * @param array<string, int> $created_pages slug => post ID
 * @return list<string> ordered slugs
 */
function lf_wizard_sitemap_slug_order(array $created_pages): array {
	$preferred = array_merge(
		lf_wizard_core_page_slugs(),
		lf_leadgen_cross_niche_page_slugs(),
		array_keys(lf_wizard_extended_page_titles())
	);
	$unique = [];
	foreach ($preferred as $s) {
		if (! in_array($s, $unique, true)) {
			$unique[] = $s;
		}
	}
	$ordered = [];
	foreach ($unique as $slug) {
		if (isset($created_pages[ $slug ])) {
			$ordered[] = $slug;
		}
	}
	foreach (array_keys($created_pages) as $slug) {
		if (! in_array($slug, $ordered, true)) {
			$ordered[] = $slug;
		}
	}
	return $ordered;
}

/**
 * SEO + hero copy for extended leadgen pages (used to build Page Builder blueprints).
 *
 * @return array<string, array{hero_headline: string, hero_subheadline: string, seo_title: string, seo_description: string}>
 */
function lf_leadgen_page_marketing_copy(string $business, string $city_line): array {
	$b = $business !== '' ? $business : get_bloginfo('name');
	$cl = $city_line;

	return [
		'financing' => [
			'hero_headline' => sprintf(__('Financing for your project%s', 'leadsforward-core'), $cl),
			'hero_subheadline' => __('Flexible options so you can move forward with confidence. Ask us what plans may be available for your scope.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Financing | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Learn about financing options for your home service project.', 'leadsforward-core'),
		],
		'faq' => [
			'hero_headline' => __('Frequently asked questions', 'leadsforward-core'),
			'hero_subheadline' => __('Quick answers about scheduling, pricing, and what to expect.', 'leadsforward-core'),
			'seo_title' => sprintf(__('FAQ | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Answers to common questions about our services and process.', 'leadsforward-core'),
		],
		'our-work' => [
			'hero_headline' => __('Our work', 'leadsforward-core'),
			'hero_subheadline' => __('Recent projects and results from local homeowners.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Our Work | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Browse project photos and see the quality we deliver.', 'leadsforward-core'),
		],
		'foundation-warning-signs' => [
			'hero_headline' => __('Signs your foundation needs attention', 'leadsforward-core'),
			'hero_subheadline' => __('Cracks, sticking doors, and uneven floors can signal structural movement. Schedule an inspection.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Foundation Warning Signs | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Learn common signs of foundation issues and when to call a specialist.', 'leadsforward-core'),
		],
		'storm-damage-roofing' => [
			'hero_headline' => __('Storm damage & insurance claims', 'leadsforward-core'),
			'hero_subheadline' => __('Hail and wind can hide damage. We document the roof and help you understand next steps.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Storm Damage Roofing | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Storm damage roof inspections and repair. Fast response after severe weather.', 'leadsforward-core'),
		],
		'commercial-pressure-washing' => [
			'hero_headline' => __('Commercial pressure washing', 'leadsforward-core'),
			'hero_subheadline' => __('Keep storefronts, lots, and building exteriors clean and professional.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Commercial Pressure Washing | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Commercial exterior cleaning with flexible scheduling.', 'leadsforward-core'),
		],
		'emergency-tree-service' => [
			'hero_headline' => __('Emergency tree service', 'leadsforward-core'),
			'hero_subheadline' => __('Downed limbs or hazardous trees — fast response when safety is on the line.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Emergency Tree Service | %s', 'leadsforward-core'), $b),
			'seo_description' => __('24/7 emergency tree removal and storm cleanup when you need help fast.', 'leadsforward-core'),
		],
		'hvac-maintenance-plan' => [
			'hero_headline' => __('HVAC maintenance plans', 'leadsforward-core'),
			'hero_subheadline' => __('Tune-ups that reduce breakdowns, extend equipment life, and keep comfort steady.', 'leadsforward-core'),
			'seo_title' => sprintf(__('HVAC Maintenance Plans | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Protect your heating and cooling system with seasonal maintenance.', 'leadsforward-core'),
		],
		'energy-efficient-windows' => [
			'hero_headline' => __('Energy-efficient windows', 'leadsforward-core'),
			'hero_subheadline' => __('Reduce drafts and improve comfort with modern window replacements.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Energy-Efficient Windows | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Window replacement options for efficiency, noise reduction, and curb appeal.', 'leadsforward-core'),
		],
		'basement-waterproofing-guide' => [
			'hero_headline' => __('Basement & crawl space waterproofing', 'leadsforward-core'),
			'hero_subheadline' => __('Stop moisture intrusion with drainage, sealing, and sump solutions tailored to your home.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Basement Waterproofing | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Waterproofing and drainage solutions for basements and crawl spaces.', 'leadsforward-core'),
		],
		'driveway-sealcoating' => [
			'hero_headline' => __('Sealcoating & driveway maintenance', 'leadsforward-core'),
			'hero_subheadline' => __('Protect asphalt from sun and moisture and extend pavement life.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Sealcoating & Paving Maintenance | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Asphalt sealcoating and maintenance for driveways and parking areas.', 'leadsforward-core'),
		],
		'solar-battery-storage' => [
			'hero_headline' => __('Solar & battery storage', 'leadsforward-core'),
			'hero_subheadline' => __('Design, install, and monitor systems with backup options for peace of mind.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Solar & Battery Storage | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Solar panels and home battery options. Ask about incentives and payback.', 'leadsforward-core'),
		],
		'emergency-water-removal' => [
			'hero_headline' => __('Emergency water removal', 'leadsforward-core'),
			'hero_subheadline' => __('Fast extraction and drying to limit damage after leaks and floods.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Emergency Water Removal | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Emergency water damage response, drying, and restoration support.', 'leadsforward-core'),
		],
		'pet-stain-treatment' => [
			'hero_headline' => __('Pet stains & odor treatment', 'leadsforward-core'),
			'hero_subheadline' => __('Targeted cleaning for odors and spots so carpets feel fresh again.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Pet Stain Carpet Cleaning | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Pet stain and odor removal for carpets and upholstery.', 'leadsforward-core'),
		],
		'decorative-concrete-options' => [
			'hero_headline' => __('Decorative concrete options', 'leadsforward-core'),
			'hero_subheadline' => __('Stamped patterns, colors, and finishes for patios, walks, and pool decks.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Decorative & Stamped Concrete | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Explore stamped and decorative concrete styles for your outdoor spaces.', 'leadsforward-core'),
		],
		'pool-opening-closing' => [
			'hero_headline' => __('Pool opening & closing', 'leadsforward-core'),
			'hero_subheadline' => __('Seasonal service to protect equipment and keep water balanced.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Pool Opening & Closing | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Professional pool opening, closing, and seasonal maintenance.', 'leadsforward-core'),
		],
		'whole-home-remodeling' => [
			'hero_headline' => __('Whole-home remodeling', 'leadsforward-core'),
			'hero_subheadline' => __('Coordinated design and construction for multi-room and full-home updates.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Whole-Home Remodeling | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Whole-home remodeling with clear timelines and communication.', 'leadsforward-core'),
		],
		'seasonal-lawn-care' => [
			'hero_headline' => __('Seasonal lawn care', 'leadsforward-core'),
			'hero_subheadline' => __('Fertilization, aeration, and cleanup plans that keep turf healthy year-round.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Seasonal Lawn Care | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Seasonal lawn and landscape maintenance programs.', 'leadsforward-core'),
		],
		'kitchen-design-ideas' => [
			'hero_headline' => __('Kitchen design ideas', 'leadsforward-core'),
			'hero_subheadline' => __('Layout, cabinets, surfaces, and lighting options for your remodel.', 'leadsforward-core'),
			'seo_title' => sprintf(__('Kitchen Design Ideas | %s', 'leadsforward-core'), $b),
			'seo_description' => __('Kitchen remodeling inspiration and planning for your project.', 'leadsforward-core'),
		],
	];
}
