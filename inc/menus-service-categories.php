<?php
/**
 * Foundation-repair Services menu: auto-group service links under category flyouts.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Whether the active niche should use categorized Services submenus.
 */
function lf_services_menu_categories_enabled(): bool {
	$niche = (string) get_option('lf_homepage_niche_slug', '');
	if ($niche === '' && function_exists('lf_default_niche_slug')) {
		$niche = (string) lf_default_niche_slug();
	}

	return (bool) apply_filters('lf_services_menu_use_categories', $niche === 'foundation-repair', $niche);
}

/**
 * @return array<string, string> slug => label
 */
function lf_services_menu_category_definitions(): array {
	$defs = [
		'foundation-repair' => __('Foundation Repair', 'leadsforward-core'),
		'waterproofing'     => __('Waterproofing / Basement Waterproofing', 'leadsforward-core'),
		'crawl-space'       => __('Crawl Space Services', 'leadsforward-core'),
	];

	return (array) apply_filters('lf_services_menu_category_definitions', $defs);
}

/**
 * Classify a service title/slug into a menu category slug, or empty for standalone.
 */
function lf_foundation_repair_service_menu_category_slug(string $title, string $slug = ''): string {
	$hay = strtolower(trim($title . ' ' . $slug));
	$hay = preg_replace('/[^a-z0-9]+/', ' ', $hay);
	$hay = trim((string) $hay);

	if ($hay !== '' && preg_match('/\b(crawl\s*space|crawlspace)\b/', $hay)) {
		return 'crawl-space';
	}
	if ($hay !== '' && preg_match('/\b(waterproof|flooded|leaking|sump\s*pump|drainage|basement\s*water|water\s*intrusion|seepage|wet\s*basement|basement\s*repair)\b/', $hay)) {
		return 'waterproofing';
	}
	if ($hay !== '' && preg_match('/\b(foundation|crack|slab|pier|beam|settlement|structural|bowing|helical|push\s*pin|basement\s*wall|wall\s*repair|wall\s*stabil)\b/', $hay)) {
		return 'foundation-repair';
	}

	return '';
}

/**
 * Find the top-level Services menu item ID.
 */
function lf_header_menu_services_parent_id(array $items): int {
	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($menu_item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		$title = strtolower(trim(wp_strip_all_tags((string) ($menu_item->title ?? ''))));
		if ($title === 'services' || lf_header_menu_item_has_class($menu_item, 'lf-menu-services-parent')) {
			return (int) ($menu_item->ID ?? 0);
		}
	}

	return 0;
}

/**
 * Minimum service links before auto-grouping kicks in (lower when mega menu is active).
 */
function lf_services_menu_category_min_services(): int {
	$min = 4;
	if (function_exists('lf_mega_menu_enabled') && lf_mega_menu_enabled()) {
		$min = 3;
	}

	return (int) apply_filters('lf_services_menu_category_min_count', $min);
}

/**
 * Whether Services menu items are already grouped under category parents with children.
 *
 * @param array<int, \WP_Post> $items
 */
function lf_header_menu_services_category_tree_is_built(array $items, int $services_parent_id): bool {
	$category_ids = [];
	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($menu_item->menu_item_parent ?? 0) !== $services_parent_id) {
			continue;
		}
		if (lf_header_menu_item_has_class($menu_item, 'lf-menu-service-category')) {
			$category_ids[(int) ($menu_item->ID ?? 0)] = false;
		}
	}
	if ($category_ids === []) {
		return false;
	}

	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		if ((string) ($menu_item->object ?? '') !== 'lf_service') {
			continue;
		}
		$parent = (int) ($menu_item->menu_item_parent ?? 0);
		if (isset($category_ids[$parent])) {
			$category_ids[$parent] = true;
		}
	}

	foreach ($category_ids as $has_children) {
		if ($has_children) {
			return true;
		}
	}

	return false;
}

