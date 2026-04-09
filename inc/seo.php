<?php
/**
 * SEO module loader (settings, meta box, rendering, sitemap, keyword engine).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

if (function_exists('lf_load_inc')) {
	lf_load_inc('seo/internal-link-map.php');
	lf_load_inc('seo/seo-settings.php');
	lf_load_inc('seo/seo-meta-box.php');
	lf_load_inc('seo/seo-quality.php');
	lf_load_inc('seo/seo-keyword-engine.php');
	lf_load_inc('seo/seo-render.php');
	lf_load_inc('seo/seo-sitemap.php');
}
