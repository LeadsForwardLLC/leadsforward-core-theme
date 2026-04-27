<?php
/**
 * Sitemap-driven page reconcile (Airtable Sitemaps → WP pages).
 *
 * Task 3: cache + index, publish strategy, safe upsert, and a callable entrypoint.
 *
 * @package LeadsForward_Core
 * @since 0.1.81
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Get current site niche slug (for filtering niche template sitemaps).
 */
function lf_sitemap_sync_get_site_niche_slug(): string {
	$niche = trim((string) get_option('lf_homepage_niche_slug', ''));
	if ($niche !== '') {
		return sanitize_title($niche);
	}
	$manifest = get_option('lf_site_manifest', []);
	$manifest = is_array($manifest) ? $manifest : [];
	$from_manifest = '';
	if (!empty($manifest['business']['niche'])) {
		$from_manifest = (string) $manifest['business']['niche'];
	} elseif (!empty($manifest['niche'])) {
		$from_manifest = (string) $manifest['niche'];
	}
	return sanitize_title((string) $from_manifest);
}

/**
 * Get primary city for {city} resolution.
 */
function lf_sitemap_sync_get_primary_city(): string {
	$manifest = get_option('lf_site_manifest', []);
	$manifest = is_array($manifest) ? $manifest : [];
	if (function_exists('lf_seo_get_manifest_city')) {
		return (string) lf_seo_get_manifest_city($manifest);
	}
	$city = (string) ($manifest['business']['primary_city'] ?? '');
	if ($city === '' && !empty($manifest['business']['address']['city'])) {
		$city = (string) $manifest['business']['address']['city'];
	}
	if ($city === '' && function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		if (is_array($entity) && !empty($entity['address_parts']['city'])) {
			$city = (string) $entity['address_parts']['city'];
		}
	}
	return $city;
}

/**
 * @return array{ratio:float, unpublished_mode:string}
 */
function lf_sitemap_sync_get_publish_settings(): array {
	$ratio = (float) get_option('lf_sitemap_publish_ratio', 0.5);
	if ($ratio <= 0.0) {
		$ratio = 0.0;
	} elseif ($ratio > 1.0) {
		$ratio = 1.0;
	}

	$mode = sanitize_key((string) get_option('lf_sitemap_unpublished_mode', 'draft'));
	$allowed = ['draft' => true, 'private' => true, 'pending' => true];
	if (!isset($allowed[$mode])) {
		$mode = 'draft';
	}

	return ['ratio' => $ratio, 'unpublished_mode' => $mode];
}

/**
 * Core hubs are always published.
 */
function lf_sitemap_sync_is_core_hub(string $resolved_slug, string $slug_template): bool {
	$resolved_slug = function_exists('lf_sitemap_normalize_slug_path') ? lf_sitemap_normalize_slug_path($resolved_slug) : ('/' . trim($resolved_slug, '/') . '/');
	$template_path = function_exists('lf_sitemap_normalize_slug_template_for_key')
		? lf_sitemap_normalize_slug_template_for_key($slug_template)
		: ('/' . trim((string) $slug_template, '/') . '/');

	$core = [
		'/' => true,
		'/services/' => true,
		'/service-areas/' => true,
		'/contact/' => true,
		'/about/' => true,
		'/why/' => true,
		'/why-us/' => true,
		'/reviews/' => true,
	];

	return !empty($core[$resolved_slug]) || !empty($core[$template_path]);
}

/**
 * @return int Page ID or 0
 */
function lf_sitemap_sync_find_page_by_key(string $key): int {
	$key = trim($key);
	if ($key === '') {
		return 0;
	}
	$posts = get_posts([
		'post_type' => 'page',
		'post_status' => 'any',
		'posts_per_page' => 1,
		'fields' => 'ids',
		'meta_key' => '_lf_sitemap_key',
		'meta_value' => $key,
		'no_found_rows' => true,
	]);
	return !empty($posts[0]) ? (int) $posts[0] : 0;
}

/**
 * @return int Parent page ID or 0
 */
function lf_sitemap_sync_find_page_parent_id(string $resolved_slug): int {
	$resolved_slug = function_exists('lf_sitemap_normalize_slug_path') ? lf_sitemap_normalize_slug_path($resolved_slug) : ('/' . trim($resolved_slug, '/') . '/');
	$path = trim($resolved_slug, '/');
	if ($path === '') {
		return 0;
	}
	$segments = array_values(array_filter(explode('/', $path), static fn(string $v): bool => $v !== ''));
	if (count($segments) <= 1) {
		return 0;
	}
	array_pop($segments);
	$parent_path = implode('/', $segments);
	$parent = get_page_by_path($parent_path, OBJECT, 'page');
	return $parent instanceof WP_Post ? (int) $parent->ID : 0;
}

/**
 * Upsert a WP Page from a PageSpec.
 *
 * @param array<string,mixed> $spec
 * @return array{ok:bool, post_id:int, created:bool, updated:bool, skipped:bool, error:string}
 */
