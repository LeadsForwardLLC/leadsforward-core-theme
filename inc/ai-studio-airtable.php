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
add_action('wp_ajax_lf_ai_airtable_preview_manifest', 'lf_ai_studio_airtable_preview_manifest');
add_action('init', 'lf_ai_studio_airtable_schedule_reviews_sync');
add_action('init', 'lf_ai_studio_airtable_schedule_generation_jobs');
add_action('lf_ai_airtable_reviews_sync', 'lf_ai_studio_airtable_run_reviews_sync');
add_action('lf_ai_airtable_generation_queue', 'lf_ai_studio_airtable_process_generation_queue');
add_action('lf_ai_airtable_generation_reconcile', 'lf_ai_studio_airtable_run_reconcile');
add_action('switch_theme', 'lf_ai_studio_airtable_clear_reviews_sync');

function lf_ai_studio_airtable_default_field_map(): array {
	return [
		'project' => 'Project',
		'project_type' => 'Project Type',
		'phone' => 'Phone Number',
		// Global site contact email should use the business/domain email, not client contact.
		'email' => 'Domain Email',
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
		'services_list' => 'Services List',
		'services_raw' => 'Services',
		'services_json' => 'Services JSON',
		'service_areas_json' => 'Service Areas JSON',
		'manifest_json' => 'Manifest JSON',
		'website_url' => 'Website URL',
		'root_domain' => 'Root Domain',
		'business_category' => 'Business Category',
		'business_hours' => 'Hours',
		'business_short_description' => 'Short description',
		'google_name' => 'Google Name',
		'google_account' => 'Google Account',
		'google_account_name' => 'Google Account Name',
		'gmails' => 'Gmails',
		'gbp_url' => 'Google Business Profile URL',
		'place_id' => 'Place ID',
		'gbp_cid_primary' => 'GMB CID Primary',
		'gbp_cid' => 'GMB CID',
		'logo_url' => 'Logo URL',
		'google_drive_folder' => 'Google Drive Folder',
		'competitors' => 'Competitors',
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
		'ga_universal_id' => 'GA Universal ID',
		'ga4_account_id' => 'GA4 Account ID',
		'analytics_measurement_id' => 'Analytics Measurement ID',
		'gsc_account_user' => 'GSC Account User',
		'bing_account' => 'Bing',
	];
}

