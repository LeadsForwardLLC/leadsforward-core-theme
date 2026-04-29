<?php
/**
 * Sitemap-driven menu build (Header Menu).
 *
 * Task 4: Build/update Header Menu from Airtable sitemap specs (menu group + hierarchy + priority),
 * include only published pages, and keep "More" rightmost.
 *
 * @package LeadsForward_Core
 * @since 0.1.82
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @return bool True when menu build is enabled.
 */
function lf_sitemap_menu_enable(): bool {
	$raw = get_option('lf_sitemap_menu_enable', '1');
	return (string) $raw !== '0';
}

/**
 * @return array<string,array{post_id:int,status:string,type:string}>
 */
function lf_sitemap_sync_get_page_index(): array {
	$raw = (string) get_option('lf_sitemap_page_index', '{}');
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return [];
	}
	$out = [];
	foreach ($decoded as $slug => $row) {
		if (!is_string($slug) || !is_array($row)) {
			continue;
		}
		$out[$slug] = [
			'post_id' => (int) ($row['post_id'] ?? 0),
			'status' => (string) ($row['status'] ?? ''),
			'type' => (string) ($row['type'] ?? ''),
		];
	}
	return $out;
}

/**
 * @param array<string,mixed> $spec
 * @return array{ok:bool, slug:string, error:string}
 */
function lf_sitemap_sync_spec_resolved_slug(array $spec): array {
	$template = (string) ($spec['slug_template'] ?? '');
	$city = function_exists('lf_sitemap_sync_get_primary_city') ? lf_sitemap_sync_get_primary_city() : '';
	return function_exists('lf_sitemap_resolve_slug_template')
		? lf_sitemap_resolve_slug_template($template, $city)
		: ['ok' => false, 'slug' => '/', 'error' => 'missing_slug_resolver'];
}

/**
 * @param string $hierarchy
 * @return list<string>
 */
function lf_sitemap_sync_parse_hierarchy(string $hierarchy): array {
	$hierarchy = trim(preg_replace('/\s+/', ' ', $hierarchy) ?? '');
	if ($hierarchy === '') {
		return [];
	}
	$parts = preg_split('/\s*>\s*/', $hierarchy) ?: [];
	$out = [];
	foreach ($parts as $p) {
		$p = trim((string) $p);
		if ($p !== '') {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Airtable "Menu hierarchy" depth:
 * - Parent => 0
 * - Parent > Child 1 => 1
 * - Parent > Child 1 > Child 2 => 2
 *
 * @param list<string> $parts
 */
function lf_sitemap_sync_hierarchy_depth(array $parts): int {
	$depth = count($parts) - 1;
	return $depth > 0 ? $depth : 0;
}

/**
 * @return array{ok:bool, menu_id:int, created:bool, assigned:bool, error:string}
 */
function lf_sitemap_sync_ensure_header_menu(): array {
	if (!function_exists('wp_get_nav_menus') || !function_exists('wp_update_nav_menu_item')) {
		return ['ok' => false, 'menu_id' => 0, 'created' => false, 'assigned' => false, 'error' => 'menus_api_missing'];
	}

	$menu_name = 'Header Menu';
	$menu_id = 0;
	foreach (wp_get_nav_menus() as $m) {
		if (isset($m->name) && $m->name === $menu_name) {
			$menu_id = (int) $m->term_id;
			break;
		}
	}

	$created = false;
	if ($menu_id <= 0) {
		$new_id = wp_create_nav_menu($menu_name);
		if (is_wp_error($new_id)) {
			return ['ok' => false, 'menu_id' => 0, 'created' => false, 'assigned' => false, 'error' => (string) $new_id->get_error_message()];
		}
		$menu_id = (int) $new_id;
		$created = true;
	}

	$locations = get_theme_mod('nav_menu_locations') ?: [];
	$assigned = false;
	if (!isset($locations['header_menu']) || (int) $locations['header_menu'] !== $menu_id) {
		$locations['header_menu'] = $menu_id;
		set_theme_mod('nav_menu_locations', $locations);
		$assigned = true;
	}

	return ['ok' => true, 'menu_id' => $menu_id, 'created' => $created, 'assigned' => $assigned, 'error' => ''];
}

/**
 * Preserve existing CTA buttons created by wizard/setup-runner.
 *
 * @param int $menu_id
 * @return list<WP_Post>
 */
function lf_sitemap_sync_get_preserved_header_items(int $menu_id): array {
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return [];
	}
	$preserve_classes = ['lf-menu-call' => true, 'lf-menu-cta' => true];
	$out = [];
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		$classes = $item->classes ?? [];
		if (!is_array($classes)) {
			$classes = is_string($classes) ? preg_split('/\s+/', $classes) : [];
		}
		$keep = false;
		foreach ($classes as $c) {
			$c = (string) $c;
			if ($c !== '' && isset($preserve_classes[$c])) {
				$keep = true;
				break;
			}
		}
		if ($keep) {
			$out[] = $item;
		}
	}
	return $out;
}

/**
 * Delete all menu items except preserved CTA items.
 */
function lf_sitemap_sync_clear_header_menu_items(int $menu_id): void {
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || empty($items)) {
		return;
	}
	$preserved = lf_sitemap_sync_get_preserved_header_items($menu_id);
	$preserved_ids = [];
	foreach ($preserved as $p) {
		$preserved_ids[(int) $p->ID] = true;
	}

	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if (!empty($preserved_ids[(int) $item->ID])) {
			continue;
		}
		wp_delete_post((int) $item->ID, true);
	}
}

