<?php
/**
 * Niche homepage blueprints — section order, copy defaults, and form/header chrome.
 * Resolved from Airtable niche slug → lf_homepage_niche_slug → rendered homepage.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @return array<string, array<string, mixed>>
 */
function lf_niche_homepage_blueprint_registry(): array {
	$foundation_problem_checklist = implode(
		"\n",
		[
			__('Cracks in walls or brick', 'leadsforward-core'),
			__('Uneven or sloping floors', 'leadsforward-core'),
			__('Doors and windows sticking', 'leadsforward-core'),
			__('Gaps around trim or cabinets', 'leadsforward-core'),
			__('Water pooling near foundation', 'leadsforward-core'),
			__('Bowing or settling foundation', 'leadsforward-core'),
		]
	);

	$foundation_trust_stats = implode(
		"\n",
		[
			'15+ || ' . __('Years in Business', 'leadsforward-core') . ' || hammer',
			'2,500+ || ' . __('Foundations Repaired', 'leadsforward-core') . ' || home',
			'5.0 || ' . __('500+ Google Reviews', 'leadsforward-core') . ' || star || stars',
			'A+ || ' . __('BBB Rating', 'leadsforward-core') . ' || building-bank || bbb',
		]
	);

	$contractor_trust_stats = implode(
		"\n",
		[
			'10+ || ' . __('Years in Business', 'leadsforward-core') . ' || hammer',
			'1,000+ || ' . __('Projects Completed', 'leadsforward-core') . ' || home',
			'5.0 || ' . __('320+ Google Reviews', 'leadsforward-core') . ' || star || stars',
			'A+ || ' . __('BBB Rating', 'leadsforward-core') . ' || building-bank || bbb',
		]
	);

	return [
		'foundation-repair' => [
			'label' => __('Foundation repair (calm, reassuring)', 'leadsforward-core'),
			'order' => [
				'hero',
				'trust_bar',
				'service_details',
				'image_content_b',
				'service_intro',
				'project_gallery',
				'process',
				'trust_reviews',
				'pricing',
				'benefits',
				'map_nap',
				'faq_accordion',
				'cta',
			],
			'section_enabled' => [
				'image_content_b' => true,
				'project_gallery' => true,
				'pricing' => true,
				'service_details__2' => false,
				'content_image_a' => false,
				'content_image_c' => false,
			],
			'hero' => [
				'hero_background_mode' => 'image',
				'hero_eyebrow_text' => __('Quality Work. Honest Service. **Built to Last.**', 'leadsforward-core'),
				'hero_headline' => __('Expert Foundation Repair. **Built Around You.**', 'leadsforward-core'),
				'hero_subheadline' => __('We inspect, diagnose, and repair foundation issues with proven solutions, clear pricing, and workmanship you can trust.', 'leadsforward-core'),
				'hero_chip_bullets' => implode(
					"\n",
					[
						__('Licensed & Insured || shield-check', 'leadsforward-core'),
						__('5-Star Rated || star', 'leadsforward-core'),
						__('Workmanship Warranty || certificate', 'leadsforward-core'),
						__('Financing Available || calendar', 'leadsforward-core'),
					]
				),
				'hero_proof_title' => __('Why homeowners choose us', 'leadsforward-core'),
				'hero_proof_bullets' => implode(
					"\n",
					[
						__('Local foundation specialists', 'leadsforward-core'),
						__('Clear inspection process', 'leadsforward-core'),
						__('No high-pressure sales tactics', 'leadsforward-core'),
					]
				),
				'cta_primary_override' => __('Get a Free Inspection', 'leadsforward-core'),
				'cta_secondary_override' => __('See Our Work', 'leadsforward-core'),
				'cta_primary_action' => 'quote',
				'cta_secondary_action' => 'link',
			],
			'trust_bar' => [
				'trust_heading' => '',
				'trust_stats_items' => $foundation_trust_stats,
				'trust_bar_layout' => 'stats_grid',
				'section_background' => 'soft',
			],
			'service_details' => [
				'section_intent' => 'problem',
				'section_heading' => __('Foundation Problems Don’t Fix Themselves', 'leadsforward-core'),
				'section_intro' => __('Small warning signs can turn into expensive structural issues if they’re ignored.', 'leadsforward-core'),
				'service_details_checklist' => $foundation_problem_checklist,
				'service_details_layout' => 'content_media',
				'content_media_show_checklist' => '1',
			],
			'image_content_b' => [
				'enabled' => true,
				'section_intent' => 'authority',
				'section_heading' => __('We Help Homeowners Fix the Problem Without the Guesswork', 'leadsforward-core'),
				'section_intro' => __('Foundation issues are stressful because most homeowners don’t know how serious the damage is, what repairs should cost, or who to trust. Our inspection process gives you clear answers, honest recommendations, and a repair plan built around your home.', 'leadsforward-core'),
				'service_details_checklist' => implode(
					"\n",
					[
						__('Local foundation specialists', 'leadsforward-core'),
						__('Clear inspection process', 'leadsforward-core'),
						__('No high-pressure sales tactics', 'leadsforward-core'),
						__('Financing options available', 'leadsforward-core'),
						__('Workmanship warranty', 'leadsforward-core'),
					]
				),
				'content_media_show_checklist' => '1',
			],
			'service_intro' => [
				'section_heading' => __('Foundation Repair Services Built for Long-Term Stability', 'leadsforward-core'),
				'section_intro' => __('Engineered solutions for settlement, moisture, and structural movement — scoped clearly before work starts.', 'leadsforward-core'),
			],
			'project_gallery' => [
				'enabled' => true,
				'section_heading' => __('Real Repairs. Real Homes. Real Results.', 'leadsforward-core'),
				'section_intro' => __('See how we’ve helped homeowners protect their homes across the area.', 'leadsforward-core'),
			],
			'process' => [
				'section_heading' => __('A Simple Process From Inspection to Repair', 'leadsforward-core'),
				'section_intro' => __('Know what happens next — no surprises.', 'leadsforward-core'),
			],
			'trust_reviews' => [
				'section_heading' => __('Homeowners Trust Us When It Matters Most', 'leadsforward-core'),
			],
			'pricing' => [
				'enabled' => true,
				'section_heading' => __('Foundation Repair Is a Big Decision. We Make It Easier.', 'leadsforward-core'),
				'section_intro' => __('Every home is different, which is why we start with a clear inspection and repair plan. We’ll explain what needs attention now, what can be monitored, and what financing options may be available.', 'leadsforward-core'),
				'financing_enabled' => '1',
				'financing_text' => __('Financing options may be available for qualified homeowners.', 'leadsforward-core'),
				'pricing_cta_text' => __('Ask About Financing', 'leadsforward-core'),
			],
			'benefits' => [
				'section_heading' => __('Why Homeowners Choose Us', 'leadsforward-core'),
				'section_intro' => __('Calm, clear guidance from inspection through warranty — without pressure.', 'leadsforward-core'),
			],
			'map_nap' => [
				'section_heading' => __('Foundation Repair Across [Your City]', 'leadsforward-core'),
				'section_intro' => __('Local crews serving homeowners throughout the region with fast inspections and engineered repairs.', 'leadsforward-core'),
			],
			'faq_accordion' => [
				'section_heading' => __('Foundation Repair Questions, Answered', 'leadsforward-core'),
				'section_intro' => __('Straight answers about inspections, warranties, timelines, and what to expect on your property.', 'leadsforward-core'),
			],
			'cta' => [
				'cta_headline' => __('Worried About Your Foundation? Get Clear Answers Today.', 'leadsforward-core'),
				'cta_subheadline' => __('Schedule a free inspection and find out what’s really happening with your home.', 'leadsforward-core'),
				'cta_primary_override' => __('Get a Free Inspection', 'leadsforward-core'),
				'section_background' => 'dark',
			],
			'inline_form' => [
				'title' => __('Get Your Free Foundation Inspection', 'leadsforward-core'),
				'subtext' => __('Tell us what’s going on. We’ll follow up quickly.', 'leadsforward-core'),
				'button_label' => __('Schedule My Free Inspection', 'leadsforward-core'),
			],
			'topbar' => [
				'enabled' => true,
				'text' => __('Free Foundation Inspections Available · Proudly Serving [Your City]', 'leadsforward-core'),
			],
			'tone' => 'calm',
		],
		'core-contractor' => [
			'label' => __('Generic home-service contractor', 'leadsforward-core'),
			'order' => [
				'hero',
				'trust_bar',
				'service_intro',
				'benefits',
				'service_details',
				'process',
				'trust_reviews',
				'pricing',
				'map_nap',
				'faq_accordion',
				'cta',
			],
			'section_enabled' => [
				'project_gallery' => false,
				'content_image_a' => false,
				'pricing' => true,
			],
			'hero' => [
				'hero_background_mode' => 'image',
				'hero_eyebrow_text' => __('Quality Work. Honest Service. **Built to Last.**', 'leadsforward-core'),
				'hero_headline' => __('Expert Craftsmanship. **Built Around You.**', 'leadsforward-core'),
				'hero_subheadline' => __('From small repairs to full projects, our licensed team delivers high-quality work with clear communication from start to finish.', 'leadsforward-core'),
				'hero_chip_bullets' => implode(
					"\n",
					[
						__('Licensed & Insured || shield-check', 'leadsforward-core'),
						__('5-Star Rated || star', 'leadsforward-core'),
						__('On Time & On Budget || calendar', 'leadsforward-core'),
						__('Workmanship Warranty || certificate', 'leadsforward-core'),
					]
				),
				'cta_primary_override' => __('Get a Free Inspection', 'leadsforward-core'),
				'cta_secondary_override' => __('See Our Work', 'leadsforward-core'),
				'cta_primary_action' => 'quote',
				'cta_secondary_action' => 'link',
			],
			'trust_bar' => [
				'trust_stats_items' => $contractor_trust_stats,
				'trust_bar_layout' => 'stats_grid',
				'section_background' => 'soft',
			],
			'pricing' => [
				'enabled' => true,
				'financing_enabled' => '1',
			],
			'inline_form' => [
				'title' => __('Get Your Free Project Estimate', 'leadsforward-core'),
				'subtext' => __('No pressure. No obligation. Just honest answers.', 'leadsforward-core'),
				'button_label' => __('Request My Free Estimate', 'leadsforward-core'),
			],
			'topbar' => [
				'enabled' => true,
				'text' => __('Free Estimates Available · Proudly Serving [Your City]', 'leadsforward-core'),
			],
			'tone' => 'balanced',
		],
	];
}

