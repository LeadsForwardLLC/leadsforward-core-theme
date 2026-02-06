<?php
/**
 * Variation tokens: body class, data-variation attribute, CSS var preset.
 * Deterministic; no runtime randomness. Does not affect schema or URLs.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_filter('body_class', 'lf_variation_body_class');
add_action('wp_enqueue_scripts', 'lf_enqueue_variation_tokens_css', 5);

/**
 * Add variation profile class for styling and footprint differentiation.
 */
function lf_variation_body_class(array $classes): array {
	$profile = function_exists('lf_get_variation_profile') ? lf_get_variation_profile() : 'a';
	$classes[] = 'variation-profile-' . $profile;
	return $classes;
}

/**
 * Return data-variation attribute for body tag. Use in header.php.
 */
function lf_variation_body_data_attribute(): string {
	$profile = function_exists('lf_get_variation_profile') ? lf_get_variation_profile() : 'a';
	return ' data-variation="' . esc_attr(strtoupper($profile)) . '"';
}

/**
 * Enqueue design system: variation tokens first, then layout + blocks (one file).
 */
function lf_enqueue_variation_tokens_css(): void {
	$path = LF_THEME_DIR . '/assets/css/variation-tokens.css';
	if (!is_readable($path)) {
		return;
	}
	wp_enqueue_style(
		'lf-variation-tokens',
		LF_THEME_URI . '/assets/css/variation-tokens.css',
		[],
		(string) filemtime($path)
	);
	$ds = LF_THEME_DIR . '/assets/css/design-system.css';
	if (is_readable($ds)) {
		wp_enqueue_style(
			'lf-design-system',
			LF_THEME_URI . '/assets/css/design-system.css',
			['lf-variation-tokens'],
			(string) filemtime($ds)
		);
	}
}
