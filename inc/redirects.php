<?php
/**
 * LeadsForward Core Theme — Redirects
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Canonicalize legacy Services overview URL.
 *
 * We now use a real Page at `/services/` as the overview hub.
 * Keep `/our-services/` as a permanent redirect for backlinks.
 */
function lf_redirect_our_services_to_services_archive(): void {
	if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}
	// If the our-services page still exists, redirect it.
	// If it was renamed/removed, redirect the raw path.
	try {
		global $wp;
		$request = isset($wp) && is_object($wp) ? (string) ($wp->request ?? '') : '';
		$request = trim($request, '/');
		if ($request !== 'our-services' && !is_page('our-services')) {
			return;
		}
	} catch (\Throwable $e) {
		if (!is_page('our-services')) {
			return;
		}
	}

	$target = home_url('/services/');
	if (!is_string($target) || $target === '') {
		return;
	}
	wp_safe_redirect($target, 301);
	exit;
}
add_action('template_redirect', 'lf_redirect_our_services_to_services_archive', 1);

/**
 * One-time migration: rename the legacy our-services Page to services.
 *
 * This restores the original Services overview template and avoids SEO "Archive" meta
 * by making /services/ a Page (not a CPT archive).
 */
function lf_migrate_services_overview_page_slug_once(): void {
	if (!is_admin() && !wp_doing_ajax()) {
		// Safe to run on front too, but keep it lightweight.
	}
	if (get_option('lf_migrated_services_overview_slug_v1', '0') === '1') {
		return;
	}
	$services = get_page_by_path('services');
	if ($services instanceof \WP_Post) {
		update_option('lf_migrated_services_overview_slug_v1', '1', true);
		return;
	}
	$legacy = get_page_by_path('our-services');
	if (!$legacy instanceof \WP_Post) {
		update_option('lf_migrated_services_overview_slug_v1', '1', true);
		return;
	}
	$updated = wp_update_post([
		'ID' => $legacy->ID,
		'post_name' => 'services',
	], true);
	update_option('lf_migrated_services_overview_slug_v1', '1', true);
	if (is_wp_error($updated)) {
		return;
	}
}
add_action('init', 'lf_migrate_services_overview_page_slug_once', 20);

/**
 * One-time repair: if /services accidentally became the Posts page, fix it.
 *
 * If WordPress is configured with a static posts page and that page is currently the
 * one with slug `services`, it will render as "Blog" (and break the intended services hub).
 * We re-point page_for_posts back to the `blog` page when available, and free up /services/.
 */
function lf_fix_services_page_for_posts_misassignment_once(): void {
	if (get_option('lf_fix_services_posts_page_v1', '0') === '1') {
		return;
	}

	$services = get_page_by_path('services');
	if (!$services instanceof \WP_Post) {
		update_option('lf_fix_services_posts_page_v1', '1', true);
		return;
	}

	$posts_page_id = (int) get_option('page_for_posts');
	if ($posts_page_id !== (int) $services->ID) {
		update_option('lf_fix_services_posts_page_v1', '1', true);
		return;
	}

	$blog = get_page_by_path('blog');
	if ($blog instanceof \WP_Post) {
		update_option('page_for_posts', (int) $blog->ID, true);
	} else {
		// If no blog page exists, unassign posts page so /services/ can be a normal page again.
		update_option('page_for_posts', 0, true);
	}

	// If the services page title looks like a blog page, revert its slug to avoid conflicts.
	$title = strtolower(trim((string) $services->post_title));
	if ($title === 'blog' || $title === 'posts' || $title === 'latest updates') {
		$new_slug = ($blog instanceof \WP_Post) ? 'blog-archive' : 'blog';
		wp_update_post([
			'ID' => (int) $services->ID,
			'post_name' => $new_slug,
			'post_title' => __('Blog', 'leadsforward-core'),
		]);
	}

	update_option('lf_fix_services_posts_page_v1', '1', true);
}
add_action('init', 'lf_fix_services_page_for_posts_misassignment_once', 21);

