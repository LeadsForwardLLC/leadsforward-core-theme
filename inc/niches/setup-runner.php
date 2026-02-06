<?php
/**
 * Setup runner: create pages, CPTs, menus, seed relationships, update ACF.
 * Idempotent where possible; no duplicate pages. Called by wizard on Generate.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Run full site setup. Returns ['success' => bool, 'message' => '', 'created' => [], 'errors' => []].
 *
 * @param array $data niche_slug, business_name, business_phone, business_email, business_address, business_hours, service_areas (array of strings or [name=>, state=>]), variation_profile_override (optional)
 */
function lf_run_setup(array $data): array {
	$log = ['created' => ['pages' => [], 'services' => [], 'service_areas' => [], 'menus' => []], 'errors' => []];
	$niche = lf_get_niche($data['niche_slug'] ?? '');
	if (!$niche) {
		$log['errors'][] = __('Invalid niche.', 'leadsforward-core');
		return ['success' => false, 'message' => __('Invalid niche.', 'leadsforward-core'), 'created' => $log['created'], 'errors' => $log['errors']];
	}

	// 1. Pages (idempotent)
	$page_titles = array_merge(lf_wizard_default_page_titles(), $niche['required_pages'] ?? []);
	$slugs = lf_wizard_required_page_slugs();
	$created_pages = [];
	foreach ($slugs as $slug) {
		$title = $page_titles[$slug] ?? $slug;
		$existing = get_page_by_path($slug, OBJECT, 'page');
		if ($existing) {
			$created_pages[$slug] = $existing->ID;
			continue;
		}
		$content = lf_wizard_placeholder_content($slug, $title, $data);
		$pid = wp_insert_post([
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
		], true);
		if (is_wp_error($pid)) {
			$log['errors'][] = sprintf(__('Page %1$s: %2$s', 'leadsforward-core'), $title, $pid->get_error_message());
			continue;
		}
		$created_pages[$slug] = $pid;
		$log['created']['pages'][] = ['slug' => $slug, 'id' => $pid];
	}

	$home_id = $created_pages['home'] ?? null;
	if ($home_id) {
		update_option('show_on_front', 'page');
		update_option('page_on_front', $home_id);
	}

	// 2. Service CPT entries (from niche; skip if slug exists)
	$service_names = $niche['services'] ?? [];
	$created_services = [];
	foreach ($service_names as $name) {
		$slug = sanitize_title($name);
		$exists = get_page_by_path($slug, OBJECT, 'lf_service');
		if ($exists) {
			$created_services[] = $exists->ID;
			continue;
		}
		$sid = wp_insert_post([
			'post_title'   => $name,
			'post_name'   => $slug,
			'post_content' => lf_wizard_service_placeholder_content($name, $data),
			'post_status'  => 'publish',
			'post_type'    => 'lf_service',
			'post_author'  => get_current_user_id(),
		], true);
		if (!is_wp_error($sid)) {
			$created_services[] = $sid;
			$log['created']['services'][] = ['title' => $name, 'id' => $sid];
		} else {
			$log['errors'][] = $sid->get_error_message();
		}
	}

	// 3. Service area CPT entries (from wizard data)
	$area_input = $data['service_areas'] ?? [];
	$areas_parsed = lf_wizard_parse_service_areas($area_input);
	$created_areas = [];
	foreach ($areas_parsed as $area) {
		$slug = $area['state'] ? sanitize_title($area['name'] . '-' . $area['state']) : sanitize_title($area['name']);
		$exists = get_page_by_path($slug, OBJECT, 'lf_service_area');
		if ($exists) {
			$created_areas[] = $exists->ID;
			continue;
		}
		$aid = wp_insert_post([
			'post_title'   => $area['name'],
			'post_name'   => sanitize_title($area['name']),
			'post_content' => lf_wizard_service_area_placeholder_content($area, $data),
			'post_status'  => 'publish',
			'post_type'    => 'lf_service_area',
			'post_author'  => get_current_user_id(),
		], true);
		if (!is_wp_error($aid)) {
			$created_areas[] = $aid;
			if (function_exists('update_field') && !empty($area['state'])) {
				update_field('lf_service_area_state', $area['state'], $aid);
			}
			$log['created']['service_areas'][] = ['title' => $area['name'], 'id' => $aid];
		}
	}

	// 4. ACF options: business, CTAs, variation, schema, homepage sections
	if (function_exists('update_field')) {
		update_field('lf_business_name', $data['business_name'] ?? '', 'option');
		update_field('lf_business_phone', $data['business_phone'] ?? '', 'option');
		update_field('lf_business_email', $data['business_email'] ?? '', 'option');
		update_field('lf_business_address', $data['business_address'] ?? '', 'option');
		if (!empty($data['business_hours'])) {
			update_field('lf_business_hours', $data['business_hours'], 'option');
		}
		update_field('lf_cta_primary_text', $niche['cta_primary_default'] ?? '', 'option');
		update_field('lf_cta_secondary_text', $niche['cta_secondary_default'] ?? '', 'option');
		$profile = $data['variation_profile_override'] ?? $niche['variation_profile'] ?? 'a';
		update_field('variation_profile', $profile, 'option');
		update_field('lf_schema_review', !empty($niche['schema_review_enabled']), 'option');
		$section_order = lf_niche_homepage_section_order($data['niche_slug']);
		lf_wizard_seed_homepage_sections($section_order, 'option');
	}

	// 5. Internal linking: service ↔ service area relationships
	foreach ($created_services as $sid) {
		if (function_exists('update_field') && !empty($created_areas)) {
			update_field('lf_service_related_areas', $created_areas, $sid);
		}
	}
	foreach ($created_areas as $aid) {
		if (function_exists('update_field') && !empty($created_services)) {
			update_field('lf_service_area_services', $created_services, $aid);
		}
	}

	// 6. Menus
	$menu_result = lf_wizard_create_menus($created_pages, $created_services, $created_areas);
	$log['created']['menus'] = $menu_result['created'] ?? [];
	if (!empty($menu_result['errors'])) {
		$log['errors'] = array_merge($log['errors'], $menu_result['errors']);
	}

	$success = empty($log['errors']);
	return [
		'success' => $success,
		'message' => $success ? __('Site setup complete.', 'leadsforward-core') : __('Setup finished with some errors.', 'leadsforward-core'),
		'created' => $log['created'],
		'errors'  => $log['errors'],
	];
}