/**
 * Remove stale Services category headers that have no nested service children.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_strip_stale_service_categories(array $items, int $services_parent_id): array {
	$stale_ids = [];
	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($menu_item->menu_item_parent ?? 0) !== $services_parent_id) {
			continue;
		}
		if (lf_header_menu_item_has_class($menu_item, 'lf-menu-service-category')) {
			$stale_ids[(int) ($menu_item->ID ?? 0)] = true;
		}
	}
	if ($stale_ids === []) {
		return $items;
	}

	return array_values(array_filter(
		$items,
		static function (\WP_Post $menu_item) use ($stale_ids): bool {
			$id = (int) ($menu_item->ID ?? 0);
			return $id === 0 || !isset($stale_ids[$id]);
		}
	));
}

/**
 * Group flat lf_service children under synthetic category parents (render-time only).
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_categorize_foundation_services(array $items, $args): array {
	if (!lf_services_menu_categories_enabled()) {
		return $items;
	}
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}
	if (function_exists('lf_header_menu_cpt_nav_dropdown_enabled') && !lf_header_menu_cpt_nav_dropdown_enabled('services')) {
		return $items;
	}

	$services_parent_id = lf_header_menu_services_parent_id($items);
	if ($services_parent_id <= 0) {
		return $items;
	}

	if (lf_header_menu_services_category_tree_is_built($items, $services_parent_id)) {
		return $items;
	}
	$items = lf_header_menu_strip_stale_service_categories($items, $services_parent_id);

	$service_children = [];
	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($menu_item->menu_item_parent ?? 0) !== $services_parent_id) {
			continue;
		}
		if (lf_header_menu_item_has_class($menu_item, 'lf-submenu-all-link')
			|| lf_header_menu_item_has_class($menu_item, 'lf-submenu-divider')) {
			continue;
		}
		if ((string) ($menu_item->object ?? '') !== 'lf_service') {
			continue;
		}
		$service_children[] = $menu_item;
	}

	$min_services = lf_services_menu_category_min_services();
	if (count($service_children) < $min_services) {
		return $items;
	}

	$bucketed = [
		'foundation-repair' => [],
		'waterproofing'     => [],
		'crawl-space'       => [],
	];
	foreach ($service_children as $child) {
		$slug = '';
		$object_id = (int) ($child->object_id ?? 0);
		if ($object_id > 0) {
			$post = get_post($object_id);
			if ($post instanceof \WP_Post) {
				$slug = (string) $post->post_name;
			}
		}
		$cat = lf_foundation_repair_service_menu_category_slug((string) ($child->title ?? ''), $slug);
		if ($cat === '' || !isset($bucketed[ $cat ])) {
			$cat = 'foundation-repair';
		}
		$bucketed[ $cat ][] = $child;
	}

	$filled_categories = array_filter($bucketed, static function (array $rows): bool {
		return $rows !== [];
	});
	if ($filled_categories === []) {
		return $items;
	}

	$defs = lf_services_menu_category_definitions();
	$synthetic_id = -2000;
	$category_items = [];
	$order_base = 10;

	foreach ($defs as $cat_slug => $cat_label) {
		$kids = $bucketed[ $cat_slug ] ?? [];
		if ($kids === []) {
			continue;
		}

		usort(
			$kids,
			static function (\WP_Post $a, \WP_Post $b): int {
				return strcasecmp(
					(string) ($a->title ?? ''),
					(string) ($b->title ?? '')
				);
			}
		);

		$cat_parent_id = $synthetic_id--;
		$cat_item = lf_header_menu_synthetic_child(
			$services_parent_id,
			$cat_parent_id,
			$cat_label,
			'#',
			[
				'menu-item',
				'lf-menu-service-category',
				'menu-item-has-children',
				'lf-menu-cat--' . sanitize_html_class($cat_slug),
			]
		);
		$cat_item->menu_order = $order_base;
		$order_base += 10;
		$category_items[] = $cat_item;

		$child_order = 1;
		foreach ($kids as $kid) {
			$kid->menu_item_parent = $cat_parent_id;
			$kid->menu_order = ($order_base - 10) + $child_order;
			++$child_order;
		}
	}

	foreach ($category_items as $cat_item) {
		$items[] = $cat_item;
	}

	return $items;
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_categorize_foundation_services', 12, 2);
