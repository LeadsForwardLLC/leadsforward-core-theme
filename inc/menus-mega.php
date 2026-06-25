<?php
/**
 * Services mega menu only (Service Areas keeps the standard dropdown).
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Whether the Services mega menu is enabled.
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
 * Flatten service category nesting into one searchable Services grid.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_mega_menu_flatten_service_categories(array $items, $args): array {
	if (!lf_mega_menu_is_header_context($args, $items)) {
		return $items;
	}

	$services_parent_id = lf_header_menu_services_parent_id($items);
	if ($services_parent_id <= 0) {
		return $items;
	}

	$category_ids = [];
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
		if ($cat_id !== 0) {
			$category_ids[$cat_id] = true;
		}
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
		if (isset($category_ids[(int) ($menu_item->ID ?? 0)])) {
			continue;
		}
		$filtered[] = $menu_item;
	}

	return $filtered;
}
add_filter('wp_nav_menu_objects', 'lf_mega_menu_flatten_service_categories', 14, 2);

/**
 * Prepend Services mega search row + mark service tiles (after all tree fixups).
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_mega_menu_prepare_services_panel(array $items, $args): array {
	if (!lf_mega_menu_is_header_context($args, $items)) {
		return $items;
	}

	$services_parent_id = lf_header_menu_services_parent_id($items);
	if ($services_parent_id <= 0) {
		return $items;
	}

	$has_search = false;
	foreach ($items as $menu_item) {
		if (!$menu_item instanceof \WP_Post) {
			continue;
		}
		if ((int) ($menu_item->menu_item_parent ?? 0) !== $services_parent_id) {
			continue;
		}
		if (lf_header_menu_item_has_class($menu_item, 'lf-mega-search-host')) {
			$has_search = true;
		}
		if ((string) ($menu_item->object ?? '') === 'lf_service') {
			$menu_item->classes = array_values(array_unique(array_merge(
				is_array($menu_item->classes ?? null) ? $menu_item->classes : [],
				['lf-mega-tile', 'menu-item']
			)));
		}
	}

	if (!$has_search) {
		$search_item = lf_header_menu_synthetic_child(
			$services_parent_id,
			-4001,
			__('Search services…', 'leadsforward-core'),
			'#',
			['menu-item', 'lf-mega-search-host', 'lf-mega-search-host--services']
		);
		$search_item->menu_order = 0;
		$items[] = $search_item;
	}

	return $items;
}
add_filter('wp_nav_menu_objects', 'lf_mega_menu_prepare_services_panel', 20, 2);

/**
 * Services parent mega-menu class (never Service Areas).
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
	}
	return array_values(array_unique($classes));
}
add_filter('nav_menu_css_class', 'lf_mega_menu_parent_css_classes', 12, 4);

/**
 * Mega search host + thumbnail tiles.
 */
function lf_mega_menu_item_output(string $item_output, \WP_Post $item, int $depth, $args): string {
	if (!lf_mega_menu_enabled() || !is_object($args) || ($args->theme_location ?? '') !== 'header_menu') {
		return $item_output;
	}

	$classes = is_array($item->classes ?? null) ? $item->classes : [];

	if (in_array('lf-mega-search-host', $classes, true)) {
		$placeholder = esc_attr(wp_strip_all_tags((string) ($item->title ?? '')));
		return $args->before
			. '<div class="lf-mega-search-wrap" role="search">'
			. '<input type="search" class="lf-mega-search__input" '
			. 'placeholder="' . $placeholder . '" '
			. 'data-lf-mega-search="services" '
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
	if ($post instanceof \WP_Post && function_exists('lf_get_post_card_thumbnail_id')) {
		$thumb_id = lf_get_post_card_thumbnail_id($post);
		if ($thumb_id > 0) {
			$thumb_url = (string) wp_get_attachment_image_url($thumb_id, 'thumbnail');
		}
	}
	if ($thumb_url === '' && function_exists('lf_get_section_default_image_url')) {
		$thumb_url = lf_get_section_default_image_url('service');
	}
	if ($thumb_url === '') {
		return $item_output;
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
