<?php
/**
 * Team role + limited backend controls for non-admin LeadsForward users.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_TEAM_EDITOR_ROLE = 'lf_team_editor';
const LF_TESTER_ROLE = 'lf_tester';

add_action('after_switch_theme', 'lf_team_role_register');
add_action('init', 'lf_team_role_register');
add_action('admin_menu', 'lf_team_role_limit_admin_menus', 1001);
add_action('admin_init', 'lf_team_role_block_sensitive_pages');

/**
 * Role for team members who should edit content but avoid risky global controls.
 */
function lf_team_role_register(): void {
	$existing = get_role(LF_TEAM_EDITOR_ROLE);
	if ($existing instanceof \WP_Role) {
		if (!$existing->has_cap('edit_theme_options')) {
			$existing->add_cap('edit_theme_options');
		}
	} else {
		$editor = get_role('editor');
		if ($editor instanceof \WP_Role) {
			$caps = $editor->capabilities;
			$caps['edit_theme_options'] = true;
			add_role(LF_TEAM_EDITOR_ROLE, __('LeadsForward Team Editor', 'leadsforward-core'), $caps);
		}
	}

	// Tester role: same idea as editor, but explicitly named for QA/testing.
	$tester = get_role(LF_TESTER_ROLE);
	if ($tester instanceof \WP_Role) {
		if (!$tester->has_cap('edit_theme_options')) {
			$tester->add_cap('edit_theme_options');
		}
		return;
	}
	$base = get_role('editor');
	if (!$base instanceof \WP_Role) {
		return;
	}
	$caps = $base->capabilities;
	$caps['edit_theme_options'] = true;
	add_role(LF_TESTER_ROLE, __('LeadsForward Tester', 'leadsforward-core'), $caps);
}

/**
 * Users with theme editing capability but no manage_options are treated as limited users.
 */
function lf_is_limited_ops_user(): bool {
	return current_user_can('edit_theme_options') && !current_user_can('manage_options');
}

/**
 * Hide high-risk core menu pages for limited users.
 */
function lf_team_role_limit_admin_menus(): void {
	if (!lf_is_limited_ops_user()) {
		return;
	}
	$hide_core = [
		'plugins.php',
		'tools.php',
		'users.php',
		'themes.php',
		'options-general.php',
		'options-writing.php',
		'options-reading.php',
		'options-discussion.php',
		'options-media.php',
		'options-permalink.php',
		'options-privacy.php',
	];
	foreach ($hide_core as $slug) {
		remove_menu_page($slug);
	}
	$hide_leadsforward = [
		'lf-ops-config',
		'lf-ops-bulk',
		'lf-ops-audit',
	];
	foreach ($hide_leadsforward as $slug) {
		remove_submenu_page('lf-ops', $slug);
	}
}

/**
 * Block direct URL access to sensitive admin pages for limited users.
 */
function lf_team_role_block_sensitive_pages(): void {
	if (!is_admin() || !lf_is_limited_ops_user()) {
		return;
	}
	$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
	if ($page === '') {
		return;
	}
	$blocked_pages = [
		'lf-ops-config',
		'lf-ops-bulk',
		'lf-ops-audit',
	];
	if (!in_array($page, $blocked_pages, true)) {
		return;
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-ops'));
	exit;
}
