<?php
/**
 * Service Areas CPT. SEO-safe URLs: /service-areas/city-name/
 * For local lead-gen: cities, regions, zip codes.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_cpt_service_areas(): void {
	$labels = [
		'name'               => _x('Service Areas', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('Service Area', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('Service Areas', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'service area', 'leadsforward-core'),
		'add_new_item'       => __('Add New Service Area', 'leadsforward-core'),
		'edit_item'          => __('Edit Service Area', 'leadsforward-core'),
		'new_item'           => __('New Service Area', 'leadsforward-core'),
		'view_item'          => __('View Service Area', 'leadsforward-core'),
		'search_items'       => __('Search Service Areas', 'leadsforward-core'),
		'not_found'          => __('No service areas found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No service areas found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'rest_base'           => 'service-areas',
		'query_var'           => true,
		'rewrite'             => ['slug' => 'service-areas', 'with_front' => false],
		'capability_type'     => 'post',
		'has_archive'         => true,
		'hierarchical'        => false,
		'menu_position'       => 21,
		'menu_icon'           => 'dashicons-location-alt',
		'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
	];
	register_post_type('lf_service_area', $args);
}
add_action('init', 'lf_register_cpt_service_areas');

/**
 * Ensure /service-areas/ resolves to the overview page when it exists.
 * Keeps single service area URLs intact at /service-areas/{city}/.
 */
function lf_service_areas_overview_page_priority(\WP $wp): void {
	if (is_admin()) {
		return;
	}
	$request_path = trim((string) ($wp->request ?? ''), '/');
	if ($request_path !== 'service-areas') {
		return;
	}
	$overview = get_page_by_path('service-areas');
	if (!$overview instanceof \WP_Post || $overview->post_type !== 'page') {
		return;
	}
	$wp->query_vars = [
		'page_id' => (string) $overview->ID,
		'pagename' => 'service-areas',
	];
}
add_action('parse_request', 'lf_service_areas_overview_page_priority', 1);

/**
 * Parse one service-area line (e.g. "City, ST" or "City1, City2, City3, ST").
 *
 * @return list<array{name: string, state: string}>
 */
function lf_parse_service_area_location_line(string $line, string $default_state = ''): array {
	$line = trim($line);
	if ($line === '') {
		return [];
	}

	$state = strtoupper(trim($default_state));
	$body = $line;

	if (preg_match('/^(.+),\s*([A-Za-z]{2})\s*$/', $line, $m) === 1) {
		$body = trim((string) ($m[1] ?? ''));
		$state = strtoupper(trim((string) ($m[2] ?? $state)));
		if ($body !== '' && substr_count($body, ',') >= 1) {
			$cities = array_values(array_filter(array_map('trim', explode(',', $body))));
			$out = [];
			foreach ($cities as $city) {
				if ($city !== '') {
					$out[] = ['name' => $city, 'state' => $state];
				}
			}
			return $out;
		}
		if ($body !== '') {
			return [['name' => $body, 'state' => $state]];
		}
	}

	if (strpos($line, ',') !== false) {
		$segments = array_values(array_filter(array_map('trim', explode(',', $line))));
		if (count($segments) > 1) {
			$out = [];
			foreach ($segments as $segment) {
				if (preg_match('/^(.+?),\s*([A-Za-z]{2})$/', $segment, $cm) === 1) {
					$out[] = [
						'name' => trim((string) ($cm[1] ?? $segment)),
						'state' => strtoupper(trim((string) ($cm[2] ?? $state))),
					];
					continue;
				}
				$out[] = ['name' => $segment, 'state' => $state];
			}
			return $out;
		}
	}

	return [['name' => $line, 'state' => $state]];
}

/**
 * Parse newline/semicolon/comma service area lists into individual cities.
 *
 * @return list<array{name: string, state: string}>
 */
function lf_parse_service_area_location_list(string $raw, string $default_state = ''): array {
	$raw = trim($raw);
	if ($raw === '') {
		return [];
	}

	$lines = preg_split('/\r\n|\r|\n|;/', $raw) ?: [];
	if (count($lines) === 1) {
		return lf_parse_service_area_location_line((string) ($lines[0] ?? ''), $default_state);
	}

	$out = [];
	foreach ($lines as $line) {
		$line = trim((string) $line);
		if ($line === '') {
			continue;
		}
		$out = array_merge($out, lf_parse_service_area_location_line($line, $default_state));
	}
	return $out;
}

/**
 * Whether a stored title looks like multiple cities collapsed into one menu/post label.
 */
function lf_service_area_title_looks_lumped(string $title): bool {
	return count(lf_parse_service_area_location_list($title)) > 1;
}

