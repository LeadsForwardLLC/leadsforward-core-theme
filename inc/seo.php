<?php
/**
 * SEO: canonical, noindex, meta; NAP helpers; geo; internal linking; Rank Math breadcrumb compatibility.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

// Canonical and noindex.
add_action('wp_head', 'lf_canonical_tag', 1);
add_action('wp_head', 'lf_robots_noindex_where_needed', 2);
add_action('wp_head', 'lf_meta_description_tag', 4);
add_filter('pre_get_document_title', 'lf_filter_document_title', 20);

/**
 * Output canonical URL. Compatible with Rank Math: use add_filter('lf_output_canonical', '__return_false')
 * if Rank Math (or another plugin) outputs its own canonical.
 */
function lf_canonical_tag(): void {
	if (!apply_filters('lf_output_canonical', true)) {
		return;
	}
	// Rank Math may output its own; we run early so they can remove or override.
	$url = lf_get_canonical_url();
	if (empty($url)) {
		return;
	}
	echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
}

/**
 * Get canonical URL for current request. Singular = permalink; archive = archive link; front = home.
 */
function lf_get_canonical_url(): string {
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
	return (string) home_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
}

/**
 * Noindex for non-ranking content: search, 404, private CPT (testimonials not public).
 */
function lf_robots_noindex_where_needed(): void {
	if (!apply_filters('lf_output_noindex_where_needed', true)) {
		return;
	}
	$noindex = false;
	if (is_search() || is_404()) {
		$noindex = true;
	}
	// Testimonials are private; if any URL ever shows them, noindex. (Currently no single/archive for them.)
	if (is_singular('lf_testimonial') || is_post_type_archive('lf_testimonial')) {
		$noindex = true;
	}
	$noindex = apply_filters('lf_noindex_current_request', $noindex);
	if ($noindex) {
		echo '<meta name="robots" content="noindex, follow" />' . "\n";
	}
}

/**
 * SEO overrides from Page Builder config (pages/posts).
 */
function lf_get_pb_seo_overrides(int $post_id): array {
	$config = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (!is_array($config)) {
		return ['title' => '', 'description' => ''];
	}
	$seo = is_array($config['seo'] ?? null) ? $config['seo'] : [];
	return [
		'title' => sanitize_text_field((string) ($seo['title'] ?? '')),
		'description' => sanitize_textarea_field((string) ($seo['description'] ?? '')),
	];
}

function lf_get_pb_hero_subheadline(int $post_id): string {
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

function lf_get_pb_hero_headline(int $post_id): string {
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
		$headline = $settings['hero_headline'] ?? '';
		return is_string($headline) ? $headline : '';
	}
	return '';
}

function lf_get_homepage_hero_headline(): string {
	if (!function_exists('lf_get_homepage_section_config')) {
		return '';
	}
	$config = lf_get_homepage_section_config();
	$hero = $config['hero']['hero_headline'] ?? '';
	return is_string($hero) ? $hero : '';
}

function lf_get_homepage_hero_subheadline(): string {
	if (!function_exists('lf_get_homepage_section_config')) {
		return '';
	}
	$config = lf_get_homepage_section_config();
	$sub = $config['hero']['hero_subheadline'] ?? '';
	return is_string($sub) ? $sub : '';
}

function lf_filter_document_title(string $title): string {
	if (is_front_page()) {
		$hero = lf_get_homepage_hero_headline();
		return $hero !== '' ? $hero : $title;
	}
	if (is_home()) {
		$page_for_posts = (int) get_option('page_for_posts');
		if ($page_for_posts) {
			$seo = lf_get_pb_seo_overrides($page_for_posts);
			if (!empty($seo['title'])) {
				return $seo['title'];
			}
			$hero = lf_get_pb_hero_headline($page_for_posts);
			if ($hero !== '') {
				return $hero;
			}
		}
		return $title;
	}
	if (!is_singular(['page', 'post', 'lf_service', 'lf_service_area'])) {
		return $title;
	}
	$post_id = get_queried_object_id();
	if (!$post_id) {
		return $title;
	}
	$seo = lf_get_pb_seo_overrides($post_id);
	if (!empty($seo['title'])) {
		return $seo['title'];
	}
	$hero = lf_get_pb_hero_headline($post_id);
	if ($hero !== '') {
		return $hero;
	}
	return $title;
}

