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
 * About page slugs — always top-level (never under More).
 *
 * @return list<string>
 */
function lf_header_menu_about_page_slugs(): array {
	$slugs = ['about-us', 'about'];

	return (array) apply_filters('lf_header_menu_about_page_slugs', $slugs);
}

/**
 * Published pages that make the More dropdown eligible (any one is enough).
 *
 * @return list<string>
 */
function lf_header_menu_more_gate_page_slugs(): array {
	$slugs = ['blog', 'reviews', 'contact'];

	return (array) apply_filters('lf_header_menu_more_gate_page_slugs', $slugs);
}

/**
 * Page slugs that belong under More when published.
 *
 * @return list<string>
 */
function lf_header_menu_more_child_page_slugs(): array {
	$slugs = [
		'why-choose-us',
		'contact',
		'blog',
		'reviews',
		'financing',
		'faq',
	];

	return (array) apply_filters('lf_header_menu_more_child_page_slugs', $slugs);
}

/**
 * @deprecated Use lf_header_menu_more_child_page_slugs().
 *
 * @return list<string>
 */
function lf_header_menu_more_secondary_page_slugs(): array {
	return lf_header_menu_more_child_page_slugs();
}

/**
 * Known More page titles (lowercase, normalized).
 *
 * @return list<string>
 */
function lf_header_menu_more_page_titles_normalized(): array {
	$titles = [
		'why choose us',
		'contact',
		'blog',
		'reviews',
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
		'why choose us'  => 'why-choose-us',
		'contact'        => 'contact',
		'blog'           => 'blog',
		'reviews'        => 'reviews',
		'financing'      => 'financing',
		'faq'            => 'faq',
		'faqs'           => 'faq',
		'projects'       => 'projects',
	];

	return (array) apply_filters('lf_header_menu_more_title_hints', $hints);
}

/**
 * Nav label for the About page.
 */
