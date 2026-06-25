<?php
/**
 * Service Categories CPT — mega menu / overview grouping images and labels.
 *
 * Post slug should match smart category slugs (foundation-repair, waterproofing, crawl-space).
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_cpt_service_categories(): void {
	$labels = [
		'name'               => _x('Service Categories', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('Service Category', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('Service Categories', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'service category', 'leadsforward-core'),
		'add_new_item'       => __('Add New Service Category', 'leadsforward-core'),
		'edit_item'          => __('Edit Service Category', 'leadsforward-core'),
		'new_item'           => __('New Service Category', 'leadsforward-core'),
		'view_item'          => __('View Service Category', 'leadsforward-core'),
		'search_items'       => __('Search Service Categories', 'leadsforward-core'),
		'not_found'          => __('No service categories found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No service categories found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => false,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_menu'        => 'edit.php?post_type=lf_service',
		'show_in_nav_menus'   => false,
		'show_in_rest'        => true,
		'rest_base'           => 'service-categories',
		'query_var'           => false,
		'rewrite'             => false,
		'capability_type'     => 'post',
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => null,
		'supports'            => ['title', 'thumbnail', 'revisions'],
	];
	register_post_type('lf_service_category', $args);
}
add_action('init', 'lf_register_cpt_service_categories');

/**
 * Fetch a published service category post by smart slug.
 */
function lf_get_service_category_post(string $slug): ?\WP_Post {
	$slug = sanitize_title($slug);
	if ($slug === '') {
		return null;
	}
	static $cache = [];
	if (array_key_exists($slug, $cache)) {
		return $cache[$slug];
	}
	$posts = get_posts([
		'post_type'              => 'lf_service_category',
		'name'                   => $slug,
		'post_status'            => 'publish',
		'posts_per_page'         => 1,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	]);
	$post = ($posts[0] ?? null) instanceof \WP_Post ? $posts[0] : null;
	$cache[$slug] = $post;

	return $post;
}

/**
 * Category card image: CPT featured image, else first child service, else default.
 *
 * @param list<\WP_Post> $fallback_service_posts
 */
function lf_get_service_category_image_url(string $slug, array $fallback_service_posts = [], string $size = 'thumbnail'): string {
	$slug = sanitize_title($slug);
	$cat_post = $slug !== '' ? lf_get_service_category_post($slug) : null;
	if ($cat_post instanceof \WP_Post) {
		$thumb_id = (int) get_post_thumbnail_id($cat_post);
		if ($thumb_id > 0) {
			$url = (string) wp_get_attachment_image_url($thumb_id, $size);
			if ($url !== '') {
				return $url;
			}
		}
	}
	foreach ($fallback_service_posts as $service) {
		if (!$service instanceof \WP_Post) {
			continue;
		}
		$thumb_id = function_exists('lf_get_post_card_thumbnail_id')
			? (int) lf_get_post_card_thumbnail_id($service)
			: (int) get_post_thumbnail_id($service);
		if ($thumb_id > 0) {
			$url = (string) wp_get_attachment_image_url($thumb_id, $size);
			if ($url !== '') {
				return $url;
			}
		}
	}
	if (function_exists('lf_get_section_default_image_url')) {
		return (string) lf_get_section_default_image_url('service');
	}

	return '';
}

/**
 * Seed smart category rows once (foundation-repair niches).
 */
function lf_maybe_seed_service_categories(): void {
	if (get_option('lf_service_categories_seeded')) {
		return;
	}
	if (!function_exists('lf_services_menu_category_definitions')) {
		return;
	}
	$defs = lf_services_menu_category_definitions();
	foreach ($defs as $slug => $label) {
		if (lf_get_service_category_post((string) $slug) instanceof \WP_Post) {
			continue;
		}
		wp_insert_post([
			'post_type'   => 'lf_service_category',
			'post_title'  => (string) $label,
			'post_name'   => sanitize_title((string) $slug),
			'post_status' => 'publish',
		]);
	}
	update_option('lf_service_categories_seeded', 1, true);
}
add_action('admin_init', 'lf_maybe_seed_service_categories', 20);
