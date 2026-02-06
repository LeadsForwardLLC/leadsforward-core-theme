<?php
/**
 * ACF field group: Schema controls (options page).
 * On/off toggles for schema types (Organization, LocalBusiness, FAQ, etc.).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_options_schema_fields');

function lf_acf_add_options_schema_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'                   => 'group_lf_options_schema',
		'title'                 => __('Schema Controls', 'leadsforward-core'),
		'fields'                => [
			[
				'key'           => 'field_lf_schema_organization',
				'label'         => __('Organization schema', 'leadsforward-core'),
				'name'          => 'lf_schema_organization',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
			],
			[
				'key'           => 'field_lf_schema_local_business',
				'label'         => __('LocalBusiness schema', 'leadsforward-core'),
				'name'          => 'lf_schema_local_business',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
			],
			[
				'key'           => 'field_lf_schema_faq',
				'label'         => __('FAQ schema', 'leadsforward-core'),
				'name'          => 'lf_schema_faq',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
			],
			[
				'key'           => 'field_lf_schema_review',
				'label'         => __('Review / AggregateRating schema', 'leadsforward-core'),
				'name'          => 'lf_schema_review',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
			],
		],
		'location'              => [
			[
				[
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'lf-schema',
				],
			],
		],
	]);
}