/**
 * @return array<string, mixed>|null
 */
function lf_niche_get_homepage_blueprint(string $niche_slug): ?array {
	$niche_slug = sanitize_title($niche_slug);
	if ($niche_slug === '') {
		return null;
	}
	$registry = lf_niche_homepage_blueprint_registry();
	if (isset($registry[$niche_slug])) {
		return $registry[$niche_slug];
	}
	if ($niche_slug === 'foundation-repair') {
		return $registry['foundation-repair'];
	}
	return $registry['core-contractor'] ?? null;
}

/**
 * Homepage section order for a niche (blueprint → niche entry → theme default).
 *
 * @return list<string>
 */
function lf_niche_homepage_blueprint_order(string $niche_slug): array {
	$blueprint = lf_niche_get_homepage_blueprint($niche_slug);
	if (is_array($blueprint) && !empty($blueprint['order']) && is_array($blueprint['order'])) {
		return array_values(array_filter(array_map('sanitize_key', $blueprint['order'])));
	}
	if (function_exists('lf_sections_default_order')) {
		return lf_sections_default_order('homepage');
	}
	return [];
}

/**
 * @return array<string, mixed>
 */
function lf_niche_homepage_inline_form_args(string $niche_slug = ''): array {
	if ($niche_slug === '') {
		$niche_slug = (string) get_option(
			defined('LF_HOMEPAGE_NICHE_OPTION') ? LF_HOMEPAGE_NICHE_OPTION : 'lf_homepage_niche_slug',
			''
		);
	}
	if ($niche_slug === '') {
		$niche_slug = 'foundation-repair';
	}
	$blueprint = lf_niche_get_homepage_blueprint($niche_slug);
	$inline = (is_array($blueprint) && is_array($blueprint['inline_form'] ?? null)) ? $blueprint['inline_form'] : [];
	return array_merge(
		[
			'form_id' => 'lf-hero-inline-form',
			'title' => __('Get Your Free Inspection', 'leadsforward-core'),
			'subtext' => __('No pressure. No obligation. Just honest answers.', 'leadsforward-core'),
			'button_label' => __('Schedule My Free Inspection', 'leadsforward-core'),
		],
		$inline
	);
}

