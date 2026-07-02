<?php
/**
 * Dynamic site launch checklist — auto-detected global readiness + manual QA toggles.
 * Resets on dev site reset; lives in wp-admin (not a static .md file).
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_LAUNCH_CHECKLIST_MANUAL_OPTION = 'lf_launch_checklist_manual_done';

add_action('admin_menu', 'lf_launch_checklist_register_menu', 11);
add_action('admin_init', 'lf_launch_checklist_handle_save');

/**
 * Manual QA rows (team checks off in admin; cleared on site reset).
 *
 * @return array<string, array{group:string,label:string,description:string}>
 */
function lf_launch_checklist_manual_items(): array {
	$items = [
		'contact-form-test' => [
			'group' => __('Launch QA', 'leadsforward-core'),
			'label' => __('Contact form delivers to the right inbox', 'leadsforward-core'),
			'description' => __('Submit a test lead from Contact and confirm receipt.', 'leadsforward-core'),
		],
		'mobile-nav' => [
			'group' => __('Launch QA', 'leadsforward-core'),
			'label' => __('Mobile header + More menu work', 'leadsforward-core'),
			'description' => __('Tap targets, Services mega menu, and More dropdown on a phone.', 'leadsforward-core'),
		],
		'homepage-cta' => [
			'group' => __('Launch QA', 'leadsforward-core'),
			'label' => __('Homepage hero CTA works', 'leadsforward-core'),
			'description' => __('Quote modal, phone link, or configured primary action.', 'leadsforward-core'),
		],
		'meta-unique' => [
			'group' => __('Launch QA', 'leadsforward-core'),
			'label' => __('Money pages have unique meta title + description', 'leadsforward-core'),
			'description' => __('Services, service areas, and core landing pages in the SEO meta box.', 'leadsforward-core'),
		],
		'analytics-fires' => [
			'group' => __('Launch QA', 'leadsforward-core'),
			'label' => __('Analytics / GTM fires on key templates', 'leadsforward-core'),
			'description' => __('Tag Assistant or network filter on home, contact, and a service page.', 'leadsforward-core'),
		],
		'staging-domain' => [
			'group' => __('Launch QA', 'leadsforward-core'),
			'label' => __('Production domain in schema, canonicals, and Search Console', 'leadsforward-core'),
			'description' => __('No staging URLs left in structured data or redirects.', 'leadsforward-core'),
		],
	];

	return (array) apply_filters('lf_launch_checklist_manual_items', $items);
}

/**
 * @return array<string, true>
 */
function lf_launch_checklist_manual_done_map(): array {
	$raw = get_option(LF_LAUNCH_CHECKLIST_MANUAL_OPTION, []);
	if (!is_array($raw)) {
		return [];
	}
	$out = [];
	foreach ($raw as $id => $val) {
		if ($val) {
			$out[sanitize_key((string) $id)] = true;
		}
	}

	return $out;
}

function lf_launch_checklist_reset_manual(): void {
	delete_option(LF_LAUNCH_CHECKLIST_MANUAL_OPTION);
}

