<?php
/**
 * Services / Service Areas mega menus: search, thumbnails, flat grid layout.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Whether mega menus are enabled for header Services / Service Areas dropdowns.
 */
function lf_mega_menu_enabled(): bool {
	return (bool) apply_filters('lf_mega_menu_enabled', true);
}

/**
 * @param array<int, \WP_Post> $items
 */
function lf_mega_menu_is_header_context($args, array $items = []): bool {
	return lf_mega_menu_enabled()
		&& is_object($args)
		&& ($args->theme_location ?? '') === 'header_menu'
		&& $items !== [];
}

/**
 * Find Services or Service Areas group parent ID.
 */
function lf_mega_menu_group_parent_id(array $items, string $kind): int {
	if ($kind === 'services') {
		return lf_header_menu_services_parent_id($items);
	}
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
 * Flatten service category nesting so mega menu shows one searchable grid.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_mega_menu_flatten_service_categories(array $items, $args): array {
	if (!lf_mega_menu_is_header_context($args, $items)) {
		return $items;
	}

	$services_parent_id = lf_mega_menu_group_parent_id($items, 'services');
	if ($services_parent_id <= 0) {
		return $items;
	}

	$category_ids = [];
	$grandchildren = [];
	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($menu_item->menu_item_parent ?? 0) !== $services_parent_id) {
			continue;
		}
		if (!lf_header_menu_item_has_class($menu_item, 'lf-menu-service-category')) {
			continue;
		}
		$cat_id = (int) ($menu_item->ID ?? 0);
		if ($cat_id === 0) {
			continue;
		}
		$category_ids[$cat_id] = true;
	}

	if ($category_ids === []) {
		return $items;
	}

	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		$parent = (int) ($menu_item->menu_item_parent ?? 0);
		if (!isset($category_ids[$parent])) {
			continue;
		}
		$menu_item->menu_item_parent = $services_parent_id;
	}

	$filtered = [];
	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		$id = (int) ($menu_item->ID ?? 0);
		if (isset($category_ids[$id])) {
			continue;
		}
		$filtered[] = $menu_item;
	}

	return $filtered;
}
add_filter('wp_nav_menu_objects', 'lf_mega_menu_flatten_service_categories', 12, 2);

/**
 * Inject mega search row and mark CPT tiles for thumbnail rendering.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_mega_menu_prepare_items(array $items, $args): array {
	if (!lf_mega_menu_is_header_context($args, $items)) {
		return $items;
	}

	$synthetic_id = -4000;
	$extra = [];

	foreach (['services', 'areas'] as $kind) {
		$parent_id = lf_mega_menu_group_parent_id($items, $kind);
		if ($parent_id <= 0) {
			continue;
		}

		$has_search = false;
		$post_type = $kind === 'services' ? 'lf_service' : 'lf_service_area';
		foreach ($items as $menu_item) {
			if (!$menu_item instanceof \WP_Post) {
				continue;
			}
			if ((int) ($menu_item->menu_item_parent ?? 0) !== $parent_id) {
				continue;
			}
			if (lf_header_menu_item_has_class($menu_item, 'lf-mega-search-host')) {
				$has_search = true;
			}
			if ((string) ($menu_item->object ?? '') === $post_type) {
				$menu_item->classes = array_values(array_unique(array_merge(
					is_array($menu_item->classes ?? null) ? $menu_item->classes : [],
					['lf-mega-tile']
				)));
			}
		}

		if (!$has_search) {
			$placeholder = $kind === 'services'
				? __('Search services…', 'leadsforward-core')
				: __('Search service areas…', 'leadsforward-core');
			$search_item = lf_header_menu_synthetic_child(
				$parent_id,
				$synthetic_id--,
				$placeholder,
				'#',
				['menu-item', 'lf-mega-search-host', 'lf-mega-search-host--' . $kind]
			);
			$search_item->menu_order = -100;
			$extra[] = $search_item;
		}
	}

	if ($extra !== []) {
		$items = array_merge($items, $extra);
	}

	return lf_header_menu_reorder_services_areas_children($items);
}
add_filter('wp_nav_menu_objects', 'lf_mega_menu_prepare_items', 13, 2);

/**
 * Add mega-menu classes to Services / Service Areas parents.
 *
 * @param array<int, string> $classes
 * @return array<int, string>
 */
