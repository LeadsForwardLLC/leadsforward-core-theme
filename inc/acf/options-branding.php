<?php
/**
 * ACF field group: Branding (global colors).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_options_branding_fields');

function lf_acf_add_options_branding_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'   => 'group_lf_options_branding',
		'title' => __('Branding', 'leadsforward-core'),
		'fields' => [
			[
				'key'   => 'field_lf_brand_primary',
				'label' => __('Primary color', 'leadsforward-core'),
				'name'  => 'lf_brand_primary',
				'type'  => 'color_picker',
				'default_value' => '#2563eb',
			],
			[
				'key'   => 'field_lf_brand_secondary',
				'label' => __('Secondary color', 'leadsforward-core'),
				'name'  => 'lf_brand_secondary',
				'type'  => 'color_picker',
				'default_value' => '#0ea5e9',
			],
			[
				'key'   => 'field_lf_brand_tertiary',
				'label' => __('Tertiary color', 'leadsforward-core'),
				'name'  => 'lf_brand_tertiary',
				'type'  => 'color_picker',
				'default_value' => '#f97316',
			],
			[
				'key'   => 'field_lf_surface_light',
				'label' => __('Light background', 'leadsforward-core'),
				'name'  => 'lf_surface_light',
				'type'  => 'color_picker',
				'default_value' => '#ffffff',
			],
			[
				'key'   => 'field_lf_surface_soft',
				'label' => __('Soft background', 'leadsforward-core'),
				'name'  => 'lf_surface_soft',
				'type'  => 'color_picker',
				'default_value' => '#f8fafc',
			],
			[
				'key'   => 'field_lf_surface_dark',
				'label' => __('Dark background', 'leadsforward-core'),
				'name'  => 'lf_surface_dark',
				'type'  => 'color_picker',
				'default_value' => '#0f172a',
			],
			[
				'key'   => 'field_lf_surface_card',
				'label' => __('Card background', 'leadsforward-core'),
				'name'  => 'lf_surface_card',
				'type'  => 'color_picker',
				'default_value' => '#ffffff',
			],
			[
				'key'   => 'field_lf_text_primary',
				'label' => __('Primary text', 'leadsforward-core'),
				'name'  => 'lf_text_primary',
				'type'  => 'color_picker',
				'default_value' => '#0f172a',
			],
			[
				'key'   => 'field_lf_text_muted',
				'label' => __('Muted text', 'leadsforward-core'),
				'name'  => 'lf_text_muted',
				'type'  => 'color_picker',
				'default_value' => '#64748b',
			],
			[
				'key'   => 'field_lf_text_inverse',
				'label' => __('Inverse text', 'leadsforward-core'),
				'name'  => 'lf_text_inverse',
				'type'  => 'color_picker',
				'default_value' => '#ffffff',
			],
			[
				'key'   => 'field_lf_link_hover_color',
				'label' => __('Link hover color', 'leadsforward-core'),
				'name'  => 'lf_link_hover_color',
				'type'  => 'color_picker',
				'default_value' => '#2563eb',
			],
		],
		'location' => [
			[
				[
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'lf-global',
				],
			],
		],
	]);
}
