<?php
/**
 * ACF field group: Service CPT.
 * SEO H1 (locked), short/long content, CTA override, related service areas.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_service_fields');

function lf_acf_add_service_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'                   => 'group_lf_service',
		'title'                 => __('Service Details', 'leadsforward-core'),
		'fields'                => [
			[
				'key'           => 'field_lf_service_seo_h1',
				'label'         => __('SEO H1', 'leadsforward-core'),
				'name'          => 'lf_service_seo_h1',
				'type'          => 'text',
				'instructions'  => __('Override H1 for this service. Leave blank to use post title.', 'leadsforward-core'),
				'readonly'      => 0,
			],
			[
				'key'   => 'field_lf_service_short_desc',
				'label' => __('Short service description', 'leadsforward-core'),
				'name'  => 'lf_service_short_desc',
				'type'  => 'textarea',
				'rows'  => 3,
			],
			[
				'key'   => 'field_lf_service_long_content',
				'label' => __('Long service content', 'leadsforward-core'),
				'name'  => 'lf_service_long_content',
				'type'  => 'wysiwyg',
				'tabs'  => 'all',
				'toolbar' => 'full',
				'media_upload' => 1,
				'delay' => 0,
			],
			[
				'key'   => 'field_lf_service_cta_override',
				'label' => __('Service-specific CTA override', 'leadsforward-core'),
				'name'  => 'lf_service_cta_override',
				'type'  => 'wysiwyg',
				'instructions' => __('Override global CTA for this service. Leave blank to use global.', 'leadsforward-core'),
				'tabs'  => 'all',
				'toolbar' => 'full',
				'media_upload' => 0,
			],
			[
				'key'       => 'field_lf_service_related_areas',
				'label'     => __('Related service areas', 'leadsforward-core'),
				'name'      => 'lf_service_related_areas',
				'type'      => 'relationship',
				'post_type' => ['lf_service_area'],
				'return_format' => 'id',
				'multiple'  => 1,
			],
		],
		'location'              => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'lf_service',
				],
			],
		],
	]);
}
