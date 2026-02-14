<?php
/**
 * Automatic keyword assignment + mapping engine.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('save_post_lf_service', 'lf_seo_handle_new_service', 10, 3);
add_action('save_post_lf_service_area', 'lf_seo_handle_new_service_area', 10, 3);
add_action('save_post_post', 'lf_seo_handle_new_post', 10, 3);

function lf_seo_keyword_engine_enabled(): bool {
	return function_exists('lf_seo_get_setting')
		? (bool) lf_seo_get_setting('ai.enable_auto_keywords', true)
		: true;
}

function lf_seo_keyword_map_enabled(): bool {
	return function_exists('lf_seo_get_setting')
		? (bool) lf_seo_get_setting('ai.enable_keyword_map', true)
		: true;
}

function lf_seo_get_keyword_map(): array {
	$map = get_option('lf_keyword_map', []);
	if (!is_array($map)) {
		$map = [];
	}
	$map = array_merge([
		'primary' => [],
		'secondary' => [],
		'last_index' => [],
	], $map);
	if (!is_array($map['primary'] ?? null)) {
		$map['primary'] = [];
	}
	if (!is_array($map['secondary'] ?? null)) {
		$map['secondary'] = [];
	}
	if (!is_array($map['last_index'] ?? null)) {
		$map['last_index'] = [];
	}
	return $map;
}

function lf_seo_update_keyword_map(array $map): void {
	if (!lf_seo_keyword_map_enabled()) {
		return;
	}
	update_option('lf_keyword_map', $map);
}

function lf_seo_keyword_map_key(int $post_id): string {
	return 'post:' . $post_id;
}

function lf_seo_register_keyword_map_for_post(int $post_id, string $primary): void {
	if (!lf_seo_keyword_map_enabled()) {
		return;
	}
	$primary = trim($primary);
	if ($primary === '') {
		return;
	}
	$map = lf_seo_get_keyword_map();
	$map['primary'][lf_seo_keyword_map_key($post_id)] = $primary;
	lf_seo_update_keyword_map($map);
}

function lf_seo_get_used_primary_keywords(array $map): array {
	$used = [];
	foreach ($map['primary'] ?? [] as $keyword) {
		$keyword = trim((string) $keyword);
		if ($keyword !== '') {
			$used[strtolower($keyword)] = true;
		}
	}
	return $used;
}

function lf_seo_mark_used_primary(array &$used, string $keyword): void {
	$keyword = trim($keyword);
	if ($keyword === '') {
		return;
	}
	$used[strtolower($keyword)] = true;
}

function lf_seo_get_manifest_keywords(): array {
	$manifest = get_option('lf_site_manifest', []);
	return lf_seo_extract_keywords_from_manifest(is_array($manifest) ? $manifest : []);
}

function lf_seo_extract_keywords_from_manifest(array $manifest): array {
	$primary = (string) ($manifest['homepage']['primary_keyword'] ?? '');
	$secondary = $manifest['homepage']['secondary_keywords'] ?? [];
	if (is_string($secondary)) {
		$secondary = preg_split('/\r\n|\r|\n|,/', $secondary);
	}
	if (!is_array($secondary)) {
		$secondary = [];
	}
	$secondary = array_values(array_unique(array_filter(array_map('sanitize_text_field', $secondary))));
	return [
		'primary' => sanitize_text_field($primary),
		'secondary' => $secondary,
		'manifest' => $manifest,
	];
}

function lf_seo_get_manifest_city(array $manifest): string {
	$city = (string) ($manifest['business']['primary_city'] ?? '');
	if ($city === '' && !empty($manifest['business']['address']['city'])) {
		$city = (string) $manifest['business']['address']['city'];
	}
	if ($city === '' && function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$city = (string) ($entity['address_parts']['city'] ?? '');
	}
	return $city;
}

function lf_seo_assign_keywords_from_manifest(array $manifest): void {
	if (!lf_seo_keyword_engine_enabled()) {
		return;
	}
	$keywords = lf_seo_extract_keywords_from_manifest($manifest);
	$primary = $keywords['primary'];
	$secondary = $keywords['secondary'];
	$city = lf_seo_get_manifest_city($manifest);
	$map = lf_seo_get_keyword_map();
	$used = lf_seo_get_used_primary_keywords($map);

	if ($primary !== '') {
		$homepage_key = 'homepage';
		$unique_primary = lf_seo_make_unique_keyword($primary, $used, $primary, $city, '');
		$map['primary'][$homepage_key] = $unique_primary;
		lf_seo_mark_used_primary($used, $unique_primary);
	}

	$services = get_posts([
		'post_type' => 'lf_service',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	$service_names = [];
	foreach ($services as $service) {
		$service_names[] = get_the_title($service);
	}

	$service_index = (int) ($map['last_index']['service'] ?? 0);
	foreach ($services as $service) {
		$current = (string) get_post_meta($service->ID, '_lf_seo_primary_keyword', true);
		if (trim($current) !== '') {
			$map['primary'][lf_seo_keyword_map_key((int) $service->ID)] = $current;
			lf_seo_mark_used_primary($used, $current);
			if (function_exists('lf_seo_maybe_populate_generated_meta')) {
				lf_seo_maybe_populate_generated_meta((int) $service->ID, $current, $secondary);
			}
			continue;
		}
		$keyword = lf_seo_pick_next_keyword($secondary, $used, $service_index, get_the_title($service), $city, $primary);
		if ($keyword === '') {
			continue;
		}
		update_post_meta($service->ID, '_lf_seo_primary_keyword', $keyword);
		if (function_exists('lf_seo_maybe_populate_generated_meta')) {
			lf_seo_maybe_populate_generated_meta((int) $service->ID, $keyword, $secondary);
		}
		$map['primary'][lf_seo_keyword_map_key((int) $service->ID)] = $keyword;
		lf_seo_mark_used_primary($used, $keyword);
	}
	$map['last_index']['service'] = $service_index;

	$areas = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	$area_index = (int) ($map['last_index']['service_area'] ?? 0);
	foreach ($areas as $area) {
		$current = (string) get_post_meta($area->ID, '_lf_seo_primary_keyword', true);
		if (trim($current) !== '') {
			$map['primary'][lf_seo_keyword_map_key((int) $area->ID)] = $current;
			lf_seo_mark_used_primary($used, $current);
			if (function_exists('lf_seo_maybe_populate_generated_meta')) {
				lf_seo_maybe_populate_generated_meta((int) $area->ID, $current, $secondary);
			}
			continue;
		}
		$service_name = '';
		if (!empty($service_names)) {
			$service_name = $service_names[$area_index % count($service_names)];
		}
		$area_keyword = trim(implode(' ', array_filter([$service_name, get_the_title($area)])));
		$keyword = lf_seo_make_unique_keyword($area_keyword, $used, get_the_title($area), $city, $primary);
		if ($keyword === '') {
			continue;
		}
		update_post_meta($area->ID, '_lf_seo_primary_keyword', $keyword);
		if (function_exists('lf_seo_maybe_populate_generated_meta')) {
			lf_seo_maybe_populate_generated_meta((int) $area->ID, $keyword, $secondary);
		}
		$map['primary'][lf_seo_keyword_map_key((int) $area->ID)] = $keyword;
		lf_seo_mark_used_primary($used, $keyword);
		$area_index++;
	}
	$map['last_index']['service_area'] = $area_index;

	$posts = get_posts([
		'post_type' => 'post',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'orderby' => 'date',
		'order' => 'DESC',
		'no_found_rows' => true,
	]);
	$post_index = (int) ($map['last_index']['post'] ?? 0);
	foreach ($posts as $post) {
		$current = (string) get_post_meta($post->ID, '_lf_seo_primary_keyword', true);
		if (trim($current) !== '') {
			$map['primary'][lf_seo_keyword_map_key((int) $post->ID)] = $current;
			lf_seo_mark_used_primary($used, $current);
			if (function_exists('lf_seo_maybe_populate_generated_meta')) {
				lf_seo_maybe_populate_generated_meta((int) $post->ID, $current, $secondary);
			}
			continue;
		}
		$keyword = lf_seo_pick_next_keyword($secondary, $used, $post_index, get_the_title($post), $city, $primary);
		if ($keyword === '') {
			continue;
		}
		update_post_meta($post->ID, '_lf_seo_primary_keyword', $keyword);
		if (function_exists('lf_seo_maybe_populate_generated_meta')) {
			lf_seo_maybe_populate_generated_meta((int) $post->ID, $keyword, $secondary);
		}
		$map['primary'][lf_seo_keyword_map_key((int) $post->ID)] = $keyword;
		lf_seo_mark_used_primary($used, $keyword);
	}
	$map['last_index']['post'] = $post_index;

	lf_seo_update_keyword_map($map);
}

function lf_seo_post_needs_primary_keyword(int $post_id): bool {
	$current = (string) get_post_meta($post_id, '_lf_seo_primary_keyword', true);
	return trim($current) === '';
}

function lf_seo_pick_next_keyword(array $secondary, array &$used, int &$index, string $fallback_title, string $city, string $primary): string {
	$secondary = array_values(array_unique(array_filter($secondary)));
	$count = count($secondary);
	if ($count > 0) {
		for ($i = 0; $i < $count; $i++) {
			$pos = ($index + $i) % $count;
			$candidate = trim((string) $secondary[$pos]);
			if ($candidate === '') {
				continue;
			}
			if (!isset($used[strtolower($candidate)])) {
				$index = ($pos + 1) % $count;
				return $candidate;
			}
		}
	}
	$fallback = trim(implode(' ', array_filter([$fallback_title, $city])));
	if ($primary !== '' && $fallback_title !== '') {
		$fallback = trim($fallback_title . ' ' . $primary);
	}
	return lf_seo_make_unique_keyword($fallback, $used, $fallback_title, $city, $primary);
}

function lf_seo_make_unique_keyword(string $candidate, array $used, string $fallback_title, string $city, string $primary): string {
	$attempts = array_filter([
		trim($candidate),
		trim(implode(' ', array_filter([$fallback_title, $city]))),
		trim(implode(' ', array_filter([$fallback_title, $primary]))),
		trim(implode(' ', array_filter([$fallback_title, $city, $primary]))),
		trim($fallback_title),
	]);
	foreach ($attempts as $attempt) {
		if ($attempt !== '' && !isset($used[strtolower($attempt)])) {
			return $attempt;
		}
	}
	return $attempts ? (string) $attempts[0] : '';
}

function lf_seo_handle_new_service(int $post_id, \WP_Post $post, bool $update): void {
	if ($update || !lf_seo_keyword_engine_enabled()) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	$keywords = lf_seo_get_manifest_keywords();
	$primary = $keywords['primary'];
	$secondary = $keywords['secondary'];
	$manifest = $keywords['manifest'] ?? [];
	$city = lf_seo_get_manifest_city(is_array($manifest) ? $manifest : []);
	$map = lf_seo_get_keyword_map();
	$used = lf_seo_get_used_primary_keywords($map);
	$index = (int) ($map['last_index']['service'] ?? 0);
	$keyword = lf_seo_pick_next_keyword($secondary, $used, $index, $post->post_title, $city, $primary);
	if ($keyword === '') {
		return;
	}
	update_post_meta($post_id, '_lf_seo_primary_keyword', $keyword);
	if (function_exists('lf_seo_maybe_populate_generated_meta')) {
		lf_seo_maybe_populate_generated_meta($post_id, $keyword, $secondary);
	}
	$map['primary'][lf_seo_keyword_map_key($post_id)] = $keyword;
	$map['last_index']['service'] = $index;
	lf_seo_update_keyword_map($map);
}

function lf_seo_handle_new_service_area(int $post_id, \WP_Post $post, bool $update): void {
	if ($update || !lf_seo_keyword_engine_enabled()) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	$keywords = lf_seo_get_manifest_keywords();
	$primary = $keywords['primary'];
	$manifest = $keywords['manifest'] ?? [];
	$city = lf_seo_get_manifest_city(is_array($manifest) ? $manifest : []);
	$services = get_posts([
		'post_type' => 'lf_service',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	$service_name = '';
	if (!empty($services)) {
		$service_name = get_the_title($services[0]);
	}
	$candidate = trim(implode(' ', array_filter([$service_name, $post->post_title])));
	$map = lf_seo_get_keyword_map();
	$used = lf_seo_get_used_primary_keywords($map);
	$keyword = lf_seo_make_unique_keyword($candidate, $used, $post->post_title, $city, $primary);
	if ($keyword === '') {
		return;
	}
	update_post_meta($post_id, '_lf_seo_primary_keyword', $keyword);
	if (function_exists('lf_seo_maybe_populate_generated_meta')) {
		lf_seo_maybe_populate_generated_meta($post_id, $keyword);
	}
	$map['primary'][lf_seo_keyword_map_key($post_id)] = $keyword;
	lf_seo_update_keyword_map($map);
}

function lf_seo_handle_new_post(int $post_id, \WP_Post $post, bool $update): void {
	if ($update || !lf_seo_keyword_engine_enabled()) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	$keywords = lf_seo_get_manifest_keywords();
	$primary = $keywords['primary'];
	$secondary = $keywords['secondary'];
	$manifest = $keywords['manifest'] ?? [];
	$city = lf_seo_get_manifest_city(is_array($manifest) ? $manifest : []);
	$map = lf_seo_get_keyword_map();
	$used = lf_seo_get_used_primary_keywords($map);
	$index = (int) ($map['last_index']['post'] ?? 0);
	$keyword = lf_seo_pick_next_keyword($secondary, $used, $index, $post->post_title, $city, $primary);
	if ($keyword === '') {
		return;
	}
	update_post_meta($post_id, '_lf_seo_primary_keyword', $keyword);
	if (function_exists('lf_seo_maybe_populate_generated_meta')) {
		lf_seo_maybe_populate_generated_meta($post_id, $keyword, $secondary);
	}
	$map['primary'][lf_seo_keyword_map_key($post_id)] = $keyword;
	$map['last_index']['post'] = $index;
	lf_seo_update_keyword_map($map);
}
