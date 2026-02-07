<?php
/**
 * Development-only site reset. Rerun wizard safely. No frontend, no AJAX, no production.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_DEV_RESET_OPTION_IDS   = 'lf_wizard_created_ids';
const LF_DEV_RESET_OPTION_LOG  = 'lf_dev_reset_log';
const LF_DEV_RESET_LOG_MAX     = 20;
const LF_DEV_RESET_MENU_NAMES  = ['Header Menu', 'Footer Menu'];
const LF_DEV_RESET_MENU_LOCATIONS = ['header_menu', 'footer_menu'];

/**
 * True only when WP_DEBUG, WP_ENV=local, or LF_DEV_RESET_ENABLED. Used for visibility and abort.
 */
function lf_dev_reset_allowed(): bool {
	if (defined('WP_DEBUG') && WP_DEBUG === true) {
		return true;
	}
	if (defined('WP_ENV') && WP_ENV === 'local') {
		return true;
	}
	if (defined('LF_DEV_RESET_ENABLED') && LF_DEV_RESET_ENABLED === true) {
		return true;
	}
	return false;
}

add_action('admin_init', 'lf_dev_reset_handle_post', 5);
// Menu item is registered under LeadsForward → Reset site (dev) in inc/ops/menu.php

function lf_dev_reset_handle_post(): void {
	if (!lf_dev_reset_allowed()) {
		return;
	}
	if (!isset($_POST['lf_dev_reset']) || $_POST['lf_dev_reset'] !== '1') {
		return;
	}
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!isset($_POST['lf_dev_reset_confirm']) || trim($_POST['lf_dev_reset_confirm']) !== 'RESET') {
		wp_safe_redirect(admin_url('admin.php?page=lf-dev-reset&error=confirm'));
		exit;
	}
	check_admin_referer('lf_dev_reset', 'lf_dev_reset_nonce');

	lf_dev_reset_run();
	wp_safe_redirect(admin_url('admin.php?page=lf-dev-reset&reset=1'));
	exit;
}

function lf_dev_reset_render_page(): void {
	if (!lf_dev_reset_allowed() || !current_user_can('manage_options')) {
		return;
	}
	$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
	$done  = isset($_GET['reset']) && $_GET['reset'] === '1';
	echo '<div class="wrap"><h1>' . esc_html__('Reset site (dev only)', 'leadsforward-core') . '</h1>';
	if ($done) {
		echo '<div class="notice notice-success"><p>' . esc_html__('Site reset complete. You can run the setup wizard again.', 'leadsforward-core') . '</p></div>';
		echo '<p><a href="' . esc_url(admin_url('admin.php?page=lf-ops')) . '" class="button button-primary">' . esc_html__('Run setup wizard', 'leadsforward-core') . '</a></p></div>';
		return;
	}
	if ($error === 'confirm') {
		echo '<div class="notice notice-error"><p>' . esc_html__('You must type RESET exactly to confirm.', 'leadsforward-core') . '</p></div>';
	}
	echo '<p>' . esc_html__('This will delete all content, menus, and options created by the setup wizard. Only available when WP_DEBUG is true or WP_ENV is local.', 'leadsforward-core') . '</p>';
	echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=lf-dev-reset')) . '">';
	wp_nonce_field('lf_dev_reset', 'lf_dev_reset_nonce');
	echo '<input type="hidden" name="lf_dev_reset" value="1" />';
	echo '<p><label for="lf_dev_reset_confirm">' . esc_html__('Type RESET to confirm:', 'leadsforward-core') . '</label><br />';
	echo '<input type="text" id="lf_dev_reset_confirm" name="lf_dev_reset_confirm" value="" autocomplete="off" style="text-transform:uppercase;" /></p>';
	echo '<p><input type="submit" class="button" value="' . esc_attr__('RESET SITE (DEV ONLY)', 'leadsforward-core') . '" style="background:#b32d2e;border-color:#b32d2e;color:#fff;" /></p>';
	echo '</form></div>';
}

/**
 * Delete wizard content, menus, reset ACF options, clear wizard flag, log.
 * Uses stored IDs when present; otherwise finds wizard pages by slug and all services/service areas.
 */
