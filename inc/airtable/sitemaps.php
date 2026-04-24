<?php
/**
 * Airtable Sitemaps fetch + normalization.
 *
 * @package LeadsForward_Core
 * @since 0.1.79
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Fetch Airtable rows from the configured Sitemaps table/view.
 *
 * @return array{ok:bool, rows:list<array<string,mixed>>, error:string}
 */
function lf_airtable_sitemaps_fetch_rows(): array {
	$settings = function_exists('lf_ai_studio_airtable_get_settings') ? lf_ai_studio_airtable_get_settings() : [];
	$enabled = !empty($settings['enabled']);
	$pat = (string) ($settings['pat'] ?? '');
	$base_id = (string) ($settings['base_id'] ?? '');
	if (!$enabled || $pat === '' || $base_id === '') {
		return ['ok' => false, 'rows' => [], 'error' => 'airtable_not_configured'];
	}

	$table = (string) get_option('lf_ai_airtable_sitemaps_table', 'Sitemaps');
	$view = (string) get_option('lf_ai_airtable_sitemaps_view', 'Primary View');
	$table = trim($table) !== '' ? trim($table) : 'Sitemaps';
	$view = trim($view) !== '' ? trim($view) : 'Primary View';

	$resolved = function_exists('lf_ai_studio_airtable_resolve_table_view')
		? lf_ai_studio_airtable_resolve_table_view([
			'pat' => $pat,
			'base_id' => $base_id,
			'table' => $table,
			'view' => $view,
		])
		: [];
	if (!empty($resolved['error'])) {
		return ['ok' => false, 'rows' => [], 'error' => (string) $resolved['error']];
	}

	$table_id = (string) ($resolved['table_id'] ?? $table);
	$view_id = (string) ($resolved['view'] ?? '');

	$base_url = function_exists('lf_ai_studio_airtable_base_url')
		? lf_ai_studio_airtable_base_url(['base_id' => $base_id, 'table' => $table_id])
		: '';
	if ($base_url === '') {
		return ['ok' => false, 'rows' => [], 'error' => 'airtable_base_url_failed'];
	}

	$params = ['pageSize' => 100];
	if ($view_id !== '') {
		$params['view'] = $view_id;
	}

	$rows = [];
	$offset = '';
	$pages = 0;
	$max_pages = 50;
	do {
		$page_params = $params;
		if ($offset !== '') {
			$page_params['offset'] = $offset;
		}
		$response = function_exists('lf_ai_studio_airtable_get')
			? lf_ai_studio_airtable_get($base_url, $page_params, $pat)
			: ['error' => 'airtable_get_missing'];
		if (!empty($response['error'])) {
			return ['ok' => false, 'rows' => [], 'error' => (string) $response['error']];
		}
		$data = is_array($response['data'] ?? null) ? $response['data'] : [];
		foreach ((array) ($data['records'] ?? []) as $record) {
			if (!is_array($record)) {
				continue;
			}
			$fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
			$rows[] = $fields;
		}
		$offset = isset($data['offset']) ? (string) $data['offset'] : '';
		$pages++;
	} while ($offset !== '' && $pages < $max_pages);

	return ['ok' => true, 'rows' => $rows, 'error' => ''];
}

/**
 * Normalize Airtable rows into a preliminary PageSpec array with strict validation.
 *
 * Each spec is a plain associative array for now (Task 2 introduces typed helpers).
 *
 * @param list<array<string,mixed>> $rows
 * @return array{specs:list<array<string,mixed>>, errors:list<string>, invalid:int}
 */
function lf_sitemap_specs_from_airtable_rows(array $rows): array {
	$specs = [];
	$errors = [];
	$invalid = 0;

	$allowed_menu_groups = [
		'home' => 'Home',
		'about' => 'About',
		'services' => 'Services',
		'service areas' => 'Service Areas',
		'more' => 'More',
	];

	foreach ($rows as $i => $row) {
		if (!is_array($row)) {
			$errors[] = sprintf('row_%d: invalid_row_type', (int) $i);
			$invalid++;
			continue;
		}

		$get = static function (array $fields, string $key): string {
			return function_exists('lf_ai_studio_airtable_string_field')
				? lf_ai_studio_airtable_string_field($fields, $key)
				: trim((string) ($fields[$key] ?? ''));
		};

		$title = $get($row, 'Page title | Niche');
		if ($title === '') {
			$title = $get($row, 'Page title (service)');
		}
		$niche = $get($row, 'Niche');
		$priority_raw = $get($row, 'Priority');
		$primary_keyword = $get($row, 'Keyword');
		$menu_group_raw = $get($row, 'menu group');
		$menu_hierarchy = $get($row, 'Menu hiearchy');
		$slug_template = $get($row, 'Slug');

		$priority = is_numeric($priority_raw) ? (float) $priority_raw : 0.0;
		$menu_group_key = strtolower(trim($menu_group_raw));
		$menu_group = $menu_group_key !== '' && isset($allowed_menu_groups[$menu_group_key])
			? $allowed_menu_groups[$menu_group_key]
			: ($menu_group_raw !== '' ? trim($menu_group_raw) : '');

		$row_errors = [];
		if (trim($slug_template) === '') {
			$row_errors[] = 'missing_slug';
		}
		if (trim($title) === '') {
			$row_errors[] = 'missing_title';
		}
		if (trim($primary_keyword) === '') {
			$row_errors[] = 'missing_keyword';
		}
		if (trim($menu_group) === '') {
			$row_errors[] = 'missing_menu_group';
		} elseif (!isset($allowed_menu_groups[$menu_group_key])) {
			$row_errors[] = 'unknown_menu_group';
		}

		if (!empty($row_errors)) {
			foreach ($row_errors as $code) {
				$errors[] = sprintf('row_%d: %s', (int) $i, $code);
			}
			$invalid++;
			continue;
		}

		$specs[] = [
			'title' => $title,
			'niche' => $niche,
			'priority' => $priority,
			'primary_keyword' => $primary_keyword,
			'menu_group' => $menu_group,
			'menu_hierarchy' => $menu_hierarchy,
			'slug_template' => $slug_template,
		];
	}

	return [
		'specs' => $specs,
		'errors' => array_values(array_filter(array_map('strval', $errors))),
		'invalid' => $invalid,
	];
}

/**
 * Small debug summary helper for quick sanity checks.
 *
 * @param array{specs?:list<array<string,mixed>>, errors?:list<string>, invalid?:int} $normalized
 * @return array{total:int, invalid:int, city_token:int}
 */
function lf_sitemap_sync_debug_summary(array $normalized): array {
	$specs = is_array($normalized['specs'] ?? null) ? $normalized['specs'] : [];
	$invalid = isset($normalized['invalid']) ? (int) $normalized['invalid'] : 0;
	$city_token = 0;
	foreach ($specs as $spec) {
		if (!is_array($spec)) {
			continue;
		}
		$template = trim((string) ($spec['slug_template'] ?? ''));
		if ($template !== '' && strpos($template, '{city}') !== false) {
			$city_token++;
		}
	}
	return [
		'total' => count($specs),
		'invalid' => $invalid,
		'city_token' => $city_token,
	];
}

