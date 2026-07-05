<?php
/**
 * Theme setup: supports, menus, editor styles, ACF options.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register theme supports and menus. Runs at after_setup_theme.
 */
function lf_theme_setup(): void {
	// Required for proper document title in <head>.
	add_theme_support('title-tag');

	// Post thumbnails for featured images across CPTs and posts.
	add_theme_support('post-thumbnails');

	// Semantic HTML5 markup for search forms, comment forms, etc.
	add_theme_support('html5', [
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	]);

	// So block editor can use theme styles; we load our own editor stylesheet.
	add_theme_support('editor-styles');
	add_editor_style('assets/css/editor.css');
	add_editor_style('assets/css/header-call-link.css');

	// Register nav menus. No hardcoded links; templates output nothing if empty.
	register_nav_menus([
		'header_menu'   => __('Header Menu', 'leadsforward-core'),
		'footer_menu'   => __('Footer Menu', 'leadsforward-core'),
		'utility_menu'  => __('Utility Menu', 'leadsforward-core'),
	]);
}
add_action('after_setup_theme', 'lf_theme_setup');

/**
 * Hero/LCP image size — large enough for full-width backgrounds without always using `full`.
 */
function lf_register_theme_image_sizes(): void {
	add_image_size('lf-hero-l', 1920, 1080, true);
}
add_action('after_setup_theme', 'lf_register_theme_image_sizes', 11);

/**
 * Seed default niche on activation for new installs.
 */
function lf_theme_seed_default_niche(): void {
	$key = defined('LF_HOMEPAGE_NICHE_OPTION') ? LF_HOMEPAGE_NICHE_OPTION : 'lf_homepage_niche_slug';
	$current = (string) get_option($key, '');
	if ($current !== '') {
		return;
	}
	$default = function_exists('lf_default_niche_slug') ? lf_default_niche_slug() : 'foundation-repair';
	update_option($key, $default, true);
}
add_action('after_switch_theme', 'lf_theme_seed_default_niche');

/**
 * Default new installs to "Discourage search engines" during build.
 *
 * WordPress uses the `blog_public` option for Settings → Reading → Search engine visibility.
 * We only set this on activation when the setup wizard is not complete, so launched sites
 * are not impacted by theme updates.
 */
function lf_theme_default_discourage_indexing_on_activation(): void {
	$wizard_done = (bool) get_option('lf_setup_wizard_complete', false);
	if ($wizard_done) {
		return;
	}
	if (!apply_filters('lf_default_discourage_indexing_on_activation', true)) {
		return;
	}
	$current = get_option('blog_public', '1');
	if ((string) $current === '0') {
		return;
	}
	update_option('blog_public', '0', true);
}
add_action('after_switch_theme', 'lf_theme_default_discourage_indexing_on_activation', 20);

function lf_header_menu_item_title(string $title, \WP_Post $item, $args, int $depth): string {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu') {
		return $title;
	}
	$classes = $item->classes ?? [];
	if (in_array('lf-menu-cta', $classes, true)) {
		$label = function_exists('lf_get_global_option') ? (string) lf_get_global_option('lf_header_cta_label', '') : '';
		if ($label === '' && function_exists('lf_get_option')) {
			$label = (string) lf_get_option('lf_cta_primary_text', 'option');
		}
		return $label !== '' ? $label : __('Free Inspection', 'leadsforward-core');
	}
	if (in_array('lf-menu-call', $classes, true)) {
		return __('Call', 'leadsforward-core');
	}
	if (function_exists('lf_header_menu_item_is_about') && lf_header_menu_item_is_about($item)) {
		return function_exists('lf_header_menu_about_nav_label')
			? lf_header_menu_about_nav_label()
			: __('About', 'leadsforward-core');
	}
	if (in_array('lf-menu-more', $classes, true) && trim(wp_strip_all_tags($title)) === '') {
		return __('More', 'leadsforward-core');
	}
	return $title;
}
add_filter('nav_menu_item_title', 'lf_header_menu_item_title', 10, 4);

