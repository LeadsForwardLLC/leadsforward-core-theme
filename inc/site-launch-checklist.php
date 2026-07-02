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
			lf_launch_checklist_business_email(),
			lf_launch_checklist_wrap_health(lf_health_check_variation_profile()),
			lf_launch_checklist_logo(),
			lf_launch_checklist_header_cta(),
			lf_launch_checklist_static_front_page(),
			lf_launch_checklist_wrap_health(lf_health_check_wizard_complete()),
		],
		__('SEO & schema', 'leadsforward-core') => [
			lf_launch_checklist_wrap_health(lf_health_check_nap_complete()),
			lf_launch_checklist_wrap_health(lf_health_check_core_pages_exist()),
			lf_launch_checklist_schema(),
			lf_launch_checklist_search_engine_visibility(),
			lf_launch_checklist_wrap_health(lf_health_check_canonicals()),
			lf_launch_checklist_xml_sitemap(),
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
 * Strip Fix links on green rows; normalize fix URLs for launch context.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function lf_launch_checklist_finalize_row(array $row): array {
	$status = (string) ($row['status'] ?? lf_health_status_warning());
	if ($status === lf_health_status_pass()) {
		$row['fix_link'] = '';
	}

	return $row;
}

/**
 * @param array<string, mixed> $check
 * @return array<string, mixed>
 */
function lf_launch_checklist_wrap_health(array $check): array {
	$row = [
		'id' => sanitize_key((string) ($check['label'] ?? 'check')),
		'label' => (string) ($check['label'] ?? ''),
		'description' => (string) ($check['message'] ?? ''),
		'status' => (string) ($check['status'] ?? lf_health_status_warning()),
		'fix_link' => (string) ($check['fix_link'] ?? ''),
		'auto' => true,
	];

	return lf_launch_checklist_finalize_row($row);
}

function lf_launch_checklist_logo(): array {
	$logo_id = function_exists('lf_get_global_option') ? (int) lf_get_global_option('lf_global_logo', 0) : 0;
	$pass = $logo_id > 0 && wp_get_attachment_url($logo_id);

	return lf_launch_checklist_finalize_row([
		'id' => 'global-logo',
		'label' => __('Site logo uploaded', 'leadsforward-core'),
		'description' => $pass
			? __('Logo is set in Global Settings.', 'leadsforward-core')
			: __('Upload a logo under Global Settings.', 'leadsforward-core'),
		'status' => $pass ? lf_health_status_pass() : lf_health_status_fail(),
		'fix_link' => admin_url('admin.php?page=lf-ops'),
		'auto' => true,
	]);
}

function lf_launch_checklist_business_email(): array {
	$stored = function_exists('lf_get_business_info_value')
		? trim((string) lf_get_business_info_value('lf_business_email', ''))
		: '';
	if ($stored === '' && function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$stored = trim((string) ($entity['email_stored'] ?? ''));
	}
	$pass = $stored !== '' && is_email($stored) && !str_ends_with(strtolower($stored), '@example.com');

	return lf_launch_checklist_finalize_row([
		'id' => 'business-email',
		'label' => __('Business email', 'leadsforward-core'),
		'description' => $pass
			? $stored
			: __('Set a public contact email in Global Settings (synced from Airtable Domain Email on import).', 'leadsforward-core'),
		'status' => $pass ? lf_health_status_pass() : lf_health_status_warning(),
		'fix_link' => admin_url('admin.php?page=lf-ops'),
		'auto' => true,
	]);
}

function lf_launch_checklist_header_cta(): array {
	$label = function_exists('lf_get_global_option') ? trim((string) lf_get_global_option('lf_header_cta_label', '')) : '';
	$cta_text = function_exists('lf_get_option') ? trim((string) lf_get_option('lf_cta_primary_text', 'option')) : '';
	$pass = $label !== '' || $cta_text !== '';

	return lf_launch_checklist_finalize_row([
		'id' => 'header-cta',
		'label' => __('Header CTA configured', 'leadsforward-core'),
		'description' => $pass
			? __('Free Estimate / primary CTA label is set.', 'leadsforward-core')
			: __('Set the header CTA label in Global Settings or CTAs.', 'leadsforward-core'),
		'status' => $pass ? lf_health_status_pass() : lf_health_status_warning(),
		'fix_link' => admin_url('admin.php?page=lf-ops'),
		'auto' => true,
	]);
}

function lf_launch_checklist_static_front_page(): array {
	$front_id = (int) get_option('page_on_front');
	$pass = $front_id > 0 && get_post_status($front_id) === 'publish';

	return lf_launch_checklist_finalize_row([
		'id' => 'static-front-page',
		'label' => __('Homepage assigned', 'leadsforward-core'),
		'description' => $pass
			? get_the_title($front_id)
			: __('Set a static homepage under Settings → Reading.', 'leadsforward-core'),
		'status' => $pass ? lf_health_status_pass() : lf_health_status_warning(),
		'fix_link' => admin_url('options-reading.php'),
		'auto' => true,
	]);
}

function lf_launch_checklist_schema(): array {
	$on = 0;
	if (function_exists('lf_seo_get_settings')) {
		$settings = lf_seo_get_settings();
		if (!empty($settings['schema']['enable_local_business'])) {
			++$on;
		}
		if (!empty($settings['schema']['enable_service'])) {
			++$on;
		}
	}
	foreach (['lf_schema_local_business', 'lf_schema_organization'] as $key) {
		$v = function_exists('get_field') ? get_field($key, 'option') : null;
		if ($v === true || $v === '1' || $v === 1) {
			++$on;
		}
	}
	$fix = function_exists('lf_health_schema_settings_url')
		? lf_health_schema_settings_url()
		: admin_url('admin.php?page=lf-seo&tab=settings#schema');

	return lf_launch_checklist_finalize_row([
		'id' => 'required-schema',
		'label' => __('Required schema', 'leadsforward-core'),
		'description' => $on > 0
			? __('LocalBusiness and/or Service schema is enabled.', 'leadsforward-core')
			: __('Turn on LocalBusiness or Service schema under SEO & Performance → SEO settings → Schema.', 'leadsforward-core'),
		'status' => $on > 0 ? lf_health_status_pass() : lf_health_status_warning(),
		'fix_link' => $fix,
		'auto' => true,
	]);
}

function lf_launch_checklist_search_engine_visibility(): array {
	$blog_public = (string) get_option('blog_public', '1');
	if ($blog_public === '0') {
		return lf_launch_checklist_finalize_row([
			'id' => 'search-engine-visibility',
			'label' => __('Search engine visibility', 'leadsforward-core'),
			'description' => __('“Discourage search engines from indexing” is still on. Disable it before launch and submit your sitemap in Google Search Console.', 'leadsforward-core'),
			'status' => lf_health_status_fail(),
			'fix_link' => lf_health_reading_settings_url(),
			'auto' => true,
		]);
	}

	return lf_launch_checklist_finalize_row([
		'id' => 'search-engine-visibility',
		'label' => __('Search engine visibility', 'leadsforward-core'),
		'description' => __('Indexing is allowed at the WordPress level.', 'leadsforward-core'),
		'status' => lf_health_status_pass(),
		'fix_link' => '',
		'auto' => true,
	]);
}

function lf_launch_checklist_xml_sitemap(): array {
	$enabled = false;
	if (function_exists('lf_seo_get_settings')) {
		$settings = lf_seo_get_settings();
		$enabled = !empty($settings['sitemap']['enable']);
	}
	$fix = admin_url('admin.php?page=lf-seo&tab=settings#sitemap');

	return lf_launch_checklist_finalize_row([
		'id' => 'xml-sitemap',
		'label' => __('XML sitemap enabled', 'leadsforward-core'),
		'description' => $enabled
			? __('Theme sitemap.xml is enabled.', 'leadsforward-core')
			: __('Enable the theme XML sitemap before launch (SEO settings → Sitemap).', 'leadsforward-core'),
		'status' => $enabled ? lf_health_status_pass() : lf_health_status_warning(),
		'fix_link' => $fix,
		'auto' => true,
	]);
}

function lf_launch_checklist_n8n_page_webhook(): array {
	$url = function_exists('lf_n8n_page_events_webhook_url')
		? trim((string) lf_n8n_page_events_webhook_url())
		: trim((string) get_option('lf_n8n_page_events_webhook', ''));
	$pass = $url !== '';

	return lf_launch_checklist_finalize_row([
		'id' => 'n8n-page-webhook',
		'label' => __('Page publish webhook (n8n images)', 'leadsforward-core'),
		'description' => $pass
			? __('Webhook URL is configured for page publish events.', 'leadsforward-core')
			: __('Set Page publish webhook in Global Settings when using n8n image placement.', 'leadsforward-core'),
		'status' => $pass ? lf_health_status_pass() : lf_health_status_warning(),
		'fix_link' => admin_url('admin.php?page=lf-ops'),
		'auto' => true,
	]);
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
	$row = lf_launch_checklist_finalize_row($row);
	$status = (string) ($row['status'] ?? lf_health_status_warning());
	$color = $status === lf_health_status_pass() ? '#00a32a' : ($status === lf_health_status_fail() ? '#d63638' : '#dba617');
	$fix_link = (string) ($row['fix_link'] ?? '');

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
	if ($fix_link !== '' && $status !== lf_health_status_pass()) {
		echo ' <a href="' . esc_url($fix_link) . '">' . esc_html__('Fix', 'leadsforward-core') . '</a>';
	}
	echo '</td></tr>';
}
