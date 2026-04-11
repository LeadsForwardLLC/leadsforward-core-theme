<?php
/**
 * WordPress core XML sitemaps (wp-sitemap.xml): exclude fragment CPTs used inside Page Builder.
 *
 * Theme sitemap.xml (lf_seo_render_sitemap) is separate; this only adjusts core wp_sitemaps.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_filter('wp_sitemaps_post_types', 'lf_seo_wp_sitemaps_exclude_fragment_post_types', 20);

/**
 * @param array<string, string> $post_types Post type => post type (names from get_post_types).
 * @return array<string, string>
 */
function lf_seo_wp_sitemaps_exclude_fragment_post_types(array $post_types): array {
	$exclude = [
		'lf_faq',
		'lf_process_step',
	];
	/**
	 * Post types to remove from WordPress core sitemaps (embedded in sections; thin standalone URLs).
	 *
	 * @param list<string> $exclude
	 */
	$exclude = apply_filters('lf_seo_wp_sitemaps_excluded_post_types', $exclude);
	foreach ((array) $exclude as $pt) {
		$pt = sanitize_key((string) $pt);
		if ($pt === '') {
			continue;
		}
		unset($post_types[$pt]);
	}
	return $post_types;
}
