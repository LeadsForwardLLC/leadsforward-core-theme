<?php
/**
 * Shared section library: registry, defaults, sanitizers, renderers.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_sections_icon_fields(): array {
	$icon_options = function_exists('lf_icon_options') ? lf_icon_options() : [];
	$icon_options = array_merge(['' => __('Auto (niche default)', 'leadsforward-core')], $icon_options);
	return [
		[
			'key' => 'icon_enabled',
			'label' => __('Enable icon', 'leadsforward-core'),
			'type' => 'select',
			'default' => '0',
			'options' => [
				'0' => __('Off', 'leadsforward-core'),
				'1' => __('On', 'leadsforward-core'),
			],
		],
		[
			'key' => 'icon_slug',
			'label' => __('Icon', 'leadsforward-core'),
			'type' => 'select',
			'default' => '',
			'options' => $icon_options,
		],
		[
			'key' => 'icon_position',
			'label' => __('Icon position', 'leadsforward-core'),
			'type' => 'select',
			'default' => 'left',
			'options' => [
				'above' => __('Above heading', 'leadsforward-core'),
				'left' => __('Left of heading', 'leadsforward-core'),
				'list' => __('Inline with list items', 'leadsforward-core'),
			],
		],
		[
			'key' => 'icon_size',
			'label' => __('Icon size', 'leadsforward-core'),
			'type' => 'select',
			'default' => 'md',
			'options' => [
				'sm' => __('Small', 'leadsforward-core'),
				'md' => __('Medium', 'leadsforward-core'),
				'lg' => __('Large', 'leadsforward-core'),
			],
		],
		[
			'key' => 'icon_color',
			'label' => __('Icon color', 'leadsforward-core'),
			'type' => 'select',
			'default' => 'primary',
			'options' => [
				'inherit' => __('Inherit', 'leadsforward-core'),
				'primary' => __('Primary', 'leadsforward-core'),
				'secondary' => __('Secondary', 'leadsforward-core'),
				'muted' => __('Muted', 'leadsforward-core'),
			],
		],
	];
}

function lf_sections_bg_options(): array {
	return [
		'white'     => __('White', 'leadsforward-core'),
		'light'     => __('Light', 'leadsforward-core'),
		'soft'      => __('Soft', 'leadsforward-core'),
		'primary'   => __('Primary', 'leadsforward-core'),
		'secondary' => __('Secondary', 'leadsforward-core'),
		'accent'    => __('Accent', 'leadsforward-core'),
		'dark'      => __('Dark', 'leadsforward-core'),
		'black'     => __('Black', 'leadsforward-core'),
		'card'      => __('Card', 'leadsforward-core'),
	];
}

function lf_sections_hero_variant_options(): array {
	return [
		'default' => __('Authority Split (Recommended)', 'leadsforward-core'),
		'a'       => __('Conversion Stack', 'leadsforward-core'),
		'b'       => __('Form First', 'leadsforward-core'),
		'c'       => __('Visual Proof', 'leadsforward-core'),
	];
}

function lf_sections_cta_action_options(bool $include_empty = false): array {
	$options = [
		'quote' => __('Open Quote Builder', 'leadsforward-core'),
		'call'  => __('Call now', 'leadsforward-core'),
		'link'  => __('Link', 'leadsforward-core'),
	];
	if ($include_empty) {
		return ['' => __('Use global/homepage setting', 'leadsforward-core')] + $options;
	}
	return $options;
}

function lf_sections_toggle_options(): array {
	return [
		'1' => __('On', 'leadsforward-core'),
		'0' => __('Off', 'leadsforward-core'),
	];
}

function lf_sections_registry(): array {
	$bg_field = [
		'key' => 'section_background',
		'label' => __('Background', 'leadsforward-core'),
		'type' => 'select',
		'default' => 'light',
		'options' => lf_sections_bg_options(),
	];
	$bg_soft = $bg_field;
	$bg_soft['default'] = 'soft';
	$bg_dark = $bg_field;
	$bg_dark['default'] = 'dark';
	$media_fields = [
		$bg_field,
		['key' => 'section_heading', 'label' => __('Section title', 'leadsforward-core'), 'type' => 'text', 'default' => __('Designed for busy homeowners', 'leadsforward-core')],
		['key' => 'section_intro', 'label' => __('Supporting text', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Clear communication, reliable crews, and a process built for modern service.', 'leadsforward-core')],
		['key' => 'section_body', 'label' => __('Main body text', 'leadsforward-core'), 'type' => 'richtext', 'default' => __('From first contact to final walkthrough, we keep the experience simple and professional. You get accurate timelines, transparent pricing, and a team that treats your home with care.', 'leadsforward-core')],
		['key' => 'cta_primary_override', 'label' => __('Primary CTA text', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
		['key' => 'cta_primary_action', 'label' => __('Primary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => 'quote', 'options' => [
			'quote' => __('Open Quote Builder', 'leadsforward-core'),
			'link'  => __('Link', 'leadsforward-core'),
		]],
		['key' => 'cta_primary_url', 'label' => __('Primary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
		['key' => 'image_id', 'label' => __('Image', 'leadsforward-core'), 'type' => 'image', 'default' => function_exists('lf_get_placeholder_image_id') ? lf_get_placeholder_image_id() : 0],
		['key' => 'image_alt', 'label' => __('Image alt text (optional)', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
		['key' => 'image_position', 'label' => __('Image focal point', 'leadsforward-core'), 'type' => 'select', 'default' => 'center', 'options' => [
			'center' => __('Center', 'leadsforward-core'),
			'top' => __('Top', 'leadsforward-core'),
			'bottom' => __('Bottom', 'leadsforward-core'),
			'left' => __('Left', 'leadsforward-core'),
			'right' => __('Right', 'leadsforward-core'),
			'top-left' => __('Top left', 'leadsforward-core'),
			'top-right' => __('Top right', 'leadsforward-core'),
			'bottom-left' => __('Bottom left', 'leadsforward-core'),
			'bottom-right' => __('Bottom right', 'leadsforward-core'),
		]],
	];
	$icon_fields = lf_sections_icon_fields();
	$sections = [
		'hero' => [
			'label' => __('Hero', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page', 'post'],
			'fields' => [
				$bg_soft,
				['key' => 'variant', 'label' => __('Hero layout', 'leadsforward-core'), 'type' => 'select', 'default' => 'default', 'options' => lf_sections_hero_variant_options()],
				['key' => 'hero_headline', 'label' => __('Headline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'hero_subheadline', 'label' => __('Subheadline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_primary_enabled', 'label' => __('Primary CTA enabled', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
				['key' => 'cta_secondary_enabled', 'label' => __('Secondary CTA enabled', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
				['key' => 'cta_primary_override', 'label' => __('Primary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_secondary_override', 'label' => __('Secondary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_primary_action', 'label' => __('Primary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => lf_sections_cta_action_options(true)],
				['key' => 'cta_primary_url', 'label' => __('Primary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
				['key' => 'cta_secondary_action', 'label' => __('Secondary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => lf_sections_cta_action_options(true)],
				['key' => 'cta_secondary_url', 'label' => __('Secondary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
			],
			'render' => 'lf_sections_render_hero',
		],
		'trust_bar' => [
			'label' => __('Trust Bar', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page'],
			'fields' => [
				$bg_field,
				['key' => 'trust_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Trusted by local homeowners', 'leadsforward-core')],
				['key' => 'trust_badges', 'label' => __('Badges (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Licensed & Insured' . "\n" . '5-Star Rated' . "\n" . 'Fast Response', 'leadsforward-core')],
				['key' => 'trust_rating', 'label' => __('Rating override (optional)', 'leadsforward-core'), 'type' => 'number', 'default' => ''],
				['key' => 'trust_review_count', 'label' => __('Review count override (optional)', 'leadsforward-core'), 'type' => 'number', 'default' => ''],
			],
			'render' => 'lf_sections_render_trust_bar',
		],
		'benefits' => [
			'label' => __('Benefits / Why Choose Us', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Why Homeowners Choose Us', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Clear pricing, fast response, and workmanship you can trust.', 'leadsforward-core')],
				['key' => 'benefits_items', 'label' => __('Benefits (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Fast response windows' . "\n" . 'Licensed, insured professionals' . "\n" . 'Upfront pricing before work starts', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_benefits',
		],
		'service_details' => [
			'label' => __('Service Details', 'leadsforward-core'),
			'contexts' => ['homepage'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Service Details', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Everything you need to know before scheduling.', 'leadsforward-core')],
				['key' => 'service_details_body', 'label' => __('Body copy', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				['key' => 'service_details_checklist', 'label' => __('Checklist (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Transparent scope and pricing' . "\n" . 'Clean, respectful crews' . "\n" . 'Work backed by warranty', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_service_details',
		],
		'content_image' => [
			'label' => __('Content with Image', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page'],
			'fields' => $media_fields,
			'render' => 'lf_sections_render_content_image',
		],
		'image_content' => [
			'label' => __('Image with Content', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page'],
			'fields' => $media_fields,
			'render' => 'lf_sections_render_image_content',
		],
		'content' => [
			'label' => __('Content', 'leadsforward-core'),
			'contexts' => ['service', 'service_area', 'page', 'post'],
			'fields' => [
				$bg_field,
			],
			'render' => 'lf_sections_render_content',
		],
		'process' => [
			'label' => __('Process', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Our Process', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Simple, clear steps from first call to completion.', 'leadsforward-core')],
				['key' => 'process_steps', 'label' => __('Steps (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Tell us what you need' . "\n" . 'Get a fast, clear estimate' . "\n" . 'Schedule and complete the work', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_process',
		],
		'faq_accordion' => [
			'label' => __('FAQ', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Frequently Asked Questions', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Answers to common questions about scheduling and service.', 'leadsforward-core')],
				['key' => 'faq_max_items', 'label' => __('Max items', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
			],
			'render' => 'lf_sections_render_faq',
		],
		'cta' => [
			'label' => __('CTA Band', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page', 'post'],
			'fields' => [
				$bg_dark,
				['key' => 'cta_headline', 'label' => __('CTA headline', 'leadsforward-core'), 'type' => 'text', 'default' => __('Get a fast, no-obligation estimate', 'leadsforward-core')],
				['key' => 'cta_subheadline', 'label' => __('Supporting text (optional)', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				['key' => 'cta_primary_override', 'label' => __('Primary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_secondary_override', 'label' => __('Secondary CTA label', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'cta_primary_action', 'label' => __('Primary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => [
					''      => __('Use global', 'leadsforward-core'),
					'quote' => __('Open Quote Builder', 'leadsforward-core'),
					'call'  => __('Call now', 'leadsforward-core'),
					'link'  => __('Link', 'leadsforward-core'),
				]],
				['key' => 'cta_primary_url', 'label' => __('Primary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
				['key' => 'cta_secondary_action', 'label' => __('Secondary CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => '', 'options' => [
					''      => __('Use global', 'leadsforward-core'),
					'call'  => __('Call now', 'leadsforward-core'),
					'quote' => __('Open Quote Builder', 'leadsforward-core'),
					'link'  => __('Link', 'leadsforward-core'),
				]],
				['key' => 'cta_secondary_url', 'label' => __('Secondary CTA URL', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
			],
			'render' => 'lf_sections_render_cta_band',
		],
		'trust_reviews' => [
			'label' => __('Reviews', 'leadsforward-core'),
			'contexts' => ['page'],
			'fields' => [
				$bg_field,
				['key' => 'trust_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('What Our Customers Say', 'leadsforward-core')],
				['key' => 'trust_max_items', 'label' => __('Max items', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
			],
			'render' => 'lf_sections_render_trust_reviews',
		],
		'service_grid' => [
			'label' => __('Services Grid', 'leadsforward-core'),
			'contexts' => ['page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Our Services', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Explore our most requested services.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_service_grid',
		],
		'service_areas' => [
			'label' => __('Service Areas Grid', 'leadsforward-core'),
			'contexts' => ['page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Areas We Serve', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Select a location to learn more.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_service_areas',
		],
		'blog_posts' => [
			'label' => __('Blog Posts', 'leadsforward-core'),
			'contexts' => ['page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Latest Articles', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Helpful tips and updates from our team.', 'leadsforward-core')],
				['key' => 'posts_per_page', 'label' => __('Posts per page', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
			],
			'render' => 'lf_sections_render_blog_posts',
		],
		'sitemap_links' => [
			'label' => __('Sitemap Links', 'leadsforward-core'),
			'contexts' => ['page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Quick links', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Browse the full site from one place.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_sitemap_links',
		],
		'related_links' => [
			'label' => __('Related Links', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page', 'post'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Explore More', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Browse related services and areas we serve.', 'leadsforward-core')],
				['key' => 'related_links_mode', 'label' => __('Links to show', 'leadsforward-core'), 'type' => 'select', 'default' => 'both', 'options' => [
					'services' => __('Services', 'leadsforward-core'),
					'areas'    => __('Service Areas', 'leadsforward-core'),
					'both'     => __('Both', 'leadsforward-core'),
				]],
			],
			'render' => 'lf_sections_render_related_links',
		],
		'services_offered_here' => [
			'label' => __('Services Offered Here', 'leadsforward-core'),
			'contexts' => ['service_area'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Services in this area', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Explore the services available in your area.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_services_offered',
		],
		'nearby_areas' => [
			'label' => __('Nearby Areas', 'leadsforward-core'),
			'contexts' => ['service_area'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Nearby service areas', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('We also serve these nearby locations.', 'leadsforward-core')],
				['key' => 'nearby_areas_max', 'label' => __('Max areas', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
			],
			'render' => 'lf_sections_render_nearby_areas',
		],
		'map_nap' => [
			'label' => __('Service Areas + Map', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Areas We Serve', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Find us on the map and explore nearby neighborhoods.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_map_nap',
		],
	];
	foreach ($sections as $id => $section) {
		$fields = $section['fields'] ?? [];
		$sections[$id]['fields'] = array_merge($fields, $icon_fields);
	}
	return $sections;
}

function lf_sections_default_order(string $context): array {
	$base = ['hero', 'trust_bar', 'benefits', 'process', 'faq_accordion', 'cta', 'related_links'];
	if ($context === 'homepage') {
		array_splice($base, 3, 0, ['service_details', 'content_image', 'image_content']);
		$base[] = 'map_nap';
		return $base;
	}
	if ($context === 'service') {
		array_splice($base, 3, 0, ['content']);
		$base[] = 'map_nap';
		return $base;
	}
	if ($context === 'service_area') {
		$base = ['hero', 'trust_bar', 'benefits', 'process', 'faq_accordion', 'cta'];
		array_splice($base, 3, 0, ['content']);
		$base[] = 'services_offered_here';
		$base[] = 'nearby_areas';
		return $base;
	}
	if ($context === 'page') {
		return ['hero', 'content'];
	}
	if ($context === 'post') {
		return ['hero', 'content', 'related_links', 'cta'];
	}
	return $base;
}

function lf_sections_get_context_sections(string $context): array {
	$registry = lf_sections_registry();
	$out = [];
	foreach ($registry as $id => $section) {
		$contexts = $section['contexts'] ?? [];
		if (in_array($context, $contexts, true)) {
			$section['id'] = $id;
			$out[$id] = $section;
		}
	}
	return $out;
}

function lf_sections_defaults_for(string $section_id, string $niche_slug = ''): array {
	$section = lf_sections_registry()[$section_id] ?? null;
	if (!$section) {
		return [];
	}
	$defaults = [];
	foreach ($section['fields'] as $field) {
		$defaults[$field['key']] = $field['default'] ?? '';
	}
	if (function_exists('lf_icon_default_settings')) {
		$niche_slug = $niche_slug !== '' ? $niche_slug : (string) get_option('lf_homepage_niche_slug', 'general');
		$defaults = array_merge($defaults, lf_icon_default_settings($section_id, $niche_slug));
	}
	return $defaults;
}

function lf_sections_sanitize_settings(string $section_id, array $input): array {
	$section = lf_sections_registry()[$section_id] ?? null;
	if (!$section) {
		return [];
	}
	$out = [];
	foreach ($section['fields'] as $field) {
		$key = $field['key'];
		$raw = $input[$key] ?? ($field['default'] ?? '');
		switch ($field['type']) {
			case 'textarea':
				$out[$key] = sanitize_textarea_field(wp_unslash((string) $raw));
				break;
			case 'richtext':
				$out[$key] = wp_kses_post(wp_unslash((string) $raw));
				break;
			case 'url':
				$out[$key] = esc_url_raw(wp_unslash((string) $raw));
				break;
			case 'number':
				$val = trim(wp_unslash((string) $raw));
				$val = preg_replace('/[^0-9.]/', '', $val);
				$out[$key] = $val;
				break;
			case 'image':
				$out[$key] = absint($raw);
				break;
			case 'select':
				$val = sanitize_text_field(wp_unslash((string) $raw));
				$options = $field['options'] ?? [];
				$out[$key] = array_key_exists($val, $options) ? $val : ($field['default'] ?? '');
				break;
			case 'list':
				$out[$key] = sanitize_textarea_field(wp_unslash((string) $raw));
				break;
			default:
				$out[$key] = sanitize_text_field(wp_unslash((string) $raw));
				break;
		}
	}
	return $out;
}

function lf_sections_parse_lines(string $value): array {
	$lines = array_filter(array_map('trim', explode("\n", $value)));
	return array_values(array_map('sanitize_text_field', $lines));
}

/**
 * Canonical CTA resolver: section > homepage > global. Returns normalized CTA payload.
 *
 * @param array $context          Context flags (e.g. ['homepage' => true, 'section' => [...]]).
 * @param array $section_instance Section-level overrides (cta_* keys).
 * @param array $fallbacks        Base fallback values (same keys as return array).
 */