function lf_header_menu_link_attributes(array $atts, \WP_Post $item, $args, int $depth): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu') {
		return $atts;
	}
	$classes = $item->classes ?? [];
	if (in_array('lf-menu-group-parent', $classes, true)) {
		$atts['href'] = '#';
		$atts['aria-disabled'] = 'true';
		$atts['tabindex'] = '-1';
	}
	if (in_array('lf-submenu-divider', $classes, true)) {
		$atts['href'] = '#';
		$atts['aria-hidden'] = 'true';
		$atts['tabindex'] = '-1';
		$atts['class'] = trim(($atts['class'] ?? '') . ' is-divider');
	}
	if (in_array('lf-menu-service-category', $classes, true)) {
		$atts['href'] = '#';
		$atts['class'] = trim(($atts['class'] ?? '') . ' lf-menu-service-category__link');
		$atts['aria-haspopup'] = 'true';
	}
	if (in_array('lf-menu-cta', $classes, true)) {
		$cta_url = function_exists('lf_get_global_option') ? (string) lf_get_global_option('lf_header_cta_url', '') : '';
		if ($cta_url !== '') {
			$atts['href'] = esc_url($cta_url);
		} else {
			$atts['href'] = '#';
			$atts['data-lf-quote-trigger'] = '1';
			$atts['data-lf-quote-source'] = 'header-menu';
		}
		$atts['class'] = trim(($atts['class'] ?? '') . ' lf-btn lf-btn--primary lf-btn--sm');
	}
	if (in_array('lf-menu-call', $classes, true)) {
		$phone = function_exists('lf_get_cta_phone') ? (string) lf_get_cta_phone() : '';
		$atts['href'] = $phone !== '' ? 'tel:' . esc_attr($phone) : '#';
		$atts['class'] = trim(($atts['class'] ?? '') . ' lf-menu-call__link lf-call-btn lf-call-btn--icon');
		$atts['aria-label'] = __('Call', 'leadsforward-core');
	}
	return $atts;
}
add_filter('nav_menu_link_attributes', 'lf_header_menu_link_attributes', 10, 4);

function lf_header_menu_item_output(string $item_output, \WP_Post $item, int $depth, $args): string {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu') {
		return $item_output;
	}
	$classes = $item->classes ?? [];
	if (in_array('lf-submenu-divider', $classes, true)) {
		return $args->before . '<span class="site-header__submenu-divider" aria-hidden="true"></span>' . $args->after;
	}
	if (in_array('lf-menu-group-parent', $classes, true)) {
		if (in_array('lf-menu-services-parent', $classes, true)) {
			$title = (string) apply_filters(
				'lf_header_services_group_label',
				__('Services', 'leadsforward-core'),
				$item,
				$args,
				$depth
			);
		} elseif (in_array('lf-menu-areas-parent', $classes, true)) {
			$title = (string) apply_filters(
				'lf_header_service_areas_group_label',
				__('Service Areas', 'leadsforward-core'),
				$item,
				$args,
				$depth
			);
		} else {
			$title = (string) apply_filters('nav_menu_item_title', $item->title, $item, $args, $depth);
		}
		if (trim(wp_strip_all_tags($title)) === '') {
			$title = (string) apply_filters('nav_menu_item_title', $item->title, $item, $args, $depth);
		}
		$item_output = $args->before
			. '<span class="site-header__group-label">' . $args->link_before . esc_html($title) . $args->link_after . '</span>'
			. '<button type="button" class="site-header__submenu-toggle" aria-expanded="false" aria-label="Toggle submenu">'
			. '<span aria-hidden="true">▾</span>'
			. '</button>'
			. $args->after;
		return $item_output;
	}
	if (in_array('lf-menu-call', $classes, true) && function_exists('lf_icon')) {
		$icon = lf_icon('phone', ['class' => 'lf-call__icon lf-icon lf-icon--inherit', 'aria-hidden' => 'true']);
		$icon = '<span class="lf-call__icon-wrap" aria-hidden="true">' . $icon . '</span>';
		$item_output = preg_replace('/(<a[^>]*>)(.*?)(<\/a>)/', '$1' . $icon . '<span class="lf-menu-call__label screen-reader-text">$2</span>$3', $item_output, 1);
	}
	if (in_array('lf-menu-more', $classes, true)) {
		$title = apply_filters('nav_menu_item_title', $item->title, $item, $args, $depth);
		$item_output = $args->before
			. '<button type="button" class="site-header__more-toggle" aria-haspopup="true" aria-expanded="false">'
			. $args->link_before . '<span class="site-header__more-text">' . esc_html($title) . '</span>'
			. $args->link_after
			. '</button>'
			. '<button type="button" class="site-header__submenu-toggle site-header__submenu-toggle--more" aria-expanded="false" aria-label="Toggle submenu">'
			. '<span aria-hidden="true">▾</span>'
			. '</button>'
			. $args->after;
	}
	return $item_output;
}
add_filter('walker_nav_menu_start_el', 'lf_header_menu_item_output', 10, 4);

