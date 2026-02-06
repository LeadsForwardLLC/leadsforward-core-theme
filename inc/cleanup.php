<?php
/**
 * WordPress cleanup: remove emojis, oEmbed, dashicons, and frontend bloat.
 *
 * Aggressively reduces payload for lead-gen sites that don't need these.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Remove emoji scripts and styles from head and body. Saves requests and parsing.
 */
function lf_disable_emojis(): void {
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'print_emoji_styles');
	remove_filter('the_content_feed', 'wp_staticize_emoji');
	remove_filter('comment_text_rss', 'wp_staticize_emoji');
	remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'lf_disable_emojis');

/**
 * Remove oEmbed discovery and script from head. Use only when embedding is needed.
 */
function lf_remove_oembed(): void {
	remove_action('wp_head', 'wp_oembed_add_discovery_links');
	remove_action('wp_head', 'wp_oembed_add_host_js');
}
add_action('init', 'lf_remove_oembed');

/**
 * Dequeue Dashicons on frontend. Admin still gets them. Saves ~30KB.
 */
function lf_remove_dashicons_frontend(): void {
	if (is_admin()) {
		return;
	}
	wp_dequeue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'lf_remove_dashicons_frontend', 100);

/**
 * Conditionally remove block library CSS on frontend when not using blocks for layout.
 * Set to false if you rely on core block styles; keeps theme ultra-light by default.
 */
function lf_maybe_remove_block_library_css(): void {
	if (is_admin()) {
		return;
	}
	// Set to true to keep core block CSS; false to strip for minimal payload.
	$keep_block_css = apply_filters('lf_keep_block_library_css', false);
	if (!$keep_block_css) {
		wp_dequeue_style('wp-block-library');
		wp_dequeue_style('wp-block-library-theme');
		// global-styles kept so theme.json palette/typography still apply
	}
}
add_action('wp_enqueue_scripts', 'lf_maybe_remove_block_library_css', 100);

/**
 * Remove RSS feed links from head if site doesn't use feeds. Filter to re-enable.
 */
function lf_remove_rss_head_links(): void {
	if (apply_filters('lf_keep_rss_links', false)) {
		return;
	}
	remove_action('wp_head', 'feed_links', 2);
	remove_action('wp_head', 'feed_links_extra', 3);
}
add_action('init', 'lf_remove_rss_head_links');
