<?php
/**
 * Performance: defer scripts, limit Heartbeat, remove head bloat, critical CSS hooks.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Defer non-critical scripts so they don't block rendering. Skip admin and already-deferred.
 */
function lf_defer_scripts(string $tag, string $handle, string $src): string {
	if (is_admin()) {
		return $tag;
	}
	// Don't defer these; they may be needed inline.
	$no_defer = apply_filters('lf_script_no_defer', ['jquery-core', 'jquery-migrate']);
	if (in_array($handle, $no_defer, true)) {
		return $tag;
	}
	if (str_contains($tag, ' defer')) {
		return $tag;
	}
	return str_replace(' src=', ' defer src=', $tag);
}
add_filter('script_loader_tag', 'lf_defer_scripts', 10, 3);

/**
 * Disable jQuery on frontend. Re-enable per-page via wp_enqueue_script('jquery') if needed.
 */
function lf_disable_jquery_frontend(): void {
	if (is_admin()) {
		return;
	}
	if (apply_filters('lf_keep_jquery', false)) {
		return;
	}
	wp_deregister_script('jquery');
	wp_register_script('jquery', false, [], null, true);
}
add_action('wp_enqueue_scripts', 'lf_disable_jquery_frontend', 100);

/**
 * Limit Heartbeat API interval and/or disable on frontend to reduce server load at scale.
 */
function lf_limit_heartbeat(array $settings): array {
	// Only in admin: slow down heartbeat. Frontend can disable entirely.
	if (!is_admin() && apply_filters('lf_disable_heartbeat_frontend', true)) {
		$settings['interval'] = 0;
		return $settings;
	}
	$settings['interval'] = (int) apply_filters('lf_heartbeat_interval', 60);
	return $settings;
}
add_filter('heartbeat_settings', 'lf_limit_heartbeat');

/**
 * Remove common wp_head() outputs that add bloat. Keeps essential meta and no superfluous links.
 */
function lf_remove_head_bloat(): void {
	remove_action('wp_head', 'rsd_link');
	remove_action('wp_head', 'wlwmanifest_link');
	remove_action('wp_head', 'wp_generator');
	remove_action('wp_head', 'wp_shortlink_wp_head');
	remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
}
add_action('init', 'lf_remove_head_bloat');

/**
 * Hook for injecting critical CSS in head. Theme or plugin can output inline critical CSS here.
 */
function lf_critical_css_placeholder(): void {
	$critical = apply_filters('lf_critical_css', '');
	if ($critical !== '') {
		echo '<style id="lf-critical-css">' . wp_strip_all_tags($critical) . '</style>' . "\n";
	}
}
add_action('wp_head', 'lf_critical_css_placeholder', 1);
