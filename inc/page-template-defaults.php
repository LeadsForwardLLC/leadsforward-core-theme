<?php
/**
 * Core page template defaults — section placeholders, FAQ hub wiring, library sync.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_PAGE_FAQ_HUB_SLUG = 'faq';
const LF_NICHE_FAQ_PAGE_CONTEXT = 'faq_page';

/**
 * Core site pages that receive standardized FAQ accordion placeholders.
 *
 * @return list<string>
 */
function lf_page_template_core_page_slugs(): array {
	return [
		'home',
		'about-us',
		'why-choose-us',
		'services',
		'our-services',
		'service-areas',
		'reviews',
		'faq',
		'contact',
	];
}

/**
 * Whether this page slug is the dedicated FAQ hub (shows entire published library).
 */
function lf_page_template_is_faq_hub(string $page_slug): bool {
	return sanitize_title($page_slug) === LF_PAGE_FAQ_HUB_SLUG;
}

/**
 * Generic FAQ section heading + intro per page purpose (overwritten on writer import).
 *
 * @return array{section_heading: string, section_intro: string}
 */
function lf_page_template_faq_section_defaults(string $page_slug): array {
	$page_slug = sanitize_title($page_slug);
	$map = [
		LF_PAGE_FAQ_HUB_SLUG => [
			'section_heading' => __('Our FAQs', 'leadsforward-core'),
			'section_intro' => __('Browse answers to common questions about our team, services, and scheduling.', 'leadsforward-core'),
		],
		'about-us' => [
			'section_heading' => __('Frequently Asked Questions', 'leadsforward-core'),
			'section_intro' => __('Quick answers about our company, process, and what to expect.', 'leadsforward-core'),
		],
		'why-choose-us' => [
			'section_heading' => __('Frequently Asked Questions', 'leadsforward-core'),
			'section_intro' => __('Quick answers about how we work and what sets us apart.', 'leadsforward-core'),
		],
		'services' => [
			'section_heading' => __('Service FAQs', 'leadsforward-core'),
			'section_intro' => __('Answers to common questions about our services, scopes, and scheduling.', 'leadsforward-core'),
		],
		'our-services' => [
			'section_heading' => __('Service FAQs', 'leadsforward-core'),
			'section_intro' => __('Answers to common questions about our services, scopes, and scheduling.', 'leadsforward-core'),
		],
		'service-areas' => [
			'section_heading' => __('Service Area FAQs', 'leadsforward-core'),
			'section_intro' => __('Quick answers about coverage, response times, and scheduling in your area.', 'leadsforward-core'),
		],
		'home' => [
			'section_heading' => __('Frequently Asked Questions', 'leadsforward-core'),
			'section_intro' => __('Straight answers before you schedule — inspections, timelines, and what to expect.', 'leadsforward-core'),
		],
		'service' => [
			'section_heading' => __('Service FAQs', 'leadsforward-core'),
			'section_intro' => __('Common questions about this service, timelines, and what is included.', 'leadsforward-core'),
		],
		'service_area' => [
			'section_heading' => __('Local FAQs', 'leadsforward-core'),
			'section_intro' => __('Answers about service in this area, scheduling, and what to expect on-site.', 'leadsforward-core'),
		],
	];
	if (isset($map[$page_slug])) {
		return $map[$page_slug];
	}
	return [
		'section_heading' => __('Frequently Asked Questions', 'leadsforward-core'),
		'section_intro' => __('Answers to common questions about scheduling and service.', 'leadsforward-core'),
	];
}

/**
 * FAQ hub accordion settings — list every published FAQ CPT entry.
 *
 * @return array{section_heading: string, section_intro: string, faq_max_items: int, faq_selected_ids: string, faq_columns: string}
 */
function lf_page_template_faq_hub_accordion_settings(): array {
	return array_merge(
		lf_page_template_faq_section_defaults(LF_PAGE_FAQ_HUB_SLUG),
		[
			'faq_max_items' => -1,
			'faq_selected_ids' => '',
			'faq_columns' => '1',
			'faq_schema_enabled' => '1',
		]
	);
}

/**
 * Default max FAQ items when wiring a curated subset (non-hub pages).
 */
