<?php
/**
 * ACF field group: Project CPT.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_project_fields');

function lf_acf_add_project_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'                   => 'group_lf_project',
		'title'                 => __('Project Details', 'leadsforward-core'),
		'fields'                => [
			[
				'key'   => 'field_lf_project_city',
				'label' => __('City', 'leadsforward-core'),
				'name'  => 'lf_project_city',
				'type'  => 'text',
			],
			[
				'key'   => 'field_lf_project_state',
				'label' => __('State', 'leadsforward-core'),
				'name'  => 'lf_project_state',
				'type'  => 'text',
			],
			[
				'key'   => 'field_lf_project_year',
				'label' => __('Year', 'leadsforward-core'),
				'name'  => 'lf_project_year',
				'type'  => 'text',
			],
			[
				'key'           => 'field_lf_project_before_image',
				'label'         => __('Before image', 'leadsforward-core'),
				'name'          => 'lf_project_before_image',
				'type'          => 'image',
				'return_format' => 'id',
				'preview_size'  => 'medium',
			],
			[
				'key'           => 'field_lf_project_after_image',
				'label'         => __('After image', 'leadsforward-core'),
				'name'          => 'lf_project_after_image',
				'type'          => 'image',
				'return_format' => 'id',
				'preview_size'  => 'medium',
			],
			[
				'key'           => 'field_lf_project_gallery',
				'label'         => __('Project gallery', 'leadsforward-core'),
				'name'          => 'lf_project_gallery',
				'type'          => 'gallery',
				'return_format' => 'id',
				'preview_size'  => 'medium',
			],
		],
		'location'              => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'lf_project',
				],
			],
		],
	]);
}