function lf_header_menu_css_classes(array $classes, \WP_Post $item, $args, int $depth): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $depth !== 0) {
		return $classes;
	}
	$services_dropdown = function_exists('lf_header_menu_cpt_nav_dropdown_enabled')
		&& lf_header_menu_cpt_nav_dropdown_enabled('services');
	$areas_dropdown = function_exists('lf_header_menu_cpt_nav_dropdown_enabled')
		&& lf_header_menu_cpt_nav_dropdown_enabled('areas');
	if (in_array('lf-menu-services-parent', $classes, true)) {
		if ($services_dropdown) {
			$classes[] = 'lf-menu-group-parent';
			$classes[] = 'menu-item-has-children';
		} else {
			$classes = array_values(array_filter(
				$classes,
				static fn (string $class): bool => !in_array(
					$class,
					['lf-menu-group-parent', 'menu-item-has-children', 'lf-mega-menu', 'lf-mega-menu--services'],
					true
				)
			));
		}
	} elseif (in_array('lf-menu-areas-parent', $classes, true)) {
		if ($areas_dropdown) {
			$classes[] = 'lf-menu-group-parent';
			$classes[] = 'menu-item-has-children';
		} else {
			$classes = array_values(array_filter(
				$classes,
				static fn (string $class): bool => !in_array(
					$class,
					['lf-menu-group-parent', 'menu-item-has-children'],
					true
				)
			));
		}
	} elseif (in_array('menu-item-has-children', $classes, true) && !in_array('lf-menu-more', $classes, true)) {
		$title = strtolower(trim(wp_strip_all_tags((string) $item->title)));
		if ($title === 'services' && $services_dropdown) {
			$classes[] = 'lf-menu-group-parent';
		} elseif ($title === 'service areas' && $areas_dropdown) {
			$classes[] = 'lf-menu-group-parent';
		}
	}
	return array_values(array_unique($classes));
}
add_filter('nav_menu_css_class', 'lf_header_menu_css_classes', 10, 4);

function lf_header_menu_item_has_class(\WP_Post $item, string $class): bool {
	$classes = is_array($item->classes ?? null) ? $item->classes : [];
	return in_array($class, $classes, true);
}

function lf_header_menu_synthetic_child(int $parent_id, int $synthetic_id, string $title, string $url, array $classes): \WP_Post {
	$item = new \stdClass();
	$item->ID = $synthetic_id;
	$item->db_id = $synthetic_id;
	$item->menu_item_parent = $parent_id;
	$item->object_id = 0;
	$item->object = 'custom';
	$item->type = 'custom';
	$item->type_label = __('Custom Link', 'leadsforward-core');
	$item->title = $title;
	$item->url = $url;
	$item->target = '';
	$item->attr_title = '';
	$item->description = '';
	$item->classes = $classes;
	$item->xfn = '';
	$item->status = 'publish';
	$item->current = false;
	$item->current_item_ancestor = false;
	$item->current_item_parent = false;
	$item->menu_order = 9999 + abs($synthetic_id);
	return new \WP_Post($item);
}

