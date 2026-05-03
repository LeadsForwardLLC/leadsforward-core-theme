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
 * Reorder top-level header menu items to match a desired label order.
 *
 * @param list<string> $labels Desired order of top-level items.
 */
function lf_sitemap_sync_reorder_header_menu_top_level(int $menu_id, array $labels): void {
	if (empty($labels) || !function_exists('wp_update_nav_menu_item')) {
		return;
	}
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || empty($items)) {
		return;
	}

	$pool = [];
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		$pool[(int) $item->ID] = $item;
	}
	if ($pool === []) {
		return;
	}

	$match_wanted_label = static function (WP_Post $item, string $want_raw): bool {
		$want = strtolower(trim((string) $want_raw));
		$title = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
		if ($title !== '' && $title === $want) {
			return true;
		}
		if ($want === 'home') {
			$home_id = (int) get_option('page_on_front');
			if (
				$home_id > 0
				&& (string) ($item->object ?? '') === 'page'
				&& (int) ($item->object_id ?? 0) === $home_id
			) {
				return true;
			}
			$url = isset($item->url) ? trim((string) $item->url) : '';
			if ($url !== '') {
				$h_slash = trailingslashit(home_url('/'));
				$h_plain = untrailingslashit($h_slash);
				if ($url === $h_slash || $url === $h_plain || trailingslashit($url) === $h_slash) {
					return true;
				}
			}
		}
		return false;
	};

	$ordered = [];
	foreach ($labels as $label) {
		foreach ($pool as $id => $item) {
			if (!$item instanceof WP_Post) {
				continue;
			}
			if ($match_wanted_label($item, (string) $label)) {
				$ordered[] = $item;
				unset($pool[$id]);
				break;
			}
		}
	}

	$remaining_non_cta = [];
	$remaining_cta = [];
	foreach ($pool as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if (lf_nav_menu_item_is_sync_preserved_cta($item)) {
			$remaining_cta[] = $item;
		} else {
			$remaining_non_cta[] = $item;
		}
	}
	usort(
		$remaining_non_cta,
		static fn ($a, $b): int => ((int) ($a->menu_order ?? 0)) <=> ((int) ($b->menu_order ?? 0))
	);
	usort(
		$remaining_cta,
		static fn ($a, $b): int => ((int) ($a->menu_order ?? 0)) <=> ((int) ($b->menu_order ?? 0))
	);

	$ordered = array_merge($ordered, $remaining_non_cta, $remaining_cta);

	$pos = 0;
	foreach ($ordered as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		$args = lf_nav_menu_item_build_update_args($item);
		$args['menu-item-position'] = $pos++;
		wp_update_nav_menu_item($menu_id, (int) $item->ID, $args);
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
	if (!$page instanceof WP_Post) {
		$page_id = wp_insert_post([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => $label,
			'post_name' => $page_slug,
		]);
		$page = $page_id && !is_wp_error($page_id) ? get_post((int) $page_id) : null;
	}
	if (!$page instanceof WP_Post || $page->post_status !== 'publish') {
		return;
	}
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		$items = [];
	}

	$child_post_type = sanitize_key((string) ($group['child_post_type'] ?? ''));

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
	if ($parent_item_id <= 0 && $child_post_type !== '') {
		$markers = strtolower(trim($label)) === 'services'
			? 'lf-menu-services-parent'
			: 'lf-menu-areas-parent';
		$parent_item_id = lf_nav_menu_find_top_parent_nav_item_by_class($items, $markers);
	}
	if ($parent_item_id <= 0 && $child_post_type !== '') {
		$parent_item_id = lf_nav_menu_find_top_parent_nav_item_by_child_type($items, $child_post_type);
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
		$group_classes = strtolower(trim($label)) === 'services'
			? 'lf-menu-group-parent lf-menu-services-parent'
			: 'lf-menu-group-parent lf-menu-areas-parent';
		$parent_item_id = lf_sitemap_sync_add_post_menu_item($menu_id, $position, [
			'post_id' => (int) $page->ID,
			'title' => $label,
			'parent_item_id' => 0,
			'object' => 'page',
			'classes' => $group_classes,
		]);
		if ($parent_item_id <= 0) {
			return;
		}
	}

	$normalize_classes = strtolower(trim($label)) === 'services'
		? 'lf-menu-group-parent lf-menu-services-parent'
		: 'lf-menu-group-parent lf-menu-areas-parent';
	lf_nav_menu_normalize_group_parent($menu_id, $parent_item_id, $label, (int) $page->ID, $normalize_classes);
	// Children detection below reflects the canonical parent linkage.
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		$items = [];
	}

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
	if ($child_post_type === 'lf_service' && function_exists('lf_ai_studio_dedupe_lf_service_posts')) {
		$preferred = function_exists('lf_ai_studio_manifest_preferred_service_slugs') ? lf_ai_studio_manifest_preferred_service_slugs() : [];
		$child_posts = lf_ai_studio_dedupe_lf_service_posts(is_array($child_posts) ? $child_posts : [], $preferred);
	}
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
		// Still enforce required dropdowns and ordering.
		lf_sitemap_sync_enforce_group_dropdown($menu_id, [
			'label' => 'Services',
			'page_slug' => 'services',
			'child_post_type' => 'lf_service',
			'child_limit' => (int) apply_filters('lf_sitemap_sync_services_menu_limit', 18),
			'child_class' => 'lf-menu-service-child',
		]);
		lf_sitemap_sync_enforce_group_dropdown($menu_id, [
			'label' => 'Service Areas',
			'page_slug' => 'service-areas',
			'child_post_type' => 'lf_service_area',
			'child_limit' => (int) apply_filters('lf_sitemap_sync_service_areas_menu_limit', 18),
			'child_class' => 'lf-menu-area-child',
		]);
		lf_header_menu_repair_nav_structure($menu_id);
		return [
			'ok' => true,
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
	lf_sitemap_sync_enforce_group_dropdown($menu_id, [
		'label' => 'Service Areas',
		'page_slug' => 'service-areas',
		'child_post_type' => 'lf_service_area',
		'child_limit' => (int) apply_filters('lf_sitemap_sync_service_areas_menu_limit', 18),
		'child_class' => 'lf-menu-area-child',
	]);
	lf_header_menu_repair_nav_structure($menu_id);

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

// ---------------------------------------------------------------------------
// Header menu structure repair (blank parent labels, duplicate dropdown trees).
// ---------------------------------------------------------------------------

/**
 * @return list<string>
 */
function lf_nav_menu_item_class_list(WP_Post $item): array {
	$classes = $item->classes ?? [];
	if (!is_array($classes)) {
		$s = trim((string) $classes);
		return $s !== ''
			? array_values(array_filter(preg_split('/\s+/', $s) ?: []))
			: [];
	}
	$out = [];
	foreach ($classes as $c) {
		$c = trim((string) $c);
		if ($c !== '') {
			$out[] = $c;
		}
	}
	return $out;
}

function lf_nav_menu_item_has_class(WP_Post $item, string $class): bool {
	return in_array($class, lf_nav_menu_item_class_list($item), true);
}

/**
 * Build a full wp_update_nav_menu_item args array from an existing menu item.
 * WordPress can reset menu-item-parent-id (flattening children) when updates omit fields.
 *
 * @return array<string, mixed>
 */
function lf_nav_menu_item_build_update_args(WP_Post $item): array {
	$args = [
		'menu-item-title' => (string) ($item->title ?? ''),
		'menu-item-status' => 'publish',
		'menu-item-parent-id' => (int) ($item->menu_item_parent ?? 0),
		'menu-item-position' => (int) ($item->menu_order ?? 0),
		'menu-item-classes' => is_array($item->classes ?? null) ? implode(' ', $item->classes) : trim((string) ($item->classes ?? '')),
	];
	$type = (string) ($item->type ?? '');
	if ($type === 'custom') {
		$args['menu-item-type'] = 'custom';
		$args['menu-item-object'] = 'custom';
		$args['menu-item-object-id'] = 0;
		$args['menu-item-url'] = (string) ($item->url ?? '');
	} elseif ($type === 'post_type') {
		$args['menu-item-type'] = 'post_type';
		$args['menu-item-object'] = (string) ($item->object ?? '');
		$args['menu-item-object-id'] = (int) ($item->object_id ?? 0);
		$args['menu-item-url'] = '';
	} elseif ($type === 'taxonomy') {
		$args['menu-item-type'] = 'taxonomy';
		$args['menu-item-object'] = (string) ($item->object ?? '');
		$args['menu-item-object-id'] = (int) ($item->object_id ?? 0);
		$args['menu-item-url'] = '';
	} else {
		$args['menu-item-type'] = $type !== '' ? $type : 'custom';
		$args['menu-item-object'] = (string) ($item->object ?? '');
		$args['menu-item-object-id'] = (int) ($item->object_id ?? 0);
		$args['menu-item-url'] = (string) ($item->url ?? '');
	}
	return $args;
}

function lf_nav_menu_item_is_sync_preserved_cta(WP_Post $item): bool {
	return lf_nav_menu_item_has_class($item, 'lf-menu-call') || lf_nav_menu_item_has_class($item, 'lf-menu-cta');
}

function lf_nav_menu_publish_page_id(string $slug): int {
	$page = get_page_by_path($slug);
	return $page instanceof WP_Post && $page->post_status === 'publish' ? (int) $page->ID : 0;
}

/**
 * @param list<WP_Post> $items
 * @return array<int, list<WP_Post>>
 */
function lf_nav_menu_items_children_by_parent(array $items): array {
	$map = [];
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		$p = (int) ($item->menu_item_parent ?? 0);
		if ($p > 0) {
			$map[$p][] = $item;
		}
	}
	return $map;
}

