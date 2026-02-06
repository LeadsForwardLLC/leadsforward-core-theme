<?php
/**
 * ACF field group: Variation (options page).
 * Site-wide variation profile, auto section order, copy template slots.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', 'lf_acf_add_options_variation_fields');

function lf_acf_add_options_variation_fields(): void {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}

	$profiles = [
		'a' => __('Profile A: Clean + Minimal', 'leadsforward-core'),
		'b' => __('Profile B: Bold + High Contrast', 'leadsforward-core'),
		'c' => __('Profile C: Trust Heavy', 'leadsforward-core'),
		'd' => __('Profile D: Service Heavy', 'leadsforward-core'),
		'e' => __('Profile E: Offer/Promo Heavy', 'leadsforward-core'),
	];

	$hero_copy_style = [
		'default'   => __('Default (business name / welcome)', 'leadsforward-core'),
		'service_city' => __('[Service] in [City]', 'leadsforward-core'),
		'quality_area' => __('Quality [Service] in [Area]', 'leadsforward-core'),
		'local_leader'  => __('[Area]’s trusted [Service]', 'leadsforward-core'),
		'simple_welcome' => __('Welcome to [Business Name]', 'leadsforward-core'),
	];

	$cta_copy_style = [
		'default' => __('Default (use CTA options)', 'leadsforward-core'),
		'call_now' => __('Call Now', 'leadsforward-core'),
		'get_quote' => __('Get a Free Quote', 'leadsforward-core'),
		'request_quote' => __('Request a Quote', 'leadsforward-core'),
		'schedule' => __('Schedule Today', 'leadsforward-core'),
	];

	$trust_badge_style = [
		'default' => __('Default labels', 'leadsforward-core'),
		'stars_years' => __('X stars · Y years', 'leadsforward-core'),
		'reviews_count' => __('X+ reviews', 'leadsforward-core'),
		'rated' => __('Rated X/5', 'leadsforward-core'),
	];

	acf_add_local_field_group([
		'key'   => 'group_lf_options_variation',
		'title' => __('Variation', 'leadsforward-core'),
		'fields' => [
			[
				'key'     => 'field_lf_variation_profile',
				'label'   => __('Variation profile', 'leadsforward-core'),
				'name'    => 'variation_profile',
				'type'    => 'select',
				'choices' => $profiles,
				'default_value' => 'a',
				'instructions' => __('Set once per site. Drives block variants, tokens, and optional section order.', 'leadsforward-core'),
			],
			[
				'key'           => 'field_lf_auto_order_sections',
				'label'         => __('Auto-order homepage sections', 'leadsforward-core'),
				'name'          => 'auto_order_sections',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
				'instructions'  => __('When on: reorders only middle sections (trust/services/cta/faq/map). Hero stays first, final CTA stays last.', 'leadsforward-core'),
			],
			[
				'key'     => 'field_lf_hero_headline_style',
				'label'   => __('Hero headline style (template)', 'leadsforward-core'),
				'name'    => 'hero_headline_style',
				'type'    => 'select',
				'choices' => $hero_copy_style,
				'default_value' => 'default',
			],
			[
				'key'     => 'field_lf_cta_microcopy_style',
				'label'   => __('CTA microcopy style (template)', 'leadsforward-core'),
				'name'    => 'cta_microcopy_style',
				'type'    => 'select',
				'choices' => $cta_copy_style,
				'default_value' => 'default',
			],
			[
				'key'     => 'field_lf_trust_badge_style',
				'label'   => __('Trust badge label style (template)', 'leadsforward-core'),
				'name'    => 'trust_badge_style',
				'type'    => 'select',
				'choices' => $trust_badge_style,
				'default_value' => 'default',
			],
		],
		'location' => [
			[
				[
					'param'    => 'options_page',
					'operator' => '==',
					'value'    => 'lf-variation',
				],
			],
		],
	]);
}
