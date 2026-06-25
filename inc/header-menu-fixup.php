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
	$services_dropdown = function_exists('lf_header_menu_cpt_nav_dropdown_enabled')
		&& lf_header_menu_cpt_nav_dropdown_enabled('services');
	$areas_dropdown = function_exists('lf_header_menu_cpt_nav_dropdown_enabled')
		&& lf_header_menu_cpt_nav_dropdown_enabled('areas');
	if (!$services_dropdown && !$areas_dropdown) {
		return $items;
	}

	$id_set = lf_header_menu_item_id_set($items);
	$services_pid = $services_dropdown ? lf_header_menu_services_parent_id($items) : 0;
	$areas_pid = $areas_dropdown ? lf_header_menu_service_areas_parent_id($items) : 0;

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$object = (string) ($item->object ?? '');
		if ($object === 'lf_service' && $services_pid > 0) {
			$parent = (int) ($item->menu_item_parent ?? 0);
			if ($parent !== $services_pid && $parent !== 0) {
				foreach ($items as $maybe_parent) {
					if (!$maybe_parent instanceof \WP_Post) {
						continue;
					}
					if ((int) ($maybe_parent->ID ?? 0) !== $parent) {
						continue;
					}
					if (lf_header_menu_item_has_class($maybe_parent, 'lf-menu-service-category')) {
						continue 2;
					}
				}
			}
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
	if (!function_exists('lf_header_menu_objects_apply_nav_rules')) {
		return $items;
	}

	try {
		return lf_header_menu_objects_apply_nav_rules($items, $args);
	} catch (\Throwable $e) {
		if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
			error_log('LF header menu nav rules: ' . $e->getMessage());
		}
		return $items;
	}
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_objects_consolidate_more', 18, 2);

/**
 * Repair stored menu when secondary pages are still saved at top level.
 * Runs once per structure version (admin or front); not on every page view.
 */
function lf_header_menu_force_structure_repair(): void {
	if (!has_nav_menu('header_menu')) {
		return;
	}

	$locations = get_nav_menu_locations();
	$menu_id = (int) ($locations['header_menu'] ?? 0);
	if ($menu_id <= 0 || !function_exists('lf_header_menu_repair_nav_structure')) {
		return;
	}

	$structure_version = 'header-nav-v6';
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
add_action('admin_init', 'lf_header_menu_force_structure_repair', 12);