function lf_ai_studio_airtable_reviews_default_field_map(): array {
	return [
		'review_project' => 'Project Name (from CID)',
		'review_reviewer' => 'Author Title',
		'review_rating' => 'Star Rating',
		'review_text' => 'Review Text',
		'review_source' => 'Type (from CID)',
		'review_source_url' => 'Review Link',
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

	$review_defaults = lf_ai_studio_airtable_reviews_default_field_map();
	$review_field_map = get_option('lf_ai_airtable_reviews_field_map', []);
	$review_field_map = is_array($review_field_map) ? $review_field_map : [];
	$normalized_review_map = [];
	foreach ($review_defaults as $key => $label) {
		$value = isset($review_field_map[$key]) ? trim((string) $review_field_map[$key]) : '';
		$normalized_review_map[$key] = $value !== '' ? $value : $label;
	}
	$review_aliases = [
		'review_project' => [
			'Project' => $review_defaults['review_project'] ?? 'Project Name (from CID)',
		],
		'review_reviewer' => [
			'Reviewer Name' => 'Author Title',
			'Reviewer' => 'Author Title',
			'Review Author' => 'Author Title',
		],
		'review_rating' => [
			'Rating' => 'Star Rating',
			'Stars' => 'Star Rating',
		],
		'review_text' => [
			'Review' => 'Review Text',
			'Review Body' => 'Review Text',
		],
		'review_source' => [
			'Source' => 'Type (from CID)',
		],
		'review_source_url' => [
			'Source URL' => 'Review Link',
		],
	];
	foreach ($review_aliases as $key => $aliases) {
		$current = $normalized_review_map[$key] ?? '';
		if ($current !== '' && isset($aliases[$current])) {
			$normalized_review_map[$key] = $aliases[$current];
		}
	}

	$table = (string) get_option('lf_ai_airtable_table', 'Business Info');
	$view = (string) get_option('lf_ai_airtable_view', 'Global Sync View (ACTIVE)');
	$review_table = (string) get_option('lf_ai_airtable_reviews_table', 'Reviews');
	$review_view = (string) get_option('lf_ai_airtable_reviews_view', '');
	$sitemaps_table = (string) get_option('lf_ai_airtable_sitemaps_table', 'Sitemaps');
	$sitemaps_view = (string) get_option('lf_ai_airtable_sitemaps_view', 'Primary View');
	$default_review_project = (string) ($review_defaults['review_project'] ?? '');
	if (($normalized_review_map['review_project'] ?? '') === 'Project' && $default_review_project !== '' && $default_review_project !== 'Project') {
		$normalized_review_map['review_project'] = $default_review_project;
	}
	if ($review_view !== '' && ($normalized_review_map['review_project'] ?? '') !== '' && $review_view === $normalized_review_map['review_project']) {
		$review_view = '';
	}

	return [
		'enabled' => get_option('lf_ai_airtable_enabled', '1') === '1',
		'pat' => (string) get_option('lf_ai_airtable_pat', ''),
		'base_id' => (string) get_option('lf_ai_airtable_base', ''),
		'table' => $table !== '' ? $table : 'Business Info',
		'view' => $view,
		'fields' => $normalized_map,
		'reviews' => [
			'table' => $review_table,
			'view' => $review_view,
			'fields' => $normalized_review_map,
		],
		'sitemaps' => [
			'table' => $sitemaps_table !== '' ? $sitemaps_table : 'Sitemaps',
			'view' => $sitemaps_view !== '' ? $sitemaps_view : 'Primary View',
		],
	];
}

function lf_ai_studio_airtable_schedule_reviews_sync(): void {
	$hook = 'lf_ai_airtable_reviews_sync';
	$next = wp_next_scheduled($hook);
	$settings = lf_ai_studio_airtable_get_settings();
	$reviews_table = trim((string) ($settings['reviews']['table'] ?? ''));
	if (empty($settings['enabled']) || $reviews_table === '') {
		if ($next) {
			wp_unschedule_event($next, $hook);
		}
		return;
	}
	if (!$next) {
		wp_schedule_event(time() + 300, 'hourly', $hook);
	}
}

function lf_ai_studio_airtable_schedule_generation_jobs(): void {
	$queue_hook = 'lf_ai_airtable_generation_queue';
	$reconcile_hook = 'lf_ai_airtable_generation_reconcile';
	$next_reconcile = wp_next_scheduled($reconcile_hook);
	$settings = lf_ai_studio_airtable_get_settings();
	$autonomy_runtime = function_exists('lf_ai_autonomy_runtime_enabled')
		? lf_ai_autonomy_runtime_enabled()
		: (get_option('lf_ai_autonomy_enabled', '0') === '1');
	if (empty($settings['enabled']) || !$autonomy_runtime) {
		if ($next_reconcile) {
			wp_unschedule_event($next_reconcile, $reconcile_hook);
		}
		$next_queue = wp_next_scheduled($queue_hook);
		if ($next_queue) {
			wp_unschedule_event($next_queue, $queue_hook);
		}
		return;
	}
	if (!$next_reconcile) {
		wp_schedule_event(time() + 300, 'hourly', $reconcile_hook);
	}
}

function lf_ai_studio_airtable_clear_reviews_sync(): void {
	$hook = 'lf_ai_airtable_reviews_sync';
	$next = wp_next_scheduled($hook);
	if ($next) {
		wp_unschedule_event($next, $hook);
	}
	$queue_hook = 'lf_ai_airtable_generation_queue';
	$queue_next = wp_next_scheduled($queue_hook);
	if ($queue_next) {
		wp_unschedule_event($queue_next, $queue_hook);
	}
	$reconcile_hook = 'lf_ai_airtable_generation_reconcile';
	$reconcile_next = wp_next_scheduled($reconcile_hook);
	if ($reconcile_next) {
		wp_unschedule_event($reconcile_next, $reconcile_hook);
	}
}

function lf_ai_studio_airtable_run_reviews_sync(): void {
	$settings = lf_ai_studio_airtable_get_settings();
	$reviews_table = trim((string) ($settings['reviews']['table'] ?? ''));
	if (empty($settings['enabled']) || $reviews_table === '') {
		return;
	}
	$project_name = lf_ai_studio_airtable_get_project_name_for_reviews($settings);
	if ($project_name === '') {
		return;
	}
	$result = lf_ai_studio_airtable_import_reviews_by_project($project_name, $settings);
	if (empty($result['error'])) {
		update_option('lf_ai_airtable_reviews_last_sync', time(), false);
	}
}

function lf_ai_studio_airtable_get_project_name_for_reviews(array $settings): string {
	$stored = trim((string) get_option('lf_ai_airtable_project_name', ''));
	if ($stored !== '') {
		return $stored;
	}
	$manifest = function_exists('lf_ai_studio_get_manifest') ? lf_ai_studio_get_manifest() : get_option('lf_site_manifest', []);
	$business = is_array($manifest['business'] ?? null) ? $manifest['business'] : [];
	$name = trim((string) ($business['name'] ?? ''));
	if ($name !== '') {
		update_option('lf_ai_airtable_project_name', $name, false);
	}
	return $name;
}

function lf_ai_studio_airtable_project_name_from_record(array $record, array $settings): string {
	$record_fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
	$project_field = (string) ($settings['fields']['project'] ?? '');
	if ($project_field === '') {
		return '';
	}
	return lf_ai_studio_airtable_string_field($record_fields, $project_field);
}

function lf_ai_studio_airtable_store_project_context(array $record, array $settings, string $project_name): void {
	if ($project_name !== '') {
		update_option('lf_ai_airtable_project_name', $project_name, false);
	}
	$record_id = (string) ($record['id'] ?? '');
	if ($record_id !== '') {
		update_option('lf_ai_airtable_project_record_id', $record_id, false);
	}
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

	$run = lf_ai_studio_airtable_generate_from_record_id($record_id, 'manual');
	if (empty($run['ok'])) {
		wp_send_json_error([
			'message' => (string) ($run['error'] ?? __('Generation failed.', 'leadsforward-core')),
			'errors' => is_array($run['errors'] ?? null) ? array_values(array_filter(array_map('strval', $run['errors']))) : [],
		], 400);
	}

	$redirect = function_exists('lf_ai_studio_manifest_admin_url')
		? lf_ai_studio_manifest_admin_url(['manifest' => '1'])
		: admin_url('admin.php?page=lf-manifest&manifest=1');
	if (!empty($run['job_id'])) {
		$redirect = add_query_arg('job', (string) $run['job_id'], $redirect);
	}
	wp_send_json_success([
		'job_id' => $run['job_id'] ?? 0,
		'redirect' => $redirect,
	]);
}

function lf_ai_studio_airtable_preview_manifest(): void {
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
		wp_send_json_error(['message' => (string) $record_result['error']], 400);
	}
	$record = is_array($record_result['record'] ?? null) ? $record_result['record'] : [];
	$settings = lf_ai_studio_airtable_get_settings();
	$build = lf_ai_studio_airtable_record_to_manifest($record, $settings);
	$errors = is_array($build['errors'] ?? null) ? $build['errors'] : [];
	if (!empty($errors)) {
		wp_send_json_error([
			'message' => __('Manifest preview failed.', 'leadsforward-core'),
			'errors' => array_values(array_filter(array_map('strval', $errors))),
		], 400);
	}
	$manifest = is_array($build['manifest'] ?? null) ? $build['manifest'] : [];
	if (function_exists('lf_ai_studio_normalize_manifest')) {
		$manifest = lf_ai_studio_normalize_manifest($manifest);
	}
	$services = is_array($manifest['services'] ?? null) ? $manifest['services'] : [];
	$areas = is_array($manifest['service_areas'] ?? null) ? $manifest['service_areas'] : [];
	$service_rows = [];
	foreach ($services as $svc) {
		if (!is_array($svc)) {
			continue;
		}
		$slug = sanitize_title((string) ($svc['slug'] ?? ''));
		$title = sanitize_text_field((string) ($svc['title'] ?? $svc['name'] ?? $slug));
		if ($slug === '') {
			continue;
		}
		$service_rows[] = ['slug' => $slug, 'title' => $title !== '' ? $title : $slug];
	}
	$area_rows = [];
	foreach ($areas as $area) {
		if (!is_array($area)) {
			continue;
		}
		$slug = sanitize_title((string) ($area['slug'] ?? ''));
		$city = sanitize_text_field((string) ($area['city'] ?? ''));
		$state = sanitize_text_field((string) ($area['state'] ?? ''));
		if ($slug === '') {
			continue;
		}
		$label = trim($city . ($state !== '' ? (', ' . $state) : ''));
		$area_rows[] = ['slug' => $slug, 'label' => $label !== '' ? $label : $slug];
	}
	wp_send_json_success([
		'services' => $service_rows,
		'service_areas' => $area_rows,
	]);
}

