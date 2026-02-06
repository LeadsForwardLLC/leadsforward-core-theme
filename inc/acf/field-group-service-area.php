<?php
/**
 * ACF field group: Service Area CPT.
 * City name (post title = locked), State, Geo, Service list, Map embed override.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_service_area_fields');

function lf_acf_add_service_area_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'                   => 'group_lf_service_area',
		'title'                 => __('Service Area Details', 'leadsforward-core'),
		'fields'                => [
			[
				'key'          => 'field_lf_service_area_state',
				'label'        => __('State', 'leadsforward-core'),
				'name'         => 'lf_service_area_state',
				'type'         => 'text',
				'instructions' => __('Post title = city name (locked). Add state here.', 'leadsforward-core'),
			],
			[
				'key'        => 'field_lf_service_area_geo',
				'label'      => __('Geo coordinates', 'leadsforward-core'),
				'name'       => 'lf_service_area_geo',
				'type'       => 'group',
				'sub_fields' => [
					[
						'key'  => 'field_lf_service_area_geo_lat',
						'label' => __('Latitude', 'leadsforward-core'),
						'name'  => 'lat',
						'type'  => 'number',
						'step'  => 'any',
					],
					[
						'key'  => 'field_lf_service_area_geo_lng',
						'label' => __('Longitude', 'leadsforward-core'),
						'name'  => 'lng',
						'type'  => 'number',
						'step'  => 'any',
					],
				],
			],
			[
				'key'         => 'field_lf_service_area_services',
				'label'       => __('Service list', 'leadsforward-core'),
				'name'        => 'lf_service_area_services',
				'type'        => 'relationship',
				'post_type'   => ['lf_service'],
				'return_format' => 'id',
				'multiple'    => 1,
			],
			[
				'key'         => 'field_lf_service_area_map_override',
				'label'       => __('Map embed override', 'leadsforward-core'),
				'name'        => 'lf_service_area_map_override',
				'type'        => 'wysiwyg',
				'instructions' => __('Override default map embed for this area. Leave blank to use global.', 'leadsforward-core'),
				'tabs'        => 'all',
				'toolbar'     => 'full',
				'media_upload' => 0,
			],
		],
		'location'              => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'lf_service_area',
				],
			],
		],
	]);
}