function lf_mega_menu_parent_css_classes(array $classes, \WP_Post $item, $args, int $depth): array {
	if (!lf_mega_menu_enabled() || !is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $depth !== 0) {
		return $classes;
	}
	if (lf_header_menu_item_has_class($item, 'lf-menu-services-parent')) {
		$classes[] = 'lf-mega-menu';
		$classes[] = 'lf-mega-menu--services';
	} elseif (lf_header_menu_item_has_class($item, 'lf-menu-areas-parent')) {
		$classes[] = 'lf-mega-menu';
		$classes[] = 'lf-mega-menu--areas';
	}
	return array_values(array_unique($classes));
}
add_filter('nav_menu_css_class', 'lf_mega_menu_parent_css_classes', 12, 4);

/**
 * Mega search host row and thumbnail tiles in submenu output.
 */
function lf_mega_menu_item_output(string $item_output, \WP_Post $item, int $depth, $args): string {
	if (!lf_mega_menu_enabled() || !is_object($args) || ($args->theme_location ?? '') !== 'header_menu') {
		return $item_output;
	}

	$classes = is_array($item->classes ?? null) ? $item->classes : [];

	if (in_array('lf-mega-search-host', $classes, true)) {
		$placeholder = esc_attr(wp_strip_all_tags((string) ($item->title ?? '')));
		$kind = in_array('lf-mega-search-host--areas', $classes, true) ? 'areas' : 'services';
		return $args->before
			. '<div class="lf-mega-search-wrap" role="search">'
			. '<input type="search" class="lf-mega-search__input" '
			. 'placeholder="' . $placeholder . '" '
			. 'data-lf-mega-search="' . esc_attr($kind) . '" '
			. 'aria-label="' . $placeholder . '" autocomplete="off" />'
			. '</div>'
			. $args->after;
	}

	if ($depth < 1 || !in_array('lf-mega-tile', $classes, true)) {
		return $item_output;
	}

	$object_id = (int) ($item->object_id ?? 0);
	$post = $object_id > 0 ? get_post($object_id) : null;
	$thumb_url = '';
	$thumb_alt = '';
	if ($post instanceof \WP_Post && function_exists('lf_get_post_card_thumbnail_id')) {
		$thumb_id = lf_get_post_card_thumbnail_id($post);
		if ($thumb_id > 0) {
			$thumb_url = (string) wp_get_attachment_image_url($thumb_id, 'thumbnail');
			$thumb_alt = (string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
		}
	}
	if ($thumb_url === '' && function_exists('lf_get_section_default_image_url')) {
		$ctx = (string) ($item->object ?? '') === 'lf_service_area' ? 'service_area' : 'service';
		$thumb_url = lf_get_section_default_image_url($ctx);
	}
	if ($thumb_url === '') {
		return $item_output;
	}

	if ($thumb_alt === '') {
		$thumb_alt = wp_strip_all_tags((string) ($item->title ?? ''));
	}

	$thumb_html = '<span class="lf-mega-tile__thumb" aria-hidden="true">'
		. '<img src="' . esc_url($thumb_url) . '" alt="" width="48" height="48" loading="lazy" decoding="async" />'
		. '</span>';

	return preg_replace(
		'/(<a\b[^>]*>)(.*?)(<\/a>)/s',
		'$1' . $thumb_html . '<span class="lf-mega-tile__label">$2</span>$3',
		$item_output,
		1
	) ?? $item_output;
}
add_filter('walker_nav_menu_start_el', 'lf_mega_menu_item_output', 11, 4);

/**
 * Enqueue mega menu search/filter script on the frontend.
 */
function lf_mega_menu_enqueue_assets(): void {
	if (is_admin() || !lf_mega_menu_enabled()) {
		return;
	}
	if (!has_nav_menu('header_menu')) {
		return;
	}
	$path = LF_THEME_DIR . '/assets/js/header-mega-menu.js';
	if (!is_readable($path)) {
		return;
	}
	wp_enqueue_script(
		'lf-header-mega-menu',
		LF_THEME_URI . '/assets/js/header-mega-menu.js',
		[],
		(string) (defined('LF_THEME_VERSION') ? LF_THEME_VERSION : '1.0'),
		true
	);
}
add_action('wp_enqueue_scripts', 'lf_mega_menu_enqueue_assets', 20);