function lf_ai_studio_airtable_generate_from_record_id(string $record_id, string $source = 'manual'): array {
	$record_result = lf_ai_studio_airtable_fetch_record($record_id);
	if (!empty($record_result['error'])) {
		return ['ok' => false, 'error' => (string) $record_result['error']];
	}
	$record = $record_result['record'] ?? [];
	$settings = lf_ai_studio_airtable_get_settings();
	$project_name = lf_ai_studio_airtable_project_name_from_record($record, $settings);
	if ($project_name !== '') {
		lf_ai_studio_airtable_store_project_context($record, $settings, $project_name);
	}
	$build = lf_ai_studio_airtable_record_to_manifest($record, $settings);
	if (!empty($build['errors'])) {
		update_option('lf_ai_studio_manifest_errors', $build['errors'], false);
		return [
			'ok' => false,
			'error' => __('Manifest validation failed.', 'leadsforward-core'),
			'errors' => $build['errors'],
		];
	}
	$manifest = $build['manifest'] ?? [];
	$errors = lf_ai_studio_validate_manifest($manifest);
	if (!empty($errors)) {
		update_option('lf_ai_studio_manifest_errors', $errors, false);
		return [
			'ok' => false,
			'error' => __('Manifest validation failed.', 'leadsforward-core'),
			'errors' => $errors,
		];
	}
	$normalized = lf_ai_studio_normalize_manifest($manifest);
	update_option('lf_site_manifest', $normalized, false);
	delete_option('lf_ai_studio_manifest_errors');
	lf_ai_studio_sync_manifest_posts($normalized);
	if (function_exists('lf_ai_studio_apply_manifest_to_site_options')) {
		lf_ai_studio_apply_manifest_to_site_options($normalized);
	}
	if (function_exists('lf_ai_studio_apply_manifest_seo_baseline')) {
		lf_ai_studio_apply_manifest_seo_baseline($normalized);
	}
	if (function_exists('lf_seo_assign_keywords_from_manifest')) {
		lf_seo_assign_keywords_from_manifest($normalized);
	}
	$review_result = lf_ai_studio_airtable_import_reviews($record, $settings);
	if (!empty($review_result['error'])) {
		error_log('LF Airtable Reviews: ' . (string) $review_result['error']);
	}
	$result = lf_ai_studio_run_generation();
	if (!empty($result['error'])) {
		$message = sprintf(__('Generation failed: %s', 'leadsforward-core'), (string) $result['error']);
		update_option('lf_ai_studio_manifest_errors', [$message], false);
		return ['ok' => false, 'error' => $message, 'errors' => [$message]];
	}
	update_option('lf_ai_autonomy_last_source', sanitize_text_field($source), false);
	update_option('lf_ai_autonomy_last_run', time(), false);
	return ['ok' => true, 'job_id' => (int) ($result['job_id'] ?? 0)];
}

function lf_ai_studio_airtable_get_stored_record_id(): string {
	return trim((string) get_option('lf_ai_airtable_project_record_id', ''));
}

function lf_ai_studio_airtable_queue_items(): array {
	$queue = get_option('lf_ai_airtable_generation_queue', []);
	return is_array($queue) ? array_values($queue) : [];
}

function lf_ai_studio_airtable_save_queue_items(array $queue): void {
	update_option('lf_ai_airtable_generation_queue', array_values($queue), false);
}

function lf_ai_studio_airtable_enqueue_generation_run(string $record_id, string $updated_at = '', string $source = 'webhook'): array {
	$settings = lf_ai_studio_airtable_get_settings();
	$autonomy_runtime = function_exists('lf_ai_autonomy_runtime_enabled')
		? lf_ai_autonomy_runtime_enabled()
		: (get_option('lf_ai_autonomy_enabled', '0') === '1');
	if (empty($settings['enabled']) || !$autonomy_runtime) {
		return ['ok' => false, 'error' => 'autonomy_disabled'];
	}
	$record_id = sanitize_text_field($record_id);
	if ($record_id === '') {
		return ['ok' => false, 'error' => 'missing_record_id'];
	}
	$updated_at = sanitize_text_field($updated_at);
	$source = sanitize_text_field($source);
	$queue = lf_ai_studio_airtable_queue_items();
	$dedupe_hash = hash('sha256', $record_id . '|' . $updated_at);
	foreach ($queue as $item) {
		$item_hash = sanitize_text_field((string) ($item['dedupe_hash'] ?? ''));
		if ($item_hash !== '' && hash_equals($item_hash, $dedupe_hash)) {
			return ['ok' => true, 'queued' => false, 'queue_size' => count($queue)];
		}
	}
	$queue[] = [
		'record_id' => $record_id,
		'updated_at' => $updated_at,
		'source' => $source !== '' ? $source : 'webhook',
		'attempts' => 0,
		'next_attempt_at' => time(),
		'queued_at' => time(),
		'dedupe_hash' => $dedupe_hash,
	];
	lf_ai_studio_airtable_save_queue_items($queue);
	if (!wp_next_scheduled('lf_ai_airtable_generation_queue')) {
		wp_schedule_single_event(time() + 5, 'lf_ai_airtable_generation_queue');
	}
	return ['ok' => true, 'queued' => true, 'queue_size' => count($queue)];
}

