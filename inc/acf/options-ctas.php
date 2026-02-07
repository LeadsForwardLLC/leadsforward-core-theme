<?php
/**
 * ACF field group: Global CTAs (options page).
 * Primary/secondary CTA text, default GHL form embed.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_options_ctas_fields');

function lf_acf_add_options_ctas_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	acf_add_local_field_group([
		'key'                   => 'group_lf_options_ctas',
		'title'                 => __('Global CTAs', 'leadsforward-core'),
		'fields'                => [
			[
				'key'   => 'field_lf_cta_primary_text',
				'label' => __('Primary CTA text', 'leadsforward-core'),
				'name'  => 'lf_cta_primary_text',
				'type'  => 'text',
			],
			[
				'key'     => 'field_lf_cta_primary_type',
				'label'   => __('Primary CTA type', 'leadsforward-core'),
				'name'    => 'lf_cta_primary_type',
				'type'    => 'select',
				'choices' => [
					'text' => __('Text only', 'leadsforward-core'),
					'call' => __('Call (phone link)', 'leadsforward-core'),
					'form' => __('Form / GHL embed', 'leadsforward-core'),
				],
				'default_value' => 'text',
				'instructions'  => __('Call uses Business Info phone; Form shows GHL embed below.', 'leadsforward-core'),
			],
			[
				'key'     => 'field_lf_cta_primary_action',
				'label'   => __('Primary CTA action', 'leadsforward-core'),
				'name'    => 'lf_cta_primary_action',
				'type'    => 'select',
				'choices' => [
					'link'  => __('Link', 'leadsforward-core'),
					'quote' => __('Open Quote Builder', 'leadsforward-core'),
				],
				'default_value' => 'link',
				'instructions'  => __('When set to Quote Builder, the primary CTA opens the modal.', 'leadsforward-core'),
			],
			[
				'key'   => 'field_lf_cta_primary_url',
				'label' => __('Primary CTA URL', 'leadsforward-core'),
				'name'  => 'lf_cta_primary_url',
				'type'  => 'url',
				'instructions' => __('Used when CTA action is Link.', 'leadsforward-core'),
				'conditional_logic' => [['field' => 'field_lf_cta_primary_action', 'operator' => '==', 'value' => 'link']],
			],
			[
				'key'   => 'field_lf_cta_secondary_text',
				'label' => __('Secondary CTA text', 'leadsforward-core'),
				'name'  => 'lf_cta_secondary_text',
				'type'  => 'text',
			],
			[
				'key'         => 'field_lf_cta_ghl_embed',
				'label'       => __('Default GHL form embed', 'leadsforward-core'),
				'name'        => 'lf_cta_ghl_embed',
				'type'        => 'wysiwyg',
				'tabs'        => 'all',
				'toolbar'     => 'full',
				'media_upload' => 0,
				'delay'       => 0,
				'instructions' => __('Paste GHL form embed code (script/iframe).', 'leadsforward-core'),
			],
		],
		'location'              => [
			[
				[
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'lf-ctas',
				],
			],
		],
	]);
}