function lf_resolve_cta(array $context = [], array $section_instance = [], array $fallbacks = []): array {
	$defaults = [
		'primary_text'     => __('Get a free estimate', 'leadsforward-core'),
		'secondary_text'   => __('Call now', 'leadsforward-core'),
		'ghl_embed'        => '',
		'primary_type'     => 'text',
		'primary_action'   => 'quote',
		'primary_url'      => '',
		'secondary_action' => 'call',
		'secondary_url'    => '',
	];
	$resolved = array_merge($defaults, array_intersect_key($fallbacks, $defaults));
	$section = !empty($section_instance) ? $section_instance : (is_array($context['section'] ?? null) ? $context['section'] : []);
	$is_homepage = (bool) ($context['homepage'] ?? false);
	if (!$is_homepage && is_front_page()) {
		$is_homepage = true;
	}

	$resolved['primary_text'] = lf_get_option('lf_cta_primary_text', 'option', $resolved['primary_text']);
	$resolved['secondary_text'] = lf_get_option('lf_cta_secondary_text', 'option', $resolved['secondary_text']);
	$resolved['ghl_embed'] = lf_get_option('lf_cta_ghl_embed', 'option', $resolved['ghl_embed']);
	$resolved['primary_type'] = lf_get_option('lf_cta_primary_type', 'option') ?: $resolved['primary_type'];
	$resolved['primary_action'] = lf_get_option('lf_cta_primary_action', 'option', $resolved['primary_action']) ?: $resolved['primary_action'];
	$resolved['primary_url'] = lf_get_option('lf_cta_primary_url', 'option', $resolved['primary_url']) ?: $resolved['primary_url'];
	$resolved['secondary_action'] = lf_get_option('lf_cta_secondary_action', 'option', $resolved['secondary_action']) ?: $resolved['secondary_action'];
	$resolved['secondary_url'] = lf_get_option('lf_cta_secondary_url', 'option', $resolved['secondary_url']) ?: $resolved['secondary_url'];

	if ($is_homepage && function_exists('get_field')) {
		$hp_primary = get_field('lf_homepage_cta_primary', 'option');
		$hp_secondary = get_field('lf_homepage_cta_secondary', 'option');
		$hp_ghl = get_field('lf_homepage_cta_ghl', 'option');
		$hp_type = get_field('lf_homepage_cta_primary_type', 'option');
		$hp_action = get_field('lf_homepage_cta_primary_action', 'option');
		$hp_url = get_field('lf_homepage_cta_primary_url', 'option');
		$hp_secondary_action = get_field('lf_homepage_cta_secondary_action', 'option');
		$hp_secondary_url = get_field('lf_homepage_cta_secondary_url', 'option');
		if ($hp_primary !== null && $hp_primary !== '') {
			$resolved['primary_text'] = $hp_primary;
		}
		if ($hp_secondary !== null && $hp_secondary !== '') {
			$resolved['secondary_text'] = $hp_secondary;
		}
		if ($hp_ghl !== null && $hp_ghl !== '') {
			$resolved['ghl_embed'] = $hp_ghl;
		}
		if ($hp_type !== null && $hp_type !== '') {
			$resolved['primary_type'] = $hp_type;
		}
		if ($hp_action !== null && $hp_action !== '') {
			$resolved['primary_action'] = $hp_action;
		}
		if ($hp_url !== null && $hp_url !== '') {
			$resolved['primary_url'] = $hp_url;
		}
		if ($hp_secondary_action !== null && $hp_secondary_action !== '') {
			$resolved['secondary_action'] = $hp_secondary_action;
		}
		if ($hp_secondary_url !== null && $hp_secondary_url !== '') {
			$resolved['secondary_url'] = $hp_secondary_url;
		}
	}

	if (is_array($section) && !empty($section)) {
		if (!empty($section['cta_primary_override'])) {
			$resolved['primary_text'] = $section['cta_primary_override'];
		}
		if (!empty($section['cta_secondary_override'])) {
			$resolved['secondary_text'] = $section['cta_secondary_override'];
		}
		if (!empty($section['cta_ghl_override'])) {
			$resolved['ghl_embed'] = $section['cta_ghl_override'];
		}
		if (!empty($section['cta_primary_action'])) {
			$resolved['primary_action'] = $section['cta_primary_action'];
		}
		if (!empty($section['cta_primary_url'])) {
			$resolved['primary_url'] = $section['cta_primary_url'];
		}
		if (!empty($section['cta_secondary_action'])) {
			$resolved['secondary_action'] = $section['cta_secondary_action'];
		}
		if (!empty($section['cta_secondary_url'])) {
			$resolved['secondary_url'] = $section['cta_secondary_url'];
		}
	}

	if ($resolved['primary_action'] === 'call') {
		$resolved['primary_type'] = 'call';
	}

	return [
		'primary_text'     => is_string($resolved['primary_text']) ? $resolved['primary_text'] : '',
		'secondary_text'   => is_string($resolved['secondary_text']) ? $resolved['secondary_text'] : '',
		'ghl_embed'        => is_string($resolved['ghl_embed']) ? $resolved['ghl_embed'] : '',
		'primary_type'     => in_array($resolved['primary_type'], ['call', 'form', 'text'], true) ? $resolved['primary_type'] : 'text',
		'primary_action'   => in_array($resolved['primary_action'], ['link', 'quote', 'call'], true) ? $resolved['primary_action'] : 'quote',
		'primary_url'      => is_string($resolved['primary_url']) ? $resolved['primary_url'] : '',
		'secondary_action' => in_array($resolved['secondary_action'], ['link', 'quote', 'call'], true) ? $resolved['secondary_action'] : 'call',
		'secondary_url'    => is_string($resolved['secondary_url']) ? $resolved['secondary_url'] : '',
	];
}