function lf_dev_reset_run(): void {
	if (!lf_dev_reset_allowed()) {
		return;
	}

	$ids = get_option(LF_DEV_RESET_OPTION_IDS, []);
	$ids = is_array($ids) ? $ids : [];

	$page_ids    = $ids['page_ids'] ?? [];
	$service_ids = $ids['service_ids'] ?? [];
	$area_ids    = $ids['service_area_ids'] ?? [];

	// Fallback when no IDs were ever stored (e.g. site set up before tracking): delete by convention.
	if (empty($page_ids) && empty($service_ids) && empty($area_ids)) {
		$slugs = function_exists('lf_wizard_required_page_slugs') ? lf_wizard_required_page_slugs() : [];
		foreach ($slugs as $slug) {
			$page = get_page_by_path($slug, OBJECT, 'page');
			if ($page && $page->ID) {
				wp_delete_post($page->ID, true);
			}
		}
		$services = get_posts([
			'post_type'      => 'lf_service',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]);
		foreach ($services as $id) {
			wp_delete_post((int) $id, true);
		}
		$areas = get_posts([
			'post_type'      => 'lf_service_area',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]);
		foreach ($areas as $id) {
			wp_delete_post((int) $id, true);
		}
	} else {
		foreach ($page_ids as $id) {
			if (is_numeric($id)) {
				wp_delete_post((int) $id, true);
			}
		}
		foreach ($service_ids as $id) {
			if (is_numeric($id)) {
				wp_delete_post((int) $id, true);
			}
		}
		foreach ($area_ids as $id) {
			if (is_numeric($id)) {
				wp_delete_post((int) $id, true);
			}
		}
	}

	$menus = wp_get_nav_menus();
	foreach ($menus as $menu) {
		if (in_array($menu->name, LF_DEV_RESET_MENU_NAMES, true)) {
			wp_delete_nav_menu($menu->term_id);
		}
	}
	$locations = get_theme_mod('nav_menu_locations') ?: [];
	foreach (LF_DEV_RESET_MENU_LOCATIONS as $loc) {
		unset($locations[$loc]);
	}
	set_theme_mod('nav_menu_locations', $locations);

	if (function_exists('update_field')) {
		$business_slug = defined('LF_OPTIONS_PAGE_BUSINESS') ? LF_OPTIONS_PAGE_BUSINESS : 'lf-business-info';
		update_field('lf_business_name', '', $business_slug);
		update_field('lf_business_phone', '', $business_slug);
		update_field('lf_business_email', '', $business_slug);
		update_field('lf_business_address', '', $business_slug);
		update_field('lf_business_hours', '', $business_slug);
		update_field('lf_cta_primary_text', '', 'option');
		update_field('lf_cta_secondary_text', '', 'option');
		update_field('variation_profile', 'a', 'option');
		update_field('lf_schema_review', false, 'option');
		update_field('homepage_sections', [], 'option');
		update_field('lf_homepage_cta_primary', '', 'option');
		update_field('lf_homepage_cta_secondary', '', 'option');
		update_field('lf_homepage_cta_ghl', '', 'option');
		update_field('lf_homepage_cta_primary_type', '', 'option');
	}

	// Clear homepage section config so LeadsForward → Homepage shows empty (all sections off, no copy)
	if (function_exists('lf_homepage_empty_config')) {
		$empty_config = lf_homepage_empty_config();
		if (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
			update_option(LF_HOMEPAGE_CONFIG_OPTION, $empty_config, true);
		}
	} elseif (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
		delete_option(LF_HOMEPAGE_CONFIG_OPTION);
	}
	if (defined('LF_HOMEPAGE_NICHE_OPTION')) {
		delete_option(LF_HOMEPAGE_NICHE_OPTION);
	}

	update_option('show_on_front', 'posts');
	update_option('page_on_front', 0);
	delete_option('lf_setup_wizard_complete');
	delete_option(LF_DEV_RESET_OPTION_IDS);

	$log = get_option(LF_DEV_RESET_OPTION_LOG, []);
	if (!is_array($log)) {
		$log = [];
	}
	array_unshift($log, [
		'time'     => time(),
		'user_id'  => get_current_user_id(),
	]);
	$log = array_slice($log, 0, LF_DEV_RESET_LOG_MAX);
	update_option(LF_DEV_RESET_OPTION_LOG, $log);
}