/**
 * Placeholder content for a page. Safe, non-lorem; structure only.
 */
function lf_wizard_placeholder_content(string $slug, string $title, array $data): string {
	$business = $data['business_name'] ?? get_bloginfo('name');
	switch ($slug) {
		case 'home':
			return '<!-- wp:paragraph --><p>' . esc_html__('Welcome. Use the block editor or Theme Options to customize this page.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'about-us':
			return '<!-- wp:paragraph --><p>' . sprintf(esc_html__('About %s. Add your story and why customers choose you.', 'leadsforward-core'), $business) . '</p><!-- /wp:paragraph -->';
		case 'our-services':
			return '<!-- wp:paragraph --><p>' . esc_html__('We offer a range of services. Browse the list below or use the Services menu.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'our-service-areas':
			return '<!-- wp:paragraph --><p>' . esc_html__('We serve multiple areas. Select a location to learn more.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'contact':
			return '<!-- wp:paragraph --><p>' . esc_html__('Contact us by phone or the form below.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'privacy-policy':
			return '<!-- wp:paragraph --><p>' . esc_html__('Privacy policy content. Replace with your legal text.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'terms-of-use':
			return '<!-- wp:paragraph --><p>' . esc_html__('Terms of use. Replace with your legal text.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'thank-you':
			return '<!-- wp:paragraph --><p>' . esc_html__('Thank you for your submission. We will be in touch soon.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		default:
			return '<!-- wp:paragraph --><p>' . esc_html($title) . '</p><!-- /wp:paragraph -->';
	}
}

function lf_wizard_service_placeholder_content(string $service_name, array $data): string {
	return '<!-- wp:paragraph --><p>' . sprintf(esc_html__('We provide %s. Contact us for more information or a quote.', 'leadsforward-core'), $service_name) . '</p><!-- /wp:paragraph -->';
}

function lf_wizard_service_area_placeholder_content(array $area, array $data): string {
	$name = $area['name'] ?? '';
	$state = $area['state'] ?? '';
	$loc = $state ? $name . ', ' . $state : $name;
	return '<!-- wp:paragraph --><p>' . sprintf(esc_html__('We serve %s. Get in touch for service in this area.', 'leadsforward-core'), $loc) . '</p><!-- /wp:paragraph -->';
}

/**
 * Parse service_areas input: array of strings "City" or "City, ST" or array of [name=>, state=>].
 */
function lf_wizard_parse_service_areas($input): array {
	if (!is_array($input)) {
		$input = array_filter(array_map('trim', explode("\n", is_string($input) ? $input : '')));
	}
	$out = [];
	foreach ($input as $item) {
		if (is_array($item)) {
			$name = $item['name'] ?? $item['city'] ?? '';
			if ($name !== '') {
				$out[] = ['name' => $name, 'state' => $item['state'] ?? ''];
			}
			continue;
		}
		$item = trim((string) $item);
		if ($item === '') continue;
		if (preg_match('/^(.+),\s*([A-Za-z]{2})$/', $item, $m)) {
			$out[] = ['name' => trim($m[1]), 'state' => strtoupper($m[2])];
		} else {
			$out[] = ['name' => $item, 'state' => ''];
		}
	}
	return $out;
}

/**
 * Seed ACF homepage_sections from section type order. Builds flexible content rows.
 */
function lf_wizard_seed_homepage_sections(array $section_order, string $options_context = 'option'): void {
	if (!function_exists('update_field')) {
		return;
	}
	$rows = [];
	foreach ($section_order as $type) {
		$rows[] = [
			'acf_fc_layout'  => 'homepage_section',
			'section_type'   => $type,
			'layout_variant' => 'default',
		];
	}
	update_field('homepage_sections', $rows, $options_context);
}

/**
 * Create Header and Footer menus; assign to theme locations; add items.
 */
function lf_wizard_create_menus(array $created_pages, array $service_ids, array $area_ids): array {
	$log = ['created' => [], 'errors' => []];
	$home_id = $created_pages['home'] ?? null;
	$about_id = $created_pages['about-us'] ?? null;
	$contact_id = $created_pages['contact'] ?? null;
	$our_services_id = $created_pages['our-services'] ?? null;
	$our_areas_id = $created_pages['our-service-areas'] ?? null;
	$privacy_id = $created_pages['privacy-policy'] ?? null;
	$terms_id = $created_pages['terms-of-use'] ?? null;

	$header_items = [];
	if ($home_id) $header_items[] = ['type' => 'page', 'object_id' => $home_id];
	$header_items[] = ['type' => 'custom', 'url' => get_post_type_archive_link('lf_service'), 'title' => __('Services', 'leadsforward-core')];
	$header_items[] = ['type' => 'custom', 'url' => get_post_type_archive_link('lf_service_area'), 'title' => __('Service Areas', 'leadsforward-core')];
	if ($about_id) $header_items[] = ['type' => 'page', 'object_id' => $about_id];
	if ($contact_id) $header_items[] = ['type' => 'page', 'object_id' => $contact_id];

	$footer_items = [];
	if ($home_id) $footer_items[] = ['type' => 'page', 'object_id' => $home_id];
	if ($contact_id) $footer_items[] = ['type' => 'page', 'object_id' => $contact_id];
	if ($privacy_id) $footer_items[] = ['type' => 'page', 'object_id' => $privacy_id];
	if ($terms_id) $footer_items[] = ['type' => 'page', 'object_id' => $terms_id];
	$footer_items[] = ['type' => 'custom', 'url' => get_post_type_archive_link('lf_service'), 'title' => __('Services', 'leadsforward-core')];
	$footer_items[] = ['type' => 'custom', 'url' => get_post_type_archive_link('lf_service_area'), 'title' => __('Service Areas', 'leadsforward-core')];

	$header_menu_id = lf_wizard_ensure_menu('Header Menu', 'header_menu', $header_items);
	$footer_menu_id = lf_wizard_ensure_menu('Footer Menu', 'footer_menu', $footer_items);
	if ($header_menu_id) $log['created'][] = 'header_menu';
	if ($footer_menu_id) $log['created'][] = 'footer_menu';
	return $log;
}

function lf_wizard_ensure_menu(string $menu_name, string $location, array $items): ?int {
	$menus = wp_get_nav_menus();
	$menu_id = null;
	foreach ($menus as $m) {
		if ($m->name === $menu_name) {
			$menu_id = $m->term_id;
			break;
		}
	}
	if (!$menu_id) {
		$menu_id = wp_create_nav_menu($menu_name);
		if (is_wp_error($menu_id)) {
			return null;
		}
	} else {
		$existing = wp_get_nav_menu_items($menu_id);
		if ($existing) {
			foreach ($existing as $item) {
				wp_delete_post($item->ID, true);
			}
		}
	}
	$position = 0;
	foreach ($items as $item) {
		if ($item['type'] === 'page' && !empty($item['object_id'])) {
			wp_update_nav_menu_item($menu_id, 0, [
				'menu-item-title'     => get_the_title($item['object_id']),
				'menu-item-url'       => get_permalink($item['object_id']),
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $item['object_id'],
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $position++,
			]);
		} elseif ($item['type'] === 'custom' && !empty($item['url'])) {
			wp_update_nav_menu_item($menu_id, 0, [
				'menu-item-title'    => $item['title'] ?? '',
				'menu-item-url'      => $item['url'],
				'menu-item-type'     => 'custom',
				'menu-item-status'   => 'publish',
				'menu-item-position' => $position++,
			]);
		}
	}
	$locations = get_theme_mod('nav_menu_locations') ?: [];
	$locations[$location] = $menu_id;
	set_theme_mod('nav_menu_locations', $locations);
	return $menu_id;
}
