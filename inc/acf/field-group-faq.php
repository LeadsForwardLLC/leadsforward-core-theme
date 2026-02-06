<?php
/**
 * ACF field group: FAQs CPT.
 * Question, Answer, Associated service/service area.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_faq_fields');

function lf_acf_add_faq_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'                   => 'group_lf_faq',
		'title'                 => __('FAQ Details', 'leadsforward-core'),
		'fields'                => [
			[
				'key'  => 'field_lf_faq_question',
				'label' => __('Question', 'leadsforward-core'),
				'name'  => 'lf_faq_question',
				'type'  => 'text',
				'instructions' => __('Leave blank to use post title as question.', 'leadsforward-core'),
			],
			[
				'key'   => 'field_lf_faq_answer',
				'label' => __('Answer', 'leadsforward-core'),
				'name'  => 'lf_faq_answer',
				'type'  => 'wysiwyg',
				'instructions' => __('Leave blank to use post content.', 'leadsforward-core'),
				'tabs'  => 'all',
				'toolbar' => 'full',
				'media_upload' => 0,
			],
			[
				'key'         => 'field_lf_faq_associated_service',
				'label'       => __('Associated service', 'leadsforward-core'),
				'name'        => 'lf_faq_associated_service',
				'type'        => 'relationship',
				'post_type'   => ['lf_service'],
				'return_format' => 'id',
				'multiple'    => 0,
			],
			[
				'key'         => 'field_lf_faq_associated_service_area',
				'label'       => __('Associated service area', 'leadsforward-core'),
				'name'        => 'lf_faq_associated_service_area',
				'type'        => 'relationship',
				'post_type'   => ['lf_service_area'],
				'return_format' => 'id',
				'multiple'    => 0,
			],
		],
		'location'              => [
			[
				[
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'lf_faq',
				],
			],
		],
	]);
}
