<?php
/**
 * Block variant registry. Allowed variants per block; profile-based defaults.
 * Deterministic, admin-controlled. No runtime randomness.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Allowed variants per block (template name). Used to validate override.
 */
function lf_get_allowed_block_variants(): array {
	return [
		'hero'           => ['default', 'a', 'b', 'c'],
		'trust-reviews'  => ['default', 'a', 'b', 'c'],
		'service-grid'   => ['default', 'a', 'b', 'c'],
		'service-areas'  => ['default', 'a', 'b', 'c'],
		'cta'            => ['default', 'a', 'b', 'c'],
		'faq-accordion'  => ['default', 'a', 'b', 'c'],
		'map-nap'        => ['default', 'a', 'b', 'c'],
	];
}

/**
 * Profile-based default variant per block. When no override, use this.
 * Variants: default = Authority Split, a = Conversion Stack, b = Form First, c = Visual Proof.
 */
function lf_get_profile_block_defaults(string $profile): array {
	$defaults = [
		'hero'           => 'default',
		'trust-reviews'  => 'default',
		'service-grid'   => 'default',
		'service-areas'  => 'default',
		'cta'            => 'default',
		'faq-accordion'  => 'default',
		'map-nap'        => 'default',
	];
	switch ($profile) {
		case 'a': // Clean + Minimal
			$defaults['hero'] = 'default';
			$defaults['cta']  = 'default';
			break;
		case 'b': // Bold + High Contrast
			$defaults['hero'] = 'a';
			$defaults['cta']  = 'a';
			break;
		case 'c': // Trust Heavy — hero variant with review snippet, testimonial early
			$defaults['hero'] = 'c';
			$defaults['trust-reviews'] = 'a';
			$defaults['cta'] = 'default';
			break;
		case 'd': // Service Heavy — service grid earlier, more internal links
			$defaults['hero'] = 'default';
			$defaults['service-grid'] = 'a';
			$defaults['cta'] = 'default';
			break;
		case 'e': // Offer/Promo Heavy
			$defaults['hero'] = 'b';
			$defaults['cta']  = 'b';
			break;
	}
	return $defaults;
}

/**
 * Get the variant to use for a block. Override only if valid for that block.
 * Resolution: valid override > profile default > 'default'.
 */
function lf_get_block_variant(string $block_name, ?string $override_variant = null): string {
	$allowed = lf_get_allowed_block_variants();
	$block_variants = $allowed[$block_name] ?? ['default'];
	$profile = lf_get_variation_profile();
	$profile_defaults = lf_get_profile_block_defaults($profile);
	$profile_variant = $profile_defaults[$block_name] ?? 'default';

	if ($override_variant !== null && $override_variant !== '' && in_array($override_variant, $block_variants, true)) {
		return $override_variant;
	}
	if (in_array($profile_variant, $block_variants, true)) {
		return $profile_variant;
	}
	return 'default';
}

/**
 * Current variation profile (a–e). Cached per request; set once per site.
 */
function lf_get_variation_profile(): string {
	static $profile = null;
	if ($profile !== null) {
		return $profile;
	}
	$profile = lf_get_option('variation_profile', 'option', 'a');
	return is_string($profile) && in_array($profile, ['a', 'b', 'c', 'd', 'e'], true) ? $profile : 'a';
}
