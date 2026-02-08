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
	$new_services = [];
	foreach ($service_names as $name) {
		$index = count($created_services);
		$slug = sanitize_title($name);
		$exists = get_page_by_path($slug, OBJECT, 'lf_service');
		if ($exists) {
			$created_services[] = $exists->ID;
			continue;
		}
		$sid = wp_insert_post([
			'post_title'   => $name,
			'post_name'   => $slug,
			'post_content' => lf_wizard_service_placeholder_content($name, $data, $index, $niche),
			'post_status'  => 'publish',
			'post_type'    => 'lf_service',
			'post_author'  => get_current_user_id(),
		], true);
		if (!is_wp_error($sid)) {
			$created_services[] = $sid;
			$new_services[] = ['id' => $sid, 'name' => $name, 'index' => $index];
			$log['created']['services'][] = ['title' => $name, 'id' => $sid];
		} else {
			$log['errors'][] = $sid->get_error_message();
		}
	}

	// 3. Service area CPT entries (from wizard data)
	$area_input = $data['service_areas'] ?? [];
	$areas_parsed = lf_wizard_parse_service_areas($area_input);
	$created_areas = [];
	$new_areas = [];
	foreach ($areas_parsed as $area) {
		$index = count($created_areas);
		$slug = $area['state'] ? sanitize_title($area['name'] . '-' . $area['state']) : sanitize_title($area['name']);
		$exists = get_page_by_path($slug, OBJECT, 'lf_service_area');
		if ($exists) {
			$created_areas[] = $exists->ID;
			continue;
		}
		$aid = wp_insert_post([
			'post_title'   => $area['name'],
			'post_name'   => sanitize_title($area['name']),
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
			lf_update_business_info_value('lf_business_name', $data['business_name'] ?? '');
			lf_update_business_info_value('lf_business_phone', $data['business_phone'] ?? '');
			lf_update_business_info_value('lf_business_email', $data['business_email'] ?? '');
			lf_update_business_info_value('lf_business_address', $data['business_address'] ?? '');
			if (!empty($data['business_hours'])) {
				lf_update_business_info_value('lf_business_hours', $data['business_hours']);
			}
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

	// 7. Menus
	$menu_result = lf_wizard_create_menus($created_pages, $created_services, $created_areas);
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
	return [
		'success' => $success,
		'message' => $success ? __('Site setup complete.', 'leadsforward-core') : __('Setup finished with some errors.', 'leadsforward-core'),
		'created' => $log['created'],
		'ids'     => $ids,
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

function lf_wizard_primary_city(array $data): string {
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
	$vars = array_merge([
		'business' => $business,
		'city'     => $city,
	], $extra);
	return $vars;
}

function lf_wizard_pick(array $items, int $index): string {
	if (empty($items)) {
		return '';
	}
	$pos = $index % count($items);
	return (string) $items[$pos];
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
	$line1 = lf_wizard_fill_template(lf_wizard_pick($templates['hero_subheadline'], $index), $vars);
	$line2 = lf_wizard_fill_template(lf_wizard_pick($templates['benefits_intro'], $index), $vars);
	return '<!-- wp:paragraph --><p>' . esc_html($line1) . '</p><!-- /wp:paragraph -->' .
		'<!-- wp:paragraph --><p>' . esc_html($line2) . '</p><!-- /wp:paragraph -->';
}

function lf_wizard_service_area_placeholder_content(array $area, array $data, int $index = 0, array $niche = []): string {
	$name = $area['name'] ?? '';
	$state = $area['state'] ?? '';
	$loc = $state ? $name . ', ' . $state : $name;
	$templates = lf_wizard_get_area_templates($niche);
	$vars = lf_wizard_template_vars($data, ['area' => $loc]);
	$line1 = lf_wizard_fill_template(lf_wizard_pick($templates['hero_subheadline'], $index), $vars);
	$line2 = lf_wizard_fill_template(lf_wizard_pick($templates['benefits_intro'], $index), $vars);
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
		],
		'benefits' => [
			'section_heading' => lf_wizard_fill_template(lf_wizard_pick($templates['benefits_heading'] ?? [], $index), $vars),
			'section_intro' => lf_wizard_fill_template(lf_wizard_pick($templates['benefits_intro'] ?? [], $index), $vars),
			'benefits_items' => lf_wizard_fill_list(lf_wizard_pick($templates['benefits_items'] ?? [], $index) ?: [], $vars),
		],
		'process' => [
			'section_heading' => lf_wizard_fill_template(lf_wizard_pick($templates['process_heading'] ?? [], $index), $vars),
			'section_intro' => lf_wizard_fill_template(lf_wizard_pick($templates['process_intro'] ?? [], $index), $vars),
			'process_steps' => lf_wizard_fill_list(lf_wizard_pick($templates['process_steps'] ?? [], $index) ?: [], $vars),
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
