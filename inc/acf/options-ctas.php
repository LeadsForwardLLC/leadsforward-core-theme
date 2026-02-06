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