function lf_sections_bg_class(?string $value): string {
	switch ($value) {
		case 'white':
			return 'lf-surface-white';
		case 'soft':
			return 'lf-surface-soft';
		case 'primary':
			return 'lf-surface-dark lf-surface-primary';
		case 'secondary':
			return 'lf-surface-dark lf-surface-secondary';
		case 'accent':
			return 'lf-surface-dark lf-surface-accent';
		case 'dark':
			return 'lf-surface-dark';
		case 'black':
			return 'lf-surface-dark lf-surface-black';
		case 'card':
			return 'lf-surface-card';
		case 'light':
		default:
			return 'lf-surface-light';
	}
}

function lf_sections_image_position(?string $value): string {
	switch ($value) {
		case 'top':
			return '50% 0%';
		case 'bottom':
			return '50% 100%';
		case 'left':
			return '0% 50%';
		case 'right':
			return '100% 50%';
		case 'top-left':
			return '0% 0%';
		case 'top-right':
			return '100% 0%';
		case 'bottom-left':
			return '0% 100%';
		case 'bottom-right':
			return '100% 100%';
		case 'center':
		default:
			return '50% 50%';
	}
}

function lf_sections_render_section(string $section_id, string $context, array $settings, \WP_Post $post): void {
	$section = lf_sections_registry()[$section_id] ?? null;
	if (!$section) {
		return;
	}
	$callback = $section['render'] ?? '';
	if (is_callable($callback)) {
		call_user_func($callback, $context, $settings, $post);
	}
}