/**
 * Services / Service Areas submenus: individual links first, divider(s), then overview (all-link) last.
 *
 * wp_nav_menu passes items keyed by menu_order; Walker sibling order follows the flattened list order.
 * We rebuild order and renumber menu_order globally so keys stay consistent.
 */
function lf_header_menu_reorder_services_areas_children(array $items): array {
	if ($items === []) {
		return $items;
	}
	$flat = array_values($items);
	usort(
		$flat,
		static function ($a, $b): int {
			return ((int) ($a->menu_order ?? 0)) <=> ((int) ($b->menu_order ?? 0));
		}
	);

	$group_parent_ids = [];
	foreach ($flat as $menu_item) {
		if ((int) ($menu_item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		$title = strtolower(trim(wp_strip_all_tags((string) ($menu_item->title ?? ''))));
		$is_services_group = $title === 'services' || lf_header_menu_item_has_class($menu_item, 'lf-menu-services-parent');
		$is_areas_group = $title === 'service areas' || lf_header_menu_item_has_class($menu_item, 'lf-menu-areas-parent');
		if (!$is_services_group && !$is_areas_group) {
			continue;
		}
		$pid = (int) ($menu_item->ID ?? 0);
		if ($pid > 0) {
			$group_parent_ids[] = $pid;
		}
	}

	$sort_by_order = static function ($a, $b): int {
		return ((int) ($a->menu_order ?? 0)) <=> ((int) ($b->menu_order ?? 0));
	};

	foreach ($group_parent_ids as $parent_id) {
		$indices = [];
		foreach ($flat as $idx => $menu_item) {
			if ((int) ($menu_item->menu_item_parent ?? 0) === $parent_id) {
				$indices[] = $idx;
			}
		}
		if ($indices === []) {
			continue;
		}
		$children = [];
		foreach ($indices as $idx) {
			$children[] = $flat[$idx];
		}
		$regular = [];
		$dividers = [];
		$all_links = [];
		$search_hosts = [];
		foreach ($children as $child) {
			if (lf_header_menu_item_has_class($child, 'lf-submenu-all-link')) {
				$all_links[] = $child;
			} elseif (lf_header_menu_item_has_class($child, 'lf-submenu-divider')) {
				$dividers[] = $child;
			} elseif (lf_header_menu_item_has_class($child, 'lf-mega-search-host')) {
				$search_hosts[] = $child;
			} elseif (lf_header_menu_item_has_class($child, 'lf-menu-service-category')) {
				$regular[] = $child;
			} else {
				$regular[] = $child;
			}
		}
		if ($regular !== [] && lf_header_menu_item_has_class($regular[0], 'lf-menu-service-category')) {
			$cat_rank = static function (\WP_Post $item): int {
				$classes = is_array($item->classes ?? null) ? $item->classes : [];
				$order = ['foundation-repair', 'waterproofing', 'crawl-space'];
				foreach ($order as $idx => $slug) {
					if (in_array('lf-menu-cat--' . $slug, $classes, true)) {
						return $idx;
					}
				}

				return 99;
			};
			usort(
				$regular,
				static function (\WP_Post $a, \WP_Post $b) use ($cat_rank): int {
					$ra = $cat_rank($a);
					$rb = $cat_rank($b);
					if ($ra !== $rb) {
						return $ra <=> $rb;
					}

					return strcasecmp((string) ($a->title ?? ''), (string) ($b->title ?? ''));
				}
			);
		} else {
			usort($regular, $sort_by_order);
		}
		usort($dividers, $sort_by_order);
		usort($all_links, $sort_by_order);
		usort($search_hosts, $sort_by_order);
		$is_services_mega = false;
		foreach ($flat as $menu_item) {
			if (!$menu_item instanceof \WP_Post) {
				continue;
			}
			if ((int) ($menu_item->ID ?? 0) === $parent_id
				&& lf_header_menu_item_has_class($menu_item, 'lf-menu-services-parent')
				&& function_exists('lf_mega_menu_enabled')
				&& lf_mega_menu_enabled()) {
				$is_services_mega = true;
				break;
			}
		}
		if ($is_services_mega) {
			// Categories, then "All Services", then search at the bottom.
			$ordered = array_merge($regular, $all_links, $search_hosts);
		} else {
			$ordered = array_merge($regular, $dividers, $all_links, $search_hosts);
		}

		$first_idx = min($indices);
		foreach (array_reverse($indices) as $idx) {
			array_splice($flat, $idx, 1);
		}
		array_splice($flat, $first_idx, 0, $ordered);
	}

	$n = 1;
	foreach ($flat as $menu_item) {
		$menu_item->menu_order = $n;
		++$n;
	}
	$out = [];
	foreach ($flat as $menu_item) {
		$out[$menu_item->menu_order] = $menu_item;
	}
	return $out;
}

function lf_header_menu_objects(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || empty($items)) {
		return $items;
	}
	$children_by_parent = [];
	foreach ($items as $menu_item) {
		$parent = (int) ($menu_item->menu_item_parent ?? 0);
		if ($parent !== 0) {
			$children_by_parent[$parent][] = $menu_item;
		}
	}
	$synthetic_id = -1000;
	$extra_items = [];
	foreach ($items as $menu_item) {
		$is_top_level = (int) ($menu_item->menu_item_parent ?? 0) === 0;
		$title = strtolower(trim(wp_strip_all_tags((string) ($menu_item->title ?? ''))));
		$is_services_group = $title === 'services' || lf_header_menu_item_has_class($menu_item, 'lf-menu-services-parent');
		$is_areas_group = $title === 'service areas' || lf_header_menu_item_has_class($menu_item, 'lf-menu-areas-parent');
		$is_group = $is_top_level && (
			($is_services_group && function_exists('lf_header_menu_cpt_nav_dropdown_enabled') && lf_header_menu_cpt_nav_dropdown_enabled('services'))
			|| ($is_areas_group && function_exists('lf_header_menu_cpt_nav_dropdown_enabled') && lf_header_menu_cpt_nav_dropdown_enabled('areas'))
		);
		if (!$is_group) {
			continue;
		}
		$parent_id = (int) ($menu_item->ID ?? 0);
		if ($parent_id <= 0) {
			continue;
		}
		$has_children = !empty($children_by_parent[$parent_id]);
		if (!$has_children) {
			continue;
		}
		$has_all_link = false;
		$has_divider = false;
		foreach ($children_by_parent[$parent_id] as $child) {
			if (lf_header_menu_item_has_class($child, 'lf-submenu-all-link')) {
				$has_all_link = true;
			}
			if (lf_header_menu_item_has_class($child, 'lf-submenu-divider')) {
				$has_divider = true;
			}
		}
		$all_url = (string) ($menu_item->url ?? '');
		if ($all_url === '' || $all_url === '#') {
			continue;
		}
		$is_services_mega = $is_services_group
			&& function_exists('lf_mega_menu_enabled')
			&& lf_mega_menu_enabled();
		if (!$has_divider && !$is_services_mega) {
			$extra_items[] = lf_header_menu_synthetic_child($parent_id, $synthetic_id--, '', '#', ['menu-item', 'lf-submenu-divider']);
		}
		if (!$has_all_link) {
			$all_title = $is_areas_group ? __('All Service Areas', 'leadsforward-core') : __('All Services', 'leadsforward-core');
			$extra_items[] = lf_header_menu_synthetic_child($parent_id, $synthetic_id--, $all_title, $all_url, ['menu-item', 'lf-submenu-all-link']);
		}
	}
	if (!empty($extra_items)) {
		$items = array_merge($items, $extra_items);
	}
	return lf_header_menu_reorder_services_areas_children($items);
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_objects', 10, 2);

/**
 * Re-order Services/Areas children after categorization and mega-menu prep (priority 12–20).
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_finalize_children_order(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}

	return lf_header_menu_reorder_services_areas_children($items);
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_finalize_children_order', 25, 2);

/**
 * Whether a header menu item represents Home / the static front page.
 */
function lf_header_menu_item_is_home_item(\WP_Post $item): bool {
	$home_id = (int) get_option('page_on_front');
	if (
		$home_id > 0
		&& (string) ($item->object ?? '') === 'page'
		&& (int) ($item->object_id ?? 0) === $home_id
	) {
		return true;
	}
	$t = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
	if ($t === 'home') {
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
	return false;
}

/**
 * Whether a top-level item is the Services dropdown parent.
 */
function lf_header_menu_item_is_services_parent(\WP_Post $item): bool {
	if ((int) ($item->menu_item_parent ?? 0) !== 0) {
		return false;
	}
	$classes = is_array($item->classes ?? null) ? $item->classes : [];
	if (in_array('lf-menu-services-parent', $classes, true)) {
		return true;
	}
	$title = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
	if ($title === 'services' || $title === 'our services') {
		return true;
	}
	if (str_contains($title, 'service') && !str_contains($title, 'area')) {
		return true;
	}
	$services_page = get_page_by_path('services');
	if ($services_page instanceof \WP_Post) {
		$services_url = trailingslashit((string) get_permalink($services_page));
		$item_url = trailingslashit((string) ($item->url ?? ''));
		if ($item_url !== '' && $item_url === $services_url) {
			return true;
		}
	}

	return false;
}

/**
 * Whether a top-level item is the Service Areas dropdown parent.
 */
function lf_header_menu_item_is_areas_parent(\WP_Post $item): bool {
	if ((int) ($item->menu_item_parent ?? 0) !== 0) {
		return false;
	}
	$classes = is_array($item->classes ?? null) ? $item->classes : [];
	if (in_array('lf-menu-areas-parent', $classes, true)) {
		return true;
	}
	$title = strtolower(trim(wp_strip_all_tags((string) ($item->title ?? ''))));
	if ($title === 'service areas' || $title === 'areas' || str_contains($title, 'service area')) {
		return true;
	}
	$areas_page = get_page_by_path('service-areas');
	if ($areas_page instanceof \WP_Post) {
		$areas_url = trailingslashit((string) get_permalink($areas_page));
		$item_url = trailingslashit((string) ($item->url ?? ''));
		if ($item_url !== '' && $item_url === $areas_url) {
			return true;
		}
	}

	return false;
}

/**
 * Stamp Services / Service Areas parent classes when the stored menu is missing markers.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function lf_header_menu_ensure_parent_marker_classes(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post || (int) ($item->menu_item_parent ?? 0) !== 0) {
			continue;
		}
		$classes = is_array($item->classes ?? null) ? $item->classes : [];
		if (lf_header_menu_item_is_services_parent($item) && !in_array('lf-menu-services-parent', $classes, true)) {
			$classes[] = 'lf-menu-services-parent';
			$item->classes = array_values(array_unique($classes));
		}
		if (lf_header_menu_item_is_areas_parent($item) && !in_array('lf-menu-areas-parent', $classes, true)) {
			$classes = is_array($item->classes ?? null) ? $item->classes : [];
			$classes[] = 'lf-menu-areas-parent';
			$item->classes = array_values(array_unique($classes));
		}
	}

	return $items;
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_ensure_parent_marker_classes', 17, 2);

/**
 * Sort key for top-level header blocks: Home → Services → Service Areas → About → … → Call → CTA → More.
 *
 * @return array{0:int,1:int}
 */
function lf_header_menu_top_level_sort_tuple(\WP_Post $item): array {
	$classes = is_array($item->classes ?? null) ? $item->classes : [];
	$mo = (int) ($item->menu_order ?? 0);
	if (in_array('lf-menu-more', $classes, true)) {
		return [900, $mo];
	}
	if (in_array('lf-menu-cta', $classes, true)) {
		return [800, $mo];
	}
	if (in_array('lf-menu-call', $classes, true)) {
		return [700, $mo];
	}
	if (lf_header_menu_item_is_home_item($item) || in_array('lf-menu-home', $classes, true)) {
		return [0, $mo];
	}
	if (lf_header_menu_item_is_services_parent($item)) {
		return [105, $mo];
	}
	if (lf_header_menu_item_is_areas_parent($item)) {
		return [110, $mo];
	}
	if (in_array('lf-menu-about', $classes, true)
		|| (function_exists('lf_header_menu_item_is_about') && lf_header_menu_item_is_about($item))) {
		return [115, $mo];
	}
	if (function_exists('lf_header_menu_item_violates_fleet_top_level')
		&& lf_header_menu_item_violates_fleet_top_level($item)) {
		return [895, $mo];
	}
	return [120, $mo];
}

/**
 * Reorder flat wp_nav_menu item list so top-level sections appear in a sensible order for the header.
 *
 * @param array<int,\WP_Post> $items
 * @return array<int,\WP_Post>
 */
function lf_header_menu_reorder_flat_blocks(array $items): array {
	if ($items === []) {
		return $items;
	}
	$items = array_values($items);
	if (count($items) < 2) {
		return $items;
	}

	$children_by_parent = [];
	foreach ($items as $item) {
		$parent = (int) ($item->menu_item_parent ?? 0);
		if ($parent !== 0) {
			$children_by_parent[$parent][] = $item;
		}
	}

	$tops = [];
	foreach ($items as $item) {
		if ((int) ($item->menu_item_parent ?? 0) === 0) {
			$tops[] = $item;
		}
	}
	if (count($tops) < 2) {
		return $items;
	}

	usort(
		$tops,
		static function ($a, $b): int {
			if (!$a instanceof \WP_Post || !$b instanceof \WP_Post) {
				return 0;
			}
			$ta = lf_header_menu_top_level_sort_tuple($a);
			$tb = lf_header_menu_top_level_sort_tuple($b);
			return $ta <=> $tb;
		}
	);

	$sort_children = static function (array $children): array {
		usort(
			$children,
			static function ($a, $b): int {
				if (!$a instanceof \WP_Post || !$b instanceof \WP_Post) {
					return 0;
				}
				return ((int) ($a->menu_order ?? 0)) <=> ((int) ($b->menu_order ?? 0));
			}
		);
		return $children;
	};

	$append_subtree = null;
	$append_subtree = static function ($item, array &$out) use (&$append_subtree, $children_by_parent, $sort_children): void {
		if (!$item instanceof \WP_Post) {
			return;
		}
		$out[] = $item;
		$kids = $sort_children(array_merge([], $children_by_parent[(int) $item->ID] ?? []));
		foreach ($kids as $child) {
			$append_subtree($child, $out);
		}
	};

	$out = [];
	foreach ($tops as $top) {
		$append_subtree($top, $out);
	}

	$seen = [];
	foreach ($out as $item) {
		$seen[(int) ($item->ID ?? 0)] = true;
	}
	foreach ($items as $item) {
		$id = (int) ($item->ID ?? 0);
		if ($id !== 0 && !isset($seen[$id])) {
			$out[] = $item;
			$seen[$id] = true;
		}
	}

	// WordPress re-sorts filtered items by menu_order before the Walker runs.
	$n = 1;
	foreach ($out as $menu_item) {
		if ($menu_item instanceof \WP_Post) {
			$menu_item->menu_order = $n;
			++$n;
		}
	}
	$renumbered = [];
	foreach ($out as $menu_item) {
		if ($menu_item instanceof \WP_Post) {
			$renumbered[(int) $menu_item->menu_order] = $menu_item;
		}
	}

	return $renumbered !== [] ? $renumbered : $out;
}

/**
 * @param array<int,\WP_Post> $items
 * @return array<int,\WP_Post>
 */
function lf_header_menu_reorder_display_objects(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || $items === []) {
		return $items;
	}
	return lf_header_menu_reorder_flat_blocks($items);
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_reorder_display_objects', 30, 2);

/**
 * Remind admins when nav menus exist but nothing is assigned to the Header Menu theme location.
 * The front header only calls wp_nav_menu for `header_menu`; a menu named "Header Menu" is not enough
 * until it is checked under Appearance → Menus → Manage Locations (or Menu Settings).
 */
function lf_admin_notice_header_menu_location(): void {
	if (!is_admin() || !current_user_can('edit_theme_options')) {
		return;
	}
	if (has_nav_menu('header_menu')) {
		return;
	}
	$menus = wp_get_nav_menus();
	if (empty($menus)) {
		return;
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!is_object($screen) || $screen->id === '') {
		return;
	}
	$id = (string) $screen->id;
	$on_appearance_sub = strpos($id, 'appearance_page_') === 0;
	$allowed = ['dashboard', 'themes', 'nav-menus'];
	if (!in_array($id, $allowed, true) && !$on_appearance_sub) {
		return;
	}
	$url = admin_url('nav-menus.php?action=locations');
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__(
		'LeadsForward: no menu is assigned to the “Header Menu” display location, so the site header will not show your primary navigation. Open Manage Locations and assign your menu to Header Menu.',
		'leadsforward-core'
	);
	echo ' <a href="' . esc_url($url) . '">' . esc_html__('Manage Locations', 'leadsforward-core') . '</a>';
	echo '</p></div>';
}
add_action('admin_notices', 'lf_admin_notice_header_menu_location');

/**
 * Register ACF Options pages when ACF is active. Global Business Info, CTAs, Schema.
 */
function lf_register_acf_options_pages(): void {
	if (!function_exists('acf_add_options_page')) {
		return;
	}
	// All options live under LeadsForward.
	$parent = 'lf-ops';
	// Global Business Info: NAP, geo, hours.
	acf_add_options_sub_page([
		'page_title'  => __('Global Business Info', 'leadsforward-core'),
		'menu_title'  => __('Business Info', 'leadsforward-core'),
		'menu_slug'   => 'lf-business-info',
		'parent_slug' => $parent,
		'capability'  => 'edit_theme_options',
	]);
	// Global Settings is rendered as a custom page (see inc/ops/menu.php).
	// Branding fields are now included inside Global Settings.
	// Global CTAs: primary/secondary text, GHL form.
	acf_add_options_sub_page([
		'page_title'  => __('Global CTAs', 'leadsforward-core'),
		'menu_title'  => __('CTAs', 'leadsforward-core'),
		'menu_slug'   => 'lf-ctas',
		'parent_slug' => $parent,
		'capability'  => 'edit_theme_options',
	]);
	// Schema controls: on/off toggles per schema type.
	acf_add_options_sub_page([
		'page_title'  => __('Schema Controls', 'leadsforward-core'),
		'menu_title'  => __('Schema', 'leadsforward-core'),
		'menu_slug'   => 'lf-schema',
		'parent_slug' => $parent,
		'capability'  => 'edit_theme_options',
	]);
	// Homepage: section order, layout variants, CTA overrides.
	acf_add_options_sub_page([
		'page_title'  => __('Homepage', 'leadsforward-core'),
		'menu_title'  => __('Homepage', 'leadsforward-core'),
		'menu_slug'   => 'lf-homepage',
		'parent_slug' => $parent,
		'capability'  => 'edit_theme_options',
	]);
	// Variation: site-wide profile, section ordering, copy templates.
	acf_add_options_sub_page([
		'page_title'  => __('Variation', 'leadsforward-core'),
		'menu_title'  => __('Variation', 'leadsforward-core'),
		'menu_slug'   => 'lf-variation',
		'parent_slug' => $parent,
		'capability'  => 'edit_theme_options',
	]);
}
add_action('acf/init', 'lf_register_acf_options_pages');
