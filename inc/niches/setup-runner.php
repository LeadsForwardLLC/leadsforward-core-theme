<?php
/**
 * Setup runner: create pages, CPTs, menus, seed relationships, update ACF.
 * Idempotent where possible; no duplicate pages. Called by site setup on Generate.
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
 * @param array $data niche_slug, business_name, business_legal_name, business_phone_primary, business_phone_tracking, business_phone_display, business_email,
 *                    business_address, business_address_street, business_address_city, business_address_state, business_address_zip,
 *                    business_service_area_type, business_geo, business_hours, business_category, business_short_description,
 *                    business_gbp_url, business_social_*, business_same_as, business_founding_year, business_license_number,
 *                    business_insurance_statement, business_place_id, business_place_name, business_place_address, business_map_embed,
 *                    service_areas (array of strings or [name=>, state=>]), variation_profile_override (optional)
 */
function lf_run_setup(array $data): array {
	$log = ['created' => ['pages' => [], 'services' => [], 'service_areas' => [], 'menus' => []], 'errors' => []];
	$niche = lf_get_niche($data['niche_slug'] ?? '');
	if (!$niche) {
		$log['errors'][] = __('Invalid niche.', 'leadsforward-core');
		return ['success' => false, 'message' => __('Invalid niche.', 'leadsforward-core'), 'created' => $log['created'], 'errors' => $log['errors']];
	}

	// 1. Pages (idempotent): core + cross-niche + per-niche landing pages
	$page_titles = array_merge(
		lf_wizard_default_page_titles(),
		function_exists('lf_wizard_extended_page_titles') ? lf_wizard_extended_page_titles() : [],
		$niche['required_pages'] ?? []
	);
	$slugs = function_exists('lf_wizard_page_slugs_for_niche') ? lf_wizard_page_slugs_for_niche($niche) : lf_wizard_required_page_slugs();
	$created_pages = [];
	$new_pages = [];
	foreach ($slugs as $slug) {
		$title = $page_titles[$slug] ?? $slug;
		$existing = get_page_by_path($slug, OBJECT, 'page');
		if (!$existing && $slug === 'terms-of-service') {
			$legacy = get_page_by_path('terms-of-use', OBJECT, 'page');
			if ($legacy) {
				wp_update_post([
					'ID' => $legacy->ID,
					'post_name' => $slug,
					'post_title' => $title,
				]);
				$existing = get_post($legacy->ID);
			}
		}
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
		$new_pages[] = $slug;
		$log['created']['pages'][] = ['slug' => $slug, 'id' => $pid];
	}

	$home_id = $created_pages['home'] ?? null;
	if ($home_id) {
		update_option('show_on_front', 'page');
		update_option('page_on_front', $home_id);
	}

	// 2. Service CPT entries (from niche or provided list; skip if slug exists)
	$service_items = [];
	if (!empty($data['services']) && is_array($data['services'])) {
		foreach ($data['services'] as $item) {
			if (is_array($item)) {
				$title = sanitize_text_field((string) ($item['title'] ?? $item['name'] ?? ''));
				$slug = sanitize_title((string) ($item['slug'] ?? ''));
				if ($slug === '' && $title !== '') {
					$slug = sanitize_title($title);
				}
				if ($title !== '' || $slug !== '') {
					$service_items[] = ['title' => $title, 'slug' => $slug];
				}
				continue;
			}
			$name = sanitize_text_field((string) $item);
			if ($name !== '') {
				$service_items[] = ['title' => $name, 'slug' => sanitize_title($name)];
			}
		}
	}
	if (empty($service_items)) {
		$service_names = $niche['services'] ?? [];
		foreach ($service_names as $name) {
			$name = sanitize_text_field((string) $name);
			if ($name !== '') {
				$service_items[] = ['title' => $name, 'slug' => sanitize_title($name)];
			}
		}
	}
	$created_services = [];
	$new_services = [];
	foreach ($service_items as $item) {
		$index = count($created_services);
		$name = (string) ($item['title'] ?? '');
		$slug = (string) ($item['slug'] ?? '');
		if ($slug === '' && $name !== '') {
			$slug = sanitize_title($name);
		}
		if ($slug === '' && $name === '') {
			continue;
		}
		$exists = get_page_by_path($slug, OBJECT, 'lf_service');
		if ($exists) {
			$created_services[] = $exists->ID;
			continue;
		}
		$sid = wp_insert_post([
			'post_title'   => $name !== '' ? $name : $slug,
			'post_name'   => $slug,
			'post_content' => lf_wizard_service_placeholder_content($name !== '' ? $name : $slug, $data, $index, $niche),
			'post_status'  => 'publish',
			'post_type'    => 'lf_service',
			'post_author'  => get_current_user_id(),
		], true);
		if (!is_wp_error($sid)) {
			$created_services[] = $sid;
			$new_services[] = ['id' => $sid, 'name' => $name !== '' ? $name : $slug, 'index' => $index];
			$log['created']['services'][] = ['title' => $name !== '' ? $name : $slug, 'id' => $sid];
		} else {
			$log['errors'][] = $sid->get_error_message();
		}
	}

	// 3. Service area CPT entries (from setup data)
	$area_input = $data['service_areas'] ?? [];
	$areas_parsed = lf_wizard_parse_service_areas($area_input);
	$created_areas = [];
	$new_areas = [];
		foreach ($areas_parsed as $area) {
		$index = count($created_areas);
			$slug = (string) ($area['slug'] ?? '');
			if ($slug === '') {
				$slug = $area['state'] ? sanitize_title($area['name'] . '-' . $area['state']) : sanitize_title($area['name']);
			}
		$exists = get_page_by_path($slug, OBJECT, 'lf_service_area');
		if ($exists) {
			$created_areas[] = $exists->ID;
			continue;
		}
		$aid = wp_insert_post([
			'post_title'   => $area['name'],
			'post_name'   => $slug,
			'post_content' => lf_wizard_service_area_placeholder_content($area, $data, $index, $niche),
			'post_status'  => 'publish',
			'post_type'    => 'lf_service_area',
			'post_author'  => get_current_user_id(),
		], true);
		if (!is_wp_error($aid)) {
			$created_areas[] = $aid;
			$new_areas[] = ['id' => $aid, 'area' => $area, 'index' => $index];
			if (function_exists('update_field') && !empty($area['state'])) {
				update_field('lf_service_area_state', $area['state'], $aid);
			}
			$log['created']['service_areas'][] = ['title' => $area['name'], 'id' => $aid];
		}
	}

	// 4. ACF options: business, CTAs, variation, schema, homepage sections
	if (function_exists('update_field')) {
		if (function_exists('lf_update_business_info_value')) {
			$biz_name = (string) ($data['business_name'] ?? '');
			$biz_legal = (string) ($data['business_legal_name'] ?? '');
			$biz_phone_primary = (string) ($data['business_phone_primary'] ?? $data['business_phone'] ?? '');
			$biz_phone_tracking = (string) ($data['business_phone_tracking'] ?? '');
			$phone_display = ($data['business_phone_display'] ?? 'primary') === 'tracking' ? 'tracking' : 'primary';
			$display_phone = $phone_display === 'tracking' && $biz_phone_tracking !== '' ? $biz_phone_tracking : $biz_phone_primary;
			$biz_email = (string) ($data['business_email'] ?? '');
			$address_street = (string) ($data['business_address_street'] ?? '');
			$address_city = (string) ($data['business_address_city'] ?? '');
			$address_state = (string) ($data['business_address_state'] ?? '');
			$address_zip = (string) ($data['business_address_zip'] ?? '');
			$address_full = (string) ($data['business_address'] ?? '');
			if ($address_full === '' && function_exists('lf_business_entity_address_string')) {
				$address_full = lf_business_entity_address_string([
					'street' => $address_street,
					'city' => $address_city,
					'state' => $address_state,
					'zip' => $address_zip,
				]);
			}
			$service_area_type = ($data['business_service_area_type'] ?? 'address') === 'service_area' ? 'service_area' : 'address';
			$geo = $data['business_geo'] ?? ['lat' => '', 'lng' => ''];
			$category = (string) ($data['business_category'] ?? 'HomeAndConstructionBusiness');
			$short_desc = (string) ($data['business_short_description'] ?? '');
			$gbp_url = (string) ($data['business_gbp_url'] ?? '');
			$same_as = (string) ($data['business_same_as'] ?? '');
			$founding_year = (string) ($data['business_founding_year'] ?? '');
			$license_number = (string) ($data['business_license_number'] ?? '');
			$insurance_statement = (string) ($data['business_insurance_statement'] ?? '');
			$place_id = (string) ($data['business_place_id'] ?? '');
			$place_name = (string) ($data['business_place_name'] ?? '');
			$place_address = (string) ($data['business_place_address'] ?? '');
			$map_embed = (string) ($data['business_map_embed'] ?? '');
			$allowed_embed = function_exists('lf_map_embed_allowed_iframe_kses')
				? lf_map_embed_allowed_iframe_kses()
				: ['iframe' => ['src' => true, 'width' => true, 'height' => true]];
			$map_embed = $map_embed !== '' ? wp_kses($map_embed, $allowed_embed) : '';

			lf_update_business_info_value('lf_business_name', $biz_name);
			lf_update_business_info_value('lf_business_legal_name', $biz_legal);
			lf_update_business_info_value('lf_business_phone_primary', $biz_phone_primary);
			lf_update_business_info_value('lf_business_phone_tracking', $biz_phone_tracking);
			lf_update_business_info_value('lf_business_phone_display', $phone_display);
			lf_update_business_info_value('lf_business_phone', $display_phone);
			lf_update_business_info_value('lf_business_email', $biz_email);
			lf_update_business_info_value('lf_business_address_street', $address_street);
			lf_update_business_info_value('lf_business_address_city', $address_city);
			lf_update_business_info_value('lf_business_address_state', $address_state);
			lf_update_business_info_value('lf_business_address_zip', $address_zip);
			lf_update_business_info_value('lf_business_address', $address_full);
			lf_update_business_info_value('lf_business_service_area_type', $service_area_type);
			lf_update_business_info_value('lf_business_geo', $geo);
			if (!empty($data['business_hours'])) {
				lf_update_business_info_value('lf_business_hours', $data['business_hours']);
			}
			lf_update_business_info_value('lf_business_category', $category);
			lf_update_business_info_value('lf_business_short_description', $short_desc);
			lf_update_business_info_value('lf_business_gbp_url', $gbp_url);
			lf_update_business_info_value('lf_business_social_facebook', (string) ($data['business_social_facebook'] ?? ''));
			lf_update_business_info_value('lf_business_social_instagram', (string) ($data['business_social_instagram'] ?? ''));
			lf_update_business_info_value('lf_business_social_youtube', (string) ($data['business_social_youtube'] ?? ''));
			lf_update_business_info_value('lf_business_social_linkedin', (string) ($data['business_social_linkedin'] ?? ''));
			lf_update_business_info_value('lf_business_social_tiktok', (string) ($data['business_social_tiktok'] ?? ''));
			lf_update_business_info_value('lf_business_social_x', (string) ($data['business_social_x'] ?? ''));
			lf_update_business_info_value('lf_business_same_as', $same_as);
			lf_update_business_info_value('lf_business_founding_year', $founding_year);
			lf_update_business_info_value('lf_business_license_number', $license_number);
			lf_update_business_info_value('lf_business_insurance_statement', $insurance_statement);
			lf_update_business_info_value('lf_business_place_id', $place_id);
			lf_update_business_info_value('lf_business_place_name', $place_name);
			lf_update_business_info_value('lf_business_place_address', $place_address);
			lf_update_business_info_value('lf_business_map_embed', $map_embed);
		}
		update_field('lf_cta_primary_text', $niche['cta_primary_default'] ?? '', 'option');
		update_field('lf_cta_secondary_text', $niche['cta_secondary_default'] ?? '', 'option');
		$profile = $data['variation_profile_override'] ?? $niche['variation_profile'] ?? 'a';
		update_field('variation_profile', $profile, 'option');
		update_field('lf_schema_review', !empty($niche['schema_review_enabled']), 'option');
	}
	// Homepage section config: always apply so front shows sections (does not require ACF).
	if (function_exists('lf_homepage_apply_niche_config')) {
		lf_homepage_apply_niche_config($data['niche_slug'], $data);
	}
	// Quote Builder config: apply default flow for this niche.
	if (function_exists('lf_quote_builder_apply_niche_config')) {
		lf_quote_builder_apply_niche_config($data['niche_slug']);
	}

	// 5. Internal linking: service area → services
	foreach ($created_areas as $aid) {
		if (function_exists('update_field') && !empty($created_services)) {
			update_field('lf_service_area_services', $created_services, $aid);
		}
	}

	// 6. Page Builder defaults for services and areas (only newly created)
	foreach ($new_services as $svc) {
		lf_wizard_seed_pb_config($svc['id'], 'service', $data, $niche, (int) $svc['index'], ['service' => $svc['name']]);
	}
	foreach ($new_areas as $row) {
		$area = $row['area'] ?? [];
		$loc = $area['name'] ?? '';
		if (!empty($area['state'])) {
			$loc = $loc ? $loc . ', ' . $area['state'] : $area['state'];
		}
		lf_wizard_seed_pb_config($row['id'], 'service_area', $data, $niche, (int) $row['index'], ['area' => $loc]);
	}
	// 6b. Page Builder defaults for core pages (only if no config)
	foreach ($created_pages as $slug => $page_id) {
		if ($slug === 'home') {
			continue;
		}
		$existing_config = get_post_meta($page_id, LF_PB_META_KEY, true);
		if (!lf_wizard_is_minimal_pb_config($existing_config)) {
			continue;
		}
		lf_wizard_seed_page_pb_config((int) $page_id, $slug, $data, $niche, $created_pages);
	}

	// 7. Menus
	$menu_result = lf_wizard_create_menus($created_pages, $created_services, $created_areas, $data);
	$log['created']['menus'] = $menu_result['created'] ?? [];
	if (!empty($menu_result['errors'])) {
		$log['errors'] = array_merge($log['errors'], $menu_result['errors']);
	}

	$success = empty($log['errors']);
	// IDs to track for dev reset: all pages/services/areas used by this run (created or existing).
	$ids = [
		'page_ids'         => array_values($created_pages),
		'service_ids'      => $created_services,
		'service_area_ids' => $created_areas,
	];
	$blueprints = [
		'success' => $success,
		'message' => $success ? __('Manual setup complete.', 'leadsforward-core') : __('Setup finished with some errors.', 'leadsforward-core'),
		'created' => $log['created'],
		'ids'     => $ids,
		'errors'  => $log['errors'],
	];
	return $blueprints;
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
		case 'service-areas':
			return '<!-- wp:paragraph --><p>' . esc_html__('We serve multiple areas. Select a location to learn more.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'reviews':
			return '<!-- wp:paragraph --><p>' . esc_html__('Read what local homeowners are saying about our work.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'blog':
			return '<!-- wp:paragraph --><p>' . esc_html__('Helpful tips, guides, and service updates from our team.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'sitemap':
			return '<!-- wp:paragraph --><p>' . esc_html__('Browse all pages, services, and areas on this site.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'contact':
			return '<!-- wp:paragraph --><p>' . esc_html__('Contact us by phone or the form below.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		case 'privacy-policy':
			$contact_bits = array_filter([
				!empty($data['business_phone']) ? sprintf(esc_html__('Call %s', 'leadsforward-core'), $data['business_phone']) : '',
				!empty($data['business_email']) ? sprintf(esc_html__('Email %s', 'leadsforward-core'), $data['business_email']) : '',
			]);
			$contact_line = $contact_bits ? sprintf(esc_html__('Questions? %s.', 'leadsforward-core'), implode(esc_html__(' or ', 'leadsforward-core'), $contact_bits)) : '';
			$intro = sprintf(esc_html__('This Privacy Policy explains how %s collects, uses, and protects your information when you use this site.', 'leadsforward-core'), $business);
			$body = esc_html__('We only use your information to respond to inquiries, schedule service, and improve your experience.', 'leadsforward-core');
			$parts = array_filter([$intro, $body, $contact_line]);
			return '<!-- wp:paragraph --><p>' . implode('</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>', $parts) . '</p><!-- /wp:paragraph -->';
		case 'terms-of-service':
			$contact_bits = array_filter([
				!empty($data['business_phone']) ? sprintf(esc_html__('Call %s', 'leadsforward-core'), $data['business_phone']) : '',
				!empty($data['business_email']) ? sprintf(esc_html__('Email %s', 'leadsforward-core'), $data['business_email']) : '',
			]);
			$contact_line = $contact_bits ? sprintf(esc_html__('Questions? %s.', 'leadsforward-core'), implode(esc_html__(' or ', 'leadsforward-core'), $contact_bits)) : '';
			$intro = sprintf(esc_html__('These Terms of Service govern use of this website and services provided by %s.', 'leadsforward-core'), $business);
			$body = esc_html__('By using this site, you agree to these terms and to provide accurate information when requesting service.', 'leadsforward-core');
			$parts = array_filter([$intro, $body, $contact_line]);
			return '<!-- wp:paragraph --><p>' . implode('</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>', $parts) . '</p><!-- /wp:paragraph -->';
		case 'thank-you':
			return '<!-- wp:paragraph --><p>' . esc_html__('Thank you for your submission. We will be in touch soon.', 'leadsforward-core') . '</p><!-- /wp:paragraph -->';
		default:
			return '<!-- wp:paragraph --><p>' . esc_html($title) . '</p><!-- /wp:paragraph -->';
	}
}

function lf_wizard_primary_city(array $data): string {
	if (!empty($data['homepage_city'])) {
		return sanitize_text_field((string) $data['homepage_city']);
	}
	$areas = $data['service_areas'] ?? [];
	if (!is_array($areas) || empty($areas)) {
		return '';
	}
	$first = reset($areas);
	if (is_array($first)) {
		return (string) ($first['name'] ?? '');
	}
	$first = trim((string) $first);
	if (preg_match('/^(.+),\s*[A-Za-z]{2}$/', $first, $m)) {
		return trim($m[1]);
	}
	return $first;
}

function lf_wizard_template_vars(array $data, array $extra = []): array {
	$business = $data['business_name'] ?? get_bloginfo('name');
	$city = lf_wizard_primary_city($data);
	$phone = $data['business_phone'] ?? '';
	$email = $data['business_email'] ?? '';
	$address = $data['business_address'] ?? '';
	$vars = array_merge([
		'business' => $business,
		'city'     => $city,
		'phone'    => $phone,
		'email'    => $email,
		'address'  => $address,
	], $extra);
	return $vars;
}

function lf_wizard_data_from_entity(): array {
	$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
	$areas = is_array($entity['service_areas'] ?? null) ? $entity['service_areas'] : [];
	$niche_slug = (string) get_option('lf_homepage_niche_slug', function_exists('lf_default_niche_slug') ? lf_default_niche_slug() : 'foundation-repair');
	$homepage_city = sanitize_text_field((string) get_option('lf_homepage_city', ''));
	if ($homepage_city === '') {
		$seed = lf_wizard_primary_city(['service_areas' => $areas]);
		$homepage_city = sanitize_text_field((string) $seed);
	}
	$blueprints = [
		'niche_slug' => $niche_slug !== '' ? $niche_slug : (function_exists('lf_default_niche_slug') ? lf_default_niche_slug() : 'foundation-repair'),
		'business_name' => (string) ($entity['name'] ?? get_bloginfo('name')),
		'business_phone' => (string) ($entity['phone_display'] ?? ''),
		'business_phone_primary' => (string) ($entity['phone_primary'] ?? ($entity['phone_display'] ?? '')),
		'business_phone_display' => (string) ($entity['phone_display'] ?? ''),
		'business_email' => (string) ($entity['email'] ?? ''),
		'business_address' => (string) ($entity['address'] ?? ''),
		'business_hours' => (string) get_option('lf_business_hours', ''),
		'homepage_city' => $homepage_city,
		'service_areas' => $areas,
	];
	return $blueprints;
}

function lf_wizard_regenerate_legal_pages(array $data): array {
	if (function_exists('lf_ensure_legal_pages')) {
		$result = lf_ensure_legal_pages();
		if (!empty($result['error'])) {
			return ['success' => false, 'message' => (string) $result['error']];
		}
	}
	return ['success' => true];
}

function lf_wizard_pick($items, int $index): string {
	if (empty($items)) {
		return '';
	}
	if (!is_array($items)) {
		return (string) $items;
	}
	$pos = $index % count($items);
	return (string) ($items[$pos] ?? '');
}

function lf_wizard_pick_list($items, int $index): array {
	if (empty($items)) {
		return [];
	}
	if (!is_array($items)) {
		return [(string) $items];
	}
	$pos = $index % count($items);
	$pick = $items[$pos] ?? [];
	if (is_array($pick)) {
		return $pick;
	}
	if ($pick !== '') {
		return [(string) $pick];
	}
	return [];
}

function lf_wizard_fill_template(string $template, array $vars): string {
	foreach ($vars as $key => $val) {
		$template = str_replace('{' . $key . '}', (string) $val, $template);
	}
	return trim(preg_replace('/\s+/', ' ', $template));
}

function lf_wizard_fill_list(array $items, array $vars): string {
	$out = [];
	foreach ($items as $item) {
		$out[] = lf_wizard_fill_template($item, $vars);
	}
	return implode("\n", $out);
}

function lf_wizard_get_service_templates(array $niche): array {
	$general = [
		'hero_headline' => [
			'Trusted {service} in {city}',
			'Expert {service} for {city} homeowners',
			'{service} you can count on in {city}',
		],
		'hero_subheadline' => [
			'Fast response, clear pricing, and professional crews for {service} projects.',
			'Local {service} specialists focused on quality and clean job sites.',
			'Schedule {service} with a local team that shows up on time.',
		],
		'benefits_heading' => [
			'Why Homeowners Choose Us',
			'Why {business} for {service}',
		],
		'benefits_intro' => [
			'Transparent pricing and expert workmanship for {service}.',
			'Clear communication, clean work, and results you can trust.',
		],
		'benefits_items' => [
			[
				'Upfront pricing before work begins',
				'Licensed & insured professionals',
				'Fast scheduling windows',
			],
			[
				'Experienced {service} technicians',
				'Clean, respectful crews',
				'Work backed by warranty',
			],
		],
		'process_heading' => [
			'Our {service} Process',
			'How {service} works',
		],
		'process_intro' => [
			'Simple, clear steps from first call to completion.',
			'We keep it easy from estimate to final walkthrough.',
		],
		'process_steps' => [
			[
				'Tell us about your {service} needs',
				'Get a fast, clear estimate',
				'Schedule and complete the work',
			],
			[
				'Book a quick consult',
				'Approve the plan and pricing',
				'We complete the job and follow up',
			],
		],
		'faq_heading' => [
			'{service} FAQs',
			'Questions about {service}',
		],
		'faq_intro' => [
			'Quick answers about scheduling, pricing, and what to expect.',
			'Helpful answers before you book your {service}.',
		],
		'cta_headline' => [
			'Ready to start your {service} project?',
			'Get your {service} estimate today',
		],
		'cta_subheadline' => [
			'Get a free estimate and a clear next step today.',
			'Fast scheduling, honest pricing, and expert support.',
		],
		'trust_heading' => [
			'Trusted by {city} homeowners',
			'Local {service} pros you can trust',
		],
		'related_heading' => [
			'Explore More',
			'Related Services & Areas',
		],
		'related_intro' => [
			'Browse related services and areas we serve.',
		],
	];
	return array_merge($general, $niche['service_templates'] ?? []);
}

function lf_wizard_get_area_templates(array $niche): array {
	$general = [
		'hero_headline' => [
			'Local service in {area}',
			'Trusted home services in {area}',
		],
		'hero_subheadline' => [
			'Trusted local team for repairs, installs, and ongoing service in {area}.',
			'Fast response times and expert service for {area} homeowners.',
		],
		'benefits_heading' => [
			'Why Homeowners Choose Us',
			'Why {business} in {area}',
		],
		'benefits_intro' => [
			'Clear pricing, fast response, and workmanship you can trust.',
			'Local, reliable service backed by clean workmanship.',
		],
		'benefits_items' => [
			[
				'Fast scheduling in {area}',
				'Licensed & insured team',
				'Upfront pricing',
			],
			[
				'Respectful crews',
				'Quality workmanship',
				'Work backed by warranty',
			],
		],
		'process_heading' => [
			'Our Process',
			'How service works in {area}',
		],
		'process_intro' => [
			'Simple steps from request to completion.',
		],
		'process_steps' => [
			[
				'Tell us what you need',
				'Get a fast estimate',
				'Schedule and complete the work',
			],
		],
		'faq_heading' => [
			'FAQs for {area}',
			'Common questions in {area}',
		],
		'faq_intro' => [
			'Helpful answers about scheduling and service in your area.',
		],
		'cta_headline' => [
			'Need service in {area}?',
			'Get your estimate for {area} today',
		],
		'cta_subheadline' => [
			'Fast scheduling and clear pricing for {area} homeowners.',
		],
		'trust_heading' => [
			'Trusted by local homeowners',
		],
		'related_heading' => [
			'Explore More',
		],
		'related_intro' => [
			'Browse related services and areas we serve.',
		],
		'services_heading' => [
			'Services in {area}',
		],
		'nearby_heading' => [
			'Nearby service areas',
		],
	];
	return array_merge($general, $niche['service_area_templates'] ?? []);
}

function lf_wizard_service_placeholder_content(string $service_name, array $data, int $index = 0, array $niche = []): string {
	$templates = lf_wizard_get_service_templates($niche);
	$vars = lf_wizard_template_vars($data, ['service' => $service_name]);
	$line1 = lf_wizard_fill_template(lf_wizard_pick($templates['hero_subheadline'] ?? [], $index), $vars);
	$line2 = lf_wizard_fill_template(lf_wizard_pick($templates['benefits_intro'] ?? [], $index), $vars);
	return '<!-- wp:paragraph --><p>' . esc_html($line1) . '</p><!-- /wp:paragraph -->' .
		'<!-- wp:paragraph --><p>' . esc_html($line2) . '</p><!-- /wp:paragraph -->';
}

function lf_wizard_service_area_placeholder_content(array $area, array $data, int $index = 0, array $niche = []): string {
	$name = $area['name'] ?? '';
	$state = $area['state'] ?? '';
	$loc = $state ? $name . ', ' . $state : $name;
	$templates = lf_wizard_get_area_templates($niche);
	$vars = lf_wizard_template_vars($data, ['area' => $loc]);
	$line1 = lf_wizard_fill_template(lf_wizard_pick($templates['hero_subheadline'] ?? [], $index), $vars);
	$line2 = lf_wizard_fill_template(lf_wizard_pick($templates['benefits_intro'] ?? [], $index), $vars);
	return '<!-- wp:paragraph --><p>' . esc_html($line1) . '</p><!-- /wp:paragraph -->' .
		'<!-- wp:paragraph --><p>' . esc_html($line2) . '</p><!-- /wp:paragraph -->';
}

function lf_wizard_apply_pb_overrides(array $config, array $overrides): array {
	if (empty($overrides) || empty($config['sections'])) {
		return $config;
	}
	foreach ($config['sections'] as $instance_id => $section) {
		$type = $section['type'] ?? '';
		if ($type === '' || empty($overrides[$type]) || empty($section['settings'])) {
			continue;
		}
		$config['sections'][$instance_id]['settings'] = array_merge($section['settings'], $overrides[$type]);
	}
	return $config;
}

function lf_wizard_seed_pb_config(int $post_id, string $context, array $data, array $niche, int $index, array $vars_extra = []): void {
	if (!function_exists('lf_pb_default_config')) {
		return;
	}
	$config = lf_pb_default_config($context);
	$templates = $context === 'service' ? lf_wizard_get_service_templates($niche) : lf_wizard_get_area_templates($niche);
	$vars = lf_wizard_template_vars($data, $vars_extra);
	$city = $vars['city'] ?? '';

	$hero_headlines = $templates['hero_headline'] ?? [];
	$trust_headings = $templates['trust_heading'] ?? [];
	if ($city === '') {
		$hero_headlines = ['Trusted {service} services', '{service} you can count on'];
		$trust_headings = ['Trusted by local homeowners'];
	}

	$overrides = [
		'hero' => [
			'hero_headline' => lf_wizard_fill_template(lf_wizard_pick($hero_headlines, $index), $vars),
			'hero_subheadline' => lf_wizard_fill_template(lf_wizard_pick($templates['hero_subheadline'] ?? [], $index), $vars),
		],
		'trust_bar' => [
			'trust_heading' => lf_wizard_fill_template(lf_wizard_pick($trust_headings, $index), $vars),
			'section_background' => 'dark',
			'trust_bar_layout' => 'brand_band',
		],
		'benefits' => [
			'section_heading' => lf_wizard_fill_template(lf_wizard_pick($templates['benefits_heading'] ?? [], $index), $vars),
			'section_intro' => lf_wizard_fill_template(lf_wizard_pick($templates['benefits_intro'] ?? [], $index), $vars),
			'benefits_items' => lf_wizard_fill_list(lf_wizard_pick_list($templates['benefits_items'] ?? [], $index), $vars),
		],
		'process' => [
			'section_heading' => lf_wizard_fill_template(lf_wizard_pick($templates['process_heading'] ?? [], $index), $vars),
			'section_intro' => lf_wizard_fill_template(lf_wizard_pick($templates['process_intro'] ?? [], $index), $vars),
			'process_steps' => lf_wizard_fill_list(lf_wizard_pick_list($templates['process_steps'] ?? [], $index), $vars),
		],
		'faq_accordion' => [
			'section_heading' => lf_wizard_fill_template(lf_wizard_pick($templates['faq_heading'] ?? [], $index), $vars),
			'section_intro' => lf_wizard_fill_template(lf_wizard_pick($templates['faq_intro'] ?? [], $index), $vars),
		],
		'cta' => [
			'cta_headline' => lf_wizard_fill_template(lf_wizard_pick($templates['cta_headline'] ?? [], $index), $vars),
			'cta_subheadline' => lf_wizard_fill_template(lf_wizard_pick($templates['cta_subheadline'] ?? [], $index), $vars),
		],
		'related_links' => [
			'section_heading' => lf_wizard_fill_template(lf_wizard_pick($templates['related_heading'] ?? [], $index), $vars),
			'section_intro' => lf_wizard_fill_template(lf_wizard_pick($templates['related_intro'] ?? [], $index), $vars),
			'related_links_mode' => 'services',
		],
	];

	if ($context === 'service') {
		$overrides['map_nap'] = [
			'section_heading' => __('Areas We Serve', 'leadsforward-core'),
			'section_intro' => __('Find us on the map and explore the neighborhoods we serve every day.', 'leadsforward-core'),
		];
	}
	if ($context === 'service_area') {
		$overrides['services_offered_here'] = [
			'section_heading' => lf_wizard_fill_template(lf_wizard_pick($templates['services_heading'] ?? [], $index), $vars),
			'section_intro' => __('Explore the services available in your area.', 'leadsforward-core'),
		];
		$overrides['nearby_areas'] = [
			'section_heading' => lf_wizard_fill_template(lf_wizard_pick($templates['nearby_heading'] ?? [], $index), $vars),
			'section_intro' => __('We also serve these nearby locations.', 'leadsforward-core'),
		];
	}

	$config = lf_wizard_apply_pb_overrides($config, $overrides);
	update_post_meta($post_id, LF_PB_META_KEY, $config);
}

function lf_wizard_build_sitemap_body(array $created_pages): string {
	$links = [];
	$titles = array_merge(
		lf_wizard_default_page_titles(),
		function_exists('lf_wizard_extended_page_titles') ? lf_wizard_extended_page_titles() : []
	);
	$order = function_exists('lf_wizard_sitemap_slug_order') ? lf_wizard_sitemap_slug_order($created_pages) : array_keys($created_pages);
	foreach ($order as $slug) {
		if (empty($created_pages[ $slug ])) {
			continue;
		}
		$label = $titles[ $slug ] ?? ucwords(str_replace(['-', '_'], ' ', (string) $slug));
		$url = get_permalink((int) $created_pages[ $slug ]);
		if ($url) {
			$links[] = '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
		}
	}

	$service_archive = get_post_type_archive_link('lf_service');
	if ($service_archive) {
		$links[] = '<li><a href="' . esc_url($service_archive) . '">' . esc_html__('Services Archive', 'leadsforward-core') . '</a></li>';
	}
	$area_archive = get_post_type_archive_link('lf_service_area');
	if ($area_archive) {
		$links[] = '<li><a href="' . esc_url($area_archive) . '">' . esc_html__('Service Areas Archive', 'leadsforward-core') . '</a></li>';
	}
	$project_archive = get_post_type_archive_link('lf_project');
	if ($project_archive) {
		$links[] = '<li><a href="' . esc_url($project_archive) . '">' . esc_html__('Projects Archive', 'leadsforward-core') . '</a></li>';
	}

	if (empty($links)) {
		return '';
	}
	return '<ul>' . implode('', $links) . '</ul>';
}

function lf_wizard_get_page_blueprints(array $data, array $niche, array $created_pages): array {
	$vars = lf_wizard_template_vars($data);
	$business = $vars['business'] ?? get_bloginfo('name');
	$city = $vars['city'] ?? '';
	$phone = $vars['phone'] ?? '';
	$email = $vars['email'] ?? '';
	$city_line = $city ? ' in ' . $city : '';
	$cta_headline = $business ? 'Get a free estimate from ' . $business : __('Get a free estimate', 'leadsforward-core');
	$contact_bits = array_filter([
		$phone ? 'Phone: ' . $phone : '',
		$email ? 'Email: ' . $email : '',
	]);
	$contact_line = $contact_bits ? 'Questions? ' . implode(' | ', $contact_bits) : '';
	$privacy_body = implode("\n", array_filter([
		$business ? $business . ' values your privacy and uses your information only to respond to requests and improve your experience.' : 'We value your privacy and use your information only to respond to requests and improve your experience.',
		'We do not sell your information and only share it when required to provide service or comply with the law.',
		$contact_line,
	]));
	$terms_body = implode("\n", array_filter([
		$business ? 'These terms govern your use of the ' . $business . ' website and services.' : 'These terms govern your use of this website and our services.',
		'By using this site, you agree to provide accurate information and to follow these terms.',
		$contact_line,
	]));

	return [
		'about-us' => [
			'order' => ['hero', 'content_image', 'benefits', 'image_content', 'faq_accordion', 'cta'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'About ' . $business,
					'hero_subheadline' => 'Local home-service professionals' . $city_line . ' focused on quality, communication, and a clean job site.',
				],
				'content_image' => [
					'section_heading' => 'Our story',
					'section_intro' => 'Built for homeowners who want clear pricing and reliable service.',
					'section_body' => 'We started ' . $business . ' to make home services simple and dependable. Our team shows up on time, keeps you informed, and treats your home with care from start to finish.',
				],
				'benefits' => [
					'section_heading' => 'Why homeowners choose us',
					'section_intro' => 'Clear communication, honest pricing, and consistent results.',
					'benefits_items' => 'Licensed and insured professionals' . "\n" . 'Upfront pricing before work starts' . "\n" . 'Respectful, clean crews',
				],
				'image_content' => [
					'section_heading' => 'How we work',
					'section_intro' => 'A straightforward process built to protect your property and deliver consistent results.',
					'section_body' => 'From the first inspection to the final walkthrough, we follow a clear, documented process so you always know what’s happening next. Expect careful staging, clean daily work areas, and communication that keeps surprises to a minimum.',
				],
				'faq_accordion' => [
					'section_heading' => 'About us FAQs',
					'section_intro' => 'Quick answers about our team, process, and what to expect.',
				],
				'cta' => [
					'cta_headline' => $cta_headline,
					'cta_subheadline' => 'Request a free estimate and get a clear next step.',
				],
			],
			'seo' => [
				'title' => $business ? 'About ' . $business . ($city ? ' | ' . $city : '') : 'About Us',
				'description' => 'Learn about our team, process, and what makes us the trusted local choice' . $city_line . '.',
			],
		],
		'why-choose-us' => [
			'order' => ['hero', 'benefits', 'content_image', 'image_content', 'faq_accordion', 'cta'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Why choose ' . $business,
					'hero_subheadline' => 'Clear communication, quality work, and a clean job site — done right from the first visit to the final walkthrough.',
				],
				'benefits' => [
					'section_heading' => 'What makes us the easy choice',
					'section_intro' => 'A process built for consistency, transparency, and results.',
					'benefits_items' => 'System-based installations' . "\n" . 'Refined project management' . "\n" . 'Premium material selection',
				],
				'content_image' => [
					'section_heading' => 'A better experience from start to finish',
					'section_intro' => 'The details that reduce disruption and keep quality high.',
					'section_body' => 'We plan the scope up front, protect your property, and keep you informed throughout the job. Expect clear scheduling, clean daily work areas, and a final walkthrough that answers every question before we wrap.',
				],
				'image_content' => [
					'section_heading' => 'Protection-first job sites',
					'section_intro' => 'Respectful crews, careful staging, and daily cleanup.\u00a0',
					'section_body' => 'From tarps and landscaping protection to debris control and magnet sweeps, we treat your property like it\u2019s our own. You\u2019ll know what\u2019s happening each day\u2014and what\u2019s next\u2014so the project stays smooth and predictable.',
				],
				'faq_accordion' => [
					'section_heading' => 'Why choose us FAQs',
					'section_intro' => 'Quick answers about how we work and what to expect.',
				],
				'cta' => [
					'cta_headline' => $cta_headline,
					'cta_subheadline' => 'Request a free estimate and get a clear plan.',
				],
			],
			'seo' => [
				'title' => $business ? 'Why Choose Us | ' . $business : 'Why Choose Us',
				'description' => 'See what makes our team the trusted local choice' . $city_line . ' — clear scopes, quality work, and reliable communication.',
			],
		],
		'our-services' => [
			'order' => ['hero', 'service_intro', 'content_image', 'faq_accordion', 'cta'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Services' . ($business ? ' by ' . $business : ''),
					'hero_subheadline' => 'Explore our most requested services and schedule fast, reliable help' . $city_line . '.',
				],
				'service_intro' => [
					'section_heading' => 'Service options',
					'section_intro' => 'Explore our core services with clear scopes and upfront expectations.',
				],
				'content_image' => [
					'section_heading' => 'How we deliver great results',
					'section_intro' => 'Clear communication and quality workmanship at every step.',
				],
				'faq_accordion' => [
					'section_heading' => 'Service FAQs',
					'section_intro' => 'Answers to common scheduling and service questions.',
				],
				'cta' => [
					'cta_headline' => $cta_headline,
					'cta_subheadline' => 'Fast response times and transparent pricing.',
				],
			],
			'seo' => [
				'title' => $business ? 'Services | ' . $business : 'Our Services',
				'description' => 'Browse our services and request a fast, free estimate' . $city_line . '.',
			],
		],
		'service-areas' => [
			'order' => ['hero', 'service_areas', 'faq_accordion', 'cta'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Service areas' . ($business ? ' for ' . $business : ''),
					'hero_subheadline' => 'See the neighborhoods and cities we serve' . $city_line . '.',
				],
				'service_areas' => [
					'section_heading' => 'Browse our service areas',
					'section_intro' => 'Search your city, filter by state, and view each area we currently serve.',
					'map_heading' => 'Service area map',
					'map_intro' => 'Map pins show the areas currently covered by our team.',
					'search_placeholder' => 'Search city or neighborhood',
					'filter_label' => 'Filter by state',
					'filter_all_label' => 'All areas',
					'no_results_text' => 'No service areas match your search yet. Clear filters to view all coverage.',
				],
				'faq_accordion' => [
					'section_heading' => 'Service area FAQs',
					'section_intro' => 'Quick answers about coverage and scheduling.',
				],
				'cta' => [
					'cta_headline' => $cta_headline,
					'cta_subheadline' => 'Schedule service anywhere we operate.',
				],
			],
			'seo' => [
				'title' => $business ? 'Service Areas | ' . $business : 'Service Areas',
				'description' => 'See all service areas we cover' . $city_line . ' and request service today.',
			],
		],
		'reviews' => [
			'order' => ['hero', 'trust_reviews', 'cta'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Customer reviews',
					'hero_subheadline' => 'Real feedback from local homeowners' . $city_line . '.',
				],
				'trust_reviews' => [
					'trust_heading' => 'What customers are saying',
					'trust_max_items' => 6,
				],
				'cta' => [
					'cta_headline' => $cta_headline,
					'cta_subheadline' => 'Join our list of happy customers.',
				],
			],
			'seo' => [
				'title' => $business ? 'Reviews | ' . $business : 'Reviews',
				'description' => 'Read verified reviews from homeowners' . $city_line . '.',
			],
		],
		'blog' => [
			'order' => ['hero', 'blog_posts', 'cta'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Home service tips & updates',
					'hero_subheadline' => 'Practical guidance, project checklists, and seasonal advice.',
				],
				'blog_posts' => [
					'section_heading' => 'Latest articles',
					'section_intro' => 'Helpful resources from our team.',
					'posts_per_page' => 6,
				],
				'cta' => [
					'cta_headline' => $cta_headline,
					'cta_subheadline' => 'Ready for a quote? We’re here to help.',
				],
			],
			'seo' => [
				'title' => $business ? 'Blog | ' . $business : 'Blog',
				'description' => 'Tips and updates from our local home-service team.',
			],
		],
		'sitemap' => [
			'order' => ['hero', 'sitemap_links'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Sitemap',
					'hero_subheadline' => 'Find every key page, service, and location on this site.',
				],
				'sitemap_links' => [
					'section_heading' => 'Quick links',
					'section_intro' => 'Browse the full site from one place.',
				],
			],
			'seo' => [
				'title' => $business ? 'Sitemap | ' . $business : 'Sitemap',
				'description' => 'Browse all pages, services, and service areas.',
			],
		],
		'contact' => [
			'order' => ['hero', 'map_nap', 'cta'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Contact ' . $business,
					'hero_subheadline' => 'Fast responses and clear next steps' . $city_line . '.',
				],
				'map_nap' => [
					'section_intent' => 'contact',
					'section_heading' => 'Get in touch',
					'section_intro' => 'Share a few details and we will reply with next steps.',
				],
				'cta' => [
					'cta_headline' => $cta_headline,
					'cta_subheadline' => 'Prefer a quick estimate? Start here.',
				],
			],
			'seo' => [
				'title' => $business ? 'Contact | ' . $business : 'Contact',
				'description' => 'Contact our team for fast scheduling and clear pricing' . $city_line . '.',
			],
		],
		'privacy-policy' => [
			'order' => ['hero', 'content'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Privacy policy',
					'hero_subheadline' => 'How we collect and protect your information.',
				],
			],
			'seo' => [
				'title' => $business ? 'Privacy Policy | ' . $business : 'Privacy Policy',
				'description' => 'Read how we collect, use, and protect your information.',
			],
		],
		'terms-of-service' => [
			'order' => ['hero', 'content'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Terms of service',
					'hero_subheadline' => 'Important details about using this site and our services.',
				],
			],
			'seo' => [
				'title' => $business ? 'Terms of Service | ' . $business : 'Terms of Service',
				'description' => 'Read the terms governing use of this site and our services.',
			],
		],
		'thank-you' => [
			'order' => ['hero', 'content'],
			'overrides' => [
				'hero' => [
					'hero_headline' => 'Thanks — we received your request',
					'hero_subheadline' => 'A local specialist will follow up soon with next steps.',
				],
			],
			'seo' => [
				'title' => $business ? 'Thank You | ' . $business : 'Thank You',
				'description' => 'Thanks for your request. We will follow up shortly.',
			],
		],
	];

	$leadgen_copy = function_exists('lf_leadgen_page_marketing_copy') ? lf_leadgen_page_marketing_copy($business, $city_line) : [];
	foreach ($leadgen_copy as $lg_slug => $copy) {
		if (!is_array($copy)) {
			continue;
		}
		$h = (string) ( $copy['hero_headline'] ?? '' );
		$s = (string) ( $copy['hero_subheadline'] ?? '' );
		$st = (string) ( $copy['seo_title'] ?? '' );
		$sd = (string) ( $copy['seo_description'] ?? '' );
		if ($lg_slug === 'our-work') {
			$blueprints[ $lg_slug ] = [
				'order' => ['hero', 'project_gallery', 'trust_reviews', 'cta'],
				'overrides' => [
					'hero' => [
						'hero_headline' => $h,
						'hero_subheadline' => $s,
					],
					'project_gallery' => [
						'section_heading' => __('Featured projects', 'leadsforward-core'),
						'section_intro' => __('A snapshot of recent work in the field.', 'leadsforward-core'),
					],
					'trust_reviews' => [
						'trust_heading' => __('What customers say', 'leadsforward-core'),
						'trust_max_items' => 6,
					],
					'cta' => [
						'cta_headline' => $cta_headline,
						'cta_subheadline' => __('Request pricing and availability.', 'leadsforward-core'),
					],
				],
				'seo' => [
					'title' => $st,
					'description' => $sd,
				],
			];
			continue;
		}
		$blueprints[ $lg_slug ] = [
			'order' => ['hero', 'content_image', 'faq_accordion', 'cta'],
			'overrides' => [
				'hero' => [
					'hero_headline' => $h,
					'hero_subheadline' => $s,
				],
				'content_image' => [
					'section_heading' => __('How we help', 'leadsforward-core'),
					'section_intro' => __('Straightforward recommendations and clear pricing paths.', 'leadsforward-core'),
					'section_body' => $contact_line !== ''
						? sprintf(__('Questions? Start with a free estimate. %s', 'leadsforward-core'), $contact_line)
						: __('Questions? Start with a free estimate and we will outline next steps.', 'leadsforward-core'),
				],
				'faq_accordion' => [
					'section_heading' => __('Common questions', 'leadsforward-core'),
					'section_intro' => __('Quick answers about scheduling and service.', 'leadsforward-core'),
				],
				'cta' => [
					'cta_headline' => $cta_headline,
					'cta_subheadline' => __('Tell us about your project — we respond quickly.', 'leadsforward-core'),
				],
			],
			'seo' => [
				'title' => $st,
				'description' => $sd,
			],
		];
	}

	$layout_profile = (string) ($niche['layout_profile'] ?? '');
	if ($layout_profile === 'project-heavy') {
		foreach (['about-us', 'our-services'] as $slug) {
			if (empty($blueprints[$slug])) {
				continue;
			}
			$order = $blueprints[$slug]['order'] ?? [];
			if (is_array($order) && !in_array('project_gallery', $order, true)) {
				$cta_pos = array_search('cta', $order, true);
				if ($cta_pos === false) {
					$order[] = 'project_gallery';
				} else {
					array_splice($order, $cta_pos, 0, ['project_gallery']);
				}
				$blueprints[$slug]['order'] = $order;
			}
			$blueprints[$slug]['overrides']['project_gallery'] = [
				'section_heading' => __('Our Projects', 'leadsforward-core'),
				'section_intro' => __('Explore recent transformations and finished work.', 'leadsforward-core'),
			];
		}
	}

	unset($blueprints['privacy-policy'], $blueprints['terms-of-service']);

	return $blueprints;
}

