<?php
/**
 * Niche definition registry. Centralized per-niche config for setup and structure.
 * Add new niches here without rewriting core setup logic.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * All required page slugs the setup flow creates. Same for all niches; titles may vary.
 */
function lf_wizard_required_page_slugs(): array {
	return [
		'home',
		'about-us',
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
 * Default page title key per slug. Niche can override via required_pages.
 */
function lf_wizard_default_page_titles(): array {
	return [
		'home'              => __('Home', 'leadsforward-core'),
		'about-us'          => __('About Us', 'leadsforward-core'),
		'our-services'      => __('Services', 'leadsforward-core'),
		'service-areas' => __('Service Areas', 'leadsforward-core'),
		'reviews'           => __('Reviews', 'leadsforward-core'),
		'blog'              => __('Blog', 'leadsforward-core'),
		'sitemap'           => __('Sitemap', 'leadsforward-core'),
		'contact'           => __('Contact', 'leadsforward-core'),
		'privacy-policy'    => __('Privacy Policy', 'leadsforward-core'),
		'terms-of-service'  => __('Terms of Service', 'leadsforward-core'),
		'thank-you'         => __('Thank You', 'leadsforward-core'),
	];
}

/**
 * Default section_enabled for niches. All sections on unless niche overrides.
 *
 * @return array<string, bool>
 */
function lf_niche_default_section_enabled(): array {
	$order = function_exists('lf_homepage_controller_order') ? lf_homepage_controller_order() : ['hero', 'trust_reviews', 'service_grid', 'service_areas', 'faq_accordion', 'cta', 'map_nap'];
	$out = [];
	foreach ($order as $type) {
		$out[$type] = true;
	}
	if (isset($out['project_gallery'])) {
		$out['project_gallery'] = false;
	}
	return $out;
}

/**
 * Niche layout profiles. Controls which sections default per context.
 */
function lf_niche_layout_profiles(): array {
	$homepage_base = function_exists('lf_sections_default_order') ? lf_sections_default_order('homepage') : ['hero', 'trust_reviews', 'service_grid', 'service_areas', 'faq_accordion', 'cta', 'map_nap'];
	return [
		'core' => [
			'homepage' => $homepage_base,
			'page' => ['hero', 'content', 'cta'],
			'service' => function_exists('lf_sections_default_order') ? lf_sections_default_order('service') : ['hero', 'trust_bar', 'benefits', 'content_image_a', 'image_content_b', 'service_details', 'process', 'faq_accordion', 'related_links', 'cta'],
			'service_area' => function_exists('lf_sections_default_order') ? lf_sections_default_order('service_area') : ['hero', 'trust_bar', 'benefits', 'content_image_a', 'image_content_b', 'services_offered_here', 'faq_accordion', 'nearby_areas', 'cta'],
			'post' => function_exists('lf_sections_default_order') ? lf_sections_default_order('post') : ['hero', 'content', 'related_links', 'cta'],
			'section_enabled' => [],
		],
		'project-heavy' => [
			'homepage' => $homepage_base,
			'page' => ['hero', 'content', 'project_gallery', 'cta'],
			'service' => function_exists('lf_sections_default_order') ? lf_sections_default_order('service') : ['hero', 'trust_bar', 'benefits', 'content_image_a', 'image_content_b', 'service_details', 'process', 'faq_accordion', 'related_links', 'cta'],
			'service_area' => function_exists('lf_sections_default_order') ? lf_sections_default_order('service_area') : ['hero', 'trust_bar', 'benefits', 'content_image_a', 'image_content_b', 'services_offered_here', 'faq_accordion', 'nearby_areas', 'cta'],
			'post' => function_exists('lf_sections_default_order') ? lf_sections_default_order('post') : ['hero', 'content', 'related_links', 'cta'],
			'section_enabled' => ['project_gallery' => true],
		],
	];
}

/**
 * Aliases for legacy or Airtable niche slugs.
 *
 * @return array<string, string>
 */
function lf_niche_slug_aliases(): array {
	return [
		'power-washing' => 'pressure-washing',
	];
}

/**
 * Build a standard niche entry with layout profile defaults.
 *
 * @param array<string> $services
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function lf_niche_build_entry(
	string $name,
	string $slug,
	array $services = [],
	string $layout_profile = 'core',
	string $variation_profile = 'c',
	array $overrides = []
): array {
	$profiles = lf_niche_layout_profiles();
	$profile = $profiles[$layout_profile] ?? $profiles['core'];
	$default_sections = lf_niche_default_section_enabled();
	$entry = [
		'name' => $name,
		'slug' => $slug,
		'services' => $services,
		'required_pages' => [],
		'homepage_section_order' => $profile['homepage'] ?? lf_wizard_default_section_order(),
		'section_enabled' => array_merge($default_sections, $profile['section_enabled'] ?? []),
		'variation_profile' => $variation_profile,
		'cta_primary_default' => __('Get a Free Estimate', 'leadsforward-core'),
		'cta_secondary_default' => __('Call Now', 'leadsforward-core'),
		'hero_headline_default' => sprintf(__('%s in [Your City]', 'leadsforward-core'), $name),
		'hero_subheadline_default' => sprintf(__('Trusted local %s services with clear timelines and quality work.', 'leadsforward-core'), $name),
		'schema_review_enabled' => true,
		'layout_profile' => $layout_profile,
		'page_section_order' => $profile['page'] ?? [],
		'service_section_order' => $profile['service'] ?? [],
		'service_area_section_order' => $profile['service_area'] ?? [],
		'post_section_order' => $profile['post'] ?? [],
	];
	return array_merge($entry, $overrides);
}

/**
 * Niche registry. Each niche: name, slug, services, section_enabled (optional), hero copy defaults, CTA, schema.
 */
function lf_get_niche_registry(): array {
	$project_profile = 'project-heavy';
	return [
		'roofing' => lf_niche_build_entry(
			__('Roofing', 'leadsforward-core'),
			'roofing',
			[
				__('Roof Repair', 'leadsforward-core'),
				__('Roof Replacement', 'leadsforward-core'),
				__('Storm Damage', 'leadsforward-core'),
				__('Emergency Roofing', 'leadsforward-core'),
				__('Commercial Roofing', 'leadsforward-core'),
			],
			'core',
			'c',
			[
				'cta_primary_default' => __('Get a Free Roof Inspection', 'leadsforward-core'),
				'cta_secondary_default' => __('Call for Emergency Service', 'leadsforward-core'),
				'hero_headline_default' => __('Quality Roofing in [Your City]', 'leadsforward-core'),
				'hero_subheadline_default' => __('Trusted local roofing repair and replacement.', 'leadsforward-core'),
			]
		),
		'plumbing' => lf_niche_build_entry(
			__('Plumbing', 'leadsforward-core'),
			'plumbing',
			[
				__('Drain Cleaning', 'leadsforward-core'),
				__('Water Heater Repair', 'leadsforward-core'),
				__('Leak Detection', 'leadsforward-core'),
				__('Emergency Plumbing', 'leadsforward-core'),
			],
			'core',
			'c',
			[
				'cta_primary_default' => __('Schedule a Plumber', 'leadsforward-core'),
				'cta_secondary_default' => __('24/7 Emergency Service', 'leadsforward-core'),
				'hero_headline_default' => __('Reliable Plumbing in [Your City]', 'leadsforward-core'),
				'hero_subheadline_default' => __('Local plumbers for repairs and emergencies.', 'leadsforward-core'),
			]
		),
		'hvac' => lf_niche_build_entry(
			__('HVAC', 'leadsforward-core'),
			'hvac',
			[
				__('AC Repair', 'leadsforward-core'),
				__('AC Installation', 'leadsforward-core'),
				__('Heating Repair', 'leadsforward-core'),
				__('Maintenance Plans', 'leadsforward-core'),
			],
			'core',
			'd',
			[
				'cta_primary_default' => __('Request HVAC Service', 'leadsforward-core'),
				'cta_secondary_default' => __('Get a Free Estimate', 'leadsforward-core'),
				'hero_headline_default' => __('HVAC Repair & Installation in [Your City]', 'leadsforward-core'),
				'hero_subheadline_default' => __('Heating, cooling, and maintenance.', 'leadsforward-core'),
			]
		),
		'landscaping' => lf_niche_build_entry(
			__('Landscaping', 'leadsforward-core'),
			'landscaping',
			[
				__('Lawn Care', 'leadsforward-core'),
				__('Landscape Design', 'leadsforward-core'),
				__('Hardscaping', 'leadsforward-core'),
				__('Seasonal Cleanup', 'leadsforward-core'),
			],
			$project_profile,
			'a',
			[
				'cta_primary_default' => __('Get a Free Quote', 'leadsforward-core'),
				'cta_secondary_default' => __('Contact Us', 'leadsforward-core'),
				'hero_headline_default' => __('Landscaping in [Your City]', 'leadsforward-core'),
				'hero_subheadline_default' => __('Lawn care and landscape design.', 'leadsforward-core'),
			]
		),
		'air-duct-cleaning' => lf_niche_build_entry(
			__('Air Duct Cleaning', 'leadsforward-core'),
			'air-duct-cleaning',
			[
				__('Air Duct Cleaning', 'leadsforward-core'),
				__('Dryer Vent Cleaning', 'leadsforward-core'),
				__('HVAC Sanitizing', 'leadsforward-core'),
				__('Air Quality Testing', 'leadsforward-core'),
			]
		),
		'basement-remodeling' => lf_niche_build_entry(
			__('Basement Remodeling', 'leadsforward-core'),
			'basement-remodeling',
			[
				__('Basement Finishing', 'leadsforward-core'),
				__('Basement Waterproofing', 'leadsforward-core'),
				__('Basement Framing', 'leadsforward-core'),
				__('Basement Flooring', 'leadsforward-core'),
			],
			$project_profile
		),
		'bathroom-remodeling' => lf_niche_build_entry(
			__('Bathroom Remodeling', 'leadsforward-core'),
			'bathroom-remodeling',
			[
				__('Full Bathroom Remodel', 'leadsforward-core'),
				__('Shower & Tub Installation', 'leadsforward-core'),
				__('Vanity Replacement', 'leadsforward-core'),
				__('Tile & Flooring', 'leadsforward-core'),
			],
			$project_profile
		),
		'boat-detailing' => lf_niche_build_entry(
			__('Boat Detailing', 'leadsforward-core'),
			'boat-detailing',
			[
				__('Exterior Wash & Wax', 'leadsforward-core'),
				__('Interior Detailing', 'leadsforward-core'),
				__('Oxidation Removal', 'leadsforward-core'),
				__('Ceramic Coating', 'leadsforward-core'),
			]
		),
		'carpet-cleaning' => lf_niche_build_entry(
			__('Carpet Cleaning', 'leadsforward-core'),
			'carpet-cleaning',
			[
				__('Residential Carpet Cleaning', 'leadsforward-core'),
				__('Commercial Carpet Cleaning', 'leadsforward-core'),
				__('Stain Removal', 'leadsforward-core'),
				__('Pet Odor Treatment', 'leadsforward-core'),
			]
		),
		'concrete-cutting' => lf_niche_build_entry(
			__('Concrete Cutting', 'leadsforward-core'),
			'concrete-cutting',
			[
				__('Slab Cutting', 'leadsforward-core'),
				__('Core Drilling', 'leadsforward-core'),
				__('Wall Sawing', 'leadsforward-core'),
				__('Trench Cutting', 'leadsforward-core'),
			]
		),
		'deck-building' => lf_niche_build_entry(
			__('Deck Building', 'leadsforward-core'),
			'deck-building',
			[
				__('Custom Deck Builds', 'leadsforward-core'),
				__('Deck Repair', 'leadsforward-core'),
				__('Deck Staining', 'leadsforward-core'),
				__('Railings & Stairs', 'leadsforward-core'),
			],
			$project_profile
		),
		'dumpster-rental' => lf_niche_build_entry(
			__('Dumpster Rental', 'leadsforward-core'),
			'dumpster-rental',
			[
				__('Roll-Off Dumpster Rental', 'leadsforward-core'),
				__('Construction Cleanup', 'leadsforward-core'),
				__('Residential Cleanouts', 'leadsforward-core'),
				__('Same-Day Delivery', 'leadsforward-core'),
			]
		),
		'electrical' => lf_niche_build_entry(
			__('Electrical', 'leadsforward-core'),
			'electrical',
			[
				__('Panel Upgrades', 'leadsforward-core'),
				__('Outlet & Switch Repair', 'leadsforward-core'),
				__('Lighting Installation', 'leadsforward-core'),
				__('Wiring & Rewiring', 'leadsforward-core'),
			]
		),
		'excavation' => lf_niche_build_entry(
			__('Excavation', 'leadsforward-core'),
			'excavation',
			[
				__('Site Preparation', 'leadsforward-core'),
				__('Grading & Leveling', 'leadsforward-core'),
				__('Trenching', 'leadsforward-core'),
				__('Foundation Excavation', 'leadsforward-core'),
			]
		),
		'fencing' => lf_niche_build_entry(
			__('Fencing', 'leadsforward-core'),
			'fencing',
			[
				__('Fence Installation', 'leadsforward-core'),
				__('Fence Repair', 'leadsforward-core'),
				__('Wood Fencing', 'leadsforward-core'),
				__('Vinyl/Metal Fencing', 'leadsforward-core'),
			],
			$project_profile
		),
		'flooring' => lf_niche_build_entry(
			__('Flooring', 'leadsforward-core'),
			'flooring',
			[
				__('Hardwood Flooring', 'leadsforward-core'),
				__('Laminate Flooring', 'leadsforward-core'),
				__('Tile Installation', 'leadsforward-core'),
				__('Floor Repairs', 'leadsforward-core'),
			],
			$project_profile
		),
		'foundation-repair' => lf_niche_build_entry(
			__('Foundation Repair', 'leadsforward-core'),
			'foundation-repair',
			[
				__('Crack Repair', 'leadsforward-core'),
				__('Piering & Underpinning', 'leadsforward-core'),
				__('Foundation Waterproofing', 'leadsforward-core'),
				__('Structural Stabilization', 'leadsforward-core'),
			]
		),
		'gutter-services' => lf_niche_build_entry(
			__('Gutter Services', 'leadsforward-core'),
			'gutter-services',
			[
				__('Gutter Cleaning', 'leadsforward-core'),
				__('Gutter Installation', 'leadsforward-core'),
				__('Gutter Guards', 'leadsforward-core'),
				__('Downspout Repair', 'leadsforward-core'),
			]
		),
		'home-builder' => lf_niche_build_entry(
			__('Home Builder', 'leadsforward-core'),
			'home-builder',
			[
				__('Custom Home Builds', 'leadsforward-core'),
				__('Home Additions', 'leadsforward-core'),
				__('Design Consultation', 'leadsforward-core'),
				__('Project Management', 'leadsforward-core'),
			],
			$project_profile,
			'b',
			[
				'cta_primary_default' => __('Schedule a Builder Consult', 'leadsforward-core'),
				'cta_secondary_default' => __('Request a Project Review', 'leadsforward-core'),
			]
		),
		'interior-design' => lf_niche_build_entry(
			__('Interior Design', 'leadsforward-core'),
			'interior-design',
			[
				__('Design Consultation', 'leadsforward-core'),
				__('Space Planning', 'leadsforward-core'),
				__('Material Selection', 'leadsforward-core'),
				__('Full-Service Design', 'leadsforward-core'),
			],
			$project_profile
		),
		'junk-removal' => lf_niche_build_entry(
			__('Junk Removal', 'leadsforward-core'),
			'junk-removal',
			[
				__('Residential Junk Removal', 'leadsforward-core'),
				__('Commercial Cleanouts', 'leadsforward-core'),
				__('Appliance Removal', 'leadsforward-core'),
				__('Yard Waste Removal', 'leadsforward-core'),
			]
		),
		'kitchen-remodeling' => lf_niche_build_entry(
			__('Kitchen Remodeling', 'leadsforward-core'),
			'kitchen-remodeling',
			[
				__('Kitchen Remodel', 'leadsforward-core'),
				__('Cabinet Installation', 'leadsforward-core'),
				__('Countertops', 'leadsforward-core'),
				__('Kitchen Lighting', 'leadsforward-core'),
			],
			$project_profile
		),
		'lanais-patios' => lf_niche_build_entry(
			__('Lanais & Patios', 'leadsforward-core'),
			'lanais-patios',
			[
				__('Patio Design', 'leadsforward-core'),
				__('Lanai Enclosures', 'leadsforward-core'),
				__('Paver Installation', 'leadsforward-core'),
				__('Outdoor Living Spaces', 'leadsforward-core'),
			],
			$project_profile
		),
		'masonry' => lf_niche_build_entry(
			__('Masonry', 'leadsforward-core'),
			'masonry',
			[
				__('Brick Repair', 'leadsforward-core'),
				__('Stone Installation', 'leadsforward-core'),
				__('Chimney Repair', 'leadsforward-core'),
				__('Retaining Walls', 'leadsforward-core'),
			],
			$project_profile
		),
		'pest-control' => lf_niche_build_entry(
			__('Pest Control', 'leadsforward-core'),
			'pest-control',
			[
				__('General Pest Control', 'leadsforward-core'),
				__('Termite Treatment', 'leadsforward-core'),
				__('Rodent Control', 'leadsforward-core'),
				__('Seasonal Prevention', 'leadsforward-core'),
			]
		),
		'painting' => lf_niche_build_entry(
			__('Painting', 'leadsforward-core'),
			'painting',
			[
				__('Interior Painting', 'leadsforward-core'),
				__('Exterior Painting', 'leadsforward-core'),
				__('Cabinet Painting', 'leadsforward-core'),
				__('Drywall Repair', 'leadsforward-core'),
			],
			$project_profile
		),
		'paving' => lf_niche_build_entry(
			__('Paving', 'leadsforward-core'),
			'paving',
			[
				__('Asphalt Paving', 'leadsforward-core'),
				__('Driveway Repair', 'leadsforward-core'),
				__('Sealcoating', 'leadsforward-core'),
				__('Parking Lot Paving', 'leadsforward-core'),
			],
			$project_profile
		),
		'plaster-restoration' => lf_niche_build_entry(
			__('Plaster Restoration', 'leadsforward-core'),
			'plaster-restoration',
			[
				__('Plaster Repair', 'leadsforward-core'),
				__('Crack Repair', 'leadsforward-core'),
				__('Texture Matching', 'leadsforward-core'),
				__('Historic Restoration', 'leadsforward-core'),
			],
			$project_profile
		),
		'pool-building' => lf_niche_build_entry(
			__('Pool Building', 'leadsforward-core'),
			'pool-building',
			[
				__('Custom Pool Design', 'leadsforward-core'),
				__('Pool Construction', 'leadsforward-core'),
				__('Hardscape & Decking', 'leadsforward-core'),
				__('Automation & Lighting', 'leadsforward-core'),
			],
			$project_profile
		),
		'pool-resurfacing' => lf_niche_build_entry(
			__('Pool Resurfacing', 'leadsforward-core'),
			'pool-resurfacing',
			[
				__('Pool Resurfacing', 'leadsforward-core'),
				__('Tile Replacement', 'leadsforward-core'),
				__('Coping Repair', 'leadsforward-core'),
				__('Surface Prep', 'leadsforward-core'),
			],
			$project_profile
		),
		'pool-service' => lf_niche_build_entry(
			__('Pool Service', 'leadsforward-core'),
			'pool-service',
			[
				__('Weekly Pool Service', 'leadsforward-core'),
				__('Chemical Balancing', 'leadsforward-core'),
				__('Filter Cleaning', 'leadsforward-core'),
				__('Pool Equipment Repair', 'leadsforward-core'),
			]
		),
		'pressure-washing' => lf_niche_build_entry(
			__('Pressure Washing', 'leadsforward-core'),
			'pressure-washing',
			[
				__('House Washing', 'leadsforward-core'),
				__('Driveway Cleaning', 'leadsforward-core'),
				__('Roof Washing', 'leadsforward-core'),
				__('Deck & Patio Cleaning', 'leadsforward-core'),
			]
		),
		'remodeling' => lf_niche_build_entry(
			__('Remodeling', 'leadsforward-core'),
			'remodeling',
			[
				__('Whole-Home Remodel', 'leadsforward-core'),
				__('Room Additions', 'leadsforward-core'),
				__('Layout Changes', 'leadsforward-core'),
				__('Finish Upgrades', 'leadsforward-core'),
			],
			$project_profile
		),
		'rv-repair' => lf_niche_build_entry(
			__('RV Repair', 'leadsforward-core'),
			'rv-repair',
			[
				__('RV Diagnostics', 'leadsforward-core'),
				__('Electrical Repair', 'leadsforward-core'),
				__('Plumbing Repair', 'leadsforward-core'),
				__('Roof Sealing', 'leadsforward-core'),
			]
		),
		'shower-doors' => lf_niche_build_entry(
			__('Shower Doors', 'leadsforward-core'),
			'shower-doors',
			[
				__('Shower Door Installation', 'leadsforward-core'),
				__('Glass Replacement', 'leadsforward-core'),
				__('Custom Enclosures', 'leadsforward-core'),
				__('Hardware Repair', 'leadsforward-core'),
			],
			$project_profile
		),
		'siding' => lf_niche_build_entry(
			__('Siding', 'leadsforward-core'),
			'siding',
			[
				__('Siding Installation', 'leadsforward-core'),
				__('Siding Repair', 'leadsforward-core'),
				__('Soffit & Fascia', 'leadsforward-core'),
				__('Insulated Siding', 'leadsforward-core'),
			],
			$project_profile
		),
		'snow-removal' => lf_niche_build_entry(
			__('Snow Removal', 'leadsforward-core'),
			'snow-removal',
			[
				__('Residential Snow Removal', 'leadsforward-core'),
				__('Commercial Snow Removal', 'leadsforward-core'),
				__('De-Icing', 'leadsforward-core'),
				__('Seasonal Contracts', 'leadsforward-core'),
			]
		),
		'solar' => lf_niche_build_entry(
			__('Solar', 'leadsforward-core'),
			'solar',
			[
				__('Solar Panel Installation', 'leadsforward-core'),
				__('System Design', 'leadsforward-core'),
				__('Battery Storage', 'leadsforward-core'),
				__('Monitoring & Maintenance', 'leadsforward-core'),
			],
			$project_profile
		),
		'spray-foam-insulation' => lf_niche_build_entry(
			__('Spray Foam Insulation', 'leadsforward-core'),
			'spray-foam-insulation',
			[
				__('Open-Cell Insulation', 'leadsforward-core'),
				__('Closed-Cell Insulation', 'leadsforward-core'),
				__('Attic Insulation', 'leadsforward-core'),
				__('Crawl Space Insulation', 'leadsforward-core'),
			]
		),
		'stamped-concrete' => lf_niche_build_entry(
			__('Stamped Concrete', 'leadsforward-core'),
			'stamped-concrete',
			[
				__('Stamped Concrete Patios', 'leadsforward-core'),
				__('Stamped Driveways', 'leadsforward-core'),
				__('Decorative Walkways', 'leadsforward-core'),
				__('Sealing & Maintenance', 'leadsforward-core'),
			],
			$project_profile
		),
		'tree-service' => lf_niche_build_entry(
			__('Tree Service', 'leadsforward-core'),
			'tree-service',
			[
				__('Tree Removal', 'leadsforward-core'),
				__('Tree Trimming', 'leadsforward-core'),
				__('Stump Grinding', 'leadsforward-core'),
				__('Storm Cleanup', 'leadsforward-core'),
			]
		),
		'water-damage' => lf_niche_build_entry(
			__('Water Damage', 'leadsforward-core'),
			'water-damage',
			[
				__('Water Extraction', 'leadsforward-core'),
				__('Drying & Dehumidification', 'leadsforward-core'),
				__('Mold Remediation', 'leadsforward-core'),
				__('Emergency Response', 'leadsforward-core'),
			]
		),
		'waterproofing' => lf_niche_build_entry(
			__('Waterproofing', 'leadsforward-core'),
			'waterproofing',
			[
				__('Basement Waterproofing', 'leadsforward-core'),
				__('Crawl Space Sealing', 'leadsforward-core'),
				__('Sump Pump Installation', 'leadsforward-core'),
				__('Drainage Solutions', 'leadsforward-core'),
			]
		),
		'window-cleaning' => lf_niche_build_entry(
			__('Window Cleaning', 'leadsforward-core'),
			'window-cleaning',
			[
				__('Residential Window Cleaning', 'leadsforward-core'),
				__('Commercial Window Cleaning', 'leadsforward-core'),
				__('Screen Cleaning', 'leadsforward-core'),
				__('Hard Water Stain Removal', 'leadsforward-core'),
			]
		),
		'windows-doors' => lf_niche_build_entry(
			__('Windows & Doors', 'leadsforward-core'),
			'windows-doors',
			[
				__('Window Replacement', 'leadsforward-core'),
				__('Door Installation', 'leadsforward-core'),
				__('Energy-Efficient Upgrades', 'leadsforward-core'),
				__('Repairs & Maintenance', 'leadsforward-core'),
			],
			$project_profile
		),
		'general' => lf_niche_build_entry(
			__('General (Local Services)', 'leadsforward-core'),
			'general',
			[
				__('Main Service', 'leadsforward-core'),
				__('Additional Service', 'leadsforward-core'),
			],
			'core',
			'a',
			[
				'cta_primary_default' => __('Get a free estimate', 'leadsforward-core'),
				'cta_secondary_default' => __('Call now', 'leadsforward-core'),
				'hero_headline_default' => __('Welcome to [Your Business]', 'leadsforward-core'),
				'hero_subheadline_default' => __('Quality local service.', 'leadsforward-core'),
			]
		),
		'power-washing' => array_merge(
			lf_niche_build_entry(
				__('Pressure Washing', 'leadsforward-core'),
				'pressure-washing',
				[
					__('House Washing', 'leadsforward-core'),
					__('Driveway Cleaning', 'leadsforward-core'),
					__('Roof Washing', 'leadsforward-core'),
					__('Deck & Patio Cleaning', 'leadsforward-core'),
				]
			),
			[
				'slug' => 'power-washing',
				'hidden' => true,
			]
		),
	];
}

/**
 * Get one niche by slug. Returns null if not found.
 */
function lf_get_niche(string $slug): ?array {
	$reg = lf_get_niche_registry();
	return $reg[$slug] ?? null;
}

/** Default section type order when niche has none. Matches controller order. */
function lf_wizard_default_section_order(): array {
	return function_exists('lf_homepage_controller_order') ? lf_homepage_controller_order() : ['hero', 'trust_reviews', 'service_grid', 'service_areas', 'cta', 'faq_accordion', 'cta', 'map_nap'];
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

/**
 * Get the default section order for a given context + niche.
 */
function lf_niche_section_order(string $context, string $niche_slug = ''): array {
	if (!function_exists('lf_sections_default_order')) {
		return [];
	}
	$niche_slug = $niche_slug !== '' ? $niche_slug : (string) get_option('lf_homepage_niche_slug', 'general');
	$niche = lf_get_niche($niche_slug);
	$default = lf_sections_default_order($context);
	if (!$niche) {
		return $default;
	}
	$key = $context . '_section_order';
	if ($context === 'service') {
		$key = 'service_section_order';
	}
	if ($context === 'service_area') {
		$key = 'service_area_section_order';
	}
	if ($context === 'post') {
		$key = 'post_section_order';
	}
	if ($context === 'page') {
		$key = 'page_section_order';
	}
	$order = $niche[$key] ?? [];
	if (is_array($order) && !empty($order)) {
		return $order;
	}
	return $default;
}