/**
 * Auto checklist rows grouped for admin UI.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function lf_launch_checklist_auto_groups(): array {
	$groups = [
		__('Global setup', 'leadsforward-core') => [
			lf_launch_checklist_wrap_health(lf_health_check_business_info()),
			lf_launch_checklist_wrap_health(lf_health_check_variation_profile()),
			lf_launch_checklist_logo(),
			lf_launch_checklist_header_cta(),
			lf_launch_checklist_wizard(),
		],
		__('Navigation', 'leadsforward-core') => [
			lf_launch_checklist_header_fleet_nav(),
			lf_launch_checklist_more_dropdown(),
			lf_launch_checklist_wrap_health(lf_health_check_footer_links()),
		],
		__('SEO & schema', 'leadsforward-core') => [
			lf_launch_checklist_wrap_health(lf_health_check_nap_complete()),
			lf_launch_checklist_wrap_health(lf_health_check_core_pages_exist()),
			lf_launch_checklist_wrap_health(lf_health_check_schema_present()),
			lf_launch_checklist_wrap_health(lf_health_check_search_engine_visibility()),
			lf_launch_checklist_wrap_health(lf_health_check_canonicals()),
		],
		__('Content', 'leadsforward-core') => [
			lf_launch_checklist_wrap_health(lf_health_check_service_pages_exist()),
			lf_launch_checklist_wrap_health(lf_health_check_service_area_pages_exist()),
			lf_launch_checklist_wrap_health(lf_health_check_thin_pages()),
		],
		__('Integrations', 'leadsforward-core') => [
			lf_launch_checklist_wrap_health(lf_health_check_manifester_config()),
			lf_launch_checklist_n8n_page_webhook(),
			lf_launch_checklist_wrap_health(lf_health_check_header_analytics()),
		],
	];

	return (array) apply_filters('lf_launch_checklist_auto_groups', $groups);
}

/**
 * @param array<string, mixed> $check
 * @return array<string, mixed>
 */
function lf_launch_checklist_wrap_health(array $check): array {
	return [
		'id' => sanitize_key((string) ($check['label'] ?? 'check')),
		'label' => (string) ($check['label'] ?? ''),
		'description' => (string) ($check['message'] ?? ''),
		'status' => (string) ($check['status'] ?? lf_health_status_warning()),
		'fix_link' => (string) ($check['fix_link'] ?? ''),
		'auto' => true,
	];
}

function lf_launch_checklist_logo(): array {
	$logo_id = function_exists('lf_get_global_option') ? (int) lf_get_global_option('lf_global_logo', 0) : 0;
	$pass = $logo_id > 0 && wp_get_attachment_url($logo_id);
	return [
		'id' => 'global-logo',
		'label' => __('Site logo uploaded', 'leadsforward-core'),
		'description' => $pass
			? __('Logo is set in Global Settings.', 'leadsforward-core')
			: __('Upload a logo under Global Settings → Branding.', 'leadsforward-core'),
		'status' => $pass ? lf_health_status_pass() : lf_health_status_fail(),
		'fix_link' => admin_url('admin.php?page=lf-ops'),
		'auto' => true,
	];
}

function lf_launch_checklist_header_cta(): array {
	$label = function_exists('lf_get_global_option') ? trim((string) lf_get_global_option('lf_header_cta_label', '')) : '';
	$cta_text = function_exists('lf_get_option') ? trim((string) lf_get_option('lf_cta_primary_text', 'option')) : '';
	$pass = $label !== '' || $cta_text !== '';
	return [
		'id' => 'header-cta',
		'label' => __('Header CTA configured', 'leadsforward-core'),
		'description' => $pass
			? __('Free Estimate / primary CTA label is set.', 'leadsforward-core')
			: __('Set the header CTA label in Global Settings or CTAs.', 'leadsforward-core'),
		'status' => $pass ? lf_health_status_pass() : lf_health_status_warning(),
		'fix_link' => admin_url('admin.php?page=lf-ops'),
		'auto' => true,
	];
}

function lf_launch_checklist_wizard(): array {
	if (function_exists('lf_health_check_wizard_complete')) {
		return lf_launch_checklist_wrap_health(lf_health_check_wizard_complete());
	}
	return [
		'id' => 'wizard-complete',
		'label' => __('Initial setup', 'leadsforward-core'),
		'description' => '',
		'status' => lf_health_status_warning(),
		'fix_link' => admin_url('admin.php?page=lf-setup'),
		'auto' => true,
	];
}