function lf_sections_render_shell_open(string $id, string $title = '', string $intro = '', string $background = 'light', array $settings = []): void {
	$bg_class = lf_sections_bg_class($background);
	$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, $id, 'above', 'lf-heading-icon') : '';
	$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, $id, 'left', 'lf-heading-icon') : '';
	$has_header = $title || $intro || $icon_above || $icon_left;
	?>
	<section class="lf-section lf-section--<?php echo esc_attr($id); ?> <?php echo esc_attr($bg_class); ?>">
		<div class="lf-section__inner">
			<?php if ($has_header) : ?>
				<header class="lf-section__header">
					<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
					<?php if ($title) : ?>
						<?php if ($icon_left) : ?>
							<div class="lf-heading-row">
								<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
								<h2 class="lf-section__title"><?php echo esc_html($title); ?></h2>
							</div>
						<?php else : ?>
							<h2 class="lf-section__title"><?php echo esc_html($title); ?></h2>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ($intro) : ?><p class="lf-section__intro"><?php echo esc_html($intro); ?></p><?php endif; ?>
				</header>
			<?php endif; ?>
			<div class="lf-section__body">
	<?php
}

function lf_sections_render_shell_close(): void {
	?>
			</div>
		</div>
	</section>
	<?php
}

function lf_sections_render_hero(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$can_use_h1 = function_exists('lf_should_output_h1')
		? lf_should_output_h1(['location' => 'hero', 'post_id' => $post->ID, 'context' => $context])
		: true;
	$heading_tag = $can_use_h1 ? 'h1' : 'h2';
	if ($context !== 'homepage') {
		static $hero_rendered = false;
		$heading_tag = ($hero_rendered || !$can_use_h1) ? 'h2' : 'h1';
		$hero_rendered = true;
	}
	$section = [
		'section_type' => 'hero',
		'hero_headline' => $settings['hero_headline'] ?? '',
		'hero_subheadline' => $settings['hero_subheadline'] ?? '',
		'section_background' => $settings['section_background'] ?? 'soft',
		'cta_primary_override' => $settings['cta_primary_override'] ?? '',
		'cta_secondary_override' => $settings['cta_secondary_override'] ?? '',
		'cta_primary_action' => $settings['cta_primary_action'] ?? '',
		'cta_primary_url' => $settings['cta_primary_url'] ?? '',
		'cta_secondary_action' => $settings['cta_secondary_action'] ?? '',
		'cta_secondary_url' => $settings['cta_secondary_url'] ?? '',
		'cta_primary_enabled' => $settings['cta_primary_enabled'] ?? '1',
		'cta_secondary_enabled' => $settings['cta_secondary_enabled'] ?? '1',
		'icon_enabled' => $settings['icon_enabled'] ?? '0',
		'icon_slug' => $settings['icon_slug'] ?? '',
		'icon_position' => $settings['icon_position'] ?? 'left',
		'icon_size' => $settings['icon_size'] ?? 'md',
		'icon_color' => $settings['icon_color'] ?? 'primary',
	];
	$variant = $settings['variant'] ?? 'default';
	$block = [
		'id'         => 'lf-hero',
		'variant'    => $variant,
		'attributes' => ['variant' => $variant, 'layout' => $variant],
		'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section, 'heading_tag' => $heading_tag],
	];
	lf_render_block_template('hero', $block, false, $block['context']);
}

