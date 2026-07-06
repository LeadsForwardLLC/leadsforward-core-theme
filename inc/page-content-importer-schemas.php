<?php
/**
 * Page content import — template registry (per page slug).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shared section heading aliases for paste docs.
 *
 * @return array<string, string>
 */
function lf_pci_common_section_aliases(): array {
	return [
		'hero' => 'hero',
		'hero section' => 'hero',
		'story' => 'content_image',
		'company intro' => 'content_image',
		'company story' => 'content_image',
		'content image' => 'content_image',
		'content_image' => 'content_image',
		'intro' => 'content_image',
		'overview' => 'content_image',
		'benefits' => 'benefits',
		'why choose us' => 'benefits',
		'team' => 'image_content',
		'our team' => 'image_content',
		'image content' => 'image_content',
		'image_content' => 'image_content',
		'process' => 'process',
		'our process' => 'process',
		'faq' => 'faq_accordion',
		'faqs' => 'faq_accordion',
		'faq accordion' => 'faq_accordion',
		'faq_accordion' => 'faq_accordion',
		'services here' => 'services_offered_here',
		'services_offered_here' => 'services_offered_here',
		'nearby areas' => 'nearby_areas',
		'nearby_areas' => 'nearby_areas',
		'related links' => 'related_links',
		'related_links' => 'related_links',
		'cta' => 'cta',
		'call to action' => 'cta',
		'seo' => 'seo',
		'meta' => 'seo',
		'trust bar' => 'trust_bar',
		'trust_bar' => 'trust_bar',
		'service details' => 'service_details',
		'service_details' => 'service_details',
		'service details 2' => 'service_details__2',
		'service details b' => 'service_details__2',
		'service_details__2' => 'service_details__2',
		'content' => 'content',
		'page content' => 'content',
		'legal' => 'content',
		'pricing' => 'pricing',
		'project gallery' => 'project_gallery',
		'project_gallery' => 'project_gallery',
		'mentor' => 'image_content_b',
		'image_content_b' => 'image_content_b',
		'services' => 'service_intro',
		'service intro' => 'service_intro',
		'service_intro' => 'service_intro',
		'map' => 'map_nap',
		'map nap' => 'map_nap',
		'reviews' => 'trust_reviews',
	];
}

/**
 * Build a page import schema.
 *
 * @param list<string> $order
 * @param array{
 *   locked?: list<string>,
 *   storage?: string,
 *   post_type?: string,
 *   downloadable?: bool,
 *   preserve_keys?: array<string, list<string>>,
 *   hero_variant?: string,
 *   process_group?: string,
 *   faq_context?: string,
 *   required?: list<string>,
 *   section_aliases?: array<string, string>
 * } $options
 * @return array<string, mixed>
 */
function lf_pci_build_schema(string $slug, string $label, array $order, array $options = []): array {
	$aliases = array_merge(lf_pci_common_section_aliases(), $options['section_aliases'] ?? []);
	$locked = array_values(array_unique(array_merge(
		function_exists('lf_pci_library_wired_section_types') ? lf_pci_library_wired_section_types() : ['process', 'faq_accordion'],
		$options['locked'] ?? []
	)));
	$importable = [];
	foreach ($order as $type) {
		if (!lf_pci_section_type_is_locked($type, $locked)) {
			$importable[] = $type;
		}
	}
	$importable[] = 'seo';

	return [
		'slug' => sanitize_title($slug),
		'label' => $label,
		'order' => $order,
		'locked' => $locked,
		'importable' => $importable,
		'storage' => $options['storage'] ?? 'page_builder',
		'post_type' => $options['post_type'] ?? 'page',
		'downloadable' => array_key_exists('downloadable', $options) ? (bool) $options['downloadable'] : true,
		'preserve_keys' => is_array($options['preserve_keys'] ?? null) ? $options['preserve_keys'] : [],
		'hero_variant' => $options['hero_variant'] ?? 'page',
		'process_group' => $options['process_group'] ?? (defined('LF_NICHE_ABOUT_PROCESS_GROUP') ? LF_NICHE_ABOUT_PROCESS_GROUP : 'about-company'),
		'faq_context' => $options['faq_context'] ?? (defined('LF_NICHE_ABOUT_FAQ_CONTEXT') ? LF_NICHE_ABOUT_FAQ_CONTEXT : 'about_company'),
		'required' => $options['required'] ?? ['hero', 'cta'],
		'section_aliases' => $aliases,
	];
}

/**
 * @param list<string> $locked
 */
function lf_pci_section_type_is_locked(string $section_key, array $locked): bool {
	if (in_array($section_key, $locked, true)) {
		return true;
	}
	$base = function_exists('lf_homepage_base_section_type')
		? lf_homepage_base_section_type($section_key)
		: $section_key;
	return $base !== $section_key && in_array($base, $locked, true);
}