function lf_sitemap_sync_upsert_page(array $spec): array {
	$key = (string) ($spec['sitemap_key'] ?? '');
	$title = sanitize_text_field((string) ($spec['title'] ?? ''));
	$slug_template = (string) ($spec['slug_template'] ?? '');
	$resolved_slug = (string) ($spec['slug_resolved'] ?? '/');
	$status = sanitize_key((string) ($spec['post_status'] ?? 'draft'));
	$keyword = sanitize_text_field((string) ($spec['primary_keyword'] ?? ''));

	if ($key === '' || $title === '' || trim($slug_template) === '' || trim($resolved_slug) === '') {
		return ['ok' => false, 'post_id' => 0, 'created' => false, 'updated' => false, 'skipped' => false, 'error' => 'missing_required_fields'];
	}

	$resolved_slug = function_exists('lf_sitemap_normalize_slug_path') ? lf_sitemap_normalize_slug_path($resolved_slug) : ('/' . trim($resolved_slug, '/') . '/');
	$path = trim($resolved_slug, '/');
	$is_home = $path === '';
	$post_name = $is_home ? '' : sanitize_title((string) basename($path));
	$post_parent = $is_home ? 0 : lf_sitemap_sync_find_page_parent_id($resolved_slug);

	$post_id = lf_sitemap_sync_find_page_by_key($key);
	$existing = null;
	if ($post_id > 0) {
		$existing = get_post($post_id);
	}
	if (!$existing instanceof WP_Post) {
		$existing = $is_home ? get_post((int) get_option('page_on_front')) : get_page_by_path($path, OBJECT, 'page');
		$post_id = $existing instanceof WP_Post ? (int) $existing->ID : 0;
	}

	$payload = [
		'post_type' => 'page',
		'post_title' => $title,
		'post_status' => $status !== '' ? $status : 'draft',
	];
	if (!$is_home) {
		$payload['post_name'] = $post_name;
		$payload['post_parent'] = $post_parent;
	}

	$created = false;
	$updated = false;
	if ($post_id <= 0) {
		$insert_id = wp_insert_post($payload, true);
		if (is_wp_error($insert_id)) {
			return ['ok' => false, 'post_id' => 0, 'created' => false, 'updated' => false, 'skipped' => false, 'error' => (string) $insert_id->get_error_message()];
		}
		$post_id = (int) $insert_id;
		$created = true;
	} else {
		$payload['ID'] = $post_id;
		$update_id = wp_update_post($payload, true);
		if (is_wp_error($update_id)) {
			return ['ok' => false, 'post_id' => $post_id, 'created' => false, 'updated' => false, 'skipped' => false, 'error' => (string) $update_id->get_error_message()];
		}
		$updated = true;
	}

	update_post_meta($post_id, '_lf_sitemap_key', $key);
	update_post_meta($post_id, '_lf_sitemap_slug_template', $slug_template);
	if ($keyword !== '') {
		update_post_meta($post_id, '_lf_seo_primary_keyword', $keyword);
	}

	return ['ok' => true, 'post_id' => $post_id, 'created' => $created, 'updated' => $updated, 'skipped' => false, 'error' => ''];
}

/**
 * Build cache payload for storage.
 *
 * @param list<array<string,mixed>> $specs
 * @return string JSON
 */
