<?php
/**
 * ACF field group: Reviews CPT.
 * Reviewer name, Rating, Review text, Source.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_testimonial_fields');

function lf_acf_add_testimonial_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'                   => 'group_lf_testimonial',
		'title'                 => __('Review Details', 'leadsforward-core'),
		'fields'                => [
			[
				'key'   => 'field_lf_testimonial_reviewer_name',
				'label' => __('Reviewer name', 'leadsforward-core'),
				'name'  => 'lf_testimonial_reviewer_name',
				'type'  => 'text',
			],
			[
				'key'           => 'field_lf_testimonial_rating',
				'label'         => __('Rating', 'leadsforward-core'),
				'name'          => 'lf_testimonial_rating',
				'type'          => 'number',
				'min'           => 1,
				'max'           => 5,
				'step'          => 1,
				'default_value' => 5,
			],
			[
				'key'   => 'field_lf_testimonial_review_text',
				'label' => __('Review text', 'leadsforward-core'),
				'name'  => 'lf_testimonial_review_text',
				'type'  => 'textarea',
				'rows'  => 4,
			],
			[
				'key'     => 'field_lf_testimonial_source',
				'label'   => __('Source', 'leadsforward-core'),
				'name'    => 'lf_testimonial_source',
				'type'    => 'select',
				'choices' => [
					'google'   => __('Google', 'leadsforward-core'),
					'facebook'  => __('Facebook', 'leadsforward-core'),
					'yelp'      => __('Yelp', 'leadsforward-core'),
					'other'     => __('Other', 'leadsforward-core'),
				],
			],
		],
		'location'              => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'lf_testimonial',
				],
			],
		],
	]);
}
