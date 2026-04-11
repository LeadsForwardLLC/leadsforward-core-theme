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
		'internal' => __('Basic Internal Hero', 'leadsforward-core'),
		'default'  => __('Authority Split (Recommended)', 'leadsforward-core'),
		'a'        => __('Conversion Stack', 'leadsforward-core'),
		'b'        => __('Form First', 'leadsforward-core'),
		'c'        => __('Visual Proof', 'leadsforward-core'),
	];
}

function lf_sections_hero_media_options(): array {
	return [
		'none'  => __('No image', 'leadsforward-core'),
		'image' => __('Image on right', 'leadsforward-core'),
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
	$trust_bar_bg = $bg_field;
	$trust_bar_bg['default'] = 'dark';
	$media_fields = [
		$bg_field,
		['key' => 'section_heading', 'label' => __('Section title', 'leadsforward-core'), 'type' => 'text', 'default' => __('Designed for busy homeowners', 'leadsforward-core')],
		['key' => 'section_intro', 'label' => __('Supporting text', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Clear communication, reliable crews, and a process built for modern service.', 'leadsforward-core')],
		['key' => 'section_body', 'label' => __('Main body text', 'leadsforward-core'), 'type' => 'richtext', 'default' => __('From first contact to final walkthrough, we keep the experience simple and professional. You get accurate timelines, transparent pricing, and a team that treats your home with care.', 'leadsforward-core')],
		// Added for density expansion – vNext
		['key' => 'section_intro_secondary', 'label' => __('Secondary intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
		// Added for density expansion – vNext
		['key' => 'section_body_secondary', 'label' => __('Expanded body text', 'leadsforward-core'), 'type' => 'richtext', 'default' => ''],
		// Added for density expansion – vNext
		['key' => 'section_bullets', 'label' => __('Bullet list (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
		// Added for density expansion – vNext
		['key' => 'section_trust_block', 'label' => __('Trust / credibility block', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
		// Added for density expansion – vNext
		['key' => 'section_guarantee_text', 'label' => __('Guarantee text', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
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
	$media_fields_a = $media_fields;
	foreach ($media_fields_a as &$field) {
		if (($field['key'] ?? '') === 'section_heading') {
			$field['default'] = __('Experience & communication', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'section_intro') {
			$field['default'] = __('Know what to expect before, during, and after service.', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'section_body') {
			$field['default'] = __('We keep every step clear: confirm scope, share timelines, and follow through with updates. You will always know who is coming, what the next step is, and how to reach us.', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'cta_primary_override') {
			$field['default'] = __('Request service details', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'image_alt') {
			$field['default'] = __('Technician reviewing service details with a homeowner', 'leadsforward-core');
		}
	}
	unset($field);
	$media_fields_a[] = ['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'Experience & Communication'];
	$media_fields_a[] = ['key' => 'section_purpose', 'label' => __('Section purpose', 'leadsforward-core'), 'type' => 'text', 'default' => 'Explain what it\'s like to work with this company.'];

	$media_fields_b = $media_fields;
	foreach ($media_fields_b as &$field) {
		if (($field['key'] ?? '') === 'section_heading') {
			$field['default'] = __('Quality & craftsmanship', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'section_intro') {
			$field['default'] = __('Materials, workmanship, and standards you can trust.', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'section_body') {
			$field['default'] = __('Our team follows documented standards and best practices on every job. We use proven materials, keep work areas clean, and finish with a quality check you can see.', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'cta_primary_override') {
			$field['default'] = __('View our quality promise', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'image_alt') {
			$field['default'] = __('Close-up of completed craftsmanship detail', 'leadsforward-core');
		}
	}
	unset($field);
	$media_fields_b[] = ['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'Quality & Craftsmanship'];
	$media_fields_b[] = ['key' => 'section_purpose', 'label' => __('Section purpose', 'leadsforward-core'), 'type' => 'text', 'default' => 'Reinforce workmanship, materials, and standards.'];

	$media_fields_c = $media_fields;
	foreach ($media_fields_c as &$field) {
		if (($field['key'] ?? '') === 'section_heading') {
			$field['default'] = __('Local trust & reliability', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'section_intro') {
			$field['default'] = __('A nearby team that shows up and stands behind the work.', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'section_body') {
			$field['default'] = __('We are accountable to the communities we serve. Expect fast responses, honest recommendations, and a team that treats your home with respect.', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'cta_primary_override') {
			$field['default'] = __('Meet the local team', 'leadsforward-core');
		}
		if (($field['key'] ?? '') === 'image_alt') {
			$field['default'] = __('Local service team arriving for a scheduled visit', 'leadsforward-core');
		}
	}
	unset($field);
	$media_fields_c[] = ['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'Local Trust & Reliability'];
	$media_fields_c[] = ['key' => 'section_purpose', 'label' => __('Section purpose', 'leadsforward-core'), 'type' => 'text', 'default' => 'Emphasize local ownership, responsiveness, and accountability.'];
	$service_details_fields = [
		$bg_field,
		['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'service_summary'],
		['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Service Details', 'leadsforward-core')],
		['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Everything you need to know before scheduling.', 'leadsforward-core')],
		['key' => 'service_details_body', 'label' => __('Body copy', 'leadsforward-core'), 'type' => 'richtext', 'default' => ''],
		['key' => 'service_details_layout', 'label' => __('Layout', 'leadsforward-core'), 'type' => 'select', 'default' => 'content_media', 'options' => [
			'content_media' => __('Content left / Media right', 'leadsforward-core'),
			'media_content' => __('Media left / Content right', 'leadsforward-core'),
		]],
		['key' => 'service_details_media_mode', 'label' => __('Media mode', 'leadsforward-core'), 'type' => 'select', 'default' => 'video', 'options' => [
			'video' => __('Video embed', 'leadsforward-core'),
			'image' => __('Image', 'leadsforward-core'),
			'none' => __('None', 'leadsforward-core'),
		]],
		['key' => 'service_details_media_embed', 'label' => __('Video embed code', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
		['key' => 'service_details_media_video_url', 'label' => __('Video URL (self-hosted or YouTube)', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
		['key' => 'service_details_media_image_id', 'label' => __('Media image', 'leadsforward-core'), 'type' => 'image', 'default' => ''],
		['key' => 'service_details_checklist', 'label' => __('Checklist (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Transparent scope and pricing' . "\n" . 'Clean, respectful crews' . "\n" . 'Work backed by warranty', 'leadsforward-core')],
		['key' => 'service_details_checklist_secondary', 'label' => __('Checklist column 2 (one per line, optional)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
		['key' => 'service_details_proof_label', 'label' => __('Mini proof label (optional)', 'leadsforward-core'), 'type' => 'text', 'default' => __('Also included', 'leadsforward-core')],
		['key' => 'service_details_proof_badges', 'label' => __('Mini proof badges (one per line, optional)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
		['key' => 'service_details_micro_sections', 'label' => __('Service micro-sections (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
	];
	$service_details_variant = static function (array $fields, array $overrides): array {
		foreach ($fields as &$field) {
			$key = $field['key'] ?? '';
			if ($key !== '' && array_key_exists($key, $overrides)) {
				$field['default'] = $overrides[$key];
			}
		}
		unset($field);
		return $fields;
	};
	$media_defaults = [];
	foreach ($media_fields as $field) {
		if (!empty($field['key'])) {
			$media_defaults[$field['key']] = $field['default'] ?? '';
		}
	}
	$media_a_defaults = [];
	foreach ($media_fields_a as $field) {
		if (!empty($field['key'])) {
			$media_a_defaults[$field['key']] = $field['default'] ?? '';
		}
	}
	$media_b_defaults = [];
	foreach ($media_fields_b as $field) {
		if (!empty($field['key'])) {
			$media_b_defaults[$field['key']] = $field['default'] ?? '';
		}
	}
	$media_c_defaults = [];
	foreach ($media_fields_c as $field) {
		if (!empty($field['key'])) {
			$media_c_defaults[$field['key']] = $field['default'] ?? '';
		}
	}
	$service_details_fields_content = $service_details_variant($service_details_fields, [
		'section_heading' => $media_defaults['section_heading'] ?? '',
		'section_intro' => $media_defaults['section_intro'] ?? '',
		'service_details_body' => $media_defaults['section_body'] ?? '',
		'service_details_layout' => 'content_media',
		'service_details_media_mode' => 'image',
	]);
	$service_details_fields_media = $service_details_variant($service_details_fields, [
		'section_heading' => $media_defaults['section_heading'] ?? '',
		'section_intro' => $media_defaults['section_intro'] ?? '',
		'service_details_body' => $media_defaults['section_body'] ?? '',
		'service_details_layout' => 'media_content',
		'service_details_media_mode' => 'image',
	]);
	$service_details_fields_a = $service_details_variant($service_details_fields, [
		'section_heading' => $media_a_defaults['section_heading'] ?? '',
		'section_intro' => $media_a_defaults['section_intro'] ?? '',
		'service_details_body' => $media_a_defaults['section_body'] ?? '',
		'service_details_layout' => 'content_media',
		'service_details_media_mode' => 'image',
	]);
	$service_details_fields_b = $service_details_variant($service_details_fields, [
		'section_heading' => $media_b_defaults['section_heading'] ?? '',
		'section_intro' => $media_b_defaults['section_intro'] ?? '',
		'service_details_body' => $media_b_defaults['section_body'] ?? '',
		'service_details_layout' => 'media_content',
		'service_details_media_mode' => 'image',
	]);
	$service_details_fields_c = $service_details_variant($service_details_fields, [
		'section_heading' => $media_c_defaults['section_heading'] ?? '',
		'section_intro' => $media_c_defaults['section_intro'] ?? '',
		'service_details_body' => $media_c_defaults['section_body'] ?? '',
		'service_details_layout' => 'content_media',
		'service_details_media_mode' => 'image',
	]);
	$icon_fields = lf_sections_icon_fields();
	$sections = [
		'hero' => [
			'label' => __('Hero', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page', 'post'],
			'fields' => [
				$bg_soft,
				['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'positioning'],
				['key' => 'hero_background_mode', 'label' => __('Hero background', 'leadsforward-core'), 'type' => 'select', 'default' => 'image', 'options' => [
					'color' => __('Background color', 'leadsforward-core'),
					'image' => __('Featured image overlay', 'leadsforward-core'),
					'video' => __('Video background', 'leadsforward-core'),
				]],
				['key' => 'hero_background_image_id', 'label' => __('Hero background image', 'leadsforward-core'), 'type' => 'image', 'default' => 0],
				['key' => 'hero_background_video_id', 'label' => __('Hero background video (MP4)', 'leadsforward-core'), 'type' => 'image', 'default' => 0],
				['key' => 'variant', 'label' => __('Hero layout', 'leadsforward-core'), 'type' => 'select', 'default' => 'default', 'options' => lf_sections_hero_variant_options()],
				['key' => 'hero_headline', 'label' => __('Headline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'hero_subheadline', 'label' => __('Subheadline', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'hero_proof_title', 'label' => __('Proof card title', 'leadsforward-core'), 'type' => 'text', 'default' => __('Why homeowners choose us', 'leadsforward-core')],
				['key' => 'hero_chip_bullets', 'label' => __('Hero pills (one per line, left column)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
				['key' => 'hero_proof_bullets', 'label' => __('Proof card bullets (one per line, right card)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Fast response and clear pricing' . "\n" . 'Licensed, insured, and local' . "\n" . 'Clean work backed by warranty', 'leadsforward-core')],
				['key' => 'hero_trust_strip_enabled', 'label' => __('Show homeowner trust row under CTAs', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
				// Added for density expansion – vNext
				['key' => 'hero_supporting_text', 'label' => __('Supporting text', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				// Added for density expansion – vNext
				['key' => 'hero_bullets', 'label' => __('Bullet list (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
				// Added for density expansion – vNext
				['key' => 'hero_trust_block', 'label' => __('Trust / credibility block', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				// Added for density expansion – vNext
				['key' => 'hero_guarantee_text', 'label' => __('Guarantee text', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'hero_eyebrow_enabled', 'label' => __('Trust badge enabled', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
				['key' => 'hero_eyebrow_text', 'label' => __('Trust badge text', 'leadsforward-core'), 'type' => 'text', 'default' => __('Licensed • Insured • Local', 'leadsforward-core')],
				['key' => 'hero_media', 'label' => __('Hero media', 'leadsforward-core'), 'type' => 'select', 'default' => 'none', 'options' => lf_sections_hero_media_options()],
				['key' => 'hero_image_id', 'label' => __('Hero image', 'leadsforward-core'), 'type' => 'image', 'default' => 0],
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
				$trust_bar_bg,
				['key' => 'trust_bar_layout', 'label' => __('Layout', 'leadsforward-core'), 'type' => 'select', 'default' => 'brand_band', 'options' => [
					'brand_band'    => __('Brand band (full-width strip)', 'leadsforward-core'),
					'split'         => __('Split (heading left, proof right)', 'leadsforward-core'),
					'grid'          => __('Grid badges', 'leadsforward-core'),
					'minimal_strip' => __('Minimal strip', 'leadsforward-core'),
					'classic'       => __('Classic centered pill', 'leadsforward-core'),
				]],
				['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'authority'],
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
				['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'authority'],
				['key' => 'benefits_layout', 'label' => __('Layout', 'leadsforward-core'), 'type' => 'select', 'default' => 'cards', 'options' => [
					'cards' => __('Cards', 'leadsforward-core'),
					'cards_points' => __('Cards + supporting points', 'leadsforward-core'),
					'split' => __('Split (cards + proof)', 'leadsforward-core'),
				]],
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Why Homeowners Choose Us', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Clear pricing, fast response, and workmanship you can trust.', 'leadsforward-core')],
				// Added for density expansion – vNext
				['key' => 'section_intro_secondary', 'label' => __('Secondary intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				['key' => 'benefits_items', 'label' => __('Benefits (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Fast response windows || Clear arrival times and quick follow-up after you reach out.' . "\n" . 'Licensed, insured professionals || Fully vetted team backed by proper coverage and local reviews.' . "\n" . 'Upfront pricing before work starts || Detailed estimates so you always know the next step.', 'leadsforward-core')],
				['key' => 'benefits_icon_overrides', 'label' => __('Benefit icons (one per line, optional icon slug)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
				['key' => 'benefits_title_word_limit', 'label' => __('Benefit title word limit', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
				['key' => 'benefits_body_word_limit', 'label' => __('Benefit body word limit', 'leadsforward-core'), 'type' => 'number', 'default' => '18'],
				// Added for density expansion – vNext
				['key' => 'benefits_supporting_points', 'label' => __('Supporting points (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
				// Added for density expansion – vNext
				['key' => 'benefits_trust_block', 'label' => __('Trust / credibility block', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				['key' => 'benefits_cta_text', 'label' => __('CTA button text (optional)', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
				['key' => 'benefits_cta_action', 'label' => __('CTA button action', 'leadsforward-core'), 'type' => 'select', 'default' => 'quote', 'options' => [
					'quote' => __('Open Quote Builder', 'leadsforward-core'),
					'link'  => __('Link', 'leadsforward-core'),
				]],
				['key' => 'benefits_cta_url', 'label' => __('CTA button URL (if action=Link)', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
			],
			'render' => 'lf_sections_render_benefits',
		],
		'service_details' => [
			'label' => __('Service Details', 'leadsforward-core'),
			'contexts' => ['homepage', 'service'],
			'fields' => $service_details_fields,
			'render' => 'lf_sections_render_service_details',
		],
		'content_image' => [
			'label' => __('Content + Media', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page', 'post'],
			'fields' => $media_fields,
			'render' => 'lf_sections_render_content_image',
		],
		'content_image_a' => [
			'label' => __('Content + Media (A)', 'leadsforward-core'),
			'contexts' => ['homepage'],
			'fields' => $media_fields_a,
			'render' => 'lf_sections_render_content_image',
		],
		'image_content' => [
			'label' => __('Media + Content', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page', 'post'],
			'fields' => $media_fields,
			'render' => 'lf_sections_render_image_content',
		],
		'image_content_b' => [
			'label' => __('Media + Content (B)', 'leadsforward-core'),
			'contexts' => ['homepage'],
			'fields' => $media_fields_b,
			'render' => 'lf_sections_render_image_content',
		],
		'content_image_c' => [
			'label' => __('Content + Media (C)', 'leadsforward-core'),
			'contexts' => ['homepage'],
			'fields' => $media_fields_c,
			'render' => 'lf_sections_render_content_image',
		],
		'content_centered' => [
			'label' => __('Centered Content', 'leadsforward-core'),
			'contexts' => ['page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Clear next steps', 'leadsforward-core')],
				['key' => 'optional_subheading', 'label' => __('Optional subheading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Everything you need before you reach out', 'leadsforward-core')],
				['key' => 'supporting_text', 'label' => __('Supporting text', 'leadsforward-core'), 'type' => 'richtext', 'default' => __('Use this space to set expectations, outline what happens next, or answer quick pre-contact questions. Keep it concise and homeowner-friendly.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_content_centered',
		],
		'content' => [
			'label' => __('Content', 'leadsforward-core'),
			'contexts' => ['service', 'service_area', 'page', 'post'],
			'fields' => [
				$bg_field,
				['key' => 'content_layout', 'label' => __('Layout', 'leadsforward-core'), 'type' => 'select', 'default' => 'single', 'options' => [
					'single' => __('Single column', 'leadsforward-core'),
					'two_col' => __('Two columns', 'leadsforward-core'),
				]],
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('What to expect', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Helpful details about this service and what homeowners should know.', 'leadsforward-core')],
				['key' => 'section_body', 'label' => __('Body copy', 'leadsforward-core'), 'type' => 'richtext', 'default' => ''],
				['key' => 'section_body_secondary', 'label' => __('Expanded body', 'leadsforward-core'), 'type' => 'richtext', 'default' => ''],
				['key' => 'section_body_left', 'label' => __('Left column body (two-column layout)', 'leadsforward-core'), 'type' => 'richtext', 'default' => ''],
				['key' => 'section_body_right', 'label' => __('Right column body (two-column layout)', 'leadsforward-core'), 'type' => 'richtext', 'default' => ''],
			],
			'render' => 'lf_sections_render_content',
		],
		'process' => [
			'label' => __('Process', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page'],
			'fields' => [
				$bg_field,
				['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'process'],
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Our Process', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Simple, clear steps from first call to completion.', 'leadsforward-core')],
				// Added for density expansion – vNext
				['key' => 'section_intro_secondary', 'label' => __('Secondary intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				['key' => 'process_selected_ids', 'label' => __('Selected process step IDs (one post ID per line, optional)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
				['key' => 'process_steps', 'label' => __('Steps (one per line, fallback if IDs empty)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Tell us what you need' . "\n" . 'Get a fast, clear estimate' . "\n" . 'Schedule and complete the work', 'leadsforward-core')],
				['key' => 'process_expectations', 'label' => __('Expectations text', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
			],
			'render' => 'lf_sections_render_process',
		],
		'faq_accordion' => [
			'label' => __('FAQ', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page'],
			'fields' => [
				$bg_field,
				['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'objection_handling'],
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Frequently Asked Questions', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Answers to common questions about scheduling and service.', 'leadsforward-core')],
				// Added for density expansion – vNext
				['key' => 'section_intro_secondary', 'label' => __('Secondary intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				['key' => 'faq_columns', 'label' => __('Columns (desktop)', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => [
					'1' => __('1 column', 'leadsforward-core'),
					'2' => __('2 columns', 'leadsforward-core'),
				]],
				['key' => 'faq_schema_enabled', 'label' => __('FAQ schema (SEO)', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
				['key' => 'faq_max_items', 'label' => __('Max items', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
				['key' => 'faq_selected_ids', 'label' => __('Selected FAQ IDs (one ID per line, optional)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
				// Added for density expansion – vNext
				['key' => 'faq_trust_block', 'label' => __('Trust / credibility block', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
			],
			'render' => 'lf_sections_render_faq',
		],
		'cta' => [
			'label' => __('CTA Band', 'leadsforward-core'),
			'contexts' => ['homepage', 'service', 'service_area', 'page', 'post'],
			'fields' => [
				$bg_dark,
				['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'conversion'],
				['key' => 'cta_trust_strip_enabled', 'label' => __('Show trust strip (rating + badges)', 'leadsforward-core'), 'type' => 'select', 'default' => '0', 'options' => lf_sections_toggle_options()],
				['key' => 'cta_trust_rating', 'label' => __('Trust strip rating (optional)', 'leadsforward-core'), 'type' => 'number', 'default' => ''],
				['key' => 'cta_trust_review_count', 'label' => __('Trust strip review count (optional)', 'leadsforward-core'), 'type' => 'number', 'default' => ''],
				['key' => 'cta_trust_badges', 'label' => __('Trust strip badges (one per line, optional)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
				['key' => 'cta_headline', 'label' => __('CTA headline', 'leadsforward-core'), 'type' => 'text', 'default' => __('Get a fast, no-obligation estimate', 'leadsforward-core')],
				['key' => 'cta_subheadline', 'label' => __('Supporting text (optional)', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				// Added for density expansion – vNext
				['key' => 'cta_bullets', 'label' => __('CTA bullets (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
				// Added for density expansion – vNext
				['key' => 'cta_trust_block', 'label' => __('Trust / credibility block', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				// Added for density expansion – vNext
				['key' => 'cta_guarantee_text', 'label' => __('Guarantee text', 'leadsforward-core'), 'type' => 'text', 'default' => ''],
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
			'contexts' => ['homepage', 'page', 'service', 'service_area'],
			'fields' => [
				$bg_field,
				['key' => 'trust_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('What Our Customers Say', 'leadsforward-core')],
				// Layout
				['key' => 'trust_layout', 'label' => __('Layout', 'leadsforward-core'), 'type' => 'select', 'default' => 'slider', 'options' => [
					'slider' => __('Slider', 'leadsforward-core'),
					'masonry' => __('Masonry', 'leadsforward-core'),
					'grid' => __('Grid', 'leadsforward-core'),
				]],
				['key' => 'trust_columns', 'label' => __('Columns', 'leadsforward-core'), 'type' => 'select', 'default' => '3', 'options' => [
					'2' => __('2 Columns', 'leadsforward-core'),
					'3' => __('3 Columns', 'leadsforward-core'),
					'4' => __('4 Columns', 'leadsforward-core'),
				]],
				['key' => 'trust_max_items', 'label' => __('Number of Reviews', 'leadsforward-core'), 'type' => 'number', 'default' => '9'],
				// Slider Only
				['key' => 'trust_slider_autoplay', 'label' => __('Auto-Play', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
			],
			'render' => 'lf_sections_render_trust_reviews',
		],
		'service_intro' => [
			'label' => __('Service Intro Boxes', 'leadsforward-core'),
			'contexts' => ['homepage', 'page', 'service', 'service_area'],
			'fields' => [
				$bg_field,
				['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'service_summary'],
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Service options built for homeowners', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Explore our core services with clear scopes and upfront expectations.', 'leadsforward-core')],
				['key' => 'service_intro_columns', 'label' => __('Columns', 'leadsforward-core'), 'type' => 'select', 'default' => '3', 'options' => [
					'3' => __('3 columns', 'leadsforward-core'),
					'4' => __('4 columns', 'leadsforward-core'),
					'5' => __('5 columns', 'leadsforward-core'),
					'6' => __('6 columns', 'leadsforward-core'),
				]],
				['key' => 'service_intro_max_items', 'label' => __('Max services', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
				['key' => 'service_intro_show_images', 'label' => __('Show images', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
				['key' => 'section_header_align', 'label' => __('Header & intro alignment', 'leadsforward-core'), 'type' => 'select', 'default' => 'center', 'options' => [
					'left' => __('Left', 'leadsforward-core'),
					'center' => __('Center', 'leadsforward-core'),
					'right' => __('Right', 'leadsforward-core'),
				]],
			],
			'render' => 'lf_sections_render_service_intro',
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
				['key' => 'map_heading', 'label' => __('Map heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Service area map', 'leadsforward-core')],
				['key' => 'map_intro', 'label' => __('Map intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Map pins show the areas currently covered by our team.', 'leadsforward-core')],
				['key' => 'search_placeholder', 'label' => __('Search placeholder', 'leadsforward-core'), 'type' => 'text', 'default' => __('Search city or neighborhood', 'leadsforward-core')],
				['key' => 'filter_label', 'label' => __('Filter label', 'leadsforward-core'), 'type' => 'text', 'default' => __('Filter by state', 'leadsforward-core')],
				['key' => 'filter_all_label', 'label' => __('Filter all label', 'leadsforward-core'), 'type' => 'text', 'default' => __('All areas', 'leadsforward-core')],
				['key' => 'no_results_text', 'label' => __('No results text', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('No service areas match your search yet. Clear filters to view all coverage.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_service_areas',
		],
		'project_gallery' => [
			'label' => __('Project Gallery', 'leadsforward-core'),
			'contexts' => ['homepage', 'page', 'service', 'service_area', 'post'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Our Projects', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Explore recent transformations and finished work.', 'leadsforward-core')],
				['key' => 'projects_per_page', 'label' => __('Projects per page', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
				['key' => 'project_layout', 'label' => __('Layout', 'leadsforward-core'), 'type' => 'select', 'default' => 'grid', 'options' => [
					'grid' => __('Grid', 'leadsforward-core'),
					'masonry' => __('Masonry', 'leadsforward-core'),
					'slider' => __('Slider', 'leadsforward-core'),
				]],
				['key' => 'project_slider_controls', 'label' => __('Show slider controls', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
				['key' => 'project_show_filters', 'label' => __('Show filters', 'leadsforward-core'), 'type' => 'select', 'default' => '0', 'options' => lf_sections_toggle_options()],
				['key' => 'project_show_before_after', 'label' => __('Show before/after toggle', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
			],
			'render' => 'lf_sections_render_project_gallery',
		],
		'logo_strip' => [
			'label' => __('Certifications / Logo Strip', 'leadsforward-core'),
			'contexts' => ['homepage', 'page', 'service', 'service_area', 'post'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Certified & trusted', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro (optional)', 'leadsforward-core'), 'type' => 'textarea', 'default' => ''],
				['key' => 'logo_strip_logos', 'label' => __('Logo image IDs (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => ''],
				['key' => 'logo_strip_max', 'label' => __('Max logos', 'leadsforward-core'), 'type' => 'number', 'default' => '10'],
				['key' => 'section_header_align', 'label' => __('Header alignment', 'leadsforward-core'), 'type' => 'select', 'default' => 'center', 'options' => [
					'left' => __('Left', 'leadsforward-core'),
					'center' => __('Center', 'leadsforward-core'),
					'right' => __('Right', 'leadsforward-core'),
				]],
			],
			'render' => 'lf_sections_render_logo_strip',
		],
		'team' => [
			'label' => __('Team', 'leadsforward-core'),
			'contexts' => ['homepage', 'page', 'service', 'service_area'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Meet the team', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro (optional)', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Local professionals committed to clean work and clear communication.', 'leadsforward-core')],
				['key' => 'team_columns', 'label' => __('Columns', 'leadsforward-core'), 'type' => 'select', 'default' => '3', 'options' => [
					'2' => __('2 columns', 'leadsforward-core'),
					'3' => __('3 columns', 'leadsforward-core'),
					'4' => __('4 columns', 'leadsforward-core'),
				]],
				['key' => 'team_members', 'label' => __('Members (one per line: Name || Role || Bio)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Alex Morgan || Project Manager || Your point of contact from scheduling to final walkthrough.' . "\n" . 'Jordan Lee || Lead Technician || Detail-focused workmanship and clean job sites.' . "\n" . 'Taylor Reed || Customer Support || Fast responses and clear next steps.', 'leadsforward-core')],
				['key' => 'section_header_align', 'label' => __('Header alignment', 'leadsforward-core'), 'type' => 'select', 'default' => 'center', 'options' => [
					'left' => __('Left', 'leadsforward-core'),
					'center' => __('Center', 'leadsforward-core'),
					'right' => __('Right', 'leadsforward-core'),
				]],
			],
			'render' => 'lf_sections_render_team',
		],
		'pricing' => [
			'label' => __('Pricing & Financing', 'leadsforward-core'),
			'contexts' => ['page', 'service', 'service_area'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('What affects cost', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Clear expectations before you schedule.', 'leadsforward-core')],
				['key' => 'pricing_factors', 'label' => __('Cost factors (one per line)', 'leadsforward-core'), 'type' => 'list', 'default' => __('Scope and materials' . "\n" . 'Access and roofline complexity' . "\n" . 'Repairs discovered during inspection', 'leadsforward-core')],
				['key' => 'financing_enabled', 'label' => __('Financing enabled', 'leadsforward-core'), 'type' => 'select', 'default' => '0', 'options' => lf_sections_toggle_options()],
				['key' => 'financing_text', 'label' => __('Financing text (optional)', 'leadsforward-core'), 'type' => 'text', 'default' => __('Ask about financing options and flexible scheduling.', 'leadsforward-core')],
				['key' => 'pricing_cta_text', 'label' => __('CTA text (optional)', 'leadsforward-core'), 'type' => 'text', 'default' => __('Get a free estimate', 'leadsforward-core')],
				['key' => 'pricing_cta_action', 'label' => __('CTA action', 'leadsforward-core'), 'type' => 'select', 'default' => 'quote', 'options' => [
					'quote' => __('Open Quote Builder', 'leadsforward-core'),
					'link'  => __('Link', 'leadsforward-core'),
				]],
				['key' => 'pricing_cta_url', 'label' => __('CTA URL (if Link)', 'leadsforward-core'), 'type' => 'url', 'default' => ''],
				['key' => 'section_header_align', 'label' => __('Header alignment', 'leadsforward-core'), 'type' => 'select', 'default' => 'center', 'options' => [
					'left' => __('Left', 'leadsforward-core'),
					'center' => __('Center', 'leadsforward-core'),
					'right' => __('Right', 'leadsforward-core'),
				]],
			],
			'render' => 'lf_sections_render_pricing',
		],
		'packages' => [
			'label' => __('Packages / Comparison', 'leadsforward-core'),
			'contexts' => ['page', 'service'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Choose the right option', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Compare scopes and pick what fits your goals.', 'leadsforward-core')],
				['key' => 'package_cards', 'label' => __('Cards (one per line: Name || Best for || bullet1, bullet2, bullet3 || highlight(1/0))', 'leadsforward-core'), 'type' => 'list', 'default' => __('Repair || Fixing a specific issue || Targeted repairs, fast scheduling, clear scope || 0' . "\n" . 'Replace || Long-term protection || Full tear-off options, premium materials, workmanship warranty || 1' . "\n" . 'Maintenance || Extending roof life || Inspection, tune-ups, minor sealing, documentation || 0', 'leadsforward-core')],
				['key' => 'section_header_align', 'label' => __('Header alignment', 'leadsforward-core'), 'type' => 'select', 'default' => 'center', 'options' => [
					'left' => __('Left', 'leadsforward-core'),
					'center' => __('Center', 'leadsforward-core'),
					'right' => __('Right', 'leadsforward-core'),
				]],
			],
			'render' => 'lf_sections_render_packages',
		],
		'blog_posts' => [
			'label' => __('Blog Posts', 'leadsforward-core'),
			'contexts' => ['page'],
			'fields' => [
				$bg_field,
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Latest Articles', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Helpful tips and updates from our team.', 'leadsforward-core')],
				['key' => 'posts_per_page', 'label' => __('Posts per page', 'leadsforward-core'), 'type' => 'number', 'default' => '6'],
				['key' => 'posts_layout', 'label' => __('Layout', 'leadsforward-core'), 'type' => 'select', 'default' => 'grid', 'options' => [
					'grid' => __('Grid', 'leadsforward-core'),
					'masonry' => __('Masonry', 'leadsforward-core'),
					'slider' => __('Slider', 'leadsforward-core'),
				]],
				['key' => 'posts_slider_controls', 'label' => __('Show slider controls', 'leadsforward-core'), 'type' => 'select', 'default' => '1', 'options' => lf_sections_toggle_options()],
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
				['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'conversion'],
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Explore More Services', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Explore more of our services.', 'leadsforward-core')],
				['key' => 'related_links_mode', 'label' => __('Links to show', 'leadsforward-core'), 'type' => 'select', 'default' => 'services', 'options' => [
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
			'contexts' => ['homepage', 'service', 'service_area', 'page'],
			'fields' => [
				$bg_field,
				['key' => 'section_intent', 'label' => __('Section intent', 'leadsforward-core'), 'type' => 'text', 'default' => 'authority'],
				['key' => 'section_heading', 'label' => __('Heading', 'leadsforward-core'), 'type' => 'text', 'default' => __('Areas We Serve', 'leadsforward-core')],
				['key' => 'section_intro', 'label' => __('Intro', 'leadsforward-core'), 'type' => 'textarea', 'default' => __('Find us on the map and explore nearby neighborhoods.', 'leadsforward-core')],
			],
			'render' => 'lf_sections_render_map_nap',
		],
	];
	$header_align_field = [
		'key' => 'section_header_align',
		'label' => __('Header alignment', 'leadsforward-core'),
		'type' => 'select',
		'default' => 'center',
		'options' => [
			'left' => __('Left', 'leadsforward-core'),
			'center' => __('Center', 'leadsforward-core'),
			'right' => __('Right', 'leadsforward-core'),
		],
	];
	foreach ($sections as $sid => $sec) {
		$fields = $sec['fields'] ?? [];
		$has_align = false;
		foreach ($fields as $f) {
			if (($f['key'] ?? '') === 'section_header_align') {
				$has_align = true;
				break;
			}
		}
		if (!$has_align && $sid !== 'hero') {
			$heading_like = false;
			foreach ($fields as $f) {
				$k = $f['key'] ?? '';
				if (in_array($k, ['section_heading', 'section_intro', 'trust_heading', 'cta_headline'], true)) {
					$heading_like = true;
					break;
				}
			}
			if ($heading_like) {
				$fields[] = $header_align_field;
			}
		}
		$sections[$sid]['fields'] = $fields;
	}
	foreach ($sections as $id => $section) {
		$fields = $section['fields'] ?? [];
		$sections[$id]['fields'] = array_merge($fields, $icon_fields);
	}
	return $sections;
}

function lf_sections_default_order(string $context): array {
	$base = ['hero', 'trust_bar', 'benefits', 'process', 'faq_accordion', 'cta', 'related_links'];
	if ($context === 'homepage') {
		return [
			'hero',
			'trust_bar',
			'service_intro',
			'benefits',
			'service_details',
			'service_details__2',
			'process',
			'faq_accordion',
			'trust_reviews',
			'related_links',
			'map_nap',
			'cta',
		];
	}
	if ($context === 'service') {
		return [
			'hero',
			'trust_bar',
			'service_details',
			'benefits',
			'process',
			'faq_accordion',
			'cta',
		];
	}
	if ($context === 'service_area') {
		return [
			'hero',
			'trust_bar',
			'content_image',
			'benefits',
			'content_image',
			'image_content',
			'process',
			'related_links',
			'nearby_areas',
			'faq_accordion',
			'cta',
		];
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

function lf_sections_service_details_alias_layouts(): array {
	return [];
}

function lf_sections_normalize_service_details_settings(string $section_id, array $settings): array {
	$aliases = lf_sections_service_details_alias_layouts();
	if (!isset($aliases[$section_id])) {
		return $settings;
	}
	$out = $settings;
	if (empty($out['service_details_layout'])) {
		$out['service_details_layout'] = $aliases[$section_id];
	}
	if (empty($out['service_details_body'])) {
		$body_primary = trim((string) ($settings['section_body'] ?? ''));
		$body_secondary = trim((string) ($settings['section_body_secondary'] ?? ''));
		if ($body_primary !== '' && $body_secondary !== '') {
			$out['service_details_body'] = $body_primary . "\n\n" . $body_secondary;
		} elseif ($body_primary !== '') {
			$out['service_details_body'] = $body_primary;
		} elseif ($body_secondary !== '') {
			$out['service_details_body'] = $body_secondary;
		}
	}
	if (empty($out['service_details_checklist']) && !empty($settings['section_bullets'])) {
		$out['service_details_checklist'] = $settings['section_bullets'];
	}
	if (empty($out['service_details_media_image_id']) && !empty($settings['image_id'])) {
		$out['service_details_media_image_id'] = $settings['image_id'];
	}
	if (empty($out['service_details_media_mode'])) {
		$out['service_details_media_mode'] = !empty($out['service_details_media_image_id']) ? 'image' : 'video';
	}
	return $out;
}

function lf_sections_sanitize_settings(string $section_id, array $input): array {
	$section = lf_sections_registry()[$section_id] ?? null;
	if (!$section) {
		return [];
	}
	$input = lf_sections_normalize_service_details_settings($section_id, $input);
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
				if (is_array($raw)) {
					$lines = [];
					foreach ($raw as $item) {
						if (is_array($item)) {
							$item = wp_json_encode($item);
						}
						$item = (string) $item;
						$item = trim($item);
						if ($item !== '') {
							$lines[] = $item;
						}
					}
					$raw = implode("\n", $lines);
				}
				$raw_list = wp_unslash((string) $raw);
				$html_list_keys = [
					'service_details_checklist',
					'service_details_checklist_secondary',
					'benefits_items',
					'hero_chip_bullets',
					'hero_proof_bullets',
					'trust_badges',
				];
				if (in_array($key, $html_list_keys, true) && function_exists('lf_ai_sanitize_inline_dom_html')) {
					$html_lines = preg_split('/\r\n|\r|\n/', $raw_list);
					$html_lines = is_array($html_lines) ? $html_lines : [];
					$clean = [];
					foreach ($html_lines as $line) {
						$line_clean = lf_ai_sanitize_inline_dom_html((string) $line);
						if (trim(wp_strip_all_tags($line_clean)) !== '') {
							$clean[] = $line_clean;
						}
					}
					$out[$key] = implode("\n", $clean);
				} else {
					$out[$key] = sanitize_textarea_field($raw_list);
				}
				break;
			default:
				$out[$key] = sanitize_text_field(wp_unslash((string) $raw));
				break;
		}
	}
	return $out;
}

function lf_sections_parse_lines($value): array {
	if (is_array($value)) {
		$lines = [];
		foreach ($value as $item) {
			if (is_array($item)) {
				$item = wp_json_encode($item);
			}
			$item = (string) $item;
			$item = trim($item);
			if ($item !== '') {
				$lines[] = $item;
			}
		}
		$value = implode("\n", $lines);
	}
	$lines = array_filter(array_map('trim', explode("\n", (string) $value)));
	return array_values(array_map(static function (string $line): string {
		if (function_exists('lf_ai_is_inline_dom_html_string') && lf_ai_is_inline_dom_html_string($line) && function_exists('lf_ai_sanitize_inline_dom_html')) {
			return lf_ai_sanitize_inline_dom_html($line);
		}
		return sanitize_text_field($line);
	}, $lines));
}

/**
 * Resolve process section steps:
 * - If any published lf_process_step posts resolve from process_selected_ids, those drive the section.
 * - Otherwise, if process_selected_ids is empty, try taxonomy auto-pick (lf_process_group) for service pages/homepage.
 * - Otherwise, fall back to line-based process_steps.
 *
 * @param array<string, mixed> $settings
 * @return array<int, array{id:int,title:string,body:string}>
 */
function lf_sections_process_steps_for_render(array $settings, ?\WP_Post $post = null): array {
	$ids_raw = trim((string) ($settings['process_selected_ids'] ?? ''));
	if ($ids_raw !== '') {
		$steps = [];
		foreach (preg_split('/\r\n|\r|\n/', $ids_raw) as $line) {
			$id = absint(trim($line));
			if ($id <= 0) {
				continue;
			}
			$step_post = get_post($id);
			if (!$step_post instanceof \WP_Post || $step_post->post_type !== 'lf_process_step' || $step_post->post_status !== 'publish') {
				continue;
			}
			$title = (string) get_the_title($step_post);
			$title = trim($title);
			if ($title === '') {
				continue;
			}
			$body = wp_strip_all_tags((string) get_post_field('post_content', $step_post));
			$body = preg_replace('/\s+/', ' ', trim((string) $body));
			$steps[] = [
				'id' => (int) $id,
				'title' => $title,
				'body' => (string) $body,
			];
		}
		if ($steps !== []) {
			return $steps;
		}
	}

	// Taxonomy auto-pick: only when no manual IDs provided.
	if ($ids_raw === '' && taxonomy_exists('lf_process_group')) {
		$term_slug = '';
		if ($post instanceof \WP_Post && $post->post_type === 'lf_service') {
			$term_slug = (string) $post->post_name;
		} elseif ($post instanceof \WP_Post && $post->post_type === 'lf_service_area') {
			$service_slug = '';
			if (function_exists('get_field')) {
				$services = get_field('lf_service_area_services', $post->ID);
				if (is_array($services) && !empty($services[0])) {
					$first = $services[0];
					if ($first instanceof \WP_Post) {
						$service_slug = (string) $first->post_name;
					} elseif (is_numeric($first)) {
						$first_post = get_post((int) $first);
						if ($first_post instanceof \WP_Post) {
							$service_slug = (string) $first_post->post_name;
						}
					}
				}
			}
			$term_slug = $service_slug;
		} elseif (is_front_page()) {
			$term_slug = 'homepage-primary';
		}
		$term_slug = sanitize_title($term_slug);
		if ($term_slug !== '' && term_exists($term_slug, 'lf_process_group')) {
			$q = new \WP_Query([
				'post_type' => 'lf_process_step',
				'post_status' => 'publish',
				'posts_per_page' => 8,
				'orderby' => 'menu_order title',
				'order' => 'ASC',
				'no_found_rows' => true,
				'tax_query' => [
					[
						'taxonomy' => 'lf_process_group',
						'field' => 'slug',
						'terms' => [$term_slug],
					],
				],
			]);
			$steps = [];
			while ($q->have_posts()) {
				$q->the_post();
				$id = (int) get_the_ID();
				$title = trim((string) get_the_title());
				if ($title === '') {
					continue;
				}
				$body = wp_strip_all_tags((string) get_post_field('post_content', $id));
				$body = preg_replace('/\s+/', ' ', trim((string) $body));
				$steps[] = [
					'id' => $id,
					'title' => $title,
					'body' => (string) $body,
				];
			}
			wp_reset_postdata();
			if ($steps !== []) {
				return $steps;
			}
		}
	}

	// Fallback: parse plain lines.
	$lines = lf_sections_parse_lines((string) ($settings['process_steps'] ?? ''));
	$steps = [];
	foreach ($lines as $line) {
		$line = trim((string) $line);
		if ($line === '') {
			continue;
		}
		$title = $line;
		$body = '';
		if (strpos($line, '||') !== false) {
			$parts = array_map('trim', explode('||', $line, 2));
			$title = (string) ($parts[0] ?? $line);
			$body = (string) ($parts[1] ?? '');
		} elseif (strpos($line, '|') !== false) {
			$parts = array_map('trim', explode('|', $line, 2));
			$title = (string) ($parts[0] ?? $line);
			$body = (string) ($parts[1] ?? '');
		} elseif (strpos($line, ' - ') !== false) {
			$parts = array_map('trim', explode(' - ', $line, 2));
			$title = (string) ($parts[0] ?? $line);
			$body = (string) ($parts[1] ?? '');
		} elseif (strpos($line, ' — ') !== false) {
			$parts = array_map('trim', explode(' — ', $line, 2));
			$title = (string) ($parts[0] ?? $line);
			$body = (string) ($parts[1] ?? '');
		} elseif (strpos($line, ':') !== false) {
			$parts = array_map('trim', explode(':', $line, 2));
			$title = (string) ($parts[0] ?? $line);
			$body = (string) ($parts[1] ?? '');
		}
		$steps[] = [
			'id' => 0,
			'title' => $title,
			'body' => $body,
		];
	}
	return $steps;
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

/**
 * Sanitize a custom section background for inline CSS (preset tokens are separate).
 */
function lf_sections_sanitize_custom_background(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}
	if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})\z/i', $raw)) {
		return strtolower($raw);
	}
	if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(\s*,\s*(0|1|0?\.\d+)\s*)?\)\s*$/i', $raw)) {
		return $raw;
	}
	return '';
}

/**
 * Approximate swatch hex for the section background picker UI (matches theme surfaces).
 *
 * @return array<string, string>
 */
function lf_sections_bg_swatch_hex_map(): array {
	return [
		'white' => '#ffffff',
		'light' => '#f8fafc',
		'soft' => '#f1f5f9',
		'primary' => '#4f46e5',
		'secondary' => '#0f766e',
		'accent' => '#c026d3',
		'dark' => '#0f172a',
		'black' => '#020617',
		'card' => '#ffffff',
	];
}

/**
 * @param array<string, mixed> $settings
 */
function lf_sections_sanitize_header_align(array $settings): string {
	$a = sanitize_key((string) ($settings['section_header_align'] ?? 'center'));
	return in_array($a, ['left', 'center', 'right'], true) ? $a : 'center';
}

/**
 * Surface class + optional inline background for block-style sections.
 *
 * @param array<string, mixed> $section
 * @return array{class:string, style:string}
 */
function lf_sections_block_surface_attrs(array $section): array {
	$custom = lf_sections_sanitize_custom_background((string) ($section['section_background_custom'] ?? ''));
	if ($custom !== '') {
		return [
			'class' => 'lf-block--custom-section-bg',
			'style' => 'background-color:' . $custom . ';',
		];
	}
	$slug = (string) ($section['section_background'] ?? 'light');
	return [
		'class' => lf_sections_bg_class($slug),
		'style' => '',
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
	$settings = lf_sections_normalize_service_details_settings($section_id, $settings);
	$settings['_section_instance_id'] = $section_id;
	$callback = $section['render'] ?? '';
	if (is_callable($callback)) {
		call_user_func($callback, $context, $settings, $post);
	}
}

function lf_sections_render_shell_open(string $id, string $title = '', string $intro = '', string $background = 'light', array $settings = [], string $extra_section_classes = ''): void {
	$custom_bg = lf_sections_sanitize_custom_background((string) ($settings['section_background_custom'] ?? ''));
	$bg_class = $custom_bg !== '' ? 'lf-section--custom-section-bg' : lf_sections_bg_class($background);
	$section_style = $custom_bg !== '' ? ' style="background-color:' . esc_attr($custom_bg) . ';"' : ''; // safe: esc_attr on color
	$header_align = lf_sections_sanitize_header_align($settings);
	$icon_above = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, $id, 'above', 'lf-heading-icon') : '';
	$icon_left = function_exists('lf_section_icon_markup') ? lf_section_icon_markup($settings, $id, 'left', 'lf-heading-icon') : '';
	$has_header = $title || $intro || $icon_above || $icon_left;
	$extra_classes = trim(preg_replace('/\s+/', ' ', $extra_section_classes));
	$extra_for_class = $extra_classes !== '' ? ' ' . esc_attr($extra_classes) : '';
	?>
	<section class="lf-section lf-section--<?php echo esc_attr($id); ?> <?php echo esc_attr($bg_class); ?><?php echo $extra_for_class; ?>"<?php echo $section_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr ?>>
		<div class="lf-section__inner">
			<?php if ($has_header) : ?>
				<header class="lf-section__header lf-section__header--align-<?php echo esc_attr($header_align); ?>">
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

/**
 * Pass-through of hero-related keys from section settings into the hero block context.
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function lf_sections_hero_block_section(array $settings): array {
	$keys = [
		'section_background', 'section_background_custom', 'section_intent',
		'hero_background_mode', 'hero_background_image_id', 'hero_background_video_id',
		'hero_headline', 'hero_subheadline', 'hero_proof_title', 'hero_chip_bullets', 'hero_proof_bullets', 'hero_trust_strip_enabled',
		'hero_supporting_text', 'hero_bullets', 'hero_trust_block', 'hero_guarantee_text',
		'hero_eyebrow_enabled', 'hero_eyebrow_text', 'hero_media', 'hero_image_id',
		'cta_primary_enabled', 'cta_secondary_enabled', 'cta_primary_override', 'cta_secondary_override',
		'cta_primary_action', 'cta_primary_url', 'cta_secondary_action', 'cta_secondary_url',
		'icon_enabled', 'icon_slug', 'icon_position', 'icon_size', 'icon_color',
	];
	$section = ['section_type' => 'hero'];
	foreach ($keys as $key) {
		if (array_key_exists($key, $settings)) {
			$section[ $key ] = $settings[ $key ];
		}
	}
	return $section;
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
	$section = lf_sections_hero_block_section($settings);
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
			if ($r <= 1) {
				continue;
			}
			$ratings_total += $r;
			$ratings_count++;
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
	$layout = sanitize_key((string) ($settings['trust_bar_layout'] ?? 'brand_band'));
	$layout_allowed = ['brand_band', 'split', 'grid', 'minimal_strip', 'classic'];
	if (!in_array($layout, $layout_allowed, true)) {
		$layout = 'brand_band';
	}
	$bg = (string) ($settings['section_background'] ?? 'dark');
	$shell_extra = 'lf-trust-bar-section lf-trust-bar-section--layout-' . $layout;
	lf_sections_render_shell_open('trust-bar', $title, '', $bg, $settings, $shell_extra);
	?>
	<div class="lf-trust-bar lf-trust-bar--layout-<?php echo esc_attr($layout); ?>">
		<div class="lf-trust-bar__panel">
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
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_benefits(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$intro_secondary = trim((string) ($settings['section_intro_secondary'] ?? ''));
	$intro_text = $intro_secondary !== '' ? trim((string) $intro . "\n\n" . $intro_secondary) : (string) $intro;
	$items = lf_sections_parse_lines((string) ($settings['benefits_items'] ?? ''));
	$card_class = 'lf-benefits__card';
	$layout = (string) ($settings['benefits_layout'] ?? 'cards');
	if (!in_array($layout, ['cards', 'cards_points', 'split'], true)) {
		$layout = 'cards';
	}
	$title_limit = max(3, min(8, (int) ($settings['benefits_title_word_limit'] ?? 5)));
	$body_limit = max(8, min(20, (int) ($settings['benefits_body_word_limit'] ?? 14)));
	$default_items = lf_sections_benefits_default_items();
	$icon_overrides = lf_sections_parse_lines((string) ($settings['benefits_icon_overrides'] ?? ''));
	$supporting_points = lf_sections_parse_lines((string) ($settings['benefits_supporting_points'] ?? ''));
	$trust_block = trim((string) ($settings['benefits_trust_block'] ?? ''));
	$cta_text = trim((string) ($settings['benefits_cta_text'] ?? ''));
	$cta_action = (string) ($settings['benefits_cta_action'] ?? 'quote');
	if (!in_array($cta_action, ['quote', 'link'], true)) {
		$cta_action = 'quote';
	}
	$cta_url = trim((string) ($settings['benefits_cta_url'] ?? ''));
	if ($cta_action === 'link' && $cta_url === '') {
		$cta_action = 'quote';
	}
	$icon_data = function_exists('lf_section_icon_data') ? lf_section_icon_data($settings, 'benefits') : ['enabled' => false];
	$icon_enabled = true;
	$icon_size = $icon_data['size'] ?? 'md';
	$icon_color = $icon_data['color'] ?? 'primary';
	$parsed_items = [];
	foreach ($items as $raw_item) {
		$item = (string) $raw_item;
		if (strpos($item, '&lt;') !== false || strpos($item, '&#60;') !== false) {
			$item = html_entity_decode($item, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		if (function_exists('lf_ai_sanitize_benefits_items_line')) {
			$item = lf_ai_sanitize_benefits_items_line($item);
		}
		$title_text = $item;
		$body_text = '';
		if (strpos($item, '||') !== false) {
			$parts = array_map('trim', explode('||', $item, 2));
			$title_text = $parts[0] ?? $item;
			$body_text = $parts[1] ?? '';
		} elseif (strpos($item, ' | ') !== false) {
			$parts = array_map('trim', explode(' | ', $item, 2));
			$title_text = $parts[0] ?? $item;
			$body_text = $parts[1] ?? '';
		} elseif (strpos($item, ' - ') !== false) {
			$parts = array_map('trim', explode(' - ', $item, 2));
			$title_text = $parts[0] ?? $item;
			$body_text = $parts[1] ?? '';
		} elseif (strpos($item, ' — ') !== false) {
			$parts = array_map('trim', explode(' — ', $item, 2));
			$title_text = $parts[0] ?? $item;
			$body_text = $parts[1] ?? '';
		}
		$title_text = trim($title_text);
		$body_text = trim($body_text);
		$trim_unless_html = static function ( string $text, int $limit ): string {
			$text = trim( $text );
			if ( $text === '' ) {
				return '';
			}
			if ( preg_match( '/<[a-z][^>]*>/i', $text ) ) {
				return $text;
			}
			return wp_trim_words( $text, $limit, '' );
		};
		$title_text = $trim_unless_html( $title_text, $title_limit );
		$body_text  = $trim_unless_html( $body_text, $body_limit );
		$parsed_items[] = [
			'title' => $title_text,
			'body' => $body_text,
			'source_line' => trim((string) $raw_item),
		];
	}
	if (count($parsed_items) > 3) {
		$parsed_items = array_slice($parsed_items, 0, 3);
	}
	while (count($parsed_items) < 3) {
		$parsed_items[] = ['title' => '', 'body' => '', 'source_line' => ''];
	}
	$used_icons = [];
	lf_sections_render_shell_open('benefits', $title, $intro_text, $settings['section_background'] ?? 'light', $settings);
	?>
	<div
		class="lf-benefits lf-benefits--<?php echo esc_attr($layout); ?>"
		style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:var(--lf-space-section);"
	>
		<?php foreach ($parsed_items as $index => $item) : ?>
				<?php if ($item['title'] === '' && isset($default_items[$index]['title'])) : ?>
					<?php $item['title'] = (string) $default_items[$index]['title']; ?>
				<?php endif; ?>
				<?php if ($item['body'] === '') : ?>
					<?php $item['body'] = lf_sections_benefits_supporting_text($item['title'], $index, $default_items); ?>
				<?php endif; ?>
				<?php
				$trim_unless_html2 = static function ( string $text, int $limit ): string {
					$text = trim( $text );
					if ( $text === '' ) {
						return '';
					}
					if ( preg_match( '/<[a-z][^>]*>/i', $text ) ) {
						return $text;
					}
					return wp_trim_words( $text, $limit, '' );
				};
				$item['title'] = $trim_unless_html2( (string) $item['title'], $title_limit );
				$item['body']  = $trim_unless_html2( (string) $item['body'], $body_limit );
				?>
				<?php
				$line_attr = (string) ($item['source_line'] ?? '');
				if ($line_attr === '' && ($item['title'] !== '' || $item['body'] !== '')) {
					$line_attr = trim($item['title'] . ' || ' . $item['body']);
				}
				?>
			<div class="<?php echo esc_attr($card_class); ?>" data-lf-benefit-line="<?php echo esc_attr($line_attr); ?>">
					<?php if ($icon_enabled) : ?>
						<?php $icon_slug = lf_sections_benefits_pick_icon_slug($item, $icon_overrides, $used_icons, $index); ?>
						<?php if ($icon_slug !== '' && function_exists('lf_icon')) : ?>
							<?php $used_icons[] = $icon_slug; ?>
							<span class="lf-benefits__icon" aria-hidden="true" data-lf-benefit-icon-index="<?php echo esc_attr((string) $index); ?>">
								<?php echo lf_icon($icon_slug, ['class' => trim('lf-icon--' . $icon_size . ' lf-icon--' . $icon_color)]); ?>
							</span>
						<?php endif; ?>
					<?php endif; ?>
					<h3 class="lf-benefits__title"><?php echo wp_kses( (string) $item['title'], function_exists( 'lf_ai_inline_link_allowed_kses' ) ? lf_ai_inline_link_allowed_kses() : [] ); ?></h3>
					<p class="lf-benefits__desc"><?php echo wp_kses( (string) $item['body'], function_exists( 'lf_ai_inline_link_allowed_kses' ) ? lf_ai_inline_link_allowed_kses() : [] ); ?></p>
			</div>
		<?php endforeach; ?>
		<?php if ($layout !== 'cards' && !empty($supporting_points)) : ?>
			<ul class="lf-benefits__points" role="list">
				<?php foreach ($supporting_points as $pt) : ?>
					<li class="lf-benefits__point"><?php echo esc_html($pt); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ($cta_text !== '') : ?>
			<div class="lf-benefits__actions">
				<?php if ($cta_action === 'link' && $cta_url !== '') : ?>
					<a class="lf-btn lf-btn--primary" href="<?php echo esc_url($cta_url); ?>"><?php echo esc_html($cta_text); ?></a>
				<?php else : ?>
					<button type="button" class="lf-btn lf-btn--primary" data-lf-quote-trigger="1" data-lf-quote-source="benefits"><?php echo esc_html($cta_text); ?></button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_benefits_default_items(): array {
	return [
		[
			'title' => __('Fast response windows', 'leadsforward-core'),
			'body' => __('Clear arrival times and quick follow-up after you reach out.', 'leadsforward-core'),
		],
		[
			'title' => __('Licensed, insured professionals', 'leadsforward-core'),
			'body' => __('Fully vetted team backed by proper coverage and local reviews.', 'leadsforward-core'),
		],
		[
			'title' => __('Upfront pricing before work starts', 'leadsforward-core'),
			'body' => __('Detailed estimates so you always know the next step.', 'leadsforward-core'),
		],
	];
}

function lf_sections_benefits_supporting_text(string $title, int $index, array $defaults = []): string {
	$title_lower = strtolower($title);
	$keyword_map = [
		'price' => __('Upfront estimates and transparent pricing with no surprises.', 'leadsforward-core'),
		'pricing' => __('Upfront estimates and transparent pricing with no surprises.', 'leadsforward-core'),
		'transparent' => __('Upfront estimates and transparent pricing with no surprises.', 'leadsforward-core'),
		'licensed' => __('Fully licensed, insured, and backed by local reviews.', 'leadsforward-core'),
		'insured' => __('Fully licensed, insured, and backed by local reviews.', 'leadsforward-core'),
		'response' => __('Fast scheduling with clear arrival windows and updates.', 'leadsforward-core'),
		'fast' => __('Fast scheduling with clear arrival windows and updates.', 'leadsforward-core'),
		'management' => __('Dedicated project manager and daily progress updates.', 'leadsforward-core'),
		'quality' => __('Premium materials and craftsmanship you can see and feel.', 'leadsforward-core'),
		'craft' => __('Premium materials and craftsmanship you can see and feel.', 'leadsforward-core'),
		'warranty' => __('Backed by workmanship guarantees and reliable service.', 'leadsforward-core'),
	];
	foreach ($keyword_map as $keyword => $copy) {
		if ($title_lower !== '' && strpos($title_lower, $keyword) !== false) {
			return $copy;
		}
	}
	if (isset($defaults[$index]['body'])) {
		return (string) $defaults[$index]['body'];
	}
	return __('Clear communication, consistent quality, and a stress-free experience.', 'leadsforward-core');
}

function lf_sections_benefits_pick_icon_slug(array $item, array $overrides, array $used_icons, int $index): string {
	$available = function_exists('lf_icon_list') ? lf_icon_list() : [];
	$override = $overrides[$index] ?? '';
	if (function_exists('lf_icon_normalize_slug')) {
		$override = lf_icon_normalize_slug($override);
	}
	if ($override !== '' && in_array($override, $available, true) && !in_array($override, $used_icons, true)) {
		return $override;
	}
	$active_pack = function_exists('lf_icon_active_pack') ? lf_icon_active_pack() : 'general';
	$pack_icons = function_exists('lf_icon_pack_section_icons') ? lf_icon_pack_section_icons('benefits', $active_pack) : [];
	$pool = array_values(array_filter(array_unique(array_merge($pack_icons, $available))));
	$pool = array_values(array_diff($pool, $used_icons));
	$text = trim(($item['title'] ?? '') . ' ' . ($item['body'] ?? ''));
	if (function_exists('lf_icon_slug_for_text')) {
		$slug = lf_icon_slug_for_text($text, $pool);
		if ($slug !== '' && !in_array($slug, $used_icons, true)) {
			return $slug;
		}
	}
	if (!empty($pool)) {
		return $pool[$index % count($pool)];
	}
	return '';
}

function lf_sections_render_service_details(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$body = $settings['service_details_body'] ?? '';
	$body_from_settings = $body !== '';
	if ($body_from_settings) {
		$body = wpautop($body);
	}
	$checklist = lf_sections_parse_lines((string) ($settings['service_details_checklist'] ?? ''));
	$checklist_secondary = lf_sections_parse_lines((string) ($settings['service_details_checklist_secondary'] ?? ''));
	$proof_label = trim((string) ($settings['service_details_proof_label'] ?? ''));
	$proof_badges = lf_sections_parse_lines((string) ($settings['service_details_proof_badges'] ?? ''));
	$checklist_class = 'lf-service-details__checklist';
	$media_mode = (string) ($settings['service_details_media_mode'] ?? 'video');
	if (!in_array($media_mode, ['video', 'image', 'none'], true)) {
		$media_mode = 'video';
	}
	$layout = (string) ($settings['service_details_layout'] ?? 'content_media');
	if (!in_array($layout, ['content_media', 'media_content'], true)) {
		$layout = 'content_media';
	}
	$instance_id = (string) ($settings['_section_instance_id'] ?? '');
	if ($context === 'homepage' && $instance_id === 'service_details__2') {
		$layout = 'media_content';
	}
	$media_embed = trim((string) ($settings['service_details_media_embed'] ?? ''));
	$media_video_url = trim((string) ($settings['service_details_media_video_url'] ?? ''));
	$media_image_id = isset($settings['service_details_media_image_id']) ? (int) $settings['service_details_media_image_id'] : 0;
	if ($media_image_id === 0 && function_exists('lf_get_placeholder_image_id')) {
		$media_image_id = lf_get_placeholder_image_id();
	}
	$show_media = $media_mode !== 'none' && ($media_embed !== '' || $media_video_url !== '' || $media_image_id);
	$render_header_in_content = $show_media;
	$embed_html = '';
	if ($media_mode === 'video' && $media_embed !== '') {
		$allowed = wp_kses_allowed_html('post');
		$allowed['iframe'] = [
			'src' => true,
			'width' => true,
			'height' => true,
			'frameborder' => true,
			'allow' => true,
			'allowfullscreen' => true,
			'title' => true,
			'loading' => true,
			'referrerpolicy' => true,
		];
		$embed_html = wp_kses($media_embed, $allowed);
	}
	$video_url = $media_mode === 'video' ? esc_url($media_video_url) : '';
	$video_is_self_hosted = $video_url !== '' && preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $video_url);
	if ($media_mode === 'video' && $embed_html === '' && $video_url !== '' && !$video_is_self_hosted && function_exists('wp_oembed_get')) {
		$oembed = wp_oembed_get($video_url);
		if ($oembed) {
			$embed_html = $oembed;
		}
	}
	lf_sections_render_shell_open(
		'service-details',
		$render_header_in_content ? '' : $title,
		$render_header_in_content ? '' : $intro,
		$settings['section_background'] ?? 'light',
		$settings
	);
	?>
	<div class="lf-service-details<?php echo $show_media ? ' lf-service-details--media' : ''; ?><?php echo $layout === 'media_content' ? ' lf-service-details--media-left' : ''; ?>">
		<?php if ($show_media && $layout === 'media_content') : ?>
			<div class="lf-service-details__media">
				<?php if ($media_mode === 'video' && $embed_html !== '') : ?>
					<div class="lf-service-details__media-embed"><?php echo $embed_html; ?></div>
				<?php elseif ($media_mode === 'video' && $video_is_self_hosted && $video_url !== '') : ?>
					<video class="lf-service-details__media-video" controls preload="metadata"<?php echo $media_image_id ? ' poster="' . esc_url(wp_get_attachment_image_url($media_image_id, 'large')) . '"' : ''; ?>>
						<source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
					</video>
				<?php elseif ($media_mode === 'image' && $media_image_id) : ?>
					<?php
					echo wp_get_attachment_image(
						$media_image_id,
						'large',
						false,
						[
							'class' => 'lf-service-details__media-image',
							'loading' => 'lazy',
							'decoding' => 'async',
						]
					);
					?>
				<?php elseif ($media_image_id) : ?>
					<div class="lf-service-details__media-placeholder">
						<?php
						echo wp_get_attachment_image(
							$media_image_id,
							'large',
							false,
							[
								'class' => 'lf-service-details__media-image',
								'loading' => 'lazy',
								'decoding' => 'async',
							]
						);
						?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="lf-service-details__content">
			<?php if ($render_header_in_content && ($title || $intro)) : ?>
				<div class="lf-service-details__header">
					<?php if ($title) : ?>
						<h2 class="lf-section__title"><?php echo esc_html($title); ?></h2>
					<?php endif; ?>
					<?php if ($intro) : ?>
						<p class="lf-section__intro"><?php echo esc_html($intro); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ($body) : ?>
				<div class="lf-service-details__body lf-prose"><?php echo wp_kses_post($body); ?></div>
			<?php endif; ?>
			<?php if (!empty($checklist)) : ?>
				<div class="lf-service-details__checklists">
					<ul class="<?php echo esc_attr($checklist_class); ?>" role="list">
						<?php foreach ($checklist as $item) : ?>
							<li>
								<span class="lf-service-details__text"><?php echo wp_kses( (string) $item, function_exists( 'lf_ai_inline_link_allowed_kses' ) ? lf_ai_inline_link_allowed_kses() : [] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if (!empty($checklist_secondary)) : ?>
						<ul class="<?php echo esc_attr($checklist_class); ?> lf-service-details__checklist--secondary" role="list">
							<?php foreach ($checklist_secondary as $item2) : ?>
								<li>
									<span class="lf-service-details__text"><?php echo wp_kses( (string) $item2, function_exists( 'lf_ai_inline_link_allowed_kses' ) ? lf_ai_inline_link_allowed_kses() : [] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if (!empty($proof_badges)) : ?>
				<div class="lf-service-details__proof" role="note" aria-label="<?php echo esc_attr($proof_label !== '' ? $proof_label : __('Also included', 'leadsforward-core')); ?>">
					<?php if ($proof_label !== '') : ?>
						<span class="lf-service-details__proof-label"><?php echo esc_html($proof_label); ?></span>
					<?php endif; ?>
					<div class="lf-service-details__proof-badges">
						<?php foreach ($proof_badges as $badge) : ?>
							<span class="lf-service-details__proof-badge"><?php echo esc_html($badge); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php if ($show_media && $layout !== 'media_content') : ?>
			<div class="lf-service-details__media">
				<?php if ($media_mode === 'video' && $embed_html !== '') : ?>
					<div class="lf-service-details__media-embed"><?php echo $embed_html; ?></div>
				<?php elseif ($media_mode === 'video' && $video_is_self_hosted && $video_url !== '') : ?>
					<video class="lf-service-details__media-video" controls preload="metadata"<?php echo $media_image_id ? ' poster="' . esc_url(wp_get_attachment_image_url($media_image_id, 'large')) . '"' : ''; ?>>
						<source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
					</video>
				<?php elseif ($media_mode === 'image' && $media_image_id) : ?>
					<?php
					echo wp_get_attachment_image(
						$media_image_id,
						'large',
						false,
						[
							'class' => 'lf-service-details__media-image',
							'loading' => 'lazy',
							'decoding' => 'async',
						]
					);
					?>
				<?php elseif ($media_image_id) : ?>
					<div class="lf-service-details__media-placeholder">
						<?php
						echo wp_get_attachment_image(
							$media_image_id,
							'large',
							false,
							[
								'class' => 'lf-service-details__media-image',
								'loading' => 'lazy',
								'decoding' => 'async',
							]
						);
						?>
					</div>
				<?php endif; ?>
			</div>
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
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$body = $settings['section_body'] ?? '';
	$body_secondary = $settings['section_body_secondary'] ?? '';
	$layout = (string) ($settings['content_layout'] ?? 'single');
	if (!in_array($layout, ['single', 'two_col'], true)) {
		$layout = 'single';
	}
	$body_left = $settings['section_body_left'] ?? '';
	$body_right = $settings['section_body_right'] ?? '';
	if ($title === '' && $intro === '' && $body === '' && $body_secondary === '') {
		return;
	}
	lf_sections_render_shell_open('content', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	?>
	<div class="lf-content lf-content--<?php echo esc_attr($layout); ?>">
		<?php if ($layout === 'two_col' && ($body_left !== '' || $body_right !== '')) : ?>
			<div class="lf-content__cols">
				<?php if ($body_left !== '') : ?>
					<div class="lf-content__col lf-content__col--left lf-prose"><?php echo wp_kses_post(wpautop((string) $body_left)); ?></div>
				<?php endif; ?>
				<?php if ($body_right !== '') : ?>
					<div class="lf-content__col lf-content__col--right lf-prose"><?php echo wp_kses_post(wpautop((string) $body_right)); ?></div>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php if ($body !== '') : ?>
				<div class="lf-content__body lf-prose"><?php echo wp_kses_post(wpautop((string) $body)); ?></div>
			<?php endif; ?>
			<?php if ($body_secondary !== '') : ?>
				<div class="lf-content__body-secondary lf-prose"><?php echo wp_kses_post(wpautop((string) $body_secondary)); ?></div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_content_centered(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$subheading = $settings['optional_subheading'] ?? '';
	$body = $settings['supporting_text'] ?? '';
	lf_sections_render_shell_open('content-centered', $title, $subheading, $settings['section_background'] ?? 'light', $settings);
	if ($body !== '') {
		echo '<div class="lf-content-centered__body">' . wpautop(wp_kses_post((string) $body)) . '</div>';
	}
	lf_sections_render_shell_close();
}
function lf_sections_render_process(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$intro_secondary = $settings['section_intro_secondary'] ?? '';
	$steps = lf_sections_process_steps_for_render($settings, $post);
	$expectations = trim((string) ($settings['process_expectations'] ?? ''));
	$process_class = 'lf-process';
	$intro_text = $intro_secondary !== '' ? trim($intro . "\n\n" . $intro_secondary) : $intro;
	$expectations_text = '';
	if ($expectations !== '') {
		$expectations_text = wp_strip_all_tags($expectations);
		$expectations_text = preg_replace('/^[\s\-\*•]+/m', '', $expectations_text);
		$expectations_text = preg_replace('/\s+/', ' ', (string) $expectations_text);
		$parts = preg_split('/[.!?]+\s*/', trim((string) $expectations_text));
		$first = '';
		if (is_array($parts)) {
			foreach ($parts as $part) {
				if (trim($part) !== '') {
					$first = trim($part);
					break;
				}
			}
		}
		if ($first !== '') {
			$expectations_text = rtrim($first, '.!?') . '.';
		}
	}
	lf_sections_render_shell_open('process', $title, $intro_text, $settings['section_background'] ?? 'light', $settings);
	?>
	<ol class="<?php echo esc_attr($process_class); ?>">
		<?php foreach ($steps as $step) : ?>
			<?php
			$step_id = isset($step['id']) ? (int) $step['id'] : 0;
			$step_title = wp_trim_words((string) ($step['title'] ?? ''), 6, '');
			// Keep descriptions short so they don't get visually cut off in the UI.
			$step_body = wp_trim_words((string) ($step['body'] ?? ''), 12, '');
			?>
			<li class="lf-process__step" <?php echo $step_id > 0 ? 'data-lf-process-id="' . esc_attr((string) $step_id) . '"' : ''; ?>>
				<?php if ($step_body !== '') : ?>
					<span class="lf-process__step-title"><strong><?php echo esc_html(rtrim($step_title, ':')); ?>:</strong></span>
					<span class="lf-process__step-body"><?php echo esc_html($step_body); ?></span>
				<?php else : ?>
					<span class="lf-process__text"><?php echo esc_html($step_title); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php if ($expectations_text !== '') : ?>
		<p class="lf-process__expectations"><?php echo esc_html($expectations_text); ?></p>
	<?php endif; ?>
	<?php
	lf_sections_render_shell_close();
}

function lf_sections_render_faq(string $context, array $settings, \WP_Post $post): void {
	if (function_exists('lf_render_block_template')) {
		$intro_secondary = trim((string) ($settings['section_intro_secondary'] ?? ''));
		$intro_text = $intro_secondary !== '' ? trim((string) ($settings['section_intro'] ?? '') . "\n\n" . $intro_secondary) : (string) ($settings['section_intro'] ?? '');
		$section = [
			'section_heading' => $settings['section_heading'] ?? '',
			'section_intro' => $intro_text,
			'faq_columns' => $settings['faq_columns'] ?? '1',
			'faq_schema_enabled' => $settings['faq_schema_enabled'] ?? '1',
			'faq_max_items' => $settings['faq_max_items'] ?? '',
			'faq_selected_ids' => $settings['faq_selected_ids'] ?? '',
			'section_background' => $settings['section_background'] ?? 'light',
			'section_background_custom' => $settings['section_background_custom'] ?? '',
			'section_header_align' => $settings['section_header_align'] ?? 'center',
			'icon_enabled' => $settings['icon_enabled'] ?? '0',
			'icon_slug' => $settings['icon_slug'] ?? '',
			'icon_position' => $settings['icon_position'] ?? 'left',
			'icon_size' => $settings['icon_size'] ?? 'md',
			'icon_color' => $settings['icon_color'] ?? 'primary',
		];
		$block = [
			'id'         => 'lf-faq',
			'variant'    => 'default',
			'attributes' => [
				'variant' => 'default',
				'layout' => 'default',
				'columns' => $settings['faq_columns'] ?? '1',
				'schema' => $settings['faq_schema_enabled'] ?? '1',
			],
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
			'cta_trust_strip_enabled' => $settings['cta_trust_strip_enabled'] ?? '0',
			'cta_trust_rating' => $settings['cta_trust_rating'] ?? '',
			'cta_trust_review_count' => $settings['cta_trust_review_count'] ?? '',
			'cta_trust_badges' => $settings['cta_trust_badges'] ?? '',
			'section_background' => $settings['section_background'] ?? 'dark',
			'section_background_custom' => $settings['section_background_custom'] ?? '',
			'section_header_align' => $settings['section_header_align'] ?? 'center',
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

function lf_sections_logo_strip_resolve_logo_token(string $token): int {
	$token = trim($token);
	if ($token === '') {
		return 0;
	}
	if (ctype_digit($token)) {
		$id = (int) $token;
		return $id > 0 ? $id : 0;
	}
	if (filter_var($token, FILTER_VALIDATE_URL) && function_exists('attachment_url_to_postid')) {
		$from_url = (int) attachment_url_to_postid($token);
		if ($from_url > 0) {
			return $from_url;
		}
	}
	$slug = sanitize_title($token);
	if ($slug !== '') {
		$slug_match = get_posts([
			'post_type' => 'attachment',
			'name' => $slug,
			'post_status' => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => true,
		]);
		if (!empty($slug_match[0])) {
			return (int) $slug_match[0];
		}
	}
	$title_match = get_posts([
		'post_type' => 'attachment',
		'post_status' => 'inherit',
		'post_mime_type' => 'image',
		's' => $token,
		'posts_per_page' => 1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	if (!empty($title_match[0])) {
		return (int) $title_match[0];
	}
	return 0;
}

function lf_sections_logo_strip_resolve_logo_ids(string $raw, int $max = 10): array {
	$max = max(3, min(24, $max));
	$tokens = preg_split('/[\r\n,]+/', $raw);
	$tokens = is_array($tokens) ? $tokens : [];
	$ids = [];
	foreach ($tokens as $token) {
		$resolved = lf_sections_logo_strip_resolve_logo_token((string) $token);
		if ($resolved > 0) {
			$ids[] = $resolved;
		}
	}
	$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
	if (count($ids) > $max) {
		$ids = array_slice($ids, 0, $max);
	}
	if (!empty($ids)) {
		return $ids;
	}
	$fallback = get_posts([
		'post_type' => 'attachment',
		'post_status' => 'inherit',
		'post_mime_type' => 'image',
		'posts_per_page' => $max,
		'fields' => 'ids',
		'orderby' => 'date',
		'order' => 'DESC',
		'no_found_rows' => true,
	]);
	return array_values(array_filter(array_map('intval', is_array($fallback) ? $fallback : [])));
}

function lf_sections_render_trust_reviews(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$section = [
		'trust_heading' => $settings['trust_heading'] ?? '',
		'trust_max_items' => $settings['trust_max_items'] ?? '',
		'trust_layout' => $settings['trust_layout'] ?? 'grid',
		'trust_columns' => $settings['trust_columns'] ?? '3',
		'trust_show_summary' => $settings['trust_show_summary'] ?? '1',
		'trust_show_stars' => $settings['trust_show_stars'] ?? '1',
		'trust_show_source' => $settings['trust_show_source'] ?? '1',
		'trust_show_avatars' => $settings['trust_show_avatars'] ?? '1',
		'trust_show_quote_icon' => $settings['trust_show_quote_icon'] ?? '1',
		'section_background' => $settings['section_background'] ?? 'soft',
		'section_background_custom' => $settings['section_background_custom'] ?? '',
		'section_header_align' => $settings['section_header_align'] ?? 'center',
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
		'section_background_custom' => $settings['section_background_custom'] ?? '',
		'section_header_align' => $settings['section_header_align'] ?? 'center',
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

function lf_sections_render_logo_strip(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$max = isset($settings['logo_strip_max']) ? (int) $settings['logo_strip_max'] : 10;
	$logo_ids = lf_sections_logo_strip_resolve_logo_ids((string) ($settings['logo_strip_logos'] ?? ''), $max);
	$section = [
		'section_heading' => $settings['section_heading'] ?? '',
		'section_intro' => $settings['section_intro'] ?? '',
		'logo_strip_logos' => implode("\n", $logo_ids),
		'logo_strip_max' => (string) $max,
		'section_background' => $settings['section_background'] ?? 'light',
		'section_background_custom' => $settings['section_background_custom'] ?? '',
		'section_header_align' => $settings['section_header_align'] ?? 'center',
	];
	$block = [
		'id'         => 'lf-logo-strip',
		'variant'    => 'default',
		'attributes' => ['variant' => 'default'],
		'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
	];
	lf_render_block_template('logo-strip', $block, false, $block['context']);
}

function lf_sections_render_team(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$section = [
		'section_heading' => $settings['section_heading'] ?? '',
		'section_intro' => $settings['section_intro'] ?? '',
		'team_columns' => $settings['team_columns'] ?? '3',
		'team_members' => $settings['team_members'] ?? '',
		'section_background' => $settings['section_background'] ?? 'light',
		'section_background_custom' => $settings['section_background_custom'] ?? '',
		'section_header_align' => $settings['section_header_align'] ?? 'center',
	];
	$block = [
		'id'         => 'lf-team',
		'variant'    => 'default',
		'attributes' => ['variant' => 'default'],
		'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
	];
	lf_render_block_template('team', $block, false, $block['context']);
}

function lf_sections_render_pricing(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$section = [
		'section_heading' => $settings['section_heading'] ?? '',
		'section_intro' => $settings['section_intro'] ?? '',
		'pricing_factors' => $settings['pricing_factors'] ?? '',
		'financing_enabled' => $settings['financing_enabled'] ?? '0',
		'financing_text' => $settings['financing_text'] ?? '',
		'pricing_cta_text' => $settings['pricing_cta_text'] ?? '',
		'pricing_cta_action' => $settings['pricing_cta_action'] ?? 'quote',
		'pricing_cta_url' => $settings['pricing_cta_url'] ?? '',
		'section_background' => $settings['section_background'] ?? 'light',
		'section_background_custom' => $settings['section_background_custom'] ?? '',
		'section_header_align' => $settings['section_header_align'] ?? 'center',
	];
	$block = [
		'id'         => 'lf-pricing',
		'variant'    => 'default',
		'attributes' => ['variant' => 'default'],
		'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
	];
	lf_render_block_template('pricing', $block, false, $block['context']);
}

function lf_sections_render_packages(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$section = [
		'section_heading' => $settings['section_heading'] ?? '',
		'section_intro' => $settings['section_intro'] ?? '',
		'package_cards' => $settings['package_cards'] ?? '',
		'section_background' => $settings['section_background'] ?? 'light',
		'section_background_custom' => $settings['section_background_custom'] ?? '',
		'section_header_align' => $settings['section_header_align'] ?? 'center',
	];
	$block = [
		'id'         => 'lf-packages',
		'variant'    => 'default',
		'attributes' => ['variant' => 'default'],
		'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
	];
	lf_render_block_template('packages', $block, false, $block['context']);
}

function lf_sections_render_service_intro(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$section = [
		'section_heading' => $settings['section_heading'] ?? '',
		'section_intro' => $settings['section_intro'] ?? '',
		'section_background' => $settings['section_background'] ?? 'light',
		'section_background_custom' => $settings['section_background_custom'] ?? '',
		'service_intro_columns' => $settings['service_intro_columns'] ?? '3',
		'service_intro_max_items' => $settings['service_intro_max_items'] ?? '6',
		'service_intro_show_images' => $settings['service_intro_show_images'] ?? '1',
		'service_intro_service_ids' => $settings['service_intro_service_ids'] ?? '',
		'section_header_align' => $settings['section_header_align'] ?? 'center',
		'icon_enabled' => $settings['icon_enabled'] ?? '0',
		'icon_slug' => $settings['icon_slug'] ?? '',
		'icon_position' => $settings['icon_position'] ?? 'left',
		'icon_size' => $settings['icon_size'] ?? 'md',
		'icon_color' => $settings['icon_color'] ?? 'primary',
	];
	$block = [
		'id'         => 'lf-service-intro',
		'variant'    => 'default',
		'attributes' => ['variant' => 'default'],
		'context'    => ['homepage' => ($context === 'homepage'), 'section' => $section],
	];
	lf_render_block_template('service-intro', $block, false, $block['context']);
}

function lf_sections_render_service_areas(string $context, array $settings, \WP_Post $post): void {
	if (!function_exists('lf_render_block_template')) {
		return;
	}
	$section = [
		'section_heading' => $settings['section_heading'] ?? '',
		'section_intro' => $settings['section_intro'] ?? '',
		'map_heading' => $settings['map_heading'] ?? '',
		'map_intro' => $settings['map_intro'] ?? '',
		'search_placeholder' => $settings['search_placeholder'] ?? '',
		'filter_label' => $settings['filter_label'] ?? '',
		'filter_all_label' => $settings['filter_all_label'] ?? '',
		'no_results_text' => $settings['no_results_text'] ?? '',
		'section_background' => $settings['section_background'] ?? 'soft',
		'section_background_custom' => $settings['section_background_custom'] ?? '',
		'section_header_align' => $settings['section_header_align'] ?? 'center',
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
	$count = max(3, min(12, $count));
	$layout = (string) ($settings['posts_layout'] ?? 'grid');
	if (!in_array($layout, ['grid', 'masonry', 'slider'], true)) {
		$layout = 'grid';
	}
	$show_controls = (string) ($settings['posts_slider_controls'] ?? '1') === '1';
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
		$grid_class = 'posts-list lf-blog-grid lf-blog-grid--' . $layout;
		if ($layout === 'slider') {
			echo '<div class="lf-slider" data-lf-slider>';
			if ($show_controls) {
				echo '<button type="button" class="lf-slider__nav lf-slider__prev" data-lf-slider-prev aria-label="' . esc_attr__('Previous items', 'leadsforward-core') . '">‹</button>';
			}
			echo '<div class="' . esc_attr($grid_class) . ' lf-slider__track" data-lf-slider-track>';
		} else {
			echo '<div class="' . esc_attr($grid_class) . '">';
		}
		while ($query->have_posts()) {
			$query->the_post();
			set_query_var('lf_post_card_variant', 'standard');
			get_template_part('templates/parts/content', get_post_type());
		}
		echo '</div>';
		if ($layout === 'slider') {
			if ($show_controls) {
				echo '<button type="button" class="lf-slider__nav lf-slider__next" data-lf-slider-next aria-label="' . esc_attr__('Next items', 'leadsforward-core') . '">›</button>';
			}
			echo '</div>';
		}
		wp_reset_postdata();
	} else {
		echo '<p>' . esc_html__('No posts yet.', 'leadsforward-core') . '</p>';
	}
	lf_sections_render_shell_close();
}

function lf_sections_render_project_gallery(string $context, array $settings, \WP_Post $post): void {
	$title = $settings['section_heading'] ?? '';
	$intro = $settings['section_intro'] ?? '';
	$count = isset($settings['projects_per_page']) ? (int) $settings['projects_per_page'] : 6;
	$count = max(3, min(12, $count));
	$show_filters = (string) ($settings['project_show_filters'] ?? '0') === '1';
	$show_before_after = (string) ($settings['project_show_before_after'] ?? '1') === '1';
	$layout = (string) ($settings['project_layout'] ?? 'grid');
	if (!in_array($layout, ['grid', 'masonry', 'slider'], true)) {
		$layout = 'grid';
	}
	$show_controls = (string) ($settings['project_slider_controls'] ?? '1') === '1';

	lf_sections_render_shell_open('project-gallery', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	if (function_exists('lf_projects_render_gallery')) {
		lf_projects_render_gallery([
			'count' => $count,
			'show_filters' => $show_filters,
			'show_before_after' => $show_before_after,
			'layout' => $layout,
			'show_controls' => $show_controls,
		]);
	} else {
		echo '<p>' . esc_html__('Project gallery is unavailable.', 'leadsforward-core') . '</p>';
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
			$image_id = (int) get_post_thumbnail_id($svc);
			if ($image_id === 0 && function_exists('lf_get_placeholder_image_id')) {
				$image_id = lf_get_placeholder_image_id();
			}
			$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
			$links[] = ['label' => $label, 'url' => get_permalink($svc), 'image' => $image_url];
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
			$image_id = (int) get_post_thumbnail_id($area);
			if ($image_id === 0 && function_exists('lf_get_placeholder_image_id')) {
				$image_id = lf_get_placeholder_image_id();
			}
			$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
			$links[] = ['label' => $label, 'url' => get_permalink($area), 'image' => $image_url];
		}
	}
	if (empty($links)) {
		return;
	}
	lf_sections_render_shell_open('related-links', $title, $intro, $settings['section_background'] ?? 'light', $settings);
	?>
	<ul class="lf-related-links lf-related-links--media" role="list">
		<?php foreach ($links as $link) : ?>
			<?php $bg = is_string($link['image'] ?? '') ? $link['image'] : ''; ?>
			<li class="lf-related-links__item"<?php echo $bg ? ' style="--lf-related-bg: url(' . esc_url($bg) . ');"' : ''; ?>>
				<a class="lf-related-links__link" href="<?php echo esc_url($link['url']); ?>">
					<span class="lf-related-links__overlay" aria-hidden="true"></span>
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
	<ul class="lf-related-links lf-cpt-driven-links" role="list">
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
		'section_background_custom' => $settings['section_background_custom'] ?? '',
		'section_header_align' => $settings['section_header_align'] ?? 'center',
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

add_action('wp_enqueue_scripts', 'lf_sections_enqueue_slider_assets', 6);

function lf_sections_enqueue_slider_assets(): void {
	if (!lf_sections_should_enqueue_slider_assets()) {
		return;
	}
	$script_path = LF_THEME_DIR . '/assets/js/section-sliders.js';
	if (is_readable($script_path)) {
		wp_enqueue_script(
			'lf-section-sliders',
			LF_THEME_URI . '/assets/js/section-sliders.js',
			[],
			(string) filemtime($script_path),
			true
		);
	}
}

function lf_sections_should_enqueue_slider_assets(): bool {
	if (is_admin()) {
		return false;
	}
	if (is_front_page() && function_exists('lf_get_homepage_section_config')) {
		$config = lf_get_homepage_section_config();
		if (is_array($config) && lf_sections_has_slider_in_homepage_config($config)) {
			return true;
		}
	}
	if (is_singular() && defined('LF_PB_META_KEY')) {
		$post_id = get_the_ID();
		if ($post_id) {
			$config = get_post_meta($post_id, LF_PB_META_KEY, true);
			if (is_array($config) && !empty($config['sections']) && lf_sections_has_slider_in_sections($config['sections'])) {
				return true;
			}
		}
	}
	return false;
}

function lf_sections_has_slider_in_homepage_config(array $config): bool {
	$checks = [
		['id' => 'project_gallery', 'key' => 'project_layout'],
		['id' => 'blog_posts', 'key' => 'posts_layout'],
		['id' => 'trust_reviews', 'key' => 'trust_layout'],
	];
	foreach ($checks as $check) {
		$section = $config[$check['id']] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$layout = (string) ($section[$check['key']] ?? 'grid');
		if ($layout === 'slider') {
			return true;
		}
	}
	return false;
}

function lf_sections_has_slider_in_sections(array $sections): bool {
	foreach ($sections as $section) {
		if (!is_array($section)) {
			continue;
		}
		if (isset($section['enabled']) && !$section['enabled']) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		if ($type === 'project_gallery') {
			$layout = (string) ($settings['project_layout'] ?? 'grid');
			if ($layout === 'slider') {
				return true;
			}
		}
		if ($type === 'blog_posts') {
			$layout = (string) ($settings['posts_layout'] ?? 'grid');
			if ($layout === 'slider') {
				return true;
			}
		}
	}
	return false;
}