/**
 * Expand manifest/Airtable area rows that collapsed multiple cities into one item.
 *
 * @param list<array<string, mixed>> $areas
 * @return list<array<string, mixed>>
 */
function lf_expand_manifest_service_area_items(array $areas, string $default_state = ''): array {
	$out = [];
	foreach ($areas as $item) {
		if (!is_array($item)) {
			continue;
		}
		$city = trim((string) ($item['city'] ?? $item['name'] ?? ''));
		$state = strtoupper(trim((string) ($item['state'] ?? '')));
		if ($city === '') {
			continue;
		}

		$parse_line = $city;
		if ($state !== '' && !lf_service_area_title_looks_lumped($city) && strpos($city, ',') === false) {
			$parse_line = trim($city . ', ' . $state);
		} elseif ($state !== '' && lf_service_area_title_looks_lumped($city) && !preg_match('/,\s*' . preg_quote($state, '/') . '\s*$/i', $city)) {
			$parse_line = trim($city . ', ' . $state);
		}

		$parsed = lf_parse_service_area_location_list($parse_line, $state !== '' ? $state : $default_state);
		if (count($parsed) <= 1) {
			$out[] = $item;
			continue;
		}

		$keyword = trim((string) ($item['primary_keyword'] ?? $item['keyword'] ?? ''));
		foreach ($parsed as $row) {
			$name = trim((string) ($row['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$row_state = strtoupper(trim((string) ($row['state'] ?? '')));
			$out[] = [
				'city' => $name,
				'state' => $row_state,
				'slug' => sanitize_title($row_state !== '' ? $name . '-' . $row_state : $name),
				'primary_keyword' => $keyword,
			];
		}
	}
	return $out;
}

/**
 * Split lumped lf_service_area posts (comma-separated titles) into one post per city.
 */
function lf_service_areas_repair_lumped_posts(): int {
	$posts = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
		'posts_per_page' => -1,
		'orderby' => 'ID',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	if (!is_array($posts) || $posts === []) {
		return 0;
	}

	$repaired = 0;
	foreach ($posts as $post) {
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$title = trim((string) $post->post_title);
		if ($title === '' || !lf_service_area_title_looks_lumped($title)) {
			continue;
		}

		$default_state = '';
		if (function_exists('get_post_meta')) {
			$default_state = strtoupper(trim((string) get_post_meta($post->ID, 'lf_service_area_state', true)));
		}
		$parsed = lf_parse_service_area_location_list($title, $default_state);
		if (count($parsed) <= 1) {
			continue;
		}

		$created_any = false;
		foreach ($parsed as $row) {
			$name = trim((string) ($row['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$row_state = strtoupper(trim((string) ($row['state'] ?? '')));
			$slug = sanitize_title($row_state !== '' ? $name . '-' . $row_state : $name);
			if ($slug === '') {
				continue;
			}

			$existing = get_page_by_path($slug, OBJECT, 'lf_service_area');
			if ($existing instanceof \WP_Post && (int) $existing->ID !== (int) $post->ID) {
				continue;
			}

			$new_title = trim($name . ($row_state !== '' ? ', ' . $row_state : ''));
			if ($existing instanceof \WP_Post) {
				wp_update_post([
					'ID' => $existing->ID,
					'post_title' => $new_title,
					'post_name' => $slug,
					'post_status' => $post->post_status,
				]);
				$created_any = true;
				continue;
			}

			$new_id = wp_insert_post([
				'post_type' => 'lf_service_area',
				'post_title' => $new_title,
				'post_name' => $slug,
				'post_status' => $post->post_status,
				'post_author' => (int) $post->post_author,
				'post_content' => $post->post_content,
			], true);
			if (is_wp_error($new_id) || !(int) $new_id) {
				continue;
			}
			if ($row_state !== '' && function_exists('update_post_meta')) {
				update_post_meta((int) $new_id, 'lf_service_area_state', $row_state);
			}
			$created_any = true;
		}

		if ($created_any) {
			wp_trash_post((int) $post->ID);
			++$repaired;
		}
	}

	return $repaired;
}

/**
 * One-time repair for sites that already have a lumped service-area CPT in the DB.
 */
function lf_service_areas_maybe_run_lump_repair(): void {
	if (is_admin() && !wp_doing_ajax()) {
		return;
	}
	if (get_option('lf_service_areas_lump_repair_v1')) {
		return;
	}
	$repaired = lf_service_areas_repair_lumped_posts();
	if ($repaired > 0 && function_exists('lf_sitemap_sync_build_header_menu')) {
		lf_sitemap_sync_build_header_menu();
	}
	update_option('lf_service_areas_lump_repair_v1', time(), false);
}
add_action('wp', 'lf_service_areas_maybe_run_lump_repair', 5);