/**
 * @param int $menu_id
 * @param int $position
 * @param array{post_id:int,title:string,parent_item_id?:int,classes?:string} $node
 * @return int Menu item ID or 0.
 */
function lf_sitemap_sync_add_post_menu_item(int $menu_id, int &$position, array $node): int {
	$post_id = (int) ($node['post_id'] ?? 0);
	if ($post_id <= 0) {
		return 0;
	}
	$post = get_post($post_id);
	if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
		return 0;
	}
	$title = (string) ($node['title'] ?? '');
	if ($title === '') {
		$title = get_the_title($post_id);
	}
	$object = sanitize_key((string) ($node['object'] ?? 'page'));
	if ($object === '') {
		$object = 'page';
	}
	$parent_item_id = (int) ($node['parent_item_id'] ?? 0);
	$classes = (string) ($node['classes'] ?? '');
	$id = wp_update_nav_menu_item($menu_id, 0, [
		'menu-item-title'     => $title,
		'menu-item-url'       => get_permalink($post_id),
		'menu-item-type'      => 'post_type',
		'menu-item-object'    => $object,
		'menu-item-object-id' => $post_id,
		'menu-item-status'    => 'publish',
		'menu-item-parent-id' => $parent_item_id,
		'menu-item-position'  => $position++,
		'menu-item-classes'   => $classes,
	]);
	return is_wp_error($id) ? 0 : (int) $id;
}

/**
 * Ensure a menu group exists and optionally attach CPT children under it.
 *
 * @param array{label:string, page_slug:string, child_post_type?:string, child_limit?:int, child_class?:string} $group
 */
function lf_sitemap_sync_enforce_group_dropdown(int $menu_id, array $group): void {
	$label = trim((string) ($group['label'] ?? ''));
	$page_slug = sanitize_title((string) ($group['page_slug'] ?? ''));
	if ($label === '' || $page_slug === '') {
		return;
	}
	$page = get_page_by_path($page_slug);
	if (!$page instanceof WP_Post || $page->post_status !== 'publish') {
		return;
	}
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		$items = [];
	}

	$parent_item_id = 0;
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		$is_top = (int) ($item->menu_item_parent ?? 0) === 0;
		if (!$is_top) {
			continue;
		}
		$title = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
		if ($title === strtolower($label)) {
			$parent_item_id = (int) $item->ID;
			break;
		}
	}

	$position = 0;
	if ($items !== []) {
		foreach ($items as $item) {
			if ($item instanceof WP_Post) {
				$position = max($position, (int) ($item->menu_order ?? 0));
			}
		}
		$position++;
	}

	if ($parent_item_id <= 0) {
		$parent_item_id = lf_sitemap_sync_add_post_menu_item($menu_id, $position, [
			'post_id' => (int) $page->ID,
			'title' => $label,
			'parent_item_id' => 0,
			'object' => 'page',
			'classes' => '',
		]);
		if ($parent_item_id <= 0) {
			return;
		}
	}

	$child_post_type = sanitize_key((string) ($group['child_post_type'] ?? ''));
	if ($child_post_type === '') {
		return;
	}
	$limit = (int) ($group['child_limit'] ?? 18);
	$limit = $limit > 0 ? $limit : 18;
	$child_class = trim((string) ($group['child_class'] ?? ''));

	$child_posts = get_posts([
		'post_type' => $child_post_type,
		'post_status' => 'publish',
		'posts_per_page' => $limit,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	if (!is_array($child_posts) || empty($child_posts)) {
		return;
	}

	$existing_child_object_ids = [];
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== $parent_item_id) {
			continue;
		}
		$oid = (int) ($item->object_id ?? 0);
		if ($oid > 0) {
			$existing_child_object_ids[$oid] = true;
		}
	}

	foreach ($child_posts as $child) {
		if (!$child instanceof WP_Post) {
			continue;
		}
		if (!empty($existing_child_object_ids[(int) $child->ID])) {
			continue;
		}
		lf_sitemap_sync_add_post_menu_item($menu_id, $position, [
			'post_id' => (int) $child->ID,
			'title' => (string) ($child->post_title ?? ''),
			'parent_item_id' => $parent_item_id,
			'object' => $child_post_type,
			'classes' => $child_class,
		]);
	}
}