/**
 * @param list<WP_Post> $children
 */
function lf_nav_menu_children_match_dropdown_signature(array $children, string $child_object): bool {
	if ($child_object === '' || $children === []) {
		return false;
	}
	$n_match = 0;
	foreach ($children as $child) {
		if (!$child instanceof WP_Post) {
			continue;
		}
		if ((string) ($child->object ?? '') === $child_object) {
			$n_match++;
		}
	}
	if ($n_match >= 2) {
		return true;
	}
	return $n_match >= 1 && $n_match === count($children);
}

/**
 * @param list<WP_Post> $items
 */
function lf_nav_menu_find_top_parent_nav_item_by_class(array $items, string $needle): int {
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		if ($needle !== '' && lf_nav_menu_item_has_class($item, $needle)) {
			return (int) $item->ID;
		}
	}
	return 0;
}

/**
 * @param list<WP_Post> $items
 */
function lf_nav_menu_find_top_parent_nav_item_by_child_type(array $items, string $child_object): int {
	$by_parent = lf_nav_menu_items_children_by_parent($items);
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		$kids = $by_parent[(int) $item->ID] ?? [];
		if ($child_object !== '' && lf_nav_menu_children_match_dropdown_signature($kids, $child_object)) {
			return (int) $item->ID;
		}
	}
	return 0;
}

