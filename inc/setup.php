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
	if (in_array('lf-menu-call', $classes, true) && function_exists('lf_icon')) {
		$icon = lf_icon('phone', ['class' => 'lf-menu-call__icon lf-icon lf-icon--sm lf-icon--inherit', 'aria-hidden' => 'true']);
		$item_output = preg_replace('/(<a[^>]*>)(.*?)(<\/a>)/', '$1' . $icon . '<span>$2</span>$3', $item_output, 1);
	}
	if (in_array('lf-menu-more', $classes, true)) {
		$title = apply_filters('nav_menu_item_title', $item->title, $item, $args, $depth);
		$item_output = $args->before
			. '<button type="button" class="site-header__more-toggle" aria-haspopup="true" aria-expanded="false">'
			. $args->link_before . $title . $args->link_after
			. '</button>'
			. $args->after;
	}
	return $item_output;
}
add_filter('walker_nav_menu_start_el', 'lf_header_menu_item_output', 10, 4);

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