function lf_page_template_faq_max_items_for_slug(string $page_slug): int {
	$page_slug = sanitize_title($page_slug);
	if ($page_slug === 'about-us') {
		return 8;
	}
	if ($page_slug === 'home') {
		return 6;
	}
	return 6;
}

/**
 * @return list<int>
 */
function lf_faq_all_published_ids(): array {
	$ids = get_posts([
		'post_type' => 'lf_faq',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	if (!is_array($ids)) {
		return [];
	}
	return array_values(array_filter(array_map('absint', $ids), static function (int $id): bool {
		return $id > 0;
	}));
}

/**
 * Normalize FAQ hub Page Builder config so the accordion lists the full library.
 */
function lf_page_template_apply_faq_hub_to_page(int $page_id): bool {
	if ($page_id <= 0 || !defined('LF_PB_META_KEY')) {
		return false;
	}
	$config = function_exists('lf_pb_get_post_config')
		? lf_pb_get_post_config($page_id, 'page')
		: get_post_meta($page_id, LF_PB_META_KEY, true);
	if (!is_array($config) || empty($config['sections'])) {
		return false;
	}
	$hub = lf_page_template_faq_hub_accordion_settings();
	$sections = is_array($config['sections']) ? $config['sections'] : [];
	$dirty = false;
	foreach ($sections as $instance_id => $row) {
		if (!is_array($row) || ($row['type'] ?? '') !== 'faq_accordion') {
			continue;
		}
		$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
		foreach ($hub as $key => $val) {
			$settings[$key] = $val;
		}
		$sections[$instance_id]['settings'] = $settings;
		$sections[$instance_id]['enabled'] = true;
		$dirty = true;
		break;
	}
	if (!$dirty) {
		return false;
	}
	$config['sections'] = $sections;
	update_post_meta($page_id, LF_PB_META_KEY, $config);
	return true;
}

/**
 * Apply generic FAQ placeholders to a page's accordion (non-hub curated pages).
 */
function lf_page_template_apply_faq_defaults_to_page(int $page_id, string $page_slug): bool {
	if ($page_id <= 0 || !defined('LF_PB_META_KEY') || lf_page_template_is_faq_hub($page_slug)) {
		return lf_page_template_apply_faq_hub_to_page($page_id);
	}
	$config = function_exists('lf_pb_get_post_config')
		? lf_pb_get_post_config($page_id, 'page')
		: get_post_meta($page_id, LF_PB_META_KEY, true);
	if (!is_array($config) || empty($config['sections'])) {
		return false;
	}
	$placeholders = lf_page_template_faq_section_defaults($page_slug);
	$sections = is_array($config['sections']) ? $config['sections'] : [];
	$dirty = false;
	foreach ($sections as $instance_id => $row) {
		if (!is_array($row) || ($row['type'] ?? '') !== 'faq_accordion') {
			continue;
		}
		$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
		if (trim((string) ($settings['section_heading'] ?? '')) === ''
			|| trim((string) ($settings['section_heading'] ?? '')) === __('Frequently Asked Questions', 'leadsforward-core')) {
			$settings['section_heading'] = $placeholders['section_heading'];
		}
		if (trim((string) ($settings['section_intro'] ?? '')) === '') {
			$settings['section_intro'] = $placeholders['section_intro'];
		}
		$max = (int) ($settings['faq_max_items'] ?? 0);
		if ($max === 0) {
			$settings['faq_max_items'] = lf_page_template_faq_max_items_for_slug($page_slug);
		}
		$sections[$instance_id]['settings'] = $settings;
		$dirty = true;
	}
	if (!$dirty) {
		return false;
	}
	$config['sections'] = $sections;
	update_post_meta($page_id, LF_PB_META_KEY, $config);
	return true;
}

/**
 * Resolve WordPress page ID by slug.
 */
function lf_page_template_get_page_id(string $slug): int {
	$page = get_page_by_path(sanitize_title($slug));
	return $page instanceof \WP_Post ? (int) $page->ID : 0;
}

/**
 * Push niche library process + FAQs to CPTs and wire About + FAQ hub pages.
 *
 * @return array{process_ids: list<int>, faq_ids: list<int>, wired_about: bool, wired_faq_hub: bool}
 */
function lf_niche_sync_site_content_library(string $niche_slug, array $vars = [], string $mode = 'fill_empty'): array {
	$result = [
		'process_ids' => [],
		'faq_ids' => [],
		'wired_about' => false,
		'wired_faq_hub' => false,
	];
	if (!function_exists('lf_niche_sync_about_library_to_site')) {
		return $result;
	}
	$synced = lf_niche_sync_about_library_to_site($niche_slug, $vars, $mode, true);
	$result['process_ids'] = is_array($synced['process_ids'] ?? null) ? $synced['process_ids'] : [];
	$result['faq_ids'] = is_array($synced['faq_ids'] ?? null) ? $synced['faq_ids'] : [];
	$result['wired_about'] = !empty($synced['wired']);

	$faq_page_id = lf_page_template_get_page_id(LF_PAGE_FAQ_HUB_SLUG);
	if ($faq_page_id > 0) {
		$result['wired_faq_hub'] = lf_page_template_apply_faq_hub_to_page($faq_page_id);
	}
	return $result;
}

/**
 * Niche-aware copy extras merged into wizard page blueprints.
 *
 * @param array<string, mixed> $vars
 * @param array<string, mixed> $niche
 * @return array<string, array{order?: list<string>, overrides?: array<string, array<string, mixed>>, seo?: array{title: string, description: string}}>
 */
function lf_page_template_enhanced_blueprints(array $vars, array $niche): array {
	$business = (string) ($vars['business'] ?? get_bloginfo('name'));
	$city = (string) ($vars['city'] ?? '');
	$city_line = (string) ($vars['city_line'] ?? ($city !== '' ? ' in ' . $city : ''));
	$niche_slug = function_exists('lf_niche_content_library_resolve_slug')
		? lf_niche_content_library_resolve_slug((string) ($niche['slug'] ?? $vars['niche'] ?? ''))
		: 'general';
	$is_foundation = $niche_slug === 'foundation-repair';
	$cta_headline = $business !== ''
		? ($is_foundation
			? sprintf(__('Schedule a foundation inspection with %s', 'leadsforward-core'), $business)
			: sprintf(__('Get a free estimate from %s', 'leadsforward-core'), $business))
		: __('Get a free estimate', 'leadsforward-core');

	$why_benefits = $is_foundation
		? __("Engineered repair plans || Every recommendation ties to measurements, soil conditions, and structural load — not sales pressure.\n")
			. __("Licensed, insured crews || Vetted installers with proper coverage and workmanship standards you can document.\n")
			. __("Responsive project leadership || A single point of contact from inspection through warranty paperwork.\n")
			. __("Protected job sites || Tarps, access paths, and daily cleanup treated with the same care we give our own homes.")
		: __("Upfront pricing before work starts || Documented scopes so you always know the next step.\n")
			. __("Licensed and insured professionals || Fully vetted crews with proper coverage and local reviews.\n")
			. __("Respectful, clean crews || Daily cleanup and clear communication throughout the project.");

	$services_story = $is_foundation
		? [
			'section_heading' => __('Complete foundation repair under one roof', 'leadsforward-core'),
			'section_intro' => __('Every service starts with inspection, documentation, and a plan you can review.', 'leadsforward-core'),
			'service_details_body' => sprintf(
				__('%1$s handles the full range of structural and waterproofing work%2$s. From piering and wall stabilization to crack repair and encapsulation, our crews follow engineered scopes — not improvised field fixes.', 'leadsforward-core'),
				$business,
				$city_line
			),
			'service_details_checklist' => __("Crack repair & structural stabilization\nPiering & underpinning\nBasement waterproofing\nCrawl space encapsulation"),
			'content_media_show_checklist' => '1',
		]
		: [
			'section_heading' => __('How we deliver great results', 'leadsforward-core'),
			'section_intro' => __('Clear communication and quality workmanship at every step.', 'leadsforward-core'),
			'service_details_body' => sprintf(
				__('%1$s offers dependable home services%2$s with documented scopes, respectful crews, and follow-through after the job is done.', 'leadsforward-core'),
				$business,
				$city_line
			),
			'content_media_show_checklist' => '0',
		];

	return [
		'why-choose-us' => [
			'overrides' => [
				'hero' => [
					'variant' => 'internal',
					'hero_headline' => $business !== ''
						? sprintf(__('Why choose %s', 'leadsforward-core'), $business)
						: __('Why choose us', 'leadsforward-core'),
					'hero_subheadline' => $is_foundation
						? sprintf(__('Engineered foundation repair%1$s with clear scopes, licensed crews, and accountability you can verify.', 'leadsforward-core'), $city_line)
						: sprintf(__('Clear communication, quality work, and a clean job site%1$s — from first visit to final walkthrough.', 'leadsforward-core'), $city_line),
					'hero_eyebrow_text' => $is_foundation
						? __('Licensed • Insured • Local Specialists', 'leadsforward-core')
						: __('Licensed • Insured • Local', 'leadsforward-core'),
				],
				'benefits' => [
					'section_heading' => $is_foundation ? __('What sets us apart', 'leadsforward-core') : __('What makes us the easy choice', 'leadsforward-core'),
					'section_intro' => $is_foundation
						? __('Homeowners choose us when they want structural work explained clearly — and executed to plan.', 'leadsforward-core')
						: __('A process built for consistency, transparency, and results.', 'leadsforward-core'),
					'benefits_items' => $why_benefits,
				],
				'content_image' => [
					'section_heading' => $is_foundation ? __('More than a quick patch', 'leadsforward-core') : __('A better experience from start to finish', 'leadsforward-core'),
					'section_intro' => $is_foundation
						? __('Foundation repair should reduce risk — not create new uncertainty.', 'leadsforward-core')
						: __('The details that reduce disruption and keep quality high.', 'leadsforward-core'),
					'service_details_body' => $is_foundation
						? sprintf(
							__('%1$s focuses on structural clarity%2$s: documented inspections, engineered scopes, and crews trained on piering, stabilization, and waterproofing systems.', 'leadsforward-core'),
							$business,
							$city_line
						)
						: __('We plan the scope up front, protect your property, and keep you informed throughout the job.', 'leadsforward-core'),
					'service_details_media_mode' => 'image',
					'content_media_show_checklist' => '0',
				],
				'image_content' => [
					'section_heading' => $is_foundation ? __('People behind the work', 'leadsforward-core') : __('Protection-first job sites', 'leadsforward-core'),
					'section_intro' => $is_foundation
						? __('Inspectors, project managers, and installers aligned on the same repair plan.', 'leadsforward-core')
						: __('Respectful crews, careful staging, and daily cleanup.', 'leadsforward-core'),
					'service_details_body' => $is_foundation
						? sprintf(
							__('Your project is coordinated by a manager who owns scheduling, permits, and daily site communication. Field crews follow written scopes — not improvised fixes — and leave your property clean at the end of each day.', 'leadsforward-core')
						)
						: __('From tarps and landscaping protection to debris control, we treat your property like our own.', 'leadsforward-core'),
					'service_details_media_mode' => 'image',
					'content_media_show_checklist' => '0',
				],
				'faq_accordion' => array_merge(
					lf_page_template_faq_section_defaults('why-choose-us'),
					['faq_max_items' => 6]
				),
				'cta' => [
					'cta_headline' => $is_foundation
						? sprintf(__('See why homeowners trust %s', 'leadsforward-core'), $business)
						: $cta_headline,
					'cta_subheadline' => $is_foundation
						? __('Schedule a foundation inspection and get a clear repair plan.', 'leadsforward-core')
						: __('Request a free estimate and get a clear plan.', 'leadsforward-core'),
				],
			],
			'seo' => [
				'title' => $business !== ''
					? sprintf(__('Why Choose Us | %s', 'leadsforward-core'), $business)
					: __('Why Choose Us', 'leadsforward-core'),
				'description' => $is_foundation
					? sprintf(__('See why homeowners choose %1$s for foundation repair%2$s. Engineered plans, licensed crews, and clear communication.', 'leadsforward-core'), $business, $city_line)
					: sprintf(__('See what makes our team the trusted local choice%1$s.', 'leadsforward-core'), $city_line),
			],
		],
		'services' => [
			'overrides' => [
				'hero' => [
					'variant' => 'internal',
					'hero_headline' => $is_foundation && $city !== ''
						? sprintf(__('Foundation Repair Services in %s', 'leadsforward-core'), $city)
						: ($business !== '' ? sprintf(__('Services by %s', 'leadsforward-core'), $business) : __('Our Services', 'leadsforward-core')),
					'hero_subheadline' => $is_foundation
						? __('Engineered repairs for settlement, cracks, bowing walls, and moisture — scoped clearly before work starts.', 'leadsforward-core')
						: sprintf(__('Explore our most requested services and schedule fast, reliable help%1$s.', 'leadsforward-core'), $city_line),
					'hero_eyebrow_text' => $is_foundation
						? __('Licensed • Insured • Local Crews', 'leadsforward-core')
						: __('Licensed • Insured • Local', 'leadsforward-core'),
				],
				'service_intro' => [
					'section_heading' => __('Service options', 'leadsforward-core'),
					'section_intro' => __('Explore our core services with clear scopes and upfront expectations.', 'leadsforward-core'),
				],
				'content_image' => array_merge(
					['service_details_media_mode' => 'image'],
					$services_story
				),
				'faq_accordion' => array_merge(
					lf_page_template_faq_section_defaults('services'),
					['faq_max_items' => 6]
				),
				'cta' => [
					'cta_headline' => $is_foundation
						? sprintf(__('Get a foundation repair estimate from %s', 'leadsforward-core'), $business)
						: $cta_headline,
					'cta_subheadline' => $is_foundation
						? __('Tell us what you are seeing — we will schedule an inspection and outline options.', 'leadsforward-core')
						: __('Fast response times and transparent pricing.', 'leadsforward-core'),
				],
			],
		],
		'our-services' => [
			'overrides' => [],
		],
		'service-areas' => [
			'overrides' => [
				'hero' => [
					'variant' => 'internal',
					'hero_headline' => $is_foundation
						? __('Foundation Repair Service Areas', 'leadsforward-core')
						: ($business !== '' ? sprintf(__('Service areas for %s', 'leadsforward-core'), $business) : __('Service Areas', 'leadsforward-core')),
					'hero_subheadline' => $is_foundation && $city !== ''
						? sprintf(__('Local crews serving %1$s and surrounding neighborhoods with fast inspections and engineered repairs.', 'leadsforward-core'), $city)
						: sprintf(__('See the neighborhoods and cities we serve%1$s.', 'leadsforward-core'), $city_line),
					'hero_eyebrow_text' => __('Local • Responsive • Licensed', 'leadsforward-core'),
				],
				'faq_accordion' => array_merge(
					lf_page_template_faq_section_defaults('service-areas'),
					['faq_max_items' => 6]
				),
				'cta' => [
					'cta_headline' => $is_foundation
						? __('Check if we serve your neighborhood', 'leadsforward-core')
						: $cta_headline,
					'cta_subheadline' => $is_foundation
						? __('Request a foundation inspection — we will confirm coverage and schedule quickly.', 'leadsforward-core')
						: __('Schedule service anywhere we operate.', 'leadsforward-core'),
				],
			],
		],
		'reviews' => [
			'overrides' => [
				'hero' => [
					'variant' => 'internal',
					'hero_headline' => $business !== ''
						? sprintf(__('%s Reviews', 'leadsforward-core'), $business)
						: __('Customer reviews', 'leadsforward-core'),
					'hero_subheadline' => $is_foundation
						? sprintf(__('See what local homeowners say about our inspections, crews, and finished foundation repairs%1$s.', 'leadsforward-core'), $city_line)
						: sprintf(__('Real feedback from local homeowners%1$s.', 'leadsforward-core'), $city_line),
					'hero_eyebrow_text' => __('Real Homeowners • Real Results', 'leadsforward-core'),
				],
				'trust_reviews' => [
					'trust_heading' => __('What customers are saying', 'leadsforward-core'),
					'trust_max_items' => 6,
				],
				'cta' => [
					'cta_headline' => $is_foundation
						? __('Ready to join our happy customers?', 'leadsforward-core')
						: $cta_headline,
					'cta_subheadline' => $is_foundation
						? __('Request a foundation inspection and experience the same clear communication our reviewers mention.', 'leadsforward-core')
						: __('Join our list of happy customers.', 'leadsforward-core'),
				],
			],
		],
		'contact' => [
			'overrides' => [
				'hero' => [
					'variant' => 'internal',
					'hero_headline' => $business !== ''
						? sprintf(__('Contact %s', 'leadsforward-core'), $business)
						: __('Contact us', 'leadsforward-core'),
					'hero_subheadline' => $is_foundation
						? sprintf(__('Request a foundation inspection or ask a question — we respond quickly%1$s.', 'leadsforward-core'), $city_line)
						: sprintf(__('Fast responses and clear next steps%1$s.', 'leadsforward-core'), $city_line),
					'hero_eyebrow_text' => __('Fast Response • Local Team', 'leadsforward-core'),
				],
				'map_nap' => [
					'section_intent' => 'contact',
					'section_heading' => __('Get in touch', 'leadsforward-core'),
					'section_intro' => __('Share a few details and we will reply with next steps.', 'leadsforward-core'),
				],
				'cta' => [
					'cta_headline' => $is_foundation
						? __('Get a free foundation inspection', 'leadsforward-core')
						: $cta_headline,
					'cta_subheadline' => $is_foundation
						? __('Tell us what you are seeing and we will schedule a visit with a clear next step.', 'leadsforward-core')
						: __('Prefer a quick estimate? Start here.', 'leadsforward-core'),
				],
			],
		],
		LF_PAGE_FAQ_HUB_SLUG => [
			'order' => ['hero', 'faq_accordion', 'cta'],
			'overrides' => [
				'hero' => [
					'variant' => 'internal',
					'hero_headline' => $is_foundation
						? __('Foundation Repair FAQ', 'leadsforward-core')
						: ($business !== '' ? sprintf(__('%s FAQ', 'leadsforward-core'), $business) : __('FAQ', 'leadsforward-core')),
					'hero_subheadline' => $is_foundation
						? sprintf(__('Straight answers about inspections, repair methods, timelines, and warranties%1$s.', 'leadsforward-core'), $city_line)
						: sprintf(__('Straight answers about scheduling, service, and what to expect%1$s.', 'leadsforward-core'), $city_line),
					'hero_eyebrow_text' => __('Clear Answers • No Jargon', 'leadsforward-core'),
				],
				'faq_accordion' => lf_page_template_faq_hub_accordion_settings(),
				'cta' => [
					'cta_headline' => __('Still have questions?', 'leadsforward-core'),
					'cta_subheadline' => $business !== ''
						? sprintf(__('Talk with %s — request an inspection and get answers tied to your property.', 'leadsforward-core'), $business)
						: __('Reach out and we will point you in the right direction.', 'leadsforward-core'),
				],
			],
			'seo' => [
				'title' => $is_foundation && $business !== ''
					? sprintf(__('Foundation Repair FAQ | %s', 'leadsforward-core'), $business)
					: ($business !== '' ? sprintf(__('FAQ | %s', 'leadsforward-core'), $business) : __('FAQ', 'leadsforward-core')),
				'description' => $is_foundation && $business !== ''
					? sprintf(__('Foundation repair FAQ from %1$s. Inspections, piering, waterproofing, warranties, and what to expect on your property.', 'leadsforward-core'), $business)
					: sprintf(__('Frequently asked questions about %1$s services%2$s.', 'leadsforward-core'), $business, $city_line),
			],
		],
	];
}

/**
 * Deep-merge enhanced blueprint fragments into wizard blueprints.
 *
 * @param array<string, array<string, mixed>> $blueprints
 * @param array<string, mixed> $vars
 * @param array<string, mixed> $niche
 */
function lf_page_template_merge_enhanced_blueprints(array &$blueprints, array $vars, array $niche): void {
	$enhanced = lf_page_template_enhanced_blueprints($vars, $niche);
	foreach ($enhanced as $slug => $fragment) {
		if (!is_array($fragment) || $fragment === []) {
			continue;
		}
		if (!isset($blueprints[$slug])) {
			$blueprints[$slug] = ['order' => [], 'overrides' => [], 'seo' => ['title' => '', 'description' => '']];
		}
		if (!empty($fragment['order']) && is_array($fragment['order'])) {
			$blueprints[$slug]['order'] = $fragment['order'];
		}
		if (!empty($fragment['overrides']) && is_array($fragment['overrides'])) {
			$existing = is_array($blueprints[$slug]['overrides'] ?? null) ? $blueprints[$slug]['overrides'] : [];
			foreach ($fragment['overrides'] as $section => $settings) {
				if (!is_array($settings)) {
					continue;
				}
				$existing[$section] = array_merge(is_array($existing[$section] ?? null) ? $existing[$section] : [], $settings);
			}
			$blueprints[$slug]['overrides'] = $existing;
		}
		if (!empty($fragment['seo']) && is_array($fragment['seo'])) {
			$blueprints[$slug]['seo'] = array_merge(
				is_array($blueprints[$slug]['seo'] ?? null) ? $blueprints[$slug]['seo'] : [],
				$fragment['seo']
			);
		}
	}
	if (isset($enhanced['services'], $enhanced['our-services']) && isset($blueprints['our-services'])) {
		$blueprints['our-services'] = $blueprints['services'];
	}
}

/**
 * Ensure standard fleet pages exist and are published (FAQ hub, About, etc.).
 *
 * @return array{created: list<string>, published: list<string>}
 */
function lf_core_pages_ensure_standard_pages(): array {
	$result = ['created' => [], 'published' => []];
	$publish_slugs = function_exists('lf_wizard_default_publish_page_slugs')
		? lf_wizard_default_publish_page_slugs()
		: ['home', 'about-us', 'why-choose-us', 'services', 'service-areas', 'faq', 'contact'];
	$titles = function_exists('lf_wizard_default_page_titles') ? lf_wizard_default_page_titles() : [];
	$extended = function_exists('lf_wizard_extended_page_titles') ? lf_wizard_extended_page_titles() : [];
	$titles = array_merge($titles, $extended);
	$data = function_exists('lf_wizard_data_from_entity') ? lf_wizard_data_from_entity() : [];

	foreach ($publish_slugs as $slug) {
		$slug = sanitize_title($slug);
		if ($slug === '') {
			continue;
		}
		$page = function_exists('lf_fleet_find_page_by_slug')
			? lf_fleet_find_page_by_slug($slug)
			: get_page_by_path($slug);
		if (!$page instanceof \WP_Post) {
			$title = (string) ($titles[$slug] ?? ucwords(str_replace('-', ' ', $slug)));
			$content = function_exists('lf_wizard_placeholder_content')
				? lf_wizard_placeholder_content($slug, $title, $data)
				: '';
			$new_id = wp_insert_post([
				'post_title' => $title,
				'post_name' => $slug,
				'post_content' => $content,
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_author' => get_current_user_id() > 0 ? get_current_user_id() : 1,
			], true);
			if (!is_wp_error($new_id) && (int) $new_id > 0) {
				$result['created'][] = $slug;
				if ($slug === 'faq' && function_exists('lf_wizard_seed_page_pb_config')) {
					$niche = function_exists('lf_get_niche')
						? lf_get_niche((string) get_option('lf_homepage_niche_slug', ''))
						: null;
					if (is_array($niche)) {
						lf_wizard_seed_page_pb_config((int) $new_id, 'faq', $data, $niche, []);
					}
				}
			}
			continue;
		}
		if ($page->post_status !== 'publish') {
			wp_update_post([
				'ID' => (int) $page->ID,
				'post_status' => 'publish',
			]);
			$result['published'][] = $slug;
		}
	}

	return $result;
}

/**
 * One-time repair: dedupe alias pages, publish FAQ/Why Choose Us/Contact, fix nav.
 */
function lf_page_template_repair_core_pages_once(): void {
	if (!is_admin() || !current_user_can('edit_theme_options')) {
		return;
	}
	if (get_option('lf_page_template_repair_v4', '0') === '1') {
		return;
	}
	if (function_exists('lf_fleet_dedupe_alias_pages')) {
		lf_fleet_dedupe_alias_pages();
	}
	if (function_exists('lf_fleet_publish_build_pages')) {
		lf_fleet_publish_build_pages();
	}
	if (function_exists('lf_fleet_sync_reviews_page_status')) {
		lf_fleet_sync_reviews_page_status();
	}
	lf_core_pages_ensure_standard_pages();
	$niche_slug = (string) get_option('lf_homepage_niche_slug', 'foundation-repair');
	$vars = function_exists('lf_wizard_data_from_entity') ? lf_wizard_data_from_entity() : [];
	if (function_exists('lf_wizard_template_vars')) {
		$vars = lf_wizard_template_vars($vars);
	}
	lf_niche_sync_site_content_library($niche_slug, $vars, 'fill_empty');

	foreach (lf_page_template_core_page_slugs() as $slug) {
		$page_id = lf_page_template_get_page_id($slug);
		if ($page_id <= 0) {
			continue;
		}
		if (lf_page_template_is_faq_hub($slug)) {
			lf_page_template_apply_faq_hub_to_page($page_id);
		} else {
			lf_page_template_apply_faq_defaults_to_page($page_id, $slug);
		}
	}
	update_option('lf_page_template_repair_v4', '1', true);

	if (function_exists('lf_header_menu_force_structure_repair')) {
		delete_option('lf_header_menu_structure_version');
		lf_header_menu_force_structure_repair();
	}
}

add_action('admin_init', 'lf_page_template_repair_core_pages_once', 25);

/**
 * One-time migration: collapse legacy hero variants (default/a/b/c) to conversion.
 */
function lf_hero_normalize_variants_once(): void {
	if (!is_admin() || !current_user_can('edit_theme_options')) {
		return;
	}
	if (get_option('lf_hero_normalize_variants_v1', '0') === '1') {
		return;
	}
	if (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
		$config = get_option(LF_HOMEPAGE_CONFIG_OPTION, []);
		if (is_array($config) && isset($config['hero']) && is_array($config['hero'])) {
			$config['hero']['variant'] = 'conversion';
			if (($config['hero']['hero_media'] ?? 'none') === 'none') {
				$config['hero']['hero_media'] = 'image';
			}
			update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
		}
	}
	if (defined('LF_PB_META_KEY')) {
		$post_types = ['page', 'lf_service', 'lf_service_area', 'post'];
		foreach ($post_types as $post_type) {
			$post_ids = get_posts([
				'post_type' => $post_type,
				'post_status' => 'any',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'no_found_rows' => true,
			]);
			if (!is_array($post_ids)) {
				continue;
			}
			foreach ($post_ids as $post_id) {
				$pb_config = get_post_meta((int) $post_id, LF_PB_META_KEY, true);
				if (!is_array($pb_config) || empty($pb_config['sections']) || !is_array($pb_config['sections'])) {
					continue;
				}
				$dirty = false;
				foreach ($pb_config['sections'] as $instance_id => $row) {
					if (!is_array($row) || ($row['type'] ?? '') !== 'hero') {
						continue;
					}
					$settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
					$old_variant = (string) ($settings['variant'] ?? 'default');
					$new_variant = function_exists('lf_sections_normalize_hero_variant')
						? lf_sections_normalize_hero_variant($old_variant, false)
						: (in_array($old_variant, ['page', 'internal'], true) ? 'page' : 'conversion');
					if ($new_variant !== $old_variant) {
						$settings['variant'] = $new_variant;
						$pb_config['sections'][$instance_id]['settings'] = $settings;
						$dirty = true;
					}
					if ($new_variant === 'conversion' && ($settings['hero_media'] ?? 'none') === 'none') {
						$settings['hero_media'] = 'image';
						$pb_config['sections'][$instance_id]['settings'] = $settings;
						$dirty = true;
					}
				}
				if ($dirty) {
					update_post_meta((int) $post_id, LF_PB_META_KEY, $pb_config);
				}
			}
		}
	}
	update_option('lf_hero_normalize_variants_v1', '1', true);
}

add_action('admin_init', 'lf_hero_normalize_variants_once', 26);