/**
 * @return array<string, mixed>|null
 */
function lf_pci_schema_locked_types(array $schema): array {
	return is_array($schema['locked'] ?? null) ? $schema['locked'] : [];
}

function lf_pci_section_is_locked(string $section_key, array $schema): bool {
	return lf_pci_section_type_is_locked($section_key, lf_pci_schema_locked_types($schema));
}

/**
 * Page Builder section keys for a context (service_details__2 for duplicates).
 *
 * @return list<string>
 */
function lf_pci_section_order_for_context(string $context): array {
	if (function_exists('lf_ai_pb_default_section_keys_for_context')) {
		$keys = lf_ai_pb_default_section_keys_for_context($context);
		if ($keys !== []) {
			return $keys;
		}
	}
	return function_exists('lf_sections_default_order')
		? lf_sections_default_order($context)
		: [];
}

/**
 * Registered import templates keyed by template slug (page slug or `service` for CPT).
 *
 * @return array<string, array<string, mixed>>
 */
function lf_pci_registry(): array {
	$about_order = function_exists('lf_fleet_page_section_order') ? lf_fleet_page_section_order('about-us') : ['hero', 'content_image', 'benefits', 'image_content', 'process', 'faq_accordion', 'cta'];
	$why_order = function_exists('lf_fleet_page_section_order') ? lf_fleet_page_section_order('why-choose-us') : ['hero', 'benefits', 'content_image', 'image_content', 'faq_accordion', 'cta'];
	$services_order = function_exists('lf_fleet_page_section_order') ? lf_fleet_page_section_order('services') : ['hero', 'service_intro', 'content_image', 'faq_accordion', 'cta'];
	$areas_order = function_exists('lf_fleet_page_section_order') ? lf_fleet_page_section_order('service-areas') : ['hero', 'service_areas', 'faq_accordion', 'cta'];
	$home_niche = (string) get_option(
		defined('LF_HOMEPAGE_NICHE_OPTION') ? LF_HOMEPAGE_NICHE_OPTION : 'lf_homepage_niche_slug',
		''
	);
	if ($home_niche === '') {
		$home_niche = 'foundation-repair';
	}
	$home_order = function_exists('lf_niche_homepage_blueprint_order')
		? lf_niche_homepage_blueprint_order($home_niche)
		: (function_exists('lf_sections_default_order')
			? lf_sections_default_order('homepage')
			: [
			'hero',
			'trust_bar',
			'service_intro',
			'benefits',
			'service_details',
			'service_details__2',
			'process',
			'faq_accordion',
			'trust_reviews',
			'map_nap',
			'cta',
		]);

	$schemas = [
		lf_pci_build_schema('home', __('Homepage', 'leadsforward-core'), $home_order, [
			'storage' => 'homepage',
			'hero_variant' => 'conversion',
			'locked' => [],
			'required' => ['hero', 'benefits', 'cta'],
			'preserve_keys' => [
				'service_intro' => [
					'service_intro_columns',
					'service_intro_max_items',
					'service_intro_show_images',
					'service_intro_service_ids',
				],
				'trust_reviews' => [
					'trust_layout',
					'trust_columns',
					'trust_max_items',
					'trust_slider_autoplay',
				],
			],
			'section_aliases' => [
				'services' => 'service_intro',
				'service intro' => 'service_intro',
				'service intro boxes' => 'service_intro',
				'service_intro' => 'service_intro',
				'problem' => 'service_details',
				'problem signs' => 'service_details',
				'pain points' => 'service_details',
				'mentor' => 'image_content_b',
				'authority' => 'image_content_b',
				'owner intro' => 'image_content_b',
				'projects' => 'project_gallery',
				'before after' => 'project_gallery',
				'project gallery' => 'project_gallery',
				'financing' => 'pricing',
				'cost reassurance' => 'pricing',
				'reviews' => 'trust_reviews',
				'trust reviews' => 'trust_reviews',
				'customer reviews' => 'trust_reviews',
				'map' => 'map_nap',
				'map nap' => 'map_nap',
				'areas map' => 'map_nap',
				'service areas map' => 'map_nap',
			],
		]),
		lf_pci_build_schema('about-us', __('About Us', 'leadsforward-core'), $about_order, [
			'required' => ['hero', 'content_image', 'benefits', 'cta'],
		]),
		lf_pci_build_schema('why-choose-us', __('Why Choose Us', 'leadsforward-core'), $why_order, [
			'required' => ['hero', 'benefits', 'cta'],
		]),
		lf_pci_build_schema('services', __('Services Overview', 'leadsforward-core'), $services_order, [
			'locked' => ['service_intro'],
			'required' => ['hero', 'cta'],
			'section_aliases' => [
				'services grid' => 'service_intro',
				'service cards' => 'service_intro',
			],
		]),
		lf_pci_build_schema('service-areas', __('Service Areas Overview', 'leadsforward-core'), $areas_order, [
			'locked' => ['service_areas'],
			'required' => ['hero', 'cta'],
			'section_aliases' => [
				'service areas' => 'service_areas',
				'areas map' => 'service_areas',
				'map' => 'service_areas',
			],
		]),
		lf_pci_build_schema('reviews', __('Reviews', 'leadsforward-core'), function_exists('lf_fleet_page_section_order') ? lf_fleet_page_section_order('reviews') : ['hero', 'trust_reviews', 'faq_accordion', 'cta'], [
			'locked' => ['trust_reviews'],
			'required' => ['hero', 'cta'],
		]),
		lf_pci_build_schema('faq', __('FAQ', 'leadsforward-core'), ['hero', 'faq_accordion', 'cta'], [
			'required' => ['hero', 'cta'],
			'faq_hub' => true,
			'faq_context' => defined('LF_NICHE_FAQ_PAGE_CONTEXT') ? LF_NICHE_FAQ_PAGE_CONTEXT : 'faq_page',
		]),
		lf_pci_build_schema('contact', __('Contact', 'leadsforward-core'), function_exists('lf_fleet_page_section_order') ? lf_fleet_page_section_order('contact') : ['hero', 'map_nap', 'faq_accordion', 'cta'], [
			'locked' => ['map_nap'],
			'required' => ['hero', 'cta'],
		]),
		lf_pci_build_schema('blog', __('Blog', 'leadsforward-core'), function_exists('lf_fleet_page_section_order') ? lf_fleet_page_section_order('blog') : ['hero', 'blog_posts', 'faq_accordion', 'cta'], [
			'locked' => ['blog_posts'],
			'required' => ['hero', 'cta'],
		]),
		lf_pci_build_schema('thank-you', __('Thank You', 'leadsforward-core'), function_exists('lf_fleet_page_section_order') ? lf_fleet_page_section_order('thank-you') : ['hero', 'content', 'faq_accordion'], [
			'required' => ['hero', 'content'],
		]),
		lf_pci_build_schema('service', __('Service Page', 'leadsforward-core'), lf_pci_section_order_for_context('service'), [
			'post_type' => 'lf_service',
			'required' => ['hero', 'service_details', 'cta'],
		]),
		lf_pci_build_schema('service-area', __('Service Area Page', 'leadsforward-core'), lf_pci_section_order_for_context('service_area'), [
			'post_type' => 'lf_service_area',
			'required' => ['hero', 'cta'],
		]),
	];

	$out = [];
	foreach ($schemas as $schema) {
		$out[(string) $schema['slug']] = $schema;
	}
	return $out;
}