/**
 * Apply blueprint section defaults onto homepage config (fill empty keys only).
 *
 * @param array<string, array<string, mixed>> $config
 * @return array<string, array<string, mixed>>
 */
function lf_niche_apply_homepage_blueprint_config(array $config, string $niche_slug, ?string $city = null): array {
	$blueprint = lf_niche_get_homepage_blueprint($niche_slug);
	if (!is_array($blueprint)) {
		return $config;
	}
	$city = $city !== null ? sanitize_text_field($city) : '';
	$replace_city = static function (string $text) use ($city): string {
		if ($city === '') {
			return $text;
		}
		return str_replace(['[Your City]', '{city}'], $city, $text);
	};
	if (!empty($blueprint['section_enabled']) && is_array($blueprint['section_enabled'])) {
		foreach ($blueprint['section_enabled'] as $section_id => $enabled) {
			if (!isset($config[$section_id]) || !is_array($config[$section_id])) {
				continue;
			}
			$config[$section_id]['enabled'] = (bool) $enabled;
		}
	}
	foreach ($blueprint as $section_id => $defaults) {
		if (!is_array($defaults) || in_array($section_id, ['label', 'order', 'section_enabled', 'inline_form', 'topbar', 'tone'], true)) {
			continue;
		}
		if (!isset($config[$section_id]) || !is_array($config[$section_id])) {
			continue;
		}
		foreach ($defaults as $key => $value) {
			if ($key === 'enabled') {
				$config[$section_id]['enabled'] = (bool) $value;
				continue;
			}
			$current = $config[$section_id][$key] ?? '';
			if (is_string($current) && trim($current) !== '' && !str_contains($current, '[Your City]') && !str_contains($current, 'Your Business')) {
				continue;
			}
			if (is_string($value)) {
				$config[$section_id][$key] = $replace_city($value);
			} else {
				$config[$section_id][$key] = $value;
			}
		}
	}
	return $config;
}

/**
 * Apply niche utility bar defaults on setup (does not overwrite custom topbar text).
 */
function lf_niche_apply_homepage_topbar_defaults(string $niche_slug, ?string $city = null): void {
	$blueprint = lf_niche_get_homepage_blueprint($niche_slug);
	if (!is_array($blueprint) || empty($blueprint['topbar']) || !is_array($blueprint['topbar'])) {
		return;
	}
	$existing = function_exists('lf_header_topbar_text') ? lf_header_topbar_text() : '';
	if ($existing !== '') {
		return;
	}
	$text = (string) ($blueprint['topbar']['text'] ?? '');
	if ($text === '') {
		return;
	}
	if ($city !== null && $city !== '') {
		$text = str_replace(['[Your City]', '{city}'], $city, $text);
	}
	if (function_exists('lf_update_global_option_value')) {
		lf_update_global_option_value('lf_header_topbar_enabled', '1');
		lf_update_global_option_value('lf_header_topbar_text', $text);
	}
}
