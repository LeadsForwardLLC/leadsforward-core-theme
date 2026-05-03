<?php
/**
 * ACF field group: Global Settings (logo + header CTA).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_options_global_fields');

function lf_acf_add_options_global_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'   => 'group_lf_options_global',
		'title' => __('Global Settings', 'leadsforward-core'),
		'fields' => [
			[
				'key'   => 'field_lf_global_logo',
				'label' => __('Logo', 'leadsforward-core'),
				'name'  => 'lf_global_logo',
				'type'  => 'image',
				'return_format' => 'id',
				'preview_size'  => 'medium',
				'library'       => 'all',
			],
			[
				'key'   => 'field_lf_header_cta_label',
				'label' => __('Header CTA label', 'leadsforward-core'),
				'name'  => 'lf_header_cta_label',
				'type'  => 'text',
				'default_value' => __('Free Estimate', 'leadsforward-core'),
			],
			[
				'key'   => 'field_lf_header_cta_url',
				'label' => __('Header CTA URL', 'leadsforward-core'),
				'name'  => 'lf_header_cta_url',
				'type'  => 'url',
			],
			[
				'key'   => 'field_lf_menu_autobuild_enabled',
				'label' => __('Auto-build primary menu', 'leadsforward-core'),
				'name'  => 'lf_menu_autobuild_enabled',
				'type'  => 'true_false',
				'ui'    => 1,
				'default_value' => 1,
				'instructions' => __('When enabled, the theme will auto-populate the Header Menu with core pages and (optionally) selected Services. This is best for new sites; disable to manage menus manually.', 'leadsforward-core'),
			],
			[
				'key'   => 'field_lf_header_layout',
				'label' => __('Header bar layout', 'leadsforward-core'),
				'name'  => 'lf_header_layout',
				'type'  => 'select',
				'default_value' => 'modern',
				'choices' => [
					'modern' => __('Logo left, links right', 'leadsforward-core'),
					'centered' => __('Centered logo and menu', 'leadsforward-core'),
					'topbar' => __('Promo strip above main bar', 'leadsforward-core'),
				],
			],
			[
				'key'   => 'field_lf_header_nav_width',
				'label' => __('Navigation bar width', 'leadsforward-core'),
				'name'  => 'lf_header_nav_width',
				'type'  => 'select',
				'default_value' => 'contained',
				'choices' => [
					'contained' => __('Contained (rounded capsule)', 'leadsforward-core'),
					'full' => __('Full width (edge-to-edge)', 'leadsforward-core'),
				],
			],
			[
				'key'   => 'field_lf_header_more_mode',
				'label' => __('“More” menu (desktop)', 'leadsforward-core'),
				'name'  => 'lf_header_more_mode',
				'type'  => 'select',
				'default_value' => 'dropdown',
				'choices' => [
					'dropdown' => __('Classic dropdown', 'leadsforward-core'),
					'slideout' => __('Slide-out panel', 'leadsforward-core'),
				],
			],
			[
				'key'   => 'field_lf_menu_autobuild_include_services',
				'label' => __('Menu: include these Services', 'leadsforward-core'),
				'name'  => 'lf_menu_autobuild_include_services',
				'type'  => 'relationship',
				'post_type' => ['lf_service'],
				'filters' => ['search'],
				'return_format' => 'id',
				'conditional_logic' => [
					[
						[
							'field' => 'field_lf_menu_autobuild_enabled',
							'operator' => '==',
							'value' => '1',
						],
					],
				],
			],
			[
				'key'   => 'field_lf_heading_case_mode',
				'label' => __('Global heading case', 'leadsforward-core'),
				'name'  => 'lf_heading_case_mode',
				'type'  => 'select',
				'default_value' => 'normal',
				'choices' => [
					'normal' => __('Normal (as written)', 'leadsforward-core'),
					'capitalize' => __('Title case (capitalize words)', 'leadsforward-core'),
					'upper' => __('UPPERCASE', 'leadsforward-core'),
					'lower' => __('lowercase', 'leadsforward-core'),
				],
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