/**
 * Build/update the Header Menu from the sitemap cache + index.
 *
 * This is safe to run repeatedly (idempotent): it clears existing non-CTA items and rebuilds.
 *
 * @return array{
 *  ok:bool,
 *  enabled:bool,
 *  menu_id:int,
 *  created_menu:bool,
 *  assigned_location:bool,
 *  used_specs:int,
 *  added_items:int,
 *  preserved_items:int,
 *  error:string
 * }
 */
function lf_sitemap_sync_build_header_menu(): array {
	$enabled = lf_sitemap_menu_enable();
	if (!$enabled) {
		return [
			'ok' => true,
			'enabled' => false,
			'menu_id' => 0,
			'created_menu' => false,
			'assigned_location' => false,
			'used_specs' => 0,
			'added_items' => 0,
			'preserved_items' => 0,
			'error' => '',
		];
	}

	$ensure = lf_sitemap_sync_ensure_header_menu();
	if (empty($ensure['ok'])) {
		return [
			'ok' => false,
			'enabled' => true,
			'menu_id' => 0,
			'created_menu' => false,
			'assigned_location' => false,
			'used_specs' => 0,
			'added_items' => 0,
			'preserved_items' => 0,
			'error' => (string) ($ensure['error'] ?? 'ensure_menu_failed'),
		];
	}

	$menu_id = (int) $ensure['menu_id'];
	$preserved = lf_sitemap_sync_get_preserved_header_items($menu_id);
	$preserved_count = count($preserved);

	$cache_raw = (string) get_option('lf_airtable_sitemap_cache', '[]');
	$cache = json_decode($cache_raw, true);
	$specs = is_array($cache) ? $cache : [];

	$index = lf_sitemap_sync_get_page_index();

	// Collect published nodes keyed by group + hierarchy (depth computed from Airtable "Menu hierarchy").
	$items = [];
	foreach ($specs as $spec) {
		if (!is_array($spec)) {
			continue;
		}

		$group = trim((string) ($spec['menu_group'] ?? ''));
		if ($group === '') {
			continue;
		}
		$priority = isset($spec['priority']) ? (float) $spec['priority'] : 0.0;
		$title = (string) ($spec['title'] ?? '');

		$resolved = lf_sitemap_sync_spec_resolved_slug($spec);
		$slug = function_exists('lf_sitemap_normalize_slug_path')
			? lf_sitemap_normalize_slug_path((string) ($resolved['slug'] ?? '/'))
			: ('/' . trim((string) ($resolved['slug'] ?? '/'), '/') . '/');

		$idx = $index[$slug] ?? null;
		if (!is_array($idx)) {
			continue;
		}
		$status = (string) ($idx['status'] ?? '');
		$post_id = (int) ($idx['post_id'] ?? 0);
		if ($post_id <= 0 || $status !== 'publish') {
			continue;
		}

		$hier = trim((string) ($spec['menu_hierarchy'] ?? ''));
		$hier_parts = lf_sitemap_sync_parse_hierarchy($hier);
		$depth = lf_sitemap_sync_hierarchy_depth($hier_parts);
		$hier_norm = strtolower(trim(preg_replace('/\s+/', ' ', $hier) ?? ''));

		$items[] = [
			'group' => $group,
			'priority' => $priority,
			'title' => $title,
			'post_id' => $post_id,
			'hierarchy_raw' => $hier,
			'hierarchy_parts' => $hier_parts,
			'depth' => $depth,
			'hierarchy_key' => $hier_norm,
			'slug' => $slug,
		];
	}

	$group_order = [
		'Home' => 0,
		'About' => 1,
		'Services' => 2,
		'Service Areas' => 3,
		'More' => 999,
	];

	usort($items, static function (array $a, array $b) use ($group_order): int {
		$ga = (string) ($a['group'] ?? '');
		$gb = (string) ($b['group'] ?? '');
		$oa = $group_order[$ga] ?? 50;
		$ob = $group_order[$gb] ?? 50;
		if ($oa !== $ob) {
			return $oa < $ob ? -1 : 1;
		}
		$da = (int) ($a['depth'] ?? 0);
		$db = (int) ($b['depth'] ?? 0);
		if ($da !== $db) {
			return $da < $db ? -1 : 1;
		}
		$pa = (float) ($a['priority'] ?? 0.0);
		$pb = (float) ($b['priority'] ?? 0.0);
		if ($pa !== $pb) {
			return $pa < $pb ? -1 : 1;
		}
		$sa = (string) ($a['slug'] ?? '');
		$sb = (string) ($b['slug'] ?? '');
		return $sa <=> $sb;
	});

	// Safety: if sitemap has no published menu nodes, do NOT clear/rebuild the menu.
	// This prevents wiping navigation when Airtable data is incomplete/invalid or publish_ratio leaves nothing published.
	if (empty($items)) {
		return [
			'ok' => false,
			'enabled' => true,
			'menu_id' => $menu_id,
			'created_menu' => !empty($ensure['created']),
			'assigned_location' => !empty($ensure['assigned']),
			'used_specs' => 0,
			'added_items' => 0,
			'preserved_items' => $preserved_count,
			'error' => 'no_published_sitemap_menu_nodes',
		];
	}

	// Clear existing non-CTA items and rebuild.
	lf_sitemap_sync_clear_header_menu_items($menu_id);

	$position = 0;
	$added = 0;

	// Single deterministic pass:
	// - Parents created before children (sort includes depth).
	// - Children attach to the closest preceding item at depth-1 within the same group.
	// - If a parent is missing, fall back to the group's depth-0 root (if present), otherwise top-level.
	$group_root_item_ids = [];
	$last_by_group_depth = [];
	$dedupe = [];

	foreach ($items as $row) {
		$group = (string) $row['group'];
		$depth = (int) ($row['depth'] ?? 0);
		$hier_key = (string) ($row['hierarchy_key'] ?? '');
		$dedupe_key = $group . '|' . $depth . '|' . $hier_key . '|' . (int) ($row['post_id'] ?? 0);
		if (isset($dedupe[$dedupe_key])) {
			continue;
		}
		$dedupe[$dedupe_key] = true;

		$parent_item_id = 0;
		if ($depth > 0) {
			$prev_parent = $last_by_group_depth[$group][$depth - 1] ?? 0;
			if ((int) $prev_parent > 0) {
				$parent_item_id = (int) $prev_parent;
			} elseif (isset($group_root_item_ids[$group])) {
				$parent_item_id = (int) $group_root_item_ids[$group];
			}
		}

		$id = lf_sitemap_sync_add_post_menu_item($menu_id, $position, [
			'post_id' => (int) $row['post_id'],
			'title' => (string) $row['title'],
			'parent_item_id' => $parent_item_id,
			'classes' => $group === 'More' && $depth === 0 ? 'lf-menu-more' : '',
		]);
		if ($id > 0) {
			if ($depth === 0 && !isset($group_root_item_ids[$group])) {
				$group_root_item_ids[$group] = $id;
			}
			$last_by_group_depth[$group][$depth] = $id;
			$added++;
		}
	}

	// Finally, re-add preserved CTA items to keep current header button behavior.
	foreach ($preserved as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		$classes = $item->classes ?? [];
		$class_str = is_array($classes) ? implode(' ', $classes) : (string) $classes;
		wp_update_nav_menu_item($menu_id, 0, [
			'menu-item-title'    => (string) ($item->title ?? ''),
			'menu-item-url'      => (string) ($item->url ?? '#'),
			'menu-item-type'     => 'custom',
			'menu-item-status'   => 'publish',
			'menu-item-position' => $position++,
			'menu-item-classes'  => $class_str,
		]);
	}

	// Enforce the Services dropdown group + children, even when Airtable sitemap specs are missing/incomplete.
	lf_sitemap_sync_enforce_group_dropdown($menu_id, [
		'label' => 'Services',
		'page_slug' => 'services',
		'child_post_type' => 'lf_service',
		'child_limit' => (int) apply_filters('lf_sitemap_sync_services_menu_limit', 18),
		'child_class' => 'lf-menu-service-child',
	]);

	return [
		'ok' => true,
		'enabled' => true,
		'menu_id' => $menu_id,
		'created_menu' => !empty($ensure['created']),
		'assigned_location' => !empty($ensure['assigned']),
		'used_specs' => count($items),
		'added_items' => $added,
		'preserved_items' => $preserved_count,
		'error' => '',
	];
}

