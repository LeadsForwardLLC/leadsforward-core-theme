<?php
/**
 * AI Studio Airtable integration.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('wp_ajax_lf_ai_airtable_search', 'lf_ai_studio_airtable_search');
add_action('wp_ajax_lf_ai_airtable_generate', 'lf_ai_studio_airtable_generate');

function lf_ai_studio_airtable_default_field_map(): array {
	return [
		'project' => 'Project',
		'phone' => 'Phone Number',
		'email' => 'Client Email',
		'street' => 'Street Address',
		'city' => 'City',
		'state' => 'State',
		'zip' => 'Zip',
		'primary_city' => 'City',
		'niche' => 'Niche',
		'niche_slug' => 'Niche Slug',
		'site_style' => 'Site Style',
		'primary_keyword' => 'Primary KWs',
		'secondary_keywords' => 'KW-Top 10',
		'secondary_keywords_all' => 'KW-All',
		'secondary_keywords_focus' => 'KW-Focus',
		'service_areas_list' => 'Service Areas',
		'services_json' => 'Services JSON',
		'service_areas_json' => 'Service Areas JSON',
		'manifest_json' => 'Manifest JSON',
		'website_url' => 'Website URL',
		'business_category' => 'Business Category',
		'business_hours' => 'Hours',
		'google_name' => 'Google Name',
		'google_account' => 'Google Account',
		'gmails' => 'Gmails',
		'gbp_cid_primary' => 'GMB CID Primary',
		'gbp_cid' => 'GMB CID',
		'facebook' => 'Facebook',
		'x' => 'X',
		'instagram' => 'Instagram',
		'youtube' => 'YouTube',
		'pinterest' => 'Pinterest',
		'houzz' => 'Houzz',
		'tumblr' => 'Tumblr',
		'yelp' => 'Yelp',
		'bing' => 'Bing',
		'foundation_year' => 'Foundation Year',
	];
}

function lf_ai_studio_airtable_get_settings(): array {
	$defaults = lf_ai_studio_airtable_default_field_map();
	$field_map = get_option('lf_ai_airtable_field_map', []);
	$field_map = is_array($field_map) ? $field_map : [];
	$normalized_map = [];
	foreach ($defaults as $key => $label) {
		$value = isset($field_map[$key]) ? trim((string) $field_map[$key]) : '';
		$normalized_map[$key] = $value !== '' ? $value : $label;
	}

	$table = (string) get_option('lf_ai_airtable_table', 'Business Info');
	$view = (string) get_option('lf_ai_airtable_view', 'Global Sync View (ACTIVE)');

	return [
		'enabled' => get_option('lf_ai_airtable_enabled', '0') === '1',
		'pat' => (string) get_option('lf_ai_airtable_pat', ''),
		'base_id' => (string) get_option('lf_ai_airtable_base', ''),
		'table' => $table !== '' ? $table : 'Business Info',
		'view' => $view,
		'fields' => $normalized_map,
	];
}

function lf_ai_studio_airtable_search(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_send_json_error(['message' => __('Insufficient permissions.', 'leadsforward-core')], 403);
	}
	check_ajax_referer('lf_ai_airtable', 'nonce');

	$query = isset($_GET['query']) ? sanitize_text_field(wp_unslash((string) $_GET['query'])) : '';
	$result = lf_ai_studio_airtable_search_records($query);
	if (!empty($result['error'])) {
		wp_send_json_error(['message' => $result['error']], 400);
	}

	wp_send_json_success([
		'records' => $result['records'] ?? [],
	]);
}

function lf_ai_studio_airtable_generate(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_send_json_error(['message' => __('Insufficient permissions.', 'leadsforward-core')], 403);
	}
	check_ajax_referer('lf_ai_airtable', 'nonce');

	$record_id = isset($_POST['record_id']) ? sanitize_text_field(wp_unslash((string) $_POST['record_id'])) : '';
	if ($record_id === '') {
		wp_send_json_error(['message' => __('Missing Airtable record ID.', 'leadsforward-core')], 400);
	}

	$record_result = lf_ai_studio_airtable_fetch_record($record_id);
	if (!empty($record_result['error'])) {
		wp_send_json_error(['message' => $record_result['error']], 400);
	}

	$record = $record_result['record'] ?? [];
	$settings = lf_ai_studio_airtable_get_settings();
	$build = lf_ai_studio_airtable_record_to_manifest($record, $settings);
	if (!empty($build['errors'])) {
		update_option('lf_ai_studio_manifest_errors', $build['errors'], false);
		wp_send_json_error(['message' => __('Manifest validation failed.', 'leadsforward-core'), 'errors' => $build['errors']], 400);
	}

	$manifest = $build['manifest'] ?? [];
	$errors = lf_ai_studio_validate_manifest($manifest);
	if (!empty($errors)) {
		update_option('lf_ai_studio_manifest_errors', $errors, false);
		wp_send_json_error(['message' => __('Manifest validation failed.', 'leadsforward-core'), 'errors' => $errors], 400);
	}

	$normalized = lf_ai_studio_normalize_manifest($manifest);
	update_option('lf_site_manifest', $normalized, false);
	delete_option('lf_ai_studio_manifest_errors');
	lf_ai_studio_sync_manifest_posts($normalized);

	$result = lf_ai_studio_run_generation();
	if (!empty($result['error'])) {
		$message = sprintf(__('Generation failed: %s', 'leadsforward-core'), (string) $result['error']);
		update_option('lf_ai_studio_manifest_errors', [$message], false);
		wp_send_json_error(['message' => $message], 400);
	}

	$redirect = admin_url('admin.php?page=lf-ops&manifest=1');
	if (!empty($result['job_id'])) {
		$redirect = add_query_arg('job', (string) $result['job_id'], $redirect);
	}
	wp_send_json_success([
		'job_id' => $result['job_id'] ?? 0,
		'redirect' => $redirect,
	]);
}

function lf_ai_studio_airtable_search_records(string $query): array {
	$settings = lf_ai_studio_airtable_get_settings();
	$ready = lf_ai_studio_airtable_is_ready($settings);
	if (!$ready['ready']) {
		return ['error' => $ready['message']];
	}

	$resolved = lf_ai_studio_airtable_resolve_table_view($settings);
	if (!empty($resolved['error'])) {
		return ['error' => $resolved['error']];
	}
	$base_url = lf_ai_studio_airtable_base_url([
		'base_id' => $settings['base_id'],
		'table' => $resolved['table_id'],
	]);
	$params = [
		'pageSize' => 20,
	];
	if (!empty($resolved['view'])) {
		$params['view'] = $resolved['view'];
	}
	if ($query !== '') {
		$field = $settings['fields']['project'];
		$needle = str_replace('"', '\"', $query);
		$params['filterByFormula'] = sprintf('FIND(LOWER("%s"), LOWER({%s}))', $needle, $field);
	}

	$response = lf_ai_studio_airtable_get($base_url, $params, $settings['pat']);
	if (!empty($response['error'])) {
		return ['error' => $response['error']];
	}

	$records = [];
	foreach ((array) ($response['data']['records'] ?? []) as $record) {
		$fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
		$name = lf_ai_studio_airtable_string_field($fields, $settings['fields']['project']);
		if ($name === '') {
			continue;
		}
		$city = lf_ai_studio_airtable_string_field($fields, $settings['fields']['city']);
		$state = lf_ai_studio_airtable_string_field($fields, $settings['fields']['state']);
		$niche = lf_ai_studio_airtable_string_field($fields, $settings['fields']['niche']);
		$records[] = [
			'id' => (string) ($record['id'] ?? ''),
			'name' => $name,
			'city' => $city,
			'state' => $state,
			'niche' => $niche,
		];
	}

	return [
		'records' => $records,
		'notice' => $resolved['notice'] ?? '',
	];
}

function lf_ai_studio_airtable_fetch_record(string $record_id): array {
	$settings = lf_ai_studio_airtable_get_settings();
	$ready = lf_ai_studio_airtable_is_ready($settings);
	if (!$ready['ready']) {
		return ['error' => $ready['message']];
	}
	$resolved = lf_ai_studio_airtable_resolve_table_view($settings);
	if (!empty($resolved['error'])) {
		return ['error' => $resolved['error']];
	}
	$base_url = lf_ai_studio_airtable_base_url([
		'base_id' => $settings['base_id'],
		'table' => $resolved['table_id'],
	]);
	$record_url = trailingslashit($base_url) . rawurlencode($record_id);

	$response = lf_ai_studio_airtable_get($record_url, [], $settings['pat']);
	if (!empty($response['error'])) {
		return ['error' => $response['error']];
	}
	$record = $response['data'] ?? [];
	if (!is_array($record)) {
		return ['error' => __('Invalid Airtable response.', 'leadsforward-core')];
	}
	return ['record' => $record];
}

function lf_ai_studio_airtable_record_to_manifest(array $record, array $settings): array {
	$fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
	$map = $settings['fields'] ?? [];
	$errors = [];

	$manifest_json = lf_ai_studio_airtable_string_field($fields, $map['manifest_json'] ?? '');
	if ($manifest_json !== '') {
		$decoded = json_decode($manifest_json, true);
		if (is_array($decoded)) {
			return ['manifest' => $decoded, 'errors' => []];
		}
		$errors[] = __('Manifest JSON field is not valid JSON.', 'leadsforward-core');
	}

	$business_name = lf_ai_studio_airtable_string_field($fields, $map['project'] ?? '');
	$legal_name = $business_name;
	$phone = lf_ai_studio_airtable_string_field($fields, $map['phone'] ?? '');
	$email = lf_ai_studio_airtable_string_field($fields, $map['email'] ?? '');
	if ($email === '') {
		$email = lf_ai_studio_airtable_first_email(
			lf_ai_studio_airtable_string_field($fields, $map['google_account'] ?? '')
		);
	}
	if ($email === '') {
		$email = lf_ai_studio_airtable_first_email(
			lf_ai_studio_airtable_string_field($fields, $map['gmails'] ?? '')
		);
	}
	$street = lf_ai_studio_airtable_string_field($fields, $map['street'] ?? '');
	$city = lf_ai_studio_airtable_string_field($fields, $map['city'] ?? '');
	$state = lf_ai_studio_airtable_string_field($fields, $map['state'] ?? '');
	$zip = lf_ai_studio_airtable_string_field($fields, $map['zip'] ?? '');
	$primary_city = lf_ai_studio_airtable_string_field($fields, $map['primary_city'] ?? '');
	$niche = lf_ai_studio_airtable_string_field($fields, $map['niche'] ?? '');
	$niche_slug = lf_ai_studio_airtable_string_field($fields, $map['niche_slug'] ?? '');
	$site_style = lf_ai_studio_airtable_string_field($fields, $map['site_style'] ?? '');
	$primary_keyword = lf_ai_studio_airtable_string_field($fields, $map['primary_keyword'] ?? '');
	$primary_keyword = lf_ai_studio_airtable_pick_primary_keyword($primary_keyword);
	if ($primary_keyword === '') {
		$primary_keyword = lf_ai_studio_airtable_pick_primary_keyword(
			lf_ai_studio_airtable_string_field($fields, $map['secondary_keywords_focus'] ?? '')
		);
	}
	$secondary_keywords = lf_ai_studio_airtable_keywords_field($fields, $map['secondary_keywords'] ?? '');
	$secondary_keywords = array_merge(
		$secondary_keywords,
		lf_ai_studio_airtable_keywords_field($fields, $map['secondary_keywords_all'] ?? ''),
		lf_ai_studio_airtable_keywords_field($fields, $map['secondary_keywords_focus'] ?? '')
	);
	$secondary_keywords = array_values(array_unique(array_filter($secondary_keywords)));
	$keyword_pool = array_values(array_unique(array_filter(array_merge([$primary_keyword], $secondary_keywords))));
	if ($primary_keyword === '') {
		$primary_keyword = trim(sprintf('%s %s %s', $niche, $city, $state));
	}

	$services = lf_ai_studio_airtable_json_array_field($fields, $map['services_json'] ?? '', 'Services JSON', $errors);
	$service_areas = lf_ai_studio_airtable_json_array_field($fields, $map['service_areas_json'] ?? '', 'Service Areas JSON', $errors);
	$service_area_list = lf_ai_studio_airtable_string_field($fields, $map['service_areas_list'] ?? '');

	$website_url = lf_ai_studio_airtable_string_field($fields, $map['website_url'] ?? '');
	$business_category = lf_ai_studio_airtable_string_field($fields, $map['business_category'] ?? '');
	$business_hours = lf_ai_studio_airtable_string_field($fields, $map['business_hours'] ?? '');
	$google_name = lf_ai_studio_airtable_string_field($fields, $map['google_name'] ?? '');
	$gbp_cid_primary = lf_ai_studio_airtable_string_field($fields, $map['gbp_cid_primary'] ?? '');
	$gbp_cid = lf_ai_studio_airtable_string_field($fields, $map['gbp_cid'] ?? '');
	$facebook = lf_ai_studio_airtable_string_field($fields, $map['facebook'] ?? '');
	$x_url = lf_ai_studio_airtable_string_field($fields, $map['x'] ?? '');
	$instagram = lf_ai_studio_airtable_string_field($fields, $map['instagram'] ?? '');
	$youtube = lf_ai_studio_airtable_string_field($fields, $map['youtube'] ?? '');
	$pinterest = lf_ai_studio_airtable_string_field($fields, $map['pinterest'] ?? '');
	$houzz = lf_ai_studio_airtable_string_field($fields, $map['houzz'] ?? '');
	$tumblr = lf_ai_studio_airtable_string_field($fields, $map['tumblr'] ?? '');
	$yelp = lf_ai_studio_airtable_string_field($fields, $map['yelp'] ?? '');
	$bing = lf_ai_studio_airtable_string_field($fields, $map['bing'] ?? '');
	$foundation_year = lf_ai_studio_airtable_string_field($fields, $map['foundation_year'] ?? '');

	if (empty($service_areas) && $service_area_list !== '') {
		$service_areas = lf_ai_studio_airtable_build_service_areas_from_list($service_area_list, $state, $niche);
	}

	if ($business_name === '') {
		$errors[] = __('Missing Project field in Airtable.', 'leadsforward-core');
	}
	if ($phone === '') {
		$errors[] = __('Missing Phone Number field in Airtable.', 'leadsforward-core');
	}
	if ($email === '') {
		$errors[] = __('Missing Email field in Airtable (Client Email or Google Account).', 'leadsforward-core');
	}
	if ($city === '' || $state === '') {
		$errors[] = __('Missing required location fields in Airtable (City, State).', 'leadsforward-core');
	}
	if ($primary_city === '') {
		$primary_city = $city;
	}
	if ($niche === '') {
		$errors[] = __('Missing Niche field in Airtable.', 'leadsforward-core');
	}
	if ($primary_keyword === '') {
		$errors[] = __('Missing Primary Keyword field in Airtable.', 'leadsforward-core');
	}
	if (empty($services) && !empty($keyword_pool)) {
		$services = lf_ai_studio_airtable_build_services_from_keywords($keyword_pool, $primary_city, $state, $business_name, $niche);
	}
	if (empty($services)) {
		$niche_slug_guess = lf_ai_studio_airtable_resolve_niche_slug($niche, $niche_slug);
		$services = lf_ai_studio_airtable_build_services_from_niche($niche_slug_guess, $primary_city, $state, $business_name);
	}
	if (empty($services)) {
		$services = lf_ai_studio_airtable_build_generic_services($niche, $primary_city, $state, $business_name);
	}
	if (empty($service_areas) && $primary_city !== '') {
		$service_areas = lf_ai_studio_airtable_build_service_areas_from_list($primary_city, $state, $niche);
	}
	if (empty($services)) {
		$errors[] = __('Services JSON is missing and no niche defaults were found.', 'leadsforward-core');
	}
	if (empty($service_areas)) {
		$errors[] = __('Service areas are missing. Add Service Areas in Airtable or provide Service Areas JSON.', 'leadsforward-core');
	}

	if (!empty($errors)) {
		return ['manifest' => [], 'errors' => $errors];
	}

	$variation_seed = 'airtable-' . ($record['id'] ?? wp_generate_uuid4());

	$niche_slug_final = lf_ai_studio_airtable_resolve_niche_slug($niche, $niche_slug);

	$manifest = [
		'business' => [
			'name' => $business_name,
			'legal_name' => $legal_name,
			'phone' => $phone,
			'email' => $email,
			'address' => [
				'street' => $street,
				'city' => $city,
				'state' => $state,
				'zip' => $zip,
			],
			'primary_city' => $primary_city,
			'niche' => $niche,
			'niche_slug' => $niche_slug_final,
			'site_style' => $site_style !== '' ? $site_style : 'premium',
			'variation_seed' => $variation_seed,
			'website_url' => $website_url,
			'hours' => $business_hours,
			'category' => $business_category,
			'place_name' => $google_name,
			'place_id' => $gbp_cid_primary !== '' ? $gbp_cid_primary : $gbp_cid,
			'founding_year' => $foundation_year,
			'social' => [
				'facebook' => $facebook,
				'instagram' => $instagram,
				'youtube' => $youtube,
				'linkedin' => '',
				'tiktok' => '',
				'x' => $x_url,
			],
			'same_as' => array_values(array_filter([
				$website_url,
				$facebook,
				$instagram,
				$youtube,
				$x_url,
				$pinterest,
				$houzz,
				$tumblr,
				$yelp,
				$bing,
			])),
		],
		'homepage' => [
			'primary_keyword' => $primary_keyword,
			'secondary_keywords' => $secondary_keywords,
		],
		'services' => $services,
		'service_areas' => $service_areas,
		'global' => [
			'global_cta_override' => false,
			'custom_global_cta' => [
				'headline' => __('Get a fast, no-obligation estimate', 'leadsforward-core'),
				'subheadline' => __('Talk to a local expert and get clear next steps today.', 'leadsforward-core'),
			],
		],
	];

	return ['manifest' => $manifest, 'errors' => []];
}

function lf_ai_studio_airtable_is_ready(array $settings): array {
	if (empty($settings['enabled'])) {
		return ['ready' => false, 'message' => __('Airtable is not enabled.', 'leadsforward-core')];
	}
	if ($settings['pat'] === '' || $settings['base_id'] === '' || $settings['table'] === '') {
		return ['ready' => false, 'message' => __('Airtable PAT, Base ID, and Table are required.', 'leadsforward-core')];
	}
	return ['ready' => true, 'message' => ''];
}

function lf_ai_studio_airtable_base_url(array $settings): string {
	$base_id = rawurlencode($settings['base_id']);
	$table = rawurlencode($settings['table']);
	return "https://api.airtable.com/v0/{$base_id}/{$table}";
}

function lf_ai_studio_airtable_get(string $url, array $params, string $pat): array {
	$url = add_query_arg(array_filter($params, static function ($value) {
		return $value !== null && $value !== '';
	}), $url);

	$response = wp_remote_get($url, [
		'timeout' => 20,
		'headers' => [
			'Authorization' => 'Bearer ' . $pat,
			'Content-Type' => 'application/json',
		],
	]);
	if (is_wp_error($response)) {
		return ['error' => $response->get_error_message()];
	}
	$code = (int) wp_remote_retrieve_response_code($response);
	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);
	if ($code >= 400) {
		$message = __('Airtable request failed.', 'leadsforward-core');
		if (is_array($data) && !empty($data['error']['message'])) {
			$message .= ' ' . $data['error']['message'];
		}
		return ['error' => $message];
	}
	if (!is_array($data)) {
		return ['error' => __('Airtable response was not valid JSON.', 'leadsforward-core')];
	}
	return ['data' => $data];
}

function lf_ai_studio_airtable_get_tables(string $base_id, string $pat): array {
	$cache_key = 'lf_ai_airtable_tables_' . md5($base_id);
	$cached = get_transient($cache_key);
	if (is_array($cached)) {
		return ['tables' => $cached];
	}
	$url = sprintf('https://api.airtable.com/v0/meta/bases/%s/tables', rawurlencode($base_id));
	$response = lf_ai_studio_airtable_get($url, [], $pat);
	if (!empty($response['error'])) {
		return ['error' => $response['error']];
	}
	$tables = $response['data']['tables'] ?? [];
	if (!is_array($tables)) {
		return ['error' => __('Airtable schema response was invalid.', 'leadsforward-core')];
	}
	set_transient($cache_key, $tables, 5 * MINUTE_IN_SECONDS);
	return ['tables' => $tables];
}

function lf_ai_studio_airtable_resolve_table_view(array $settings): array {
	$tables_response = lf_ai_studio_airtable_get_tables($settings['base_id'], $settings['pat']);
	if (!empty($tables_response['error'])) {
		return ['error' => $tables_response['error']];
	}
	$tables = $tables_response['tables'] ?? [];
	if (!is_array($tables) || empty($tables)) {
		return ['error' => __('No Airtable tables found for this base.', 'leadsforward-core')];
	}

	$input_table = trim((string) ($settings['table'] ?? ''));
	$input_view = trim((string) ($settings['view'] ?? ''));

	$table = lf_ai_studio_airtable_match_table($tables, $input_table);
	$view = null;
	$notice = '';

	if (!$table && $input_table !== '') {
		$view_hit = lf_ai_studio_airtable_match_view($tables, $input_table);
		if ($view_hit) {
			$table = $view_hit['table'];
			$view = $view_hit['view'];
			$notice = __('Detected table/view swap. Using view from Table Name input.', 'leadsforward-core');
		}
	}

	if (!$table && count($tables) === 1) {
		$table = $tables[0];
		$notice = __('Using the only table in this base.', 'leadsforward-core');
	}

	if (!$table) {
		$table_names = array_map(static function ($t) {
			return (string) ($t['name'] ?? '');
		}, $tables);
		return ['error' => sprintf(__('Table not found. Available tables: %s', 'leadsforward-core'), implode(', ', array_filter($table_names)))];
	}

	if ($input_view !== '') {
		$view = lf_ai_studio_airtable_match_view_in_table($table, $input_view);
		if (!$view) {
			$view_hit = lf_ai_studio_airtable_match_view($tables, $input_view);
			if ($view_hit) {
				$table = $view_hit['table'];
				$view = $view_hit['view'];
				$notice = __('Detected view name assigned to the wrong table. Auto-corrected.', 'leadsforward-core');
			}
		}
	}

	return [
		'table_id' => (string) ($table['id'] ?? $table['name'] ?? ''),
		'table_name' => (string) ($table['name'] ?? ''),
		'view' => $view ? (string) ($view['id'] ?? $view['name'] ?? '') : '',
		'notice' => $notice,
	];
}

function lf_ai_studio_airtable_match_table(array $tables, string $needle): ?array {
	if ($needle === '') {
		return null;
	}
	foreach ($tables as $table) {
		$name = (string) ($table['name'] ?? '');
		$id = (string) ($table['id'] ?? '');
		if (strcasecmp($needle, $name) === 0 || strcasecmp($needle, $id) === 0) {
			return $table;
		}
	}
	return null;
}

function lf_ai_studio_airtable_match_view(array $tables, string $needle): ?array {
	if ($needle === '') {
		return null;
	}
	foreach ($tables as $table) {
		$view = lf_ai_studio_airtable_match_view_in_table($table, $needle);
		if ($view) {
			return ['table' => $table, 'view' => $view];
		}
	}
	return null;
}

function lf_ai_studio_airtable_match_view_in_table(array $table, string $needle): ?array {
	$views = is_array($table['views'] ?? null) ? $table['views'] : [];
	foreach ($views as $view) {
		$name = (string) ($view['name'] ?? '');
		$id = (string) ($view['id'] ?? '');
		if (strcasecmp($needle, $name) === 0 || strcasecmp($needle, $id) === 0) {
			return $view;
		}
	}
	return null;
}

function lf_ai_studio_airtable_string_field(array $fields, string $key): string {
	if ($key === '' || !array_key_exists($key, $fields)) {
		return '';
	}
	$value = $fields[$key];
	if (is_array($value)) {
		$parts = array_map('strval', $value);
		return trim(implode(', ', array_filter($parts)));
	}
	return trim((string) $value);
}

function lf_ai_studio_airtable_keywords_field(array $fields, string $key): array {
	if ($key === '' || !array_key_exists($key, $fields)) {
		return [];
	}
	$value = $fields[$key];
	if (is_array($value)) {
		return array_values(array_filter(array_map('sanitize_text_field', $value)));
	}
	$raw = trim((string) $value);
	if ($raw === '') {
		return [];
	}
	$parts = preg_split('/\r\n|\r|\n|,/', $raw);
	return array_values(array_filter(array_map('sanitize_text_field', (array) $parts)));
}

function lf_ai_studio_airtable_pick_primary_keyword(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}
	$parts = preg_split('/\r\n|\r|\n|,/', $raw);
	if (!$parts) {
		return $raw;
	}
	$first = trim((string) $parts[0]);
	return $first !== '' ? $first : $raw;
}

function lf_ai_studio_airtable_build_service_areas_from_list(string $raw, string $state, string $niche): array {
	$parts = preg_split('/\r\n|\r|\n|,/', $raw);
	$areas = [];
	foreach ((array) $parts as $part) {
		$city = trim((string) $part);
		if ($city === '') {
			continue;
		}
		$areas[] = [
			'city' => $city,
			'state' => $state,
			'slug' => sanitize_title($city),
			'primary_keyword' => trim(sprintf('%s %s %s', $niche, $city, $state)),
		];
	}
	return $areas;
}

function lf_ai_studio_airtable_build_services_from_niche(string $niche_slug, string $city, string $state, string $business_name): array {
	if (!function_exists('lf_get_niche')) {
		return [];
	}
	$niche = lf_get_niche($niche_slug);
	if (!$niche || empty($niche['services']) || !is_array($niche['services'])) {
		return [];
	}
	$services = [];
	foreach ($niche['services'] as $service_name) {
		$name = trim((string) $service_name);
		if ($name === '') {
			continue;
		}
		$services[] = [
			'title' => $name,
			'slug' => sanitize_title($name),
			'primary_keyword' => trim(sprintf('%s %s %s', $name, $city, $state)),
			'secondary_keywords' => [],
			'custom_cta_context' => trim(sprintf('Get trusted %s from %s.', $name, $business_name)),
		];
	}
	return $services;
}

function lf_ai_studio_airtable_build_services_from_keywords(array $keywords, string $city, string $state, string $business_name, string $niche): array {
	$services = [];
	$seen = [];
	$city = trim($city);
	$state = trim($state);
	$niche = trim($niche);
	foreach ($keywords as $keyword) {
		$raw = trim((string) $keyword);
		if ($raw === '') {
			continue;
		}
		$candidate = strtolower($raw);
		$candidate = preg_replace('/\bnear me\b/i', '', $candidate);
		$candidate = preg_replace('/\bnear\b/i', '', $candidate);
		$candidate = preg_replace('/\bservices?\b/i', '', $candidate);
		$candidate = preg_replace('/\bcompany\b|\bcontractors?\b|\bexperts?\b/i', '', $candidate);
		$candidate = preg_replace('/\b\d{5}(?:-\d{4})?\b/', '', $candidate);
		if ($city !== '') {
			$candidate = preg_replace('/\b' . preg_quote(strtolower($city), '/') . '\b/i', '', $candidate);
		}
		if ($state !== '') {
			$candidate = preg_replace('/\b' . preg_quote(strtolower($state), '/') . '\b/i', '', $candidate);
		}
		if ($niche !== '') {
			$candidate = preg_replace('/\b' . preg_quote(strtolower($niche), '/') . '\b/i', $niche, $candidate);
		}
		$candidate = trim(preg_replace('/\s+/', ' ', $candidate));
		if ($candidate === '') {
			continue;
		}
		$title = ucwords($candidate);
		$slug = sanitize_title($title);
		if ($slug === '' || isset($seen[$slug])) {
			continue;
		}
		$seen[$slug] = true;
		$services[] = [
			'title' => $title,
			'slug' => $slug,
			'primary_keyword' => trim(sprintf('%s %s %s', $title, $city, $state)),
			'secondary_keywords' => [],
			'custom_cta_context' => trim(sprintf('Get trusted %s from %s.', $title, $business_name)),
		];
		if (count($services) >= 6) {
			break;
		}
	}
	return $services;
}

function lf_ai_studio_airtable_resolve_niche_slug(string $niche, string $niche_slug): string {
	$registry = function_exists('lf_get_niche_registry') ? lf_get_niche_registry() : [];
	$valid = is_array($registry) ? array_keys($registry) : [];
	$slug = '';
	if ($niche_slug !== '') {
		$slug = sanitize_title($niche_slug);
	} elseif ($niche !== '') {
		$slug = sanitize_title($niche);
	}
	if ($slug !== '' && in_array($slug, $valid, true)) {
		return $slug;
	}
	if (in_array('general', $valid, true)) {
		return 'general';
	}
	return '';
}

function lf_ai_studio_airtable_build_generic_services(string $niche, string $city, string $state, string $business_name): array {
	$title = trim($niche);
	if ($title === '') {
		$title = __('Service', 'leadsforward-core');
	}
	$primary_keyword = trim(sprintf('%s %s %s', $title, $city, $state));
	return [
		[
			'title' => $title,
			'slug' => sanitize_title($title),
			'primary_keyword' => $primary_keyword,
			'secondary_keywords' => [],
			'custom_cta_context' => trim(sprintf('Get trusted %s from %s.', $title, $business_name)),
		],
	];
}

function lf_ai_studio_airtable_json_array_field(array $fields, string $key, string $label, array &$errors): array {
	if ($key === '' || !array_key_exists($key, $fields)) {
		return [];
	}
	$value = $fields[$key];
	if (is_array($value)) {
		return $value;
	}
	$raw = trim((string) $value);
	if ($raw === '') {
		return [];
	}
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		$errors[] = sprintf(__('%s field must be valid JSON.', 'leadsforward-core'), $label);
		return [];
	}
	return $decoded;
}

function lf_ai_studio_airtable_first_email(string $raw): string {
	$raw = trim($raw);
	if ($raw === '') {
		return '';
	}
	if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $raw, $match)) {
		return strtolower($match[0]);
	}
	return '';
}
