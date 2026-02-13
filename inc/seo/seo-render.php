<?php
/**
 * Frontend SEO rendering: title, meta, canonical, robots, social, schema.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('wp', 'lf_seo_disable_legacy_schema', 1);
add_action('wp_head', 'lf_seo_render_header_scripts', 1);
add_action('wp_head', 'lf_seo_render_head', 2);
add_filter('pre_get_document_title', 'lf_seo_filter_document_title', 20);
add_action('wp_head', 'lf_seo_render_schema_json_ld', 5);
add_action('wp_head', 'lf_geo_meta', 3);
add_action('wp_body_open', 'lf_seo_render_body_open_scripts', 1);
add_action('wp_footer', 'lf_seo_render_footer_scripts', 5);

function lf_seo_disable_legacy_schema(): void {
	if (has_action('wp_head', 'lf_output_schema_json_ld')) {
		remove_action('wp_head', 'lf_output_schema_json_ld', 5);
	}
}

function lf_seo_filter_document_title(string $title): string {
	$custom = lf_seo_build_title();
	return $custom !== '' ? $custom : $title;
}

function lf_seo_render_head(): void {
	$title = lf_seo_build_title();
	$description = lf_seo_build_description();
	$canonical = lf_seo_get_canonical_url();
	$robots = lf_seo_get_robots_content();
	$og = lf_seo_get_social_meta();

	if ($description !== '') {
		echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
	}
	if ($canonical !== '') {
		echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
	}
	if ($robots !== '') {
		echo '<meta name="robots" content="' . esc_attr($robots) . '" />' . "\n";
	}
	foreach ($og as $tag) {
		echo $tag . "\n";
	}
}

function lf_seo_render_header_scripts(): void {
	$scripts = lf_seo_get_context_script('header');
	if ($scripts !== '') {
		echo "\n" . $scripts . "\n";
	}
}

function lf_seo_render_body_open_scripts(): void {
	$scripts = lf_seo_get_global_script('body_open');
	if ($scripts !== '') {
		echo "\n" . $scripts . "\n";
	}
}

function lf_seo_render_footer_scripts(): void {
	$scripts = lf_seo_get_context_script('footer');
	if ($scripts !== '') {
		echo "\n" . $scripts . "\n";
	}
}

function lf_seo_build_title(): string {
	$post_id = lf_seo_get_context_post_id();
	$custom = $post_id ? (string) get_post_meta($post_id, '_lf_seo_meta_title', true) : '';
	$custom = trim($custom);
	if ($custom === '' && $post_id) {
		$pb = lf_seo_get_pb_seo_overrides($post_id);
		$custom = (string) ($pb['title'] ?? '');
	}
	$settings = function_exists('lf_seo_get_settings') ? lf_seo_get_settings() : [];
	$template = (string) ($settings['general']['title_template'] ?? '');
	$separator = (string) ($settings['general']['title_separator'] ?? '|');
	$append_brand = !empty($settings['general']['append_brand']);
	$vars = lf_seo_get_template_vars($post_id);

	$title = $custom !== '' ? $custom : lf_seo_apply_template($template, $vars);
	$brand = $vars['{{brand}}'] ?? '';

	if ($append_brand && $brand !== '' && stripos($title, $brand) === false) {
		$sep = $separator !== '' ? ' ' . $separator . ' ' : ' ';
		$title = rtrim($title) . $sep . $brand;
	}
	return trim($title);
}

function lf_seo_build_description(): string {
	$post_id = lf_seo_get_context_post_id();
	$custom = $post_id ? (string) get_post_meta($post_id, '_lf_seo_meta_description', true) : '';
	$custom = trim($custom);
	if ($custom !== '') {
		return $custom;
	}
	if ($post_id) {
		$pb = lf_seo_get_pb_seo_overrides($post_id);
		if (!empty($pb['description'])) {
			return (string) $pb['description'];
		}
	}
	$settings = function_exists('lf_seo_get_settings') ? lf_seo_get_settings() : [];
	$template = (string) ($settings['general']['meta_description_template'] ?? '');
	$vars = lf_seo_get_template_vars($post_id);
	$desc = $template !== '' ? lf_seo_apply_template($template, $vars) : '';
	if ($desc !== '') {
		return trim($desc);
	}
	return lf_seo_get_fallback_description($post_id);
}

function lf_seo_get_canonical_url(): string {
	$post_id = lf_seo_get_context_post_id();
	$custom = $post_id ? (string) get_post_meta($post_id, '_lf_seo_canonical_url', true) : '';
	$custom = trim($custom);
	if ($custom !== '') {
		return $custom;
	}
	if (is_singular()) {
		return (string) get_permalink();
	}
	if (is_home() && !is_front_page()) {
		return (string) get_permalink(get_option('page_for_posts'));
	}
	if (is_post_type_archive()) {
		return (string) get_post_type_archive_link(get_post_type());
	}
	if (is_front_page()) {
		return (string) home_url('/');
	}
	if (is_search() || is_404()) {
		return '';
	}
	if (is_paged()) {
		return (string) get_pagenum_link(get_query_var('paged') ?: 1);
	}
	return (string) home_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
}

function lf_seo_get_robots_content(): string {
	$settings = function_exists('lf_seo_get_settings') ? lf_seo_get_settings() : [];
	$noindex = false;
	$nofollow = false;
	$post_id = lf_seo_get_context_post_id();

	if (!empty($settings['indexing']['noindex_search']) && is_search()) {
		$noindex = true;
	}
	if (!empty($settings['indexing']['noindex_archives']) && is_archive()) {
		$noindex = true;
	}
	if (!empty($settings['indexing']['noindex_paginated']) && is_paged()) {
		$noindex = true;
	}
	if ($post_id) {
		$noindex = $noindex || (string) get_post_meta($post_id, '_lf_seo_noindex', true) === '1';
		$nofollow = $nofollow || (string) get_post_meta($post_id, '_lf_seo_nofollow', true) === '1';
		$pb = lf_seo_get_pb_seo_overrides($post_id);
		if (!empty($pb['noindex'])) {
			$noindex = true;
		}
	}
	if ($noindex && $nofollow) {
		return 'noindex, nofollow';
	}
	if ($noindex) {
		return 'noindex, follow';
	}
	if ($nofollow) {
		return 'index, nofollow';
	}
	return 'index, follow';
}

function lf_seo_get_social_meta(): array {
	$settings = function_exists('lf_seo_get_settings') ? lf_seo_get_settings() : [];
	$title = lf_seo_build_title();
	$description = lf_seo_build_description();
	$url = lf_seo_get_canonical_url();
	$og_image = lf_seo_get_og_image_url();
	$type = is_singular('post') ? 'article' : 'website';
	$tags = [];
	if (!empty($settings['social']['facebook_app_id'])) {
		$tags[] = '<meta property="fb:app_id" content="' . esc_attr((string) $settings['social']['facebook_app_id']) . '" />';
	}
	if ($title !== '') {
		$tags[] = '<meta property="og:title" content="' . esc_attr($title) . '" />';
	}
	if ($description !== '') {
		$tags[] = '<meta property="og:description" content="' . esc_attr($description) . '" />';
	}
	if ($url !== '') {
		$tags[] = '<meta property="og:url" content="' . esc_url($url) . '" />';
	}
	$tags[] = '<meta property="og:type" content="' . esc_attr($type) . '" />';
	if ($og_image !== '') {
		$tags[] = '<meta property="og:image" content="' . esc_url($og_image) . '" />';
	}
	$brand = lf_seo_get_brand_name();
	if ($brand !== '') {
		$tags[] = '<meta property="og:site_name" content="' . esc_attr($brand) . '" />';
	}
	$card = (string) ($settings['social']['twitter_card'] ?? 'summary_large_image');
	$card = in_array($card, ['summary', 'summary_large_image'], true) ? $card : 'summary_large_image';
	$tags[] = '<meta name="twitter:card" content="' . esc_attr($card) . '" />';
	if ($title !== '') {
		$tags[] = '<meta name="twitter:title" content="' . esc_attr($title) . '" />';
	}
	if ($description !== '') {
		$tags[] = '<meta name="twitter:description" content="' . esc_attr($description) . '" />';
	}
	if ($og_image !== '') {
		$tags[] = '<meta name="twitter:image" content="' . esc_url($og_image) . '" />';
	}
	return $tags;
}

function lf_seo_render_schema_json_ld(): void {
	if (!function_exists('lf_seo_get_settings')) {
		return;
	}
	$settings = lf_seo_get_settings();
	$scripts = [];

	if (!empty($settings['schema']['enable_local_business']) && function_exists('lf_build_local_business_schema')) {
		$local = lf_build_local_business_schema();
		if (!empty($local)) {
			$scripts[] = $local;
		}
	}
	if (function_exists('lf_build_organization_schema')) {
		$org = lf_build_organization_schema();
		if (!empty($org)) {
			$scripts[] = $org;
		}
	}
	if (function_exists('lf_build_website_schema')) {
		$site = lf_build_website_schema();
		if (!empty($site)) {
			$scripts[] = $site;
		}
	}
	if (function_exists('lf_build_breadcrumb_schema')) {
		$crumbs = lf_build_breadcrumb_schema();
		if (!empty($crumbs)) {
			$scripts[] = $crumbs;
		}
	}
	if (!empty($settings['schema']['enable_service']) && is_singular('lf_service') && function_exists('lf_build_service_schema')) {
		$service = lf_build_service_schema();
		if (!empty($service)) {
			$scripts[] = $service;
		}
	}
	if (is_singular('lf_service_area') && function_exists('lf_build_service_area_schema')) {
		$area = lf_build_service_area_schema();
		if (!empty($area)) {
			$scripts[] = $area;
		}
	}
	if (function_exists('lf_build_faq_page_schema')) {
		$faq = lf_build_faq_page_schema();
		if (!empty($faq)) {
			$scripts[] = $faq;
		}
	}
	if (function_exists('lf_build_review_schema')) {
		$review = lf_build_review_schema();
		if (!empty($review)) {
			$scripts[] = $review;
		}
	}

	foreach ($scripts as $json) {
		echo '<script type="application/ld+json">' . "\n" . wp_json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n" . '</script>' . "\n";
	}
}

function lf_seo_get_context_post_id(): int {
	if (is_singular()) {
		return (int) get_queried_object_id();
	}
	if (is_home() && !is_front_page()) {
		return (int) get_option('page_for_posts');
	}
	return 0;
}

function lf_seo_get_post_scripts(int $post_id): array {
	$scripts = get_post_meta($post_id, '_lf_seo_scripts', true);
	if (!is_array($scripts)) {
		return [];
	}
	return $scripts;
}

function lf_seo_get_global_script(string $key): string {
	if (!function_exists('lf_seo_get_settings')) {
		return '';
	}
	$settings = lf_seo_get_settings();
	$scripts = is_array($settings['scripts'] ?? null) ? $settings['scripts'] : [];
	$value = (string) ($scripts[$key] ?? '');
	return trim($value);
}

function lf_seo_get_context_script(string $key): string {
	$post_id = lf_seo_get_context_post_id();
	if ($post_id > 0) {
		$per_page = lf_seo_get_post_scripts($post_id);
		$override = trim((string) ($per_page[$key] ?? ''));
		if ($override !== '') {
			return $override;
		}
	}
	return lf_seo_get_global_script($key);
}

function lf_seo_get_template_vars(int $post_id = 0): array {
	$brand = lf_seo_get_brand_name();
	$city = lf_seo_get_city_name();
	$page_title = lf_seo_get_page_title($post_id);
	$primary = lf_seo_get_primary_keyword_for_context($post_id);
	return [
		'{{page_title}}' => $page_title,
		'{{city}}' => $city,
		'{{brand}}' => $brand,
		'{{primary_keyword}}' => $primary,
	];
}

function lf_seo_get_page_title(int $post_id = 0): string {
	if ($post_id > 0) {
		$title = get_the_title($post_id);
		return is_string($title) ? $title : '';
	}
	if (is_archive()) {
		return (string) get_the_archive_title();
	}
	return (string) get_bloginfo('name');
}

function lf_seo_get_brand_name(): string {
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$name = (string) ($entity['name'] ?? '');
		if ($name !== '') {
			return $name;
		}
	}
	return (string) get_bloginfo('name');
}

function lf_seo_get_city_name(): string {
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$city = (string) ($entity['address_parts']['city'] ?? '');
		if ($city !== '') {
			return $city;
		}
	}
	return '';
}

function lf_seo_get_primary_keyword_for_context(int $post_id = 0): string {
	if (is_front_page()) {
		$map = function_exists('lf_seo_get_keyword_map') ? lf_seo_get_keyword_map() : [];
		$primary = is_array($map) ? (string) ($map['primary']['homepage'] ?? '') : '';
		return trim($primary);
	}
	if ($post_id > 0) {
		return trim((string) get_post_meta($post_id, '_lf_seo_primary_keyword', true));
	}
	return '';
}

function lf_seo_get_fallback_description(int $post_id = 0): string {
	if (is_front_page()) {
		$hero = lf_seo_get_homepage_hero_subheadline();
		if ($hero !== '') {
			return $hero;
		}
	}
	if ($post_id > 0) {
		$sub = lf_seo_get_pb_hero_subheadline($post_id);
		if ($sub !== '') {
			return $sub;
		}
		$excerpt = (string) get_post_field('post_excerpt', $post_id);
		if ($excerpt !== '') {
			return wp_trim_words($excerpt, 24, '...');
		}
		$content = (string) get_post_field('post_content', $post_id);
		if ($content !== '') {
			return wp_trim_words(wp_strip_all_tags($content), 24, '...');
		}
	}
	return '';
}

function lf_seo_get_pb_hero_subheadline(int $post_id): string {
	$config = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (!is_array($config)) {
		return '';
	}
	$order = $config['order'] ?? [];
	$sections = $config['sections'] ?? [];
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!$section || ($section['type'] ?? '') !== 'hero') {
			continue;
		}
		$settings = $section['settings'] ?? [];
		$sub = $settings['hero_subheadline'] ?? '';
		return is_string($sub) ? $sub : '';
	}
	return '';
}

function lf_seo_get_pb_seo_overrides(int $post_id): array {
	$config = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (!is_array($config)) {
		return ['title' => '', 'description' => '', 'noindex' => false];
	}
	$seo = is_array($config['seo'] ?? null) ? $config['seo'] : [];
	return [
		'title' => sanitize_text_field((string) ($seo['title'] ?? '')),
		'description' => sanitize_textarea_field((string) ($seo['description'] ?? '')),
		'noindex' => !empty($seo['noindex']),
	];
}

function lf_seo_get_homepage_hero_subheadline(): string {
	if (!function_exists('lf_get_homepage_section_config')) {
		return '';
	}
	$config = lf_get_homepage_section_config();
	$sub = $config['hero']['hero_subheadline'] ?? '';
	return is_string($sub) ? $sub : '';
}

function lf_seo_apply_template(string $template, array $vars): string {
	if ($template === '') {
		return '';
	}
	return strtr($template, $vars);
}

function lf_seo_get_og_image_url(): string {
	$post_id = lf_seo_get_context_post_id();
	if ($post_id) {
		$override_id = (int) get_post_meta($post_id, '_lf_seo_og_image_id', true);
		if ($override_id) {
			$url = wp_get_attachment_image_url($override_id, 'large');
			if ($url) {
				return $url;
			}
		}
	}
	if (function_exists('lf_seo_get_settings')) {
		$settings = lf_seo_get_settings();
		$og_id = (int) ($settings['social']['default_og_image_id'] ?? 0);
		if ($og_id) {
			$url = wp_get_attachment_image_url($og_id, 'large');
			if ($url) {
				return $url;
			}
		}
	}
	return '';
}

function lf_nap_data(): array {
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		return [
			'name'    => (string) ($entity['name'] ?? ''),
			'address' => (string) ($entity['address'] ?? ''),
			'phone'   => (string) ($entity['phone_display'] ?? ''),
			'email'   => (string) ($entity['email'] ?? ''),
		];
	}
	return [
		'name'    => lf_get_option('lf_business_name', 'option'),
		'address' => lf_get_option('lf_business_address', 'option'),
		'phone'   => lf_get_option('lf_business_phone', 'option'),
		'email'   => lf_get_option('lf_business_email', 'option'),
	];
}

function lf_nap_plain(string $separator = "\n"): string {
	$nap = lf_nap_data();
	$parts = array_filter([$nap['name'], $nap['address'], $nap['phone'], $nap['email']]);
	return implode($separator, $parts);
}

function lf_nap_html(array $args = []): string {
	$nap = lf_nap_data();
	$show_phone_link = $args['phone_link'] ?? true;
	$show_email_link = $args['email_link'] ?? true;
	$class = $args['class'] ?? 'lf-nap';
	$parts = [];
	if (!empty($nap['name'])) {
		$parts[] = '<span class="' . esc_attr($class) . '__name">' . esc_html($nap['name']) . '</span>';
	}
	if (!empty($nap['address'])) {
		$parts[] = '<span class="' . esc_attr($class) . '__address">' . nl2br(esc_html($nap['address'])) . '</span>';
	}
	if (!empty($nap['phone'])) {
		$tel = preg_replace('/\s+/', '', $nap['phone']);
		if ($show_phone_link && $tel) {
			$parts[] = '<a href="tel:' . esc_attr($tel) . '" class="' . esc_attr($class) . '__phone">' . esc_html($nap['phone']) . '</a>';
		} else {
			$parts[] = '<span class="' . esc_attr($class) . '__phone">' . esc_html($nap['phone']) . '</span>';
		}
	}
	if (!empty($nap['email'])) {
		if ($show_email_link) {
			$parts[] = '<a href="mailto:' . esc_attr($nap['email']) . '" class="' . esc_attr($class) . '__email">' . esc_html($nap['email']) . '</a>';
		} else {
			$parts[] = '<span class="' . esc_attr($class) . '__email">' . esc_html($nap['email']) . '</span>';
		}
	}
	if (empty($parts)) {
		return '';
	}
	return '<address class="' . esc_attr($class) . '">' . implode(' ', $parts) . '</address>';
}

function lf_geo_data(?int $context_post_id = null): ?array {
	if ($context_post_id) {
		$geo = function_exists('get_field') ? get_field('lf_service_area_geo', $context_post_id) : null;
		if (is_array($geo) && isset($geo['lat'], $geo['lng']) && $geo['lat'] !== '' && $geo['lng'] !== '') {
			return [(float) $geo['lat'], (float) $geo['lng']];
		}
	}
	$geo = lf_get_option('lf_business_geo', 'option');
	if (is_array($geo) && isset($geo['lat'], $geo['lng']) && $geo['lat'] !== '' && $geo['lng'] !== '') {
		return [(float) $geo['lat'], (float) $geo['lng']];
	}
	return null;
}

function lf_geo_meta(): void {
	$post_id = null;
	if (is_singular('lf_service_area')) {
		$post_id = get_queried_object_id();
	}
	$geo = lf_geo_data($post_id);
	if (!$geo) {
		return;
	}
	list($lat, $lng) = $geo;
	echo '<meta name="geo.region" content="US" />' . "\n";
	echo '<meta name="ICBM" content="' . esc_attr($lat . ', ' . $lng) . '" />' . "\n";
	$placename = '';
	if ($post_id) {
		$placename = get_the_title($post_id);
	} else {
		$placename = lf_get_option('lf_business_name', 'option');
	}
	if ($placename) {
		echo '<meta name="geo.placename" content="' . esc_attr($placename) . '" />' . "\n";
	}
}

function lf_related_services_for_area(int $service_area_id): array {
	$ids = function_exists('get_field') ? get_field('lf_service_area_services', $service_area_id) : null;
	if (empty($ids) || !is_array($ids)) {
		return [];
	}
	$posts = array_filter(array_map('get_post', $ids));
	return array_values(array_filter($posts, fn($p) => $p && $p->post_status === 'publish'));
}

function lf_related_areas_for_service(int $service_id): array {
	$ids = get_posts([
		'post_type'      => 'lf_service_area',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	]);
	$posts = array_filter(array_map('get_post', $ids));
	return array_values(array_filter($posts, fn($p) => $p && $p->post_status === 'publish'));
}

function lf_internal_link_seed(): string {
	$seed = (string) get_option('lf_site_seed', '');
	if ($seed === '') {
		$seed = wp_generate_password(12, false, false);
		update_option('lf_site_seed', $seed);
	}
	return $seed;
}

function lf_internal_link_templates(): array {
	$templates = [
		'service' => [
			'{service} services',
			'Book {service}',
			'Get {service}',
			'{business} {service}',
			'{service} in {city}',
		],
		'area' => [
			'Service in {area}',
			'Serving {area}',
			'{business} in {area}',
			'{area} service area',
			'Local help in {area}',
		],
		'generic' => [
			'Learn about {title}',
			'Explore {title}',
			'View {title}',
			'{business} — {title}',
			'{title} details',
		],
	];
	return apply_filters('lf_internal_link_templates', $templates);
}

function lf_internal_link_label(string $type, \WP_Post $target, int $origin_id = 0): string {
	$templates = lf_internal_link_templates();
	$list = $templates[$type] ?? $templates['generic'];
	$seed = lf_internal_link_seed();
	$hash = crc32($seed . '|' . $type . '|' . $origin_id . '|' . $target->ID);
	$index = (int) (abs($hash) % count($list));
	$template = $list[$index] ?? $target->post_title;
	$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
	$business = (string) ($entity['name'] ?? get_bloginfo('name'));
	$city = (string) ($entity['address_parts']['city'] ?? '');
	if ($city === '' && !empty($entity['service_areas'][0])) {
		$city = (string) $entity['service_areas'][0];
	}
	$replacements = [
		'{service}' => $target->post_title,
		'{area}' => $target->post_title,
		'{title}' => $target->post_title,
		'{business}' => $business,
		'{city}' => $city !== '' ? $city : $target->post_title,
	];
	$label = strtr($template, $replacements);
	$label = trim(preg_replace('/\s+/', ' ', $label));
	return $label !== '' ? $label : $target->post_title;
}

function lf_breadcrumb_items(): array {
	$items = [];
	$items[] = ['label' => get_bloginfo('name'), 'url' => home_url('/')];

	if (is_front_page()) {
		return apply_filters('lf_breadcrumb_items', $items);
	}
	if (is_singular()) {
		$post = get_queried_object();
		if ($post->post_type === 'lf_service') {
			$items[] = ['label' => __('Services', 'leadsforward-core'), 'url' => get_post_type_archive_link('lf_service')];
		} elseif ($post->post_type === 'lf_service_area') {
			$items[] = ['label' => __('Service Areas', 'leadsforward-core'), 'url' => get_post_type_archive_link('lf_service_area')];
		} elseif ($post->post_type === 'lf_faq') {
			$items[] = ['label' => __('FAQs', 'leadsforward-core'), 'url' => get_post_type_archive_link('lf_faq')];
		} elseif ($post->post_type === 'page' && $post->post_parent) {
			$ancestors = get_post_ancestors($post);
			foreach (array_reverse($ancestors) as $aid) {
				$items[] = ['label' => get_the_title($aid), 'url' => get_permalink($aid)];
			}
		}
		$items[] = ['label' => get_the_title(), 'url' => ''];
		return apply_filters('lf_breadcrumb_items', $items);
	}
	if (is_post_type_archive()) {
		$items[] = ['label' => post_type_archive_title('', false), 'url' => ''];
		return apply_filters('lf_breadcrumb_items', $items);
	}
	if (is_home()) {
		$items[] = ['label' => get_the_title(get_option('page_for_posts')), 'url' => ''];
		return apply_filters('lf_breadcrumb_items', $items);
	}

	return apply_filters('lf_breadcrumb_items', $items);
}
