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
 * Final fleet contract pass before display reorder — bucket any stray top-level links.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_objects_enforce_fleet_contract(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}
	if (!function_exists('lf_header_menu_item_violates_fleet_top_level')
		|| !function_exists('lf_header_menu_objects_apply_nav_rules')) {
		return $items;
	}

	$needs_bucket = false;
	foreach ($items as $item) {
		if ($item instanceof \WP_Post && lf_header_menu_item_violates_fleet_top_level($item)) {
			$needs_bucket = true;
			break;
		}
	}
	if (!$needs_bucket) {
		return $items;
	}

	try {
		return lf_header_menu_objects_apply_nav_rules($items, $args);
	} catch (\Throwable $e) {
		if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
			error_log('LF header fleet enforce: ' . $e->getMessage());
		}
		return $items;
	}
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_objects_enforce_fleet_contract', 28, 2);

/**
 * Last-chance fleet pass after display reorder — bucket stragglers under More.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_objects_enforce_fleet_contract_final(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}
	if (!function_exists('lf_header_menu_item_violates_fleet_top_level')
		|| !function_exists('lf_header_menu_objects_apply_nav_rules')) {
		return $items;
	}

	foreach ($items as $item) {
		if ($item instanceof \WP_Post && lf_header_menu_item_violates_fleet_top_level($item)) {
			return lf_header_menu_objects_apply_nav_rules($items, $args);
		}
	}

	return $items;
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_objects_enforce_fleet_contract_final', 31, 2);

const LF_HEADER_MENU_STRUCTURE_VERSION = 'header-nav-v13';
const LF_HEADER_MENU_DEFERRED_REPAIR_HOOK = 'lf_header_menu_deferred_structure_repair';
const LF_HEADER_MENU_REPAIR_LOCK = 'lf_header_menu_repair_lock';

/**
 * Whether the stored header menu still violates fleet nav rules.
 */
function lf_header_menu_structure_needs_repair(int $menu_id): bool {
	if ($menu_id <= 0 || !function_exists('wp_get_nav_menu_items')) {
		return false;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || $items === []) {
		return false;
	}

	$top_about = 0;
	$more_parent_id = function_exists('lf_header_menu_find_more_parent_id')
		? lf_header_menu_find_more_parent_id($items)
		: 0;
	$more_children = 0;
	$services_parent_ok = false;
	$areas_parent_ok = false;

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$parent = (int) ($item->menu_item_parent ?? 0);
		if ($parent === 0 && function_exists('lf_header_menu_item_is_services_parent')
			&& lf_header_menu_item_is_services_parent($item)) {
			$services_parent_ok = lf_header_menu_item_has_class($item, 'lf-menu-services-parent');
		}
		if ($parent === 0 && function_exists('lf_header_menu_item_is_areas_parent')
			&& lf_header_menu_item_is_areas_parent($item)) {
			$areas_parent_ok = lf_header_menu_item_has_class($item, 'lf-menu-areas-parent');
		}
		if ($parent === 0 && function_exists('lf_header_menu_item_is_about') && lf_header_menu_item_is_about($item)) {
			++$top_about;
		}
		if ($parent === 0 && function_exists('lf_header_menu_item_violates_fleet_top_level')
			&& lf_header_menu_item_violates_fleet_top_level($item)) {
			return true;
		}
		if ($parent === 0 && function_exists('lf_header_menu_item_belongs_in_more')
			&& lf_header_menu_item_belongs_in_more($item)) {
			return true;
		}
		if ($more_parent_id !== 0 && $parent === $more_parent_id) {
			if (function_exists('lf_header_menu_item_must_stay_top_level') && lf_header_menu_item_must_stay_top_level($item)) {
				return true;
			}
			++$more_children;
		}
	}

	if ($top_about > 1) {
		return true;
	}
	if (function_exists('lf_header_menu_has_top_level_service_areas') && !lf_header_menu_has_top_level_service_areas($items)) {
		return true;
	}
	if (!$areas_parent_ok) {
		$areas_page = get_page_by_path('service-areas');
		if ($areas_page instanceof \WP_Post && $areas_page->post_status === 'publish') {
			return true;
		}
	}
	if (!$services_parent_ok && function_exists('lf_header_menu_cpt_nav_dropdown_enabled')
		&& lf_header_menu_cpt_nav_dropdown_enabled('services')) {
		return true;
	}
	if (function_exists('lf_header_menu_more_is_enabled') && lf_header_menu_more_is_enabled()) {
		if ($more_parent_id <= 0) {
			return true;
		}
		if ($more_children === 0 && function_exists('lf_header_menu_get_published_more_child_pages')) {
			$child_pages = lf_header_menu_get_published_more_child_pages();
			if ($child_pages !== []) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Repair stored menu when secondary pages are still saved at top level.
 * Runs synchronously so a missed cron job cannot leave the nav flat forever.
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

	$stored = (string) get_option('lf_header_menu_structure_version', '');
	if ($stored === LF_HEADER_MENU_STRUCTURE_VERSION && !lf_header_menu_structure_needs_repair($menu_id)) {
		return;
	}

	lf_header_menu_run_deferred_structure_repair($menu_id);
}
add_action('admin_init', 'lf_header_menu_force_structure_repair', 12);
add_action('wp', 'lf_header_menu_force_structure_repair', 5);

/**
 * Background menu structure repair (cron / async HTTP).
 */
function lf_header_menu_run_deferred_structure_repair(int $menu_id): void {
	if ($menu_id <= 0 || !function_exists('lf_header_menu_repair_nav_structure')) {
		return;
	}
	if (get_transient(LF_HEADER_MENU_REPAIR_LOCK)) {
		return;
	}
	set_transient(LF_HEADER_MENU_REPAIR_LOCK, 1, 10 * MINUTE_IN_SECONDS);

	try {
		if (function_exists('set_time_limit')) {
			@set_time_limit(300);
		}
		lf_header_menu_repair_nav_structure($menu_id);
		if (!lf_header_menu_structure_needs_repair($menu_id)) {
			update_option('lf_header_menu_structure_version', LF_HEADER_MENU_STRUCTURE_VERSION, false);
		}
	} catch (\Throwable $e) {
		if (function_exists('error_log')) {
			error_log('LF header menu deferred repair failed: ' . $e->getMessage());
		}
	} finally {
		delete_transient(LF_HEADER_MENU_REPAIR_LOCK);
	}
}
add_action(LF_HEADER_MENU_DEFERRED_REPAIR_HOOK, 'lf_header_menu_run_deferred_structure_repair', 10, 1);
