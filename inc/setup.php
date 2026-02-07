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
 * Register ACF Options pages when ACF is active. Global Business Info, CTAs, Schema.
 */
function lf_register_acf_options_pages(): void {
	if (!function_exists('acf_add_options_page')) {
		return;
	}
	// Parent: Theme Options (container only; redirects to first child).
	acf_add_options_page([
		'page_title' => __('Theme Options', 'leadsforward-core'),
		'menu_title' => __('Theme Options', 'leadsforward-core'),
		'menu_slug'  => 'lf-theme-options',
		'capability' => 'edit_theme_options',
		'redirect'   => true,
	]);
	// Global Business Info: NAP, geo, hours.
	acf_add_options_sub_page([
		'page_title'  => __('Global Business Info', 'leadsforward-core'),
		'menu_title'  => __('Business Info', 'leadsforward-core'),
		'menu_slug'   => 'lf-business-info',
		'parent_slug' => 'lf-theme-options',
	]);
	// Branding: global colors and surfaces.
	acf_add_options_sub_page([
		'page_title'  => __('Branding', 'leadsforward-core'),
		'menu_title'  => __('Branding', 'leadsforward-core'),
		'menu_slug'   => 'lf-branding',
		'parent_slug' => 'lf-theme-options',
	]);
	// Global CTAs: primary/secondary text, GHL form.
	acf_add_options_sub_page([
		'page_title'  => __('Global CTAs', 'leadsforward-core'),
		'menu_title'  => __('CTAs', 'leadsforward-core'),
		'menu_slug'   => 'lf-ctas',
		'parent_slug' => 'lf-theme-options',
	]);
	// Schema controls: on/off toggles per schema type.
	acf_add_options_sub_page([
		'page_title'  => __('Schema Controls', 'leadsforward-core'),
		'menu_title'  => __('Schema', 'leadsforward-core'),
		'menu_slug'   => 'lf-schema',
		'parent_slug' => 'lf-theme-options',
	]);
	// Homepage: section order, layout variants, CTA overrides.
	acf_add_options_sub_page([
		'page_title'  => __('Homepage', 'leadsforward-core'),
		'menu_title'  => __('Homepage', 'leadsforward-core'),
		'menu_slug'   => 'lf-homepage',
		'parent_slug' => 'lf-theme-options',
	]);
	// Variation: site-wide profile, section ordering, copy templates.
	acf_add_options_sub_page([
		'page_title'  => __('Variation', 'leadsforward-core'),
		'menu_title'  => __('Variation', 'leadsforward-core'),
		'menu_slug'   => 'lf-variation',
		'parent_slug' => 'lf-theme-options',
	]);
}
add_action('acf/init', 'lf_register_acf_options_pages');
