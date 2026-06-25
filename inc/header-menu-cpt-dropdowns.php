<?php
/**
 * Services / Service Areas nav: overview links until that CPT has published posts, then dropdown.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * CPT post type for a nav group kind.
 */
function lf_header_menu_cpt_post_type_for_kind(string $kind): string {
	return $kind === 'areas' ? 'lf_service_area' : 'lf_service';
}

/**
 * Whether at least one published CPT exists for a nav group (services or areas).
 */
function lf_header_menu_cpt_nav_dropdown_enabled(string $kind): bool {
	static $cache = [];
	if (isset($cache[$kind])) {
		return $cache[$kind];
	}

	$cache[$kind] = false;
	$found = get_posts([
		'post_type'              => lf_header_menu_cpt_post_type_for_kind($kind),
		'post_status'            => 'publish',
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	]);
	if (is_array($found) && $found !== []) {
		$cache[$kind] = true;
	}

	return (bool) apply_filters('lf_header_menu_cpt_nav_dropdown_enabled', $cache[$kind], $kind);
}

/**
 * Whether any Services or Service Areas nav dropdown is active.
 */
function lf_header_menu_cpt_nav_dropdowns_enabled(): bool {
	return lf_header_menu_cpt_nav_dropdown_enabled('services')
		|| lf_header_menu_cpt_nav_dropdown_enabled('areas');
}

/**
 * Overview page URL for Services or Service Areas.
 */
function lf_header_menu_cpt_overview_url(string $kind): string {
	$slug = $kind === 'areas' ? 'service-areas' : 'services';
	if (function_exists('lf_nav_menu_publish_page_id')) {
		$page_id = lf_nav_menu_publish_page_id($slug);
		if ($page_id > 0) {
			$url = get_permalink($page_id);
			return is_string($url) ? $url : '';
		}
	}
	$page = get_page_by_path($slug);
	if ($page instanceof \WP_Post) {
		$url = get_permalink($page);
		return is_string($url) ? $url : '';
	}

	return '';
}

/**
 * Top-level Services or Service Areas menu row (if present).
 *
 * @param array<int, \WP_Post> $items
 */
function lf_header_menu_find_cpt_group_parent(array $items, string $kind): ?\WP_Post {
	$is_areas = $kind === 'areas';
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		$marker = $is_areas ? 'lf-menu-areas-parent' : 'lf-menu-services-parent';
		if (lf_header_menu_item_has_class($item, $marker)) {
			return $item;
		}
		$title = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
		if ($is_areas && $title === 'service areas') {
			return $item;
		}
		if (!$is_areas && $title === 'services') {
			return $item;
		}
	}

	return null;
}

/**
 * Strip group dropdown chrome; link straight to overview pages when no CPTs are published.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_apply_cpt_overview_link_mode(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}

	$parent_ids = [];
	foreach (['services', 'areas'] as $kind) {
		if (lf_header_menu_cpt_nav_dropdown_enabled($kind)) {
			continue;
		}
		$parent = lf_header_menu_find_cpt_group_parent($items, $kind);
		if (!$parent instanceof \WP_Post) {
			continue;
		}
		$pid = (int) ($parent->ID ?? 0);
		if ($pid === 0) {
			continue;
		}
		$parent_ids[$pid] = $kind;

		$url = lf_header_menu_cpt_overview_url($kind);
		if ($url !== '') {
			$parent->url = $url;
		}
		$classes = is_array($parent->classes ?? null) ? $parent->classes : [];
		$parent->classes = array_values(array_filter(
			$classes,
			static fn (string $class): bool => !in_array(
				$class,
				['lf-menu-group-parent', 'menu-item-has-children', 'lf-mega-menu', 'lf-mega-menu--services'],
				true
			)
		));
	}

	if ($parent_ids === []) {
		return $items;
	}

	$filtered = [];
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$parent = (int) ($item->menu_item_parent ?? 0);
		if ($parent !== 0 && isset($parent_ids[$parent])) {
			continue;
		}
		$filtered[] = $item;
	}

	return $filtered;
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_apply_cpt_overview_link_mode', 6, 2);

/**
 * When dropdowns are active but a group has no CPT children, keep a single overview link inside the panel.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_ensure_cpt_group_dropdown_children(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}

	$synthetic_id = -6000;
	$extra = [];

	foreach (['services' => 'lf_service', 'areas' => 'lf_service_area'] as $kind => $post_type) {
		if (!lf_header_menu_cpt_nav_dropdown_enabled($kind)) {
			continue;
		}
		$parent = lf_header_menu_find_cpt_group_parent($items, $kind);
		if (!$parent instanceof \WP_Post) {
			continue;
		}
		$parent_id = (int) ($parent->ID ?? 0);
		if ($parent_id <= 0) {
			continue;
		}

		$has_cpt_child = false;
		$has_all_link = false;
		foreach ($items as $child) {
			if (!$child instanceof \WP_Post) {
				continue;
			}
			if ((int) ($child->menu_item_parent ?? 0) !== $parent_id) {
				continue;
			}
			if ((string) ($child->object ?? '') === $post_type) {
				$has_cpt_child = true;
			}
			if (lf_header_menu_item_has_class($child, 'lf-submenu-all-link')) {
				$has_all_link = true;
			}
		}

		if ($has_cpt_child || $has_all_link) {
			continue;
		}

		$url = lf_header_menu_cpt_overview_url($kind);
		if ($url === '') {
			continue;
		}

		$label = $kind === 'areas'
			? __('All Service Areas', 'leadsforward-core')
			: __('All Services', 'leadsforward-core');
		$extra[] = lf_header_menu_synthetic_child(
			$parent_id,
			$synthetic_id--,
			$label,
			$url,
			['menu-item', 'lf-submenu-all-link']
		);
	}

	if ($extra === []) {
		return $items;
	}

	return array_merge($items, $extra);
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_ensure_cpt_group_dropdown_children', 7, 2);