function lf_ai_studio_airtable_run_reconcile(): void {
	$settings = lf_ai_studio_airtable_get_settings();
	$autonomy_runtime = function_exists('lf_ai_autonomy_runtime_enabled')
		? lf_ai_autonomy_runtime_enabled()
		: (get_option('lf_ai_autonomy_enabled', '0') === '1');
	if (empty($settings['enabled']) || !$autonomy_runtime) {
		return;
	}
	$record_id = lf_ai_studio_airtable_get_stored_record_id();
	if ($record_id === '') {
		return;
	}
	lf_ai_studio_airtable_enqueue_generation_run($record_id, gmdate('c'), 'reconcile');
}

function lf_ai_studio_airtable_process_generation_queue(): void {
	$autonomy_runtime = function_exists('lf_ai_autonomy_runtime_enabled')
		? lf_ai_autonomy_runtime_enabled()
		: (get_option('lf_ai_autonomy_enabled', '0') === '1');
	if (!$autonomy_runtime) {
		return;
	}
	$lock_key = 'lf_ai_airtable_generation_lock';
	if (get_transient($lock_key)) {
		return;
	}
	set_transient($lock_key, 1, 4 * MINUTE_IN_SECONDS);
	$paused_until = (int) get_option('lf_ai_autonomy_paused_until', 0);
	if ($paused_until > time()) {
		delete_transient($lock_key);
		return;
	}
	$queue = lf_ai_studio_airtable_queue_items();
	if (empty($queue)) {
		delete_transient($lock_key);
		return;
	}
	$index = -1;
	$item = null;
	foreach ($queue as $i => $candidate) {
		$next_attempt_at = isset($candidate['next_attempt_at']) ? (int) $candidate['next_attempt_at'] : 0;
		if ($next_attempt_at <= time()) {
			$index = (int) $i;
			$item = $candidate;
			break;
		}
	}
	if ($index < 0 || !is_array($item)) {
		delete_transient($lock_key);
		return;
	}
	$record_id = sanitize_text_field((string) ($item['record_id'] ?? ''));
	$attempts = isset($item['attempts']) ? (int) $item['attempts'] : 0;
	$max_retries = (int) get_option('lf_ai_autonomy_max_retries', 3);
	$max_retries = max(1, min(10, $max_retries));
	$result = lf_ai_studio_airtable_generate_from_record_id($record_id, (string) ($item['source'] ?? 'queue'));
	if (!empty($result['ok'])) {
		array_splice($queue, $index, 1);
		lf_ai_studio_airtable_save_queue_items($queue);
		update_option('lf_ai_autonomy_failures_count', 0, false);
		update_option('lf_ai_autonomy_paused_until', 0, false);
	} else {
		$attempts++;
		$queue[$index]['attempts'] = $attempts;
		if ($attempts >= $max_retries) {
			array_splice($queue, $index, 1);
			$failures = (int) get_option('lf_ai_autonomy_failures_count', 0) + 1;
			update_option('lf_ai_autonomy_failures_count', $failures, false);
			$threshold = (int) get_option('lf_ai_autonomy_circuit_threshold', 3);
			$threshold = max(1, min(20, $threshold));
			if ($failures >= $threshold) {
				$cooldown = (int) get_option('lf_ai_autonomy_cooldown_seconds', 900);
				$cooldown = max(60, min(86400, $cooldown));
				update_option('lf_ai_autonomy_paused_until', time() + $cooldown, false);
			}
		} else {
			$delay = min(3600, (int) pow(2, $attempts) * 60);
			$queue[$index]['next_attempt_at'] = time() + $delay;
		}
		lf_ai_studio_airtable_save_queue_items($queue);
	}
	if (!empty($queue) && !wp_next_scheduled('lf_ai_airtable_generation_queue')) {
		wp_schedule_single_event(time() + 60, 'lf_ai_airtable_generation_queue');
	}
	delete_transient($lock_key);
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

function lf_ai_studio_airtable_import_reviews(array $record, array $settings): array {
	$reviews_settings = is_array($settings['reviews'] ?? null) ? $settings['reviews'] : [];
	$reviews_table = trim((string) ($reviews_settings['table'] ?? ''));
	if ($reviews_table === '') {
		return ['skipped' => true];
	}
	$reviews_settings['table'] = $reviews_table;
	$reviews_settings['view'] = (string) ($reviews_settings['view'] ?? '');
	$reviews_settings['fields'] = is_array($reviews_settings['fields'] ?? null) ? $reviews_settings['fields'] : [];

	$ready = lf_ai_studio_airtable_is_ready([
		'enabled' => $settings['enabled'] ?? false,
		'pat' => (string) ($settings['pat'] ?? ''),
		'base_id' => (string) ($settings['base_id'] ?? ''),
		'table' => $reviews_settings['table'],
	]);
	if (!$ready['ready']) {
		return ['error' => $ready['message']];
	}

	$resolved = lf_ai_studio_airtable_resolve_table_view([
		'pat' => (string) ($settings['pat'] ?? ''),
		'base_id' => (string) ($settings['base_id'] ?? ''),
		'table' => $reviews_settings['table'],
		'view' => (string) ($reviews_settings['view'] ?? ''),
	]);
	if (!empty($resolved['error'])) {
		return ['error' => $resolved['error']];
	}

	$project_name = lf_ai_studio_airtable_project_name_from_record($record, $settings);
	if ($project_name === '') {
		return ['skipped' => true];
	}
	lf_ai_studio_airtable_store_project_context($record, $settings, $project_name);

	return lf_ai_studio_airtable_import_reviews_by_project($project_name, $settings, $resolved);
}

function lf_ai_studio_airtable_import_reviews_by_project(string $project_name, array $settings, array $resolved = []): array {
	$reviews_settings = is_array($settings['reviews'] ?? null) ? $settings['reviews'] : [];
	$reviews_table = trim((string) ($reviews_settings['table'] ?? ''));
	if ($reviews_table === '') {
		return ['skipped' => true];
	}
	$reviews_settings['table'] = $reviews_table;
	$reviews_settings['view'] = (string) ($reviews_settings['view'] ?? '');
	$reviews_settings['fields'] = is_array($reviews_settings['fields'] ?? null) ? $reviews_settings['fields'] : [];

	$ready = lf_ai_studio_airtable_is_ready([
		'enabled' => $settings['enabled'] ?? false,
		'pat' => (string) ($settings['pat'] ?? ''),
		'base_id' => (string) ($settings['base_id'] ?? ''),
		'table' => $reviews_settings['table'],
	]);
	if (!$ready['ready']) {
		return ['error' => $ready['message']];
	}

	if (empty($resolved)) {
		$resolved = lf_ai_studio_airtable_resolve_table_view([
			'pat' => (string) ($settings['pat'] ?? ''),
			'base_id' => (string) ($settings['base_id'] ?? ''),
			'table' => $reviews_settings['table'],
			'view' => (string) ($reviews_settings['view'] ?? ''),
		]);
		if (!empty($resolved['error'])) {
			return ['error' => $resolved['error']];
		}
	}

	$review_records = lf_ai_studio_airtable_fetch_reviews(
		$project_name,
		$reviews_settings,
		$resolved,
		(string) ($settings['pat'] ?? ''),
		(string) ($settings['base_id'] ?? '')
	);
	if (
		empty($review_records['error'])
		&& empty($review_records['records'])
		&& $project_name !== ''
	) {
		// Retry with safer fallback project names when stored filter is stale.
		$fallback_names = lf_ai_studio_airtable_review_project_fallback_names($project_name);
		foreach ($fallback_names as $fallback_name) {
			$fallback_records = lf_ai_studio_airtable_fetch_reviews(
				$fallback_name,
				$reviews_settings,
				$resolved,
				(string) ($settings['pat'] ?? ''),
				(string) ($settings['base_id'] ?? '')
			);
			if (!empty($fallback_records['error'])) {
				continue;
			}
			if (!empty($fallback_records['records'])) {
				$review_records = $fallback_records;
				update_option('lf_ai_airtable_project_name', $fallback_name, false);
				break;
			}
		}
	}
	if (!empty($review_records['error'])) {
		return ['error' => $review_records['error']];
	}

	$imported = 0;
	foreach ((array) ($review_records['records'] ?? []) as $review_record) {
		$import = lf_ai_studio_airtable_upsert_review($review_record, $reviews_settings['fields']);
		if (!empty($import['created']) || !empty($import['updated'])) {
			$imported++;
		}
	}

	return ['imported' => $imported];
}

function lf_ai_studio_airtable_review_project_fallback_names(string $project_name): array {
	$candidates = [];
	$project_name = trim($project_name);
	if ($project_name !== '') {
		$candidates[] = $project_name;
	}
	$manifest = function_exists('lf_ai_studio_get_manifest') ? lf_ai_studio_get_manifest() : [];
	$manifest_business = is_array($manifest['business'] ?? null) ? $manifest['business'] : [];
	$manifest_name = trim((string) ($manifest_business['name'] ?? ''));
	if ($manifest_name !== '') {
		$candidates[] = $manifest_name;
	}
	$business_name = trim((string) get_option('lf_business_name', ''));
	if ($business_name !== '') {
		$candidates[] = $business_name;
	}
	$site_name = trim((string) get_bloginfo('name'));
	if ($site_name !== '') {
		$candidates[] = $site_name;
	}
	$cleaned = [];
	foreach ($candidates as $candidate) {
		$candidate = trim(preg_replace('/\s+/u', ' ', (string) $candidate));
		if ($candidate !== '') {
			$cleaned[] = $candidate;
		}
	}
	$cleaned = array_values(array_unique($cleaned));
	if (!empty($cleaned)) {
		array_shift($cleaned); // first entry is original project name already attempted
	}
	return $cleaned;
}

function lf_ai_studio_airtable_fetch_reviews(
	string $project_name,
	array $reviews_settings,
	array $resolved,
	string $pat,
	string $base_id
): array {
	$project_field = (string) ($reviews_settings['fields']['review_project'] ?? 'Project');
	if ($project_field === '') {
		return ['error' => __('Reviews project field is not configured.', 'leadsforward-core')];
	}
	$base_url = lf_ai_studio_airtable_base_url([
		'base_id' => $base_id,
		'table' => $resolved['table_id'],
	]);
	$params = [
		'pageSize' => 100,
	];
	$fields = lf_ai_studio_airtable_collect_review_fields($reviews_settings['fields']);
	if (!empty($fields)) {
		$params['fields'] = $fields;
	}
	if (!empty($resolved['view'])) {
		$params['view'] = $resolved['view'];
	}
	$offset = '';
	$pages = 0;
	$max_pages = 80;
	$matched = [];
	do {
		$page_params = $params;
		if ($offset !== '') {
			$page_params['offset'] = $offset;
		}
		$response = lf_ai_studio_airtable_get($base_url, $page_params, $pat);
		if (
			!empty($response['error'])
			&& !empty($page_params['fields'])
			&& stripos((string) $response['error'], 'Unknown field name') !== false
		) {
			// Backward-compatible fallback: if a mapped Airtable field no longer exists,
			// retry without field projection so sync can still proceed.
			unset($page_params['fields']);
			$response = lf_ai_studio_airtable_get($base_url, $page_params, $pat);
		}
		if (!empty($response['error'])) {
			return ['error' => $response['error']];
		}
		$records = $response['data']['records'] ?? [];
		if ($project_name !== '' && $project_field !== '') {
			$records = lf_ai_studio_airtable_filter_reviews_by_project((array) $records, $project_name, $project_field);
		}
		$matched = array_merge($matched, (array) $records);
		$offset = isset($response['data']['offset']) ? (string) $response['data']['offset'] : '';
		$pages++;
	} while ($offset !== '' && $pages < $max_pages);
	return ['records' => $matched];
}

function lf_ai_studio_airtable_reviews_filter_formula(string $project_name, string $project_field): string {
	$needle = strtolower(trim($project_name));
	$needle = str_replace(['\\', '"'], ['\\\\', '\"'], $needle);
	$field = trim((string) $project_field);
	$field = str_replace(['{', '}'], '', $field);
	return sprintf(
		'LOWER({%s} & "") = "%s"',
		$field,
		$needle,
	);
}

function lf_ai_studio_airtable_filter_reviews_by_project(array $records, string $project_name, string $project_field): array {
	$needle = strtolower(trim($project_name));
	if ($needle === '') {
		return $records;
	}
	$field = trim((string) $project_field);
	$out = [];
	foreach ($records as $record) {
		$fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
		$resolved_field = lf_ai_studio_airtable_resolve_field_key($fields, $field);
		if ($resolved_field === '') {
			continue;
		}
		$value = $fields[$resolved_field];
		$match = false;
		if (is_array($value)) {
			foreach ($value as $item) {
				if (strtolower(trim((string) $item)) === $needle) {
					$match = true;
					break;
				}
			}
		} else {
			$raw = trim((string) $value);
			if ($raw !== '') {
				$parts = array_map('trim', explode(',', $raw));
				foreach ($parts as $part) {
					if (strtolower($part) === $needle) {
						$match = true;
						break;
					}
				}
			}
		}
		if ($match) {
			$out[] = $record;
		}
	}
	return $out;
}

function lf_ai_studio_airtable_resolve_field_key(array $fields, string $key): string {
	$key = trim((string) $key);
	if ($key === '') {
		return '';
	}
	if (array_key_exists($key, $fields)) {
		return $key;
	}
	$normalize = static function (string $value): string {
		$value = strtolower(trim($value));
		$value = preg_replace('/[^a-z0-9]+/', '', $value);
		return is_string($value) ? $value : '';
	};
	$key_normalized = $normalize($key);
	foreach (array_keys($fields) as $field_name) {
		$field_name = (string) $field_name;
		if (strcasecmp($field_name, $key) === 0) {
			return $field_name;
		}
		if ($key_normalized !== '' && $normalize($field_name) === $key_normalized) {
			return $field_name;
		}
	}
	return '';
}

function lf_ai_studio_airtable_collect_review_fields(array $field_map): array {
	$fields = [];
	foreach ($field_map as $field_name) {
		$field_name = trim((string) $field_name);
		if ($field_name === '') {
			continue;
		}
		$fields[] = $field_name;
	}
	$fields = array_values(array_unique($fields));
	return $fields;
}

function lf_ai_studio_airtable_upsert_review(array $record, array $field_map): array {
	$record_id = (string) ($record['id'] ?? '');
	if ($record_id === '') {
		return ['skipped' => true];
	}
	$fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
	$name = lf_ai_studio_airtable_string_field($fields, (string) ($field_map['review_reviewer'] ?? ''));
	$text = lf_ai_studio_airtable_string_field($fields, (string) ($field_map['review_text'] ?? ''));
	if ($text === '') {
		return ['skipped' => true];
	}
	$rating_raw = lf_ai_studio_airtable_string_field($fields, (string) ($field_map['review_rating'] ?? ''));
	$rating = (int) round((float) $rating_raw);
	if ($rating < 1 || $rating > 5) {
		$rating = 5;
	}
	$source_raw = lf_ai_studio_airtable_string_field($fields, (string) ($field_map['review_source'] ?? ''));
	$source_url = lf_ai_studio_airtable_string_field($fields, (string) ($field_map['review_source_url'] ?? ''));
	$source = lf_ai_studio_airtable_normalize_review_source($source_raw, $source_url);

	$existing = get_posts([
		'post_type' => 'lf_testimonial',
		'post_status' => 'any',
		'fields' => 'ids',
		'numberposts' => 1,
		'meta_query' => [
			[
				'key' => 'lf_airtable_review_id',
				'value' => $record_id,
			],
		],
	]);
	$post_id = !empty($existing) ? (int) $existing[0] : 0;
	$title = $name !== '' ? $name : __('Customer review', 'leadsforward-core');
	$post_data = [
		'post_type' => 'lf_testimonial',
		'post_status' => 'publish',
		'post_title' => $title,
		'post_content' => $text,
	];
	if ($post_id) {
		$post_data['ID'] = $post_id;
		wp_update_post($post_data);
	} else {
		$post_id = (int) wp_insert_post($post_data);
	}
	if (!$post_id) {
		return ['error' => __('Unable to save review.', 'leadsforward-core')];
	}

	update_post_meta($post_id, 'lf_airtable_review_id', $record_id);
	if (function_exists('update_field')) {
		update_field('lf_testimonial_reviewer_name', $name, $post_id);
		update_field('lf_testimonial_rating', $rating, $post_id);
		update_field('lf_testimonial_review_text', $text, $post_id);
		update_field('lf_testimonial_source', $source, $post_id);
		if ($source_url !== '') {
			update_field('lf_testimonial_source_url', $source_url, $post_id);
		}
	} else {
		update_post_meta($post_id, 'lf_testimonial_reviewer_name', $name);
		update_post_meta($post_id, 'lf_testimonial_rating', $rating);
		update_post_meta($post_id, 'lf_testimonial_review_text', $text);
		update_post_meta($post_id, 'lf_testimonial_source', $source);
		if ($source_url !== '') {
			update_post_meta($post_id, 'lf_testimonial_source_url', $source_url);
		}
	}

	return ['updated' => !empty($existing), 'created' => empty($existing)];
}

function lf_ai_studio_airtable_normalize_review_source(string $source, string $source_url): string {
	$haystack = strtolower(trim($source . ' ' . $source_url));
	if ($haystack === '') {
		return 'other';
	}
	if (strpos($haystack, 'google') !== false) {
		return 'google';
	}
	if (strpos($haystack, 'facebook') !== false) {
		return 'facebook';
	}
	if (strpos($haystack, 'yelp') !== false) {
		return 'yelp';
	}
	return 'other';
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
	// Public site / schema email: mapped column only (default "Domain Email"). Do not fall back to Google Account or Gmails — those are often personal client inboxes.
	$domain_email_only = lf_ai_studio_airtable_string_field($fields, $map['email'] ?? '');
	$email = $domain_email_only;
	if ($email === '') {
		$domain_seed = sanitize_title($business_name !== '' ? $business_name : 'lead');
		if ($domain_seed === '') {
			$domain_seed = 'lead';
		}
		$email = $domain_seed . '@example.com';
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
	if ($city !== '' && $state === '' && strpos($city, ',') !== false) {
		$city_parts = array_map('trim', explode(',', $city));
		if (count($city_parts) >= 2) {
			$city = (string) $city_parts[0];
			$state = (string) $city_parts[1];
		}
	}
	if ($primary_city === '') {
		$primary_city = $city;
	}
	if ($niche === '') {
		$niche = lf_ai_studio_airtable_string_field($fields, $map['business_category'] ?? '');
	}
	if ($niche === '') {
		$niche = __('Home Services', 'leadsforward-core');
	}
	if ($phone === '') {
		$phone = '(000) 000-0000';
	}
	if ($primary_keyword === '') {
		$primary_keyword = trim(sprintf('%s %s %s', $niche, $primary_city, $state));
	}

	$services = lf_ai_studio_airtable_json_array_field($fields, $map['services_json'] ?? '', 'Services JSON', $errors);
	$service_areas = lf_ai_studio_airtable_json_array_field($fields, $map['service_areas_json'] ?? '', 'Service Areas JSON', $errors);
	$service_area_list = lf_ai_studio_airtable_string_field($fields, $map['service_areas_list'] ?? '');
	$services_list = lf_ai_studio_airtable_string_field($fields, $map['services_list'] ?? '');
	$services_raw = lf_ai_studio_airtable_string_field($fields, $map['services_raw'] ?? '');

	$website_url = lf_ai_studio_airtable_string_field($fields, $map['website_url'] ?? '');
	$root_domain = lf_ai_studio_airtable_string_field($fields, $map['root_domain'] ?? '');
	$business_category = lf_ai_studio_airtable_string_field($fields, $map['business_category'] ?? '');
	$business_hours = lf_ai_studio_airtable_string_field($fields, $map['business_hours'] ?? '');
	$business_short_description = lf_ai_studio_airtable_string_field($fields, $map['business_short_description'] ?? '');
	$google_name = lf_ai_studio_airtable_string_field($fields, $map['google_name'] ?? '');
	$gbp_url = lf_ai_studio_airtable_string_field($fields, $map['gbp_url'] ?? '');
	$place_id = lf_ai_studio_airtable_string_field($fields, $map['place_id'] ?? '');
	$gbp_cid_primary = lf_ai_studio_airtable_string_field($fields, $map['gbp_cid_primary'] ?? '');
	$gbp_cid = lf_ai_studio_airtable_string_field($fields, $map['gbp_cid'] ?? '');
	$logo_url = lf_ai_studio_airtable_string_field($fields, $map['logo_url'] ?? '');
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
	if ($business_category !== '') {
		$keyword_pool[] = $business_category;
	}
	if ($niche !== '') {
		$keyword_pool[] = $niche;
	}
	$keyword_pool = array_values(array_unique(array_filter($keyword_pool)));

	if (empty($service_areas) && $service_area_list !== '') {
		$service_areas = lf_ai_studio_airtable_build_service_areas_from_list($service_area_list, $state, $niche);
	}

	if ($business_name === '') {
		$errors[] = __('Missing Project field in Airtable.', 'leadsforward-core');
	}
	if ($city === '' || $state === '') {
		$errors[] = __('Missing required location fields in Airtable (City, State).', 'leadsforward-core');
	}
	if ($primary_keyword === '') {
		$errors[] = __('Missing Primary Keyword field in Airtable.', 'leadsforward-core');
	}
	$generic_titles = ['main', 'additional', 'main service', 'additional service', 'service', 'services'];
	$service_titles = [];
	foreach ($services as $svc) {
		if (is_array($svc)) {
			$service_titles[] = strtolower(trim((string) ($svc['title'] ?? '')));
		}
	}
	$has_only_generic = !empty($services);
	if (!empty($service_titles)) {
		foreach ($service_titles as $title) {
			if ($title === '' || !in_array($title, $generic_titles, true)) {
				$has_only_generic = false;
				break;
			}
		}
	} else {
		$has_only_generic = false;
	}
	if ((empty($services) || $has_only_generic) && $services_list !== '') {
		$services = lf_ai_studio_airtable_build_services_from_list($services_list, $primary_city, $state, $business_name, $niche);
	}
	if ((empty($services) || $has_only_generic) && $services_raw !== '') {
		$services = lf_ai_studio_airtable_build_services_from_list($services_raw, $primary_city, $state, $business_name, $niche);
	}

	// If Airtable provided placeholder services (e.g. "Main", "Additional"), treat it as missing so we can fall back
	// to niche/keyword defaults. This keeps Manifest Website agnostic to the operator's Global Settings.
	if (!empty($services)) {
		$service_titles = [];
		foreach ($services as $svc) {
			if (is_array($svc)) {
				$service_titles[] = strtolower(trim((string) ($svc['title'] ?? '')));
			}
		}
		$only_generic_after_build = !empty($service_titles);
		foreach ($service_titles as $title) {
			if ($title === '' || !in_array($title, $generic_titles, true)) {
				$only_generic_after_build = false;
				break;
			}
		}
		if ($only_generic_after_build) {
			$services = [];
		}
	}
	if (empty($services)) {
		$niche_slug_guess = lf_ai_studio_airtable_resolve_niche_slug($niche, $niche_slug);
		$services = lf_ai_studio_airtable_build_services_from_niche($niche_slug_guess, $primary_city, $state, $business_name);
	}
	if (empty($services) && !empty($keyword_pool)) {
		$services = lf_ai_studio_airtable_build_services_from_keywords($keyword_pool, $primary_city, $state, $business_name, $niche);
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
			'domain_email' => $domain_email_only,
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
			'root_domain' => $root_domain,
			'hours' => $business_hours,
			'category' => $business_category,
			'short_description' => $business_short_description,
			'place_name' => $google_name,
			// IMPORTANT: Place ID is not the same as a GBP CID. Only populate place_id when Airtable provides a real Place ID.
			'place_id' => $place_id,
			'gbp_cid_primary' => $gbp_cid_primary,
			'gbp_cid' => $gbp_cid,
			'gbp_url' => $gbp_url,
			'founding_year' => $foundation_year,
			'logo_url' => $logo_url,
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
	$resolved_key = lf_ai_studio_airtable_resolve_field_key($fields, $key);
	if ($resolved_key === '') {
		return '';
	}
	$value = $fields[$resolved_key];
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
	$raw = trim($raw);
	if ($raw === '') {
		return [];
	}

	// Prefer newline/semicolon-delimited lists. Do NOT blindly split on commas because many teams store values like
	// "San Antonio, TX" which would otherwise become two separate "cities" ("San Antonio" + "TX").
	$parts = preg_split('/\r\n|\r|\n|;/', $raw) ?: [];
	if (count($parts) === 1) {
		$single = trim((string) ($parts[0] ?? ''));
		// If it looks like repeated "City, ST" pairs, extract each pair as one item.
		if ($single !== '') {
			$pairs = [];
			if (preg_match_all('/([^,;\n]+?),\s*([A-Za-z]{2})\b/', $single, $m, PREG_SET_ORDER) === 1) {
				// exactly one match; handle below
			} elseif (!empty($m) && is_array($m) && count($m) > 1) {
				foreach ($m as $row) {
					$city_part = trim((string) ($row[1] ?? ''));
					$st_part = strtoupper(trim((string) ($row[2] ?? '')));
					if ($city_part !== '' && $st_part !== '') {
						$pairs[] = $city_part . ', ' . $st_part;
					}
				}
			}
			if (!empty($pairs)) {
				$parts = $pairs;
			} elseif (preg_match('/,\s*[A-Za-z]{2}\b/', $single) === 1) {
				// Single "City, ST" value.
				$parts = [$single];
			} else {
				// Otherwise allow comma-delimited lists (e.g. "City1, City2, City3").
				$parts = preg_split('/,/', $single) ?: [];
			}
		}
	}
	$areas = [];
	foreach ((array) $parts as $part) {
		$city = trim((string) $part);
		if ($city === '') {
			continue;
		}
		$state_for_area = $state;
		$city_name = $city;
		if (preg_match('/^(.+?),\s*([A-Za-z]{2})$/', $city, $mm) === 1) {
			$city_name = trim((string) ($mm[1] ?? $city));
			$maybe_state = strtoupper(trim((string) ($mm[2] ?? '')));
			if ($maybe_state !== '') {
				$state_for_area = $maybe_state;
			}
		}
		if ($city_name === '') {
			continue;
		}
		$areas[] = [
			'city' => $city_name,
			'state' => $state_for_area,
			'slug' => sanitize_title($city_name),
			'primary_keyword' => trim(sprintf('%s %s %s', $niche, $city_name, $state_for_area)),
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

function lf_ai_studio_airtable_clean_service_label(string $raw, string $city, string $state, string $niche): string {
	$label = strtolower(trim($raw));
	if ($label === '') {
		return '';
	}
	$label = preg_replace('/\bnear me\b/i', '', $label);
	$label = preg_replace('/\bnearby\b|\bnear\b|\baround\b|\bwithin\b/i', '', $label);
	$label = preg_replace('/\bservices?\b/i', '', $label);
	$label = preg_replace('/\bcompanies?\b|\bcompany\b|\bcontractors?\b|\bexperts?\b|\bspecialists?\b/i', '', $label);
	$label = preg_replace('/\b\d{5}(?:-\d{4})?\b/', '', $label);
	if ($city !== '') {
		$label = preg_replace('/\b' . preg_quote(strtolower($city), '/') . '\b/i', '', $label);
	}
	if ($state !== '') {
		$label = preg_replace('/\b' . preg_quote(strtolower($state), '/') . '\b/i', '', $label);
	}
	$label = preg_replace('/\b(in|for)\b/i', '', $label);
	$label = trim(preg_replace('/\s+/', ' ', $label));
	if ($label === '') {
		return '';
	}
	$clean = ucwords($label);
	if ($niche !== '' && strtolower($clean) === strtolower($niche)) {
		return '';
	}
	return $clean;
}

function lf_ai_studio_airtable_build_services_from_list(string $raw, string $city, string $state, string $business_name, string $niche): array {
	$parts = preg_split('/\r\n|\r|\n|,|;/', (string) $raw);
	if (!is_array($parts)) {
		return [];
	}
	$services = [];
	$seen = [];
	foreach ($parts as $part) {
		$label = trim((string) $part);
		$label = trim($label, "-\t ");
		if ($label === '') {
			continue;
		}
		$clean = lf_ai_studio_airtable_clean_service_label($label, $city, $state, $niche);
		if ($clean === '') {
			continue;
		}
		$slug = sanitize_title($clean);
		if ($slug === '' || isset($seen[$slug])) {
			continue;
		}
		$seen[$slug] = true;
		$services[] = [
			'title' => $clean,
			'slug' => $slug,
			'primary_keyword' => trim(sprintf('%s %s %s', $clean, $city, $state)),
			'secondary_keywords' => [],
			'custom_cta_context' => trim(sprintf('Get trusted %s from %s.', $clean, $business_name)),
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
	$stop_words = [
		'best', 'top', 'affordable', 'cheap', 'local', 'nearby', 'professional',
		'residential', 'commercial', 'licensed', 'insured', 'trusted'
	];
	foreach ($keywords as $keyword) {
		$raw = trim((string) $keyword);
		if ($raw === '') {
			continue;
		}
		$candidate = lf_ai_studio_airtable_clean_service_label($raw, $city, $state, $niche);
		if ($candidate === '') {
			continue;
		}
		if (!empty($stop_words)) {
			$candidate = preg_replace('/\b(' . implode('|', array_map('preg_quote', $stop_words)) . ')\b/i', '', $candidate);
			$candidate = trim(preg_replace('/\s+/', ' ', $candidate));
		}
		if ($candidate === '' || preg_match('/\bestimate\b|\bquote\b|\bquotes\b/i', $candidate)) {
			continue;
		}
		$title = $candidate;
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
	$aliases = function_exists('lf_niche_slug_aliases') ? lf_niche_slug_aliases() : [];
	$slug = '';
	if ($niche_slug !== '') {
		$slug = sanitize_title($niche_slug);
	} elseif ($niche !== '') {
		$slug = sanitize_title($niche);
	}
	if ($slug !== '' && isset($aliases[$slug])) {
		$slug = (string) $aliases[$slug];
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