function lf_nav_menu_normalize_group_parent(int $menu_id, int $db_id, string $label, int $page_id, string $classes): void {
	if ($menu_id <= 0 || $db_id <= 0 || $page_id <= 0 || !function_exists('wp_update_nav_menu_item')) {
		return;
	}

	$item = null;
	$fresh = wp_get_nav_menu_items($menu_id);
	if (is_array($fresh)) {
		foreach ($fresh as $it) {
			if (!$it instanceof WP_Post) {
				continue;
			}
			if ((int) $it->ID === $db_id) {
				$item = $it;
				break;
			}
		}
	}
	if (!$item instanceof WP_Post) {
		return;
	}

	wp_update_nav_menu_item($menu_id, $db_id, [
		'menu-item-title' => $label,
		'menu-item-type' => 'post_type',
		'menu-item-object' => 'page',
		'menu-item-object-id' => $page_id,
		'menu-item-url' => '',
		'menu-item-status' => 'publish',
		'menu-item-parent-id' => (int) ($item->menu_item_parent ?? 0),
		'menu-item-classes' => $classes,
		'menu-item-position' => (int) ($item->menu_order ?? 0),
	]);
}

function lf_nav_menu_contains_page_object(array $items, int $page_id): bool {
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((string) ($item->object ?? '') === 'page' && (int) ($item->object_id ?? 0) === $page_id) {
			return true;
		}
	}
	return false;
}

/**
 * Persisted submenu divider rows clutter wp-admin with blank “Custom Links” and add empty stacks in the submenu.
 */