function lf_launch_checklist_header_fleet_nav(): array {
	$violations = lf_launch_checklist_header_fleet_violations();
	if ($violations === []) {
		return [
			'id' => 'header-fleet-nav',
			'label' => __('Header fleet navigation', 'leadsforward-core'),
			'description' => __('Top level is Home → Services → Service Areas → About → Call → CTA → More only.', 'leadsforward-core'),
			'status' => lf_health_status_pass(),
			'fix_link' => admin_url('nav-menus.php'),
			'auto' => true,
		];
	}

	return [
		'id' => 'header-fleet-nav',
		'label' => __('Header fleet navigation', 'leadsforward-core'),
		'description' => sprintf(
			/* translators: %s: comma-separated menu labels */
			__('These items must move under More: %s', 'leadsforward-core'),
			implode(', ', $violations)
		),
		'status' => lf_health_status_fail(),
		'fix_link' => admin_url('nav-menus.php'),
		'auto' => true,
	];
}

/**
 * @return list<string>
 */
function lf_launch_checklist_header_fleet_violations(): array {
	if (!has_nav_menu('header_menu') || !function_exists('lf_header_menu_item_violates_fleet_top_level')) {
		return [__('Header Menu not assigned', 'leadsforward-core')];
	}
	$locations = get_nav_menu_locations();
	$menu_id = (int) ($locations['header_menu'] ?? 0);
	if ($menu_id <= 0) {
		return [__('Header Menu not assigned', 'leadsforward-core')];
	}
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items)) {
		return [];
	}
	$labels = [];
	foreach ($items as $item) {
		if ($item instanceof \WP_Post && lf_header_menu_item_violates_fleet_top_level($item)) {
			$labels[] = trim(wp_strip_all_tags((string) ($item->title ?? __('(untitled)', 'leadsforward-core'))));
		}
	}

	return array_values(array_unique($labels));
}

function lf_launch_checklist_more_dropdown(): array {
	if (!has_nav_menu('header_menu')) {
		return [
			'id' => 'header-more-dropdown',
			'label' => __('More dropdown', 'leadsforward-core'),
			'description' => __('Assign Header Menu under Appearance → Menus.', 'leadsforward-core'),
			'status' => lf_health_status_fail(),
			'fix_link' => admin_url('nav-menus.php?action=locations'),
			'auto' => true,
		];
	}
	if (!function_exists('lf_header_menu_more_is_enabled') || !lf_header_menu_more_is_enabled()) {
		return [
			'id' => 'header-more-dropdown',
			'label' => __('More dropdown', 'leadsforward-core'),
			'description' => __('No secondary pages published yet — More appears when Contact, Blog, Reviews, etc. exist.', 'leadsforward-core'),
			'status' => lf_health_status_warning(),
			'fix_link' => '',
			'auto' => true,
		];
	}
	$locations = get_nav_menu_locations();
	$menu_id = (int) ($locations['header_menu'] ?? 0);
	$items = $menu_id > 0 ? wp_get_nav_menu_items($menu_id) : false;
	$more_id = is_array($items) && function_exists('lf_header_menu_find_more_parent_id')
		? lf_header_menu_find_more_parent_id($items)
		: 0;
	if ($more_id <= 0) {
		return [
			'id' => 'header-more-dropdown',
			'label' => __('More dropdown', 'leadsforward-core'),
			'description' => __('More parent is missing — Contact and other secondary links are spilling to the top bar.', 'leadsforward-core'),
			'status' => lf_health_status_fail(),
			'fix_link' => admin_url('nav-menus.php'),
			'auto' => true,
		];
	}
	$children = 0;
	if (is_array($items)) {
		foreach ($items as $item) {
			if ($item instanceof \WP_Post && (int) ($item->menu_item_parent ?? 0) === $more_id) {
				++$children;
			}
		}
	}
	if ($children === 0) {
		return [
			'id' => 'header-more-dropdown',
			'label' => __('More dropdown', 'leadsforward-core'),
			'description' => __('More menu exists but has no children.', 'leadsforward-core'),
			'status' => lf_health_status_fail(),
			'fix_link' => admin_url('nav-menus.php'),
			'auto' => true,
		];
	}

	return [
		'id' => 'header-more-dropdown',
		'label' => __('More dropdown', 'leadsforward-core'),
		'description' => sprintf(
			/* translators: %d: number of links under More */
			_n('%d link under More.', '%d links under More.', $children, 'leadsforward-core'),
			$children
		),
		'status' => lf_health_status_pass(),
		'fix_link' => admin_url('nav-menus.php'),
		'auto' => true,
	];
}

