<?php
/**
 * Sitemap Sync admin UI + cron scheduler.
 *
 * @package LeadsForward_Core
 * @since 0.1.84
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @return array{
 *   ran_at:int,
 *   ok:bool,
 *   mode:string,
 *   reconcile:array<string,mixed>,
 *   menu:array<string,mixed>
 * }
 */
function lf_sitemap_sync_last_result(): array {
	$raw = (string) get_option('lf_sitemap_sync_last_result', '');
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return [
			'ran_at' => 0,
			'ok' => false,
			'mode' => '',
			'reconcile' => [],
			'menu' => [],
		];
	}
	return [
		'ran_at' => (int) ($decoded['ran_at'] ?? 0),
		'ok' => !empty($decoded['ok']),
		'mode' => (string) ($decoded['mode'] ?? ''),
		'reconcile' => is_array($decoded['reconcile'] ?? null) ? (array) $decoded['reconcile'] : [],
		'menu' => is_array($decoded['menu'] ?? null) ? (array) $decoded['menu'] : [],
	];
}

/**
 * @param array<string,mixed> $reconcile
 * @param array<string,mixed> $menu
 */
function lf_sitemap_sync_store_last_result(string $mode, bool $ok, array $reconcile, array $menu): void {
	update_option('lf_sitemap_sync_last_result', wp_json_encode([
		'ran_at' => time(),
		'ok' => $ok,
		'mode' => $mode,
		'reconcile' => $reconcile,
		'menu' => $menu,
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
}

function lf_sitemap_sync_cron_enabled(): bool {
	return (string) get_option('lf_sitemap_sync_cron_enabled', '1') !== '0';
}

function lf_sitemap_sync_schedule_cron(): void {
	$hook = 'lf_sitemap_sync_cron';
	$next = wp_next_scheduled($hook);
	if (!lf_sitemap_sync_cron_enabled()) {
		if ($next) {
			wp_unschedule_event($next, $hook);
		}
		return;
	}
	if (!$next) {
		wp_schedule_event(time() + 300, 'hourly', $hook);
	}
}
add_action('init', 'lf_sitemap_sync_schedule_cron');

/**
 * Shared runner for cron + manual sync.
 *
 * @return array{ok:bool,reconcile:array<string,mixed>,menu:array<string,mixed>}
 */
function lf_sitemap_sync_run_all(string $mode = 'manual'): array {
	$reconcile = function_exists('lf_sitemap_sync_reconcile_run') ? lf_sitemap_sync_reconcile_run() : ['ok' => false, 'errors' => ['missing_reconcile']];
	$menu = function_exists('lf_sitemap_sync_build_header_menu') ? lf_sitemap_sync_build_header_menu() : ['ok' => false, 'error' => 'missing_menu_builder'];
	$ok = !empty($reconcile['ok']) && !empty($menu['ok']);
	lf_sitemap_sync_store_last_result($mode, $ok, is_array($reconcile) ? $reconcile : [], is_array($menu) ? $menu : []);
	return ['ok' => $ok, 'reconcile' => (array) $reconcile, 'menu' => (array) $menu];
}

function lf_sitemap_sync_cron_handler(): void {
	lf_sitemap_sync_run_all('cron');
}
add_action('lf_sitemap_sync_cron', 'lf_sitemap_sync_cron_handler');

function lf_sitemap_sync_admin_register_menu(): void {
	if (!defined('LF_OPS_CAP')) {
		return;
	}
	add_submenu_page(
		'lf-ops',
		__('Sitemap Sync', 'leadsforward-core'),
		__('Sitemap Sync', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-sitemap-sync',
		'lf_sitemap_sync_admin_render_page'
	);
}
add_action('admin_menu', 'lf_sitemap_sync_admin_register_menu', 60);

function lf_sitemap_sync_admin_handle_sync_now(): void {
	if (!defined('LF_OPS_CAP') || !current_user_can(LF_OPS_CAP)) {
		wp_die(esc_html__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_sitemap_sync_now');
	lf_sitemap_sync_run_all('manual');
	wp_safe_redirect(admin_url('admin.php?page=lf-sitemap-sync&ran=1'));
	exit;
}
add_action('admin_post_lf_sitemap_sync_now', 'lf_sitemap_sync_admin_handle_sync_now');

function lf_sitemap_sync_admin_render_page(): void {
	if (!defined('LF_OPS_CAP') || !current_user_can(LF_OPS_CAP)) {
		return;
	}
	$last = lf_sitemap_sync_last_result();
	$reconcile = (array) ($last['reconcile'] ?? []);
	$menu = (array) ($last['menu'] ?? []);
	$errors = is_array($reconcile['errors'] ?? null) ? (array) $reconcile['errors'] : [];

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Sitemap Sync', 'leadsforward-core') . '</h1>';
	echo '<p class="description">' . esc_html__('Syncs Airtable Sitemaps into WordPress pages, then builds the Header Menu from the sitemap structure.', 'leadsforward-core') . '</p>';

	$sync_url = wp_nonce_url(admin_url('admin-post.php?action=lf_sitemap_sync_now'), 'lf_sitemap_sync_now');
	echo '<p><a class="button button-primary" href="' . esc_url($sync_url) . '">' . esc_html__('Sync now', 'leadsforward-core') . '</a></p>';

	$ran_at = (int) ($last['ran_at'] ?? 0);
	echo '<div class="card" style="max-width:980px;">';
	echo '<h2 style="margin-top:0;">' . esc_html__('Last run', 'leadsforward-core') . '</h2>';
	echo '<p><strong>' . esc_html__('Status:', 'leadsforward-core') . '</strong> ' . esc_html(!empty($last['ok']) ? __('OK', 'leadsforward-core') : __('Needs attention', 'leadsforward-core')) . '</p>';
	echo '<p><strong>' . esc_html__('Mode:', 'leadsforward-core') . '</strong> ' . esc_html((string) ($last['mode'] ?? '')) . '</p>';
	echo '<p><strong>' . esc_html__('Ran at:', 'leadsforward-core') . '</strong> ' . esc_html($ran_at > 0 ? gmdate('Y-m-d H:i:s', $ran_at) . ' UTC' : __('Never', 'leadsforward-core')) . '</p>';

	echo '<h3>' . esc_html__('Reconcile summary', 'leadsforward-core') . '</h3>';
	echo '<ul style="margin-left:1.2rem;">';
	echo '<li><strong>' . esc_html__('Site niche:', 'leadsforward-core') . '</strong> ' . esc_html((string) ($reconcile['niche'] ?? '')) . '</li>';
	echo '<li><strong>' . esc_html__('Primary city:', 'leadsforward-core') . '</strong> ' . esc_html((string) ($reconcile['city'] ?? '')) . '</li>';
	echo '<li><strong>' . esc_html__('Fetched rows:', 'leadsforward-core') . '</strong> ' . esc_html((string) ((int) ($reconcile['fetched_rows'] ?? 0))) . '</li>';
	echo '<li><strong>' . esc_html__('Normalized:', 'leadsforward-core') . '</strong> ' . esc_html((string) ((int) ($reconcile['normalized'] ?? 0))) . '</li>';
	echo '<li><strong>' . esc_html__('Invalid:', 'leadsforward-core') . '</strong> ' . esc_html((string) ((int) ($reconcile['invalid'] ?? 0))) . '</li>';
	echo '<li><strong>' . esc_html__('Created:', 'leadsforward-core') . '</strong> ' . esc_html((string) ((int) ($reconcile['created'] ?? 0))) . '</li>';
	echo '<li><strong>' . esc_html__('Updated:', 'leadsforward-core') . '</strong> ' . esc_html((string) ((int) ($reconcile['updated'] ?? 0))) . '</li>';
	echo '<li><strong>' . esc_html__('Index count:', 'leadsforward-core') . '</strong> ' . esc_html((string) ((int) ($reconcile['index_count'] ?? 0))) . '</li>';
	echo '</ul>';

	echo '<h3>' . esc_html__('Header menu summary', 'leadsforward-core') . '</h3>';
	echo '<ul style="margin-left:1.2rem;">';
	echo '<li><strong>' . esc_html__('Enabled:', 'leadsforward-core') . '</strong> ' . esc_html(!empty($menu['enabled']) ? __('Yes', 'leadsforward-core') : __('No', 'leadsforward-core')) . '</li>';
	echo '<li><strong>' . esc_html__('Used specs:', 'leadsforward-core') . '</strong> ' . esc_html((string) ((int) ($menu['used_specs'] ?? 0))) . '</li>';
	echo '<li><strong>' . esc_html__('Added items:', 'leadsforward-core') . '</strong> ' . esc_html((string) ((int) ($menu['added_items'] ?? 0))) . '</li>';
	echo '<li><strong>' . esc_html__('Preserved items:', 'leadsforward-core') . '</strong> ' . esc_html((string) ((int) ($menu['preserved_items'] ?? 0))) . '</li>';
	echo '</ul>';

	if (!empty($errors)) {
		echo '<h3>' . esc_html__('Errors (sample)', 'leadsforward-core') . '</h3>';
		echo '<ul style="margin-left:1.2rem;">';
		foreach (array_slice($errors, 0, 12) as $err) {
			echo '<li><code>' . esc_html((string) $err) . '</code></li>';
		}
		echo '</ul>';
	}
	echo '</div>';
	echo '</div>';
}