function lf_nav_menu_remove_persisted_lf_submenu_divider_placeholders(int $menu_id): void {
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return;
	}
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((string) ($item->type ?? '') !== 'custom') {
			continue;
		}
		$plain = trim(wp_strip_all_tags((string) ($item->title ?? '')));
		if ($plain !== '') {
			continue;
		}
		$url = trim((string) ($item->url ?? ''));
		if ($url !== '' && $url !== '#') {
			continue;
		}
		if (!lf_nav_menu_item_has_class($item, 'lf-submenu-divider')) {
			continue;
		}
		wp_delete_post((int) $item->ID, true);
	}
}

function lf_nav_menu_infer_blank_more_parent(int $menu_id): void {
	if (!function_exists('wp_update_nav_menu_item')) {
		return;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return;
	}

	$by_parent = lf_nav_menu_items_children_by_parent($items);
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		if (lf_nav_menu_item_is_sync_preserved_cta($item)) {
			continue;
		}

		$classes_merge = lf_nav_menu_item_class_list($item);
		$plain = trim(wp_strip_all_tags((string) ($item->title ?? '')));

		if (lf_nav_menu_item_has_class($item, 'lf-menu-more')) {
			if ($plain !== '') {
				continue;
			}
			$args_fix = [
				'menu-item-title' => __('More', 'leadsforward-core'),
				'menu-item-status' => 'publish',
				'menu-item-parent-id' => 0,
				'menu-item-classes' => implode(' ', array_unique(array_merge($classes_merge, ['lf-menu-more']))),
				'menu-item-position' => (int) ($item->menu_order ?? 0),
			];
			$stype = (string) ($item->type ?? '');
			if ($stype === 'custom') {
				$args_fix['menu-item-type'] = 'custom';
				$args_fix['menu-item-object'] = 'custom';
				$args_fix['menu-item-object-id'] = 0;
				$args_fix['menu-item-url'] = trim((string) ($item->url ?? '')) !== '' ? trim((string) ($item->url ?? '')) : '#';
			} elseif ($stype === 'post_type') {
				$args_fix['menu-item-type'] = 'post_type';
				$o = sanitize_key((string) ($item->object ?? ''));
				$args_fix['menu-item-object'] = $o !== '' ? $o : 'page';
				$args_fix['menu-item-object-id'] = (int) ($item->object_id ?? 0);
				$args_fix['menu-item-url'] = '';
			}
			wp_update_nav_menu_item($menu_id, (int) $item->ID, $args_fix);
			continue;
		}

		if ($plain !== '') {
			continue;
		}
		if ((string) ($item->type ?? '') !== 'custom') {
			continue;
		}

		$url = trim((string) ($item->url ?? ''));
		if ($url !== '' && $url !== '#') {
			continue;
		}

		$kids = array_merge([], $by_parent[(int) $item->ID] ?? []);
		if (count($kids) < 2) {
			continue;
		}

		$hits_cpt = false;
		foreach ($kids as $k) {
			if (!$k instanceof WP_Post) {
				continue;
			}
			$obj = (string) ($k->object ?? '');
			if ($obj === 'lf_service' || $obj === 'lf_service_area') {
				$hits_cpt = true;
				break;
			}
		}
		if ($hits_cpt) {
			continue;
		}

		wp_update_nav_menu_item($menu_id, (int) $item->ID, [
			'menu-item-title' => __('More', 'leadsforward-core'),
			'menu-item-url' => '#',
			'menu-item-status' => 'publish',
			'menu-item-type' => 'custom',
			'menu-item-object' => 'custom',
			'menu-item-object-id' => 0,
			'menu-item-parent-id' => 0,
			'menu-item-classes' => implode(' ', array_unique(array_merge($classes_merge, ['lf-menu-more', 'menu-item-has-children']))),
			'menu-item-position' => (int) ($item->menu_order ?? 0),
		]);
	}
}

