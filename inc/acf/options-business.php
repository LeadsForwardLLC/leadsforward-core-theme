<?php
/**
 * ACF field group: Global Business Info (options page).
 * Business Name, Phone, Email, NAP, Geo, Opening hours.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_options_business_fields');

function lf_acf_add_options_business_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'                   => 'group_lf_options_business',
		'title'                 => __('Global Business Info', 'leadsforward-core'),
		'fields'                => [
			[
				'key'   => 'field_lf_business_name',
				'label' => __('Business Name', 'leadsforward-core'),
				'name'  => 'lf_business_name',
				'type'  => 'text',
			],
			[
				'key'   => 'field_lf_business_phone',
				'label' => __('Phone', 'leadsforward-core'),
				'name'  => 'lf_business_phone',
				'type'  => 'text',
			],
			[
				'key'   => 'field_lf_business_email',
				'label' => __('Email', 'leadsforward-core'),
				'name'  => 'lf_business_email',
				'type'  => 'email',
			],
			[
				'key'      => 'field_lf_business_address',
				'label'    => __('Address (NAP)', 'leadsforward-core'),
				'name'     => 'lf_business_address',
				'type'     => 'textarea',
				'rows'     => 3,
				'required' => 0,
			],
			[
				'key'         => 'field_lf_business_geo',
				'label'       => __('Geo coordinates', 'leadsforward-core'),
				'name'        => 'lf_business_geo',
				'type'        => 'group',
				'sub_fields'  => [
					[
						'key'  => 'field_lf_business_geo_lat',
						'label' => __('Latitude', 'leadsforward-core'),
						'name'  => 'lat',
						'type'  => 'number',
						'step'  => 'any',
					],
					[
						'key'  => 'field_lf_business_geo_lng',
						'label' => __('Longitude', 'leadsforward-core'),
						'name'  => 'lng',
						'type'  => 'number',
						'step'  => 'any',
					],
				],
			],
			[
				'key'      => 'field_lf_business_hours',
				'label'    => __('Opening hours', 'leadsforward-core'),
				'name'     => 'lf_business_hours',
				'type'     => 'textarea',
				'rows'     => 4,
				'instructions' => __('e.g. Mon–Fri 8am–6pm', 'leadsforward-core'),
			],
			[
				'key'   => 'field_lf_footer_address_link_auto',
				'label' => __('Footer: auto-link address to GBP URL', 'leadsforward-core'),
				'name'  => 'lf_footer_address_link_auto',
				'type'  => 'true_false',
				'ui'    => 1,
				'default_value' => 1,
				'instructions' => __('When enabled, the footer address links to the Google Business Profile URL (useful for CID links).', 'leadsforward-core'),
			],
			[
				'key'   => 'field_lf_footer_address_link_url',
				'label' => __('Footer: address link URL override', 'leadsforward-core'),
				'name'  => 'lf_footer_address_link_url',
				'type'  => 'url',
				'instructions' => __('Optional. If set, this URL is used instead of the GBP URL.', 'leadsforward-core'),
			],
		],
		'location'              => [
			[
				[
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'lf-business-info',
				],
			],
		],
	]);
}
