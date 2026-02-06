<?php
/**
 * ACF field group: Homepage (options page).
 * Flexible content homepage_sections: section type, variant, overrides.
 * Homepage-level CTA overrides for conversion.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_options_homepage_fields');

function lf_acf_add_options_homepage_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}

	$section_types = [
		'hero'           => __('Hero', 'leadsforward-core'),
		'trust_reviews'  => __('Trust / Reviews', 'leadsforward-core'),
		'service_grid'   => __('Service Grid', 'leadsforward-core'),
		'cta'            => __('CTA', 'leadsforward-core'),
		'faq_accordion'  => __('FAQ Accordion', 'leadsforward-core'),
		'map_nap'        => __('Map + NAP', 'leadsforward-core'),
	];
	$variants = [
		'default' => __('Default', 'leadsforward-core'),
		'a'       => __('Variant A', 'leadsforward-core'),
		'b'       => __('Variant B', 'leadsforward-core'),
		'c'       => __('Variant C', 'leadsforward-core'),
	];

	acf_add_local_field_group([
		'key'   => 'group_lf_options_homepage',
		'title' => __('Homepage', 'leadsforward-core'),
		'fields' => [
			[
				'key'   => 'field_lf_homepage_cta_primary',
				'label' => __('Homepage primary CTA override', 'leadsforward-core'),
				'name'  => 'lf_homepage_cta_primary',
				'type'  => 'text',
				'instructions' => __('Overrides global primary CTA on homepage only. Leave blank to use global.', 'leadsforward-core'),
			],
			[
				'key'   => 'field_lf_homepage_cta_secondary',
				'label' => __('Homepage secondary CTA override', 'leadsforward-core'),
				'name'  => 'lf_homepage_cta_secondary',
				'type'  => 'text',
			],
			[
				'key'         => 'field_lf_homepage_cta_ghl',
				'label'       => __('Homepage GHL form override', 'leadsforward-core'),
				'name'        => 'lf_homepage_cta_ghl',
				'type'        => 'wysiwyg',
				'tabs'        => 'all',
				'toolbar'     => 'full',
				'media_upload' => 0,
				'instructions' => __('Overrides global GHL embed on homepage. Leave blank to use global.', 'leadsforward-core'),
			],
			[
				'key'     => 'field_lf_homepage_cta_primary_type',
				'label'   => __('Homepage primary CTA type', 'leadsforward-core'),
				'name'    => 'lf_homepage_cta_primary_type',
				'type'    => 'select',
				'choices' => [
					''      => __('Use global setting', 'leadsforward-core'),
					'text'  => __('Text only', 'leadsforward-core'),
					'call'  => __('Call (phone link)', 'leadsforward-core'),
					'form'  => __('Form / GHL embed', 'leadsforward-core'),
				],
				'instructions' => __('Overrides global Primary CTA type on homepage only.', 'leadsforward-core'),
			],
			[
				'key'        => 'field_lf_homepage_sections',
				'label'      => __('Homepage sections', 'leadsforward-core'),
				'name'       => 'homepage_sections',
				'type'       => 'flexible_content',
				'layouts'    => [
					'layout_homepage_section' => [
						'key'        => 'layout_homepage_section',
						'name'       => 'homepage_section',
						'label'      => __('Section', 'leadsforward-core'),
						'display'    => 'block',
						'sub_fields' => [
							[
								'key'     => 'field_lf_section_type',
								'label'   => __('Section type', 'leadsforward-core'),
								'name'    => 'section_type',
								'type'    => 'select',
								'choices' => $section_types,
								'required' => 1,
							],
							[
								'key'     => 'field_lf_section_variant',
								'label'   => __('Layout variant', 'leadsforward-core'),
								'name'    => 'layout_variant',
								'type'    => 'select',
								'choices' => $variants,
								'default_value' => 'default',
							],
							// Hero overrides
							[
								'key'           => 'field_lf_section_hero_headline',
								'label'         => __('Hero headline', 'leadsforward-core'),
								'name'          => 'hero_headline',
								'type'          => 'text',
								'placeholder'   => __('e.g. [Service] in [City]', 'leadsforward-core'),
								'conditional_logic' => [['field' => 'field_lf_section_type', 'operator' => '==', 'value' => 'hero']],
							],
							[
								'key'           => 'field_lf_section_hero_subheadline',
								'label'         => __('Hero subheadline', 'leadsforward-core'),
								'name'          => 'hero_subheadline',
								'type'          => 'text',
								'conditional_logic' => [['field' => 'field_lf_section_type', 'operator' => '==', 'value' => 'hero']],
							],
							[
								'key'           => 'field_lf_section_hero_cta',
								'label'         => __('Hero CTA override', 'leadsforward-core'),
								'name'          => 'hero_cta_override',
								'type'          => 'text',
								'conditional_logic' => [['field' => 'field_lf_section_type', 'operator' => '==', 'value' => 'hero']],
							],
							// Trust overrides
							[
								'key'           => 'field_lf_section_trust_max',
								'label'         => __('Max reviews to show', 'leadsforward-core'),
								'name'          => 'trust_max_items',
								'type'          => 'number',
								'min'           => 1,
								'max'           => 10,
								'default_value' => 3,
								'conditional_logic' => [['field' => 'field_lf_section_type', 'operator' => '==', 'value' => 'trust_reviews']],
							],
							// CTA section overrides
							[
								'key'           => 'field_lf_section_cta_primary',
								'label'         => __('Section primary CTA', 'leadsforward-core'),
								'name'          => 'cta_primary_override',
								'type'          => 'text',
								'conditional_logic' => [['field' => 'field_lf_section_type', 'operator' => '==', 'value' => 'cta']],
							],
							[
								'key'           => 'field_lf_section_cta_secondary',
								'label'         => __('Section secondary CTA', 'leadsforward-core'),
								'name'          => 'cta_secondary_override',
								'type'          => 'text',
								'conditional_logic' => [['field' => 'field_lf_section_type', 'operator' => '==', 'value' => 'cta']],
							],
							[
								'key'           => 'field_lf_section_cta_ghl',
								'label'         => __('Section GHL embed override', 'leadsforward-core'),
								'name'          => 'cta_ghl_override',
								'type'          => 'wysiwyg',
								'tabs'          => 'all',
								'toolbar'       => 'full',
								'media_upload'  => 0,
								'conditional_logic' => [['field' => 'field_lf_section_type', 'operator' => '==', 'value' => 'cta']],
							],
						],
					],
				],
				'button_label' => __('Add section', 'leadsforward-core'),
				'instructions' => __('Order sections for the homepage. Leave empty to use conversion-optimized defaults.', 'leadsforward-core'),
			],
		],
		'location' => [
			[
				[
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'lf-homepage',
				],
			],
		],
	]);
}
