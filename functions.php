<?php
/**
 * LeadsForward Core Theme — Bootstrap
 *
 * Loads all theme logic from /inc/. No business logic in this file.
 * PHP 8+ compatible.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

define('LF_THEME_VERSION', '0.1.0');
define('LF_THEME_DIR', get_template_directory());
define('LF_THEME_URI', get_template_directory_uri());

/**
 * Load a single inc file by relative path from inc/.
 */
function lf_load_inc(string $path): void {
	$file = LF_THEME_DIR . '/inc/' . ltrim($path, '/');
	if (is_readable($file)) {
		require_once $file;
	}
}

// Core setup: theme support, menus, editor styles.
lf_load_inc('setup.php');

// WordPress cleanup: emojis, oEmbed, dashicons, bloat.
lf_load_inc('cleanup.php');

// Performance: defer scripts, heartbeat, head cleanup.
lf_load_inc('performance.php');

// SEO and schema (foundation only).
lf_load_inc('seo.php');
lf_load_inc('schema.php');

// Custom post types.
lf_load_inc('cpt/services.php');
lf_load_inc('cpt/service-areas.php');
lf_load_inc('cpt/testimonials.php');
lf_load_inc('cpt/faqs.php');

// ACF options + field groups (load only when ACF present; guardrails handle fallback).
lf_load_inc('acf/options-business.php');
lf_load_inc('acf/options-ctas.php');
lf_load_inc('acf/options-schema.php');
lf_load_inc('acf/options-homepage.php');
lf_load_inc('acf/options-variation.php');
lf_load_inc('acf/field-group-service.php');
lf_load_inc('acf/field-group-service-area.php');
lf_load_inc('acf/field-group-testimonial.php');
lf_load_inc('acf/field-group-faq.php');

// ACF blocks (server-rendered).
lf_load_inc('blocks/register.php');
lf_load_inc('blocks/variants.php');

// Variation tokens: body class, data-variation, CSS vars.
lf_load_inc('variation-tokens.php');

// Homepage section registry, defaults, CTA resolution.
lf_load_inc('homepage.php');
lf_load_inc('variation-copy.php');

// Safety: CPT protect, admin notices, ACF-off fallbacks.
lf_load_inc('guardrails.php');
