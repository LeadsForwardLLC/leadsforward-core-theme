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

	// Register nav menus. No hardcoded links; templates output nothing if empty.
	register_nav_menus([
		'header_menu'   => __('Header Menu', 'leadsforward-core'),
		'footer_menu'   => __('Footer Menu', 'leadsforward-core'),
		'utility_menu'  => __('Utility Menu', 'leadsforward-core'),
	]);
}
add_action('after_setup_theme', 'lf_theme_setup');

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
		return $label !== '' ? $label : __('Free Estimate', 'leadsforward-core');
	}
	if (in_array('lf-menu-call', $classes, true)) {
		return __('Call Now', 'leadsforward-core');
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
	if (in_array('lf-menu-cta', $classes, true)) {
		$cta_url = function_exists('lf_get_global_option') ? (string) lf_get_global_option('lf_header_cta_url', '') : '';
		if ($cta_url !== '') {
			$atts['href'] = esc_url($cta_url);
		} else {
			$atts['href'] = '#';
			$atts['data-lf-quote-trigger'] = '1';
			$atts['data-lf-quote-source'] = 'header-menu';
		}
		$atts['class'] = trim(($atts['class'] ?? '') . ' lf-btn lf-btn--primary');
	}
	if (in_array('lf-menu-call', $classes, true)) {
		$phone = function_exists('lf_get_cta_phone') ? (string) lf_get_cta_phone() : '';
		$atts['href'] = $phone !== '' ? 'tel:' . esc_attr($phone) : '#';
		$atts['class'] = trim(($atts['class'] ?? '') . ' lf-menu-call__link');
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
		$title = apply_filters('nav_menu_item_title', $item->title, $item, $args, $depth);
		$item_output = $args->before
			. '<span class="site-header__group-label">' . $args->link_before . esc_html($title) . $args->link_after . '</span>'
			. '<button type="button" class="site-header__submenu-toggle" aria-expanded="false" aria-label="Toggle submenu">'
			. '<span aria-hidden="true">▾</span>'
			. '</button>'
			. $args->after;
		return $item_output;
	}
	if (in_array('lf-menu-call', $classes, true) && function_exists('lf_icon')) {
		$icon = lf_icon('phone', ['class' => 'lf-menu-call__icon lf-icon lf-icon--sm lf-icon--inherit', 'aria-hidden' => 'true']);
		$item_output = preg_replace('/(<a[^>]*>)(.*?)(<\/a>)/', '$1' . $icon . '<span>$2</span>$3', $item_output, 1);
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
	if (in_array('menu-item-has-children', $classes, true) && !in_array('lf-menu-more', $classes, true)) {
		$title = strtolower(trim(wp_strip_all_tags((string) $item->title)));
		if ($title === 'services' || $title === 'service areas') {
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

function lf_header_menu_objects(array $items, $args): array {
	if (!is_object($args) || ($args->theme_location ?? '') !== 'header_menu' || empty($items)) {
		return $items;
	}
	$children_by_parent = [];
	foreach ($items as $menu_item) {
		$parent = (int) ($menu_item->menu_item_parent ?? 0);
		if ($parent > 0) {
			$children_by_parent[$parent][] = $menu_item;
		}
	}
	$synthetic_id = -1000;
	$extra_items = [];
	foreach ($items as $menu_item) {
		$is_top_level = (int) ($menu_item->menu_item_parent ?? 0) === 0;
		$title = strtolower(trim(wp_strip_all_tags((string) ($menu_item->title ?? ''))));
		$is_group = $is_top_level && ($title === 'services' || $title === 'service areas');
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
		if (!$has_divider) {
			$extra_items[] = lf_header_menu_synthetic_child($parent_id, $synthetic_id--, '', '#', ['menu-item', 'lf-submenu-divider']);
		}
		if (!$has_all_link) {
			$all_title = $title === 'service areas' ? __('All Service Areas', 'leadsforward-core') : __('All Services', 'leadsforward-core');
			$extra_items[] = lf_header_menu_synthetic_child($parent_id, $synthetic_id--, $all_title, $all_url, ['menu-item', 'lf-submenu-all-link']);
		}
	}
	if (!empty($extra_items)) {
		$items = array_merge($items, $extra_items);
	}
	return $items;
}
add_filter('wp_nav_menu_objects', 'lf_header_menu_objects', 10, 2);

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
