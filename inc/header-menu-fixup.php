<?php
/**
 * Header menu tree fixup: reparent orphans, bucket More, keep dropdown trees intact.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @param array<int, \WP_Post> $items
 * @return array<int, bool>
 */
function lf_header_menu_item_id_set(array $items): array {
	$set = [];
	foreach ($items as $item) {
		if ($item instanceof \WP_Post) {
			$set[(int) ($item->ID ?? 0)] = true;
		}
	}
	return $set;
}

/**
 * Canonical Service Areas parent menu item ID.
 *
 * @param array<int, \WP_Post> $items
 */
function lf_header_menu_service_areas_parent_id(array $items): int {
	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($menu_item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		$title = strtolower(trim(wp_strip_all_tags((string) ($menu_item->title ?? ''))));
		if ($title === 'service areas' || lf_header_menu_item_has_class($menu_item, 'lf-menu-areas-parent')) {
			return (int) ($menu_item->ID ?? 0);
		}
	}
	return 0;
}

/**
 * Reparent CPT / orphaned rows so Walker can render submenus (orphans are otherwise dropped).
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_fixup_dropdown_tree(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}

	$id_set = lf_header_menu_item_id_set($items);
	$services_pid = lf_header_menu_services_parent_id($items);
	$areas_pid = lf_header_menu_service_areas_parent_id($items);

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$object = (string) ($item->object ?? '');
		if ($object === 'lf_service' && $services_pid > 0) {
			$item->menu_item_parent = $services_pid;
			continue;
		}
		if ($object === 'lf_service_area' && $areas_pid > 0) {
			$item->menu_item_parent = $areas_pid;
			continue;
		}

		$parent = (int) ($item->menu_item_parent ?? 0);
		if ($parent === 0 || isset($id_set[$parent])) {
			continue;
		}
		if ($object === 'lf_service' && $services_pid > 0) {
			$item->menu_item_parent = $services_pid;
		} elseif ($object === 'lf_service_area' && $areas_pid > 0) {
			$item->menu_item_parent = $areas_pid;
		}
	}

	return $items;
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_fixup_dropdown_tree', 5, 2);

/**
 * Whether a menu item should live under More (alias).
 */
function lf_header_menu_item_is_more_candidate(\WP_Post $item): bool {
	return lf_header_menu_item_belongs_in_more($item);
}

/**
 * Bucket secondary links under More; flatten nested secondary submenus to direct More children.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_objects_consolidate_more(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}

	$more_parent_id = 0;
	$candidate_ids = [];
	$candidates = [];

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		if (lf_header_menu_item_has_class($item, 'lf-menu-more')) {
			$more_parent_id = (int) $item->ID;
			continue;
		}
		$title = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
		if ($title === 'more') {
			$more_parent_id = (int) $item->ID;
			continue;
		}
		if (lf_header_menu_item_is_more_candidate($item)) {
			$candidates[] = $item;
			$candidate_ids[(int) $item->ID] = true;
		}
	}

	if ($candidates === []) {
		return $items;
	}

	if ($more_parent_id <= 0) {
		$more_parent_id = -5100;
		$more_item = lf_header_menu_synthetic_child(
			0,
			$more_parent_id,
			__('More', 'leadsforward-core'),
			'#',
			['menu-item', 'lf-menu-more', 'menu-item-has-children']
		);
		$more_item->menu_order = 9000;
		$items[] = $more_item;
	}

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$parent = (int) ($item->menu_item_parent ?? 0);
		if ($parent !== 0 && isset($candidate_ids[$parent])) {
			$item->menu_item_parent = $more_parent_id;
		}
	}

	foreach ($candidates as $item) {
		$item->menu_item_parent = $more_parent_id;
	}

	return $items;
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_objects_consolidate_more', 18, 2);

/**
 * Repair stored menu when secondary pages are still saved at top level.
 */
function lf_header_menu_force_structure_repair(): void {
	if (is_admin() || !has_nav_menu('header_menu')) {
		return;
	}

	$locations = get_nav_menu_locations();
	$menu_id = (int) ($locations['header_menu'] ?? 0);
	if ($menu_id <= 0 || !function_exists('lf_header_menu_repair_nav_structure')) {
		return;
	}

	$structure_version = 'header-nav-v4';
	$stored = (string) get_option('lf_header_menu_structure_version', '');
	if ($stored === $structure_version) {
		return;
	}

	lf_header_menu_repair_nav_structure($menu_id);
	if (function_exists('lf_header_menu_consolidate_secondary_into_more')) {
		lf_header_menu_consolidate_secondary_into_more($menu_id);
	}
	update_option('lf_header_menu_structure_version', $structure_version, false);
}
add_action('wp', 'lf_header_menu_force_structure_repair', 12);