function lf_launch_checklist_n8n_page_webhook(): array {
	$url = function_exists('lf_n8n_page_events_webhook_url')
		? trim((string) lf_n8n_page_events_webhook_url())
		: trim((string) get_option('lf_n8n_page_events_webhook', ''));
	$pass = $url !== '';
	return [
		'id' => 'n8n-page-webhook',
		'label' => __('Page publish webhook (n8n images)', 'leadsforward-core'),
		'description' => $pass
			? __('Webhook URL is configured for page publish events.', 'leadsforward-core')
			: __('Set Page publish webhook in Global Settings when using n8n image placement.', 'leadsforward-core'),
		'status' => $pass ? lf_health_status_pass() : lf_health_status_warning(),
		'fix_link' => admin_url('admin.php?page=lf-ops'),
		'auto' => true,
	];
}

/**
 * @return array{auto_pass:int,auto_total:int,manual_done:int,manual_total:int,percent:int}
 */
function lf_launch_checklist_progress(): array {
	$auto_pass = 0;
	$auto_total = 0;
	foreach (lf_launch_checklist_auto_groups() as $rows) {
		foreach ($rows as $row) {
			++$auto_total;
			if (($row['status'] ?? '') === lf_health_status_pass()) {
				++$auto_pass;
			}
		}
	}
	$manual = lf_launch_checklist_manual_items();
	$manual_done_map = lf_launch_checklist_manual_done_map();
	$manual_done = 0;
	foreach (array_keys($manual) as $id) {
		if (isset($manual_done_map[$id])) {
			++$manual_done;
		}
	}
	$manual_total = count($manual);
	$total = $auto_total + $manual_total;
	$done = $auto_pass + $manual_done;
	$percent = $total > 0 ? (int) round(($done / $total) * 100) : 0;

	return [
		'auto_pass' => $auto_pass,
		'auto_total' => $auto_total,
		'manual_done' => $manual_done,
		'manual_total' => $manual_total,
		'percent' => min(100, max(0, $percent)),
	];
}

function lf_launch_checklist_register_menu(): void {
	add_submenu_page(
		'lf-ops',
		__('Site Launch Checklist', 'leadsforward-core'),
		__('Launch Checklist', 'leadsforward-core'),
		LF_OPS_CAP,
		'lf-launch-checklist',
		'lf_launch_checklist_render_page'
	);
}

function lf_launch_checklist_handle_save(): void {
	if (!isset($_POST['lf_launch_checklist_save']) || !current_user_can(LF_OPS_CAP)) {
		return;
	}
	check_admin_referer('lf_launch_checklist_save', 'lf_launch_checklist_nonce');
	$allowed = array_keys(lf_launch_checklist_manual_items());
	$done = [];
	if (isset($_POST['lf_launch_manual']) && is_array($_POST['lf_launch_manual'])) {
		foreach ($_POST['lf_launch_manual'] as $id => $val) {
			$key = sanitize_key((string) $id);
			if (in_array($key, $allowed, true) && (string) $val === '1') {
				$done[$key] = true;
			}
		}
	}
	update_option(LF_LAUNCH_CHECKLIST_MANUAL_OPTION, $done, false);
	wp_safe_redirect(admin_url('admin.php?page=lf-launch-checklist&saved=1'));
	exit;
}

