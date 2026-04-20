<?php
/**
 * Menu helpers: optional auto-build of primary menu (Header Menu).
 *
 * @package LeadsForward_Core
 * @since 0.1.73
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_menu_autobuild_enabled(): bool {
	return function_exists('lf_get_global_option') && (string) lf_get_global_option('lf_menu_autobuild_enabled', '0') === '1';
}

/**
 * Build (or rebuild) the "Header Menu" and assign to header_menu location.
 * Safe: only runs when lf_menu_autobuild_enabled() is true.
 */
function lf_menu_maybe_autobuild_header_menu(): void {
	if (!lf_menu_autobuild_enabled() || is_admin()) {
		return;
	}
	if (!function_exists('wp_get_nav_menus') || !function_exists('wp_update_nav_menu_item')) {
		return;
	}

	// Avoid doing this on every request forever; rebuild at most once per day unless forced.
	$last = (int) get_option('lf_menu_autobuild_header_last', 0);
	if ($last > 0 && (time() - $last) < DAY_IN_SECONDS) {
		return;
	}
	update_option('lf_menu_autobuild_header_last', time(), false);

	$menu_name = 'Header Menu';
	$menu_id = null;
	$menus = wp_get_nav_menus();
	foreach ($menus as $m) {
		if (isset($m->name) && $m->name === $menu_name) {
			$menu_id = (int) $m->term_id;
			break;
		}
	}
	if (!$menu_id) {
		$created = wp_create_nav_menu($menu_name);
		if (is_wp_error($created)) {
			return;
		}
		$menu_id = (int) $created;
	}

	// Clear existing items (autobuild owns the menu).
	$existing = wp_get_nav_menu_items($menu_id);
	if (!empty($existing)) {
		foreach ($existing as $item) {
			if (isset($item->ID)) {
				wp_delete_post((int) $item->ID, true);
			}
		}
	}

	$position = 0;
	$add_page = static function (int $page_id, int $parent_id = 0, string $title_override = '') use ($menu_id, &$position): int {
		$post = get_post($page_id);
		if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
			return 0;
		}
		$title = $title_override !== '' ? $title_override : get_the_title($page_id);
		$id = wp_update_nav_menu_item($menu_id, 0, [
			'menu-item-title'     => $title,
			'menu-item-url'       => get_permalink($page_id),
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent_id,
			'menu-item-position'  => $position++,
		]);
		return is_wp_error($id) ? 0 : (int) $id;
	};

	$add_cpt = static function (string $post_type, int $post_id, int $parent_id = 0): int {
		$post = get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
			return 0;
		}
		$id = wp_update_nav_menu_item($menu_id, 0, [
			'menu-item-title'     => get_the_title($post_id),
			'menu-item-url'       => get_permalink($post_id),
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => $post_type,
			'menu-item-object-id' => $post_id,
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent_id,
		]);
		return is_wp_error($id) ? 0 : (int) $id;
	};

	// Core pages.
	$home = (int) get_option('page_on_front');
	if ($home > 0) {
		$add_page($home);
	}

	$services_page = get_page_by_path('services');
	$services_parent_item = 0;
	if ($services_page instanceof \WP_Post && $services_page->post_status === 'publish') {
		$services_parent_item = $add_page((int) $services_page->ID);
	}

	// Optional: include selected services as children.
	$service_ids = function_exists('lf_get_global_option') ? lf_get_global_option('lf_menu_autobuild_include_services', []) : [];
	if (!is_array($service_ids)) {
		$service_ids = [];
	}
	if ($services_parent_item > 0 && !empty($service_ids)) {
		foreach ($service_ids as $sid) {
			$add_cpt('lf_service', (int) $sid, $services_parent_item);
		}
	}

	// Common pages if present and published.
	foreach (['service-areas', 'reviews', 'about-us', 'why-choose-us', 'contact'] as $slug) {
		$page = get_page_by_path($slug);
		if ($page instanceof \WP_Post && $page->post_status === 'publish') {
			$add_page((int) $page->ID);
		}
	}

	// Assign to theme location.
	$locations = get_theme_mod('nav_menu_locations') ?: [];
	$locations['header_menu'] = $menu_id;
	set_theme_mod('nav_menu_locations', $locations);
}
add_action('wp', 'lf_menu_maybe_autobuild_header_menu', 20);