function lf_sitemap_sync_encode_cache(array $specs): string {
	$specs = array_values(array_filter($specs, static fn($v): bool => is_array($v)));
	return wp_json_encode($specs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
}

/**
 * Entry point: fetch rows -> normalize -> resolve slugs -> upsert pages -> build index -> persist options.
 *
 * @return array{
 *   ok:bool,
 *   fetched_rows:int,
 *   normalized:int,
 *   invalid:int,
 *   city:string,
 *   created:int,
 *   updated:int,
 *   skipped:int,
 *   errors:list<string>,
 *   error_codes:list<string>,
 *   index_count:int
 * }
 */
function lf_sitemap_sync_reconcile_run(): array {
	$result = [
		'ok' => false,
		'fetched_rows' => 0,
		'normalized' => 0,
		'invalid' => 0,
		'niche' => '',
		'city' => '',
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'errors' => [],
		'error_codes' => [],
		'index_count' => 0,
	];

	$fetch = function_exists('lf_airtable_sitemaps_fetch_rows')
		? lf_airtable_sitemaps_fetch_rows()
		: ['ok' => false, 'rows' => [], 'error' => 'missing_airtable_sitemaps_fetch'];
	if (empty($fetch['ok'])) {
		$result['errors'][] = (string) ($fetch['error'] ?? 'unknown_error');
		return $result;
	}

	$rows = is_array($fetch['rows'] ?? null) ? $fetch['rows'] : [];
	$result['fetched_rows'] = count($rows);

	$normalized = function_exists('lf_sitemap_specs_from_airtable_rows')
		? lf_sitemap_specs_from_airtable_rows($rows)
		: ['specs' => [], 'errors' => ['missing_sitemap_specs_normalizer'], 'invalid' => 0];

	$specs = is_array($normalized['specs'] ?? null) ? $normalized['specs'] : [];
	$errors = is_array($normalized['errors'] ?? null) ? $normalized['errors'] : [];
	$result['invalid'] = (int) ($normalized['invalid'] ?? 0);
	$site_niche = lf_sitemap_sync_get_site_niche_slug();
	$result['niche'] = $site_niche;
	if ($site_niche !== '') {
		$specs = array_values(array_filter($specs, static function ($spec) use ($site_niche): bool {
			if (!is_array($spec)) {
				return false;
			}
			$spec_niche = sanitize_title((string) ($spec['niche'] ?? ''));
			return $spec_niche !== '' && $spec_niche === $site_niche;
		}));
	}
	$result['normalized'] = count($specs);
	if ($site_niche !== '' && $result['fetched_rows'] > 0 && $result['normalized'] === 0) {
		$errors[] = 'no_specs_for_site_niche';
	}

	$city = lf_sitemap_sync_get_primary_city();
	$result['city'] = $city;

	$settings = lf_sitemap_sync_get_publish_settings();
	$ratio = (float) $settings['ratio'];
	$unpublished_mode = (string) $settings['unpublished_mode'];

	// Compute planned publish/draft per spec.
	$sortable = [];
	foreach ($specs as $i => $spec) {
		if (!is_array($spec)) {
			continue;
		}
		$priority = isset($spec['priority']) ? (float) $spec['priority'] : 0.0;
		$sortable[] = ['i' => (int) $i, 'priority' => $priority];
	}
	usort($sortable, static function (array $a, array $b): int {
		if ($a['priority'] === $b['priority']) return 0;
		return $a['priority'] > $b['priority'] ? -1 : 1;
	});
	$publish_count = (int) ceil(count($sortable) * $ratio);
	$publish_set = [];
	foreach ($sortable as $rank => $row) {
		if ($rank < $publish_count) {
			$publish_set[(int) $row['i']] = true;
		}
	}

	$index = [];
	$cache_specs = [];

	foreach ($specs as $i => $spec) {
		if (!is_array($spec)) {
			continue;
		}

		$slug_template = (string) ($spec['slug_template'] ?? '');
		$niche = (string) ($spec['niche'] ?? '');
		$key = function_exists('lf_sitemap_spec_key') ? lf_sitemap_spec_key($niche, $slug_template) : hash('sha256', strtolower(trim($niche)) . ':' . strtolower(trim($slug_template)));

		$resolved = function_exists('lf_sitemap_resolve_slug_template')
			? lf_sitemap_resolve_slug_template($slug_template, $city)
			: ['ok' => false, 'slug' => '/', 'error' => 'missing_slug_resolver'];

		$resolved_slug = (string) ($resolved['slug'] ?? '/');
		$resolved_ok = !empty($resolved['ok']);
		$resolved_err = (string) ($resolved['error'] ?? '');

		$is_core = lf_sitemap_sync_is_core_hub($resolved_slug, $slug_template);
		$planned_status = $is_core || !empty($publish_set[(int) $i]) ? 'publish' : $unpublished_mode;
		if (!$resolved_ok) {
			$planned_status = $unpublished_mode;
			$errors[] = sprintf('spec_%d: %s', (int) $i, $resolved_err !== '' ? $resolved_err : 'slug_resolve_failed');
		}

		$enriched = $spec;
		$enriched['sitemap_key'] = $key;
		$enriched['slug_resolved'] = $resolved_slug;
		$enriched['post_type'] = 'page';
		$enriched['post_status'] = $planned_status;

		$cache_specs[] = $enriched;

		$upsert = lf_sitemap_sync_upsert_page($enriched);
		if (empty($upsert['ok'])) {
			$errors[] = sprintf('spec_%d: upsert_failed: %s', (int) $i, (string) ($upsert['error'] ?? 'unknown_error'));
			$result['skipped']++;
			continue;
		}
		if (!empty($upsert['created'])) {
			$result['created']++;
		} elseif (!empty($upsert['updated'])) {
			$result['updated']++;
		} else {
			$result['skipped']++;
		}

		$post_id = (int) ($upsert['post_id'] ?? 0);
		if ($post_id > 0) {
			$post = get_post($post_id);
			$index[$resolved_slug] = [
				'post_id' => $post_id,
				'status' => $post instanceof WP_Post ? (string) $post->post_status : $planned_status,
				'type' => 'page',
			];
		}
	}

	// Persist options (JSON).
	update_option('lf_airtable_sitemap_cache', lf_sitemap_sync_encode_cache($cache_specs));
	update_option('lf_airtable_sitemap_cache_at', (string) time());
	update_option('lf_sitemap_page_index', wp_json_encode($index, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
	$result['index_count'] = count($index);

	// Error code extraction.
	$codes = [];
	foreach ($errors as $err) {
		$err = (string) $err;
		$parts = explode(':', $err, 3);
		$code = trim((string) ($parts[1] ?? $parts[0]));
		if ($code !== '') {
			$codes[] = $code;
		}
	}
	$codes = array_values(array_unique($codes));

	$result['errors'] = array_values(array_filter(array_map('strval', $errors)));
	$result['error_codes'] = array_slice($codes, 0, 20);
	// Consider reconcile "needs attention" if it produced any errors or zero usable specs.
	$result['ok'] = empty($errors) && $result['normalized'] > 0;
	return $result;
}