function lf_get_meta_description_default(int $post_id): string {
	$sub = lf_get_pb_hero_subheadline($post_id);
	if ($sub !== '') {
		return $sub;
	}
	$excerpt = get_the_excerpt($post_id);
	if ($excerpt !== '') {
		return $excerpt;
	}
	$content = wp_strip_all_tags(get_post_field('post_content', $post_id));
	return wp_trim_words($content, 28);
}

function lf_meta_description_tag(): void {
	if (!apply_filters('lf_output_meta_description', true)) {
		return;
	}
	$desc = '';
	if (is_front_page()) {
		$desc = lf_get_homepage_hero_subheadline();
	}
	if ($desc === '' && is_home()) {
		$page_for_posts = (int) get_option('page_for_posts');
		if ($page_for_posts) {
			$seo = lf_get_pb_seo_overrides($page_for_posts);
			$desc = $seo['description'] ?? '';
			if ($desc === '') {
				$hero = lf_get_pb_hero_subheadline($page_for_posts);
				$desc = $hero !== '' ? $hero : lf_get_meta_description_default($page_for_posts);
			}
		}
	}
	if ($desc === '' && is_singular(['page', 'post', 'lf_service', 'lf_service_area'])) {
		$post_id = get_queried_object_id();
		if ($post_id) {
			$seo = lf_get_pb_seo_overrides($post_id);
			$desc = $seo['description'] ?? '';
			if ($desc === '') {
				$hero = lf_get_pb_hero_subheadline($post_id);
				$desc = $hero !== '' ? $hero : lf_get_meta_description_default($post_id);
			}
		}
	}
	$desc = trim(preg_replace('/\s+/', ' ', (string) $desc));
	if ($desc === '') {
		return;
	}
	echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";
}

/**
 * NAP: name, address, phone. Returns associative array for flexible output.
 */
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

/**
 * NAP as plain text (one line or multiline). For schema or meta.
 */
function lf_nap_plain(string $separator = "\n"): string {
	$nap = lf_nap_data();
	$parts = array_filter([$nap['name'], $nap['address'], $nap['phone'], $nap['email']]);
	return implode($separator, $parts);
}

/**
 * NAP as semantic HTML (address block with optional links for phone/email).
 */
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

/**
 * Geo coordinates from global options or current service area. Returns [lat, lng] or null.
 */
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

/**
 * Output geo meta tags (ICBM, geo.region, geo.placename) when appropriate.
 */
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
add_action('wp_head', 'lf_geo_meta', 3);

/**
 * Related services for a service area (IDs or post objects). For internal linking.
 */
function lf_related_services_for_area(int $service_area_id): array {
	$ids = function_exists('get_field') ? get_field('lf_service_area_services', $service_area_id) : null;
	if (empty($ids) || !is_array($ids)) {
		return [];
	}
	$posts = array_filter(array_map('get_post', $ids));
	return array_values(array_filter($posts, fn($p) => $p && $p->post_status === 'publish'));
}

/**
 * Related service areas for a service (IDs or post objects). For internal linking.
 */
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

/**
 * Stable seed for deterministic internal anchor variations.
 */
function lf_internal_link_seed(): string {
	$seed = (string) get_option('lf_site_seed', '');
	if ($seed === '') {
		$seed = wp_generate_password(12, false, false);
		update_option('lf_site_seed', $seed);
	}
	return $seed;
}

/**
 * Anchor templates for internal links (5 per type).
 */
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

/**
 * Deterministic anchor text based on seed + origin + target.
 */
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

/**
 * Breadcrumb items for current request. Rank Math–compatible: array of [label, url].
 * Filter lf_breadcrumb_items to extend or override.
 */
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
		$type = get_post_type();
		$items[] = ['label' => post_type_archive_title('', false), 'url' => ''];
		return apply_filters('lf_breadcrumb_items', $items);
	}
	if (is_home()) {
		$items[] = ['label' => get_the_title(get_option('page_for_posts')), 'url' => ''];
		return apply_filters('lf_breadcrumb_items', $items);
	}

	return apply_filters('lf_breadcrumb_items', $items);
}