function lf_launch_checklist_render_page(): void {
	if (!current_user_can(LF_OPS_CAP)) {
		return;
	}
	$progress = lf_launch_checklist_progress();
	$manual_done = lf_launch_checklist_manual_done_map();
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Site Launch Checklist', 'leadsforward-core') . '</h1>';
	if ($saved) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Checklist saved.', 'leadsforward-core') . '</p></div>';
	}
	echo '<p class="description">' . esc_html__(
		'Global readiness for go-live. Auto rows update from site data; manual rows reset when you run Site Reset.',
		'leadsforward-core'
	) . '</p>';

	echo '<div style="max-width:720px;margin:16px 0 24px;padding:16px 20px;background:#f0f6fc;border:1px solid #c3d9ed;border-radius:10px;">';
	echo '<strong>' . esc_html__('Progress', 'leadsforward-core') . '</strong>: ';
	echo esc_html((string) $progress['percent']) . '% — ';
	echo esc_html(sprintf(
		/* translators: 1: auto pass count, 2: auto total, 3: manual done, 4: manual total */
		__('%1$d / %2$d automated checks passing; %3$d / %4$d manual items done.', 'leadsforward-core'),
		$progress['auto_pass'],
		$progress['auto_total'],
		$progress['manual_done'],
		$progress['manual_total']
	));
	echo '<div style="margin-top:10px;height:10px;background:#fff;border-radius:6px;overflow:hidden;">';
	echo '<div style="width:' . esc_attr((string) $progress['percent']) . '%;height:100%;background:#2271b1;"></div>';
	echo '</div></div>';

	foreach (lf_launch_checklist_auto_groups() as $group => $rows) {
		echo '<h2>' . esc_html((string) $group) . '</h2>';
		echo '<table class="widefat striped" style="max-width:920px;margin-bottom:24px;"><tbody>';
		foreach ($rows as $row) {
			lf_launch_checklist_render_row($row, false, false);
		}
		echo '</tbody></table>';
	}

	echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=lf-launch-checklist')) . '">';
	wp_nonce_field('lf_launch_checklist_save', 'lf_launch_checklist_nonce');
	echo '<h2>' . esc_html__('Launch QA (manual)', 'leadsforward-core') . '</h2>';
	echo '<table class="widefat striped" style="max-width:920px;"><tbody>';
	foreach (lf_launch_checklist_manual_items() as $id => $item) {
		lf_launch_checklist_render_row([
			'id' => $id,
			'label' => $item['label'],
			'description' => $item['description'],
			'status' => isset($manual_done[$id]) ? lf_health_status_pass() : lf_health_status_warning(),
			'fix_link' => '',
			'auto' => false,
		], true, isset($manual_done[$id]));
	}
	echo '</tbody></table>';
	echo '<p><button type="submit" name="lf_launch_checklist_save" class="button button-primary" value="1">';
	echo esc_html__('Save manual checklist', 'leadsforward-core') . '</button></p>';
	echo '</form>';

	echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=lf-seo&tab=health')) . '">';
	echo esc_html__('Run full pre-launch health scan', 'leadsforward-core') . '</a></p>';
	echo '</div>';
}

/**
 * @param array<string, mixed> $row
 */
function lf_launch_checklist_render_row(array $row, bool $manual_field, bool $checked): void {
	$status = (string) ($row['status'] ?? lf_health_status_warning());
	$color = $status === lf_health_status_pass() ? '#00a32a' : ($status === lf_health_status_fail() ? '#d63638' : '#dba617');
	echo '<tr>';
	echo '<td style="width:36px;vertical-align:top;padding-top:14px;">';
	if ($manual_field) {
		$id = esc_attr((string) ($row['id'] ?? ''));
		echo '<input type="checkbox" name="lf_launch_manual[' . $id . ']" value="1"' . checked($checked, true, false) . ' />';
	} else {
		echo '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:' . esc_attr($color) . ';" aria-hidden="true"></span>';
	}
	echo '</td>';
	echo '<td><strong>' . esc_html((string) ($row['label'] ?? '')) . '</strong>';
	if (!empty($row['description'])) {
		echo '<br><span class="description">' . esc_html((string) $row['description']) . '</span>';
	}
	if (!empty($row['fix_link'])) {
		echo ' <a href="' . esc_url((string) $row['fix_link']) . '">' . esc_html__('Fix', 'leadsforward-core') . '</a>';
	}
	echo '</td></tr>';
}