function lf_header_menu_append_missing_core_top_levels(int $menu_id): void {
	if (!function_exists('wp_update_nav_menu_item')) {
		return;
	}
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return;
	}

	$cursor = 0;
	foreach ($items as $it) {
		if ($it instanceof WP_Post) {
			$cursor = max($cursor, (int) ($it->menu_order ?? 0));
		}
	}

	$page_on_front = (int) get_option('page_on_front');
	if ($page_on_front > 0 && get_post_status($page_on_front) === 'publish' && !lf_nav_menu_contains_page_object($items, $page_on_front)) {
		$cursor++;
		lf_sitemap_sync_add_post_menu_item($menu_id, $cursor, [
			'post_id' => $page_on_front,
			'title' => __('Home', 'leadsforward-core'),
			'parent_item_id' => 0,
			'object' => 'page',
			'classes' => '',
		]);
		$items = wp_get_nav_menu_items($menu_id) ?: [];
	}

	$reviews = get_page_by_path('reviews');
	$reviews_id = ($reviews instanceof WP_Post && $reviews->post_status === 'publish') ? (int) $reviews->ID : 0;
	if ($reviews_id <= 0) {
		return;
	}

	if (!lf_nav_menu_contains_page_object($items, $reviews_id)) {
		$cursor = 0;
		foreach ($items as $it) {
			if ($it instanceof WP_Post) {
				$cursor = max($cursor, (int) ($it->menu_order ?? 0));
			}
		}
		$cursor++;
		lf_sitemap_sync_add_post_menu_item($menu_id, $cursor, [
			'post_id' => $reviews_id,
			'title' => __('Reviews', 'leadsforward-core'),
			'parent_item_id' => 0,
			'object' => 'page',
			'classes' => '',
		]);
	}
}

function lf_nav_menu_infer_blank_top_dropdown_parents(int $menu_id): void {
	$services_pid = lf_nav_menu_publish_page_id('services');
	$areas_pid = lf_nav_menu_publish_page_id('service-areas');
	if ($services_pid <= 0 && $areas_pid <= 0) {
		return;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return;
	}
	$by_parent = lf_nav_menu_items_children_by_parent($items);

	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		if (lf_nav_menu_item_is_sync_preserved_cta($item) || lf_nav_menu_item_has_class($item, 'lf-menu-more')) {
			continue;
		}

		$plain = trim(wp_strip_all_tags((string) ($item->title ?? '')));
		if ($plain !== '') {
			continue;
		}

		$kids = array_merge([], $by_parent[(int) $item->ID] ?? []);
		if ($kids === []) {
			continue;
		}

		$s_sig = lf_nav_menu_children_match_dropdown_signature($kids, 'lf_service');
		$a_sig = lf_nav_menu_children_match_dropdown_signature($kids, 'lf_service_area');

		if ($s_sig && !$a_sig && $services_pid > 0) {
			lf_nav_menu_normalize_group_parent(
				$menu_id,
				(int) $item->ID,
				__('Services', 'leadsforward-core'),
				$services_pid,
				'lf-menu-group-parent lf-menu-services-parent'
			);
			continue;
		}
		if ($a_sig && !$s_sig && $areas_pid > 0) {
			lf_nav_menu_normalize_group_parent(
				$menu_id,
				(int) $item->ID,
				__('Service Areas', 'leadsforward-core'),
				$areas_pid,
				'lf-menu-group-parent lf-menu-areas-parent'
			);
			continue;
		}
		if (!$s_sig || !$a_sig) {
			continue;
		}

		$nf_svc = 0;
		$nf_area = 0;
		foreach ($kids as $k) {
			if (!$k instanceof WP_Post) {
				continue;
			}
			if ((string) ($k->object ?? '') === 'lf_service') {
				$nf_svc++;
			}
			if ((string) ($k->object ?? '') === 'lf_service_area') {
				$nf_area++;
			}
		}
		if ($nf_svc >= $nf_area && $services_pid > 0) {
			lf_nav_menu_normalize_group_parent(
				$menu_id,
				(int) $item->ID,
				__('Services', 'leadsforward-core'),
				$services_pid,
				'lf-menu-group-parent lf-menu-services-parent'
			);
		} elseif ($areas_pid > 0) {
			lf_nav_menu_normalize_group_parent(
				$menu_id,
				(int) $item->ID,
				__('Service Areas', 'leadsforward-core'),
				$areas_pid,
				'lf-menu-group-parent lf-menu-areas-parent'
			);
		}
	}
}

function lf_nav_menu_delete_blank_placeholder_top_parents(int $menu_id): void {
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return;
	}
	$by_parent = lf_nav_menu_items_children_by_parent($items);

	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		if (lf_nav_menu_item_is_sync_preserved_cta($item) || lf_nav_menu_item_has_class($item, 'lf-menu-more')) {
			continue;
		}
		if (!empty($by_parent[(int) $item->ID])) {
			continue;
		}

		$plain = trim(wp_strip_all_tags((string) ($item->title ?? '')));
		if ($plain !== '') {
			continue;
		}
		if ((string) ($item->type ?? '') !== 'custom') {
			continue;
		}

		$url = trim((string) ($item->url ?? ''));
		if ($url !== '' && $url !== '#') {
			continue;
		}

		wp_delete_post((int) $item->ID, true);
	}
}