function lf_sections_render_trust_bar(string $context, array $settings, \WP_Post $post): void {
	$rating = (float) ($settings['trust_rating'] ?? 0);
	$count = (int) ($settings['trust_review_count'] ?? 0);
	if ($rating <= 0 || $count <= 0) {
		$query = new WP_Query([
			'post_type'      => 'lf_testimonial',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'post_status'    => 'publish',
		]);
		$ratings_total = 0;
		$ratings_count = 0;
		foreach ($query->posts as $p) {
			$r = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', $p->ID) : 5;
			if ($r > 0) {
				$ratings_total += $r;
				$ratings_count++;
			}
		}
		$computed_rating = $ratings_count > 0 ? round($ratings_total / $ratings_count, 1) : 5.0;
		$computed_count = $ratings_count > 0 ? $ratings_count : 0;
		if ($rating <= 0) {
			$rating = $computed_rating;
		}
		if ($count <= 0) {
			$count = $computed_count;
		}
	}
	$badges = lf_sections_parse_lines((string) ($settings['trust_badges'] ?? ''));
	if (empty($badges)) {
		$badges = [__('Licensed & Insured', 'leadsforward-core'), __('5-Star Rated', 'leadsforward-core')];
	}
	$title = $settings['trust_heading'] ?? '';
	$badge_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, 'trust_bar', 'list', 'lf-trust-bar__badge-icon') : '';
	lf_sections_render_shell_open('trust-bar', $title, '', $settings['section_background'] ?? 'light', $settings);
	?>
	<div class="lf-trust-bar">
		<div class="lf-trust-bar__rating">
			<span class="lf-trust-bar__stars" aria-hidden="true">
				<?php for ($i = 0; $i < 5; $i++) : ?>
					<svg class="lf-trust-bar__star" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<?php endfor; ?>
			</span>
			<?php if ($rating) : ?><span class="lf-trust-bar__score"><?php echo esc_html(number_format($rating, 1)); ?></span><?php endif; ?>
			<?php if ($count) : ?><span class="lf-trust-bar__count"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $count, 'leadsforward-core'), $count)); ?></span><?php endif; ?>
		</div>
		<div class="lf-trust-bar__badges">
			<?php foreach ($badges as $badge) : ?>
				<span class="lf-trust-bar__badge">
					<?php if ($badge_icon) : ?><span class="lf-trust-bar__badge-icon"><?php echo $badge_icon; ?></span><?php endif; ?>
					<?php echo esc_html($badge); ?>
				</span>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_benefits(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$items = lf_sections_parse_lines((string) ($settings['benefits_items'] ?? ''));
	$item_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, 'benefits', 'list', 'lf-benefits__icon') : '';
	$card_class = $item_icon ? 'lf-benefits__card lf-benefits__card--icon' : 'lf-benefits__card';
	lf_sections_render_shell_open('benefits', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	?>
	<div class="lf-benefits">
		<?php foreach ($items as $item) : ?>
			<div class="<?php echo esc_attr($card_class); ?>">
				<?php if ($item_icon) : ?><span class="lf-benefits__icon"><?php echo $item_icon; ?></span><?php endif; ?>
				<span class="lf-benefits__text"><?php echo esc_html($item); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_service_details(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$body = $settings['service_details_body'] ?? '';
	$body_from_settings = $body !== '';
	if ($body === '' && $context !== 'homepage') {
		$body = apply_filters('the_content', $post->post_content);
	}
	if ($body_from_settings) {
		$body = wpautop($body);
	}
	$checklist = lf_sections_parse_lines((string) ($settings['service_details_checklist'] ?? ''));
	$list_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, 'service_details', 'list', 'lf-service-details__icon') : '';
	$checklist_class = $list_icon ? 'lf-service-details__checklist lf-service-details__checklist--icon' : 'lf-service-details__checklist';
	lf_sections_render_shell_open('service-details', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	?>
	<div class="lf-service-details">
		<?php if ($body) : ?>
			<div class="lf-service-details__body"><?php echo wp_kses_post($body); ?></div>
		<?php endif; ?>
		<?php if (!empty($checklist)) : ?>
			<ul class="<?php echo esc_attr($checklist_class); ?>" role="list">
				<?php foreach ($checklist as $item) : ?>
					<li>
						<?php if ($list_icon) : ?><span class="lf-service-details__icon"><?php echo $list_icon; ?></span><?php endif; ?>
						<span class="lf-service-details__text"><?php echo esc_html($item); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_media_content(string $context, array $settings, \WP_Post $post, string $layout): void {
	$title = $settings['section_heading'] ?? '';
	$support = $settings['section_intro'] ?? '';
	$body = $settings['section_body'] ?? '';
	$cta_override = $settings['cta_primary_override'] ?? '';
	$cta_action = $settings['cta_primary_action'] ?? '';
	$cta_url = $settings['cta_primary_url'] ?? '';

	$cta_section = [
		'cta_primary_override' => $cta_override,
		'cta_primary_action' => $cta_action,
		'cta_primary_url' => $cta_url,
	];
	$resolved_cta = function_exists('lf_resolve_cta')
		? lf_resolve_cta(['homepage' => ($context === 'homepage')], $cta_section, [])
		: [];

	$primary_text = $resolved_cta['primary_text'] ?? $cta_override;
	$primary_action = $resolved_cta['primary_action'] ?? ($cta_action ?: 'quote');
	if (!in_array($primary_action, ['quote', 'link'], true)) {
		$primary_action = 'quote';
	}
	$primary_url = $resolved_cta['primary_url'] ?? $cta_url;
	if ($primary_action === 'link' && $primary_url === '') {
		$primary_action = 'quote';
	}

	$image_id = isset($settings['image_id']) ? (int) $settings['image_id'] : 0;
	if ($image_id === 0 && function_exists('lf_get_placeholder_image_id')) {
		$image_id = lf_get_placeholder_image_id();
	}
	$alt_override = trim((string) ($settings['image_alt'] ?? ''));
	$alt_text = $alt_override;
	if ($alt_text === '' && $image_id) {
		$alt_text = (string) get_post_meta($image_id, '_wp_attachment_image_alt', true);
	}
	if ($alt_text === '') {
		$alt_text = $title !== '' ? $title : get_the_title($post);
	}
	$position = lf_sections_image_position($settings['image_position'] ?? 'center');
	$img_style = $position ? 'object-position:' . esc_attr($position) . ';' : '';
	$section_id = $layout === 'image-left' ? 'image_content' : 'content_image';
	$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, $section_id, 'above', 'lf-heading-icon') : '';
	$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, $section_id, 'left', 'lf-heading-icon') : '';

	$layout_class = $layout === 'image-left' ? 'lf-media-section--image-left' : 'lf-media-section--image-right';
	lf_sections_render_shell_open('media', '', '', $settings['section_background'] ?? 'light', []);
	?>
	<div class="lf-media-section <?php echo esc_attr($layout_class); ?>">
		<div class="lf-media-section__content">
			<?php if ($icon_above) : ?><span class="lf-heading-icon lf-heading-icon--above"><?php echo $icon_above; ?></span><?php endif; ?>
			<?php if ($title !== '') : ?>
				<?php if ($icon_left) : ?>
					<div class="lf-heading-row">
						<span class="lf-heading-icon lf-heading-icon--left"><?php echo $icon_left; ?></span>
						<h2 class="lf-media-section__title"><?php echo esc_html($title); ?></h2>
					</div>
				<?php else : ?>
					<h2 class="lf-media-section__title"><?php echo esc_html($title); ?></h2>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ($support !== '') : ?>
				<p class="lf-media-section__support"><?php echo esc_html($support); ?></p>
			<?php endif; ?>
			<?php if ($body !== '') : ?>
				<div class="lf-media-section__body lf-prose"><?php echo wp_kses_post(wpautop($body)); ?></div>
			<?php endif; ?>
			<?php if ($primary_text) : ?>
				<div class="lf-media-section__actions">
					<?php if ($primary_action === 'quote') : ?>
						<button type="button" class="lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="content-image"><?php echo esc_html($primary_text); ?></button>
					<?php elseif ($primary_action === 'link' && $primary_url !== '') : ?>
						<a href="<?php echo esc_url($primary_url); ?>" class="lf-btn lf-btn--primary"><?php echo esc_html($primary_text); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="lf-media-section__media">
			<div class="lf-media-section__image-frame">
				<?php if ($image_id) : ?>
					<?php
					echo wp_get_attachment_image(
						$image_id,
						'large',
						false,
						[
							'class' => 'lf-media-section__image',
							'loading' => 'lazy',
							'decoding' => 'async',
							'alt' => $alt_text,
							'style' => $img_style,
							'sizes' => '(max-width: 960px) 100vw, 50vw',
						]
					);
					?>
				<?php else : ?>
					<div class="lf-media-section__image-placeholder" aria-hidden="true"></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_content_image(string $context, array $settings, \WP_Post $post): void {
	lf_sections_render_media_content($context, $settings, $post, 'image-right');
}

function lf_sections_render_image_content(string $context, array $settings, \WP_Post $post): void {
	lf_sections_render_media_content($context, $settings, $post, 'image-left');
}

function lf_sections_render_content(string $context, array $settings, \WP_Post $post): void {
	$body = apply_filters('the_content', $post->post_content);
	if ($body === '') {
		return;
	}
	lf_sections_render_shell_open('content', '', '', $settings['section_background'] ?? 'light', $settings);
	?>
	<div class="lf-prose"><?php echo $body; ?></div>
	<?php
	lf_sections_render_shell_close();
}
function lf_sections_render_process(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$steps = lf_sections_parse_lines((string) ($settings['process_steps'] ?? ''));
	$list_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, 'process', 'list', 'lf-process__icon') : '';
	$process_class = $list_icon ? 'lf-process lf-process--icon' : 'lf-process';
	lf_sections_render_shell_open('process', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	?>
	<ol class="<?php echo esc_attr($process_class); ?>">
		<?php foreach ($steps as $step) : ?>
			<li class="lf-process__step">
				<?php if ($list_icon) : ?><span class="lf-process__icon"><?php echo $list_icon; ?></span><?php endif; ?>
				<span class="lf-process__text"><?php echo esc_html($step); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_faq(string $context, array $settings, \WP_Post $post): void {
	if (function_exists('lf_render_block_template')) {
		$section = [
			'section_heading' => $settings['section_heading'] ?? '',
			'section_intro' => $settings['section_intro'] ?? '',
			'faq_max_items' => $settings['faq_max_items'] ?? '',
			'section_background' => $settings['section_background'] ?? 'light',
			'icon_enabled' => $settings['icon_enabled'] ?? '0',
			'icon_slug' => $settings['icon_slug'] ?? '',
			'icon_position' => $settings['icon_position'] ?? 'left',
			'icon_size' => $settings['icon_size'] ?? 'md',
			'icon_color' => $settings['icon_color'] ?? 'primary',
		];
		$block = [
			'id'         => 'lf-faq',
			'variant'    => 'default',
			'attributes' => ['variant' => 'default', 'layout' => 'default'],
			'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
		];
		lf_render_block_template('faq-accordion', $block, false, $block['context']);
	}
}

function lf_sections_render_cta_band(string $context, array $settings, \WP_Post $post): void {
	if (function_exists('lf_render_block_template')) {
		$section = [
			'cta_headline' => $settings['cta_headline'] ?? '',
			'cta_subheadline' => $settings['cta_subheadline'] ?? '',
			'cta_primary_override' => $settings['cta_primary_override'] ?? '',
			'cta_secondary_override' => $settings['cta_secondary_override'] ?? '',
			'cta_primary_action' => $settings['cta_primary_action'] ?? '',
			'cta_primary_url' => $settings['cta_primary_url'] ?? '',
			'cta_secondary_action' => $settings['cta_secondary_action'] ?? '',
			'cta_secondary_url' => $settings['cta_secondary_url'] ?? '',
			'section_background' => $settings['section_background'] ?? 'dark',
			'icon_enabled' => $settings['icon_enabled'] ?? '0',
			'icon_slug' => $settings['icon_slug'] ?? '',
			'icon_position' => $settings['icon_position'] ?? 'left',
			'icon_size' => $settings['icon_size'] ?? 'md',
			'icon_color' => $settings['icon_color'] ?? 'primary',
		];
		$block = [
			'id'         => 'lf-cta-band',
			'variant'    => 'default',
			'attributes' => ['variant' => 'default', 'layout' => 'default'],
			'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
		];
		lf_render_block_template('cta', $block, false, $block['context']);
	}
}

function lf_sections_render_trust_reviews(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$section = [
		'trust_heading' => $settings['trust_heading'] ?? '',
		'trust_max_items' => $settings['trust_max_items'] ?? '',
		'section_background' => $settings['section_background'] ?? 'soft',
		'icon_enabled' => $settings['icon_enabled'] ?? '0',
		'icon_slug' => $settings['icon_slug'] ?? '',
		'icon_position' => $settings['icon_position'] ?? 'left',
		'icon_size' => $settings['icon_size'] ?? 'md',
		'icon_color' => $settings['icon_color'] ?? 'primary',
	];
	$block = [
		'id'         => 'lf-trust-reviews',
		'variant'    => 'default',
		'attributes' => ['variant' => 'default'],
		'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
	];
	lf_render_block_template('trust-reviews', $block, false, $block['context']);
}

function lf_sections_render_service_grid(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$section = [
		'section_heading' => $settings['section_heading'] ?? '',
		'section_intro' => $settings['section_intro'] ?? '',
		'section_background' => $settings['section_background'] ?? 'light',
		'icon_enabled' => $settings['icon_enabled'] ?? '0',
		'icon_slug' => $settings['icon_slug'] ?? '',
		'icon_position' => $settings['icon_position'] ?? 'left',
		'icon_size' => $settings['icon_size'] ?? 'md',
		'icon_color' => $settings['icon_color'] ?? 'primary',
	];
	$block = [
		'id'         => 'lf-service-grid',
		'variant'    => 'default',
		'attributes' => ['variant' => 'default'],
		'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
	];
	lf_render_block_template('service-grid', $block, false, $block['context']);
}

function lf_sections_render_service_areas(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$section = [
		'section_heading' => $settings['section_heading'] ?? '',
		'section_intro' => $settings['section_intro'] ?? '',
		'section_background' => $settings['section_background'] ?? 'soft',
		'icon_enabled' => $settings['icon_enabled'] ?? '0',
		'icon_slug' => $settings['icon_slug'] ?? '',
		'icon_position' => $settings['icon_position'] ?? 'left',
		'icon_size' => $settings['icon_size'] ?? 'md',
		'icon_color' => $settings['icon_color'] ?? 'primary',
	];
	$block = [
		'id'         => 'lf-service-areas',
		'variant'    => 'default',
		'attributes' => ['variant' => 'default'],
		'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
	];
	lf_render_block_template('service-areas', $block, false, $block['context']);
}

function lf_sections_render_blog_posts(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$count = isset($settings['posts_per_page']) ? (int) $settings['posts_per_page'] : 6;
	$count = max(1, min(12, $count));
	lf_sections_render_shell_open('blog-posts', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	$query = new \WP_Query([
		'post_type'      => 'post',
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	]);
	if ($query->have_posts()) {
		echo '<div class="posts-list">';
		while ($query->have_posts()) {
			$query->the_post();
			get_template_part('templates/parts/content', get_post_type());
		}
		echo '</div>';
		wp_reset_postdata();
	} else {
		echo '<p>' . esc_html__('No posts yet.', 'leadsforward-core') . '</p>';
	}
	lf_sections_render_shell_close();
}

function lf_sections_render_sitemap_links(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	lf_sections_render_shell_open('sitemap-links', $title, $intro, $settings['section_background'] ?? 'light', $settings);

	$pages = get_pages(['post_status' => 'publish', 'sort_order' => 'ASC', 'sort_column' => 'menu_order,post_title']);
	$services = get_posts([
		'post_type' => 'lf_service',
		'posts_per_page' => -1,
		'post_status' => 'publish',
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	$areas = get_posts([
		'post_type' => 'lf_service_area',
		'posts_per_page' => -1,
		'post_status' => 'publish',
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);

	echo '<div class="lf-sitemap">';
	if (!empty($pages)) {
		echo '<h3>' . esc_html__('Pages', 'leadsforward-core') . '</h3>';
		echo '<ul class="lf-related-links" role="list">';
		foreach ($pages as $page) {
			echo '<li><a href="' . esc_url(get_permalink($page)) . '">' . esc_html(get_the_title($page)) . '</a></li>';
		}
		echo '</ul>';
	}
	if (!empty($services)) {
		echo '<h3>' . esc_html__('Services', 'leadsforward-core') . '</h3>';
		echo '<ul class="lf-related-links" role="list">';
		foreach ($services as $svc) {
			echo '<li><a href="' . esc_url(get_permalink($svc)) . '">' . esc_html(get_the_title($svc)) . '</a></li>';
		}
		echo '</ul>';
	}
	if (!empty($areas)) {
		echo '<h3>' . esc_html__('Service Areas', 'leadsforward-core') . '</h3>';
		echo '<ul class="lf-related-links" role="list">';
		foreach ($areas as $area) {
			echo '<li><a href="' . esc_url(get_permalink($area)) . '">' . esc_html(get_the_title($area)) . '</a></li>';
		}
		echo '</ul>';
	}
	echo '</div>';

	lf_sections_render_shell_close();
}

function lf_sections_render_related_links(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$mode = $settings['related_links_mode'] ?? 'both';
	if (!in_array($mode, ['services', 'areas', 'both'], true)) {
		$mode = 'both';
	}
	$links = [];
	$max_links = 8;
	$origin_id = $post->ID;
	if ($mode === 'services' || $mode === 'both') {
		$services = get_posts([
			'post_type'      => 'lf_service',
			'posts_per_page' => $max_links,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		]);
		foreach ($services as $svc) {
			if (count($links) >= $max_links) {
				break;
			}
			$label = function_exists('lf_internal_link_label') ? lf_internal_link_label('service', $svc, $origin_id) : get_the_title($svc);
			$links[] = ['label' => $label, 'url' => get_permalink($svc)];
		}
	}
	if ($mode === 'areas' || $mode === 'both') {
		$areas = get_posts([
			'post_type'      => 'lf_service_area',
			'posts_per_page' => $max_links,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		]);
		foreach ($areas as $area) {
			if (count($links) >= $max_links) {
				break;
			}
			$label = function_exists('lf_internal_link_label') ? lf_internal_link_label('area', $area, $origin_id) : get_the_title($area);
			$links[] = ['label' => $label, 'url' => get_permalink($area)];
		}
	}
	if (empty($links)) {
		return;
	}
	$item_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, 'related_links', 'list', 'lf-related-links__icon') : '';
	lf_sections_render_shell_open('related-links', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	?>
	<ul class="lf-related-links" role="list">
		<?php foreach ($links as $link) : ?>
			<li>
				<a href="<?php echo esc_url($link['url']); ?>">
					<?php if ($item_icon) : ?><span class="lf-related-links__icon"><?php echo $item_icon; ?></span><?php endif; ?>
					<span class="lf-related-links__label"><?php echo esc_html($link['label']); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_service_areas_served(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$list_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, 'service_areas_served', 'list', 'lf-related-links__icon') : '';
	lf_sections_render_shell_open('service-areas-served', $title, $intro, 'light', $settings);
	get_template_part('templates/parts/related-service-areas', null, ['icon' => $list_icon]);
	lf_sections_render_shell_close();
}

function lf_sections_render_services_offered(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$list_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, 'services_offered_here', 'list', 'lf-related-links__icon') : '';
	lf_sections_render_shell_open('services-offered', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	get_template_part('templates/parts/related-services', null, ['icon' => $list_icon]);
	lf_sections_render_shell_close();
}

function lf_sections_render_nearby_areas(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$max = max(1, (int) ($settings['nearby_areas_max'] ?? 6));
	$origin_id = $post->ID;
	$area_icon = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, 'nearby_areas', 'list', 'lf-nearby-areas__icon') : '';
	$query = new WP_Query([
		'post_type'      => 'lf_service_area',
		'posts_per_page' => $max,
		'post__not_in'   => [$post->ID],
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	]);
	if (!$query->have_posts()) {
		return;
	}
	lf_sections_render_shell_open('nearby-areas', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	?>
	<ul class="lf-related-links" role="list">
		<?php while ($query->have_posts()) : $query->the_post();
			$label = function_exists('lf_internal_link_label') ? lf_internal_link_label('area', get_post(), $origin_id) : get_the_title();
		?>
			<li>
				<a href="<?php the_permalink(); ?>">
					<?php if ($area_icon) : ?><span class="lf-nearby-areas__icon"><?php echo $area_icon; ?></span><?php endif; ?>
					<span class="lf-related-links__label"><?php echo esc_html($label); ?></span>
				</a>
			</li>
		<?php endwhile; ?>
	</ul>
	<?php
	wp_reset_postdata();
	lf_sections_render_shell_close();
}

function lf_sections_render_map_nap(string $context, array $settings, \WP_Post $post): void {
	if (function_exists('lf_render_block_template')) {
		$section = [
			'section_heading' => $settings['section_heading'] ?? '',
			'section_intro'   => $settings['section_intro'] ?? '',
			'section_background' => $settings['section_background'] ?? 'light',
			'icon_enabled' => $settings['icon_enabled'] ?? '0',
			'icon_slug' => $settings['icon_slug'] ?? '',
			'icon_position' => $settings['icon_position'] ?? 'left',
			'icon_size' => $settings['icon_size'] ?? 'md',
			'icon_color' => $settings['icon_color'] ?? 'primary',
		];
		$block = [
			'id'         => 'lf-map-nap',
			'variant'    => 'default',
			'attributes' => ['variant' => 'default', 'layout' => 'default'],
			'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
		];
		lf_render_block_template('map-nap', $block, false, $block['context']);
	}
}
