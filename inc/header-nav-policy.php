<?php
/**
 * Fleet navigation contract — header + footer order must not drift.
 *
 * Header (top level): Home → Services (mega) → Service Areas → About → Call → Free Estimate → More
 * Contact and other secondary pages live under More only.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/** @var list<string> */
function lf_header_nav_fleet_top_level_labels(): array {
	$labels = ['Home', 'Services', 'Service Areas', 'About'];

	return (array) apply_filters('lf_header_nav_fleet_top_level_labels', $labels);
}

/**
 * Menu items that must never be bucketed under More.
 */
function lf_header_menu_item_must_stay_top_level(\WP_Post $item): bool {
	if (!$item instanceof \WP_Post || (int) ($item->menu_item_parent ?? 0) !== 0) {
		return false;
	}
	if (function_exists('lf_header_menu_item_is_home_item') && lf_header_menu_item_is_home_item($item)) {
		return true;
	}
	if (lf_header_menu_item_has_class($item, 'lf-menu-services-parent')
		|| lf_header_menu_item_has_class($item, 'lf-menu-areas-parent')) {
		return true;
	}
	if (function_exists('lf_header_menu_item_is_services_parent') && lf_header_menu_item_is_services_parent($item)) {
		return true;
	}
	if (function_exists('lf_header_menu_item_is_areas_parent') && lf_header_menu_item_is_areas_parent($item)) {
		return true;
	}
	if (function_exists('lf_header_menu_item_is_about') && lf_header_menu_item_is_about($item)) {
		return true;
	}
	if (lf_nav_menu_item_is_sync_preserved_cta($item)) {
		return true;
	}
	if (lf_header_menu_item_has_class($item, 'lf-menu-more')) {
		return true;
	}

	$page = function_exists('lf_header_menu_resolve_menu_item_page')
		? lf_header_menu_resolve_menu_item_page($item)
		: null;
	if ($page instanceof \WP_Post && in_array((string) $page->post_name, ['services', 'service-areas'], true)) {
		return true;
	}

	$title = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
	if (in_array($title, ['services', 'service areas'], true)) {
		return true;
	}

	return (bool) apply_filters('lf_header_menu_item_must_stay_top_level', false, $item);
}

/**
 * Pull Service Areas (and other fleet top-level items) out from under More in the stored menu.
 */
function lf_header_menu_ensure_fleet_top_levels(int $menu_id): void {
	if ($menu_id <= 0 || !function_exists('wp_get_nav_menu_items') || !function_exists('wp_update_nav_menu_item')) {
		return;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || $items === []) {
		return;
	}

	$more_parent_id = function_exists('lf_header_menu_find_more_parent_id')
		? lf_header_menu_find_more_parent_id($items)
		: 0;
	if ($more_parent_id <= 0) {
		return;
	}

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== $more_parent_id) {
			continue;
		}
		if (!lf_header_menu_item_must_stay_top_level($item)) {
			continue;
		}
		$args = lf_nav_menu_item_build_update_args($item);
		$args['menu-item-parent-id'] = 0;
		wp_update_nav_menu_item($menu_id, (int) $item->ID, $args);
	}
}

/**
 * Whether Service Areas is present at top level with the areas parent marker.
 *
 * @param array<int, \WP_Post> $items
 */
function lf_header_menu_has_top_level_service_areas(array $items): bool {
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post || (int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		if (lf_header_menu_item_has_class($item, 'lf-menu-areas-parent')) {
			return true;
		}
		if (function_exists('lf_header_menu_item_is_areas_parent') && lf_header_menu_item_is_areas_parent($item)) {
			return true;
		}
		$page = function_exists('lf_header_menu_resolve_menu_item_page')
			? lf_header_menu_resolve_menu_item_page($item)
			: null;
		if ($page instanceof \WP_Post && (string) $page->post_name === 'service-areas') {
			return true;
		}
	}
	return false;
}

/** @var list<string> */
function lf_footer_nav_fleet_page_slugs(): array {
	$slugs = [
		'home',
		'contact',
		'reviews',
		'financing',
		'blog',
		'sitemap',
		'privacy-policy',
		'terms-of-service',
		'services',
		'service-areas',
	];

	return (array) apply_filters('lf_footer_nav_fleet_page_slugs', $slugs);
}

/**
 * Reorder footer menu items to the fleet default without deleting custom links.
 */
function lf_footer_menu_repair_fleet_order(int $menu_id): void {
	if ($menu_id <= 0 || !function_exists('wp_get_nav_menu_items') || !function_exists('wp_update_nav_menu_item')) {
		return;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || $items === []) {
		return;
	}

	$pool = [];
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post || (int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		$pool[(int) $item->ID] = $item;
	}
	if ($pool === []) {
		return;
	}

	$ordered = [];
	$used = [];
	foreach (lf_footer_nav_fleet_page_slugs() as $slug) {
		foreach ($pool as $id => $item) {
			if (!$item instanceof \WP_Post) {
				continue;
			}
			$page = function_exists('lf_header_menu_resolve_menu_item_page')
				? lf_header_menu_resolve_menu_item_page($item)
				: null;
			if ($page instanceof \WP_Post && (string) $page->post_name === $slug) {
				$ordered[] = $item;
				$used[$id] = true;
				break;
			}
		}
	}

	foreach ($pool as $id => $item) {
		if (!isset($used[$id])) {
			$ordered[] = $item;
		}
	}

	$pos = 0;
	foreach ($ordered as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$args = lf_nav_menu_item_build_update_args($item);
		$args['menu-item-position'] = $pos++;
		wp_update_nav_menu_item($menu_id, (int) $item->ID, $args);
	}
}

function lf_footer_menu_force_fleet_repair(): void {
	if (!has_nav_menu('footer_menu')) {
		return;
	}
	$locations = get_nav_menu_locations();
	$menu_id = (int) ($locations['footer_menu'] ?? 0);
	if ($menu_id <= 0) {
		return;
	}
	lf_footer_menu_repair_fleet_order($menu_id);
}
add_action('admin_init', 'lf_footer_menu_force_fleet_repair', 13);
add_action('wp', 'lf_footer_menu_force_fleet_repair', 6);
