<?php
/**
 * Controlled copy template slots. Dropdown-selected templates with placeholders.
 * No randomness; admin chooses style once. lf_copy_template($key, $fallback, $context).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Copy template definitions. Keys match ACF select values; value = string with {placeholders}.
 */
function lf_get_copy_templates(string $key): array {
	$templates = [
		'hero_headline' => [
			'default'        => '', /* use fallback */
			'service_city'   => '{service} in {city}',
			'quality_area'   => __('Quality {service} in {area}', 'leadsforward-core'),
			'local_leader'   => __("{area}'s trusted {service}", 'leadsforward-core'),
			'simple_welcome' => __('Welcome to {business_name}', 'leadsforward-core'),
		],
		'cta_microcopy' => [
			'default'       => '',
			'call_now'      => __('Call Now', 'leadsforward-core'),
			'get_quote'     => __('Get a Free Quote', 'leadsforward-core'),
			'request_quote' => __('Request a Quote', 'leadsforward-core'),
			'schedule'      => __('Schedule Today', 'leadsforward-core'),
		],
		'trust_badge' => [
			'default'      => '',
			'stars_years'  => __('{rating} stars · {years} years', 'leadsforward-core'),
			'reviews_count' => __('{count}+ reviews', 'leadsforward-core'),
			'rated'        => __('Rated {rating}/5', 'leadsforward-core'),
		],
	];
	return $templates[$key] ?? [];
}

/**
 * Get selected copy style from ACF (Theme Options > Variation). Returns select value or 'default'.
 */
function lf_get_copy_style(string $field_name): string {
	$val = function_exists('get_field') ? get_field($field_name, 'option') : '';
	return is_string($val) && $val !== '' ? $val : 'default';
}

/**
 * Return copy from selected template with context substitution. Controlled dropdowns only.
 *
 * @param string $key Template set key: 'hero_headline', 'cta_microcopy', 'trust_badge'
 * @param string $fallback Used when style is 'default' or template missing
 * @param array  $context Placeholders: business_name, service, city, area, rating, years, count, etc.
 * @return string
 */
function lf_copy_template(string $key, string $fallback = '', array $context = []): string {
	$templates = lf_get_copy_templates($key);
	$field_map = [
		'hero_headline'   => 'hero_headline_style',
		'cta_microcopy'   => 'cta_microcopy_style',
		'trust_badge'     => 'trust_badge_style',
	];
	$field = $field_map[$key] ?? $key . '_style';
	$style = lf_get_copy_style($field);
	if ($style === 'default' || !isset($templates[$style])) {
		return $fallback;
	}
	$tpl = $templates[$style];
	foreach ($context as $k => $v) {
		$tpl = str_replace('{' . $k . '}', (string) $v, $tpl);
	}
	return preg_replace('/\{[a-z_]+\}/', '', $tpl);
}