function lf_header_menu_about_nav_label(): string {
	return (string) apply_filters('lf_header_menu_about_nav_label', __('About', 'leadsforward-core'));
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
 * Published page for slug, or null.
 */
function lf_header_menu_published_page_for_slug(string $slug): ?\WP_Post {
	$page = get_page_by_path($slug);
	if (!$page instanceof \WP_Post || $page->post_status !== 'publish') {
		return null;
	}

	return $page;
}

/**
 * Whether the More dropdown should render (blog, reviews, or contact published).
 */
function lf_header_menu_more_is_enabled(): bool {
	foreach (lf_header_menu_more_gate_page_slugs() as $slug) {
		if (lf_header_menu_published_page_for_slug($slug) instanceof \WP_Post) {
			return (bool) apply_filters('lf_header_menu_more_is_enabled', true);
		}
	}

	return (bool) apply_filters('lf_header_menu_more_is_enabled', false);
}

/**
 * Published pages that should appear as More children.
 *
 * @return array<string, \WP_Post> slug => page
 */
function lf_header_menu_get_published_more_child_pages(): array {
	$pages = [];
	foreach (lf_header_menu_more_child_page_slugs() as $slug) {
		$page = lf_header_menu_published_page_for_slug($slug);
		if ($page instanceof \WP_Post) {
			$pages[$slug] = $page;
		}
	}

	if (post_type_exists('lf_project')) {
		$found = get_posts([
			'post_type'              => 'lf_project',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		]);
		if (is_array($found) && $found !== []) {
			$pages['projects'] = null;
		}
	}

	return $pages;
}

/**
 * Whether a page is the About page.
 */
function lf_header_menu_page_is_about(\WP_Post $page): bool {
	if ($page->post_type !== 'page') {
		return false;
	}
	if (in_array((string) $page->post_name, lf_header_menu_about_page_slugs(), true)) {
		return true;
	}
	$norm = lf_header_menu_normalize_more_label((string) $page->post_title);

	return in_array($norm, ['about', 'about us'], true);
}

/**
 * Whether a published page belongs in More (slug or post title).
 */
function lf_header_menu_page_belongs_in_more(\WP_Post $page): bool {
	if ($page->post_type !== 'page' || $page->post_status !== 'publish') {
		return false;
	}
	if (lf_header_menu_page_is_about($page)) {
		return false;
	}

	$slug = (string) $page->post_name;
	if (in_array($slug, lf_header_menu_more_child_page_slugs(), true)) {
		return true;
	}

	return lf_header_menu_more_title_matches((string) $page->post_title);
}

/**
 * Whether a menu item is the About link.
 */
function lf_header_menu_item_is_about(\WP_Post $item): bool {
	if (!$item instanceof \WP_Post) {
		return false;
	}
	$page = lf_header_menu_resolve_menu_item_page($item);
	if ($page instanceof \WP_Post && lf_header_menu_page_is_about($page)) {
		return true;
	}
	$norm = lf_header_menu_normalize_more_label((string) ($item->title ?? ''));

	return in_array($norm, ['about', 'about us'], true);
}

/**
 * Whether the menu already contains a link to a page ID.
 *
 * @param array<int, \WP_Post> $items
 */
function lf_header_menu_menu_has_page(array $items, int $page_id): bool {
	if ($page_id <= 0) {
		return false;
	}
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$page = lf_header_menu_resolve_menu_item_page($item);
		if ($page instanceof \WP_Post && (int) $page->ID === $page_id) {
			return true;
		}
	}

	return false;
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
	if (!lf_header_menu_more_is_enabled()) {
		return false;
	}
	if (lf_nav_menu_item_is_sync_preserved_cta($item)) {
		return false;
	}
	if (lf_header_menu_item_is_about($item)) {
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
 * Inject missing published About / More pages; bucket secondary links under More.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_objects_apply_nav_rules(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}

	$more_parent_id = lf_header_menu_find_more_parent_id($items);
	$more_enabled = lf_header_menu_more_is_enabled();

	// Pull About out of More; drop More subtree when gate is off.
	$filtered = [];
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$parent = (int) ($item->menu_item_parent ?? 0);
		$is_more_parent = lf_header_menu_item_has_class($item, 'lf-menu-more')
			|| lf_header_menu_normalize_more_label((string) ($item->title ?? '')) === 'more';
		if (!$more_enabled) {
			if ($is_more_parent && $parent === 0) {
				continue;
			}
			if ($more_parent_id > 0 && $parent === $more_parent_id) {
				continue;
			}
		}
		if ($more_parent_id > 0 && $parent === $more_parent_id && lf_header_menu_item_is_about($item)) {
			$item->menu_item_parent = 0;
		}
		$filtered[] = $item;
	}
	$items = $filtered;
	$more_parent_id = lf_header_menu_find_more_parent_id($items);

	// Ensure published About is top-level with nav label "About".
	foreach (lf_header_menu_about_page_slugs() as $slug) {
		$about_page = lf_header_menu_published_page_for_slug($slug);
		if (!$about_page instanceof \WP_Post) {
			continue;
		}
		if (!lf_header_menu_menu_has_page($items, (int) $about_page->ID)) {
			$about_item = lf_header_menu_synthetic_child(
				0,
				-5200,
				lf_header_menu_about_nav_label(),
				(string) get_permalink($about_page),
				['menu-item', 'lf-menu-about']
			);
			$about_item->object = 'page';
			$about_item->object_id = (int) $about_page->ID;
			$about_item->type = 'post_type';
			$about_item->menu_order = 115;
			$items[] = $about_item;
		}
		break;
	}

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if (lf_header_menu_item_is_about($item) && (int) ($item->menu_item_parent ?? 0) !== 0) {
			$item->menu_item_parent = 0;
		}
		if (lf_header_menu_item_is_about($item)) {
			$item->title = lf_header_menu_about_nav_label();
		}
	}

	if (!$more_enabled) {
		return $items;
	}

	$candidate_ids = [];
	$candidates = [];

	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if (lf_header_menu_item_has_class($item, 'lf-menu-more')) {
			if ((int) ($item->menu_item_parent ?? 0) === 0) {
				$more_parent_id = (int) $item->ID;
			}
			continue;
		}
		$title = lf_header_menu_normalize_more_label((string) ($item->title ?? ''));
		if ($title === 'more' && (int) ($item->menu_item_parent ?? 0) === 0) {
			$more_parent_id = (int) $item->ID;
			continue;
		}
	}

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
		$candidates[] = $item;
		$candidate_ids[(int) $item->ID] = true;
	}

	// Inject published More children missing from the menu.
	$synthetic_id = -5300;
	$child_pages = lf_header_menu_get_published_more_child_pages();
	foreach ($child_pages as $slug => $page) {
		if ($slug === 'projects') {
			$archive = get_post_type_archive_link('lf_project');
			if ($archive === false) {
				continue;
			}
			$has = false;
			foreach ($items as $item) {
				if (!$item instanceof \WP_Post) {
					continue;
				}
				$url = trim((string) ($item->url ?? ''));
				if ($url !== '' && untrailingslashit($url) === untrailingslashit((string) $archive)) {
					$has = true;
					break;
				}
			}
			if ($has) {
				continue;
			}
			$candidates[] = lf_header_menu_synthetic_child(
				0,
				$synthetic_id--,
				__('Projects', 'leadsforward-core'),
				(string) $archive,
				['menu-item', 'lf-menu-more-child']
			);
			continue;
		}
		if (!$page instanceof \WP_Post) {
			continue;
		}
		if (lf_header_menu_menu_has_page($items, (int) $page->ID)) {
			continue;
		}
		$child = lf_header_menu_synthetic_child(
			0,
			$synthetic_id--,
			(string) $page->post_title,
			(string) get_permalink($page),
			['menu-item', 'lf-menu-more-child']
		);
		$child->object = 'page';
		$child->object_id = (int) $page->ID;
		$child->type = 'post_type';
		$candidates[] = $child;
	}

	if ($candidates === []) {
		// Remove orphan More parent.
		if ($more_parent_id > 0) {
			$items = array_values(array_filter(
				$items,
				static fn ($item): bool => $item instanceof \WP_Post && (int) ($item->ID ?? 0) !== $more_parent_id
			));
		}
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

/**
 * Move published secondary pages from top-level into More; remove empty More parent.
 */
function lf_header_menu_consolidate_secondary_into_more(int $menu_id): void {
	if ($menu_id <= 0 || !function_exists('wp_update_nav_menu_item') || !function_exists('wp_get_nav_menu_items')) {
		return;
	}

	if (!lf_header_menu_more_is_enabled()) {
		$items = wp_get_nav_menu_items($menu_id);
		if (!is_array($items)) {
			return;
		}
		$more_parent_id = lf_header_menu_find_more_parent_id($items);
		if ($more_parent_id > 0) {
			wp_delete_post($more_parent_id, true);
		}
		return;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || $items === []) {
		return;
	}

	$more_parent_id = lf_header_menu_find_more_parent_id($items);

	// Ensure About is top-level with correct label.
	foreach (lf_header_menu_about_page_slugs() as $slug) {
		$about_page = lf_header_menu_published_page_for_slug($slug);
		if (!$about_page instanceof \WP_Post) {
			continue;
		}
		$about_item_id = 0;
		foreach ($items as $item) {
			if (!$item instanceof \WP_Post) {
				continue;
			}
			$page = lf_header_menu_resolve_menu_item_page($item);
			if ($page instanceof \WP_Post && (int) $page->ID === (int) $about_page->ID) {
				$about_item_id = (int) $item->ID;
				$about_label = lf_header_menu_about_nav_label();
				$needs_about_update = (int) ($item->menu_item_parent ?? 0) !== 0
					|| trim(wp_strip_all_tags((string) ($item->title ?? ''))) !== $about_label;
				if ($needs_about_update) {
					$args = lf_nav_menu_item_build_update_args($item);
					$args['menu-item-title'] = $about_label;
					$args['menu-item-parent-id'] = 0;
					wp_update_nav_menu_item($menu_id, $about_item_id, $args);
				}
				break;
			}
		}
		if ($about_item_id <= 0 && function_exists('lf_sitemap_sync_add_post_menu_item')) {
			$cursor = 0;
			foreach ($items as $it) {
				if ($it instanceof \WP_Post) {
					$cursor = max($cursor, (int) ($it->menu_order ?? 0));
				}
			}
			lf_sitemap_sync_add_post_menu_item($menu_id, $cursor + 1, [
				'post_id' => (int) $about_page->ID,
				'title' => lf_header_menu_about_nav_label(),
				'parent_item_id' => 0,
				'object' => 'page',
				'classes' => 'lf-menu-about',
			]);
			$items = wp_get_nav_menu_items($menu_id) ?: [];
		}
		break;
	}

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

	// Add missing published More children.
	$child_pages = lf_header_menu_get_published_more_child_pages();
	foreach ($child_pages as $slug => $page) {
		if ($slug === 'projects') {
			continue;
		}
		if (!$page instanceof \WP_Post || lf_header_menu_menu_has_page($items, (int) $page->ID)) {
			continue;
		}
		$cursor = 0;
		foreach ($items as $it) {
			if ($it instanceof \WP_Post) {
				$cursor = max($cursor, (int) ($it->menu_order ?? 0));
			}
		}
		if (!function_exists('lf_sitemap_sync_add_post_menu_item')) {
			continue;
		}
		$new_id = lf_sitemap_sync_add_post_menu_item($menu_id, $cursor + 1, [
			'post_id' => (int) $page->ID,
			'title' => (string) $page->post_title,
			'parent_item_id' => 0,
			'object' => 'page',
			'classes' => 'lf-menu-more-child',
		]);
		if ($new_id > 0) {
			$fresh = get_post($new_id);
			if ($fresh instanceof \WP_Post) {
				$to_move[] = $fresh;
			}
			$items = wp_get_nav_menu_items($menu_id) ?: [];
		}
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

	if (!lf_header_menu_more_is_enabled()) {
		lf_header_menu_consolidate_secondary_into_more($menu_id);
		return;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return;
	}

	$needs_repair = false;
	foreach (lf_header_menu_get_published_more_child_pages() as $slug => $page) {
		if ($slug === 'projects') {
			continue;
		}
		if ($page instanceof \WP_Post && !lf_header_menu_menu_has_page($items, (int) $page->ID)) {
			$needs_repair = true;
			break;
		}
	}

	$more_parent_id = lf_header_menu_find_more_parent_id($items);
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) === 0 && lf_header_menu_item_belongs_in_more($item)) {
			$needs_repair = true;
			break;
		}
		if (lf_header_menu_item_is_about($item) && (int) ($item->menu_item_parent ?? 0) !== 0) {
			$needs_repair = true;
			break;
		}
	}

	if ($more_parent_id > 0) {
		$more_children = 0;
		foreach ($items as $item) {
			if ($item instanceof \WP_Post && (int) ($item->menu_item_parent ?? 0) === $more_parent_id) {
				++$more_children;
			}
		}
		if ($more_children === 0) {
			$needs_repair = true;
		}
	}

	if ($needs_repair) {
		lf_header_menu_consolidate_secondary_into_more($menu_id);
	}
}
// Persist only during admin saves / menu sync — not on public page views (was causing DB churn + fatals).
// add_action('wp', 'lf_header_menu_maybe_persist_more_consolidation', 14);

/**
 * During sitemap menu build, remap secondary pages into the More group (About stays top-level).
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function lf_header_menu_remap_items_to_more_group(array $items): array {
	foreach ($items as &$row) {
		if (!is_array($row)) {
			continue;
		}
		$slug = trim((string) ($row['slug'] ?? '/'), '/');
		$group = (string) ($row['group'] ?? '');
		$depth = (int) ($row['depth'] ?? 0);
		$title = lf_header_menu_normalize_more_label((string) ($row['title'] ?? ''));

		if ($group === 'About' || in_array($slug, lf_header_menu_about_page_slugs(), true) || in_array($title, ['about', 'about us'], true)) {
			$row['group'] = 'About';
			$row['depth'] = 0;
			$row['title'] = lf_header_menu_about_nav_label();
			continue;
		}

		if (in_array($slug, lf_header_menu_more_child_page_slugs(), true) || lf_header_menu_more_title_matches($title)) {
			$row['group'] = 'More';
			if ($depth === 0) {
				$row['depth'] = 1;
			}
		}
	}
	unset($row);

	return $items;
}
