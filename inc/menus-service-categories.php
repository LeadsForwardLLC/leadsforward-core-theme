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
		'waterproofing'     => __('Waterproofing', 'leadsforward-core'),
		'crawl-space'       => __('Crawl Space', 'leadsforward-core'),
	];

	return (array) apply_filters('lf_services_menu_category_definitions', $defs);
}

/**
 * Classify a service title/slug into a menu category slug.
 */
function lf_foundation_repair_service_menu_category_slug(string $title, string $slug = ''): string {
	$hay = strtolower(trim($title . ' ' . $slug));
	$hay = preg_replace('/[^a-z0-9]+/', ' ', $hay);
	$hay = trim((string) $hay);

	if ($hay !== '' && preg_match('/\b(crawl\s*space|crawlspace)\b/', $hay)) {
		return 'crawl-space';
	}
	if ($hay !== '' && preg_match('/\b(waterproof|flooded|leaking|sump\s*pump|drainage|basement\s*water|water\s*intrusion|seepage|wet\s*basement)\b/', $hay)) {
		return 'waterproofing';
	}

	return 'foundation-repair';
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

	$services_parent_id = lf_header_menu_services_parent_id($items);
	if ($services_parent_id <= 0) {
		return $items;
	}

	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($menu_item->menu_item_parent ?? 0) === $services_parent_id
			&& lf_header_menu_item_has_class($menu_item, 'lf-menu-service-category')) {
			return $items;
		}
	}

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

	if (count($service_children) < 4) {
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
		if (!isset($bucketed[ $cat ])) {
			$cat = 'foundation-repair';
		}
		$bucketed[ $cat ][] = $child;
	}

	$filled_categories = array_filter($bucketed, static function (array $rows): bool {
		return $rows !== [];
	});
	if (count($filled_categories) < 2) {
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
add_filter('wp_nav_menu_objects', 'lf_header_menu_categorize_foundation_services', 9, 2);
