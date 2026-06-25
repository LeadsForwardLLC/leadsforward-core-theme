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
 * Known More page titles (lowercase, normalized). Primary trigger is published page title.
 *
 * @return list<string>
 */
function lf_header_menu_more_page_titles_normalized(): array {
	$titles = [
		'about us',
		'why choose us',
		'contact',
		'blog',
		'financing',
		'faq',
		'faqs',
		'projects',
	];

	return (array) apply_filters('lf_header_menu_more_page_titles_normalized', $titles);
}

/**
 * Title hints for pages that belong under More (fallback when slug differs).
 *
 * @return array<string, string> lowercase title => slug
 */
function lf_header_menu_more_title_hints(): array {
	$hints = [
		'about us'       => 'about-us',
		'why choose us'  => 'why-choose-us',
		'contact'        => 'contact',
		'blog'           => 'blog',
		'financing'      => 'financing',
		'faq'            => 'faq',
		'faqs'           => 'faq',
		'projects'       => 'projects',
	];

	return (array) apply_filters('lf_header_menu_more_title_hints', $hints);
}

/**
 * Normalize a menu/page label for title comparison.
 */
function lf_header_menu_normalize_more_label(string $label): string {
	$label = html_entity_decode(wp_strip_all_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$label = preg_replace('/\s+/', ' ', $label) ?? $label;

	return strtolower(trim($label));
}

/**
 * Whether a normalized title belongs in More.
 */
function lf_header_menu_more_title_matches(string $title): bool {
	$norm = lf_header_menu_normalize_more_label($title);
	if ($norm === '') {
		return false;
	}

	return in_array($norm, lf_header_menu_more_page_titles_normalized(), true);
}

/**
 * Whether a published page belongs in More (slug or post title).
 */
function lf_header_menu_page_belongs_in_more(\WP_Post $page): bool {
	if ($page->post_type !== 'page' || $page->post_status !== 'publish') {
		return false;
	}

	$slug = (string) $page->post_name;
	if (in_array($slug, lf_header_menu_more_secondary_page_slugs(), true)) {
		return true;
	}

	return lf_header_menu_more_title_matches((string) $page->post_title);
}

/**
 * Resolve the WordPress page for a nav menu item (page object or custom URL).
 */
function lf_header_menu_resolve_menu_item_page(\WP_Post $item): ?\WP_Post {
	$object = (string) ($item->object ?? '');
	if ($object === 'page') {
		$page_id = (int) ($item->object_id ?? 0);
		if ($page_id <= 0) {
			return null;
		}
		$post = get_post($page_id);
		return $post instanceof \WP_Post ? $post : null;
	}

	if ($object === 'custom') {
		$url = trim((string) ($item->url ?? ''));
		if ($url === '' || $url === '#') {
			return null;
		}
		$page_id = (int) url_to_postid($url);
		if ($page_id <= 0) {
			return null;
		}
		$post = get_post($page_id);
		return ($post instanceof \WP_Post && $post->post_type === 'page') ? $post : null;
	}

	return null;
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

	$page = lf_header_menu_resolve_menu_item_page($item);
	if ($page instanceof \WP_Post) {
		return lf_header_menu_page_belongs_in_more($page);
	}

	$object = (string) ($item->object ?? '');
	if ($object === 'custom') {
		$title = (string) ($item->title ?? '');
		if (!lf_header_menu_more_title_matches($title)) {
			return false;
		}
		$norm = lf_header_menu_normalize_more_label($title);
		if ($norm === 'projects') {
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
		$title = lf_header_menu_normalize_more_label((string) ($item->title ?? ''));
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

	$more_parent_id = lf_header_menu_find_more_parent_id($items);
	$to_move = [];

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if (!lf_header_menu_item_belongs_in_more($item)) {
			continue;
		}
		if ($more_parent_id > 0 && (int) ($item->menu_item_parent ?? 0) === $more_parent_id) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		$to_move[] = $item;
	}

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

	$moved_ids = [];
	foreach ($to_move as $item) {
		$moved_ids[(int) $item->ID] = true;
	}
	$items = wp_get_nav_menu_items($menu_id) ?: [];
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$parent = (int) ($item->menu_item_parent ?? 0);
		if ($parent === 0 || !isset($moved_ids[$parent])) {
			continue;
		}
		$args = lf_nav_menu_item_build_update_args($item);
		$args['menu-item-parent-id'] = $more_parent_id;
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
 * Persist More bucketing whenever a published secondary page is still top-level in the DB menu.
 */
function lf_header_menu_maybe_persist_more_consolidation(): void {
	if (is_admin() || !has_nav_menu('header_menu')) {
		return;
	}

	$locations = get_nav_menu_locations();
	$menu_id = (int) ($locations['header_menu'] ?? 0);
	if ($menu_id <= 0 || !function_exists('lf_header_menu_consolidate_secondary_into_more')) {
		return;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return;
	}

	$more_parent_id = lf_header_menu_find_more_parent_id($items);
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
		lf_header_menu_consolidate_secondary_into_more($menu_id);
		return;
	}

	if ($more_parent_id > 0) {
		$child_count = 0;
		foreach ($items as $item) {
			if (!$item instanceof \WP_Post) {
				continue;
			}
			if ((int) ($item->menu_item_parent ?? 0) === $more_parent_id) {
				++$child_count;
			}
		}
		if ($child_count === 0) {
			lf_header_menu_consolidate_secondary_into_more($menu_id);
		}
	}
}
add_action('wp', 'lf_header_menu_maybe_persist_more_consolidation', 14);

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
		$title = lf_header_menu_normalize_more_label((string) ($row['title'] ?? ''));

		if ($group === 'About' || in_array($slug, $secondary, true) || lf_header_menu_more_title_matches($title)) {
			$row['group'] = 'More';
			if ($depth === 0) {
				$row['depth'] = 1;
			}
		}
	}
	unset($row);

	return $items;
}
