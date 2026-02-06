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
