<?php
/**
 * Admin-only guided tour mode for backend + frontend.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_enqueue_scripts', 'lf_tour_mode_admin_assets');
add_action('wp_enqueue_scripts', 'lf_tour_mode_frontend_assets');

function lf_tour_mode_enabled(): bool {
	return get_option('lf_tour_mode_admin', '0') === '1';
}

function lf_tour_mode_admin_allowed(): bool {
	return current_user_can('manage_options');
}

function lf_tour_mode_admin_assets(string $hook): void {
	if (!lf_tour_mode_enabled() || !lf_tour_mode_admin_allowed()) {
		return;
	}
	$is_leadsforward_page = $hook === 'toplevel_page_lf-ops' || str_starts_with($hook, 'leadsforward_page_lf-');
	if (!$is_leadsforward_page) {
		return;
	}
	wp_enqueue_style(
		'lf-tour-mode',
		LF_THEME_URI . '/assets/css/tour-mode.css',
		[],
		LF_THEME_VERSION
	);
	wp_enqueue_script(
		'lf-tour-mode',
		LF_THEME_URI . '/assets/js/tour-mode.js',
		[],
		LF_THEME_VERSION,
		true
	);
	$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
	wp_localize_script('lf-tour-mode', 'LFTourMode', [
		'enabled' => true,
		'area' => 'admin',
		'page' => $page,
		'storageKey' => 'lf_tour_mode',
		'canManage' => true,
	]);
}

function lf_tour_mode_frontend_assets(): void {
	if (!lf_tour_mode_enabled() || !is_user_logged_in() || !lf_tour_mode_admin_allowed()) {
		return;
	}
	wp_enqueue_style(
		'lf-tour-mode',
		LF_THEME_URI . '/assets/css/tour-mode.css',
		[],
		LF_THEME_VERSION
	);
	wp_enqueue_script(
		'lf-tour-mode',
		LF_THEME_URI . '/assets/js/tour-mode.js',
		[],
		LF_THEME_VERSION,
		true
	);
	wp_localize_script('lf-tour-mode', 'LFTourMode', [
		'enabled' => true,
		'area' => 'frontend',
		'page' => '',
		'storageKey' => 'lf_tour_mode',
		'canManage' => true,
	]);
}
