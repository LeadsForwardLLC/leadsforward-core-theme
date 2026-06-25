<?php
/**
 * Header "More" menu: bucket secondary pages under a single dropdown when published.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Page slugs that belong under the More dropdown (not top-level), when published.
 *
 * @return list<string>
 */
function lf_header_menu_more_secondary_page_slugs(): array {
	$slugs = [
		'about-us',
		'why-choose-us',
		'financing',
		'faq',
		'blog',
		'contact',
	];

	return (array) apply_filters('lf_header_menu_more_secondary_page_slugs', $slugs);
}

/**
 * Whether a menu item should live under More (published page or projects archive).
 */
function lf_header_menu_item_belongs_in_more(\WP_Post $item): bool {
	if (!$item instanceof \WP_Post) {
		return false;
	}
	if (lf_nav_menu_item_is_sync_preserved_cta($item)) {
		return false;
	}
	if (lf_header_menu_item_has_class($item, 'lf-menu-more')) {
		return false;
	}
	if (lf_header_menu_item_has_class($item, 'lf-menu-services-parent')
		|| lf_header_menu_item_has_class($item, 'lf-menu-areas-parent')
		|| lf_header_menu_item_has_class($item, 'lf-menu-group-parent')) {
		return false;
	}

	$object = (string) ($item->object ?? '');
	if ($object === 'page') {
		$page_id = (int) ($item->object_id ?? 0);
		if ($page_id <= 0) {
			return false;
		}
		$post = get_post($page_id);
		if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
			return false;
		}
		$slug = (string) $post->post_name;
		return in_array($slug, lf_header_menu_more_secondary_page_slugs(), true);
	}

	if ($object === 'custom') {
		$title = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
		if ($title === 'projects') {
			$url = trim((string) ($item->url ?? ''));
			$archive = get_post_type_archive_link('lf_project');
			return $archive !== false && $url !== '' && untrailingslashit($url) === untrailingslashit((string) $archive);
		}
	}

	return false;
}

/**
 * Find existing More parent menu item ID.
 */
function lf_header_menu_find_more_parent_id(array $items): int {
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		if (lf_header_menu_item_has_class($item, 'lf-menu-more')) {
			return (int) $item->ID;
		}
		$title = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
		if ($title === 'more') {
			return (int) $item->ID;
		}
	}
	return 0;
}

/**
 * Move published secondary pages from top-level into More; remove empty More parent.
 */
function lf_header_menu_consolidate_secondary_into_more(int $menu_id): void {
	if ($menu_id <= 0 || !function_exists('wp_update_nav_menu_item') || !function_exists('wp_get_nav_menu_items')) {
		return;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || $items === []) {
		return;
	}

	$to_move = [];
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		if (!lf_header_menu_item_belongs_in_more($item)) {
			continue;
		}
		$to_move[] = $item;
	}

	$more_parent_id = lf_header_menu_find_more_parent_id($items);

	if ($to_move === [] && $more_parent_id <= 0) {
		return;
	}

	if ($to_move !== [] && $more_parent_id <= 0) {
		$position = 0;
		foreach ($items as $it) {
			if ($it instanceof \WP_Post) {
				$position = max($position, (int) ($it->menu_order ?? 0));
			}
		}
		$more_parent_id = (int) wp_update_nav_menu_item($menu_id, 0, [
			'menu-item-title'     => __('More', 'leadsforward-core'),
			'menu-item-url'       => '#',
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'custom',
			'menu-item-object'    => 'custom',
			'menu-item-object-id' => 0,
			'menu-item-parent-id' => 0,
			'menu-item-classes'   => 'lf-menu-more menu-item-has-children',
			'menu-item-position'  => $position + 1,
		]);
		if ($more_parent_id <= 0) {
			return;
		}
	}

	if ($more_parent_id <= 0) {
		return;
	}

	$child_order = 0;
	foreach ($items as $it) {
		if ($it instanceof \WP_Post && (int) ($it->menu_item_parent ?? 0) === $more_parent_id) {
			$child_order = max($child_order, (int) ($it->menu_order ?? 0));
		}
	}

	foreach ($to_move as $item) {
		++$child_order;
		$args = lf_nav_menu_item_build_update_args($item);
		$args['menu-item-parent-id'] = $more_parent_id;
		$args['menu-item-position'] = $child_order;
		wp_update_nav_menu_item($menu_id, (int) $item->ID, $args);
	}

	$items = wp_get_nav_menu_items($menu_id) ?: [];
	$more_children = 0;
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== $more_parent_id) {
			continue;
		}
		++$more_children;
	}

	if ($more_children === 0) {
		wp_delete_post($more_parent_id, true);
		return;
	}

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post || (int) $item->ID !== $more_parent_id) {
			continue;
		}
		$classes = lf_nav_menu_item_class_list($item);
		$classes = array_unique(array_merge($classes, ['lf-menu-more', 'menu-item-has-children']));
		wp_update_nav_menu_item($menu_id, $more_parent_id, [
			'menu-item-title'     => __('More', 'leadsforward-core'),
			'menu-item-url'       => '#',
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'custom',
			'menu-item-object'    => 'custom',
			'menu-item-object-id' => 0,
			'menu-item-parent-id' => 0,
			'menu-item-classes'   => implode(' ', $classes),
			'menu-item-position'  => (int) ($item->menu_order ?? 0),
		]);
		break;
	}
}

/**
 * During sitemap menu build, remap About-group and secondary pages into the More group.
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function lf_header_menu_remap_items_to_more_group(array $items): array {
	$secondary = lf_header_menu_more_secondary_page_slugs();
	foreach ($items as &$row) {
		if (!is_array($row)) {
			continue;
		}
		$slug = trim((string) ($row['slug'] ?? '/'), '/');
		$group = (string) ($row['group'] ?? '');
		$depth = (int) ($row['depth'] ?? 0);

		if ($group === 'About' || in_array($slug, $secondary, true)) {
			$row['group'] = 'More';
			if ($depth === 0) {
				$row['depth'] = 1;
			}
		}
	}
	unset($row);

	return $items;
}
