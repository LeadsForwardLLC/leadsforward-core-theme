<?php
/**
 * XML sitemap: /sitemap.xml
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('init', 'lf_seo_register_sitemap_route');
add_filter('query_vars', 'lf_seo_register_sitemap_query_var');
add_action('template_redirect', 'lf_seo_render_sitemap');
add_action('after_switch_theme', 'lf_seo_flush_sitemap_rewrite');

function lf_seo_register_sitemap_route(): void {
	add_rewrite_rule('^sitemap\.xml$', 'index.php?lf_sitemap=1', 'top');
}

function lf_seo_register_sitemap_query_var(array $vars): array {
	$vars[] = 'lf_sitemap';
	return $vars;
}

function lf_seo_flush_sitemap_rewrite(): void {
	lf_seo_register_sitemap_route();
	flush_rewrite_rules();
}

function lf_seo_render_sitemap(): void {
	if ((int) get_query_var('lf_sitemap') !== 1) {
		return;
	}
	$settings = function_exists('lf_seo_get_settings') ? lf_seo_get_settings() : [];
	if (empty($settings['sitemap']['enable'])) {
		status_header(404);
		exit;
	}

	$urls = [];
	$front_id = (int) get_option('page_on_front');
	$posts_page = (int) get_option('page_for_posts');
	$now = current_time('c');

	lf_seo_add_sitemap_url($urls, home_url('/'), $now);

	$pages = get_posts([
		'post_type' => 'page',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	foreach ($pages as $page) {
		if ((int) $page->ID === $front_id) {
			continue;
		}
		if (lf_seo_post_noindexed((int) $page->ID)) {
			continue;
		}
		$lastmod = get_post_modified_time('c', true, $page);
		lf_seo_add_sitemap_url($urls, get_permalink($page), $lastmod ?: $now);
	}

	if (!empty($settings['sitemap']['include_services'])) {
		$services = get_posts([
			'post_type' => 'lf_service',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
			'no_found_rows' => true,
		]);
		foreach ($services as $service) {
			if (lf_seo_post_noindexed((int) $service->ID)) {
				continue;
			}
			$lastmod = get_post_modified_time('c', true, $service);
			lf_seo_add_sitemap_url($urls, get_permalink($service), $lastmod ?: $now);
		}
	}

	if (!empty($settings['sitemap']['include_service_areas'])) {
		$areas = get_posts([
			'post_type' => 'lf_service_area',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
			'no_found_rows' => true,
		]);
		foreach ($areas as $area) {
			if (lf_seo_post_noindexed((int) $area->ID)) {
				continue;
			}
			$lastmod = get_post_modified_time('c', true, $area);
			lf_seo_add_sitemap_url($urls, get_permalink($area), $lastmod ?: $now);
		}
	}

	if (!empty($settings['sitemap']['include_posts'])) {
		$posts = get_posts([
			'post_type' => 'post',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
		]);
		foreach ($posts as $post) {
			if (lf_seo_post_noindexed((int) $post->ID)) {
				continue;
			}
			$lastmod = get_post_modified_time('c', true, $post);
			lf_seo_add_sitemap_url($urls, get_permalink($post), $lastmod ?: $now);
		}
	}

	header('Content-Type: application/xml; charset=UTF-8');
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ($urls as $url) {
		echo "\t<url>\n";
		echo "\t\t<loc>" . esc_url($url['loc']) . "</loc>\n";
		if (!empty($url['lastmod'])) {
			echo "\t\t<lastmod>" . esc_html($url['lastmod']) . "</lastmod>\n";
		}
		echo "\t</url>\n";
	}
	echo '</urlset>';
	exit;
}

function lf_seo_add_sitemap_url(array &$urls, string $loc, string $lastmod = ''): void {
	$loc = trim($loc);
	if ($loc === '') {
		return;
	}
	$urls[] = [
		'loc' => $loc,
		'lastmod' => $lastmod,
	];
}

function lf_seo_post_noindexed(int $post_id): bool {
	$noindex = (string) get_post_meta($post_id, '_lf_seo_noindex', true) === '1';
	return $noindex;
}
