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
			],
			[
				'key'   => 'field_lf_header_cta_url',
				'label' => __('Header CTA URL', 'leadsforward-core'),
				'name'  => 'lf_header_cta_url',
				'type'  => 'url',
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