/**
 * Remove duplicate CPT links that share the same object_id under one parent (after merges).
 *
 * @param list<WP_Post> $children
 */
function lf_nav_menu_dedupe_duplicate_object_children(int $menu_id, array $children, string $child_object): void {
	if ($menu_id <= 0 || $child_object === '') {
		return;
	}

	$seen = [];
	foreach ($children as $ch) {
		if (!$ch instanceof WP_Post || (string) ($ch->object ?? '') !== $child_object) {
			continue;
		}
		$oid = (int) ($ch->object_id ?? 0);
		if ($oid <= 0) {
			continue;
		}
		if (!isset($seen[$oid])) {
			$seen[$oid] = true;
			continue;
		}
		wp_delete_post((int) $ch->ID, true);
	}
}

/**
 * @param list<WP_Post> $direct_children
 */
function lf_nav_menu_score_dropdown_parent(WP_Post $parent, array $direct_children, int $canonical_page_id, string $child_object, string $marker_class): int {
	$score = 0;
	if ($marker_class !== '' && lf_nav_menu_item_has_class($parent, $marker_class)) {
		$score += 100;
	}
	if (
		$canonical_page_id > 0
		&& (string) ($parent->object ?? '') === 'page'
		&& (int) ($parent->object_id ?? 0) === $canonical_page_id
	) {
		$score += 1000;
	}
	foreach ($direct_children as $ch) {
		if (!$ch instanceof WP_Post || (string) ($ch->object ?? '') !== $child_object) {
			continue;
		}
		$score += 5;
		if (lf_nav_menu_item_has_class($ch, 'lf-submenu-all-link')) {
			$score += 50;
		}
	}
	return $score;
}

/**
 * @param list<int> $candidate_ids
 * @param array<int, list<WP_Post>> $direct_children_map
 *
 * @return list<int>
 */
function lf_nav_menu_sort_dropdown_candidates_by_score(array $candidate_ids, array $items_by_id, array $direct_children_map, int $canonical_page_id, string $child_object, string $marker_class): array {
	usort($candidate_ids, static function (int $a, int $b) use ($items_by_id, $direct_children_map, $canonical_page_id, $child_object, $marker_class): int {
		$pa = $items_by_id[$a] ?? null;
		$pb = $items_by_id[$b] ?? null;
		if (!$pa instanceof WP_Post || !$pb instanceof WP_Post) {
			return $a <=> $b;
		}
		$sa = lf_nav_menu_score_dropdown_parent($pa, $direct_children_map[$a] ?? [], $canonical_page_id, $child_object, $marker_class);
		$sb = lf_nav_menu_score_dropdown_parent($pb, $direct_children_map[$b] ?? [], $canonical_page_id, $child_object, $marker_class);
		return $sb <=> $sa;
	});
	return $candidate_ids;
}

