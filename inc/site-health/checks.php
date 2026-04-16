<?php
/**
 * Site health checks: dashboard, SEO, performance, internal links. Flag only; no auto-fix.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Required plugin slugs (theme expects these). Filter lf_health_required_plugins to change.
 */
function lf_health_required_plugins(): array {
	return apply_filters('lf_health_required_plugins', ['advanced-custom-fields/acf.php', 'advanced-custom-fields-pro/acf.php']);
}

/**
 * URL for an ACF options submenu; falls back to Global Settings if ACF is inactive.
 */
function lf_health_fix_url_acf_option_page(string $page_slug): string {
	if (function_exists('get_field')) {
		return admin_url('admin.php?page=' . sanitize_key($page_slug));
	}
	return admin_url('admin.php?page=lf-global');
}

function lf_health_seo_settings_url(): string {
	return admin_url('admin.php?page=lf-seo&tab=settings');
}

function lf_health_check_theme_active(): array {
	$theme = wp_get_theme();
	$name = $theme->get('Name');
	$is_lf = stripos($name, 'LeadsForward') !== false;
	if (!$is_lf) {
		return ['status' => lf_health_status_fail(), 'label' => __('Theme active', 'leadsforward-core'), 'message' => __('LeadsForward theme is not active.', 'leadsforward-core'), 'fix_link' => admin_url('themes.php')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Theme active', 'leadsforward-core'), 'message' => $name, 'fix_link' => ''];
}

function lf_health_check_required_plugins(): array {
	$required = lf_health_required_plugins();
	$all = get_option('active_plugins', []);
	$active = [];
	foreach ($required as $slug) {
		if (in_array($slug, $all, true)) {
			$active[] = $slug;
		}
	}
	// At least one of the required (e.g. ACF or ACF Pro) must be active.
	if (empty($active)) {
		return ['status' => lf_health_status_fail(), 'label' => __('Required plugins', 'leadsforward-core'), 'message' => __('Advanced Custom Fields (ACF) is required. Install and activate.', 'leadsforward-core'), 'fix_link' => admin_url('plugins.php')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Required plugins', 'leadsforward-core'), 'message' => __('All required plugins active.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_wizard_complete(): array {
	$done = (bool) get_option('lf_setup_wizard_complete', false);
	if ($done) {
		return ['status' => lf_health_status_pass(), 'label' => __('Initial setup', 'leadsforward-core'), 'message' => __('Manual setup wizard marked complete.', 'leadsforward-core'), 'fix_link' => ''];
	}
	$slugs = function_exists('lf_wizard_required_page_slugs') ? lf_wizard_required_page_slugs() : [];
	$missing = 0;
	foreach ($slugs as $slug) {
		$page = get_page_by_path((string) $slug);
		if (!$page instanceof \WP_Post) {
			$missing++;
		}
	}
	$fix = admin_url('admin.php?page=lf-setup');
	if ($missing === 0 && !empty($slugs)) {
		return [
			'status' => lf_health_status_warning(),
			'label' => __('Initial setup', 'leadsforward-core'),
			'message' => __('The manual setup wizard is not marked complete, but core pages from the checklist exist. Open Manual setup to confirm or finish the flow. If you use Airtable, Manifest Website may have created pages without running the wizard.', 'leadsforward-core'),
			'fix_link' => $fix,
		];
	}
	return [
		'status' => lf_health_status_fail(),
		'label' => __('Initial setup', 'leadsforward-core'),
		'message' => __('Core pages are missing or the manual setup wizard was not completed. Use Manifest Website (Airtable recommended) or Manual setup (no Airtable).', 'leadsforward-core'),
		'fix_link' => $fix,
	];
}

function lf_health_check_variation_profile(): array {
	$profile = function_exists('get_field') ? get_field('variation_profile', 'option') : null;
	$fix = lf_health_fix_url_acf_option_page('lf-variation');
	if ($profile === null || $profile === '') {
		return ['status' => lf_health_status_warning(), 'label' => __('Variation profile', 'leadsforward-core'), 'message' => __('Not set. A default profile is used until you choose one under Variation (or Global / design preset).', 'leadsforward-core'), 'fix_link' => $fix];
	}
	if (!in_array($profile, ['a', 'b', 'c', 'd', 'e'], true)) {
		return ['status' => lf_health_status_warning(), 'label' => __('Variation profile', 'leadsforward-core'), 'message' => __('Invalid value.', 'leadsforward-core'), 'fix_link' => $fix];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Variation profile', 'leadsforward-core'), 'message' => (string) $profile, 'fix_link' => ''];
}

function lf_health_check_business_info(): array {
	$nap = function_exists('lf_nap_data') ? lf_nap_data() : ['name' => '', 'phone' => ''];
	$name = $nap['name'] ?? '';
	$phone = $nap['phone'] ?? '';
	$missing = [];
	if (empty(trim((string) $name))) {
		$missing[] = __('Business name', 'leadsforward-core');
	}
	if (empty(trim((string) $phone))) {
		$missing[] = __('Phone', 'leadsforward-core');
	}
	if (!empty($missing)) {
		return ['status' => lf_health_status_fail(), 'label' => __('Global business info', 'leadsforward-core'), 'message' => __('Missing: ', 'leadsforward-core') . implode(', ', $missing), 'fix_link' => admin_url('admin.php?page=lf-global')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Global business info', 'leadsforward-core'), 'message' => __('Name and phone set.', 'leadsforward-core'), 'fix_link' => ''];
}

// --- SEO integrity ---

function lf_health_check_nap_complete(): array {
	$nap = function_exists('lf_nap_data') ? lf_nap_data() : ['name' => '', 'address' => '', 'phone' => '', 'email' => ''];
	$missing = [];
	if (empty(trim((string) ($nap['name'] ?? '')))) {
		$missing[] = __('Name', 'leadsforward-core');
	}
	if (empty(trim((string) ($nap['phone'] ?? '')))) {
		$missing[] = __('Phone', 'leadsforward-core');
	}
	if (!empty($missing)) {
		return ['status' => lf_health_status_fail(), 'label' => __('NAP complete', 'leadsforward-core'), 'message' => __('Missing: ', 'leadsforward-core') . implode(', ', $missing), 'fix_link' => admin_url('admin.php?page=lf-global')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('NAP complete', 'leadsforward-core'), 'message' => __('Name, address, phone present.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_core_pages_exist(): array {
	$required = function_exists('lf_wizard_required_page_slugs') ? lf_wizard_required_page_slugs() : [
		'home',
		'about-us',
		'services',
		'service-areas',
		'reviews',
		'blog',
		'sitemap',
		'contact',
		'privacy-policy',
		'terms-of-service',
		'thank-you',
	];
	$missing = [];
	foreach ($required as $slug) {
		$page = get_page_by_path($slug);
		if (!$page instanceof \WP_Post) {
			$missing[] = $slug;
		}
	}
	if (!empty($missing)) {
		return [
			'status' => lf_health_status_fail(),
			'label' => __('Core pages present', 'leadsforward-core'),
			'message' => __('Missing: ', 'leadsforward-core') . implode(', ', $missing),
			'fix_link' => admin_url('admin.php?page=lf-setup'),
		];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Core pages present', 'leadsforward-core'), 'message' => __('All required pages exist.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_service_pages_exist(): array {
	$services = get_posts(['post_type' => 'lf_service', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids']);
	if (empty($services)) {
		return ['status' => lf_health_status_warning(), 'label' => __('Service pages', 'leadsforward-core'), 'message' => __('No service pages found.', 'leadsforward-core'), 'fix_link' => admin_url('edit.php?post_type=lf_service')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Service pages', 'leadsforward-core'), 'message' => __('At least one service exists.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_service_area_pages_exist(): array {
	$areas = get_posts(['post_type' => 'lf_service_area', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids']);
	if (empty($areas)) {
		return ['status' => lf_health_status_warning(), 'label' => __('Service area pages', 'leadsforward-core'), 'message' => __('No service area pages found.', 'leadsforward-core'), 'fix_link' => admin_url('edit.php?post_type=lf_service_area')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Service area pages', 'leadsforward-core'), 'message' => __('At least one service area exists.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_thin_pages(): array {
	if (!function_exists('lf_pb_get_post_config')) {
		return ['status' => lf_health_status_warning(), 'label' => __('Thin pages', 'leadsforward-core'), 'message' => __('Page builder not available.', 'leadsforward-core'), 'fix_link' => ''];
	}
	$slugs = function_exists('lf_wizard_required_page_slugs') ? lf_wizard_required_page_slugs() : [];
	$thin = [];
	foreach ($slugs as $slug) {
		$page = get_page_by_path($slug);
		if (!$page instanceof \WP_Post) {
			continue;
		}
		$config = lf_pb_get_post_config($page->ID, 'page');
		$sections = $config['sections'] ?? [];
		$count = 0;
		foreach ($sections as $section) {
			if (!empty($section['enabled'])) {
				$count++;
			}
		}
		if ($count > 0 && $count < 3) {
			$thin[] = $page->post_title;
		}
	}
	if (!empty($thin)) {
		return ['status' => lf_health_status_warning(), 'label' => __('Thin pages', 'leadsforward-core'), 'message' => __('Low section count: ', 'leadsforward-core') . implode(', ', $thin), 'fix_link' => admin_url('edit.php?post_type=page')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Thin pages', 'leadsforward-core'), 'message' => __('No thin core pages detected.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_schema_present(): array {
	$toggles = ['lf_schema_local_business', 'lf_schema_organization'];
	$on = 0;
	foreach ($toggles as $key) {
		$v = function_exists('get_field') ? get_field($key, 'option') : null;
		if ($v === true || $v === '1' || $v === 1) {
			$on++;
		}
	}
	if ($on === 0) {
		return ['status' => lf_health_status_warning(), 'label' => __('Required schema', 'leadsforward-core'), 'message' => __('LocalBusiness/Organization schema toggles are off.', 'leadsforward-core'), 'fix_link' => lf_health_fix_url_acf_option_page('lf-schema')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Required schema', 'leadsforward-core'), 'message' => __('Schema toggles set.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_noindex_money_pages(): array {
	// Theme only noindexes search, 404, testimonials. Front, services, areas, pages are indexable.
	if (!apply_filters('lf_output_noindex_where_needed', true)) {
		return ['status' => lf_health_status_warning(), 'label' => __('Noindex on money pages', 'leadsforward-core'), 'message' => __('Filter lf_output_noindex_where_needed is disabled; verify noindex is not applied to key pages.', 'leadsforward-core'), 'fix_link' => ''];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Noindex on money pages', 'leadsforward-core'), 'message' => __('Theme only noindexes search, 404, testimonials.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_canonicals(): array {
	if (!apply_filters('lf_output_canonical', true)) {
		return ['status' => lf_health_status_warning(), 'label' => __('Canonicals present', 'leadsforward-core'), 'message' => __('Theme canonical is disabled. Ensure another source outputs canonicals.', 'leadsforward-core'), 'fix_link' => ''];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Canonicals present', 'leadsforward-core'), 'message' => __('Theme outputs canonical.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_single_h1(): array {
	if (!function_exists('lf_heading_collect_site_issues')) {
		return ['status' => lf_health_status_pass(), 'label' => __('Heading rules', 'leadsforward-core'), 'message' => __('Heading validator unavailable.', 'leadsforward-core'), 'fix_link' => ''];
	}
	$issues = lf_heading_collect_site_issues();
	if (empty($issues)) {
		return ['status' => lf_health_status_pass(), 'label' => __('Heading rules', 'leadsforward-core'), 'message' => __('No heading violations detected.', 'leadsforward-core'), 'fix_link' => ''];
	}
	$labels = [];
	foreach ($issues as $post_id => $warnings) {
		$label = get_the_title((int) $post_id);
		$labels[] = $label !== '' ? $label : sprintf(__('Post %d', 'leadsforward-core'), (int) $post_id);
	}
	$sample = array_slice($labels, 0, 5);
	$more = count($labels) - count($sample);
	$message = __('Heading issues detected on: ', 'leadsforward-core') . implode(', ', $sample);
	if ($more > 0) {
		$message .= sprintf(__(' (+%d more)', 'leadsforward-core'), $more);
	}
	return ['status' => lf_health_status_warning(), 'label' => __('Heading rules', 'leadsforward-core'), 'message' => $message, 'fix_link' => admin_url('edit.php?post_type=page')];
}

// --- Performance ---

function lf_health_check_jquery(): array {
	// Theme deregisters jQuery on frontend. Check that we're not re-enqueueing.
	$keep = apply_filters('lf_keep_jquery', false);
	if ($keep) {
		return ['status' => lf_health_status_warning(), 'label' => __('jQuery on frontend', 'leadsforward-core'), 'message' => __('lf_keep_jquery is true; jQuery may be loaded.', 'leadsforward-core'), 'fix_link' => ''];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('jQuery on frontend', 'leadsforward-core'), 'message' => __('jQuery not enqueued by theme.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_theme_script_count(): array {
	return [
		'status' => lf_health_status_pass(),
		'label' => __('Script weight (manual)', 'leadsforward-core'),
		'message' => __('Use DevTools → Network on homepage, a service page, and contact to confirm total JS weight and third-party calls.', 'leadsforward-core'),
		'fix_link' => '',
	];
}

function lf_health_check_lazy_load(): array {
	$loading = apply_filters('wp_lazy_loading_enabled', true);
	if (!$loading) {
		return ['status' => lf_health_status_warning(), 'label' => __('Image lazy-loading', 'leadsforward-core'), 'message' => __('Lazy-loading is disabled.', 'leadsforward-core'), 'fix_link' => ''];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Image lazy-loading', 'leadsforward-core'), 'message' => __('Enabled.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_dom_manual(): array {
	return ['status' => lf_health_status_pass(), 'label' => __('DOM size', 'leadsforward-core'), 'message' => __('Manual: verify key pages in browser (e.g. &lt; 1500 nodes).', 'leadsforward-core'), 'fix_link' => ''];
}

// --- Internal links ---

function lf_health_check_homepage_links_services(): array {
	$front_id = (int) get_option('page_on_front');
	if ($front_id === 0) {
		return ['status' => lf_health_status_warning(), 'label' => __('Homepage links to services', 'leadsforward-core'), 'message' => __('No static front page set.', 'leadsforward-core'), 'fix_link' => admin_url('options-reading.php')];
	}
	$config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
	$has_service = !empty($config['service_grid']['enabled']);
	if (!$has_service && !empty($config)) {
		return ['status' => lf_health_status_warning(), 'label' => __('Homepage links to services', 'leadsforward-core'), 'message' => __('Homepage has no Service Grid section.', 'leadsforward-core'), 'fix_link' => admin_url('admin.php?page=lf-homepage-settings')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Homepage links to services', 'leadsforward-core'), 'message' => $has_service ? __('Service grid present.', 'leadsforward-core') : __('No sections configured.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_footer_links(): array {
	$locations = get_theme_mod('nav_menu_locations', []);
	$footer_id = $locations['footer_menu'] ?? 0;
	if (empty($footer_id)) {
		return ['status' => lf_health_status_warning(), 'label' => __('Footer links', 'leadsforward-core'), 'message' => __('No footer menu assigned.', 'leadsforward-core'), 'fix_link' => admin_url('nav-menus.php')];
	}
	$items = wp_get_nav_menu_items($footer_id);
	if (empty($items)) {
		return ['status' => lf_health_status_warning(), 'label' => __('Footer links', 'leadsforward-core'), 'message' => __('Footer menu has no items.', 'leadsforward-core'), 'fix_link' => admin_url('nav-menus.php')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Footer links', 'leadsforward-core'), 'message' => count($items) . ' ' . __('items.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_orphaned_services(): array {
	$services = get_posts(['post_type' => 'lf_service', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids']);
	$areas = get_posts(['post_type' => 'lf_service_area', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids']);
	if (count($services) > 0 && count($areas) === 0) {
		return ['status' => lf_health_status_warning(), 'label' => __('Orphaned services/areas', 'leadsforward-core'), 'message' => __('Services exist but no service areas. Add areas and run Rebuild linking.', 'leadsforward-core'), 'fix_link' => admin_url('admin.php?page=lf-ops-bulk')];
	}
	$orphan_areas = 0;
	foreach ($areas as $area_id) {
		$linked = function_exists('get_field') ? get_field('lf_service_area_services', $area_id) : null;
		if (empty($linked) || !is_array($linked)) {
			$orphan_areas++;
		}
	}
	if ($orphan_areas > 0) {
		return ['status' => lf_health_status_warning(), 'label' => __('Orphaned services/areas', 'leadsforward-core'), 'message' => sprintf(__('Service areas missing linked services: %d', 'leadsforward-core'), $orphan_areas), 'fix_link' => admin_url('edit.php?post_type=lf_service_area')];
	}
	return ['status' => lf_health_status_pass(), 'label' => __('Orphaned services/areas', 'leadsforward-core'), 'message' => __('None detected.', 'leadsforward-core'), 'fix_link' => ''];
}

function lf_health_check_header_analytics(): array {
	$settings = function_exists('lf_seo_get_settings') ? lf_seo_get_settings() : [];
	$header = trim((string) ($settings['scripts']['header'] ?? ''));
	$fix = lf_health_seo_settings_url();
	if ($header === '') {
		return [
			'status' => lf_health_status_warning(),
			'label' => __('Header analytics (GTM / tags)', 'leadsforward-core'),
			'message' => __('Nothing is in SEO & Site Health → SEO settings → Scripts → Header scripts. Add Google Tag Manager or your measurement snippet.', 'leadsforward-core'),
			'fix_link' => $fix,
		];
	}
	$has_gtm = (bool) preg_match('/googletagmanager\.com|GTM-[A-Z0-9]+/i', $header);
	$has_ga = (bool) preg_match('/google-analytics\.com|googletagmanager\.com\/gtag\/js|gtag\s*\(/i', $header);
	if (!$has_gtm && !$has_ga) {
		return [
			'status' => lf_health_status_warning(),
			'label' => __('Header analytics (GTM / tags)', 'leadsforward-core'),
			'message' => __('Header scripts exist, but no typical GTM/gtag pattern was detected. Confirm the snippet is correct (or dismiss if you use another stack).', 'leadsforward-core'),
			'fix_link' => $fix,
		];
	}
	return [
		'status' => lf_health_status_pass(),
		'label' => __('Header analytics (GTM / tags)', 'leadsforward-core'),
		'message' => $has_gtm ? __('GTM or container ID pattern found in header scripts.', 'leadsforward-core') : __('Analytics-related pattern found in header scripts.', 'leadsforward-core'),
		'fix_link' => '',
	];
}

function lf_health_check_manifester_config(): array {
	$enabled = get_option('lf_ai_studio_enabled', '1') === '1';
	$webhook = trim((string) get_option('lf_ai_studio_webhook', ''));
	$secret = trim((string) get_option('lf_ai_studio_secret', ''));
	$fix = admin_url('admin.php?page=lf-global');
	if (!$enabled) {
		return [
			'status' => lf_health_status_warning(),
			'label' => __('Manifest Website (AI)', 'leadsforward-core'),
			'message' => __('Manifester is disabled. Turn it on in Global Settings when you use orchestrated generation.', 'leadsforward-core'),
			'fix_link' => $fix,
		];
	}
	if ($webhook === '' || $secret === '') {
		return [
			'status' => lf_health_status_fail(),
			'label' => __('Manifest Website (AI)', 'leadsforward-core'),
			'message' => __('Webhook URL or shared secret is missing; queued generation will fail.', 'leadsforward-core'),
			'fix_link' => $fix,
		];
	}
	return [
		'status' => lf_health_status_pass(),
		'label' => __('Manifest Website (AI)', 'leadsforward-core'),
		'message' => __('Webhook and secret are configured.', 'leadsforward-core'),
		'fix_link' => '',
	];
}

function lf_health_check_low_onpage_scores(): array {
	$query = new \WP_Query([
		'post_type'           => ['page', 'post', 'lf_service', 'lf_service_area'],
		'post_status'         => 'publish',
		'posts_per_page'      => 1,
		'fields'              => 'ids',
		'no_found_rows'       => false,
		'ignore_sticky_posts' => true,
		'meta_query'          => [
			[
				'key'     => '_lf_seo_quality_score',
				'value'   => 60,
				'type'    => 'NUMERIC',
				'compare' => '<',
			],
		],
	]);
	$low = (int) $query->found_posts;
	wp_reset_postdata();
	if ($low > 0) {
		return [
			'status' => lf_health_status_warning(),
			'label' => __('On-page SEO scores', 'leadsforward-core'),
			'message' => sprintf(
				/* translators: %d: number of posts */
				_n(
					'%d published entry has an SEO quality score under 60. Edit it and use the SEO meta box checklist.',
					'%d published entries have an SEO quality score under 60. Edit them and use the SEO meta box checklist.',
					$low,
					'leadsforward-core'
				),
				$low
			),
			'fix_link' => admin_url('edit.php?post_type=page'),
		];
	}
	return [
		'status' => lf_health_status_pass(),
		'label' => __('On-page SEO scores', 'leadsforward-core'),
		'message' => __('No published entries scored under 60. Re-save important pages if scores are still empty.', 'leadsforward-core'),
		'fix_link' => '',
	];
}

/**
 * All dashboard (quick) checks.
 */
function lf_health_dashboard_checks(): array {
	return [
		lf_health_check_theme_active(),
		lf_health_check_required_plugins(),
		lf_health_check_wizard_complete(),
		lf_health_check_variation_profile(),
		lf_health_check_business_info(),
		lf_health_check_header_analytics(),
		lf_health_check_manifester_config(),
	];
}

/**
 * All pre-launch checks grouped by category.
 */
function lf_health_prelaunch_checks(): array {
	return [
		'dashboard' => lf_health_dashboard_checks(),
		'seo'       => [
			lf_health_check_nap_complete(),
			lf_health_check_core_pages_exist(),
			lf_health_check_service_pages_exist(),
			lf_health_check_service_area_pages_exist(),
			lf_health_check_thin_pages(),
			lf_health_check_schema_present(),
			lf_health_check_noindex_money_pages(),
			lf_health_check_canonicals(),
			lf_health_check_single_h1(),
		],
		'onpage' => [
			lf_health_check_low_onpage_scores(),
		],
		'performance' => [
			lf_health_check_jquery(),
			lf_health_check_theme_script_count(),
			lf_health_check_lazy_load(),
			lf_health_check_dom_manual(),
		],
		'links' => [
			lf_health_check_homepage_links_services(),
			lf_health_check_footer_links(),
			lf_health_check_orphaned_services(),
		],
	];
}