/**
 * Writer-facing .docx templates (grouped for admin UI).
 *
 * @return array<string, list<array{key: string, label: string, group: string}>>
 */
function lf_pci_writer_template_groups(): array {
	$groups = [
		'site_pages' => [
			'label' => __('Site pages', 'leadsforward-core'),
			'keys' => ['home', 'about-us', 'why-choose-us', 'services', 'service-areas', 'reviews', 'blog', 'faq', 'contact', 'thank-you'],
		],
		'service_posts' => [
			'label' => __('Service posts', 'leadsforward-core'),
			'keys' => ['service'],
		],
		'service_area_posts' => [
			'label' => __('Service area posts', 'leadsforward-core'),
			'keys' => ['service-area'],
		],
	];
	$registry = lf_pci_registry();
	$out = [];
	foreach ($groups as $group_id => $group) {
		$items = [];
		foreach ($group['keys'] as $key) {
			$schema = $registry[$key] ?? null;
			if (!is_array($schema) || empty($schema['downloadable'])) {
				continue;
			}
			$items[] = [
				'key' => $key,
				'label' => (string) ($schema['label'] ?? $key),
				'group' => $group_id,
			];
		}
		if ($items !== []) {
			$out[$group_id] = [
				'label' => (string) $group['label'],
				'items' => $items,
			];
		}
	}
	return $out;
}

/**
 * @return array<string, string>
 */
function lf_pci_registry_slug_aliases(): array {
	return [
		'about' => 'about-us',
		'aboutus' => 'about-us',
		'homepage' => 'home',
		'front-page' => 'home',
		'service_areas' => 'service-areas',
		'thankyou' => 'thank-you',
		'lf_service' => 'service',
		'lf_service_area' => 'service-area',
		'service_area' => 'service-area',
	];
}