function lf_nav_menu_merge_duplicate_dropdowns(
	int $menu_id,
	string $canonical_label_lc,
	int $canonical_page_id,
	string $child_object,
	string $marker_class,
	string $normalize_classes,
	string $parent_title_label
): void {
	if (
		$menu_id <= 0
		|| $canonical_page_id <= 0
		|| $child_object === ''
		|| !function_exists('wp_update_nav_menu_item')
	) {
		return;
	}

	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || empty($items)) {
		return;
	}
	$by_parent = lf_nav_menu_items_children_by_parent($items);
	$candidates = [];
	$items_by_id = [];
	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		$items_by_id[(int) $item->ID] = $item;
	}

	foreach ($items as $item) {
		if (!$item instanceof WP_Post) {
			continue;
		}
		if ((int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		if (lf_nav_menu_item_is_sync_preserved_cta($item) || lf_nav_menu_item_has_class($item, 'lf-menu-more')) {
			continue;
		}

		$kid_list = array_merge([], $by_parent[(int) $item->ID] ?? []);
		if (!lf_nav_menu_children_match_dropdown_signature($kid_list, $child_object)) {
			continue;
		}

		$t = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
		$marked = $marker_class !== '' && lf_nav_menu_item_has_class($item, $marker_class);
		if (!($marked || ($t !== '' && $t === strtolower($canonical_label_lc)) || $t === '')) {
			continue;
		}

		$candidates[] = (int) $item->ID;
	}

	$direct_children_map = [];
	foreach ($candidates as $cid) {
		$direct_children_map[$cid] = array_merge([], $by_parent[$cid] ?? []);
	}

	$candidates = lf_nav_menu_sort_dropdown_candidates_by_score(
		array_values(array_unique($candidates)),
		$items_by_id,
		$direct_children_map,
		$canonical_page_id,
		$child_object,
		$marker_class
	);
	if ($candidates === []) {
		return;
	}

	$winner_id = (int) $candidates[0];
	lf_nav_menu_normalize_group_parent($menu_id, $winner_id, $parent_title_label, $canonical_page_id, $normalize_classes);

	$losers = array_slice($candidates, 1);
	foreach ($losers as $loser_id) {
		if ($loser_id <= 0 || $loser_id === $winner_id) {
			continue;
		}

		$fresh = wp_get_nav_menu_items($menu_id);
		if (!is_array($fresh)) {
			continue;
		}
		$map = lf_nav_menu_items_children_by_parent($fresh);
		$kids = array_merge([], $map[$loser_id] ?? []);
		foreach ($kids as $ch) {
			if (!$ch instanceof WP_Post || (int) ($ch->menu_item_parent ?? 0) !== $loser_id) {
				continue;
			}

			$move_args = lf_nav_menu_item_build_update_args($ch);
			$move_args['menu-item-parent-id'] = $winner_id;
			wp_update_nav_menu_item($menu_id, (int) $ch->ID, $move_args);
		}

		wp_delete_post((int) $loser_id, true);
	}

	$fresh2 = wp_get_nav_menu_items($menu_id);
	if (!is_array($fresh2)) {
		return;
	}
	$m2 = lf_nav_menu_items_children_by_parent($fresh2);
	$winner_kids = array_merge([], $m2[$winner_id] ?? []);
	lf_nav_menu_dedupe_duplicate_object_children($menu_id, $winner_kids, $child_object);
}

/**
 * Fix blank Services/Areas group parents (chevron-only), merge duplicate dropdown trees,
 * and remove empty placeholder `#` customs at the header root.
 *
 * Intended for Header Menu assignments; callers pass the resolved menu term ID.
 *
 * When $apply_preferred_order is true, top-level positions are snapped to Home → … → Reviews → More —
 * intentional for synced fleet menus so partial Airtable publishes do not leave core links missing.
 *
 * @param bool $apply_preferred_order Snap top-level ordering for known labels (fleet default).
 */
function lf_header_menu_repair_nav_structure(int $menu_id, bool $apply_preferred_order = true): void {
	if ($menu_id <= 0 || !function_exists('wp_get_nav_menu_items')) {
		return;
	}

	lf_nav_menu_remove_persisted_lf_submenu_divider_placeholders($menu_id);
	lf_nav_menu_infer_blank_more_parent($menu_id);
	lf_nav_menu_infer_blank_top_dropdown_parents($menu_id);
	lf_nav_menu_delete_blank_placeholder_top_parents($menu_id);

	$services_pid = lf_nav_menu_publish_page_id('services');
	$areas_pid = lf_nav_menu_publish_page_id('service-areas');

	lf_nav_menu_merge_duplicate_dropdowns(
		$menu_id,
		'services',
		$services_pid,
		'lf_service',
		'lf-menu-services-parent',
		'lf-menu-group-parent lf-menu-services-parent',
		__('Services', 'leadsforward-core')
	);
	lf_nav_menu_merge_duplicate_dropdowns(
		$menu_id,
		'service areas',
		$areas_pid,
		'lf_service_area',
		'lf-menu-areas-parent',
		'lf-menu-group-parent lf-menu-areas-parent',
		__('Service Areas', 'leadsforward-core')
	);

	lf_nav_menu_delete_blank_placeholder_top_parents($menu_id);
	lf_header_menu_append_missing_core_top_levels($menu_id);

	if ($apply_preferred_order) {
		lf_sitemap_sync_reorder_header_menu_top_level($menu_id, ['Home', 'Services', 'Service Areas', 'Reviews', 'More']);
	}
}