function lf_wizard_is_minimal_pb_config($config): bool {
	if (!is_array($config) || empty($config)) {
		return true;
	}
	$order = $config['order'] ?? [];
	$sections = $config['sections'] ?? [];
	if (!is_array($order) || empty($order) || !is_array($sections)) {
		return true;
	}
	$enabled_types = [];
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		if ($type !== '') {
			$enabled_types[$type] = true;
		}
	}
	if (count($enabled_types) <= 1) {
		return true;
	}
	if (count($enabled_types) === 1 && isset($enabled_types['hero'])) {
		return true;
	}
	if (count($enabled_types) <= 2) {
		$allowed_minimal = ['hero' => true, 'content' => true];
		foreach (array_keys($enabled_types) as $type) {
			if (!isset($allowed_minimal[$type])) {
				return false;
			}
		}
		return true;
	}
	return false;
}

function lf_wizard_seed_page_pb_config(int $post_id, string $slug, array $data, array $niche, array $created_pages): void {
	if (!function_exists('lf_pb_default_config')) {
		return;
	}
	$blueprints = lf_wizard_get_page_blueprints($data, $niche, $created_pages);
	if (empty($blueprints[$slug])) {
		return;
	}
	$blueprint = $blueprints[$slug];
	$order = $blueprint['order'] ?? [];
	$overrides = $blueprint['overrides'] ?? [];
	$seo = is_array($blueprint['seo'] ?? null) ? $blueprint['seo'] : ['title' => '', 'description' => ''];

	$sections = [];
	$counts = [];
	foreach ($order as $type) {
		$counts[$type] = ($counts[$type] ?? 0) + 1;
		$instance_id = lf_pb_instance_id($type, $counts[$type]);
		$sections[$instance_id] = [
			'type' => $type,
			'enabled' => true,
			'deletable' => false,
			'settings' => array_merge(lf_sections_defaults_for($type), $overrides[$type] ?? []),
		];
	}
	update_post_meta($post_id, LF_PB_META_KEY, ['order' => array_keys($sections), 'sections' => $sections, 'seo' => $seo]);
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
				$out[] = [
					'name' => $name,
					'state' => $item['state'] ?? '',
					'slug' => $item['slug'] ?? '',
				];
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
function lf_wizard_create_menus(array $created_pages, array $service_ids, array $area_ids, array $data = []): array {
	$log = ['created' => [], 'errors' => []];
	$home_id = $created_pages['home'] ?? null;
	$about_id = $created_pages['about-us'] ?? null;
	$why_choose_id = $created_pages['why-choose-us'] ?? null;
	$contact_id = $created_pages['contact'] ?? null;
	$reviews_id = $created_pages['reviews'] ?? null;
	$blog_id = $created_pages['blog'] ?? null;
	$financing_id = $created_pages['financing'] ?? null;
	$faq_page_id = $created_pages['faq'] ?? null;
	$our_work_id = $created_pages['our-work'] ?? null;
	$sitemap_id = $created_pages['sitemap'] ?? null;
	$privacy_id = $created_pages['privacy-policy'] ?? null;
	$terms_id = $created_pages['terms-of-service'] ?? null;
	$services_page_id = $created_pages['our-services'] ?? null;
	$areas_page_id = $created_pages['service-areas'] ?? null;

	$service_children = [];
	if (!empty($service_ids)) {
		$services = get_posts([
			'post_type' => 'lf_service',
			'post_status' => 'publish',
			'posts_per_page' => 50,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
			'no_found_rows' => true,
			'include' => array_map('absint', $service_ids),
		]);
		foreach ($services as $service) {
			$service_children[] = [
				'type' => 'post_type',
				'object' => 'lf_service',
				'object_id' => $service->ID,
			];
		}
	}
	$area_children = [];
	if (!empty($area_ids)) {
		$areas = get_posts([
			'post_type' => 'lf_service_area',
			'post_status' => 'publish',
			'posts_per_page' => 50,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
			'no_found_rows' => true,
			'include' => array_map('absint', $area_ids),
		]);
		foreach ($areas as $area) {
			$area_children[] = [
				'type' => 'post_type',
				'object' => 'lf_service_area',
				'object_id' => $area->ID,
			];
		}
	}

	$header_items = [];
	if ($home_id) $header_items[] = ['type' => 'page', 'object_id' => $home_id];
	if ($services_page_id) {
		$header_items[] = [
			'type' => 'page',
			'object_id' => $services_page_id,
			'classes' => ['lf-menu-group-parent', 'lf-menu-services-parent'],
			'children' => $service_children,
		];
		if (!empty($service_children)) {
			$header_items[count($header_items) - 1]['children'][] = [
				'type' => 'custom',
				'url' => '#',
				'title' => '',
				'classes' => 'lf-submenu-divider',
			];
		}
		$header_items[count($header_items) - 1]['children'][] = [
			'type' => 'page',
			'object_id' => $services_page_id,
			'classes' => 'lf-submenu-all-link',
			'title' => __('All Services', 'leadsforward-core'),
		];
	} else {
		$header_items[] = [
			'type' => 'custom',
			'url' => get_post_type_archive_link('lf_service'),
			'title' => __('Services', 'leadsforward-core'),
			'classes' => ['lf-menu-group-parent', 'lf-menu-services-parent'],
			'children' => $service_children,
		];
		if (!empty($service_children)) {
			$header_items[count($header_items) - 1]['children'][] = [
				'type' => 'custom',
				'url' => '#',
				'title' => '',
				'classes' => 'lf-submenu-divider',
			];
		}
		$header_items[count($header_items) - 1]['children'][] = [
			'type' => 'custom',
			'url' => get_post_type_archive_link('lf_service'),
			'title' => __('All Services', 'leadsforward-core'),
			'classes' => 'lf-submenu-all-link',
		];
	}
	if ($areas_page_id) {
		$header_items[] = [
			'type' => 'page',
			'object_id' => $areas_page_id,
			'classes' => ['lf-menu-group-parent', 'lf-menu-areas-parent'],
			'children' => $area_children,
		];
		if (!empty($area_children)) {
			$header_items[count($header_items) - 1]['children'][] = [
				'type' => 'custom',
				'url' => '#',
				'title' => '',
				'classes' => 'lf-submenu-divider',
			];
		}
		$header_items[count($header_items) - 1]['children'][] = [
			'type' => 'page',
			'object_id' => $areas_page_id,
			'classes' => 'lf-submenu-all-link',
			'title' => __('All Service Areas', 'leadsforward-core'),
		];
	} else {
		$header_items[] = [
			'type' => 'custom',
			'url' => get_post_type_archive_link('lf_service_area'),
			'title' => __('Service Areas', 'leadsforward-core'),
			'classes' => ['lf-menu-group-parent', 'lf-menu-areas-parent'],
			'children' => $area_children,
		];
		if (!empty($area_children)) {
			$header_items[count($header_items) - 1]['children'][] = [
				'type' => 'custom',
				'url' => '#',
				'title' => '',
				'classes' => 'lf-submenu-divider',
			];
		}
		$header_items[count($header_items) - 1]['children'][] = [
			'type' => 'custom',
			'url' => get_post_type_archive_link('lf_service_area'),
			'title' => __('All Service Areas', 'leadsforward-core'),
			'classes' => 'lf-submenu-all-link',
		];
	}
	if ($reviews_id) $header_items[] = ['type' => 'page', 'object_id' => $reviews_id];

	$more_children = [];
	if ($about_id) $more_children[] = ['type' => 'page', 'object_id' => $about_id];
	if ($why_choose_id) $more_children[] = ['type' => 'page', 'object_id' => $why_choose_id];
	if ($financing_id) $more_children[] = ['type' => 'page', 'object_id' => $financing_id];
	if ($faq_page_id) $more_children[] = ['type' => 'page', 'object_id' => $faq_page_id];
	if ($our_work_id) $more_children[] = ['type' => 'page', 'object_id' => $our_work_id];
	if ($blog_id) $more_children[] = ['type' => 'page', 'object_id' => $blog_id];
	$project_archive = get_post_type_archive_link('lf_project');
	if ($project_archive) $more_children[] = ['type' => 'custom', 'url' => $project_archive, 'title' => __('Projects', 'leadsforward-core')];
	if ($contact_id) $more_children[] = ['type' => 'page', 'object_id' => $contact_id];
	if (!empty($more_children)) {
		$header_items[] = [
			'type' => 'custom',
			'url' => '#',
			'title' => __('More', 'leadsforward-core'),
			'classes' => 'lf-menu-more',
			'children' => $more_children,
		];
	}
	$phone_raw = (string) ($data['business_phone'] ?? (function_exists('lf_get_option') ? lf_get_option('lf_business_phone', 'option') : ''));
	$phone_href = $phone_raw !== '' ? 'tel:' . preg_replace('/\s+/', '', $phone_raw) : '#';
	$header_items[] = [
		'type' => 'custom',
		'url' => $phone_href,
		'title' => __('Call Now', 'leadsforward-core'),
		'classes' => 'lf-menu-call',
	];
	$header_items[] = [
		'type' => 'custom',
		'url' => '#',
		'title' => __('Free Estimate', 'leadsforward-core'),
		'classes' => 'lf-menu-cta',
	];

	$footer_items = [];
	if ($home_id) $footer_items[] = ['type' => 'page', 'object_id' => $home_id];
	if ($contact_id) $footer_items[] = ['type' => 'page', 'object_id' => $contact_id];
	if ($reviews_id) $footer_items[] = ['type' => 'page', 'object_id' => $reviews_id];
	if ($financing_id) $footer_items[] = ['type' => 'page', 'object_id' => $financing_id];
	if ($blog_id) $footer_items[] = ['type' => 'page', 'object_id' => $blog_id];
	if ($project_archive) $footer_items[] = ['type' => 'custom', 'url' => $project_archive, 'title' => __('Projects', 'leadsforward-core')];
	if ($sitemap_id) $footer_items[] = ['type' => 'page', 'object_id' => $sitemap_id];
	if ($privacy_id) $footer_items[] = ['type' => 'page', 'object_id' => $privacy_id];
	if ($terms_id) $footer_items[] = ['type' => 'page', 'object_id' => $terms_id];
	if ($services_page_id) {
		$footer_items[] = ['type' => 'page', 'object_id' => $services_page_id];
	} else {
		$footer_items[] = ['type' => 'custom', 'url' => get_post_type_archive_link('lf_service'), 'title' => __('Services', 'leadsforward-core')];
	}
	if ($areas_page_id) {
		$footer_items[] = ['type' => 'page', 'object_id' => $areas_page_id];
	} else {
		$footer_items[] = ['type' => 'custom', 'url' => get_post_type_archive_link('lf_service_area'), 'title' => __('Service Areas', 'leadsforward-core')];
	}

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
		$parent_id = 0;
		$classes = '';
		if (!empty($item['classes'])) {
			$classes = is_array($item['classes']) ? implode(' ', $item['classes']) : (string) $item['classes'];
		}
		if ($item['type'] === 'page' && !empty($item['object_id'])) {
			$parent_id = wp_update_nav_menu_item($menu_id, 0, [
				'menu-item-title'     => get_the_title($item['object_id']),
				'menu-item-url'       => get_permalink($item['object_id']),
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $item['object_id'],
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $position++,
				'menu-item-classes'   => $classes,
			]);
		} elseif ($item['type'] === 'custom' && !empty($item['url'])) {
			$parent_id = wp_update_nav_menu_item($menu_id, 0, [
				'menu-item-title'    => $item['title'] ?? '',
				'menu-item-url'      => $item['url'],
				'menu-item-type'     => 'custom',
				'menu-item-status'   => 'publish',
				'menu-item-position' => $position++,
				'menu-item-classes'  => $classes,
			]);
		} elseif ($item['type'] === 'post_type' && !empty($item['object_id']) && !empty($item['object'])) {
			$parent_id = wp_update_nav_menu_item($menu_id, 0, [
				'menu-item-title'     => get_the_title($item['object_id']),
				'menu-item-url'       => get_permalink($item['object_id']),
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => $item['object'],
				'menu-item-object-id' => $item['object_id'],
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $position++,
				'menu-item-classes'   => $classes,
			]);
		}
		if (!empty($item['children']) && is_array($item['children']) && $parent_id && !is_wp_error($parent_id)) {
			foreach ($item['children'] as $child) {
				$child_classes = '';
				if (!empty($child['classes'])) {
					$child_classes = is_array($child['classes']) ? implode(' ', $child['classes']) : (string) $child['classes'];
				}
				$child_type = (string) ($child['type'] ?? '');
				if ($child_type === 'post_type' && !empty($child['object_id']) && !empty($child['object'])) {
					wp_update_nav_menu_item($menu_id, 0, [
						'menu-item-title'     => get_the_title($child['object_id']),
						'menu-item-url'       => get_permalink($child['object_id']),
						'menu-item-type'      => 'post_type',
						'menu-item-object'    => $child['object'],
						'menu-item-object-id' => $child['object_id'],
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => $parent_id,
						'menu-item-position'  => $position++,
						'menu-item-classes'   => $child_classes,
					]);
					continue;
				}
				if ($child_type === 'page' && !empty($child['object_id'])) {
					wp_update_nav_menu_item($menu_id, 0, [
						'menu-item-title'     => $child['title'] ?? get_the_title($child['object_id']),
						'menu-item-url'       => get_permalink($child['object_id']),
						'menu-item-type'      => 'post_type',
						'menu-item-object'    => 'page',
						'menu-item-object-id' => $child['object_id'],
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => $parent_id,
						'menu-item-position'  => $position++,
						'menu-item-classes'   => $child_classes,
					]);
					continue;
				}
				if ($child_type === 'custom' && isset($child['url'])) {
					wp_update_nav_menu_item($menu_id, 0, [
						'menu-item-title'     => $child['title'] ?? '',
						'menu-item-url'       => (string) $child['url'],
						'menu-item-type'      => 'custom',
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => $parent_id,
						'menu-item-position'  => $position++,
						'menu-item-classes'   => $child_classes,
					]);
				}
			}
		}
	}
	$locations = get_theme_mod('nav_menu_locations') ?: [];
	$locations[$location] = $menu_id;
	set_theme_mod('nav_menu_locations', $locations);
	return $menu_id;
}
