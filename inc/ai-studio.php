<?php
/**
 * AI Studio: orchestrator-driven site content generation.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_AI_STUDIO_JOB_CPT = 'lf_ai_job';
const LF_MANIFEST_SCHEMA_VERSION = '1.0';

add_action('init', 'lf_ai_studio_register_cpt');
add_action('admin_post_lf_ai_studio_save', 'lf_ai_studio_handle_save');
add_action('admin_post_lf_ai_studio_orchestrator_save', 'lf_ai_studio_handle_orchestrator_save');
add_action('admin_post_lf_ai_studio_scope_save', 'lf_ai_studio_handle_scope_save');
add_action('admin_post_lf_ai_studio_image_settings_save', 'lf_ai_studio_handle_image_settings_save');
add_action('admin_post_lf_ai_studio_generate', 'lf_ai_studio_handle_generate');
add_action('admin_post_lf_ai_studio_retry', 'lf_ai_studio_handle_retry');
add_action('admin_post_lf_ai_studio_manifest', 'lf_ai_studio_handle_manifest');
add_action('admin_post_lf_ai_studio_manifest_template', 'lf_ai_studio_handle_manifest_template');
add_action('admin_post_lf_ai_studio_research', 'lf_ai_studio_handle_research');
add_action('wp_ajax_lf_ai_studio_research_upload', 'lf_ai_studio_handle_research_ajax');
add_action('admin_post_lf_ai_studio_run_audit', 'lf_ai_studio_handle_run_audit');
add_action('admin_post_lf_ai_studio_regen_blog_posts', 'lf_ai_studio_handle_regen_blog_posts');
add_action('admin_post_lf_ai_studio_save_logo', 'lf_ai_studio_handle_save_logo');
add_action('admin_post_lf_ai_studio_images_upload', 'lf_ai_studio_handle_images_upload');
add_action('wp_ajax_lf_ai_studio_images_upload', 'lf_ai_studio_handle_images_upload_ajax');
add_action('wp_ajax_lf_ai_studio_job_status', 'lf_ai_studio_job_status_ajax');
add_action('admin_enqueue_scripts', 'lf_ai_studio_assets');

function lf_ai_studio_maybe_cleanup_templates(): void {
	if (function_exists('lf_pb_cleanup_templates_once')) {
		lf_pb_cleanup_templates_once();
		return;
	}
	if (function_exists('lf_homepage_cleanup_sections_once')) {
		lf_homepage_cleanup_sections_once();
	}
}

function lf_ai_studio_auth_mode(): string {
	$mode = sanitize_text_field((string) get_option('lf_ai_auth_mode', 'compatibility'));
	return $mode === 'strict_hmac' ? 'strict_hmac' : 'compatibility';
}

function lf_ai_studio_hmac_tolerance_seconds(): int {
	$seconds = (int) get_option('lf_ai_hmac_tolerance_seconds', 300);
	if ($seconds < 60) {
		return 60;
	}
	if ($seconds > 1800) {
		return 1800;
	}
	return $seconds;
}

function lf_ai_autonomy_is_eligible(): bool {
	return get_option('lf_ai_autonomy_eligible', '0') === '1';
}

function lf_ai_autonomy_is_paused(): bool {
	$paused_until = (int) get_option('lf_ai_autonomy_paused_until', 0);
	return $paused_until > time();
}

function lf_ai_autonomy_pause_reason(): string {
	return sanitize_text_field((string) get_option('lf_ai_autonomy_pause_reason', ''));
}

function lf_ai_autonomy_can_enable(): bool {
	return lf_ai_autonomy_is_eligible() && !lf_ai_autonomy_is_paused();
}

function lf_ai_autonomy_runtime_enabled(): bool {
	return get_option('lf_ai_autonomy_enabled', '0') === '1' && lf_ai_autonomy_can_enable();
}

function lf_ai_autonomy_set_enabled_from_request(bool $requested): string {
	if (!$requested) {
		delete_option('lf_ai_autonomy_enable_error');
		return '0';
	}
	if (!lf_ai_autonomy_can_enable()) {
		update_option('lf_ai_autonomy_enable_error', 'Autonomous mode is not eligible yet. Run the Website Manifester successfully first.', false);
		return '0';
	}
	update_option('lf_ai_autonomy_enabled_at', time(), false);
	delete_option('lf_ai_autonomy_enable_error');
	return '1';
}

function lf_ai_autonomy_mark_generation_started(int $job_id = 0): void {
	update_option('lf_ai_autonomy_enabled', '0', false);
	update_option('lf_ai_autonomy_eligible', '0', false);
	update_option('lf_ai_autonomy_pause_reason', 'awaiting_post_manifester_baseline', false);
	update_option('lf_ai_autonomy_last_baseline_job_id', (int) $job_id, false);
}

function lf_ai_autonomy_mark_generation_failed(int $job_id = 0, string $reason = ''): void {
	update_option('lf_ai_autonomy_eligible', '0', false);
	$pause_reason = $reason !== '' ? $reason : 'manifester_failed';
	update_option('lf_ai_autonomy_pause_reason', sanitize_text_field($pause_reason), false);
	if ($job_id > 0) {
		update_option('lf_ai_autonomy_last_baseline_job_id', $job_id, false);
	}
}

function lf_ai_autonomy_mark_generation_success(int $job_id, array $report = []): void {
	$baseline_hash = hash('sha256', (string) wp_json_encode($report));
	update_option('lf_ai_autonomy_eligible', '1', false);
	update_option('lf_ai_autonomy_last_baseline_job_id', $job_id, false);
	update_option('lf_ai_autonomy_last_baseline_hash', $baseline_hash, false);
	update_option('lf_ai_autonomy_last_health_check', time(), false);
	update_option('lf_ai_autonomy_pause_reason', '', false);
	update_option('lf_ai_autonomy_paused_until', 0, false);
	update_option('lf_ai_autonomy_failures_count', 0, false);
}

function lf_ai_studio_register_cpt(): void {
	register_post_type(LF_AI_STUDIO_JOB_CPT, [
		'label' => __('AI Generation Jobs', 'leadsforward-core'),
		'public' => false,
		'show_ui' => false,
		'show_in_menu' => false,
		'supports' => ['title'],
		'capability_type' => 'post',
		'map_meta_cap' => true,
	]);
}

function lf_ai_studio_assets(string $hook): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!in_array($hook, ['toplevel_page_lf-ops', 'leadsforward_page_lf-ops'], true)) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style(
		'lf-ai-studio-airtable',
		LF_THEME_URI . '/assets/css/ai-studio-airtable.css',
		[],
		LF_THEME_VERSION
	);
	wp_enqueue_script(
		'lf-ai-studio-airtable',
		LF_THEME_URI . '/assets/js/ai-studio-airtable.js',
		[],
		LF_THEME_VERSION,
		true
	);
	$airtable_settings = function_exists('lf_ai_studio_airtable_get_settings')
		? lf_ai_studio_airtable_get_settings()
		: ['enabled' => false];
	wp_localize_script('lf-ai-studio-airtable', 'LFAirtableManifester', [
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('lf_ai_airtable'),
		'researchNonce' => wp_create_nonce('lf_ai_studio_research_ajax'),
		'imagesUploadNonce' => wp_create_nonce('lf_ai_studio_images_upload'),
		'jobStatusNonce' => wp_create_nonce('lf_ai_studio_job_status'),
		'enabled' => !empty($airtable_settings['enabled']),
		'strings' => [
			'searchPlaceholder' => __('Search Airtable projects…', 'leadsforward-core'),
			'noResults' => __('No projects found.', 'leadsforward-core'),
			'notConfigured' => __('Airtable is not configured.', 'leadsforward-core'),
			'selectPrompt' => __('Select a project to preview before generating.', 'leadsforward-core'),
			'generating' => __('Generating from Airtable…', 'leadsforward-core'),
		],
		'researchStrings' => [
			'uploading' => __('Uploading research…', 'leadsforward-core'),
			'success' => __('Research uploaded. Ready for generation.', 'leadsforward-core'),
			'error' => __('Research upload failed.', 'leadsforward-core'),
		],
		'imagesStrings' => [
			'uploading' => __('Uploading images…', 'leadsforward-core'),
			'success' => __('Images uploaded to Media Library.', 'leadsforward-core'),
			'error' => __('Image upload failed.', 'leadsforward-core'),
			'empty' => __('Please choose one or more images before uploading.', 'leadsforward-core'),
		],
	]);
}

function lf_ai_studio_job_status_ajax(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_send_json_error(['message' => __('Insufficient permissions.', 'leadsforward-core')], 403);
	}
	check_ajax_referer('lf_ai_studio_job_status', 'nonce');
	$job_id = isset($_GET['job_id']) ? absint($_GET['job_id']) : (isset($_POST['job_id']) ? absint($_POST['job_id']) : 0);
	if (!$job_id) {
		wp_send_json_error(['message' => __('Missing job ID.', 'leadsforward-core')], 400);
	}
	$job = get_post($job_id);
	if (!$job instanceof \WP_Post || $job->post_type !== LF_AI_STUDIO_JOB_CPT) {
		wp_send_json_error(['message' => __('Invalid job.', 'leadsforward-core')], 404);
	}
	$status = (string) get_post_meta($job_id, 'lf_ai_job_status', true);
	$error = (string) get_post_meta($job_id, 'lf_ai_job_error', true);
	$progress = get_post_meta($job_id, 'lf_ai_job_progress', true);
	if (!is_array($progress)) {
		$progress = [];
	}
	if (in_array($status, ['queued', 'running'], true)) {
		$queued_at = (int) get_post_meta($job_id, 'lf_ai_job_queued_at', true);
		$running_at = (int) get_post_meta($job_id, 'lf_ai_job_running_at', true);
		$progress_updated = isset($progress['updated']) ? (int) $progress['updated'] : 0;
		$heartbeat = max($progress_updated, $running_at, $queued_at, (int) get_post_time('U', true, $job_id));
		if ($heartbeat <= 0) {
			$heartbeat = time();
		}
		$timeout_seconds = 12 * MINUTE_IN_SECONDS;
		if ((time() - $heartbeat) > $timeout_seconds) {
			$status = 'failed';
			$error = __('Generation timed out while waiting for orchestrator callback. Check n8n execution logs and callback mapping.', 'leadsforward-core');
			update_post_meta($job_id, 'lf_ai_job_status', $status);
			update_post_meta($job_id, 'lf_ai_job_error', $error);
			if (function_exists('lf_ai_autonomy_mark_generation_failed')) {
				lf_ai_autonomy_mark_generation_failed($job_id, 'callback_timeout');
			}
		}
	}
	wp_send_json_success([
		'job_id' => $job_id,
		'status' => $status,
		'error' => $error,
		'progress' => $progress,
	]);
}

function lf_ai_studio_handle_save(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_save', 'lf_ai_studio_nonce');
	$prev_logo_id = function_exists('lf_get_global_option')
		? (int) lf_get_global_option('lf_global_logo', 0)
		: (int) get_option('options_lf_global_logo', 0);
	$logo_id = isset($_POST['lf_global_logo']) ? (int) $_POST['lf_global_logo'] : 0;
	if (function_exists('lf_update_global_option_value')) {
		lf_update_global_option_value('lf_global_logo', (string) $logo_id);
	} else {
		update_option('options_lf_global_logo', $logo_id);
	}
	if ($logo_id > 0 && $logo_id !== $prev_logo_id && function_exists('lf_branding_auto_from_logo')) {
		lf_branding_auto_from_logo($logo_id);
	}
	if ($prev_logo_id > 0 && $prev_logo_id !== $logo_id) {
		update_post_meta($prev_logo_id, '_lf_skip_auto_distribution', '0');
	}
	if ($logo_id > 0) {
		update_post_meta($logo_id, '_lf_skip_auto_distribution', '1');
	}
	if (function_exists('lf_invalidate_media_index_cache')) {
		lf_invalidate_media_index_cache();
	}
	update_option('lf_ai_studio_enabled', isset($_POST['lf_ai_studio_enabled']) ? '1' : '0');
	update_option('lf_ai_studio_webhook', isset($_POST['lf_ai_studio_webhook']) ? esc_url_raw(wp_unslash($_POST['lf_ai_studio_webhook'])) : '');
	update_option('lf_ai_studio_secret', isset($_POST['lf_ai_studio_secret']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_studio_secret'])) : '');
	$auth_mode = isset($_POST['lf_ai_auth_mode']) ? sanitize_text_field(wp_unslash((string) $_POST['lf_ai_auth_mode'])) : 'compatibility';
	update_option('lf_ai_auth_mode', $auth_mode === 'strict_hmac' ? 'strict_hmac' : 'compatibility');
	$tolerance = isset($_POST['lf_ai_hmac_tolerance_seconds']) ? (int) $_POST['lf_ai_hmac_tolerance_seconds'] : 300;
	update_option('lf_ai_hmac_tolerance_seconds', max(60, min(1800, $tolerance)));
	$autonomy_requested = isset($_POST['lf_ai_autonomy_enabled']);
	update_option('lf_ai_autonomy_enabled', lf_ai_autonomy_set_enabled_from_request($autonomy_requested));
	update_option('lf_ai_autonomy_dry_run', isset($_POST['lf_ai_autonomy_dry_run']) ? '1' : '0');
	$max_retries = isset($_POST['lf_ai_autonomy_max_retries']) ? (int) $_POST['lf_ai_autonomy_max_retries'] : 3;
	update_option('lf_ai_autonomy_max_retries', max(1, min(10, $max_retries)));
	$cooldown = isset($_POST['lf_ai_autonomy_cooldown_seconds']) ? (int) $_POST['lf_ai_autonomy_cooldown_seconds'] : 900;
	update_option('lf_ai_autonomy_cooldown_seconds', max(60, min(86400, $cooldown)));
	$circuit_threshold = isset($_POST['lf_ai_autonomy_circuit_threshold']) ? (int) $_POST['lf_ai_autonomy_circuit_threshold'] : 3;
	update_option('lf_ai_autonomy_circuit_threshold', max(1, min(20, $circuit_threshold)));
	update_option('lf_ai_studio_keywords', isset($_POST['lf_ai_studio_keywords']) ? sanitize_textarea_field(wp_unslash($_POST['lf_ai_studio_keywords'])) : '');
	update_option('lf_ai_studio_scope', isset($_POST['lf_ai_studio_scope']) && $_POST['lf_ai_studio_scope'] === 'selected' ? 'selected' : 'all');
	$scope_types = isset($_POST['lf_ai_studio_scope_types']) && is_array($_POST['lf_ai_studio_scope_types'])
		? array_map('sanitize_text_field', $_POST['lf_ai_studio_scope_types'])
		: [];
	update_option('lf_ai_studio_scope_types', $scope_types);
	$style = isset($_POST['lf_ai_studio_style']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_studio_style'])) : 'professional';
	if (!in_array($style, ['professional', 'friendly', 'premium'], true)) {
		$style = 'professional';
	}
	update_option('lf_ai_studio_style', $style);
	$homepage_city = isset($_POST['lf_homepage_city']) ? sanitize_text_field(wp_unslash($_POST['lf_homepage_city'])) : '';
	update_option('lf_homepage_city', $homepage_city, true);
	$primary_kw = isset($_POST['lf_homepage_keyword_primary']) ? sanitize_text_field(wp_unslash($_POST['lf_homepage_keyword_primary'])) : '';
	$secondary_raw = isset($_POST['lf_homepage_keyword_secondary']) ? sanitize_textarea_field(wp_unslash($_POST['lf_homepage_keyword_secondary'])) : '';
	$secondary = array_filter(array_map('sanitize_text_field', preg_split('/\r\n|\r|\n|,/', $secondary_raw)));
	update_option('lf_homepage_keywords', [
		'primary' => $primary_kw,
		'secondary' => array_values($secondary),
	], true);
	update_option('lf_ai_airtable_enabled', isset($_POST['lf_ai_airtable_enabled']) ? '1' : '0');
	update_option('lf_ai_airtable_pat', isset($_POST['lf_ai_airtable_pat']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_pat'])) : '');
	update_option('lf_ai_airtable_base', isset($_POST['lf_ai_airtable_base']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_base'])) : '');
	update_option('lf_ai_airtable_table', isset($_POST['lf_ai_airtable_table']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_table'])) : '');
	update_option('lf_ai_airtable_view', isset($_POST['lf_ai_airtable_view']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_view'])) : '');
	update_option('lf_ai_airtable_reviews_table', isset($_POST['lf_ai_airtable_reviews_table']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_reviews_table'])) : '');
	update_option('lf_ai_airtable_reviews_view', isset($_POST['lf_ai_airtable_reviews_view']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_reviews_view'])) : '');
	$field_defaults = function_exists('lf_ai_studio_airtable_default_field_map') ? lf_ai_studio_airtable_default_field_map() : [];
	$field_input = isset($_POST['lf_ai_airtable_field_map']) && is_array($_POST['lf_ai_airtable_field_map'])
		? $_POST['lf_ai_airtable_field_map']
		: [];
	$sanitized_map = [];
	foreach ($field_defaults as $key => $label) {
		$value = isset($field_input[$key]) ? sanitize_text_field(wp_unslash((string) $field_input[$key])) : '';
		$sanitized_map[$key] = $value !== '' ? $value : $label;
	}
	if (!empty($sanitized_map)) {
		update_option('lf_ai_airtable_field_map', $sanitized_map);
	}
	$review_defaults = function_exists('lf_ai_studio_airtable_reviews_default_field_map') ? lf_ai_studio_airtable_reviews_default_field_map() : [];
	$review_input = isset($_POST['lf_ai_airtable_reviews_field_map']) && is_array($_POST['lf_ai_airtable_reviews_field_map'])
		? $_POST['lf_ai_airtable_reviews_field_map']
		: [];
	$review_map = [];
	foreach ($review_defaults as $key => $label) {
		$value = isset($review_input[$key]) ? sanitize_text_field(wp_unslash((string) $review_input[$key])) : '';
		$review_map[$key] = $value !== '' ? $value : $label;
	}
	if (!empty($review_map)) {
		update_option('lf_ai_airtable_reviews_field_map', $review_map);
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-ops&saved=1'));
	exit;
}

function lf_ai_studio_handle_orchestrator_save(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_orchestrator_save', 'lf_ai_studio_orchestrator_nonce');

	update_option('lf_ai_studio_enabled', isset($_POST['lf_ai_studio_enabled']) ? '1' : '0');
	update_option('lf_ai_studio_webhook', isset($_POST['lf_ai_studio_webhook']) ? esc_url_raw(wp_unslash($_POST['lf_ai_studio_webhook'])) : '');
	update_option('lf_ai_studio_secret', isset($_POST['lf_ai_studio_secret']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_studio_secret'])) : '');
	$auth_mode = isset($_POST['lf_ai_auth_mode']) ? sanitize_text_field(wp_unslash((string) $_POST['lf_ai_auth_mode'])) : 'compatibility';
	update_option('lf_ai_auth_mode', $auth_mode === 'strict_hmac' ? 'strict_hmac' : 'compatibility');
	$tolerance = isset($_POST['lf_ai_hmac_tolerance_seconds']) ? (int) $_POST['lf_ai_hmac_tolerance_seconds'] : 300;
	update_option('lf_ai_hmac_tolerance_seconds', max(60, min(1800, $tolerance)));
	$autonomy_requested = isset($_POST['lf_ai_autonomy_enabled']);
	update_option('lf_ai_autonomy_enabled', lf_ai_autonomy_set_enabled_from_request($autonomy_requested));
	update_option('lf_ai_autonomy_dry_run', isset($_POST['lf_ai_autonomy_dry_run']) ? '1' : '0');
	$max_retries = isset($_POST['lf_ai_autonomy_max_retries']) ? (int) $_POST['lf_ai_autonomy_max_retries'] : 3;
	update_option('lf_ai_autonomy_max_retries', max(1, min(10, $max_retries)));
	$cooldown = isset($_POST['lf_ai_autonomy_cooldown_seconds']) ? (int) $_POST['lf_ai_autonomy_cooldown_seconds'] : 900;
	update_option('lf_ai_autonomy_cooldown_seconds', max(60, min(86400, $cooldown)));
	$circuit_threshold = isset($_POST['lf_ai_autonomy_circuit_threshold']) ? (int) $_POST['lf_ai_autonomy_circuit_threshold'] : 3;
	update_option('lf_ai_autonomy_circuit_threshold', max(1, min(20, $circuit_threshold)));

	update_option('lf_ai_airtable_enabled', isset($_POST['lf_ai_airtable_enabled']) ? '1' : '0');
	update_option('lf_ai_airtable_pat', isset($_POST['lf_ai_airtable_pat']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_pat'])) : '');
	update_option('lf_ai_airtable_base', isset($_POST['lf_ai_airtable_base']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_base'])) : '');
	update_option('lf_ai_airtable_table', isset($_POST['lf_ai_airtable_table']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_table'])) : '');
	update_option('lf_ai_airtable_view', isset($_POST['lf_ai_airtable_view']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_view'])) : '');
	update_option('lf_ai_airtable_reviews_table', isset($_POST['lf_ai_airtable_reviews_table']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_reviews_table'])) : '');
	update_option('lf_ai_airtable_reviews_view', isset($_POST['lf_ai_airtable_reviews_view']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_reviews_view'])) : '');
	$field_defaults = function_exists('lf_ai_studio_airtable_default_field_map') ? lf_ai_studio_airtable_default_field_map() : [];
	$field_input = isset($_POST['lf_ai_airtable_field_map']) && is_array($_POST['lf_ai_airtable_field_map'])
		? $_POST['lf_ai_airtable_field_map']
		: [];
	$sanitized_map = [];
	foreach ($field_defaults as $key => $label) {
		$value = isset($field_input[$key]) ? sanitize_text_field(wp_unslash((string) $field_input[$key])) : '';
		$sanitized_map[$key] = $value !== '' ? $value : $label;
	}
	if (!empty($sanitized_map)) {
		update_option('lf_ai_airtable_field_map', $sanitized_map);
	}
	$review_defaults = function_exists('lf_ai_studio_airtable_reviews_default_field_map') ? lf_ai_studio_airtable_reviews_default_field_map() : [];
	$review_input = isset($_POST['lf_ai_airtable_reviews_field_map']) && is_array($_POST['lf_ai_airtable_reviews_field_map'])
		? $_POST['lf_ai_airtable_reviews_field_map']
		: [];
	$review_map = [];
	foreach ($review_defaults as $key => $label) {
		$value = isset($review_input[$key]) ? sanitize_text_field(wp_unslash((string) $review_input[$key])) : '';
		$review_map[$key] = $value !== '' ? $value : $label;
	}
	if (!empty($review_map)) {
		update_option('lf_ai_airtable_reviews_field_map', $review_map);
	}

	wp_safe_redirect(admin_url('admin.php?page=lf-global&saved=1'));
	exit;
}

function lf_ai_studio_handle_scope_save(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_scope_save', 'lf_ai_studio_scope_nonce');

	update_option('lf_ai_gen_homepage', isset($_POST['lf_ai_gen_homepage']) ? '1' : '0');
	update_option('lf_ai_gen_services', isset($_POST['lf_ai_gen_services']) ? '1' : '0');
	update_option('lf_ai_gen_service_areas', isset($_POST['lf_ai_gen_service_areas']) ? '1' : '0');
	update_option('lf_ai_gen_core_pages', isset($_POST['lf_ai_gen_core_pages']) ? '1' : '0');
	update_option('lf_ai_gen_blog_posts', isset($_POST['lf_ai_gen_blog_posts']) ? '1' : '0');
	update_option('lf_ai_gen_projects', isset($_POST['lf_ai_gen_projects']) ? '1' : '0');

	wp_safe_redirect(admin_url('admin.php?page=lf-ops&saved=1'));
	exit;
}

function lf_ai_studio_handle_image_settings_save(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_image_settings_save', 'lf_ai_studio_image_settings_nonce');
	$image_generation_limit = isset($_POST['lf_ai_image_generation_limit']) ? absint($_POST['lf_ai_image_generation_limit']) : 12;
	$image_generation_limit = max(1, min(60, $image_generation_limit));
	update_option('lf_ai_image_generation_limit', (string) $image_generation_limit, false);
	wp_safe_redirect(admin_url('admin.php?page=lf-ops&saved=1'));
	exit;
}

function lf_ai_studio_handle_generate(): void {
	if (!current_user_can('edit_theme_options')) {
		error_log('LF DEBUG: Regenerate Site blocked: insufficient permissions.');
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_generate', 'lf_ai_studio_generate_nonce');
	lf_ai_studio_maybe_cleanup_templates();
	$result = lf_ai_studio_run_homepage_generation();
	$redirect = admin_url('admin.php?page=lf-ops');
	if (!empty($result['error'])) {
		$redirect = add_query_arg('error', rawurlencode($result['error']), $redirect);
	} else {
		$redirect = add_query_arg('job', (string) ($result['job_id'] ?? ''), $redirect);
	}
	wp_safe_redirect($redirect);
	exit;
}

function lf_ai_studio_handle_retry(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_retry', 'lf_ai_studio_retry_nonce');
	lf_ai_studio_maybe_cleanup_templates();
	$job_id = isset($_GET['job_id']) ? absint($_GET['job_id']) : 0;
	if (!$job_id) {
		wp_safe_redirect(admin_url('admin.php?page=lf-ops&error=missing_job'));
		exit;
	}
	$request_payload = get_post_meta($job_id, 'lf_ai_job_request', true);
	if (!is_array($request_payload)) {
		wp_safe_redirect(admin_url('admin.php?page=lf-ops&error=missing_payload'));
		exit;
	}
	$result = lf_ai_studio_send_request($request_payload, $job_id);
	$redirect = admin_url('admin.php?page=lf-ops');
	if (!empty($result['error'])) {
		$redirect = add_query_arg('error', rawurlencode($result['error']), $redirect);
	} else {
		$redirect = add_query_arg('job', (string) $job_id, $redirect);
	}
	wp_safe_redirect($redirect);
	exit;
}

function lf_ai_studio_handle_manifest(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_manifest', 'lf_ai_studio_manifest_nonce');
	lf_ai_studio_maybe_cleanup_templates();
	$redirect = admin_url('admin.php?page=lf-ops');
	if (empty($_FILES['lf_site_manifest']) || !is_array($_FILES['lf_site_manifest'])) {
		update_option('lf_ai_studio_manifest_errors', [__('Manifest file is required.', 'leadsforward-core')], false);
		wp_safe_redirect(add_query_arg('manifest_error', '1', $redirect));
		exit;
	}
	$file = $_FILES['lf_site_manifest'];
	if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		update_option('lf_ai_studio_manifest_errors', [__('Manifest upload failed.', 'leadsforward-core')], false);
		wp_safe_redirect(add_query_arg('manifest_error', '1', $redirect));
		exit;
	}
	$raw = file_get_contents((string) ($file['tmp_name'] ?? ''));
	if (!is_string($raw) || trim($raw) === '') {
		update_option('lf_ai_studio_manifest_errors', [__('Manifest file is empty.', 'leadsforward-core')], false);
		wp_safe_redirect(add_query_arg('manifest_error', '1', $redirect));
		exit;
	}
	$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		$reason = function_exists('json_last_error_msg') ? json_last_error_msg() : '';
		$message = $reason ? sprintf(__('Manifest JSON is invalid: %s', 'leadsforward-core'), $reason) : __('Manifest JSON is invalid.', 'leadsforward-core');
		error_log('LF MANIFEST: ' . $message);
		update_option('lf_ai_studio_manifest_errors', [$message], false);
		wp_safe_redirect(add_query_arg('manifest_error', '1', $redirect));
		exit;
	}
	$errors = lf_ai_studio_validate_manifest($decoded);
	if (!empty($errors)) {
		error_log('LF MANIFEST: validation errors ' . print_r($errors, true));
		update_option('lf_ai_studio_manifest_errors', $errors, false);
		wp_safe_redirect(add_query_arg('manifest_error', '1', $redirect));
		exit;
	}
	$normalized = lf_ai_studio_normalize_manifest($decoded);
	update_option('lf_site_manifest', $normalized, false);
	if (function_exists('lf_seo_assign_keywords_from_manifest')) {
		lf_seo_assign_keywords_from_manifest($normalized);
	}
	delete_option('lf_ai_studio_manifest_errors');
	lf_ai_studio_sync_manifest_posts($normalized);
	$result = lf_ai_studio_run_generation();
	if (!empty($result['error'])) {
		update_option('lf_ai_studio_manifest_errors', [sprintf(__('Generation failed: %s', 'leadsforward-core'), (string) $result['error'])], false);
		wp_safe_redirect(add_query_arg('manifest_error', '1', $redirect));
		exit;
	}
	$redirect = add_query_arg('manifest', '1', $redirect);
	if (!empty($result['job_id'])) {
		$redirect = add_query_arg('job', (string) $result['job_id'], $redirect);
	}
	wp_safe_redirect($redirect);
	exit;
}

function lf_ai_studio_handle_manifest_template(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	$nonce = isset($_GET['lf_ai_studio_manifest_template_nonce']) ? sanitize_text_field(wp_unslash((string) $_GET['lf_ai_studio_manifest_template_nonce'])) : '';
	if (!wp_verify_nonce($nonce, 'lf_ai_studio_manifest_template')) {
		wp_die(__('Invalid request.', 'leadsforward-core'));
	}
	$template = lf_ai_studio_manifest_template();
	$payload = wp_json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if (!is_string($payload)) {
		wp_die(__('Failed to generate manifest template.', 'leadsforward-core'));
	}
	nocache_headers();
	header('Content-Type: application/json; charset=utf-8');
	header('Content-Disposition: attachment; filename="leadsforward-manifest-template.json"');
	echo $payload;
	exit;
}

function lf_ai_studio_parse_research_upload(array $file): array {
	if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		return ['ok' => false, 'errors' => [__('Research upload failed.', 'leadsforward-core')]];
	}
	$filetype = wp_check_filetype_and_ext((string) ($file['tmp_name'] ?? ''), (string) ($file['name'] ?? ''));
	$ext = strtolower((string) ($filetype['ext'] ?? ''));
	if ($ext === '') {
		$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
	}
	$type = strtolower((string) ($filetype['type'] ?? ''));
	$allowed_mimes = ['application/json', 'text/plain', 'application/octet-stream'];
	$looks_json = ($ext === 'json') || in_array($type, $allowed_mimes, true);
	if (!$looks_json) {
		return ['ok' => false, 'errors' => [__('Research file must be valid JSON.', 'leadsforward-core')]];
	}
	$raw = file_get_contents((string) ($file['tmp_name'] ?? ''));
	if (!is_string($raw) || trim($raw) === '') {
		return ['ok' => false, 'errors' => [__('Research file is empty.', 'leadsforward-core')]];
	}
	$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		$reason = function_exists('json_last_error_msg') ? json_last_error_msg() : '';
		$message = $reason ? sprintf(__('Research JSON is invalid: %s', 'leadsforward-core'), $reason) : __('Research JSON is invalid.', 'leadsforward-core');
		return ['ok' => false, 'errors' => [$message]];
	}
	$errors = lf_ai_studio_validate_research_document($decoded);
	if (!empty($errors)) {
		return ['ok' => false, 'errors' => $errors];
	}
	return ['ok' => true, 'document' => $decoded];
}

function lf_ai_studio_handle_research(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_research', 'lf_ai_studio_research_nonce');
	$redirect = admin_url('admin.php?page=lf-ops');
	if (empty($_FILES['lf_site_research']) || !is_array($_FILES['lf_site_research'])) {
		update_option('lf_ai_studio_research_errors', [__('Research file is required.', 'leadsforward-core')], false);
		wp_safe_redirect(add_query_arg('research_error', '1', $redirect));
		exit;
	}
	$result = lf_ai_studio_parse_research_upload($_FILES['lf_site_research']);
	if (empty($result['ok'])) {
		update_option('lf_ai_studio_research_errors', $result['errors'] ?? [__('Research file must be valid JSON.', 'leadsforward-core')], false);
		wp_safe_redirect(add_query_arg('research_error', '1', $redirect));
		exit;
	}
	update_option('lf_site_research_document', $result['document'], false);
	delete_option('lf_ai_studio_research_errors');
	wp_safe_redirect(add_query_arg('research', '1', $redirect));
	exit;
}

function lf_ai_studio_handle_research_ajax(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_send_json_error(['message' => __('Insufficient permissions.', 'leadsforward-core')], 403);
	}
	check_ajax_referer('lf_ai_studio_research_ajax', 'nonce');
	if (empty($_FILES['lf_site_research']) || !is_array($_FILES['lf_site_research'])) {
		wp_send_json_error(['message' => __('Research file is required.', 'leadsforward-core')], 400);
	}
	$result = lf_ai_studio_parse_research_upload($_FILES['lf_site_research']);
	if (empty($result['ok'])) {
		wp_send_json_error(['message' => __('Research validation failed.', 'leadsforward-core'), 'errors' => $result['errors'] ?? []], 422);
	}
	update_option('lf_site_research_document', $result['document'], false);
	delete_option('lf_ai_studio_research_errors');
	wp_send_json_success(['message' => __('Research uploaded successfully.', 'leadsforward-core')]);
}

function lf_ai_studio_handle_run_audit(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_run_audit', 'lf_ai_studio_run_audit_nonce');
	$manifest = lf_ai_studio_get_manifest();
	lf_ai_studio_ensure_core_page_sections($manifest, true);
	$report = lf_ai_studio_run_content_audit('manual');
	lf_ai_studio_store_audit_report($report, 0);
	$redirect = add_query_arg('audit', '1', admin_url('admin.php?page=lf-ops'));
	$has_issues = false;
	foreach ((array) ($report['pages'] ?? []) as $page) {
		if (!empty($page['issues'])) {
			$has_issues = true;
			break;
		}
	}
	if ($has_issues) {
		$repair_request = lf_ai_studio_build_repair_request($report, []);
		if (is_array($repair_request) && empty($repair_request['error'])) {
			$job_id = lf_ai_studio_create_job($repair_request);
			if ($job_id) {
				lf_ai_studio_send_request($repair_request, $job_id);
				$redirect = add_query_arg('job', (string) $job_id, $redirect);
			}
		}
	}
	wp_safe_redirect($redirect);
	exit;
}

function lf_ai_studio_handle_regen_blog_posts(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_regen_blog_posts', 'lf_ai_studio_regen_blog_posts_nonce');
	$enabled = get_option('lf_ai_studio_enabled', '0') === '1';
	if (!$enabled) {
		wp_safe_redirect(add_query_arg('error', rawurlencode(__('Website Manifester is disabled.', 'leadsforward-core')), admin_url('admin.php?page=lf-ops')));
		exit;
	}
	$webhook = (string) get_option('lf_ai_studio_webhook', '');
	$secret = (string) get_option('lf_ai_studio_secret', '');
	if ($webhook === '' || $secret === '') {
		wp_safe_redirect(add_query_arg('error', rawurlencode(__('Webhook URL and shared secret are required.', 'leadsforward-core')), admin_url('admin.php?page=lf-ops')));
		exit;
	}
	$request = lf_ai_studio_build_blog_payload();
	if (!is_array($request) || !empty($request['error'])) {
		$message = is_array($request) ? (string) ($request['error'] ?? '') : '';
		$message = $message !== '' ? $message : __('Blog payload build failed.', 'leadsforward-core');
		wp_safe_redirect(add_query_arg('error', rawurlencode($message), admin_url('admin.php?page=lf-ops')));
		exit;
	}
	$job_id = lf_ai_studio_create_job($request);
	lf_ai_studio_send_request($request, $job_id);
	$redirect = add_query_arg('job', (string) $job_id, admin_url('admin.php?page=lf-ops'));
	wp_safe_redirect($redirect);
	exit;
}

function lf_ai_studio_handle_save_logo(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_save_logo', 'lf_ai_studio_logo_nonce');
	$prev_logo_id = function_exists('lf_get_global_option')
		? (int) lf_get_global_option('lf_global_logo', 0)
		: (int) get_option('options_lf_global_logo', 0);
	$logo_id = isset($_POST['lf_global_logo']) ? (int) $_POST['lf_global_logo'] : 0;
	if (function_exists('lf_update_global_option_value')) {
		lf_update_global_option_value('lf_global_logo', (string) $logo_id);
	} else {
		update_option('options_lf_global_logo', $logo_id);
	}
	if ($logo_id > 0 && $logo_id !== $prev_logo_id && function_exists('lf_branding_auto_from_logo')) {
		lf_branding_auto_from_logo($logo_id);
	}
	if ($prev_logo_id > 0 && $prev_logo_id !== $logo_id) {
		update_post_meta($prev_logo_id, '_lf_skip_auto_distribution', '0');
	}
	if ($logo_id > 0) {
		update_post_meta($logo_id, '_lf_skip_auto_distribution', '1');
	}
	if (function_exists('lf_invalidate_media_index_cache')) {
		lf_invalidate_media_index_cache();
	}
	$redirect = add_query_arg('logo', '1', admin_url('admin.php?page=lf-ops'));
	wp_safe_redirect($redirect);
	exit;
}

function lf_ai_studio_process_images_upload(array $files): array {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$uploaded = [];
	$errors = [];
	$count = is_array($files['name'] ?? null) ? count($files['name']) : 0;
	for ($i = 0; $i < $count; $i++) {
		$name = (string) ($files['name'][$i] ?? '');
		if ($name === '') {
			continue;
		}
		$file = [
			'name' => $name,
			'type' => (string) ($files['type'][$i] ?? ''),
			'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
			'error' => (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
			'size' => (int) ($files['size'][$i] ?? 0),
		];
		if (function_exists('lf_image_intelligence_generate_upload_filename')) {
			$file['name'] = lf_image_intelligence_generate_upload_filename($name);
		}
		$attachment_id = media_handle_sideload($file, 0);
		if (is_wp_error($attachment_id)) {
			$errors[] = sprintf('%s: %s', $name, $attachment_id->get_error_message());
			continue;
		}
		if (function_exists('lf_image_intelligence_finalize_uploaded_attachment')) {
			lf_image_intelligence_finalize_uploaded_attachment((int) $attachment_id);
		}
		if (function_exists('lf_image_intelligence_maybe_set_alt_text') && function_exists('lf_image_intelligence_upload_context_defaults')) {
			lf_image_intelligence_maybe_set_alt_text((int) $attachment_id, lf_image_intelligence_upload_context_defaults());
		}
		$uploaded[] = [
			'id' => (int) $attachment_id,
			'name' => $name,
			'url' => (string) wp_get_attachment_image_url((int) $attachment_id, 'medium'),
			'full_url' => (string) wp_get_attachment_url((int) $attachment_id),
		];
	}
	if (!empty($uploaded)) {
		lf_invalidate_media_index_cache();
		lf_build_media_index();
	}
	return [
		'uploaded' => $uploaded,
		'uploaded_count' => count($uploaded),
		'errors' => $errors,
		'error_count' => count($errors),
	];
}

function lf_ai_studio_handle_images_upload_ajax(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_send_json_error(['message' => __('Insufficient permissions.', 'leadsforward-core')], 403);
	}
	check_ajax_referer('lf_ai_studio_images_upload', 'nonce');
	if (empty($_FILES['lf_manifest_images']) || !is_array($_FILES['lf_manifest_images'])) {
		wp_send_json_error(['message' => __('Please choose one or more images before uploading.', 'leadsforward-core')], 400);
	}
	$result = lf_ai_studio_process_images_upload($_FILES['lf_manifest_images']);
	if ($result['uploaded_count'] === 0) {
		wp_send_json_error([
			'message' => __('No images were uploaded.', 'leadsforward-core'),
			'errors' => $result['errors'],
		], 422);
	}
	wp_send_json_success([
		'uploaded' => $result['uploaded'],
		'uploaded_count' => $result['uploaded_count'],
		'error_count' => $result['error_count'],
		'errors' => $result['errors'],
	]);
}

function lf_ai_studio_handle_images_upload(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_images_upload', 'lf_ai_studio_images_upload_nonce');
	if (empty($_FILES['lf_manifest_images']) || !is_array($_FILES['lf_manifest_images'])) {
		wp_safe_redirect(add_query_arg('images_error', 'missing', admin_url('admin.php?page=lf-ops')));
		exit;
	}
	$result = lf_ai_studio_process_images_upload($_FILES['lf_manifest_images']);
	$redirect = add_query_arg('images_uploaded', (string) $result['uploaded_count'], admin_url('admin.php?page=lf-ops'));
	if ($result['error_count'] > 0) {
		$redirect = add_query_arg('images_errors', (string) $result['error_count'], $redirect);
	}
	wp_safe_redirect($redirect);
	exit;
}

function lf_ai_studio_latest_job_id_for_debug(): int {
	$posts = get_posts([
		'post_type' => LF_AI_STUDIO_JOB_CPT,
		'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
		'posts_per_page' => 1,
		'orderby' => 'date',
		'order' => 'DESC',
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	return !empty($posts) ? (int) $posts[0] : 0;
}

function lf_ai_studio_decode_job_payload($payload): array {
	if (is_array($payload)) {
		return $payload;
	}
	if (is_string($payload) && trim($payload) !== '') {
		$decoded = json_decode($payload, true);
		if (is_array($decoded)) {
			return $decoded;
		}
	}
	return [];
}

function lf_ai_studio_extract_media_annotations_for_debug(array $payload): array {
	$candidates = [];
	$top = $payload['media_annotations'] ?? null;
	if (is_array($top)) {
		$candidates = $top;
	}
	if (empty($candidates) && is_array($payload['apply'] ?? null)) {
		$apply_media = $payload['apply']['media_annotations'] ?? null;
		if (is_array($apply_media)) {
			$candidates = $apply_media;
		}
	}
	if (empty($candidates) && is_array($payload['vision'] ?? null)) {
		$vision_media = $payload['vision']['media_annotations'] ?? null;
		if (is_array($vision_media)) {
			$candidates = $vision_media;
		}
	}
	if (empty($candidates) && is_array($payload['image_analysis'] ?? null)) {
		$analysis_media = $payload['image_analysis']['media_annotations'] ?? null;
		if (is_array($analysis_media)) {
			$candidates = $analysis_media;
		}
	}
	return is_array($candidates) ? $candidates : [];
}

function lf_ai_studio_render_page(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	$error = isset($_GET['error']) ? sanitize_text_field(wp_unslash((string) $_GET['error'])) : '';
	$job_id = isset($_GET['job']) ? absint($_GET['job']) : 0;
	$job_status = $job_id ? (string) get_post_meta($job_id, 'lf_ai_job_status', true) : '';
	$job_error = $job_id ? (string) get_post_meta($job_id, 'lf_ai_job_error', true) : '';
	$reset_done = isset($_GET['reset_done']) && $_GET['reset_done'] === '1';
	$reset_error = isset($_GET['reset_error']) ? sanitize_text_field(wp_unslash((string) $_GET['reset_error'])) : '';
	$manifest = lf_ai_studio_get_manifest();
	$manifest_errors = get_option('lf_ai_studio_manifest_errors', []);
	$manifest_saved = isset($_GET['manifest']) && $_GET['manifest'] === '1';
	$research = lf_ai_studio_get_research_document();
	$research_errors = get_option('lf_ai_studio_research_errors', []);
	$research_saved = isset($_GET['research']) && $_GET['research'] === '1';
	$audit_saved = isset($_GET['audit']) && $_GET['audit'] === '1';
	$repair_queued = isset($_GET['job']) ? absint($_GET['job']) : 0;
	$logo_saved = isset($_GET['logo']) && $_GET['logo'] === '1';
	$images_uploaded = isset($_GET['images_uploaded']) ? absint($_GET['images_uploaded']) : 0;
	$images_errors = isset($_GET['images_errors']) ? absint($_GET['images_errors']) : 0;
	$images_error = isset($_GET['images_error']) ? sanitize_text_field(wp_unslash((string) $_GET['images_error'])) : '';
	$audit_report = get_option('lf_ai_studio_last_audit', []);
	$logo_id = function_exists('lf_get_global_option')
		? (int) lf_get_global_option('lf_global_logo', 0)
		: (int) get_option('options_lf_global_logo', 0);
	$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
	$airtable_settings = function_exists('lf_ai_studio_airtable_get_settings')
		? lf_ai_studio_airtable_get_settings()
		: [];
	$airtable_enabled = !empty($airtable_settings['enabled']);
	$airtable_ready = $airtable_enabled
		&& !empty($airtable_settings['pat'])
		&& !empty($airtable_settings['base_id'])
		&& !empty($airtable_settings['table']);
	$gen_homepage = get_option('lf_ai_gen_homepage', '1') === '1';
	$gen_services = get_option('lf_ai_gen_services', '1') === '1';
	$gen_service_areas = get_option('lf_ai_gen_service_areas', '1') === '1';
	$gen_core_pages = get_option('lf_ai_gen_core_pages', '1') === '1';
	$gen_blog_posts = get_option('lf_ai_gen_blog_posts', '1') === '1';
	$gen_projects = get_option('lf_ai_gen_projects', '1') === '1';
	$image_generation_limit = max(1, min(60, (int) get_option('lf_ai_image_generation_limit', 12)));
	$vision_debug_job_id = $job_id > 0 ? $job_id : lf_ai_studio_latest_job_id_for_debug();
	$vision_debug_payload = [];
	$vision_annotations = [];
	$vision_applied_count = 0;
	$vision_warning_items = [];
	if ($vision_debug_job_id > 0) {
		$vision_debug_payload = lf_ai_studio_decode_job_payload(get_post_meta($vision_debug_job_id, 'lf_ai_job_response', true));
		$vision_annotations = lf_ai_studio_extract_media_annotations_for_debug($vision_debug_payload);
		$vision_applied_count = (int) get_post_meta($vision_debug_job_id, 'lf_ai_job_media_annotations_applied', true);
		$warnings_raw = get_post_meta($vision_debug_job_id, 'lf_ai_job_quality_warnings', true);
		if (is_array($warnings_raw)) {
			$vision_warning_items = array_values(array_filter(array_map('sanitize_text_field', $warnings_raw)));
		}
	}
	$vision_received_count = is_array($vision_annotations) ? count($vision_annotations) : 0;
	$vision_samples = [];
	if (!empty($vision_annotations)) {
		foreach (array_slice($vision_annotations, 0, 3) as $row) {
			if (!is_array($row)) {
				continue;
			}
			$vision_samples[] = [
				'attachment_id' => (int) ($row['attachment_id'] ?? 0),
				'alt_text' => sanitize_text_field((string) ($row['alt_text'] ?? ($row['altText'] ?? ($row['alt'] ?? '')))),
				'title' => sanitize_text_field((string) ($row['title'] ?? ($row['image_title'] ?? ''))),
				'caption' => sanitize_text_field((string) ($row['caption'] ?? ($row['image_caption'] ?? ''))),
				'description' => sanitize_text_field((string) ($row['description'] ?? ($row['summary'] ?? ''))),
			];
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Website Manifester', 'leadsforward-core'); ?></h1>
		<p class="description"><?php esc_html_e('Deterministic, orchestrator-driven generation for full site content and structure.', 'leadsforward-core'); ?></p>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Website Manifester settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($manifest_saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Manifest uploaded. Generation queued and running in the background.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($research_saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Research document uploaded.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($audit_saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Content audit completed.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($logo_saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Logo saved and brand colors updated.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($images_uploaded > 0) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(_n('%d image uploaded to Media Library.', '%d images uploaded to Media Library.', $images_uploaded, 'leadsforward-core'), $images_uploaded)); ?></p></div>
		<?php endif; ?>
		<?php if ($images_errors > 0) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html(sprintf(_n('%d image failed to upload.', '%d images failed to upload.', $images_errors, 'leadsforward-core'), $images_errors)); ?></p></div>
		<?php endif; ?>
		<?php if ($images_error === 'missing') : ?>
			<div class="notice notice-error"><p><?php esc_html_e('Please choose one or more images before uploading.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($repair_queued) : ?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html(sprintf(__('Repair job queued (#%d).', 'leadsforward-core'), $repair_queued)); ?></p></div>
		<?php endif; ?>
		<?php if ($reset_done) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Site reset complete.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($reset_error === 'confirm') : ?>
			<div class="notice notice-error"><p><?php esc_html_e('Reset confirmation did not match. Type RESET to continue.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($reset_error === 'ack') : ?>
			<div class="notice notice-error"><p><?php esc_html_e('Please confirm you understand this will delete site content before resetting.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($job_id && $job_status) : ?>
			<?php if (in_array($job_status, ['queued', 'running'], true)) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html(sprintf(__('Generation job #%d is running. Refresh in a minute to see completion.', 'leadsforward-core'), $job_id)); ?></p></div>
			<?php elseif ($job_status === 'done') : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(__('Generation job #%d completed successfully.', 'leadsforward-core'), $job_id)); ?></p></div>
			<?php elseif ($job_status === 'failed') : ?>
				<div class="notice notice-error"><p><?php echo esc_html(sprintf(__('Generation job #%d failed: %s', 'leadsforward-core'), $job_id, $job_error ?: __('Unknown error', 'leadsforward-core'))); ?></p></div>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ($error) : ?>
			<div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
		<?php endif; ?>
		<?php if (is_array($manifest_errors) && !empty($manifest_errors)) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e('Manifest validation failed:', 'leadsforward-core'); ?></strong></p>
				<ul>
					<?php foreach ($manifest_errors as $err) : ?>
						<li><?php echo esc_html((string) $err); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php if (is_array($research_errors) && !empty($research_errors)) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e('Research validation failed:', 'leadsforward-core'); ?></strong></p>
				<ul>
					<?php foreach ($research_errors as $err) : ?>
						<li><?php echo esc_html((string) $err); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<div class="card lf-manifester-card" style="max-width: 980px; padding: 16px; margin: 16px 0;">
			<h2 style="margin-top:0;"><?php esc_html_e('Website Manifester', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Follow the steps below to generate a full site with consistent branding and content.', 'leadsforward-core'); ?></p>
			<?php $research_prompt_url = LF_THEME_URI . '/docs/06_AI_PROMPT_ENGINE.md'; ?>
			<?php $global_settings_url = admin_url('admin.php?page=lf-global'); ?>
			<?php $template_url = wp_nonce_url(admin_url('admin-post.php?action=lf_ai_studio_manifest_template'), 'lf_ai_studio_manifest_template', 'lf_ai_studio_manifest_template_nonce'); ?>

			<div class="lf-manifester-steps">
				<div class="lf-manifester-step">
					<div class="lf-manifester-step__badge">1</div>
					<div class="lf-manifester-step__content">
						<h3><?php esc_html_e('Connect your API settings', 'leadsforward-core'); ?></h3>
						<p class="description"><?php esc_html_e('Add your Orchestrator + Airtable credentials in Global Settings before you generate.', 'leadsforward-core'); ?></p>
						<a class="button" href="<?php echo esc_url($global_settings_url); ?>"><?php esc_html_e('Open Global Settings', 'leadsforward-core'); ?></a>
					</div>
				</div>

				<div class="lf-manifester-step">
					<div class="lf-manifester-step__badge">2</div>
					<div class="lf-manifester-step__content">
						<h3><?php esc_html_e('Select your Airtable project', 'leadsforward-core'); ?></h3>
						<p class="description"><?php esc_html_e('Pick a project first. If you prefer a manifest file, upload it below as an alternate source.', 'leadsforward-core'); ?></p>
						<div class="lf-manifester-source">
							<div class="lf-manifester-panel" id="lf-airtable-picker">
								<h4 style="margin-top:0;"><?php esc_html_e('Airtable Projects', 'leadsforward-core'); ?></h4>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 12px;">
									<?php wp_nonce_field('lf_ai_studio_scope_save', 'lf_ai_studio_scope_nonce'); ?>
									<input type="hidden" name="action" value="lf_ai_studio_scope_save" />
									<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
										<strong><?php esc_html_e('Generate:', 'leadsforward-core'); ?></strong>
										<label><input type="checkbox" name="lf_ai_gen_homepage" value="1" <?php checked($gen_homepage); ?> /> <?php esc_html_e('Homepage', 'leadsforward-core'); ?></label>
										<label><input type="checkbox" name="lf_ai_gen_services" value="1" <?php checked($gen_services); ?> /> <?php esc_html_e('Service pages', 'leadsforward-core'); ?></label>
										<label><input type="checkbox" name="lf_ai_gen_service_areas" value="1" <?php checked($gen_service_areas); ?> /> <?php esc_html_e('Service area pages', 'leadsforward-core'); ?></label>
										<label><input type="checkbox" name="lf_ai_gen_core_pages" value="1" <?php checked($gen_core_pages); ?> /> <?php esc_html_e('Core pages', 'leadsforward-core'); ?></label>
										<label><input type="checkbox" name="lf_ai_gen_blog_posts" value="1" <?php checked($gen_blog_posts); ?> /> <?php esc_html_e('AI blog posts (3 now + 2 weekly)', 'leadsforward-core'); ?></label>
										<label><input type="checkbox" name="lf_ai_gen_projects" value="1" <?php checked($gen_projects); ?> /> <?php esc_html_e('Projects', 'leadsforward-core'); ?></label>
										<button type="submit" class="button"><?php esc_html_e('Save Scope', 'leadsforward-core'); ?></button>
									</div>
									<p class="description" style="margin-top:8px;"><?php esc_html_e('Defaults to everything. Manifest can override with generation_scope=homepage_only.', 'leadsforward-core'); ?></p>
								</form>
								<?php if (!$airtable_ready) : ?>
									<div class="notice notice-warning inline">
										<p><?php esc_html_e('Airtable is not configured yet. Add your PAT, Base ID, and Table in Global Settings above, then save.', 'leadsforward-core'); ?></p>
									</div>
								<?php endif; ?>
								<div class="lf-airtable-grid">
									<div class="lf-airtable-search">
										<label class="screen-reader-text" for="lf-airtable-search"><?php esc_html_e('Search Airtable projects', 'leadsforward-core'); ?></label>
										<input type="text" id="lf-airtable-search" class="regular-text" placeholder="<?php esc_attr_e('Search Airtable projects…', 'leadsforward-core'); ?>" <?php echo $airtable_ready ? '' : 'disabled'; ?> />
										<div id="lf-airtable-results" class="lf-airtable-results"></div>
									</div>
									<div class="lf-airtable-preview">
										<div id="lf-airtable-preview" class="lf-airtable-preview-card">
											<?php esc_html_e('Select a project to preview before generating.', 'leadsforward-core'); ?>
										</div>
										<div id="lf-airtable-status" class="lf-airtable-status" role="status" aria-live="polite"></div>
									</div>
								</div>
							</div>
							<div class="lf-manifester-panel">
								<h4 style="margin-top:0;"><?php esc_html_e('Manifest Upload (Deterministic)', 'leadsforward-core'); ?></h4>
								<p class="description"><?php esc_html_e('Use a manifest JSON when you want full control over business data, services, and site structure.', 'leadsforward-core'); ?></p>
								<?php if (!empty($manifest)) : ?>
									<?php
									$site_name = (string) ($manifest['business']['name'] ?? '');
									$site_niche = (string) ($manifest['business']['niche'] ?? '');
									$site_city = (string) ($manifest['business']['primary_city'] ?? ($manifest['business']['address']['city'] ?? ''));
									?>
									<p class="description">
										<?php echo esc_html(sprintf(__('Active manifest: %1$s (%2$s) — %3$s', 'leadsforward-core'), $site_name ?: __('Unnamed', 'leadsforward-core'), $site_niche ?: __('No niche', 'leadsforward-core'), $site_city ?: __('No city', 'leadsforward-core'))); ?>
									</p>
								<?php endif; ?>
								<form id="lf-ai-manifest-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
									<?php wp_nonce_field('lf_ai_studio_manifest', 'lf_ai_studio_manifest_nonce'); ?>
									<input type="hidden" name="action" value="lf_ai_studio_manifest" />
									<input type="file" name="lf_site_manifest" id="lf_site_manifest" class="lf-manifester-file" accept="application/json,.json" />
									<button type="submit" class="button button-primary lf-manifester-hidden-submit"><?php esc_html_e('Upload Manifest', 'leadsforward-core'); ?></button>
								</form>
								<p class="description">
									<a class="button" href="<?php echo esc_url($template_url); ?>"><?php esc_html_e('Download Manifest Template', 'leadsforward-core'); ?></a>
								</p>
							</div>
						</div>
					</div>
				</div>

				<div class="lf-manifester-step">
					<div class="lf-manifester-step__badge">3</div>
					<div class="lf-manifester-step__content">
						<h3><?php esc_html_e('Research runs automatically', 'leadsforward-core'); ?></h3>
						<p class="description">
							<?php
							echo wp_kses(
								sprintf(
									__('n8n runs a research pass to build positioning, SEO strategy, and FAQ angles before content is written. You can review the <a href="%s" target="_blank" rel="noopener noreferrer">research template</a> any time.', 'leadsforward-core'),
									esc_url($research_prompt_url)
								),
								[
									'a' => [
										'href' => true,
										'target' => true,
										'rel' => true,
									],
								]
							);
							?>
						</p>
						<?php if (!empty($research)) : ?>
							<div class="lf-manifester-status is-success"><?php esc_html_e('Research document is already stored and will be reused.', 'leadsforward-core'); ?></div>
						<?php else : ?>
							<div class="lf-manifester-status is-info"><?php esc_html_e('Research will be generated during this run.', 'leadsforward-core'); ?></div>
						<?php endif; ?>
					</div>
				</div>

				<div class="lf-manifester-step">
					<div class="lf-manifester-step__badge">4</div>
					<div class="lf-manifester-step__content">
						<h3><?php esc_html_e('Upload required images for auto-distribution', 'leadsforward-core'); ?></h3>
						<p class="description"><?php esc_html_e('Upload your image library now. The theme auto-optimizes/compresses images, converts PNG to lightweight JPG when possible, normalizes filenames, and fills missing ALT text before deterministic placement.', 'leadsforward-core'); ?></p>
						<form id="lf-manifester-images-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
							<?php wp_nonce_field('lf_ai_studio_images_upload', 'lf_ai_studio_images_upload_nonce'); ?>
							<input type="hidden" name="action" value="lf_ai_studio_images_upload" />
							<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
								<input id="lf-manifester-images" type="file" name="lf_manifest_images[]" class="lf-manifester-file" multiple accept="image/*" />
								<button type="submit" class="button lf-manifester-hidden-submit"><?php esc_html_e('Upload Images to Media Library', 'leadsforward-core'); ?></button>
							</div>
						</form>
						<div id="lf-manifester-images-preview" class="lf-manifester-images-preview"></div>
						<div id="lf-manifester-images-status" class="lf-manifester-status" role="status" aria-live="polite"></div>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
							<?php wp_nonce_field('lf_ai_studio_image_settings_save', 'lf_ai_studio_image_settings_nonce'); ?>
							<input type="hidden" name="action" value="lf_ai_studio_image_settings_save" />
							<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
								<label for="lf_ai_image_generation_limit"><?php esc_html_e('Hybrid image generation limit per run', 'leadsforward-core'); ?></label>
								<input id="lf_ai_image_generation_limit" type="number" name="lf_ai_image_generation_limit" min="1" max="60" value="<?php echo esc_attr((string) $image_generation_limit); ?>" style="width:76px;" />
								<button type="submit" class="button"><?php esc_html_e('Save Image Settings', 'leadsforward-core'); ?></button>
							</div>
						</form>
						<p class="description" style="margin-top:8px;">
							<?php esc_html_e('Hybrid image mode targets only missing hero/content slots (up to your limit). If your workflow returns image annotations, they are applied directly; otherwise the theme falls back to media-library candidate mapping and deterministic placement.', 'leadsforward-core'); ?>
						</p>
						<p class="description" style="margin-top:8px;">
							<?php esc_html_e('Naming strategy examples: roof-repair-kansas-city-1.jpg, kitchen-remodel-sarasota-modern.jpg, bathroom-remodel-before-after.jpg, general-contractor-team.jpg. Include service + city + niche words in filenames for best matching.', 'leadsforward-core'); ?>
						</p>
					</div>
				</div>

				<div class="lf-manifester-step">
					<div class="lf-manifester-step__badge">5</div>
					<div class="lf-manifester-step__content">
						<h3><?php esc_html_e('Upload your logo (optional)', 'leadsforward-core'); ?></h3>
						<p class="description"><?php esc_html_e('Your logo sets the brand colors automatically, but you can skip it.', 'leadsforward-core'); ?></p>
						<div class="lf-manifester-logo">
							<form id="lf-manifester-logo-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
								<?php wp_nonce_field('lf_ai_studio_save_logo', 'lf_ai_studio_logo_nonce'); ?>
								<input type="hidden" name="action" value="lf_ai_studio_save_logo" />
								<div style="display:flex;flex-wrap:wrap;align-items:center;gap:16px;">
									<div>
										<img id="lf-manifester-logo-preview" src="<?php echo esc_url($logo_url); ?>" style="max-height:60px;<?php echo $logo_url ? '' : 'display:none;'; ?>" alt="" />
									</div>
									<input type="hidden" name="lf_global_logo" id="lf_manifester_logo" value="<?php echo esc_attr((string) $logo_id); ?>" />
									<button type="button" class="button" id="lf-manifester-logo-select"><?php esc_html_e('Select Logo', 'leadsforward-core'); ?></button>
									<button type="button" class="button" id="lf-manifester-logo-clear"><?php esc_html_e('Remove Logo', 'leadsforward-core'); ?></button>
								</div>
								<p class="description" style="margin-top:6px;"><?php esc_html_e('Selecting a logo immediately applies your palette.', 'leadsforward-core'); ?></p>
							</form>
						</div>
					</div>
				</div>

				<div class="lf-manifester-step lf-manifester-step--action">
					<div class="lf-manifester-step__badge">6</div>
					<div class="lf-manifester-step__content">
						<h3><?php esc_html_e('Manifest your website', 'leadsforward-core'); ?></h3>
						<p class="description"><?php esc_html_e('We will use the manifest file if one is selected, otherwise the selected Airtable project.', 'leadsforward-core'); ?></p>
						<button type="button" class="button button-primary button-hero" id="lf-manifester-generate" disabled>
							<?php esc_html_e('Manifest Your Website', 'leadsforward-core'); ?>
						</button>
						<div id="lf-manifester-status" class="lf-manifester-status" role="status" aria-live="polite"></div>
						<?php
						$progress = $job_id ? get_post_meta($job_id, 'lf_ai_job_progress', true) : [];
						$progress_percent = is_array($progress) ? (float) ($progress['percent'] ?? 0) : 0;
						$progress_label = is_array($progress) ? (string) ($progress['step'] ?? ($progress['message'] ?? '')) : '';
						if ($job_id && $job_status && $progress_label === '') {
							if ($job_status === 'done') {
								$progress_label = __('Complete.', 'leadsforward-core');
								$progress_percent = 100;
							} elseif ($job_status === 'failed') {
								$progress_label = __('Failed.', 'leadsforward-core');
							} else {
								$progress_label = __('In progress…', 'leadsforward-core');
							}
						}
						?>
						<div class="lf-manifester-progress" data-job-id="<?php echo esc_attr((string) $job_id); ?>" data-job-status="<?php echo esc_attr($job_status); ?>">
							<div class="lf-manifester-progress__bar">
								<span style="width: <?php echo esc_attr((string) $progress_percent); ?>%;"></span>
							</div>
							<div class="lf-manifester-progress__label"><?php echo esc_html($progress_label); ?></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0;">
			<h2 style="margin-top:0;"><?php esc_html_e('Research Override (Optional)', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Upload a research JSON to override the automated research step for the next generation.', 'leadsforward-core'); ?></p>
			<form id="lf-ai-research-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field('lf_ai_studio_research', 'lf_ai_studio_research_nonce'); ?>
				<input type="hidden" name="action" value="lf_ai_studio_research" />
				<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
					<input type="file" name="lf_site_research" id="lf_site_research" class="lf-manifester-file" accept="application/json,.json" />
					<a class="button" href="<?php echo esc_url($research_prompt_url); ?>" download><?php esc_html_e('Download Master Research Prompt', 'leadsforward-core'); ?></a>
				</div>
			</form>
			<div id="lf-research-status" class="lf-manifester-status" role="status" aria-live="polite"></div>
			<?php if (!empty($research)) : ?>
				<p class="description" style="margin-top:6px;"><?php esc_html_e('Research document stored and ready for the next generation run.', 'leadsforward-core'); ?></p>
			<?php endif; ?>
		</div>
		<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0;">
			<h2 style="margin-top:0;"><?php esc_html_e('Content QA Report', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Audit completion and default content across all pages. Auto-repair runs once if issues are found.', 'leadsforward-core'); ?></p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 12px;">
				<?php wp_nonce_field('lf_ai_studio_run_audit', 'lf_ai_studio_run_audit_nonce'); ?>
				<input type="hidden" name="action" value="lf_ai_studio_run_audit" />
				<button type="submit" class="button"><?php esc_html_e('Run Audit', 'leadsforward-core'); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 12px;">
				<?php wp_nonce_field('lf_ai_studio_regen_blog_posts', 'lf_ai_studio_regen_blog_posts_nonce'); ?>
				<input type="hidden" name="action" value="lf_ai_studio_regen_blog_posts" />
				<button type="submit" class="button"><?php esc_html_e('Regenerate AI Blog Posts', 'leadsforward-core'); ?></button>
				<p class="description" style="margin-top:6px;"><?php esc_html_e('Rebuilds AI blog posts only (does not change core pages).', 'leadsforward-core'); ?></p>
			</form>
			<?php if (is_array($audit_report) && !empty($audit_report)) : ?>
				<?php
				$summary = $audit_report['summary'] ?? [];
				$pages = $audit_report['pages'] ?? [];
				$cta_dupes = $audit_report['cta_duplicates'] ?? [];
				$timestamp = isset($audit_report['timestamp']) ? absint($audit_report['timestamp']) : 0;
				$last_run = $timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : '';
				?>
				<p class="description">
					<?php echo esc_html(sprintf(__('Last audit: %s', 'leadsforward-core'), $last_run ?: __('Never', 'leadsforward-core'))); ?>
				</p>
				<div style="display:flex;flex-wrap:wrap;gap:12px;margin:8px 0;">
					<div><strong><?php esc_html_e('Missing fields:', 'leadsforward-core'); ?></strong> <?php echo esc_html((string) ($summary['missing_fields'] ?? 0)); ?></div>
					<div><strong><?php esc_html_e('Pages with issues:', 'leadsforward-core'); ?></strong> <?php echo esc_html((string) ($summary['pages_with_issues'] ?? 0)); ?></div>
					<div><strong><?php esc_html_e('CTA uniqueness:', 'leadsforward-core'); ?></strong> <?php echo esc_html((string) ($summary['cta_unique'] ?? 0)); ?>/<?php echo esc_html((string) ($summary['cta_total'] ?? 0)); ?></div>
					<div><strong><?php esc_html_e('Internal links:', 'leadsforward-core'); ?></strong> <?php echo esc_html((string) ($summary['internal_links_total'] ?? 0)); ?></div>
					<div><strong><?php esc_html_e('Pages with links:', 'leadsforward-core'); ?></strong> <?php echo esc_html((string) ($summary['pages_with_internal_links'] ?? 0)); ?></div>
					<div><strong><?php esc_html_e('Avg SEO quality:', 'leadsforward-core'); ?></strong> <?php echo esc_html((string) ($summary['seo_quality_avg'] ?? 0)); ?>/100</div>
				</div>
				<?php $quality_warnings = is_array($audit_report['quality_warnings'] ?? null) ? $audit_report['quality_warnings'] : []; ?>
				<?php if (!empty($quality_warnings)) : ?>
					<p><strong><?php esc_html_e('Quality warnings from orchestrator:', 'leadsforward-core'); ?></strong></p>
					<ul style="margin: 0 0 12px 18px;">
						<?php foreach ($quality_warnings as $warning) : ?>
							<li><?php echo esc_html((string) $warning); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if (!empty($pages) && is_array($pages)) : ?>
					<ul style="margin: 0 0 12px 18px;">
						<?php foreach ($pages as $page) : ?>
							<?php
							$issues = $page['issues'] ?? [];
							if (empty($issues)) {
								continue;
							}
							$title = $page['title'] ?? '';
							$slug = $page['slug'] ?? '';
							?>
							<li>
								<strong><?php echo esc_html($title !== '' ? $title : $slug); ?></strong>
								<?php echo esc_html(sprintf(__(' — %d missing fields', 'leadsforward-core'), count($issues))); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if (!empty($cta_dupes)) : ?>
					<p><strong><?php esc_html_e('Duplicate CTAs detected:', 'leadsforward-core'); ?></strong></p>
					<ul style="margin: 0 0 12px 18px;">
						<?php foreach ($cta_dupes as $dupe) : ?>
							<li><?php echo esc_html((string) ($dupe['headline'] ?? __('CTA', 'leadsforward-core'))); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php else : ?>
				<p class="description"><?php esc_html_e('No audit results yet. Run the audit to see coverage.', 'leadsforward-core'); ?></p>
			<?php endif; ?>
		</div>
		<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0;">
			<h2 style="margin-top:0;"><?php esc_html_e('Vision Metadata Debug', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Checks whether n8n returned image annotations and how many were actually applied to Media Library metadata.', 'leadsforward-core'); ?></p>
			<?php if ($vision_debug_job_id > 0) : ?>
				<p class="description"><?php echo esc_html(sprintf(__('Latest checked job: #%d', 'leadsforward-core'), $vision_debug_job_id)); ?></p>
				<div style="display:flex;flex-wrap:wrap;gap:12px;margin:8px 0;">
					<div><strong><?php esc_html_e('Annotations received:', 'leadsforward-core'); ?></strong> <?php echo esc_html((string) $vision_received_count); ?></div>
					<div><strong><?php esc_html_e('Annotations applied:', 'leadsforward-core'); ?></strong> <?php echo esc_html((string) $vision_applied_count); ?></div>
				</div>
				<?php if (!empty($vision_warning_items)) : ?>
					<p><strong><?php esc_html_e('Warnings:', 'leadsforward-core'); ?></strong></p>
					<ul style="margin: 0 0 12px 18px;">
						<?php foreach ($vision_warning_items as $warning) : ?>
							<li><?php echo esc_html($warning); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if (!empty($vision_samples)) : ?>
					<p><strong><?php esc_html_e('Sample annotations (first 3):', 'leadsforward-core'); ?></strong></p>
					<pre style="max-height:220px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:12px;"><?php echo esc_html(wp_json_encode($vision_samples, JSON_PRETTY_PRINT)); ?></pre>
				<?php else : ?>
					<p class="description"><?php esc_html_e('No annotation samples found for this job payload.', 'leadsforward-core'); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p class="description"><?php esc_html_e('No AI jobs found yet. Run the manifester once to populate vision debug data.', 'leadsforward-core'); ?></p>
			<?php endif; ?>
		</div>
		<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0; border: 1px solid #f87171;">
			<h2 style="margin-top:0;"><?php esc_html_e('Reset site (dev only)', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Deletes setup-created content. API/Airtable settings and legal pages are preserved.', 'leadsforward-core'); ?></p>
			<?php if (current_user_can('manage_options')) : ?>
				<form method="post">
					<?php wp_nonce_field('lf_dev_reset', 'lf_dev_reset_nonce'); ?>
					<input type="hidden" name="lf_dev_reset" value="1" />
					<p>
						<label for="lf_dev_reset_confirm"><?php esc_html_e('Type RESET to confirm:', 'leadsforward-core'); ?></label><br />
						<input type="text" id="lf_dev_reset_confirm" name="lf_dev_reset_confirm" class="regular-text" />
					</p>
					<p>
						<label>
							<input type="checkbox" name="lf_dev_reset_ack" value="1" required />
							<?php esc_html_e('I understand this will permanently delete site content and cannot be undone.', 'leadsforward-core'); ?>
						</label>
					</p>
					<p><button type="submit" class="button button-secondary"><?php esc_html_e('Reset Site', 'leadsforward-core'); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<div id="lf-ai-manifest-loading" class="lf-ai-loading-overlay" aria-hidden="true">
			<div class="lf-ai-loading-card" role="status" aria-live="polite">
				<div class="lf-ai-loading-title"><?php esc_html_e('Generating site…', 'leadsforward-core'); ?></div>
				<div class="lf-ai-loading-bar"><span></span></div>
				<div class="lf-ai-loading-status"><?php esc_html_e('Uploading manifest…', 'leadsforward-core'); ?></div>
			</div>
		</div>
		<style>
			.lf-ai-loading-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); display: none; align-items: center; justify-content: center; z-index: 9999; }
			.lf-ai-loading-overlay.is-active { display: flex; }
			.lf-ai-loading-card { background: #fff; border-radius: 12px; padding: 24px 28px; width: min(520px, 90vw); box-shadow: 0 18px 60px rgba(15, 23, 42, 0.25); text-align: center; }
			.lf-ai-loading-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 12px; color: #111827; }
			.lf-ai-loading-status { margin-top: 10px; color: #4b5563; font-size: 0.95rem; }
			.lf-ai-loading-bar { position: relative; height: 10px; background: #e5e7eb; border-radius: 999px; overflow: hidden; }
			.lf-ai-loading-bar span { position: absolute; inset: 0; width: 40%; background: linear-gradient(90deg, #3b82f6, #60a5fa, #3b82f6); animation: lf-ai-progress 1.4s ease-in-out infinite; }
			@keyframes lf-ai-progress {
				0% { transform: translateX(-100%); }
				50% { transform: translateX(60%); }
				100% { transform: translateX(200%); }
			}
		</style>
		<script>
			(function() {
				var form = document.getElementById('lf-manifester-logo-form');
				var selectBtn = document.getElementById('lf-manifester-logo-select');
				var clearBtn = document.getElementById('lf-manifester-logo-clear');
				var input = document.getElementById('lf_manifester_logo');
				var preview = document.getElementById('lf-manifester-logo-preview');
				var frame;
				if (selectBtn) {
					selectBtn.addEventListener('click', function (e) {
						e.preventDefault();
						if (frame) { frame.open(); return; }
						frame = wp.media({ title: 'Select Logo', button: { text: 'Use logo' }, multiple: false });
						frame.on('select', function () {
							var attachment = frame.state().get('selection').first().toJSON();
							if (input) input.value = attachment.id;
							if (preview) { preview.src = attachment.url; preview.style.display = 'block'; }
							if (form) { form.submit(); }
						});
						frame.open();
					});
				}
				if (clearBtn) {
					clearBtn.addEventListener('click', function (e) {
						e.preventDefault();
						if (input) input.value = '';
						if (preview) { preview.src = ''; preview.style.display = 'none'; }
						if (form) { form.submit(); }
					});
				}
			})();
		</script>
		<script>
			(function() {
				var form = document.getElementById('lf-ai-manifest-form');
				var overlay = document.getElementById('lf-ai-manifest-loading');
				if (!form || !overlay) {
					return;
				}
				var status = overlay.querySelector('.lf-ai-loading-status');
				var button = form.querySelector('button[type="submit"]');
				var steps = [
					'Uploading manifest…',
					'Validating schema…',
					'Building blueprints…',
					'Generating content…',
					'Applying updates…'
				];
				form.addEventListener('submit', function() {
					overlay.classList.add('is-active');
					overlay.setAttribute('aria-hidden', 'false');
					if (button) {
						button.disabled = true;
					}
					var idx = 0;
					if (status) {
						status.textContent = steps[0];
						window.setInterval(function() {
							idx = (idx + 1) % steps.length;
							status.textContent = steps[idx];
						}, 2200);
					}
				});
			})();
		</script>
	</div>
	<?php
}

function lf_ai_studio_run_generation(): array {
	$enabled = get_option('lf_ai_studio_enabled', '0') === '1';
	$webhook = (string) get_option('lf_ai_studio_webhook', '');
	$secret = (string) get_option('lf_ai_studio_secret', '');
	if (!$enabled) {
		return ['error' => __('Website Manifester is disabled.', 'leadsforward-core')];
	}
	if ($webhook === '' || $secret === '') {
		return ['error' => __('Webhook URL and shared secret are required.', 'leadsforward-core')];
	}
	$request = lf_ai_studio_build_full_site_payload(false);
	if (!is_array($request)) {
		return ['error' => __('Full site payload build failed.', 'leadsforward-core')];
	}
	if (!empty($request['error'])) {
		return ['error' => (string) $request['error']];
	}
	$job_id = lf_ai_studio_create_job($request);
	return lf_ai_studio_send_request($request, $job_id);
}

function lf_ai_studio_run_homepage_generation(): array {
	$enabled = get_option('lf_ai_studio_enabled', '0') === '1';
	$webhook = (string) get_option('lf_ai_studio_webhook', '');
	$secret = (string) get_option('lf_ai_studio_secret', '');
	if (!$enabled) {
		error_log('LF DEBUG: Regenerate Site blocked: AI Studio disabled.');
		return ['error' => __('Website Manifester is disabled.', 'leadsforward-core')];
	}
	if ($webhook === '' || $secret === '') {
		error_log('LF DEBUG: Regenerate Site blocked: missing webhook or secret.');
		return ['error' => __('Webhook URL and shared secret are required.', 'leadsforward-core')];
	}
	$manifest = lf_ai_studio_get_manifest();
	if (empty($manifest)) {
		$keywords = lf_homepage_keywords();
		if (empty($keywords['primary'])) {
			error_log('LF DEBUG: Regenerate Site blocked: missing primary keyword.');
			return ['error' => __('Homepage primary keyword is required.', 'leadsforward-core')];
		}
	}
	$request = lf_ai_studio_build_full_site_payload(false);
	if (!is_array($request)) {
		error_log('LF DEBUG: Regenerate Site blocked: payload build returned non-array.');
		return ['error' => __('Full site payload build failed.', 'leadsforward-core')];
	}
	if (isset($request['error'])) {
		error_log('LF DEBUG: Regenerate Site blocked: ' . (string) $request['error']);
		return ['error' => (string) $request['error']];
	}
	$job_id = lf_ai_studio_create_job($request);
	return lf_ai_studio_send_request($request, $job_id);
}

function lf_ai_studio_send_request(array $request, int $job_id): array {
	$webhook = (string) get_option('lf_ai_studio_webhook', '');
	$secret = (string) get_option('lf_ai_studio_secret', '');
	$callback_url = lf_ai_studio_build_callback_url();
	$request_id = sanitize_text_field((string) ($request['request_id'] ?? ''));
	if ($request_id === '') {
		$request_id = wp_generate_uuid4();
	}
	$request['request_id'] = $request_id;
	$request['job_id'] = $job_id;
	$parent_job_id = (int) get_post_meta($job_id, 'lf_ai_job_parent', true);
	if ($parent_job_id > 0) {
		$request['parent_job_id'] = $parent_job_id;
	}
	$is_repair_phase = !empty($request['repair_only']) || ((int) get_post_meta($job_id, 'lf_ai_job_repair', true) === 1);
	$request['run_phase'] = $is_repair_phase ? 'repair' : 'initial';
	$request['repair_attempt'] = $is_repair_phase ? max(1, (int) ($request['repair_attempt'] ?? 1)) : 0;
	$request['callback_url'] = $callback_url;
	$request['callback_auth_mode'] = function_exists('lf_ai_studio_auth_mode') ? lf_ai_studio_auth_mode() : 'compatibility';
	$request['callback_hmac_tolerance_seconds'] = function_exists('lf_ai_studio_hmac_tolerance_seconds') ? lf_ai_studio_hmac_tolerance_seconds() : 300;
	if (function_exists('lf_image_intelligence_build_media_candidates_for_vision')) {
		$request['media_library_candidates'] = lf_image_intelligence_build_media_candidates_for_vision(250);
	}
	$media_candidate_count = is_array($request['media_library_candidates'] ?? null) ? count($request['media_library_candidates']) : 0;
	$strict_media_annotations = get_option('lf_ai_require_media_annotations', '0') === '1';
	$request['media_annotation_required'] = $strict_media_annotations && $media_candidate_count > 0;
	$request['media_annotation_min_expected'] = ($strict_media_annotations && $media_candidate_count > 0) ? 1 : 0;
	update_post_meta($job_id, 'lf_ai_job_status', 'queued');
	update_post_meta($job_id, 'lf_ai_job_queued_at', time());
	update_post_meta($job_id, 'lf_ai_job_request', $request);
	update_post_meta($job_id, 'lf_ai_job_request_id', $request_id);
	update_post_meta($job_id, 'lf_ai_job_run_phase', $request['run_phase']);
	update_post_meta($job_id, 'lf_ai_job_request_hash', hash('sha256', (string) wp_json_encode($request)));
	lf_ai_autonomy_mark_generation_started($job_id);
	$log_payload = [
		'keys' => array_keys($request),
		'blueprints' => isset($request['blueprints']) && is_array($request['blueprints'])
			? ['count' => count($request['blueprints'])]
			: ['count' => 0],
	];
	if (empty($request['blueprints']) || !is_array($request['blueprints'])) {
		lf_ai_autonomy_mark_generation_failed($job_id, 'missing_blueprints');
		error_log('LF DEBUG: Aborting orchestrator request: missing blueprints');
		return ['success' => false, 'summary' => __('No blueprints to send. Check generation scope and setup data.', 'leadsforward-core')];
	}
	$research = lf_ai_studio_get_research_document();
	$log_payload['research_present'] = !empty($research);
	$log_payload['research_hash'] = !empty($research) ? lf_ai_studio_research_hash($research) : '';
	error_log('LF AI Studio payload keys: ' . wp_json_encode($log_payload));
	$webhook_host = wp_parse_url($webhook, PHP_URL_HOST);
	$webhook_host = is_string($webhook_host) ? $webhook_host : '';
	error_log('LF AI Studio webhook invoked: job=' . $job_id . ($webhook_host ? ' host=' . $webhook_host : ''));
	error_log('LF DEBUG: About to POST full-site payload to orchestrator');
	$response = wp_remote_post($webhook, [
		'method' => 'POST',
		'timeout' => 20,
		'blocking' => true,
		'headers' => [
			'Authorization' => 'Bearer ' . $secret,
			'Content-Type' => 'application/json',
		],
		'body' => wp_json_encode($request),
	]);
	$status_code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
	error_log('LF DEBUG: Webhook call returned status=' . $status_code);
	if (is_wp_error($response)) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', $response->get_error_message());
		lf_ai_autonomy_mark_generation_failed($job_id, 'webhook_request_failed');
		error_log('LF DEBUG: Regenerate Site failed: WP error on webhook call: ' . $response->get_error_message());
		return ['error' => $response->get_error_message(), 'job_id' => $job_id];
	}
	$body = wp_remote_retrieve_body($response);
	$status = (int) wp_remote_retrieve_response_code($response);
	if ($status < 200 || $status >= 300) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', 'http_' . $status);
		update_post_meta($job_id, 'lf_ai_job_response', $body);
		lf_ai_autonomy_mark_generation_failed($job_id, 'webhook_http_' . $status);
		error_log('LF DEBUG: Regenerate Site failed: HTTP ' . $status);
		return ['error' => sprintf(__('Orchestrator returned HTTP %d: %s', 'leadsforward-core'), $status, (string) $body), 'job_id' => $job_id];
	}
	$payload = json_decode($body, true);
	if (is_array($payload) && !empty($payload['request_id'])) {
		update_post_meta($job_id, 'lf_ai_job_request_id', sanitize_text_field((string) $payload['request_id']));
		update_post_meta($job_id, 'lf_ai_job_response', $payload);
	} elseif ($body !== '') {
		update_post_meta($job_id, 'lf_ai_job_response', $body);
	}
	update_post_meta($job_id, 'lf_ai_job_status', 'running');
	update_post_meta($job_id, 'lf_ai_job_running_at', time());
	return ['job_id' => $job_id];
}

function lf_ai_studio_build_callback_url(): string {
	$override = (string) get_option('lf_ai_studio_callback_url', '');
	$callback_url = '';
	if ($override !== '') {
		$callback_url = trim($override);
		if (strpos($callback_url, '/wp-json/') === false) {
			$callback_url = rtrim($callback_url, '/') . '/wp-json/leadsforward/v1/orchestrator';
		}
	} else {
		$callback_url = rest_url('leadsforward/v1/orchestrator');
	}
	return $callback_url;
}

function lf_ai_studio_create_job(array $request): int {
	$user = get_current_user_id();
	$job_id = wp_insert_post([
		'post_type' => LF_AI_STUDIO_JOB_CPT,
		'post_status' => 'publish',
		'post_title' => 'AI Generation Job',
	]);
	if ($job_id) {
		update_post_meta($job_id, 'lf_ai_job_status', 'queued');
		update_post_meta($job_id, 'lf_ai_job_user', $user);
		update_post_meta($job_id, 'lf_ai_job_request', $request);
	}
	return (int) $job_id;
}

function lf_ai_studio_keywords(): array {
	$raw = (string) get_option('lf_ai_studio_keywords', '');
	$lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
	return array_values(array_map('sanitize_text_field', $lines));
}

function lf_ai_studio_seed_dummy_posts(string $business_name = ''): void {
	$existing = get_posts([
		'post_type'      => 'post',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	]);
	$label = $business_name !== '' ? $business_name : __('your home', 'leadsforward-core');
	if (empty($existing)) {
		$posts = [
		[
			'post_title' => sprintf(__('Planning your next project with %s in mind', 'leadsforward-core'), $label),
			'post_content' => __('This is a placeholder post created during site generation. Replace it with a helpful guide or FAQ that answers your customers’ most common questions.', 'leadsforward-core'),
		],
		[
			'post_title' => __('What to expect during a typical service visit', 'leadsforward-core'),
			'post_content' => __('Use this post to explain your process, timeline, and how homeowners should prepare. Add real examples and photos once available.', 'leadsforward-core'),
		],
		[
			'post_title' => __('Common pitfalls homeowners should avoid', 'leadsforward-core'),
			'post_content' => __('Outline mistakes you see often and how your team prevents them. This builds trust and clarifies why professional help matters.', 'leadsforward-core'),
		],
		];
		foreach ($posts as $post) {
			wp_insert_post([
				'post_type' => 'post',
				'post_status' => 'publish',
				'post_title' => $post['post_title'],
				'post_content' => $post['post_content'],
			]);
		}
	}
	lf_ai_studio_seed_sample_projects();
}

function lf_ai_studio_seed_sample_projects(): void {
	$existing = get_posts([
		'post_type'      => 'lf_project',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	]);
	if (!empty($existing)) {
		return;
	}
	$manifest = lf_ai_studio_get_manifest();
	$business = is_array($manifest['business'] ?? null) ? $manifest['business'] : [];
	$address = is_array($business['address'] ?? null) ? $business['address'] : [];
	$city = sanitize_text_field((string) ($address['city'] ?? ($business['primary_city'] ?? '')));
	$state = sanitize_text_field((string) ($address['state'] ?? ''));
	$location = trim($city . ($state !== '' ? ', ' . $state : ''));
	$services = is_array($manifest['services'] ?? null) ? $manifest['services'] : [];
	$service_titles = [];
	foreach ($services as $item) {
		$normalized = lf_ai_studio_normalize_service_item($item);
		if ($normalized['title'] !== '') {
			$service_titles[] = $normalized['title'];
		}
	}
	if (empty($service_titles)) {
		$niche = (string) ($business['niche'] ?? __('Service', 'leadsforward-core'));
		$service_titles = [$niche, $niche, $niche];
	}
	$service_titles = array_slice($service_titles, 0, 3);
	$year = (string) date('Y');
	foreach ($service_titles as $service) {
		$title = $location !== '' ? sprintf(__('%s Project — %s', 'leadsforward-core'), $service, $location) : sprintf(__('%s Project', 'leadsforward-core'), $service);
		$project_copy = $location !== ''
			? sprintf(__('%1$s project completed in %2$s with a clear scope, reliable timeline, and durable finish built for long-term performance.', 'leadsforward-core'), $service, $location)
			: sprintf(__('%1$s project completed with clear planning, reliable execution, and a durable finish designed for long-term performance.', 'leadsforward-core'), $service);
		$post_id = wp_insert_post([
			'post_type' => 'lf_project',
			'post_status' => 'publish',
			'post_title' => $title,
			'post_content' => $project_copy,
		]);
		if (!$post_id) {
			continue;
		}
		if (function_exists('lf_match_images_for_context')) {
			$image_context = [
				'page_type' => 'overview',
				'niche' => (string) ($business['niche'] ?? ''),
				'city' => $city,
				'primary_keyword' => $service,
				'secondary_keywords' => is_array($manifest['homepage']['secondary_keywords'] ?? null) ? $manifest['homepage']['secondary_keywords'] : [],
				'variation_seed' => (string) get_option('lf_homepage_variation_seed', ''),
				'service_name' => $service,
			];
			$matches = lf_match_images_for_context($image_context);
			$before_id = (int) ($matches['content_image_a'] ?? 0);
			$after_id = (int) ($matches['image_content_b'] ?? 0);
			$featured_id = (int) ($matches['featured'] ?? $before_id);
			if ($before_id > 0) {
				update_post_meta($post_id, 'lf_project_before_image', $before_id);
			}
			if ($after_id > 0) {
				update_post_meta($post_id, 'lf_project_after_image', $after_id);
			}
			if ($featured_id > 0 && function_exists('set_post_thumbnail')) {
				set_post_thumbnail($post_id, $featured_id);
			}
		}
		if ($city !== '') {
			update_post_meta($post_id, 'lf_project_city', $city);
		}
		if ($state !== '') {
			update_post_meta($post_id, 'lf_project_state', $state);
		}
		update_post_meta($post_id, 'lf_project_year', $year);
		$taxonomy = 'lf_project_type';
		if (taxonomy_exists($taxonomy)) {
			$term_name = $service;
			$term = term_exists($term_name, $taxonomy);
			if (!$term) {
				$term = wp_insert_term($term_name, $taxonomy);
			}
			if (!is_wp_error($term)) {
				$term_id = is_array($term) ? (int) ($term['term_id'] ?? 0) : (int) $term;
				if ($term_id) {
					wp_set_post_terms($post_id, [$term_id], $taxonomy, false);
				}
			}
		}
	}
}

function lf_ai_studio_blog_post_topics(array $manifest, array $homepage_payload): array {
	$topics = [];
	$primary = (string) ($homepage_payload['keywords']['primary'] ?? '');
	$secondary = $homepage_payload['keywords']['secondary'] ?? [];
	if (!is_array($secondary)) {
		$secondary = [];
	}
	$business_name = (string) ($homepage_payload['business_name'] ?? '');
	$niche = (string) ($homepage_payload['niche'] ?? '');
	$city = (string) ($homepage_payload['city_region'] ?? '');
	if (!empty($manifest)) {
		$primary = (string) ($manifest['homepage']['primary_keyword'] ?? $primary);
		$secondary = is_array($manifest['homepage']['secondary_keywords'] ?? null) ? $manifest['homepage']['secondary_keywords'] : $secondary;
		$business_name = (string) ($manifest['business']['name'] ?? $business_name);
		$niche = (string) ($manifest['business']['niche'] ?? $niche);
		$city = (string) ($manifest['business']['primary_city'] ?? ($manifest['business']['address']['city'] ?? $city));
	}
	$primary = sanitize_text_field($primary);
	$niche = sanitize_text_field($niche);
	$city = sanitize_text_field($city);
	$secondary = array_values(array_unique(array_filter(array_map('sanitize_text_field', $secondary))));
	$secondary = array_values(array_filter($secondary, static function (string $keyword) use ($primary): bool {
		return $keyword !== '' && strcasecmp($keyword, $primary) !== 0;
	}));

	$focus = $primary !== '' ? $primary : ($niche !== '' ? $niche : __('home services', 'leadsforward-core'));
	$location = $city !== '' ? $city : __('your area', 'leadsforward-core');
	$secondary_or_focus = static function (int $index) use ($secondary, $focus): string {
		if (!empty($secondary)) {
			return (string) ($secondary[$index % count($secondary)] ?? $focus);
		}
		return $focus;
	};

	$topics[] = [
		'title' => sprintf(__('Complete pillar guide to %1$s in %2$s', 'leadsforward-core'), $focus, $location),
		'keyword' => $focus,
		'format' => 'pillar',
	];
	$topics[] = [
		'title' => sprintf(__('How to plan a %s project without costly surprises', 'leadsforward-core'), $secondary_or_focus(0)),
		'keyword' => $secondary_or_focus(0),
		'format' => 'how_to',
	];
	$topics[] = [
		'title' => sprintf(__('How much does %1$s cost in %2$s', 'leadsforward-core'), $secondary_or_focus(1), $location),
		'keyword' => $secondary_or_focus(1),
		'format' => 'cost',
	];
	$topics[] = [
		'title' => sprintf(__('%1$s versus alternatives: what homeowners should choose', 'leadsforward-core'), $secondary_or_focus(2)),
		'keyword' => $secondary_or_focus(2),
		'format' => 'comparison',
	];
	$topics[] = [
		'title' => sprintf(__('Homeowner checklist before hiring a %s company', 'leadsforward-core'), $niche !== '' ? $niche : $focus),
		'keyword' => $secondary_or_focus(3),
		'format' => 'checklist',
	];
	$topics[] = [
		'title' => sprintf(__('Seasonal timing for %1$s in %2$s', 'leadsforward-core'), $secondary_or_focus(4), $location),
		'keyword' => $secondary_or_focus(4),
		'format' => 'local_guide',
	];
	$topics[] = [
		'title' => sprintf(__('Top homeowner questions about %s answered', 'leadsforward-core'), $secondary_or_focus(5)),
		'keyword' => $secondary_or_focus(5),
		'format' => 'faq_roundup',
	];

	return array_slice($topics, 0, 5);
}

function lf_ai_studio_ensure_blog_posts(array $topics): array {
	$now_ts = current_time('timestamp');
	$publish_now_count = 3;
	$total_posts = 5;
	$topics = array_slice($topics, 0, $total_posts);
	$ai_ids = get_posts([
		'post_type'      => 'post',
		'post_status'    => ['publish', 'future', 'draft', 'pending', 'private'],
		'posts_per_page' => 200,
		'meta_key'       => 'lf_ai_generated',
		'meta_value'     => '1',
		'fields'         => 'all',
	]);
	$slot_map = [];
	foreach ($ai_ids as $ai_post) {
		if (!$ai_post instanceof \WP_Post) {
			continue;
		}
		$slot = (int) get_post_meta((int) $ai_post->ID, 'lf_ai_post_slot', true);
		if ($slot >= 1 && $slot <= $total_posts) {
			$slot_map[$slot] = $ai_post;
		}
	}

	for ($slot = 1; $slot <= $total_posts; $slot++) {
		$topic = $topics[$slot - 1] ?? null;
		if (!is_array($topic)) {
			continue;
		}
		$title = (string) ($topic['title'] ?? '');
		$keyword = (string) ($topic['keyword'] ?? '');
		$format = sanitize_key((string) ($topic['format'] ?? 'standard'));
		if ($title === '') {
			continue;
		}
		$post = $slot_map[$slot] ?? null;
		if (!$post instanceof \WP_Post) {
			$post_id = wp_insert_post([
				'post_type' => 'post',
				'post_status' => 'draft',
				'post_title' => $title,
				'post_content' => '',
				'post_excerpt' => '',
				'comment_status' => 'open',
				'ping_status' => 'closed',
			]);
			if (!$post_id || is_wp_error($post_id)) {
				continue;
			}
			$post = get_post((int) $post_id);
			if (!$post instanceof \WP_Post) {
				continue;
			}
			update_post_meta((int) $post->ID, 'lf_ai_generated', 1);
			update_post_meta((int) $post->ID, 'lf_ai_generated_filled', 0);
		}

		update_post_meta((int) $post->ID, 'lf_ai_post_slot', $slot);
		if ($keyword !== '') {
			update_post_meta((int) $post->ID, 'lf_ai_post_keyword', $keyword);
		}
		update_post_meta((int) $post->ID, 'lf_ai_post_format', $format);
		update_post_meta((int) $post->ID, 'lf_ai_post_schedule_managed', 1);

		$post_update = [
			'ID' => (int) $post->ID,
			'comment_status' => 'open',
		];
		if ($slot <= $publish_now_count) {
			$post_update['post_status'] = 'publish';
		} else {
			$weeks_out = $slot - $publish_now_count;
			$scheduled_ts = strtotime('+' . $weeks_out . ' week', $now_ts);
			$local_date = wp_date('Y-m-d 09:00:00', $scheduled_ts, wp_timezone());
			$post_update['post_status'] = 'future';
			$post_update['post_date'] = $local_date;
			$post_update['post_date_gmt'] = get_gmt_from_date($local_date);
		}
		wp_update_post($post_update);
	}

	$ai_posts = get_posts([
		'post_type' => 'post',
		'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
		'posts_per_page' => $total_posts,
		'meta_key' => 'lf_ai_generated',
		'meta_value' => '1',
		'meta_query' => [
			[
				'key' => 'lf_ai_post_slot',
				'value' => [1, $total_posts],
				'compare' => 'BETWEEN',
				'type' => 'NUMERIC',
			],
		],
		'orderby' => 'meta_value_num',
		'order' => 'ASC',
	]);
	$out = [];
	foreach ($ai_posts as $post) {
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$keyword = (string) get_post_meta($post->ID, 'lf_ai_post_keyword', true);
		$format = (string) get_post_meta($post->ID, 'lf_ai_post_format', true);
		$out[] = ['post' => $post, 'keyword' => $keyword, 'format' => $format];
	}
	return $out;
}

function lf_ai_studio_backfill_post_title_excerpt(int $post_id): void {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || $post->post_type !== 'post') {
		return;
	}
	$is_ai = (string) get_post_meta($post_id, 'lf_ai_generated', true) === '1';
	if (!$is_ai) {
		return;
	}
	$filled = (string) get_post_meta($post_id, 'lf_ai_generated_filled', true) === '1';
	if ($filled) {
		return;
	}
	$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
	if ($context !== 'post' || !function_exists('lf_pb_get_post_config')) {
		return;
	}
	$config = lf_pb_get_post_config($post_id, $context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$title = '';
	$excerpt = '';
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		if ($title === '') {
			$title = (string) ($settings['hero_headline'] ?? $settings['section_heading'] ?? '');
		}
		if ($excerpt === '') {
			$excerpt = (string) ($settings['hero_subheadline'] ?? $settings['hero_supporting_text'] ?? $settings['section_intro'] ?? '');
		}
		if ($title !== '' && $excerpt !== '') {
			break;
		}
	}
	$title = trim((string) $title);
	$excerpt = trim((string) $excerpt);
	if ($title !== '' && !preg_match('/[A-Z]/', $title)) {
		$title = ucwords($title);
	}
	$update = ['ID' => $post_id];
	if ($title !== '') {
		$update['post_title'] = sanitize_text_field($title);
	}
	if ($excerpt !== '') {
		$update['post_excerpt'] = sanitize_textarea_field(wp_trim_words($excerpt, 28));
	}
	if (count($update) > 1) {
		wp_update_post($update);
		update_post_meta($post_id, 'lf_ai_generated_filled', 1);
	}
}

function lf_ai_studio_is_generic_copy(string $value): bool {
	$value = trim(wp_strip_all_tags($value));
	if ($value === '') {
		return true;
	}
	$needle = strtolower($value);
	if (lf_ai_studio_contains_json_placeholder($needle)) {
		return true;
	}
	$patterns = [
		'short overview of',
		'what to expect',
		'sample project created during site generation',
		'replace with real before/after details and photos',
		'lorem ipsum',
		'placeholder',
	];
	foreach ($patterns as $pattern) {
		if (strpos($needle, $pattern) !== false) {
			return true;
		}
	}
	$token_markers = ['primary_keyword', 'business_name', 'city_region'];
	foreach ($token_markers as $tok) {
		if (strpos($needle, $tok) !== false) {
			return true;
		}
	}
	return false;
}

function lf_ai_studio_fallback_copy_for_field(string $field_key, \WP_Post $post, string $field_type = 'text'): string {
	$business = trim((string) get_option('lf_business_name', get_bloginfo('name')));
	$city = trim((string) get_option('lf_city_region', get_option('lf_homepage_city', '')));
	if ($post->post_type === 'lf_service_area') {
		$city = trim((string) $post->post_title);
	}
	$keyword = trim((string) get_option('lf_primary_keyword', ''));
	if ($keyword === '') {
		$keyword = trim((string) get_post_meta($post->ID, 'lf_ai_post_keyword', true));
	}
	$title = trim((string) $post->post_title);
	if ($title === '') {
		$title = __('Our team', 'leadsforward-core');
	}
	$focus = $keyword !== '' ? $keyword : $title;
	$location_suffix = $city !== '' ? sprintf(__(' in %s', 'leadsforward-core'), $city) : '';
	if ($field_type === 'list') {
		$items = [
			sprintf(__('%s completed with careful planning and durable workmanship.', 'leadsforward-core'), $focus),
			sprintf(__('Delivered by %s with clear communication and reliable timelines.', 'leadsforward-core'), $business !== '' ? $business : get_bloginfo('name')),
		];
		if ($city !== '') {
			$items[] = sprintf(__('Tailored for homeowners in %s with practical next-step guidance.', 'leadsforward-core'), $city);
		}
		return implode("\n", $items);
	}

	if (strpos($field_key, 'heading') !== false || strpos($field_key, 'headline') !== false) {
		return sanitize_text_field(sprintf(__('%1$s%2$s', 'leadsforward-core'), $focus, $location_suffix));
	}
	if (strpos($field_key, 'intro') !== false || strpos($field_key, 'subheadline') !== false) {
		if ($city !== '') {
			return sanitize_textarea_field(sprintf(__('%1$s delivers tailored solutions in %2$s with clear communication, clean execution, and long-lasting results.', 'leadsforward-core'), $business !== '' ? $business : get_bloginfo('name'), $city));
		}
		return sanitize_textarea_field(sprintf(__('%1$s delivers tailored solutions with clear communication, clean execution, and long-lasting results.', 'leadsforward-core'), $business !== '' ? $business : get_bloginfo('name')));
	}
	if (strpos($field_key, 'body') !== false || strpos($field_key, 'description') !== false || strpos($field_key, 'content') !== false) {
		if ($city !== '') {
			return wp_kses_post(sprintf(
				/* translators: 1: focus keyword/title, 2: city, 3: business name */
				__('%3$s plans every %1$s project around your property, goals, and budget in %2$s. We focus on durable materials, precise workmanship, and transparent timelines so homeowners get results that look great now and hold up over time.', 'leadsforward-core'),
				$focus,
				$city,
				$business !== '' ? $business : get_bloginfo('name')
			));
		}
		return wp_kses_post(sprintf(
			/* translators: 1: focus keyword/title, 2: business name */
			__('%2$s plans every %1$s project around your property, goals, and budget. We focus on durable materials, precise workmanship, and transparent timelines so homeowners get results that look great now and hold up over time.', 'leadsforward-core'),
			$focus,
			$business !== '' ? $business : get_bloginfo('name')
		));
	}
	return '';
}

function lf_ai_studio_location_label_for_post(\WP_Post $post): string {
	$city = trim((string) get_option('lf_city_region', get_option('lf_homepage_city', '')));
	if ($post->post_type === 'lf_service_area') {
		$city = trim((string) $post->post_title);
	}
	return $city;
}

function lf_ai_studio_replace_generic_location_phrases(array $settings, \WP_Post $post): array {
	$location = lf_ai_studio_location_label_for_post($post);
	$location_norm = strtolower($location);
	foreach ($settings as $key => $value) {
		if (!is_string($value) || $value === '') {
			continue;
		}
		$plain = strtolower(wp_strip_all_tags($value));
		if (strpos($plain, 'in your area') === false) {
			continue;
		}
		$replacement = '';
		if ($location !== '' && ($location_norm === '' || strpos($plain, $location_norm) === false)) {
			$replacement = 'in ' . $location;
		}
		$updated = preg_replace('/\bin your area\b/i', $replacement, $value);
		if ($replacement === '') {
			$updated = preg_replace('/\s+/', ' ', (string) $updated);
		}
		$settings[$key] = trim((string) $updated);
	}
	return $settings;
}

function lf_ai_studio_heading_key_for_section_type(string $type): string {
	if ($type === 'hero') {
		return 'hero_headline';
	}
	if ($type === 'trust_bar') {
		return 'trust_heading';
	}
	if ($type === 'cta') {
		return 'cta_headline';
	}
	return 'section_heading';
}

function lf_ai_studio_pick_variant_index(string $seed, int $count): int {
	if ($count <= 0) {
		return 0;
	}
	$hash = crc32($seed);
	if ($hash < 0) {
		$hash = $hash * -1;
	}
	return (int) ($hash % $count);
}

function lf_ai_studio_service_label_for_post(\WP_Post $post): string {
	$title = trim((string) ($post->post_title ?? ''));
	if ($title !== '') {
		return $title;
	}
	$niche_slug = (string) get_option('lf_homepage_niche_slug', 'general');
	$niche = function_exists('lf_get_niche') ? lf_get_niche($niche_slug) : null;
	$niche_name = is_array($niche) ? (string) ($niche['name'] ?? '') : '';
	if ($niche_name !== '') {
		return $niche_name;
	}
	return __('local services', 'leadsforward-core');
}

function lf_ai_studio_is_generic_heading_value(string $value, \WP_Post $post): bool {
	$plain = strtolower(preg_replace('/\s+/', ' ', trim($value)));
	if ($plain === '') {
		return true;
	}
	if (strpos($plain, 'in your area') !== false) {
		return true;
	}
	$service = strtolower(trim((string) ($post->post_title ?? '')));
	$city = strtolower(trim(lf_ai_studio_location_label_for_post($post)));
	if ($service !== '' && $city !== '' && $plain === strtolower($service . ' in ' . $city)) {
		return true;
	}
	if ($service !== '' && $plain === $service) {
		return true;
	}
	return false;
}

function lf_ai_studio_build_section_heading_candidate(string $type, array $section, \WP_Post $post, array $registry): string {
	$service = lf_ai_studio_service_label_for_post($post);
	$business = trim((string) get_option('lf_business_name', get_bloginfo('name')));
	$city = lf_ai_studio_location_label_for_post($post);
	$intent = trim((string) ($section['settings']['section_intent'] ?? ''));
	$label = ($type !== '' && isset($registry[$type]['label'])) ? (string) $registry[$type]['label'] : '';
	$variant = lf_ai_studio_pick_variant_index($post->ID . '|' . $type, 3);

	if ($intent !== '') {
		$candidate = $intent;
		if ($service !== '' && stripos($candidate, $service) === false && $type !== 'hero') {
			$candidate = $candidate . ' for ' . $service;
		}
		return $candidate;
	}

	switch ($type) {
		case 'benefits':
			$templates = [
				__('Why homeowners choose our %s', 'leadsforward-core'),
				__('Benefits of our %s', 'leadsforward-core'),
				__('The %s advantage', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $service !== '' ? $service : __('services', 'leadsforward-core'));
		case 'service_details':
			$templates = [
				__('What’s included with %s', 'leadsforward-core'),
				__('Scope and details of %s', 'leadsforward-core'),
				__('What to expect from our %s', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $service !== '' ? $service : __('this service', 'leadsforward-core'));
		case 'process':
			$templates = [
				__('Our %s process', 'leadsforward-core'),
				__('How %s projects work', 'leadsforward-core'),
				__('Step-by-step %s delivery', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $service !== '' ? $service : __('projects', 'leadsforward-core'));
		case 'faq_accordion':
			$templates = [
				__('Questions about %s', 'leadsforward-core'),
				__('%s FAQs', 'leadsforward-core'),
				__('Answers for %s projects', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $service !== '' ? $service : __('our services', 'leadsforward-core'));
		case 'trust_bar':
			$templates = [
				__('Trusted by homeowners in %s', 'leadsforward-core'),
				__('Local teams trusted across %s', 'leadsforward-core'),
				__('Trusted local service in %s', 'leadsforward-core'),
			];
			if ($city !== '') {
				return sprintf($templates[$variant], $city);
			}
			return __('Trusted by local homeowners', 'leadsforward-core');
		case 'cta':
			$templates = [
				__('Ready for %s?', 'leadsforward-core'),
				__('Start your %s project', 'leadsforward-core'),
				__('Get %s scheduled', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $service !== '' ? $service : __('a project', 'leadsforward-core'));
		case 'map_nap':
			$templates = [
				__('Serving %s', 'leadsforward-core'),
				__('Service area & contact', 'leadsforward-core'),
				__('Where we work', 'leadsforward-core'),
			];
			if ($city !== '') {
				return sprintf($templates[$variant], $city);
			}
			return $templates[$variant];
		case 'related_links':
			$templates = [
				__('Explore related services', 'leadsforward-core'),
				__('Related services to consider', 'leadsforward-core'),
				__('Similar services we offer', 'leadsforward-core'),
			];
			return $templates[$variant];
		case 'services_offered_here':
			$templates = [
				__('Services available in %s', 'leadsforward-core'),
				__('What we do in %s', 'leadsforward-core'),
				__('Local services in %s', 'leadsforward-core'),
			];
			if ($city !== '') {
				return sprintf($templates[$variant], $city);
			}
			return __('Services available here', 'leadsforward-core');
		case 'nearby_areas':
			$templates = [
				__('Nearby areas we serve', 'leadsforward-core'),
				__('Cities near %s', 'leadsforward-core'),
				__('Also serving nearby neighborhoods', 'leadsforward-core'),
			];
			if ($city !== '') {
				return sprintf($templates[$variant], $city);
			}
			return $templates[$variant];
		case 'content_image':
		case 'image_content':
			$templates = [
				__('What sets our %s apart', 'leadsforward-core'),
				__('How we deliver %s', 'leadsforward-core'),
				__('The standards behind our %s', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $service !== '' ? $service : __('our work', 'leadsforward-core'));
		case 'hero':
			$templates = [
				__('%1$s built for %2$s homes', 'leadsforward-core'),
				__('Trusted %1$s specialists in %2$s', 'leadsforward-core'),
				__('High quality %1$s in %2$s', 'leadsforward-core'),
			];
			if ($city !== '') {
				return sprintf($templates[$variant], $service !== '' ? $service : __('Local services', 'leadsforward-core'), $city);
			}
			return sprintf(__('Trusted %s specialists', 'leadsforward-core'), $service !== '' ? $service : __('Local services', 'leadsforward-core'));
		default:
			break;
	}

	if ($label !== '') {
		return $label;
	}
	if ($service !== '') {
		return $service;
	}
	if ($business !== '') {
		return $business;
	}
	return '';
}

function lf_ai_studio_enforce_unique_headings_for_post_sections(array $sections, array $registry, \WP_Post $post): array {
	$counts = [];
	$hero_norm = '';
	foreach ($sections as $section) {
		if (!is_array($section)) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		$key = lf_ai_studio_heading_key_for_section_type($type);
		$value = is_array($section['settings'] ?? null) ? (string) ($section['settings'][$key] ?? '') : '';
		$norm = strtolower(preg_replace('/\s+/', ' ', trim($value)));
		if ($norm === '') {
			continue;
		}
		if ($type === 'hero') {
			$hero_norm = $norm;
		}
		$counts[$norm] = ($counts[$norm] ?? 0) + 1;
	}

	foreach ($sections as $id => $section) {
		if (!is_array($section)) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		$key = lf_ai_studio_heading_key_for_section_type($type);
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$current = is_string($settings[$key] ?? null) ? trim((string) $settings[$key]) : '';
		$norm = strtolower(preg_replace('/\s+/', ' ', $current));
		$needs = ($current === '')
			|| lf_ai_studio_is_generic_heading_value($current, $post)
			|| ($norm !== '' && ($counts[$norm] ?? 0) > 1)
			|| ($hero_norm !== '' && $norm === $hero_norm && $type !== 'hero');
		if (!$needs) {
			continue;
		}
		$candidate = lf_ai_studio_build_section_heading_candidate($type, $section, $post, $registry);
		if ($candidate === '') {
			continue;
		}
		$final = $candidate;
		$final_norm = strtolower(preg_replace('/\s+/', ' ', trim($final)));
		if ($final_norm !== '' && isset($counts[$final_norm])) {
			$label = ($type !== '' && isset($registry[$type]['label'])) ? (string) $registry[$type]['label'] : '';
			if ($label !== '' && stripos($final, $label) === false) {
				$final = trim($final . ' — ' . $label);
				$final_norm = strtolower(preg_replace('/\s+/', ' ', trim($final)));
			}
		}
		$settings[$key] = $final;
		$sections[$id]['settings'] = $settings;
		$counts[$final_norm] = ($counts[$final_norm] ?? 0) + 1;
	}

	return $sections;
}

function lf_ai_studio_build_section_intro_candidate(string $type, \WP_Post $post): string {
	$service = lf_ai_studio_service_label_for_post($post);
	$business = trim((string) get_option('lf_business_name', get_bloginfo('name')));
	$city = lf_ai_studio_location_label_for_post($post);
	$variant = lf_ai_studio_pick_variant_index($post->ID . '|' . $type . '|intro', 3);
	$location = $city !== '' ? ' in ' . $city : '';

	switch ($type) {
		case 'hero':
			$templates = [
				__('%1$s delivers %2$s%3$s with clear communication and reliable timelines.', 'leadsforward-core'),
				__('We help homeowners plan %2$s%3$s with transparent pricing and clean execution.', 'leadsforward-core'),
				__('Local team focused on %2$s%3$s with dependable results and clear next steps.', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $business !== '' ? $business : get_bloginfo('name'), $service, $location);
		case 'benefits':
			$templates = [
				__('A few reasons homeowners choose our %1$s%2$s.', 'leadsforward-core'),
				__('What sets our %1$s apart for local homeowners%2$s.', 'leadsforward-core'),
				__('Built for quality, communication, and long-term performance in every %1$s%2$s.', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $service, $location);
		case 'service_details':
			return sprintf(__('We plan every %1$s project around your goals and property%2$s.', 'leadsforward-core'), $service, $location);
		case 'process':
			return sprintf(__('A clear, step-by-step process keeps your %1$s project on track%2$s.', 'leadsforward-core'), $service, $location);
		case 'faq_accordion':
			return sprintf(__('Common questions about %1$s%2$s.', 'leadsforward-core'), $service, $location);
		case 'trust_bar':
			if ($city !== '') {
				return sprintf(__('Trusted by homeowners across %s.', 'leadsforward-core'), $city);
			}
			return __('Trusted by local homeowners.', 'leadsforward-core');
		case 'map_nap':
			if ($city !== '') {
				return sprintf(__('Serving %s and nearby neighborhoods.', 'leadsforward-core'), $city);
			}
			return __('Serving nearby neighborhoods with responsive local service.', 'leadsforward-core');
		case 'related_links':
			return sprintf(__('Explore related services that complement %s.', 'leadsforward-core'), $service);
		case 'services_offered_here':
			if ($city !== '') {
				return sprintf(__('See the services available in %s.', 'leadsforward-core'), $city);
			}
			return __('See the services available in this area.', 'leadsforward-core');
		case 'nearby_areas':
			if ($city !== '') {
				return sprintf(__('We also work in nearby neighborhoods around %s.', 'leadsforward-core'), $city);
			}
			return __('We also work in nearby neighborhoods.', 'leadsforward-core');
		case 'content_image':
		case 'image_content':
			return sprintf(__('We combine planning, craftsmanship, and clear communication to deliver %1$s%2$s.', 'leadsforward-core'), $service, $location);
		default:
			break;
	}
	return '';
}

function lf_ai_studio_build_cta_copy_candidate(\WP_Post $post): array {
	$service = lf_ai_studio_service_label_for_post($post);
	$city = lf_ai_studio_location_label_for_post($post);
	$variant = lf_ai_studio_pick_variant_index($post->ID . '|cta', 3);
	$location = $city !== '' ? ' in ' . $city : '';

	$headlines = [
		sprintf(__('Get a fast quote for %s', 'leadsforward-core'), $service),
		sprintf(__('Plan your %s project', 'leadsforward-core'), $service),
		sprintf(__('Schedule your %s estimate', 'leadsforward-core'), $service),
	];
	$subheadlines = [
		__('Clear scope, timing, and pricing from a local team.', 'leadsforward-core'),
		__('Talk with our team about your goals and next steps.', 'leadsforward-core'),
		__('We make it easy to get started and stay informed.', 'leadsforward-core'),
	];
	$secondary = [
		sprintf(__('Quick answers for %s%s.', 'leadsforward-core'), $service, $location),
		sprintf(__('Get a clear plan for %s.', 'leadsforward-core'), $service),
		sprintf(__('Start with a simple walkthrough for %s.', 'leadsforward-core'), $service),
	];

	return [
		'cta_headline' => $headlines[$variant] ?? $headlines[0],
		'cta_subheadline' => $subheadlines[$variant] ?? $subheadlines[0],
		'cta_subheadline_secondary' => $secondary[$variant] ?? $secondary[0],
	];
}

function lf_ai_studio_enforce_unique_text_fields_for_post_sections(array $sections, array $registry, \WP_Post $post): array {
	$seen = [];
	foreach ($sections as $id => $section) {
		if (!is_array($section)) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$intro_keys = [];
		if (isset($settings['section_intro'])) {
			$intro_keys[] = 'section_intro';
		}
		if ($type === 'hero') {
			foreach (['hero_subheadline', 'hero_supporting_text'] as $hero_key) {
				if (isset($settings[$hero_key])) {
					$intro_keys[] = $hero_key;
				}
			}
		}
		foreach ($intro_keys as $key) {
			$value = is_string($settings[$key] ?? null) ? trim((string) $settings[$key]) : '';
			$norm = strtolower(preg_replace('/\s+/', ' ', wp_strip_all_tags($value)));
			$needs = ($value === '') || ($norm !== '' && isset($seen[$norm])) || lf_ai_studio_is_generic_copy($value);
			if ($needs) {
				$candidate = lf_ai_studio_build_section_intro_candidate($type, $post);
				if ($candidate !== '') {
					$settings[$key] = $candidate;
					$norm = strtolower(preg_replace('/\s+/', ' ', wp_strip_all_tags($candidate)));
				}
			}
			if ($norm !== '') {
				$seen[$norm] = true;
			}
		}

		if ($type === 'cta') {
			$cta = lf_ai_studio_build_cta_copy_candidate($post);
			foreach ($cta as $cta_key => $cta_value) {
				$current = is_string($settings[$cta_key] ?? null) ? trim((string) $settings[$cta_key]) : '';
				$norm = strtolower(preg_replace('/\s+/', ' ', wp_strip_all_tags($current)));
				$needs = ($current === '') || lf_ai_studio_is_generic_copy($current) || isset($seen[$norm]);
				if ($needs) {
					$settings[$cta_key] = $cta_value;
					$norm = strtolower(preg_replace('/\s+/', ' ', wp_strip_all_tags($cta_value)));
				}
				if ($norm !== '') {
					$seen[$norm] = true;
				}
			}
		}

		$sections[$id]['settings'] = $settings;
	}

	return $sections;
}

function lf_ai_studio_normalize_for_uniqueness(string $value): string {
	$plain = wp_strip_all_tags($value);
	$plain = strtolower(preg_replace('/\s+/', ' ', trim($plain)));
	return $plain;
}

function lf_ai_studio_should_enforce_uniqueness_on_field(string $field_key, string $field_type): bool {
	if ($field_key === 'faq_selected_ids') {
		return false;
	}
	if (in_array($field_type, ['text', 'textarea', 'richtext', 'wysiwyg', 'list'], true)) {
		$deny = [
			'url', 'link', 'target', 'slug', 'id', 'image', 'icon', 'color', 'background',
			'layout', 'variant', 'toggle', 'enabled', 'size', 'position', 'align', 'style',
			'map', 'address', 'phone', 'email', 'city', 'state', 'zip', 'latitude', 'longitude',
			'hours', 'schema', 'shortcode',
		];
		foreach ($deny as $needle) {
			if (strpos($field_key, $needle) !== false) {
				return $field_key === 'image_alt';
			}
		}
		return true;
	}
	return false;
}

function lf_ai_studio_build_section_body_candidate(string $type, \WP_Post $post): string {
	$service = lf_ai_studio_service_label_for_post($post);
	$business = trim((string) get_option('lf_business_name', get_bloginfo('name')));
	$city = lf_ai_studio_location_label_for_post($post);
	$location = $city !== '' ? ' in ' . $city : '';
	$variant = lf_ai_studio_pick_variant_index($post->ID . '|' . $type . '|body', 3);

	switch ($type) {
		case 'service_details':
			$templates = [
				__('%1$s plans every %2$s project around your property, goals, and budget%3$s. We focus on durable materials, precise workmanship, and transparent timelines so homeowners get results that look great now and hold up over time.', 'leadsforward-core'),
				__('Our %2$s work starts with a clear scope and honest recommendations%3$s. You get consistent communication, clean execution, and a finished result that performs long term.', 'leadsforward-core'),
				__('From planning to final walkthrough, we keep %2$s projects organized and predictable%3$s. Expect clear timelines, tidy job sites, and craftsmanship that lasts.', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $business !== '' ? $business : get_bloginfo('name'), $service, $location);
		case 'content_image':
		case 'image_content':
			$templates = [
				__('We combine planning, craftsmanship, and clear communication to deliver %1$s%2$s. Your goals guide the scope, and our team keeps every detail on track.', 'leadsforward-core'),
				__('Every %1$s project is scoped around your property and priorities%2$s. We focus on clean execution, durable materials, and a finished look you can be proud of.', 'leadsforward-core'),
				__('Our team treats %1$s work as a full-service process%2$s—clear estimates, consistent updates, and results that stand up over time.', 'leadsforward-core'),
			];
			return sprintf($templates[$variant], $service, $location);
		default:
			break;
	}

	return sprintf(__('%1$s delivers %2$s with clear communication, organized timelines, and results that hold up%3$s.', 'leadsforward-core'), $business !== '' ? $business : get_bloginfo('name'), $service, $location);
}

function lf_ai_studio_build_list_candidate(string $field_key, string $type, \WP_Post $post): string {
	$service = lf_ai_studio_service_label_for_post($post);
	$city = lf_ai_studio_location_label_for_post($post);
	$location = $city !== '' ? ' in ' . $city : '';
	$variant = lf_ai_studio_pick_variant_index($post->ID . '|' . $type . '|' . $field_key, 3);

	if ($field_key === 'benefits_items') {
		$sets = [
			[
				sprintf(__('Clear scope || Defined steps for every %s project.', 'leadsforward-core'), $service),
				__('Respect for your home || Clean, careful work from start to finish.', 'leadsforward-core'),
				__('Reliable timelines || We stay on schedule and keep you updated.', 'leadsforward-core'),
			],
			[
				__('Craftsmanship || Durable materials and precise installation.', 'leadsforward-core'),
				sprintf(__('Local experience || Proven results%s.', 'leadsforward-core'), $location),
				__('Transparent pricing || No surprises, just clear next steps.', 'leadsforward-core'),
			],
			[
				sprintf(__('Project planning || Every %s detail considered.', 'leadsforward-core'), $service),
				__('Communication || Fast responses and clear updates.', 'leadsforward-core'),
				__('Lasting results || Built to perform and look great.', 'leadsforward-core'),
			],
		];
		return implode("\n", $sets[$variant]);
	}

	if ($field_key === 'process_steps') {
		$sets = [
			[
				sprintf(__('Consultation: Review goals and property%s.', 'leadsforward-core'), $location),
				__('Plan: Define scope, materials, and timeline.', 'leadsforward-core'),
				sprintf(__('Build: Complete %s work with clean execution.', 'leadsforward-core'), $service),
				__('Walkthrough: Confirm details and final touches.', 'leadsforward-core'),
			],
			[
				sprintf(__('Site review: Evaluate the project%s.', 'leadsforward-core'), $location),
				__('Design: Finalize approach and expectations.', 'leadsforward-core'),
				sprintf(__('Delivery: Execute %s with skilled crews.', 'leadsforward-core'), $service),
				__('Finish: Inspect and address any adjustments.', 'leadsforward-core'),
			],
			[
				sprintf(__('Discovery: Clarify needs and priorities%s.', 'leadsforward-core'), $location),
				__('Scope: Confirm materials and sequence.', 'leadsforward-core'),
				sprintf(__('Work: Complete %s with quality control.', 'leadsforward-core'), $service),
				__('Confirm: Review outcome and next steps.', 'leadsforward-core'),
			],
		];
		return implode("\n", $sets[$variant]);
	}

	if ($field_key === 'trust_badges' || $field_key === 'hero_proof_bullets' || $field_key === 'cta_bullets') {
		$sets = [
			['Licensed and insured', 'Clear scope and pricing', 'Consistent communication'],
			['Local crews', 'Clean job sites', 'On-time scheduling'],
			['Skilled workmanship', 'Transparent timelines', 'Responsive support'],
		];
		return implode("\n", $sets[$variant]);
	}

	if ($field_key === 'service_details_checklist') {
		$sets = [
			['Project planning', 'Durable materials', 'Clean execution'],
			['Property walkthrough', 'Clear scope', 'Final quality check'],
			['Organized timeline', 'Consistent updates', 'Lasting results'],
		];
		return implode("\n", $sets[$variant]);
	}

	$generic = [
		sprintf(__('%s completed with careful planning.', 'leadsforward-core'), $service),
		sprintf(__('Tailored for homeowners%1$s.', 'leadsforward-core'), $location),
		__('Delivered with clear communication.', 'leadsforward-core'),
	];
	return implode("\n", $generic);
}

function lf_ai_studio_build_unique_field_value(string $field_key, string $field_type, string $section_type, \WP_Post $post): string {
	if ($field_type === 'list') {
		return lf_ai_studio_build_list_candidate($field_key, $section_type, $post);
	}
	if ($field_key === 'image_alt') {
		$service = lf_ai_studio_service_label_for_post($post);
		$city = lf_ai_studio_location_label_for_post($post);
		return $city !== '' ? sprintf(__('%1$s project in %2$s', 'leadsforward-core'), $service, $city) : sprintf(__('%s project', 'leadsforward-core'), $service);
	}
	if (strpos($field_key, 'headline') !== false || strpos($field_key, 'heading') !== false) {
		return lf_ai_studio_build_section_heading_candidate($section_type, ['settings' => []], $post, []);
	}
	if (strpos($field_key, 'intro') !== false || strpos($field_key, 'subheadline') !== false || strpos($field_key, 'supporting') !== false) {
		return lf_ai_studio_build_section_intro_candidate($section_type, $post);
	}
	if (strpos($field_key, 'body') !== false || strpos($field_key, 'description') !== false || strpos($field_key, 'content') !== false) {
		return lf_ai_studio_build_section_body_candidate($section_type, $post);
	}
	return '';
}

function lf_ai_studio_enforce_unique_content_for_post_sections(array $sections, array $registry, \WP_Post $post, array &$global_seen): array {
	$local_seen = [];
	foreach ($sections as $id => $section) {
		if (!is_array($section)) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		foreach ($settings as $field_key => $value) {
			if (!is_string($field_key)) {
				continue;
			}
			$field_value = is_scalar($value) ? (string) $value : '';
			$field_type = $type !== '' ? lf_ai_studio_registry_field_type($registry, $type, $field_key) : 'text';
			if (!lf_ai_studio_should_enforce_uniqueness_on_field($field_key, $field_type)) {
				continue;
			}
			$norm = lf_ai_studio_normalize_for_uniqueness($field_value);
			$needs = ($field_value === '')
				|| lf_ai_studio_is_generic_copy($field_value)
				|| (isset($local_seen[$norm]) || isset($global_seen[$norm]));
			if ($needs) {
				$candidate = lf_ai_studio_build_unique_field_value($field_key, $field_type, $type, $post);
				if ($candidate !== '') {
					$field_value = $candidate;
					$settings[$field_key] = $candidate;
					$norm = lf_ai_studio_normalize_for_uniqueness($candidate);
				}
			}
			if ($norm !== '') {
				$local_seen[$norm] = true;
				$global_seen[$norm] = true;
			}
		}
		$sections[$id]['settings'] = $settings;
	}
	return $sections;
}

function lf_ai_studio_fill_generic_section_copy(array $settings, \WP_Post $post, string $section_type = '', array $section_registry = []): array {
	$keys_to_fill = array_keys($settings);
	if ($section_type !== '' && !empty($section_registry)) {
		$keys_to_fill = lf_ai_studio_homepage_allowed_field_keys($section_type, $section_registry);
	}
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$defaults = ($section_type !== '' && function_exists('lf_sections_defaults_for'))
		? lf_sections_defaults_for($section_type, (string) get_option('lf_homepage_niche_slug', 'general'))
		: [];
	foreach ($keys_to_fill as $field_key) {
		if (!is_string($field_key) || $field_key === '') {
			continue;
		}
		$value = $settings[$field_key] ?? '';
		if (is_array($value)) {
			continue;
		}
		$field_type = $section_type !== ''
			? lf_ai_studio_registry_field_type($registry, $section_type, $field_key)
			: 'text';
		$text = is_scalar($value) ? (string) $value : '';
		$default = $defaults[$field_key] ?? '';
		$needs_fill = lf_ai_studio_is_generic_copy($text)
			|| (function_exists('lf_ai_studio_audit_value_matches_default') && $default !== '' && lf_ai_studio_audit_value_matches_default($text, $default, $field_type));
		if (!$needs_fill) {
			continue;
		}
		$fallback = lf_ai_studio_fallback_copy_for_field($field_key, $post, $field_type);
		if ($fallback === '') {
			continue;
		}
		$settings[$field_key] = $fallback;
	}
	return $settings;
}

function lf_ai_studio_fallback_homepage_field_value(string $section_id, string $field_key, string $field_type = 'text'): string {
	$business = trim((string) get_option('lf_business_name', get_bloginfo('name')));
	$city = trim((string) get_option('lf_city_region', get_option('lf_homepage_city', '')));
	$keywords = get_option('lf_homepage_keywords', []);
	$primary = is_array($keywords) ? trim((string) ($keywords['primary'] ?? '')) : '';
	$niche = trim((string) (defined('LF_HOMEPAGE_NICHE_OPTION') ? get_option(LF_HOMEPAGE_NICHE_OPTION, '') : ''));
	$focus = $primary !== '' ? $primary : ($niche !== '' ? $niche : __('local services', 'leadsforward-core'));
	$city_label = $city !== '' ? $city : '';
	$location_suffix = $city_label !== '' ? sprintf(__(' in %s', 'leadsforward-core'), $city_label) : '';

	if ($field_type === 'list') {
		if (stripos($field_key, 'faq') !== false) {
			return implode("\n", [
				sprintf(__('How does %s work in %s?', 'leadsforward-core'), $focus, $city_label),
				sprintf(__('What is the timeline for %s?', 'leadsforward-core'), $focus),
				sprintf(__('How do I choose the right %s team?', 'leadsforward-core'), $niche !== '' ? $niche : $focus),
			]);
		}
		return implode("\n", [
			sprintf(__('%s with clear planning and dependable execution.', 'leadsforward-core'), $focus),
			sprintf(__('Built for homes in %s with long-term durability in mind.', 'leadsforward-core'), $city_label),
			sprintf(__('Delivered by %s with transparent communication at every step.', 'leadsforward-core'), $business !== '' ? $business : get_bloginfo('name')),
		]);
	}

	if (strpos($field_key, 'headline') !== false || strpos($field_key, 'heading') !== false) {
		return sanitize_text_field(sprintf(__('%1$s%2$s', 'leadsforward-core'), $focus, $location_suffix));
	}
	if (strpos($field_key, 'subheadline') !== false || strpos($field_key, 'intro') !== false) {
		if ($city_label !== '') {
			return sanitize_textarea_field(sprintf(__('%1$s helps homeowners in %2$s plan and complete high-quality projects with clear timelines, fair pricing, and durable results.', 'leadsforward-core'), $business !== '' ? $business : get_bloginfo('name'), $city_label));
		}
		return sanitize_textarea_field(sprintf(__('%1$s helps homeowners plan and complete high-quality projects with clear timelines, fair pricing, and durable results.', 'leadsforward-core'), $business !== '' ? $business : get_bloginfo('name')));
	}
	if (strpos($field_key, 'body') !== false || strpos($field_key, 'description') !== false || strpos($field_key, 'content') !== false) {
		if ($city_label !== '') {
			return wp_kses_post(sprintf(__('%1$s combines practical strategy and skilled execution for %2$s in %3$s. Every project is scoped around your goals, property conditions, and budget so you get clean workmanship and predictable outcomes.', 'leadsforward-core'), $business !== '' ? $business : get_bloginfo('name'), $focus, $city_label));
		}
		return wp_kses_post(sprintf(__('%1$s combines practical strategy and skilled execution for %2$s. Every project is scoped around your goals, property conditions, and budget so you get clean workmanship and predictable outcomes.', 'leadsforward-core'), $business !== '' ? $business : get_bloginfo('name'), $focus));
	}
	if (strpos($field_key, 'cta_primary') !== false) {
		return __('Get a Free Quote', 'leadsforward-core');
	}
	if (strpos($field_key, 'cta_secondary') !== false) {
		return __('Call Now', 'leadsforward-core');
	}
	if (strpos($field_key, 'label') !== false) {
		return sanitize_text_field(sprintf(__('%s details', 'leadsforward-core'), $focus));
	}
	return '';
}

function lf_ai_studio_fill_generic_homepage_copy(array $settings, string $section_id, array $registry): array {
	$allowed = lf_ai_studio_homepage_allowed_field_keys($section_id, $registry);
	foreach ($allowed as $field_key) {
		$current = $settings[$field_key] ?? '';
		$current_text = is_scalar($current) ? (string) $current : '';
		if (!lf_ai_studio_is_generic_copy($current_text)) {
			continue;
		}
		$field_type = lf_ai_studio_registry_field_type($registry, $section_id, $field_key);
		$fallback = lf_ai_studio_fallback_homepage_field_value($section_id, $field_key, $field_type);
		if ($fallback === '') {
			continue;
		}
		$settings[$field_key] = $fallback;
	}
	return $settings;
}

function lf_ai_studio_fill_site_content_without_ai(): array {
	$updated = ['homepage_sections' => 0, 'post_sections' => 0, 'posts_updated' => 0];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];

	if (function_exists('lf_get_homepage_section_config') && !empty($registry)) {
		$home_config = lf_get_homepage_section_config();
		if (is_array($home_config) && !empty($home_config)) {
			$home_changed = false;
			foreach ($home_config as $section_id => $settings) {
				$section_id = (string) $section_id;
				if (!is_array($settings) || !isset($registry[$section_id])) {
					continue;
				}
				$filled = lf_ai_studio_fill_generic_homepage_copy($settings, $section_id, $registry[$section_id]);
				if ($filled !== $settings) {
					$home_config[$section_id] = lf_sections_sanitize_settings($section_id, $filled);
					$updated['homepage_sections']++;
					$home_changed = true;
				}
			}
			if ($home_changed) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $home_config, true);
			}
		}
	}

	if (!function_exists('lf_pb_get_context_for_post') || !function_exists('lf_pb_get_post_config')) {
		return $updated;
	}

	$post_ids = get_posts([
		'post_type' => ['page', 'post', 'lf_service', 'lf_service_area'],
		'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	foreach (array_map('intval', $post_ids) as $post_id) {
		$post = get_post($post_id);
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$context = lf_pb_get_context_for_post($post);
		if ($context === '') {
			continue;
		}
		$config = lf_pb_get_post_config($post_id, $context);
		$order = is_array($config['order'] ?? null) ? $config['order'] : [];
		$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
		if (empty($order) || empty($sections)) {
			continue;
		}
		$changed = false;
		foreach ($order as $instance_id) {
			$section = $sections[$instance_id] ?? null;
			if (!is_array($section) || empty($section['enabled'])) {
				continue;
			}
			$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
			$type = (string) ($section['type'] ?? '');
			$section_registry = ($type !== '' && isset($registry[$type]) && is_array($registry[$type])) ? $registry[$type] : [];
			$filled = lf_ai_studio_fill_generic_section_copy($settings, $post, $type, $section_registry);
			$link_result = lf_ai_studio_orchestrate_internal_links_for_settings($filled, $type, $registry, $post);
			$linked = is_array($link_result['settings'] ?? null) ? $link_result['settings'] : $filled;
			if ($linked !== $settings) {
				$sections[$instance_id]['settings'] = lf_sections_sanitize_settings($type, $linked);
				$updated['post_sections']++;
				$changed = true;
			}
		}
		if ($changed) {
			update_post_meta($post_id, LF_PB_META_KEY, [
				'order' => $order,
				'sections' => $sections,
				'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
			]);
			$updated['posts_updated']++;
			if ($post->post_type === 'post') {
				lf_ai_studio_backfill_post_title_excerpt($post_id);
				lf_ai_studio_sync_blog_post_content_from_sections($post_id);
			}
		}
	}

	return $updated;
}

function lf_ai_studio_sync_blog_post_content_from_sections(int $post_id): void {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || $post->post_type !== 'post') {
		return;
	}
	$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
	if ($context !== 'post' || !function_exists('lf_pb_get_post_config')) {
		return;
	}
	$config = lf_pb_get_post_config($post_id, $context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$chunks = [];
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$heading = trim((string) ($settings['section_heading'] ?? $settings['hero_headline'] ?? ''));
		if ($heading !== '') {
			$chunks[] = '<h2>' . esc_html($heading) . '</h2>';
		}
		$candidates = [
			(string) ($settings['hero_supporting_text'] ?? ''),
			(string) ($settings['hero_subheadline'] ?? ''),
			(string) ($settings['section_intro'] ?? ''),
			(string) ($settings['section_body'] ?? ''),
			(string) ($settings['section_body_secondary'] ?? ''),
			(string) ($settings['service_details_body'] ?? ''),
			(string) ($settings['project_excerpt'] ?? ''),
		];
		foreach ($candidates as $candidate) {
			$plain = trim(wp_strip_all_tags($candidate));
			if ($plain === '' || lf_ai_studio_is_generic_copy($plain)) {
				continue;
			}
			$chunks[] = '<p>' . wp_kses_post($plain) . '</p>';
		}
	}
	$content = trim(implode("\n\n", $chunks));
	if (str_word_count(wp_strip_all_tags($content)) < 220) {
		$keyword = trim((string) get_post_meta($post_id, 'lf_ai_post_keyword', true));
		$business = trim((string) get_option('lf_business_name', get_bloginfo('name')));
		$city = trim((string) get_option('lf_city_region', __('your area', 'leadsforward-core')));
		$focus = $keyword !== '' ? $keyword : $post->post_title;
		$content = '<p>' . esc_html(sprintf(__('%1$s homeowners in %2$s need practical guidance before investing in %3$s. This guide covers planning, budget ranges, material choices, and the key checkpoints that prevent delays and costly mistakes.', 'leadsforward-core'), $business, $city, $focus)) . '</p>'
			. '<p>' . esc_html(sprintf(__('%1$s helps clients scope projects clearly, compare options, and phase work in a way that protects quality while keeping timelines realistic. You can use this framework whether you are comparing bids or preparing for construction.', 'leadsforward-core'), $business)) . '</p>'
			. '<p>' . esc_html(__('Start with goals, measurements, and a clear maintenance plan. Then align the solution to property conditions, seasonal timing, and long-term value so the finished result performs as well as it looks.', 'leadsforward-core')) . '</p>'
			. '<p>' . esc_html(__('If you want a detailed estimate, request a consultation and we will map out a tailored scope with transparent next steps.', 'leadsforward-core')) . '</p>';
	}
	wp_update_post([
		'ID' => $post_id,
		'post_content' => wp_kses_post($content),
	]);
}

function lf_ai_studio_collect_writing_samples(): array {
	return [];
}

function lf_ai_studio_manifest_template(): array {
	return [
		'business' => [
			'name' => 'ProClean Power Washing Sarasota',
			'legal_name' => 'ProClean Power Washing LLC',
			'phone' => '(941) 260-0596',
			'email' => 'hello@procleanpowerwash.com',
			'address' => [
				'street' => '2075 Main Street #1',
				'city' => 'Sarasota',
				'state' => 'FL',
				'zip' => '34237',
			],
			'primary_city' => 'Sarasota',
			'niche' => 'Power Washing',
			'niche_slug' => 'power-washing',
			'site_style' => 'premium',
			'variation_seed' => 'proclean-sarasota-2026-02-11',
		],
		'homepage' => [
			'primary_keyword' => 'Power washing Sarasota',
			'secondary_keywords' => [
				'Pressure washing Sarasota',
				'House washing Sarasota',
				'Driveway cleaning Sarasota',
			],
		],
		'services' => [
			[
				'title' => 'House Washing',
				'slug' => 'house-washing',
				'primary_keyword' => 'House washing Sarasota',
				'secondary_keywords' => ['Soft wash house cleaning', 'Exterior house wash'],
				'custom_cta_context' => 'Gentle, safe washes that protect paint and landscaping.',
			],
			[
				'title' => 'Roof Soft Washing',
				'slug' => 'roof-soft-washing',
				'primary_keyword' => 'Roof soft washing Sarasota',
				'secondary_keywords' => ['Roof algae removal', 'Low pressure roof cleaning'],
				'custom_cta_context' => 'No-pressure roof cleaning that preserves shingles.',
			],
			[
				'title' => 'Driveway Cleaning',
				'slug' => 'driveway-cleaning',
				'primary_keyword' => 'Driveway cleaning Sarasota',
				'secondary_keywords' => ['Concrete cleaning', 'Oil stain removal'],
				'custom_cta_context' => 'Brighten concrete and improve curb appeal fast.',
			],
			[
				'title' => 'Patio & Paver Cleaning',
				'slug' => 'patio-paver-cleaning',
				'primary_keyword' => 'Paver cleaning Sarasota',
				'secondary_keywords' => ['Patio washing', 'Paver sand restoration'],
				'custom_cta_context' => 'Restore color and traction on patios and pool decks.',
			],
			[
				'title' => 'Gutter Cleaning & Whitening',
				'slug' => 'gutter-cleaning-whitening',
				'primary_keyword' => 'Gutter cleaning Sarasota',
				'secondary_keywords' => ['Gutter whitening', 'Downspout clearing'],
				'custom_cta_context' => 'Clear flow and brighten gutters for a clean edge.',
			],
			[
				'title' => 'Commercial Power Washing',
				'slug' => 'commercial-power-washing',
				'primary_keyword' => 'Commercial power washing Sarasota',
				'secondary_keywords' => ['Storefront washing', 'Parking lot cleaning'],
				'custom_cta_context' => 'Keep storefronts and walkways spotless and safe.',
			],
		],
		'service_areas' => [
			[
				'city' => 'Sarasota',
				'state' => 'FL',
				'slug' => 'sarasota',
				'primary_keyword' => 'Power washing Sarasota FL',
			],
			[
				'city' => 'Bradenton',
				'state' => 'FL',
				'slug' => 'bradenton',
				'primary_keyword' => 'Power washing Bradenton FL',
			],
			[
				'city' => 'Lakewood Ranch',
				'state' => 'FL',
				'slug' => 'lakewood-ranch',
				'primary_keyword' => 'Power washing Lakewood Ranch FL',
			],
			[
				'city' => 'Venice',
				'state' => 'FL',
				'slug' => 'venice',
				'primary_keyword' => 'Power washing Venice FL',
			],
			[
				'city' => 'Palmetto',
				'state' => 'FL',
				'slug' => 'palmetto',
				'primary_keyword' => 'Power washing Palmetto FL',
			],
		],
		'global' => [
			'global_cta_override' => false,
			'custom_global_cta' => [
				'headline' => 'Get a fast, no-obligation estimate',
				'subheadline' => 'Talk to a local expert and get clear next steps today.',
			],
		],
	];
}

function lf_ai_studio_manifest_to_setup_data(array $manifest): array {
	$business = $manifest['business'] ?? [];
	$address = is_array($business['address'] ?? null) ? $business['address'] : [];
	$social = is_array($business['social'] ?? null) ? $business['social'] : [];
	$category = trim((string) ($business['category'] ?? ''));
	if ($category === '') {
		$category = 'HomeAndConstructionBusiness';
	}
	$same_as = is_array($business['same_as'] ?? null) ? $business['same_as'] : [];
	$website_url = (string) ($business['website_url'] ?? '');
	if ($website_url !== '') {
		$same_as[] = $website_url;
	}
	$same_as = array_values(array_unique(array_filter(array_map('esc_url_raw', $same_as))));
	$same_as_string = $same_as ? implode("\n", $same_as) : '';
	$niche_slug = lf_ai_studio_manifest_niche_slug($business);
	if (is_wp_error($niche_slug)) {
		return ['error' => $niche_slug->get_error_message()];
	}
	$services = $manifest['services'] ?? [];
	$areas = $manifest['service_areas'] ?? [];
	$mapped_services = [];
	foreach ($services as $item) {
		if (!is_array($item)) {
			continue;
		}
		$mapped_services[] = [
			'title' => (string) ($item['title'] ?? ''),
			'slug' => (string) ($item['slug'] ?? ''),
		];
	}
	$mapped_areas = [];
	foreach ($areas as $item) {
		if (!is_array($item)) {
			continue;
		}
		$mapped_areas[] = [
			'name' => (string) ($item['city'] ?? ''),
			'state' => (string) ($item['state'] ?? ''),
			'slug' => (string) ($item['slug'] ?? ''),
		];
	}
	return [
		'niche_slug' => $niche_slug,
		'business_name' => (string) ($business['name'] ?? ''),
		'business_legal_name' => (string) ($business['legal_name'] ?? ''),
		'business_phone_primary' => (string) ($business['phone'] ?? ''),
		'business_phone_tracking' => '',
		'business_phone_display' => 'primary',
		'business_phone' => (string) ($business['phone'] ?? ''),
		'business_email' => (string) ($business['email'] ?? ''),
		'business_address' => '',
		'business_address_street' => (string) ($address['street'] ?? ''),
		'business_address_city' => (string) ($address['city'] ?? ''),
		'business_address_state' => (string) ($address['state'] ?? ''),
		'business_address_zip' => (string) ($address['zip'] ?? ''),
		'business_service_area_type' => 'service_area',
		'business_geo' => ['lat' => '', 'lng' => ''],
		'business_hours' => (string) ($business['hours'] ?? ''),
		'business_category' => $category,
		'business_short_description' => '',
		'business_gbp_url' => (string) ($business['gbp_url'] ?? ''),
		'business_social_facebook' => (string) ($social['facebook'] ?? ''),
		'business_social_instagram' => (string) ($social['instagram'] ?? ''),
		'business_social_youtube' => (string) ($social['youtube'] ?? ''),
		'business_social_linkedin' => (string) ($social['linkedin'] ?? ''),
		'business_social_tiktok' => (string) ($social['tiktok'] ?? ''),
		'business_social_x' => (string) ($social['x'] ?? ''),
		'business_same_as' => $same_as_string,
		'business_founding_year' => (string) ($business['founding_year'] ?? ''),
		'business_license_number' => '',
		'business_insurance_statement' => '',
		'business_place_id' => (string) ($business['place_id'] ?? ''),
		'business_place_name' => (string) ($business['place_name'] ?? ''),
		'business_place_address' => '',
		'business_map_embed' => '',
		'services' => $mapped_services,
		'service_areas' => $mapped_areas,
	];
}

function lf_ai_studio_manifest_niche_slug(array $business) {
	$registry = function_exists('lf_get_niche_registry') ? lf_get_niche_registry() : [];
	$valid_slugs = is_array($registry) ? array_keys($registry) : [];
	$provided = sanitize_title((string) ($business['niche_slug'] ?? ''));
	if ($provided !== '') {
		if (in_array($provided, $valid_slugs, true)) {
			return $provided;
		}
		return new \WP_Error('invalid_niche_slug', 'Invalid niche slug in manifest.');
	}
	$derived = sanitize_title((string) ($business['niche'] ?? ''));
	if ($derived !== '' && in_array($derived, $valid_slugs, true)) {
		return $derived;
	}
	return new \WP_Error('invalid_niche_slug', 'Invalid niche slug in manifest.');
}

function lf_ai_studio_scaffold_manifest(array $manifest): array {
	if (!function_exists('lf_run_setup')) {
		return ['success' => false, 'message' => __('Setup runner not available.', 'leadsforward-core')];
	}
	$data = lf_ai_studio_manifest_to_setup_data($manifest);
	if (!empty($data['error'])) {
		return ['success' => false, 'message' => (string) $data['error'], 'errors' => [(string) $data['error']]];
	}
	$result = lf_run_setup($data);
	lf_ai_studio_ensure_header_menu_more_children();
	lf_ai_studio_ensure_header_menu_primary_pages();
	$business = $manifest['business'] ?? [];
	$address = is_array($business['address'] ?? null) ? $business['address'] : [];
	$biz_name = (string) ($business['name'] ?? '');
	$biz_legal = (string) ($business['legal_name'] ?? '');
	$biz_phone = (string) ($business['phone'] ?? '');
	$biz_email = (string) ($business['email'] ?? '');
	$address_street = (string) ($address['street'] ?? '');
	$address_city = (string) ($address['city'] ?? '');
	$address_state = (string) ($address['state'] ?? '');
	$address_zip = (string) ($address['zip'] ?? '');
	$address_full = function_exists('lf_business_entity_address_string')
		? lf_business_entity_address_string([
			'street' => $address_street,
			'city' => $address_city,
			'state' => $address_state,
			'zip' => $address_zip,
		])
		: trim(implode(', ', array_filter([$address_street, $address_city, $address_state, $address_zip])));
	if ($biz_name !== '') {
		update_option('blogname', $biz_name);
	}
	if (function_exists('lf_update_business_info_value')) {
		$phone_tracking = (string) ($data['business_phone_tracking'] ?? '');
		$phone_display = ($data['business_phone_display'] ?? 'primary') === 'tracking' ? 'tracking' : 'primary';
		$display_phone = $phone_display === 'tracking' && $phone_tracking !== '' ? $phone_tracking : $biz_phone;
		$category = (string) ($data['business_category'] ?? '');
		if ($category === '') {
			$category = 'HomeAndConstructionBusiness';
		}
		lf_update_business_info_value('lf_business_name', $biz_name);
		lf_update_business_info_value('lf_business_legal_name', $biz_legal);
		lf_update_business_info_value('lf_business_phone_primary', $biz_phone);
		lf_update_business_info_value('lf_business_phone_tracking', $phone_tracking);
		lf_update_business_info_value('lf_business_phone_display', $phone_display);
		lf_update_business_info_value('lf_business_phone', $display_phone);
		lf_update_business_info_value('lf_business_email', $biz_email);
		lf_update_business_info_value('lf_business_address_street', $address_street);
		lf_update_business_info_value('lf_business_address_city', $address_city);
		lf_update_business_info_value('lf_business_address_state', $address_state);
		lf_update_business_info_value('lf_business_address_zip', $address_zip);
		lf_update_business_info_value('lf_business_address', $address_full);
		lf_update_business_info_value('lf_business_service_area_type', (string) ($data['business_service_area_type'] ?? 'service_area'));
		lf_update_business_info_value('lf_business_geo', $data['business_geo'] ?? ['lat' => '', 'lng' => '']);
		lf_update_business_info_value('lf_business_hours', (string) ($data['business_hours'] ?? ''));
		lf_update_business_info_value('lf_business_category', $category);
		lf_update_business_info_value('lf_business_short_description', (string) ($data['business_short_description'] ?? ''));
		lf_update_business_info_value('lf_business_gbp_url', (string) ($data['business_gbp_url'] ?? ''));
		lf_update_business_info_value('lf_business_social_facebook', (string) ($data['business_social_facebook'] ?? ''));
		lf_update_business_info_value('lf_business_social_instagram', (string) ($data['business_social_instagram'] ?? ''));
		lf_update_business_info_value('lf_business_social_youtube', (string) ($data['business_social_youtube'] ?? ''));
		lf_update_business_info_value('lf_business_social_linkedin', (string) ($data['business_social_linkedin'] ?? ''));
		lf_update_business_info_value('lf_business_social_tiktok', (string) ($data['business_social_tiktok'] ?? ''));
		lf_update_business_info_value('lf_business_social_x', (string) ($data['business_social_x'] ?? ''));
		lf_update_business_info_value('lf_business_same_as', (string) ($data['business_same_as'] ?? ''));
		lf_update_business_info_value('lf_business_founding_year', (string) ($data['business_founding_year'] ?? ''));
		lf_update_business_info_value('lf_business_license_number', (string) ($data['business_license_number'] ?? ''));
		lf_update_business_info_value('lf_business_insurance_statement', (string) ($data['business_insurance_statement'] ?? ''));
		lf_update_business_info_value('lf_business_place_id', (string) ($data['business_place_id'] ?? ''));
		lf_update_business_info_value('lf_business_place_name', (string) ($data['business_place_name'] ?? ''));
		lf_update_business_info_value('lf_business_place_address', (string) ($data['business_place_address'] ?? ''));
		lf_update_business_info_value('lf_business_map_embed', (string) ($data['business_map_embed'] ?? ''));
	}
	if (function_exists('lf_update_global_option_value')) {
		lf_update_global_option_value('lf_header_cta_label', '');
		lf_update_global_option_value('lf_header_cta_url', '');
	} else {
		update_option('options_lf_header_cta_label', '');
		update_option('options_lf_header_cta_url', '');
	}
	if (defined('LF_HOMEPAGE_NICHE_OPTION')) {
		update_option(LF_HOMEPAGE_NICHE_OPTION, $data['niche_slug'], true);
	}
	if (!empty($data['niche_slug'])) {
		update_option('lf_active_icon_pack', (string) $data['niche_slug'], true);
	}
	update_option('lf_homepage_city', (string) ($business['primary_city'] ?? $address_city), true);
	update_option('lf_homepage_keywords', [
		'primary' => (string) ($manifest['homepage']['primary_keyword'] ?? ''),
		'secondary' => $manifest['homepage']['secondary_keywords'] ?? [],
	], true);
	$home = get_page_by_path('home', OBJECT, 'page');
	if ($home instanceof \WP_Post) {
		update_option('show_on_front', 'page');
		update_option('page_on_front', $home->ID);
	}
	$scaffold_success = is_array($result) && !empty($result['success']);
	if ($scaffold_success) {
		$seo_settings = function_exists('lf_seo_get_settings') ? lf_seo_get_settings() : get_option('lf_seo_settings', []);
		if (!is_array($seo_settings)) {
			$seo_settings = [];
		}
		$seo_settings = array_replace_recursive([
			'general' => [],
			'schema' => [],
			'sitemap' => [],
			'ai' => [],
		], $seo_settings);
		$seo_settings['general']['title_template'] = '{{page_title}} | {{primary_city}} | {{brand}}';
		$seo_settings['schema']['enable_local_business'] = true;
		$seo_settings['sitemap']['enable'] = true;
		$seo_settings['ai']['enable_auto_keywords'] = true;
		$seo_settings['ai']['enable_keyword_map'] = true;
		$seo_settings['ai_keyword_engine'] = true;
		update_option('lf_seo_settings', $seo_settings);

		if (!empty($seo_settings['ai_keyword_engine'])) {
			$homepage_primary = sanitize_text_field((string) ($manifest['homepage']['primary_keyword'] ?? ''));
			$secondary_raw = $manifest['homepage']['secondary_keywords'] ?? [];
			if (is_string($secondary_raw)) {
				$secondary_raw = preg_split('/\r\n|\r|\n|,/', $secondary_raw);
			}
			if (!is_array($secondary_raw)) {
				$secondary_raw = [];
			}
			$secondary_pool = array_values(array_unique(array_filter(array_map('sanitize_text_field', $secondary_raw))));
			if ($homepage_primary !== '') {
				$secondary_pool = array_values(array_filter($secondary_pool, function ($keyword) use ($homepage_primary) {
					return strcasecmp($keyword, $homepage_primary) !== 0;
				}));
			}

			$map = [
				'primary' => [],
				'secondary' => [
					'pool' => $secondary_pool,
				],
				'last_index' => [],
			];
			if ($homepage_primary !== '') {
				$map['primary']['homepage'] = $homepage_primary;
			}
			$homepage_id = (int) get_option('page_on_front');
			if (!$homepage_id) {
				$homepage_id = $home instanceof \WP_Post ? (int) $home->ID : 0;
			}
			if ($homepage_id > 0 && $homepage_primary !== '') {
				update_post_meta($homepage_id, '_lf_seo_primary_keyword', $homepage_primary);
				if (function_exists('lf_seo_maybe_populate_generated_meta')) {
					lf_seo_maybe_populate_generated_meta($homepage_id, $homepage_primary, $secondary_pool);
				}
			}

			$used = [];
			if ($homepage_primary !== '') {
				$used[strtolower($homepage_primary)] = true;
			}

			$services = get_posts([
				'post_type' => 'lf_service',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'orderby' => 'menu_order title',
				'order' => 'ASC',
				'no_found_rows' => true,
			]);
			$service_keywords = [];
			$service_index = 0;
			if (!empty($secondary_pool)) {
				$pool_count = count($secondary_pool);
				foreach ($services as $service) {
					$keyword = $secondary_pool[$service_index % $pool_count] ?? '';
					$service_index++;
					$keyword = trim((string) $keyword);
					if ($keyword === '') {
						continue;
					}
					update_post_meta($service->ID, '_lf_seo_primary_keyword', $keyword);
					if (function_exists('lf_seo_maybe_populate_generated_meta')) {
						lf_seo_maybe_populate_generated_meta((int) $service->ID, $keyword, $secondary_pool);
					}
					$map['primary']['post:' . (int) $service->ID] = $keyword;
					$used[strtolower($keyword)] = true;
					$service_keywords[] = $keyword;
				}
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
			$area_index = 0;
			if (!empty($service_keywords)) {
				foreach ($areas as $area) {
					$service_keyword = $service_keywords[$area_index % count($service_keywords)] ?? '';
					$area_index++;
					$area_city = trim((string) get_the_title($area));
					$keyword = trim(implode(' ', array_filter([$service_keyword, $area_city])));
					if ($keyword === '') {
						continue;
					}
					if ($homepage_primary !== '' && strcasecmp($keyword, $homepage_primary) === 0) {
						continue;
					}
					update_post_meta($area->ID, '_lf_seo_primary_keyword', $keyword);
					if (function_exists('lf_seo_maybe_populate_generated_meta')) {
						lf_seo_maybe_populate_generated_meta((int) $area->ID, $keyword, $secondary_pool);
					}
					$map['primary']['post:' . (int) $area->ID] = $keyword;
				}
			}
			$map['last_index']['service_area'] = $area_index;

			$remaining_pool = [];
			foreach ($secondary_pool as $keyword) {
				if (!isset($used[strtolower($keyword)])) {
					$remaining_pool[] = $keyword;
				}
			}
			$posts = get_posts([
				'post_type' => 'post',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'orderby' => 'date',
				'order' => 'DESC',
				'no_found_rows' => true,
			]);
			$post_index = 0;
			if (!empty($remaining_pool)) {
				$pool_count = count($remaining_pool);
				foreach ($posts as $post_item) {
					$keyword = $remaining_pool[$post_index % $pool_count] ?? '';
					$post_index++;
					$keyword = trim((string) $keyword);
					if ($keyword === '') {
						continue;
					}
					update_post_meta($post_item->ID, '_lf_seo_primary_keyword', $keyword);
					if (function_exists('lf_seo_maybe_populate_generated_meta')) {
						lf_seo_maybe_populate_generated_meta((int) $post_item->ID, $keyword, $secondary_pool);
					}
					$map['primary']['post:' . (int) $post_item->ID] = $keyword;
				}
			}
			$map['last_index']['post'] = $post_index;

			update_option('lf_keyword_map', $map);
		}
		if (function_exists('lf_prime_image_distribution_for_site')) {
			lf_prime_image_distribution_for_site();
		}
		$homepage_payload = lf_ai_studio_build_homepage_blueprint();
		$blog_topics = lf_ai_studio_blog_post_topics($manifest, is_array($homepage_payload) ? $homepage_payload : []);
		lf_ai_studio_ensure_blog_posts($blog_topics);
		lf_ai_studio_fill_site_content_without_ai();
		if (function_exists('lf_seo_refresh_metadata_for_generated_content')) {
			lf_seo_refresh_metadata_for_generated_content();
		}
	}
	return is_array($result) ? $result : ['success' => false, 'message' => __('Setup runner failed.', 'leadsforward-core')];
}

function lf_ai_studio_ensure_header_menu_more_children(): void {
	$locations = get_nav_menu_locations();
	$menu_id = $locations['header_menu'] ?? 0;
	if (!$menu_id) {
		return;
	}
	$menu = wp_get_nav_menu_object($menu_id);
	if (!$menu || ($menu->name ?? '') !== 'Header Menu') {
		return;
	}
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || empty($items)) {
		return;
	}
	$more_item = null;
	foreach ($items as $item) {
		$classes = $item->classes ?? [];
		if (is_array($classes) && in_array('lf-menu-more', $classes, true)) {
			$more_item = $item;
			break;
		}
	}
	if (!$more_item) {
		return;
	}
	$existing_children = [];
	foreach ($items as $item) {
		if ((int) $item->menu_item_parent === (int) $more_item->ID) {
			$existing_children[] = (string) $item->url;
		}
	}
	$slugs = ['about-us', 'blog', 'contact'];
	foreach ($slugs as $slug) {
		$page = get_page_by_path($slug);
		if (!$page instanceof \WP_Post) {
			continue;
		}
		$url = get_permalink($page->ID);
		if ($url && in_array($url, $existing_children, true)) {
			continue;
		}
		wp_update_nav_menu_item($menu_id, 0, [
			'menu-item-title' => get_the_title($page->ID),
			'menu-item-url' => $url,
			'menu-item-type' => 'post_type',
			'menu-item-object' => 'page',
			'menu-item-object-id' => $page->ID,
			'menu-item-parent-id' => (int) $more_item->ID,
			'menu-item-status' => 'publish',
		]);
	}
	$project_archive = get_post_type_archive_link('lf_project');
	if ($project_archive && !in_array($project_archive, $existing_children, true)) {
		wp_update_nav_menu_item($menu_id, 0, [
			'menu-item-title' => __('Projects', 'leadsforward-core'),
			'menu-item-url' => $project_archive,
			'menu-item-type' => 'custom',
			'menu-item-status' => 'publish',
			'menu-item-parent-id' => (int) $more_item->ID,
		]);
	}
}

function lf_ai_studio_ensure_header_menu_primary_pages(): void {
	$locations = get_nav_menu_locations();
	$menu_id = $locations['header_menu'] ?? 0;
	if (!$menu_id) {
		return;
	}
	$menu = wp_get_nav_menu_object($menu_id);
	if (!$menu || ($menu->name ?? '') !== 'Header Menu') {
		return;
	}
	$services_page = get_page_by_path('our-services');
$areas_page = get_page_by_path('service-areas');
	if (!$services_page instanceof \WP_Post && !$areas_page instanceof \WP_Post) {
		return;
	}
	$items = wp_get_nav_menu_items($menu_id);
	if (!is_array($items) || empty($items)) {
		return;
	}
	$services_archive = get_post_type_archive_link('lf_service');
	$areas_archive = get_post_type_archive_link('lf_service_area');
	foreach ($items as $item) {
		if (!$item instanceof \WP_Post) {
			continue;
		}
		$title = strtolower((string) $item->title);
		$url = (string) $item->url;
		if ($services_page instanceof \WP_Post) {
			$is_services = $title === 'services' || ($services_archive && $url === $services_archive);
			if ($is_services) {
				wp_update_nav_menu_item($menu_id, $item->ID, [
					'menu-item-title' => get_the_title($services_page->ID),
					'menu-item-url' => get_permalink($services_page->ID),
					'menu-item-type' => 'post_type',
					'menu-item-object' => 'page',
					'menu-item-object-id' => $services_page->ID,
					'menu-item-status' => 'publish',
					'menu-item-parent-id' => (int) $item->menu_item_parent,
					'menu-item-position' => (int) $item->menu_order,
					'menu-item-classes' => is_array($item->classes) ? implode(' ', $item->classes) : '',
				]);
			}
		}
		if ($areas_page instanceof \WP_Post) {
			$is_areas = $title === 'service areas' || ($areas_archive && $url === $areas_archive);
			if ($is_areas) {
				wp_update_nav_menu_item($menu_id, $item->ID, [
					'menu-item-title' => get_the_title($areas_page->ID),
					'menu-item-url' => get_permalink($areas_page->ID),
					'menu-item-type' => 'post_type',
					'menu-item-object' => 'page',
					'menu-item-object-id' => $areas_page->ID,
					'menu-item-status' => 'publish',
					'menu-item-parent-id' => (int) $item->menu_item_parent,
					'menu-item-position' => (int) $item->menu_order,
					'menu-item-classes' => is_array($item->classes) ? implode(' ', $item->classes) : '',
				]);
			}
		}
	}
}

function lf_ai_studio_get_manifest(): array {
	$manifest = get_option('lf_site_manifest', []);
	return is_array($manifest) ? $manifest : [];
}

function lf_ai_studio_get_research_document(): array {
	$doc = get_option('lf_site_research_document', []);
	return is_array($doc) ? $doc : [];
}

function lf_ai_studio_research_hash(array $doc): string {
	if (empty($doc)) {
		return '';
	}
	$payload = wp_json_encode($doc);
	return is_string($payload) ? hash('sha256', $payload) : '';
}

function lf_ai_studio_compact_research_value($value, int $max_items = 6, int $max_len = 240) {
	if (is_string($value)) {
		$trimmed = trim($value);
		if ($trimmed === '') {
			return '';
		}
		if (strlen($trimmed) > $max_len) {
			return substr($trimmed, 0, max(0, $max_len - 3)) . '...';
		}
		return $trimmed;
	}
	if (is_array($value)) {
		$is_assoc = array_keys($value) !== range(0, count($value) - 1);
		if ($is_assoc) {
			$out = [];
			foreach ($value as $key => $item) {
				$out[$key] = lf_ai_studio_compact_research_value($item, $max_items, $max_len);
			}
			return $out;
		}
		$slice = array_slice($value, 0, $max_items);
		return array_values(array_map(function ($item) use ($max_items, $max_len) {
			return lf_ai_studio_compact_research_value($item, $max_items, $max_len);
		}, $slice));
	}
	return $value;
}

function lf_ai_studio_build_research_context(): array {
	$doc = lf_ai_studio_get_research_document();
	if (empty($doc)) {
		return [];
	}
	$keys = [
		'brand_positioning',
		'conversion_strategy',
		'voice_guidelines',
		'seo_strategy',
		'faq_strategy',
		'content_expansion_guidelines',
	];
	$out = [];
	foreach ($keys as $key) {
		if (isset($doc[$key]) && is_array($doc[$key])) {
			$out[$key] = lf_ai_studio_compact_research_value($doc[$key]);
		}
	}
	return $out;
}

function lf_ai_studio_manifest_exists(): bool {
	return !empty(lf_ai_studio_get_manifest());
}

function lf_ai_studio_validate_manifest(array $manifest): array {
	$errors = [];
	if (!isset($manifest['business']) || !is_array($manifest['business'])) {
		$errors[] = __('Manifest missing business object.', 'leadsforward-core');
	} else {
		$biz = $manifest['business'];
		$addr = isset($biz['address']) && is_array($biz['address']) ? $biz['address'] : [];
		$required_business_keys = ['name', 'legal_name', 'phone', 'email', 'primary_city', 'niche', 'site_style', 'variation_seed'];
		foreach ($required_business_keys as $key) {
			if (!array_key_exists($key, $biz)) {
				$errors[] = sprintf(__('Manifest missing business.%s.', 'leadsforward-core'), $key);
			}
		}
		foreach (['street', 'city', 'state', 'zip'] as $key) {
			if (!array_key_exists($key, $addr)) {
				$errors[] = sprintf(__('Manifest missing business.address.%s.', 'leadsforward-core'), $key);
			}
		}
		if (array_key_exists('niche_slug', $biz)) {
			$slug = sanitize_title((string) $biz['niche_slug']);
			$registry = function_exists('lf_get_niche_registry') ? lf_get_niche_registry() : [];
			$valid = is_array($registry) ? array_keys($registry) : [];
			if ($slug === '' || !in_array($slug, $valid, true)) {
				$errors[] = __('Invalid niche slug in manifest.', 'leadsforward-core');
			}
		}
	}
	if (!isset($manifest['homepage']) || !is_array($manifest['homepage'])) {
		$errors[] = __('Manifest missing homepage object.', 'leadsforward-core');
	} else {
		$home = $manifest['homepage'];
		if (!array_key_exists('primary_keyword', $home)) {
			$errors[] = __('Manifest missing homepage.primary_keyword.', 'leadsforward-core');
		}
		if (!array_key_exists('secondary_keywords', $home)) {
			$errors[] = __('Manifest missing homepage.secondary_keywords.', 'leadsforward-core');
		}
	}
	if (!isset($manifest['services']) || !is_array($manifest['services']) || empty($manifest['services'])) {
		$errors[] = __('Manifest missing services array.', 'leadsforward-core');
	}
	if (!isset($manifest['service_areas']) || !is_array($manifest['service_areas']) || empty($manifest['service_areas'])) {
		$errors[] = __('Manifest missing service_areas array.', 'leadsforward-core');
	}
	if (!isset($manifest['global']) || !is_array($manifest['global'])) {
		$errors[] = __('Manifest missing global object.', 'leadsforward-core');
	} else {
		$global = $manifest['global'];
		if (!array_key_exists('global_cta_override', $global)) {
			$errors[] = __('Manifest missing global.global_cta_override.', 'leadsforward-core');
		}
		$cta = isset($global['custom_global_cta']) && is_array($global['custom_global_cta']) ? $global['custom_global_cta'] : [];
		if (!array_key_exists('headline', $cta)) {
			$errors[] = __('Manifest missing global.custom_global_cta.headline.', 'leadsforward-core');
		}
		if (!array_key_exists('subheadline', $cta)) {
			$errors[] = __('Manifest missing global.custom_global_cta.subheadline.', 'leadsforward-core');
		}
	}
	if (!empty($manifest['services']) && is_array($manifest['services'])) {
		foreach ($manifest['services'] as $index => $item) {
			if (!is_array($item)) {
				$errors[] = sprintf(__('Service item %d must be an object.', 'leadsforward-core'), $index + 1);
				continue;
			}
			foreach (['title', 'slug', 'primary_keyword', 'secondary_keywords', 'custom_cta_context'] as $key) {
				if (!array_key_exists($key, $item)) {
					$errors[] = sprintf(__('Service item %d missing %s.', 'leadsforward-core'), $index + 1, $key);
				}
			}
		}
	}
	if (!empty($manifest['service_areas']) && is_array($manifest['service_areas'])) {
		foreach ($manifest['service_areas'] as $index => $item) {
			if (!is_array($item)) {
				$errors[] = sprintf(__('Service area item %d must be an object.', 'leadsforward-core'), $index + 1);
				continue;
			}
			foreach (['city', 'state', 'slug', 'primary_keyword'] as $key) {
				if (!array_key_exists($key, $item)) {
					$errors[] = sprintf(__('Service area item %d missing %s.', 'leadsforward-core'), $index + 1, $key);
				}
			}
		}
	}
	$normalized = lf_ai_studio_normalize_manifest($manifest);
	$business = $normalized['business'] ?? [];
	$address = is_array($business['address'] ?? null) ? $business['address'] : [];
	$homepage = is_array($normalized['homepage'] ?? null) ? $normalized['homepage'] : [];
	$services = $normalized['services'] ?? [];
	$areas = $normalized['service_areas'] ?? [];
	$business_name = trim((string) ($business['name'] ?? ''));
	$niche = trim((string) ($business['niche'] ?? ''));
	$city = trim((string) ($address['city'] ?? ''));
	$primary_city = trim((string) ($business['primary_city'] ?? ''));
	$primary_keyword = trim((string) ($homepage['primary_keyword'] ?? ''));
	if ($business_name === '') {
		$errors[] = __('Manifest missing business.name.', 'leadsforward-core');
	}
	if ($niche === '') {
		$errors[] = __('Manifest missing business.niche.', 'leadsforward-core');
	}
	if ($city === '') {
		$errors[] = __('Manifest missing business.address.city.', 'leadsforward-core');
	}
	if ($primary_city === '') {
		$errors[] = __('Manifest missing business.primary_city.', 'leadsforward-core');
	}
	if ($primary_keyword === '') {
		$errors[] = __('Manifest missing homepage.primary_keyword.', 'leadsforward-core');
	}
	if (!is_array($services) || empty($services)) {
		$errors[] = __('Manifest missing services array.', 'leadsforward-core');
	}
	if (!is_array($areas) || empty($areas)) {
		$errors[] = __('Manifest missing service_areas array.', 'leadsforward-core');
	}
	$service_slugs = [];
	if (!empty($services) && is_array($services)) {
		foreach ($services as $index => $item) {
			$normalized_item = lf_ai_studio_normalize_service_item($item);
			if ($normalized_item['slug'] === '' || $normalized_item['primary_keyword'] === '') {
				$errors[] = sprintf(__('Service item %d is missing slug or primary_keyword.', 'leadsforward-core'), $index + 1);
			}
			if ($normalized_item['slug'] !== '') {
				if (in_array($normalized_item['slug'], $service_slugs, true)) {
					$errors[] = sprintf(__('Duplicate service slug "%s".', 'leadsforward-core'), $normalized_item['slug']);
				}
				$service_slugs[] = $normalized_item['slug'];
			}
		}
	}
	$area_slugs = [];
	if (!empty($areas) && is_array($areas)) {
		foreach ($areas as $index => $item) {
			$normalized_item = lf_ai_studio_normalize_area_item($item);
			if ($normalized_item['slug'] === '' || $normalized_item['primary_keyword'] === '') {
				$errors[] = sprintf(__('Service area item %d is missing slug or primary_keyword.', 'leadsforward-core'), $index + 1);
			}
			if ($normalized_item['slug'] !== '') {
				if (in_array($normalized_item['slug'], $area_slugs, true)) {
					$errors[] = sprintf(__('Duplicate service area slug "%s".', 'leadsforward-core'), $normalized_item['slug']);
				}
				$area_slugs[] = $normalized_item['slug'];
			}
		}
	}
	return $errors;
}

function lf_ai_studio_validate_research_document(array $doc): array {
	$errors = [];
	$required_top = [
		'brand_positioning',
		'competitor_analysis',
		'conversion_strategy',
		'voice_guidelines',
		'seo_strategy',
		'faq_strategy',
		'image_strategy',
		'content_expansion_guidelines',
	];
	foreach ($required_top as $key) {
		if (!isset($doc[$key]) || !is_array($doc[$key])) {
			$errors[] = sprintf(__('Research document missing %s object.', 'leadsforward-core'), $key);
		}
	}
	$brand = isset($doc['brand_positioning']) && is_array($doc['brand_positioning']) ? $doc['brand_positioning'] : [];
	foreach (['market_angle', 'primary_differentiator', 'secondary_differentiators', 'authority_positioning', 'local_positioning_strategy'] as $key) {
		if (!array_key_exists($key, $brand)) {
			$errors[] = sprintf(__('Research document missing brand_positioning.%s.', 'leadsforward-core'), $key);
		} elseif ($key === 'secondary_differentiators' && !is_array($brand[$key])) {
			$errors[] = __('brand_positioning.secondary_differentiators must be an array.', 'leadsforward-core');
		}
	}
	if (isset($doc['competitor_analysis'])) {
		if (!is_array($doc['competitor_analysis'])) {
			$errors[] = __('competitor_analysis must be an array.', 'leadsforward-core');
		} else {
			foreach ($doc['competitor_analysis'] as $index => $entry) {
				if (!is_array($entry)) {
					$errors[] = sprintf(__('Competitor entry %d must be an object.', 'leadsforward-core'), $index + 1);
					continue;
				}
				foreach (['competitor_name', 'strengths', 'weaknesses', 'content_patterns', 'seo_patterns'] as $key) {
					if (!array_key_exists($key, $entry)) {
						$errors[] = sprintf(__('Competitor entry %d missing %s.', 'leadsforward-core'), $index + 1, $key);
					} elseif ($key !== 'competitor_name' && !is_array($entry[$key])) {
						$errors[] = sprintf(__('Competitor entry %d %s must be an array.', 'leadsforward-core'), $index + 1, $key);
					}
				}
			}
		}
	}
	$conversion = isset($doc['conversion_strategy']) && is_array($doc['conversion_strategy']) ? $doc['conversion_strategy'] : [];
	foreach (['primary_cta_style', 'emotional_drivers', 'trust_elements_required', 'risk_reduction_elements'] as $key) {
		if (!array_key_exists($key, $conversion)) {
			$errors[] = sprintf(__('Research document missing conversion_strategy.%s.', 'leadsforward-core'), $key);
		} elseif ($key !== 'primary_cta_style' && !is_array($conversion[$key])) {
			$errors[] = sprintf(__('conversion_strategy.%s must be an array.', 'leadsforward-core'), $key);
		}
	}
	$voice = isset($doc['voice_guidelines']) && is_array($doc['voice_guidelines']) ? $doc['voice_guidelines'] : [];
	foreach (['tone', 'sentence_style', 'avoid_phrases', 'preferred_phrases', 'reading_level_target'] as $key) {
		if (!array_key_exists($key, $voice)) {
			$errors[] = sprintf(__('Research document missing voice_guidelines.%s.', 'leadsforward-core'), $key);
		} elseif (in_array($key, ['avoid_phrases', 'preferred_phrases'], true) && !is_array($voice[$key])) {
			$errors[] = sprintf(__('voice_guidelines.%s must be an array.', 'leadsforward-core'), $key);
		}
	}
	$seo = isset($doc['seo_strategy']) && is_array($doc['seo_strategy']) ? $doc['seo_strategy'] : [];
	foreach (['primary_keyword_clusters', 'semantic_entities', 'supporting_topics', 'internal_linking_angles'] as $key) {
		if (!array_key_exists($key, $seo)) {
			$errors[] = sprintf(__('Research document missing seo_strategy.%s.', 'leadsforward-core'), $key);
		} elseif (!is_array($seo[$key])) {
			$errors[] = sprintf(__('seo_strategy.%s must be an array.', 'leadsforward-core'), $key);
		}
	}
	$faq = isset($doc['faq_strategy']) && is_array($doc['faq_strategy']) ? $doc['faq_strategy'] : [];
	foreach (['objection_clusters', 'high_intent_questions', 'authority_questions'] as $key) {
		if (!array_key_exists($key, $faq)) {
			$errors[] = sprintf(__('Research document missing faq_strategy.%s.', 'leadsforward-core'), $key);
		} elseif (!is_array($faq[$key])) {
			$errors[] = sprintf(__('faq_strategy.%s must be an array.', 'leadsforward-core'), $key);
		}
	}
	$image = isset($doc['image_strategy']) && is_array($doc['image_strategy']) ? $doc['image_strategy'] : [];
	foreach (['recommended_image_types', 'placement_guidelines', 'alt_text_style'] as $key) {
		if (!array_key_exists($key, $image)) {
			$errors[] = sprintf(__('Research document missing image_strategy.%s.', 'leadsforward-core'), $key);
		} elseif ($key !== 'alt_text_style' && !is_array($image[$key])) {
			$errors[] = sprintf(__('image_strategy.%s must be an array.', 'leadsforward-core'), $key);
		}
	}
	$expansion = isset($doc['content_expansion_guidelines']) && is_array($doc['content_expansion_guidelines']) ? $doc['content_expansion_guidelines'] : [];
	foreach (['homepage_depth_strategy', 'service_page_depth_strategy', 'service_area_localization_strategy'] as $key) {
		if (!array_key_exists($key, $expansion)) {
			$errors[] = sprintf(__('Research document missing content_expansion_guidelines.%s.', 'leadsforward-core'), $key);
		}
	}
	return $errors;
}

function lf_ai_studio_normalize_service_item($item): array {
	if (!is_array($item)) {
		$item = [];
	}
	$title = sanitize_text_field((string) ($item['title'] ?? $item['name'] ?? ''));
	$slug = sanitize_title((string) ($item['slug'] ?? ''));
	if ($slug === '' && $title !== '') {
		$slug = sanitize_title($title);
	}
	$primary = sanitize_text_field((string) ($item['primary_keyword'] ?? $item['keyword'] ?? ''));
	$secondary = $item['secondary_keywords'] ?? [];
	if (!is_array($secondary)) {
		$secondary = [];
	}
	$secondary = array_values(array_filter(array_map('sanitize_text_field', $secondary)));
	$cta_context = sanitize_text_field((string) ($item['custom_cta_context'] ?? ''));
	return [
		'title' => $title,
		'slug' => $slug,
		'primary_keyword' => $primary,
		'secondary_keywords' => $secondary,
		'custom_cta_context' => $cta_context,
	];
}

function lf_ai_studio_clean_service_title(string $title, string $location): string {
	$base = strtolower(trim($title));
	$location = trim($location);
	if ($location !== '') {
		$base = preg_replace('/\b' . preg_quote(strtolower($location), '/') . '\b/i', '', $base);
	}
	$base = preg_replace('/\bselect\b/i', '', $base);
	$base = preg_replace('/\bservices?\b/i', '', $base);
	$base = preg_replace('/\bcompany\b|\bcontractors?\b|\bexperts?\b/i', '', $base);
	$base = preg_replace('/\bnear me\b|\bnear\b/i', '', $base);
	$base = preg_replace('/\b(in|for)\b/i', '', $base);
	$base = trim(preg_replace('/\s+/', ' ', $base));
	if ($base === '') {
		$base = __('Service', 'leadsforward-core');
	}
	$clean = ucwords($base);
	return $clean;
}

function lf_ai_studio_normalize_area_item($item): array {
	if (!is_array($item)) {
		$item = [];
	}
	$city = sanitize_text_field((string) ($item['city'] ?? ''));
	$state = sanitize_text_field((string) ($item['state'] ?? ''));
	$slug = sanitize_title((string) ($item['slug'] ?? ''));
	if ($slug === '' && $city !== '') {
		$slug = sanitize_title(trim($city . ' ' . $state));
	}
	$primary = sanitize_text_field((string) ($item['primary_keyword'] ?? $item['keyword'] ?? ''));
	return [
		'city' => $city,
		'state' => $state,
		'slug' => $slug,
		'primary_keyword' => $primary,
	];
}

function lf_ai_studio_normalize_manifest(array $manifest): array {
	$business = isset($manifest['business']) && is_array($manifest['business']) ? $manifest['business'] : [];
	$site = isset($manifest['site']) && is_array($manifest['site']) ? $manifest['site'] : [];
	if (empty($business) && !empty($site)) {
		$business = [
			'name' => $site['business_name'] ?? '',
			'legal_name' => $site['legal_name'] ?? '',
			'phone' => $site['phone'] ?? '',
			'email' => $site['email'] ?? '',
			'address' => $site['address'] ?? [],
			'primary_city' => $site['primary_city'] ?? ($site['address']['city'] ?? ''),
			'niche' => $site['niche'] ?? '',
			'site_style' => $site['site_style'] ?? '',
			'variation_seed' => $site['variation_seed'] ?? '',
		];
	}
	$address = isset($business['address']) && is_array($business['address']) ? $business['address'] : [];
	$homepage = isset($manifest['homepage']) && is_array($manifest['homepage']) ? $manifest['homepage'] : [];
	$services = isset($manifest['services']) && is_array($manifest['services']) ? $manifest['services'] : [];
	$areas = isset($manifest['service_areas']) && is_array($manifest['service_areas']) ? $manifest['service_areas'] : [];
	$global = isset($manifest['global']) && is_array($manifest['global']) ? $manifest['global'] : [];
	$secondary = $homepage['secondary_keywords'] ?? [];
	if (!is_array($secondary)) {
		$secondary = [];
	}
	$location_label = sanitize_text_field((string) ($address['state'] ?? ''));
	if ($location_label === '') {
		$location_label = sanitize_text_field((string) ($business['primary_city'] ?? ($address['city'] ?? '')));
	}
	$normalized_services = [];
	foreach ($services as $item) {
		$normalized = lf_ai_studio_normalize_service_item($item);
		if ($normalized['title'] !== '') {
			$normalized['title'] = lf_ai_studio_clean_service_title($normalized['title'], $location_label);
			$normalized['slug'] = sanitize_title(str_replace(' in ', ' ', strtolower($normalized['title'])));
			if ($normalized['primary_keyword'] === '' || stripos($normalized['primary_keyword'], $normalized['title']) === false) {
				$normalized['primary_keyword'] = $normalized['title'];
			}
		}
		$normalized_services[] = $normalized;
	}
	return [
		'business' => [
			'name' => sanitize_text_field((string) ($business['name'] ?? '')),
			'legal_name' => sanitize_text_field((string) ($business['legal_name'] ?? '')),
			'phone' => sanitize_text_field((string) ($business['phone'] ?? '')),
			'email' => sanitize_text_field((string) ($business['email'] ?? '')),
			'address' => [
				'street' => sanitize_text_field((string) ($address['street'] ?? '')),
				'city' => sanitize_text_field((string) ($address['city'] ?? '')),
				'state' => sanitize_text_field((string) ($address['state'] ?? '')),
				'zip' => sanitize_text_field((string) ($address['zip'] ?? '')),
			],
			'primary_city' => sanitize_text_field((string) ($business['primary_city'] ?? ($address['city'] ?? ''))),
			'niche' => sanitize_text_field((string) ($business['niche'] ?? '')),
			'niche_slug' => sanitize_title((string) ($business['niche_slug'] ?? '')),
			'site_style' => sanitize_text_field((string) ($business['site_style'] ?? '')),
			'variation_seed' => sanitize_text_field((string) ($business['variation_seed'] ?? '')),
			'website_url' => esc_url_raw((string) ($business['website_url'] ?? '')),
			'hours' => sanitize_textarea_field((string) ($business['hours'] ?? '')),
			'category' => sanitize_text_field((string) ($business['category'] ?? '')),
			'place_id' => sanitize_text_field((string) ($business['place_id'] ?? '')),
			'place_name' => sanitize_text_field((string) ($business['place_name'] ?? '')),
			'gbp_url' => esc_url_raw((string) ($business['gbp_url'] ?? '')),
			'founding_year' => sanitize_text_field((string) ($business['founding_year'] ?? '')),
			'social' => [
				'facebook' => esc_url_raw((string) ($business['social']['facebook'] ?? '')),
				'instagram' => esc_url_raw((string) ($business['social']['instagram'] ?? '')),
				'youtube' => esc_url_raw((string) ($business['social']['youtube'] ?? '')),
				'linkedin' => esc_url_raw((string) ($business['social']['linkedin'] ?? '')),
				'tiktok' => esc_url_raw((string) ($business['social']['tiktok'] ?? '')),
				'x' => esc_url_raw((string) ($business['social']['x'] ?? '')),
			],
			'same_as' => array_values(array_filter(array_map('esc_url_raw', (array) ($business['same_as'] ?? [])))),
		],
		'homepage' => [
			'primary_keyword' => sanitize_text_field((string) ($homepage['primary_keyword'] ?? '')),
			'secondary_keywords' => array_values(array_filter(array_map('sanitize_text_field', $secondary))),
		],
		'services' => $normalized_services,
		'service_areas' => array_values(array_map('lf_ai_studio_normalize_area_item', $areas)),
		'global' => [
			'global_cta_override' => !empty($global['global_cta_override']),
			'custom_global_cta' => [
				'headline' => sanitize_text_field((string) ($global['custom_global_cta']['headline'] ?? '')),
				'subheadline' => sanitize_text_field((string) ($global['custom_global_cta']['subheadline'] ?? '')),
			],
		],
	];
}

function lf_ai_studio_manifest_business_entity(array $manifest, array $fallback = []): array {
	$business = is_array($manifest['business'] ?? null) ? $manifest['business'] : [];
	$address = is_array($business['address'] ?? null) ? $business['address'] : [];
	$address_parts = [
		'street' => (string) ($address['street'] ?? ''),
		'city' => (string) ($address['city'] ?? ''),
		'state' => (string) ($address['state'] ?? ''),
		'zip' => (string) ($address['zip'] ?? ''),
	];
	$address_full = function_exists('lf_business_entity_address_string')
		? lf_business_entity_address_string($address_parts)
		: trim(implode(', ', array_filter($address_parts)));
	$phone = (string) ($business['phone'] ?? '');
	$entity = is_array($fallback) ? $fallback : [];
	$entity['name'] = (string) ($business['name'] ?? '');
	$entity['legal_name'] = (string) ($business['legal_name'] ?? '');
	$entity['phone_primary'] = $phone;
	$entity['phone_tracking'] = '';
	$entity['phone_display_pref'] = 'primary';
	$entity['phone_display'] = $phone;
	$entity['email'] = (string) ($business['email'] ?? '');
	$entity['address_parts'] = $address_parts;
	$entity['address'] = $address_full;
	$entity['niche'] = (string) ($business['niche'] ?? '');
	if (!empty($business['category'])) {
		$entity['category'] = (string) $business['category'];
	}
	if (!empty($business['hours'])) {
		$entity['hours'] = (string) $business['hours'];
	}
	if (!empty($business['gbp_url'])) {
		$entity['gbp_url'] = (string) $business['gbp_url'];
	}
	if (!empty($business['founding_year'])) {
		$entity['founding_year'] = (string) $business['founding_year'];
	}
	if (!empty($business['place_id'])) {
		$entity['place_id'] = (string) $business['place_id'];
	}
	if (!empty($business['place_name'])) {
		$entity['place_name'] = (string) $business['place_name'];
	}
	if (!empty($business['social']) && is_array($business['social'])) {
		$entity_social = is_array($entity['social'] ?? null) ? $entity['social'] : [];
		$entity['social'] = array_merge($entity_social, array_filter($business['social']));
	}
	$incoming_same_as = [];
	if (!empty($business['same_as']) && is_array($business['same_as'])) {
		$incoming_same_as = $business['same_as'];
	}
	if (!empty($business['website_url'])) {
		$incoming_same_as[] = (string) $business['website_url'];
	}
	if (!empty($incoming_same_as)) {
		$existing = is_array($entity['same_as'] ?? null) ? $entity['same_as'] : [];
		$entity['same_as'] = array_values(array_unique(array_filter(array_merge($existing, $incoming_same_as))));
	}
	return $entity;
}

function lf_ai_studio_manifest_hash(array $manifest): string {
	$normalized = lf_ai_studio_normalize_manifest($manifest);
	return hash('sha256', wp_json_encode($normalized));
}

function lf_ai_studio_manifest_keyword_map(array $manifest, string $key): array {
	$items = isset($manifest[$key]) && is_array($manifest[$key]) ? $manifest[$key] : [];
	$map = [];
	foreach ($items as $item) {
		$normalized = ($key === 'services')
			? lf_ai_studio_normalize_service_item($item)
			: lf_ai_studio_normalize_area_item($item);
		if ($normalized['slug'] !== '') {
			$map[$normalized['slug']] = $normalized['primary_keyword'];
		}
	}
	return $map;
}

function lf_ai_studio_sync_manifest_posts(array $manifest): void {
	$services = isset($manifest['services']) && is_array($manifest['services']) ? $manifest['services'] : [];
	foreach ($services as $item) {
		$normalized = lf_ai_studio_normalize_service_item($item);
		if ($normalized['slug'] === '') {
			continue;
		}
		$existing = get_page_by_path($normalized['slug'], OBJECT, 'lf_service');
		if ($existing instanceof \WP_Post) {
			$title = $normalized['title'] !== '' ? $normalized['title'] : $existing->post_title;
			wp_update_post([
				'ID' => $existing->ID,
				'post_title' => $title,
				'post_name' => $normalized['slug'],
			]);
		} else {
			wp_insert_post([
				'post_type' => 'lf_service',
				'post_status' => 'publish',
				'post_title' => $normalized['title'] !== '' ? $normalized['title'] : $normalized['slug'],
				'post_name' => $normalized['slug'],
				'post_author' => get_current_user_id(),
			]);
		}
	}
	$areas = isset($manifest['service_areas']) && is_array($manifest['service_areas']) ? $manifest['service_areas'] : [];
	foreach ($areas as $item) {
		$normalized = lf_ai_studio_normalize_area_item($item);
		if ($normalized['slug'] === '') {
			continue;
		}
		$existing = get_page_by_path($normalized['slug'], OBJECT, 'lf_service_area');
		if ($existing instanceof \WP_Post) {
			$title = trim($normalized['city'] . ($normalized['state'] ? ', ' . $normalized['state'] : ''));
			if ($title === '') {
				$title = $existing->post_title;
			}
			wp_update_post([
				'ID' => $existing->ID,
				'post_title' => $title,
				'post_name' => $normalized['slug'],
			]);
		} else {
			$title = trim($normalized['city'] . ($normalized['state'] ? ', ' . $normalized['state'] : ''));
			if ($title === '') {
				$title = $normalized['slug'];
			}
			wp_insert_post([
				'post_type' => 'lf_service_area',
				'post_status' => 'publish',
				'post_title' => $title,
				'post_name' => $normalized['slug'],
				'post_author' => get_current_user_id(),
			]);
		}
	}
}

function lf_ai_studio_llm_system_message(): string {
	return implode("\n", [
		'Return JSON only. No markdown, no commentary.',
		'HTML is allowed only inside richtext fields (use <p>, <ul>, <li>, <a>).',
		'Use only allowed_field_keys. Do not invent fields.',
		'If section.intent, section.purpose, field_labels, or field_types are provided in the blueprint, use them to shape content appropriately.',
		'Headlines: never use dash or hyphen separators. Sentence case or title case only. No trailing punctuation unless a question mark. Hero headline max 12 words.',
		'Benefits: 15-35 words each, max 2 sentences per benefit. No dash separators in benefit titles.',
		'Internal linking: include 1-2 internal links only in richtext/body fields using internal_links list. Never add links in headlines, subheadlines, intros, trust bars, chips, bullets, labels, or CTA button text.',
		'SERP intent alignment: service/service-area pages should read transactional+local, and blog posts should read informational. Keep metadata-aligned language in headlines and first paragraph.',
		'You may receive research_context. Use it for positioning, differentiation, SEO entity strategy, tone alignment, FAQ angle selection, and authority modeling. Do NOT copy research text verbatim. Apply strategically.',
		'Content separation by page type:',
		'Homepage: broad positioning; do not reuse service or area copy verbatim.',
		'Services overview: broad authority content; no detailed process repetition; avoid excessive city modifiers.',
		'Service page: deep service-specific content; do not reuse homepage hero copy; reference the exact service.',
		'Service areas overview: broad coverage explanation; no detailed local signals per city.',
		'Service area page: localized content; do not repeat service overview intro verbatim.',
		'Blog post rules: when page_intent is blog_post, write a complete long-form article suitable for publication with substantial depth, practical guidance, and concrete homeowner takeaways.',
		'If blog_post_type is provided in blueprint, shape the article to that format (pillar, how_to, cost, comparison, checklist, local_guide, faq_roundup) while preserving factual accuracy for business and location context.',
		'Never reuse sentences across page types.',
		'Minimum total content: every page must contain at least 1000 words of unique body copy across all text/richtext fields. Expand each section with depth; do not pad with fluff or repetition.',
		'Never output machine tokens (PRIMARY_KEYWORD, BUSINESS_NAME, CITY_REGION, NICHE_TOKEN) or bracket placeholders like [Your City] in customer-facing fields—always substitute real business facts from the payload.',
		'FAQ strategy: create one global pool of 8-12 evergreen FAQs. Reuse across pages unless contextual variation is required. Homepage shows 5. Service pages show 4-6 relevant. Service area pages show 3-5 localized. Overview pages optionally 3-4.',
		'CTA strategy: treat the homepage CTA section as the canonical global CTA copy. For each page, add exactly one contextual sentence in cta_subheadline_secondary. Never duplicate CTA sentences across pages.',
		'CTA button labels: keep 2-5 words, max 32 characters, no trailing punctuation.',
	]);
}

function lf_ai_studio_faq_strategy(): array {
	return [
		'global_pool' => ['min' => 8, 'max' => 12],
		'homepage' => ['count' => 5],
		'service' => ['min' => 4, 'max' => 6],
		'service_area' => ['min' => 3, 'max' => 5],
		'services_overview' => ['min' => 3, 'max' => 4],
		'service_areas_overview' => ['min' => 3, 'max' => 4],
		'reuse_policy' => 'Reuse global pool whenever possible; only vary for context.',
	];
}

function lf_ai_studio_cta_strategy(): array {
	return [
		'global_cta' => [
			'write_once' => true,
			'store' => 'options',
			'fields' => ['cta_headline', 'cta_subheadline', 'cta_primary_override', 'cta_secondary_override'],
		],
		'page_context_sentence' => [
			'field' => 'cta_subheadline_secondary',
			'sentences' => 1,
		],
		'no_exact_duplicates' => true,
	];
}

function lf_ai_studio_faq_target_range(string $page): array {
	switch ($page) {
		case 'homepage':
			return ['min' => 5, 'max' => 5];
		case 'service':
			return ['min' => 4, 'max' => 6];
		case 'service_area':
			return ['min' => 3, 'max' => 5];
		case 'services_overview':
		case 'service_areas_overview':
			return ['min' => 3, 'max' => 4];
		default:
			return [];
	}
}

function lf_ai_studio_normalize_section_type(string $section_type): string {
	switch ($section_type) {
		case 'content_image_a':
		case 'content_image_c':
			return 'content_image';
		case 'image_content_b':
			return 'image_content';
		default:
			return $section_type;
	}
}

// Long-form density expansion – Step 3
function lf_ai_studio_section_length_targets(string $section_type, string $page = ''): array {
	$type = lf_ai_studio_normalize_section_type($section_type);
	switch ($type) {
		case 'hero':
			return [
				'headline_subheadline_words' => ['min' => 20, 'max' => 40],
				'hero_headline_words' => ['max' => 12],
			];
		case 'benefits':
			return [
				'min_items' => 5,
				'item_words' => ['min' => 15, 'max' => 35],
				'item_sentences_max' => 2,
				'title_no_dashes' => true,
			];
		case 'process':
			return ['min_items' => 4, 'item_words' => ['min' => 40, 'max' => 80]];
		case 'service_details':
			return ['body_words' => ['min' => 500, 'max' => 800]];
		case 'content_image':
		case 'image_content':
			if ($page === 'homepage') {
				return ['body_words' => ['min' => 400, 'max' => 700]];
			}
			if (in_array($page, ['service', 'service_area'], true)) {
				return ['body_words' => ['min' => 350, 'max' => 600]];
			}
			return ['body_words' => ['min' => 300, 'max' => 600]];
		case 'faq_accordion':
			return ['min_items' => 5, 'max_items' => 8, 'answer_words' => ['min' => 80, 'max' => 150]];
		default:
			return [];
	}
}

function lf_homepage_keywords(): array {
	$raw = get_option('lf_homepage_keywords', []);
	if (!is_array($raw)) {
		$raw = [];
	}
	$primary = sanitize_text_field((string) ($raw['primary'] ?? ''));
	$secondary = $raw['secondary'] ?? [];
	if (!is_array($secondary)) {
		$secondary = [];
	}
	$secondary = array_values(array_filter(array_map('sanitize_text_field', $secondary)));
	return [
		'primary' => $primary,
		'secondary' => $secondary,
	];
}

function lf_ai_studio_build_homepage_blueprint(): array {
	$manifest = lf_ai_studio_get_manifest();
	$use_manifest = !empty($manifest);
	$entity = [];
	$business_name = '';
	$niche = '';
	$city = '';
	$keywords = ['primary' => '', 'secondary' => []];
	if ($use_manifest) {
		$manifest_errors = lf_ai_studio_validate_manifest($manifest);
		if (!empty($manifest_errors)) {
			update_option('lf_ai_studio_manifest_errors', $manifest_errors, false);
			return ['error' => __('Manifest validation failed. Fix the uploaded manifest to continue.', 'leadsforward-core')];
		}
		$manifest = lf_ai_studio_normalize_manifest($manifest);
		$entity = lf_ai_studio_manifest_business_entity($manifest, $entity);
		$business_name = (string) ($manifest['business']['name'] ?? '');
		$niche = (string) ($manifest['business']['niche'] ?? '');
		$city = (string) ($manifest['business']['primary_city'] ?? ($manifest['business']['address']['city'] ?? ''));
		$keywords = [
			'primary' => (string) ($manifest['homepage']['primary_keyword'] ?? ''),
			'secondary' => $manifest['homepage']['secondary_keywords'] ?? [],
		];
	} else {
		$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
		$business_name = is_array($entity) ? (string) ($entity['name'] ?? get_bloginfo('name')) : get_bloginfo('name');
		$niche = (string) get_option(LF_HOMEPAGE_NICHE_OPTION, 'general');
		$city = (string) get_option('lf_homepage_city', '');
		if ($city === '' && is_array($entity)) {
			$city = (string) ($entity['address_parts']['city'] ?? '');
			if ($city === '' && !empty($entity['service_areas'][0])) {
				$city = (string) $entity['service_areas'][0];
			}
		}
		$keywords = lf_homepage_keywords();
	}
	$config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
	$order = function_exists('lf_homepage_controller_order') ? lf_homepage_controller_order() : [];
	if ($use_manifest && (empty($config) || empty($order)) && function_exists('lf_homepage_default_config')) {
		$config = lf_homepage_default_config($niche !== '' ? $niche : null);
		$order = function_exists('lf_homepage_controller_order') ? lf_homepage_controller_order() : array_keys($config);
		if (empty($order)) {
			$order = array_keys($config);
		}
	}
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$hero_variant = isset($config['hero']['variant']) ? (string) $config['hero']['variant'] : 'default';
	$variation_seed = lf_homepage_variation_seed();
	$front_id = (int) get_option('page_on_front');
	$page_title = $front_id ? get_the_title($front_id) : '';
	$page_title = is_string($page_title) ? $page_title : '';
	$page_slug = $front_id ? (string) get_post_field('post_name', $front_id) : 'home';
	$page_excerpt = $front_id ? (string) get_post_field('post_excerpt', $front_id) : '';

	$sections = [];
	foreach ($order as $section_id) {
		$section = $config[$section_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$schema = $registry[$section_id] ?? [];
		$allowed = lf_ai_studio_homepage_allowed_fields($section_id, $schema);
		$intent = (string) ($section['section_intent'] ?? '');
		$purpose = (string) ($section['section_purpose'] ?? '');
		$sections[] = [
			'section_id' => $section_id,
			'enabled' => true,
			'allowed_fields' => $allowed,
			'intent' => $intent,
			'purpose' => $purpose,
		];
	}
	$blueprint_sections = [];
	foreach ($order as $section_id) {
		$schema = $registry[$section_id] ?? null;
		if (!is_array($schema)) {
			continue;
		}
		$section = $config[$section_id] ?? [];
		$allowed_keys = lf_ai_studio_homepage_allowed_field_keys($section_id, $schema);
		$field_meta = lf_ai_studio_section_field_meta($schema, $allowed_keys);
		$blueprint_sections[] = [
			'section_id' => $section_id,
			'section_type' => lf_ai_studio_homepage_section_type($section_id),
			'intent' => (string) ($section['section_intent'] ?? ''),
			'purpose' => (string) ($section['section_purpose'] ?? ''),
			'section_label' => (string) ($schema['label'] ?? ''),
			// Long-form density expansion – Step 3
			'length_targets' => lf_ai_studio_section_length_targets(lf_ai_studio_homepage_section_type($section_id), 'homepage'),
			'allowed_field_keys' => $allowed_keys,
			'field_labels' => $field_meta['labels'],
			'field_types' => $field_meta['types'],
		];
	}
	if (empty($order) || empty($blueprint_sections)) {
		if ($use_manifest) {
			$fallback = lf_ai_studio_build_default_homepage_blueprint($manifest);
			if (!empty($fallback)) {
				$blueprint_sections = $fallback['sections'] ?? [];
				$order = $fallback['order'] ?? $order;
				$hero_variant = (string) ($fallback['hero_variant'] ?? $hero_variant);
				if (function_exists('lf_homepage_default_config')) {
					$config = lf_homepage_default_config($niche !== '' ? $niche : null);
				}
			}
		}
		if (empty($order) || empty($blueprint_sections)) {
			return ['error' => __('Homepage blueprint could not be built. Check homepage configuration.', 'leadsforward-core')];
		}
	}
	$research_context = lf_ai_studio_build_research_context();

	$base = [
		'variation_seed' => $variation_seed,
		'business_name' => $business_name,
		'business_entity' => $entity,
		'niche' => $niche,
		'city_region' => $city,
		'keywords' => $keywords,
		'system_message' => lf_ai_studio_llm_system_message(),
		'faq_strategy' => lf_ai_studio_faq_strategy(),
		'cta_strategy' => lf_ai_studio_cta_strategy(),
		'blueprint' => [
			'page' => 'homepage',
			'page_intent' => 'homepage',
			'page_title' => $page_title,
			'page_slug' => $page_slug,
			'page_excerpt' => $page_excerpt,
			'hero_variant' => $hero_variant,
			'sections' => $blueprint_sections,
			'order' => $order,
			'services' => lf_ai_studio_homepage_service_catalog(),
			'service_areas' => lf_ai_studio_homepage_area_catalog(),
			'faqs' => lf_ai_studio_homepage_faq_catalog(),
			'faq_target_count' => lf_ai_studio_homepage_faq_target_count($config),
			'faq_target_range' => lf_ai_studio_faq_target_range('homepage'),
		],
	];
	if (!empty($research_context)) {
		$base['blueprint']['research_context'] = $research_context;
	}
	$request_id = lf_ai_studio_homepage_request_id($base);

	$base['blueprint']['request_id'] = $request_id;
	return ['request_id' => $request_id] + $base;
}

function lf_ai_studio_build_default_homepage_blueprint(array $manifest = []): array {
	$niche = '';
	if (!empty($manifest) && is_array($manifest)) {
		$normalized = lf_ai_studio_normalize_manifest($manifest);
		$niche = (string) ($normalized['business']['niche'] ?? '');
	}
	$config = function_exists('lf_homepage_default_config')
		? lf_homepage_default_config($niche !== '' ? $niche : null)
		: [];
	$order = function_exists('lf_homepage_controller_order') ? lf_homepage_controller_order() : array_keys($config);
	if (empty($order)) {
		$order = array_keys($config);
	}
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$hero_variant = isset($config['hero']['variant']) ? (string) $config['hero']['variant'] : 'default';
	$blueprint_sections = [];
	$front_id = (int) get_option('page_on_front');
	$page_title = $front_id ? get_the_title($front_id) : '';
	$page_title = is_string($page_title) ? $page_title : '';
	$page_slug = $front_id ? (string) get_post_field('post_name', $front_id) : 'home';
	$page_excerpt = $front_id ? (string) get_post_field('post_excerpt', $front_id) : '';
	foreach ($order as $section_id) {
		$schema = $registry[$section_id] ?? null;
		if (!is_array($schema)) {
			continue;
		}
		$section = $config[$section_id] ?? [];
		$allowed_keys = lf_ai_studio_homepage_allowed_field_keys($section_id, $schema);
		$field_meta = lf_ai_studio_section_field_meta($schema, $allowed_keys);
		$blueprint_sections[] = [
			'section_id' => $section_id,
			'section_type' => lf_ai_studio_homepage_section_type($section_id),
			'intent' => (string) ($section['section_intent'] ?? ''),
			'purpose' => (string) ($section['section_purpose'] ?? ''),
			'section_label' => (string) ($schema['label'] ?? ''),
			'length_targets' => lf_ai_studio_section_length_targets(lf_ai_studio_homepage_section_type($section_id), 'homepage'),
			'allowed_field_keys' => $allowed_keys,
			'field_labels' => $field_meta['labels'],
			'field_types' => $field_meta['types'],
		];
	}
	if (empty($order) || empty($blueprint_sections)) {
		return [];
	}
	return [
		'page' => 'homepage',
		'page_intent' => 'homepage',
		'page_title' => $page_title,
		'page_slug' => $page_slug,
		'page_excerpt' => $page_excerpt,
		'hero_variant' => $hero_variant,
		'sections' => $blueprint_sections,
		'order' => $order,
		'services' => lf_ai_studio_homepage_service_catalog(),
		'service_areas' => lf_ai_studio_homepage_area_catalog(),
		'faqs' => lf_ai_studio_homepage_faq_catalog(),
		'faq_target_count' => lf_ai_studio_homepage_faq_target_count($config),
		'faq_target_range' => lf_ai_studio_faq_target_range('homepage'),
	];
}

function lf_ai_studio_homepage_allowed_field_keys(string $section_id, array $schema): array {
	$fields = $schema['fields'] ?? [];
	$allowed_types = ['text', 'textarea', 'list', 'richtext'];
	// Long-form density expansion – Step 3
	$blocked_keys = [
		'section_background',
		'variant',
		'section_intent',
		'section_purpose',
		'service_intro_columns',
		'service_intro_max_items',
		'service_intro_show_images',
		'service_details_layout',
		'service_details_media_mode',
		'service_details_media_embed',
		'service_details_media_video_url',
		'service_details_media_image_id',
		'cta_primary_enabled',
		'cta_secondary_enabled',
		'cta_primary_action',
		'cta_secondary_action',
		'cta_primary_url',
		'cta_secondary_url',
		'cta_ghl_override',
		'hero_media',
		'hero_image_id',
		'image_id',
		'image_position',
		'trust_max_items',
		'trust_rating',
		'trust_review_count',
		'posts_per_page',
		'faq_max_items',
		'nearby_areas_max',
		'icon_enabled',
		'icon_slug',
		'icon_position',
		'icon_size',
		'icon_color',
	];
	$out = [];
	foreach ($fields as $field) {
		$key = $field['key'] ?? '';
		$type = $field['type'] ?? 'text';
		if ($key === '' || in_array($key, $blocked_keys, true)) {
			continue;
		}
		if (!in_array($type, $allowed_types, true)) {
			continue;
		}
		$out[] = $key;
	}
	return $out;
}

function lf_ai_studio_section_field_meta(array $schema, array $allowed_keys): array {
	$labels = [];
	$types = [];
	$field_defs = $schema['fields'] ?? [];
	if (!is_array($field_defs) || empty($allowed_keys)) {
		return ['labels' => [], 'types' => []];
	}
	foreach ($field_defs as $field) {
		if (!is_array($field)) {
			continue;
		}
		$key = (string) ($field['key'] ?? '');
		if ($key === '' || !in_array($key, $allowed_keys, true)) {
			continue;
		}
		$label = $field['label'] ?? '';
		if (is_string($label) && $label !== '') {
			$labels[$key] = $label;
		}
		$type = $field['type'] ?? '';
		if (is_string($type) && $type !== '') {
			$types[$key] = $type;
		}
	}
	return [
		'labels' => $labels,
		'types' => $types,
	];
}

function lf_ai_studio_homepage_allowed_fields(string $section_id, array $schema): array {
	$out = [];
	foreach (lf_ai_studio_homepage_allowed_field_keys($section_id, $schema) as $key) {
		$out[] = $section_id . '.' . $key;
	}
	return $out;
}

function lf_ai_studio_homepage_section_type(string $section_id): string {
	switch ($section_id) {
		case 'content_image_a':
		case 'content_image_c':
			return 'content_image';
		case 'image_content_b':
			return 'image_content';
		default:
			return $section_id;
	}
}

function lf_ai_studio_homepage_service_catalog(): array {
	$manifest = lf_ai_studio_get_manifest();
	if (!empty($manifest) && is_array($manifest)) {
		$items = $manifest['services'] ?? [];
		if (is_array($items) && !empty($items)) {
			$out = [];
			foreach ($items as $item) {
				$normalized = lf_ai_studio_normalize_service_item($item);
				if ($normalized['slug'] === '') {
					continue;
				}
				$post = get_page_by_path($normalized['slug'], OBJECT, 'lf_service');
				$out[] = [
					'id' => $post instanceof \WP_Post ? $post->ID : 0,
					'slug' => $normalized['slug'],
					'title' => $normalized['title'] !== '' ? $normalized['title'] : $normalized['slug'],
					'short_desc' => '',
					'primary_keyword' => $normalized['primary_keyword'],
					'secondary_keywords' => $normalized['secondary_keywords'],
					'custom_cta_context' => $normalized['custom_cta_context'],
				];
			}
			return $out;
		}
	}
	$services = get_posts([
		'post_type' => 'lf_service',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
	]);
	$out = [];
	foreach ($services as $service) {
		if (!$service instanceof \WP_Post) {
			continue;
		}
		$out[] = [
			'id' => $service->ID,
			'slug' => $service->post_name,
			'title' => $service->post_title,
			'short_desc' => function_exists('get_field') ? (string) get_field('lf_service_short_desc', $service->ID) : '',
		];
	}
	return $out;
}

function lf_ai_studio_homepage_area_catalog(): array {
	$manifest = lf_ai_studio_get_manifest();
	if (!empty($manifest) && is_array($manifest)) {
		$items = $manifest['service_areas'] ?? [];
		if (is_array($items) && !empty($items)) {
			$out = [];
			foreach ($items as $item) {
				$normalized = lf_ai_studio_normalize_area_item($item);
				if ($normalized['slug'] === '') {
					continue;
				}
				$post = get_page_by_path($normalized['slug'], OBJECT, 'lf_service_area');
				$title = trim($normalized['city'] . ($normalized['state'] ? ', ' . $normalized['state'] : ''));
				if ($title === '') {
					$title = $normalized['slug'];
				}
				$out[] = [
					'id' => $post instanceof \WP_Post ? $post->ID : 0,
					'slug' => $normalized['slug'],
					'title' => $title,
					'primary_keyword' => $normalized['primary_keyword'],
				];
			}
			return $out;
		}
	}
	$areas = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
	]);
	$out = [];
	foreach ($areas as $area) {
		if (!$area instanceof \WP_Post) {
			continue;
		}
		$out[] = [
			'id' => $area->ID,
			'slug' => $area->post_name,
			'title' => $area->post_title,
		];
	}
	return $out;
}

function lf_ai_studio_homepage_faq_target_count(array $config): int {
	return 5;
}

function lf_ai_studio_homepage_faq_catalog(): array {
	$faqs = get_posts([
		'post_type' => 'lf_faq',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
	]);
	$out = [];
	foreach ($faqs as $faq) {
		if (!$faq instanceof \WP_Post) {
			continue;
		}
		$question = function_exists('get_field') ? (string) get_field('lf_faq_question', $faq->ID) : '';
		$answer = function_exists('get_field') ? (string) get_field('lf_faq_answer', $faq->ID) : '';
		if ($question === '') {
			$question = (string) $faq->post_title;
		}
		if ($answer === '') {
			$answer = (string) $faq->post_content;
		}
		$out[] = [
			'id' => $faq->ID,
			'question' => $question,
			'answer' => $answer,
		];
	}
	return $out;
}

function lf_ai_studio_build_post_blueprint(\WP_Post $post, string $page, string $page_intent, string $primary_keyword = ''): array {
	$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
	if ($context === '') {
		return [];
	}
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$config = lf_pb_get_post_config($post->ID, $context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$out_sections = [];
	$out_order = [];
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$type = $section['type'] ?? '';
		if ($type === '' || !isset($registry[$type])) {
			continue;
		}
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$allowed_keys = lf_ai_studio_homepage_allowed_field_keys($type, $registry[$type]);
		$field_meta = lf_ai_studio_section_field_meta($registry[$type], $allowed_keys);
		$out_sections[] = [
			'section_id' => $instance_id,
			'section_type' => $type,
			'intent' => (string) ($settings['section_intent'] ?? ''),
			'purpose' => (string) ($settings['section_purpose'] ?? ''),
			'section_label' => (string) ($registry[$type]['label'] ?? ''),
			// Long-form density expansion – Step 3
			'length_targets' => lf_ai_studio_section_length_targets($type, $page),
			'allowed_field_keys' => $allowed_keys,
			'field_labels' => $field_meta['labels'],
			'field_types' => $field_meta['types'],
		];
		$out_order[] = $instance_id;
	}
	$page_title = get_the_title($post);
	$page_title = is_string($page_title) ? $page_title : '';
	$blueprint = [
		'page' => $page,
		'post_id' => $post->ID,
		'page_intent' => $page_intent,
		'primary_keyword' => $primary_keyword,
		'page_title' => $page_title,
		'page_slug' => (string) ($post->post_name ?? ''),
		'page_excerpt' => (string) get_post_field('post_excerpt', $post->ID),
		'sections' => $out_sections,
		'order' => $out_order,
		'faq_target_range' => lf_ai_studio_faq_target_range($page),
	];
	if ($post->post_type === 'page') {
		$blueprint['page_template'] = (string) get_page_template_slug($post->ID);
	}
	if ($page === 'service') {
		$blueprint['service'] = [
			'title' => (string) ($post->post_title ?? ''),
			'slug' => (string) ($post->post_name ?? ''),
			'short_description' => (string) get_post_meta((int) $post->ID, 'lf_service_short_desc', true),
		];
	}
	if ($page === 'service_area') {
		$service_ids = get_post_meta((int) $post->ID, 'lf_service_area_services', true);
		if (!is_array($service_ids)) {
			$service_ids = [];
		}
		$service_titles = [];
		foreach ($service_ids as $service_id) {
			$service_id = (int) $service_id;
			if ($service_id > 0) {
				$title = get_the_title($service_id);
				if (is_string($title) && $title !== '') {
					$service_titles[] = $title;
				}
			}
		}
		$blueprint['service_area'] = [
			'city' => (string) ($post->post_title ?? ''),
			'state' => (string) get_post_meta((int) $post->ID, 'lf_service_area_state', true),
			'slug' => (string) ($post->post_name ?? ''),
			'services' => $service_titles,
		];
	}
	if ($page === 'post') {
		$blog_post_type = (string) get_post_meta((int) $post->ID, 'lf_ai_post_format', true);
		if ($blog_post_type !== '') {
			$blueprint['blog_post_type'] = $blog_post_type;
		}
	}
	$research_context = lf_ai_studio_build_research_context();
	if (!empty($research_context)) {
		$blueprint['research_context'] = $research_context;
	}
	return $blueprint;
}

function lf_ai_studio_get_generation_scope(array $manifest, bool $respect_manifest_scope = true): array {
	$default = [
		'homepage' => true,
		'services' => true,
		'service_areas' => true,
		'core_pages' => true,
		'blog_posts' => true,
		'projects' => true,
	];
	$scope = [
		'homepage' => get_option('lf_ai_gen_homepage', '1') === '1',
		'services' => get_option('lf_ai_gen_services', '1') === '1',
		'service_areas' => get_option('lf_ai_gen_service_areas', '1') === '1',
		'core_pages' => get_option('lf_ai_gen_core_pages', '1') === '1',
		'blog_posts' => get_option('lf_ai_gen_blog_posts', '1') === '1',
		'projects' => get_option('lf_ai_gen_projects', '1') === '1',
	];
	foreach ($default as $key => $val) {
		if (!isset($scope[$key])) {
			$scope[$key] = $val;
		}
	}
	$manifest_scope = (string) ($manifest['generation_scope'] ?? '');
	if ($respect_manifest_scope && $manifest_scope === 'homepage_only') {
		$scope = [
			'homepage' => true,
			'services' => false,
			'service_areas' => false,
			'core_pages' => false,
			'blog_posts' => false,
			'projects' => false,
		];
	}
	return $scope;
}

function lf_ai_studio_ensure_core_page_sections(array $manifest = [], bool $force_reseed_all = false): void {
	if (!function_exists('lf_wizard_seed_page_pb_config')) {
		return;
	}
	$data = [];
	if (!empty($manifest)) {
		$data = lf_ai_studio_manifest_to_setup_data($manifest);
	}
	if (empty($data) && function_exists('lf_wizard_data_from_entity')) {
		try {
			$entity_data = lf_wizard_data_from_entity();
			$data = is_array($entity_data) ? $entity_data : [];
		} catch (\Throwable $e) {
			$data = [];
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('LF AI Studio: failed reading wizard entity data: ' . $e->getMessage());
			}
		}
	}
	$niche_slug = (string) ($data['niche_slug'] ?? get_option('lf_homepage_niche_slug', 'general'));
	$niche = function_exists('lf_get_niche') ? lf_get_niche($niche_slug) : null;
	if (!$niche && function_exists('lf_get_niche')) {
		$niche = lf_get_niche('general');
	}
	if ($force_reseed_all && function_exists('lf_homepage_apply_niche_config')) {
		lf_homepage_apply_niche_config($niche_slug !== '' ? $niche_slug : 'general', $data);
		if (defined('LF_HOMEPAGE_ORDER_OPTION') && function_exists('lf_homepage_default_order')) {
			update_option(LF_HOMEPAGE_ORDER_OPTION, lf_homepage_default_order(), true);
		}
		if (defined('LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION')) {
			update_option(LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION, false, true);
		}
		// Preserve frontend editor overrides during reseed.
	}
	$slugs = function_exists('lf_wizard_required_page_slugs')
		? lf_wizard_required_page_slugs()
		: ['about-us', 'our-services', 'service-areas', 'reviews', 'blog', 'sitemap', 'contact', 'privacy-policy', 'terms-of-service', 'thank-you'];
	$created_pages = [];
	foreach ($slugs as $slug) {
		$page = get_page_by_path($slug);
		if ($page instanceof \WP_Post) {
			$created_pages[$slug] = $page->ID;
		}
	}
	if (empty($created_pages['service-areas'])) {
		$legacy_areas = get_page_by_path('our-service-areas');
		if ($legacy_areas instanceof \WP_Post) {
			wp_update_post([
				'ID' => $legacy_areas->ID,
				'post_name' => 'service-areas',
				'post_title' => __('Service Areas', 'leadsforward-core'),
			]);
			$created_pages['service-areas'] = $legacy_areas->ID;
		}
	}
	foreach ($created_pages as $slug => $page_id) {
		if ($slug === 'home') {
			continue;
		}
		$existing_config = get_post_meta($page_id, LF_PB_META_KEY, true);
		$is_minimal = function_exists('lf_wizard_is_minimal_pb_config')
			? lf_wizard_is_minimal_pb_config($existing_config)
			: (!is_array($existing_config) || empty($existing_config));
		$force_reseed = $force_reseed_all;
		if ($slug === 'about-us') {
			$force_reseed = $force_reseed || !lf_ai_studio_config_has_section_types($existing_config, ['content_image', 'image_content_b', 'benefits', 'process']);
		}
		if ($slug === 'our-services') {
			$has_new = lf_ai_studio_config_has_section_types($existing_config, ['service_intro', 'faq_accordion', 'cta']);
			$has_old = lf_ai_studio_config_has_section_types($existing_config, ['content_centered', 'process']);
			$force_reseed = $force_reseed || !$has_new || $has_old;
		}
		if ($slug === 'service-areas') {
			$has_new = lf_ai_studio_config_has_section_types($existing_config, ['service_areas', 'faq_accordion', 'cta']);
			$has_old = lf_ai_studio_config_has_section_types($existing_config, ['nearby_areas', 'content_image_a']);
			$force_reseed = $force_reseed || !$has_new || $has_old;
		}
		if (!$is_minimal && !$force_reseed) {
			continue;
		}
		lf_wizard_seed_page_pb_config((int) $page_id, $slug, $data, is_array($niche) ? $niche : [], $created_pages);
	}
	if ($force_reseed_all && function_exists('lf_wizard_seed_pb_config')) {
		$service_posts = get_posts([
			'post_type' => 'lf_service',
			'post_status' => 'publish',
			'posts_per_page' => 300,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
			'no_found_rows' => true,
		]);
		foreach (array_values($service_posts) as $index => $service_post) {
			if (!$service_post instanceof \WP_Post) {
				continue;
			}
			// Preserve frontend editor overrides during reseed.
			lf_wizard_seed_pb_config((int) $service_post->ID, 'service', $data, is_array($niche) ? $niche : [], (int) $index, ['service' => (string) $service_post->post_title]);
		}
		$area_posts = get_posts([
			'post_type' => 'lf_service_area',
			'post_status' => 'publish',
			'posts_per_page' => 300,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
			'no_found_rows' => true,
		]);
		foreach (array_values($area_posts) as $index => $area_post) {
			if (!$area_post instanceof \WP_Post) {
				continue;
			}
			// Preserve frontend editor overrides during reseed.
			$area_title = trim((string) $area_post->post_title);
			$loc = $area_title;
			if (preg_match('/^(.+?),\s*([A-Za-z]{2})$/', $area_title, $m)) {
				$city_part = trim((string) ($m[1] ?? ''));
				$state_part = strtoupper(trim((string) ($m[2] ?? '')));
				$loc = $city_part !== '' ? $city_part . ', ' . $state_part : $state_part;
			}
			lf_wizard_seed_pb_config((int) $area_post->ID, 'service_area', $data, is_array($niche) ? $niche : [], (int) $index, ['area' => $loc]);
		}
	}
	if ($force_reseed_all) {
		$page_posts = get_posts([
			'post_type' => 'page',
			'post_status' => ['publish', 'draft', 'pending', 'private'],
			'posts_per_page' => 300,
			'fields' => 'ids',
			'no_found_rows' => true,
		]);
		foreach ($page_posts as $page_id) {
			$page_id = absint($page_id);
			if ($page_id <= 0) {
				continue;
			}
			// Preserve frontend editor overrides during reseed.
		}
	}
	if (!empty($created_pages['our-services'])) {
		$page = get_post((int) $created_pages['our-services']);
		if ($page instanceof \WP_Post && $page->post_title !== __('Services', 'leadsforward-core')) {
			wp_update_post(['ID' => $page->ID, 'post_title' => __('Services', 'leadsforward-core')]);
		}
	}
if (!empty($created_pages['service-areas'])) {
	$page = get_post((int) $created_pages['service-areas']);
		if ($page instanceof \WP_Post && $page->post_title !== __('Service Areas', 'leadsforward-core')) {
			wp_update_post(['ID' => $page->ID, 'post_title' => __('Service Areas', 'leadsforward-core')]);
		}
	}
	lf_ai_studio_force_related_links_services();
	if (function_exists('lf_ai_studio_ensure_header_menu_primary_pages')) {
		lf_ai_studio_ensure_header_menu_primary_pages();
	}
}

function lf_ai_studio_config_has_section_types($config, array $types): bool {
	if (!is_array($config) || empty($types)) {
		return false;
	}
	$order = $config['order'] ?? [];
	$sections = $config['sections'] ?? [];
	if (!is_array($order) || !is_array($sections)) {
		return false;
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
	foreach ($types as $type) {
		if (empty($enabled_types[$type])) {
			return false;
		}
	}
	return true;
}

function lf_ai_studio_force_related_links_services(): void {
	if (!function_exists('lf_pb_get_post_config')) {
		return;
	}
	$post_types = ['page', 'lf_service', 'lf_service_area', 'post'];
	foreach ($post_types as $post_type) {
		$posts = get_posts([
			'post_type' => $post_type,
			'post_status' => 'publish',
			'posts_per_page' => 200,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
			'no_found_rows' => true,
		]);
		foreach ($posts as $post) {
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
			if ($context === '') {
				continue;
			}
			$config = lf_pb_get_post_config($post->ID, $context);
			$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
			$changed = false;
			foreach ($sections as $instance_id => $section) {
				if (!is_array($section)) {
					continue;
				}
				if (($section['type'] ?? '') !== 'related_links') {
					continue;
				}
				if (!isset($sections[$instance_id]['settings']) || !is_array($sections[$instance_id]['settings'])) {
					$sections[$instance_id]['settings'] = [];
				}
				if (($sections[$instance_id]['settings']['related_links_mode'] ?? '') !== 'services') {
					$sections[$instance_id]['settings']['related_links_mode'] = 'services';
					$changed = true;
				}
			}
			if ($changed) {
				update_post_meta($post->ID, LF_PB_META_KEY, [
					'order' => $config['order'] ?? [],
					'sections' => $sections,
					'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
				]);
			}
		}
	}
}

function lf_ai_studio_build_full_site_payload(bool $respect_manifest_scope = true): array {
	$manifest = lf_ai_studio_get_manifest();
	$use_manifest = !empty($manifest);
	if ($use_manifest) {
		error_log('LF MANIFEST: loaded');
		$manifest_errors = lf_ai_studio_validate_manifest($manifest);
		if (!empty($manifest_errors)) {
			update_option('lf_ai_studio_manifest_errors', $manifest_errors, false);
			return ['error' => __('Manifest validation failed. Fix the uploaded manifest to continue.', 'leadsforward-core')];
		}
		$manifest = lf_ai_studio_normalize_manifest($manifest);
		$scaffold = lf_ai_studio_scaffold_manifest($manifest);
		error_log('LF MANIFEST: scaffold ran ' . wp_json_encode(['success' => $scaffold['success'] ?? false, 'errors' => $scaffold['errors'] ?? []]));
		if (empty($scaffold['success'])) {
			$errors = $scaffold['errors'] ?? [];
			if (is_array($errors) && !empty($errors)) {
				return ['error' => 'Manifest scaffold failed: ' . print_r($errors, true)];
			}
			$message = (string) ($scaffold['message'] ?? '');
			if ($message !== '') {
				return ['error' => 'Manifest scaffold failed: ' . $message];
			}
			return ['error' => __('Manifest scaffold failed. Fix manifest data and try again.', 'leadsforward-core')];
		}
		lf_ai_studio_sync_manifest_posts($manifest);
	}
	lf_ai_studio_ensure_core_page_sections($manifest, true);
	$homepage_payload = lf_ai_studio_build_homepage_blueprint();
	if (!is_array($homepage_payload)) {
		return ['error' => __('Full site payload build failed.', 'leadsforward-core')];
	}
	if (!empty($homepage_payload['error'])) {
		return ['error' => (string) $homepage_payload['error']];
	}
	$homepage_blueprint = $homepage_payload['blueprint'] ?? [];
	if (!is_array($homepage_blueprint) || empty($homepage_blueprint)) {
		if ($use_manifest) {
			$homepage_blueprint = lf_ai_studio_build_default_homepage_blueprint($manifest);
		}
		if (!is_array($homepage_blueprint) || empty($homepage_blueprint)) {
			return ['error' => __('Homepage blueprint is missing.', 'leadsforward-core')];
		}
	}

	$scope = lf_ai_studio_get_generation_scope($manifest, $respect_manifest_scope);

	$blueprints = [];
	if ($scope['homepage']) {
		$blueprints[] = $homepage_blueprint;
	}

	$overview_keyword = '';
	if ($use_manifest) {
		$overview_keyword = (string) ($manifest['homepage']['primary_keyword'] ?? '');
	} else {
		$overview_keyword = (string) ($homepage_payload['keywords']['primary'] ?? '');
	}

	$service_keyword_map = $use_manifest ? lf_ai_studio_manifest_keyword_map($manifest, 'services') : [];
	$area_keyword_map = $use_manifest ? lf_ai_studio_manifest_keyword_map($manifest, 'service_areas') : [];

	if ($scope['services']) {
		$services = get_posts([
			'post_type' => 'lf_service',
			'post_status' => 'publish',
			'posts_per_page' => 200,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
		]);
		foreach ($services as $service) {
			if (!$service instanceof \WP_Post) {
				continue;
			}
			if ($use_manifest && !isset($service_keyword_map[$service->post_name])) {
				continue;
			}
			$keyword = $service_keyword_map[$service->post_name] ?? '';
			if ($keyword === '') {
				$keyword = $overview_keyword !== ''
					? trim($service->post_title . ' ' . $overview_keyword)
					: (string) $service->post_title;
			}
			$blueprint = lf_ai_studio_build_post_blueprint($service, 'service', 'service_detail', $keyword);
			if (!empty($blueprint)) {
				$blueprints[] = $blueprint;
			}
		}
	}

	if ($scope['service_areas']) {
		$areas = get_posts([
			'post_type' => 'lf_service_area',
			'post_status' => 'publish',
			'posts_per_page' => 200,
			'orderby' => 'menu_order title',
			'order' => 'ASC',
		]);
		foreach ($areas as $area) {
			if (!$area instanceof \WP_Post) {
				continue;
			}
			if ($use_manifest && !isset($area_keyword_map[$area->post_name])) {
				continue;
			}
			$keyword = $area_keyword_map[$area->post_name] ?? '';
			if ($keyword === '') {
				$keyword = $overview_keyword !== ''
					? trim($overview_keyword . ' ' . $area->post_title)
					: (string) $area->post_title;
			}
			$blueprint = lf_ai_studio_build_post_blueprint($area, 'service_area', 'service_area_detail', $keyword);
			if (!empty($blueprint)) {
				$blueprints[] = $blueprint;
			}
		}
	}

	if ($scope['core_pages']) {
		$about = get_page_by_path('about-us');
		if ($about instanceof \WP_Post) {
			$blueprint = lf_ai_studio_build_post_blueprint($about, 'about', 'about_overview', '');
			if (!empty($blueprint)) {
				$blueprints[] = $blueprint;
			}
		}

		$core_pages = [
			'contact' => ['page' => 'contact', 'intent' => 'contact', 'keyword' => ''],
			'reviews' => ['page' => 'reviews', 'intent' => 'reviews', 'keyword' => ''],
			'blog' => ['page' => 'blog', 'intent' => 'blog', 'keyword' => ''],
			'sitemap' => ['page' => 'sitemap', 'intent' => 'sitemap', 'keyword' => ''],
			'thank-you' => ['page' => 'thank_you', 'intent' => 'thank_you', 'keyword' => ''],
			'privacy-policy' => ['page' => 'privacy_policy', 'intent' => 'privacy_policy', 'keyword' => ''],
			'terms-of-service' => ['page' => 'terms_of_service', 'intent' => 'terms_of_service', 'keyword' => ''],
		];
		if ($scope['services']) {
			$core_pages['our-services'] = ['page' => 'services_overview', 'intent' => 'services_overview', 'keyword' => $overview_keyword];
		}
		if ($scope['service_areas']) {
			$core_pages['service-areas'] = ['page' => 'service_areas_overview', 'intent' => 'service_areas_overview', 'keyword' => $overview_keyword];
		}
		foreach ($core_pages as $slug => $meta) {
			$page = get_page_by_path($slug);
			if (!$page instanceof \WP_Post) {
				continue;
			}
			$blueprint = lf_ai_studio_build_post_blueprint(
				$page,
				(string) $meta['page'],
				(string) $meta['intent'],
				(string) $meta['keyword']
			);
			if (!empty($blueprint)) {
				$blueprints[] = $blueprint;
			}
		}

	}
	if (!empty($scope['blog_posts'])) {
		$blog_topics = lf_ai_studio_blog_post_topics($manifest, $homepage_payload);
		$blog_posts = lf_ai_studio_ensure_blog_posts($blog_topics);
		foreach ($blog_posts as $entry) {
			$post = $entry['post'] ?? null;
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$keyword = (string) ($entry['keyword'] ?? '');
			$blueprint = lf_ai_studio_build_post_blueprint($post, 'post', 'blog_post', $keyword);
			if (!empty($blueprint)) {
				$blueprints[] = $blueprint;
			}
		}
	}
	if (!empty($scope['projects'])) {
		$projects = get_posts(
			[
				'post_type'      => 'lf_project',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);
		foreach ($projects as $project) {
			if (!$project instanceof \WP_Post) {
				continue;
			}
			$blueprint = lf_ai_studio_build_post_blueprint($project, 'project', 'project_case_study', '');
			if (!empty($blueprint)) {
				$blueprints[] = $blueprint;
			}
		}
	}
	if (empty($blueprints)) {
		return ['error' => __('Generation scope has no enabled targets.', 'leadsforward-core')];
	}

	$business_name = $use_manifest ? (string) ($manifest['business']['name'] ?? '') : (string) ($homepage_payload['business_name'] ?? '');
	$niche = $use_manifest ? (string) ($manifest['business']['niche'] ?? '') : (string) ($homepage_payload['niche'] ?? '');
	$city = $use_manifest ? (string) ($manifest['business']['primary_city'] ?? ($manifest['business']['address']['city'] ?? '')) : (string) ($homepage_payload['city_region'] ?? '');
	$keywords = $use_manifest
		? [
			'primary' => (string) ($manifest['homepage']['primary_keyword'] ?? ''),
			'secondary' => $manifest['homepage']['secondary_keywords'] ?? [],
		]
		: ($homepage_payload['keywords'] ?? []);
	$business_entity = $homepage_payload['business_entity'] ?? [];
	if ($use_manifest) {
		$business_entity = lf_ai_studio_manifest_business_entity($manifest, is_array($business_entity) ? $business_entity : []);
	}
	error_log('LF MANIFEST: blueprints count ' . count($blueprints));
	$internal_links = lf_ai_studio_internal_links_catalog();
	$payload = [
		'request_id' => (string) ($homepage_payload['request_id'] ?? ''),
		'variation_seed' => (string) ($homepage_payload['variation_seed'] ?? ''),
		'business_name' => $business_name,
		'niche' => $niche,
		'city_region' => $city,
		'keywords' => $keywords,
		'writing_samples' => lf_ai_studio_collect_writing_samples(),
		'business_entity' => $business_entity,
		'system_message' => lf_ai_studio_llm_system_message(),
		'faq_strategy' => lf_ai_studio_faq_strategy(),
		'cta_strategy' => lf_ai_studio_cta_strategy(),
		'internal_links' => $internal_links,
		'internal_link_rules' => [
			'max_links_per_richtext' => 2,
			'avoid_self_link' => true,
			'prefer_services' => true,
		],
		'image_generation' => lf_ai_studio_build_hybrid_image_generation_plan(
			(int) get_option('lf_ai_image_generation_limit', 12)
		),
		'generation_scope' => $scope,
		'blueprints' => $blueprints,
	];
	$research = lf_ai_studio_get_research_document();
	if (!empty($research)) {
		$payload['research_document'] = $research;
	}
	return $payload;
}

function lf_ai_studio_build_blog_payload(): array {
	$manifest = lf_ai_studio_get_manifest();
	$homepage_payload = lf_ai_studio_build_homepage_blueprint();
	if (!is_array($homepage_payload)) {
		return ['error' => __('Blog payload build failed.', 'leadsforward-core')];
	}
	if (!empty($homepage_payload['error'])) {
		return ['error' => (string) $homepage_payload['error']];
	}
	$blog_topics = lf_ai_studio_blog_post_topics($manifest, $homepage_payload);
	$blog_posts = lf_ai_studio_ensure_blog_posts($blog_topics);
	$blueprints = [];
	foreach ($blog_posts as $entry) {
		$post = $entry['post'] ?? null;
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$keyword = (string) ($entry['keyword'] ?? '');
		$blueprint = lf_ai_studio_build_post_blueprint($post, 'post', 'blog_post', $keyword);
		if (!empty($blueprint)) {
			$blueprints[] = $blueprint;
		}
	}
	if (empty($blueprints)) {
		return ['error' => __('No AI blog posts available to regenerate.', 'leadsforward-core')];
	}
	$base_request_id = (string) ($homepage_payload['request_id'] ?? '');
	if ($base_request_id === '') {
		$base_request_id = wp_generate_uuid4();
	}
	$request_id = 'blog-' . $base_request_id . '-' . time();
	$keywords = $homepage_payload['keywords'] ?? ['primary' => '', 'secondary' => []];
	$internal_links = lf_ai_studio_internal_links_catalog();
	$payload = [
		'request_id' => $request_id,
		'variation_seed' => (string) ($homepage_payload['variation_seed'] ?? ''),
		'business_name' => (string) ($homepage_payload['business_name'] ?? ''),
		'niche' => (string) ($homepage_payload['niche'] ?? ''),
		'city_region' => (string) ($homepage_payload['city_region'] ?? ''),
		'keywords' => $keywords,
		'writing_samples' => lf_ai_studio_collect_writing_samples(),
		'business_entity' => $homepage_payload['business_entity'] ?? [],
		'system_message' => lf_ai_studio_llm_system_message(),
		'faq_strategy' => lf_ai_studio_faq_strategy(),
		'cta_strategy' => lf_ai_studio_cta_strategy(),
		'internal_links' => $internal_links,
		'internal_link_rules' => [
			'max_links_per_richtext' => 2,
			'avoid_self_link' => true,
			'prefer_services' => true,
		],
		'image_generation' => lf_ai_studio_build_hybrid_image_generation_plan(
			(int) get_option('lf_ai_image_generation_limit', 12)
		),
		'blueprints' => $blueprints,
	];
	$research = lf_ai_studio_get_research_document();
	if (!empty($research)) {
		$payload['research_document'] = $research;
	}
	return $payload;
}

function lf_ai_studio_internal_links_catalog(): array {
	$links = [];
	$add = function (string $type, string $label, string $url) use (&$links): void {
		$url = trim($url);
		$label = trim($label);
		if ($url === '' || $label === '') {
			return;
		}
		$links[] = [
			'type' => $type,
			'label' => $label,
			'url' => $url,
		];
	};

	$add('page', __('Home', 'leadsforward-core'), home_url('/'));
	foreach (['about-us', 'our-services', 'service-areas', 'reviews', 'blog', 'contact'] as $slug) {
		$page = get_page_by_path($slug);
		if ($page instanceof \WP_Post) {
			$add('page', get_the_title($page), get_permalink($page));
		}
	}

	$services = get_posts([
		'post_type'      => 'lf_service',
		'post_status'    => 'publish',
		'posts_per_page' => 8,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	]);
	foreach ($services as $service) {
		if ($service instanceof \WP_Post) {
			$add('service', $service->post_title, get_permalink($service));
		}
	}

	$areas = get_posts([
		'post_type'      => 'lf_service_area',
		'post_status'    => 'publish',
		'posts_per_page' => 8,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	]);
	foreach ($areas as $area) {
		if ($area instanceof \WP_Post) {
			$add('service_area', $area->post_title, get_permalink($area));
		}
	}

	$posts = get_posts([
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 4,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	]);
	foreach ($posts as $post) {
		if ($post instanceof \WP_Post) {
			$add('post', $post->post_title, get_permalink($post));
		}
	}

	return $links;
}

function lf_ai_studio_registry_richtext_keys(string $section_type, array $registry): array {
	$schema = is_array($registry[$section_type] ?? null) ? $registry[$section_type] : [];
	$fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
	$keys = [];
	foreach ($fields as $field) {
		if (!is_array($field)) {
			continue;
		}
		$key = sanitize_key((string) ($field['key'] ?? ''));
		$type = (string) ($field['type'] ?? '');
		if ($key !== '' && $type === 'richtext') {
			$keys[] = $key;
		}
	}
	return array_values(array_unique($keys));
}

function lf_ai_studio_pick_internal_link(array $catalog, \WP_Post $post, string $section_type, string $field_key): array {
	$self_url = (string) get_permalink($post);
	$candidates = [];
	foreach ($catalog as $entry) {
		if (!is_array($entry)) {
			continue;
		}
		$url = trim((string) ($entry['url'] ?? ''));
		$label = trim((string) ($entry['label'] ?? ''));
		if ($url === '' || $label === '' || $url === $self_url) {
			continue;
		}
		$candidates[] = ['url' => $url, 'label' => $label];
	}
	if (empty($candidates)) {
		return [];
	}
	$seed = crc32($post->ID . '|' . $section_type . '|' . $field_key);
	$index = (int) (abs($seed) % count($candidates));
	return $candidates[$index] ?? [];
}

function lf_ai_studio_inject_internal_link_markup(string $value, array $target): string {
	$clean = trim($value);
	if ($clean === '' || stripos($clean, '<a ') !== false) {
		return $value;
	}
	$url = trim((string) ($target['url'] ?? ''));
	$label = trim((string) ($target['label'] ?? ''));
	if ($url === '' || $label === '') {
		return $value;
	}
	$link = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
	if (stripos($clean, '</p>') !== false) {
		return preg_replace('/<\/p>\s*$/i', ' ' . $link . '</p>', $clean, 1) ?: ($clean . ' ' . $link);
	}
	return wp_kses_post($clean . ' ' . $link);
}

function lf_ai_studio_orchestrate_internal_links_for_settings(array $settings, string $section_type, array $registry, \WP_Post $post): array {
	$catalog = lf_ai_studio_internal_links_catalog();
	if (empty($catalog)) {
		return ['settings' => $settings, 'inserted' => 0];
	}
	$rich_keys = lf_ai_studio_registry_richtext_keys($section_type, $registry);
	if (empty($rich_keys)) {
		return ['settings' => $settings, 'inserted' => 0];
	}
	$inserted = 0;
	foreach ($rich_keys as $field_key) {
		$value = $settings[$field_key] ?? null;
		if (!is_string($value) || trim($value) === '') {
			continue;
		}
		$target = lf_ai_studio_pick_internal_link($catalog, $post, $section_type, $field_key);
		if (empty($target)) {
			continue;
		}
		$updated = lf_ai_studio_inject_internal_link_markup($value, $target);
		if ($updated !== $value) {
			$settings[$field_key] = $updated;
			$inserted++;
		}
	}
	return ['settings' => $settings, 'inserted' => $inserted];
}

function lf_ai_studio_homepage_internal_links(): array {
	$services = get_posts([
		'post_type' => 'lf_service',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'fields' => 'ids',
	]);
	$areas = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'fields' => 'ids',
	]);
	$service_targets = [];
	foreach ($services as $id) {
		$post = get_post($id);
		if ($post instanceof \WP_Post) {
			$service_targets[] = ['id' => $post->ID, 'slug' => $post->post_name];
		}
	}
	$area_targets = [];
	foreach ($areas as $id) {
		$post = get_post($id);
		if ($post instanceof \WP_Post) {
			$area_targets[] = ['id' => $post->ID, 'slug' => $post->post_name];
		}
	}
	return [
		'services' => $service_targets,
		'service_areas' => $area_targets,
	];
}

function lf_homepage_variation_seed(): string {
	$manifest = lf_ai_studio_get_manifest();
	if (!empty($manifest)) {
		$manifest_errors = lf_ai_studio_validate_manifest($manifest);
		if (empty($manifest_errors)) {
			$normalized = lf_ai_studio_normalize_manifest($manifest);
			$business = (string) ($normalized['business']['name'] ?? '');
			$city = (string) ($normalized['business']['primary_city'] ?? ($normalized['business']['address']['city'] ?? ''));
			$niche = (string) ($normalized['business']['niche'] ?? '');
			$seed_source = trim($business . '|' . $city . '|' . $niche);
			if ($seed_source !== '') {
				return substr(hash('sha256', $seed_source), 0, 20);
			}
		}
	}
	$seed = get_option('lf_homepage_variation_seed', '');
	$seed = is_string($seed) ? trim($seed) : '';
	if ($seed !== '') {
		return $seed;
	}
	$generated = '';
	if (function_exists('wp_generate_password')) {
		$generated = wp_generate_password(20, false, false);
	}
	if (!is_string($generated) || trim($generated) === '') {
		$generated = substr(hash('sha256', (string) get_site_url()), 0, 20);
	}
	$seed = trim((string) $generated);
	update_option('lf_homepage_variation_seed', $seed, true);
	return $seed;
}

function lf_ai_studio_homepage_request_id(array $base): string {
	$hash = hash('sha256', wp_json_encode($base));
	return substr($hash, 0, 16);
}

function lf_ai_studio_build_blueprint(): array {
	$scope = (string) get_option('lf_ai_studio_scope', 'all');
	$scope_types = get_option('lf_ai_studio_scope_types', []);
	$scope_types = is_array($scope_types) ? $scope_types : [];
	$style = (string) get_option('lf_ai_studio_style', 'professional');
	$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
	$keywords = lf_ai_studio_keywords();
	$sections = function_exists('lf_sections_registry') ? lf_sections_registry() : [];

	$posts = [];
	$included = function (string $key) use ($scope, $scope_types): bool {
		return $scope === 'all' || in_array($key, $scope_types, true);
	};

	$home_id = (int) get_option('page_on_front');
	$homepage = [];
	if ($home_id && $included('home') && function_exists('lf_get_homepage_section_config')) {
		$homepage = [
			'post_id' => $home_id,
			'config' => lf_get_homepage_section_config(),
		];
	}

	$page_map = [
		'about' => 'about-us',
		'contact' => 'contact',
		'reviews' => 'reviews',
		'blog' => 'blog',
	];
	foreach ($page_map as $key => $slug) {
		if (!$included($key)) {
			continue;
		}
		$page = get_page_by_path($slug);
		if ($page instanceof \WP_Post && function_exists('lf_pb_get_post_config')) {
			$posts[] = lf_ai_studio_collect_post($page);
		}
	}

	if ($included('services')) {
		$services = get_posts(['post_type' => 'lf_service', 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'menu_order title', 'order' => 'ASC']);
		foreach ($services as $service) {
			$posts[] = lf_ai_studio_collect_post($service);
		}
	}
	if ($included('service_areas')) {
		$areas = get_posts(['post_type' => 'lf_service_area', 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'menu_order title', 'order' => 'ASC']);
		foreach ($areas as $area) {
			$posts[] = lf_ai_studio_collect_post($area);
		}
	}

	return [
		'schema_version' => '1.0',
		'style' => $style,
		'scope' => $scope,
		'scope_types' => $scope_types,
		'keywords' => $keywords,
		'business_entity' => $entity,
		'sections' => $sections,
		'homepage' => $homepage,
		'posts' => $posts,
	];
}

function lf_ai_studio_collect_post(\WP_Post $post): array {
	$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
	$config = function_exists('lf_pb_get_post_config') && $context !== '' ? lf_pb_get_post_config($post->ID, $context) : [];
	return [
		'id' => $post->ID,
		'post_type' => $post->post_type,
		'slug' => $post->post_name,
		'title' => $post->post_title,
		'context' => $context,
		'post_content' => $post->post_content,
		'config' => $config,
	];
}

function lf_ai_studio_validate_payload(array $payload): array {
	$errors = [];
	if (!is_array($payload)) {
		return [__('Payload must be an object.', 'leadsforward-core')];
	}
	if (isset($payload['updates']) && !is_array($payload['updates'])) {
		$errors[] = __('Updates payload must be an array.', 'leadsforward-core');
	}
	return $errors;
}

function lf_ai_studio_apply_payload(array $payload): array {
	return lf_apply_orchestrator_updates($payload);
}

function lf_ai_studio_normalize_text(string $text): string {
	$clean = wp_check_invalid_utf8($text, true);
	if ($clean === false) {
		$clean = '';
	}
	$clean = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $clean);
	$clean = str_replace(["\r\n", "\r"], "\n", $clean);
	$clean = str_replace(["\u{2018}", "\u{2019}"], "'", $clean);
	$clean = preg_replace('/\\\\([\'"’])/u', '$1', $clean);
	$clean = preg_replace('/\\\\{2,}/', '\\', $clean);
	return $clean;
}

function lf_ai_studio_strip_link_markup(string $text): string {
	$text = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{2033}"], '"', $text);
	$text = str_replace(["\u{2018}", "\u{2019}", "\u{2032}"], "'", $text);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = preg_replace('/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/iu', '$1', $text);
	$text = preg_replace('/<a\b[^>]*>(.*?)<\/a>/iu', '$1', $text);
	$text = preg_replace('/<\/?a\b[^>]*>?/iu', '', $text);
	$text = preg_replace('/\s*href\s*=\s*["\'\x{201C}\x{201D}\x{2018}\x{2019}][^"\'\x{201C}\x{201D}\x{2018}\x{2019}]*["\'\x{201C}\x{201D}\x{2018}\x{2019}]/iu', ' ', $text);
	$text = preg_replace('/<\s*\/?\s*a\b/iu', '', $text);
	return is_string($text) ? $text : '';
}

function lf_ai_studio_clean_text_field_value(string $text): string {
	$text = lf_ai_studio_strip_link_markup($text);
	$text = wp_strip_all_tags((string) $text);
	$text = preg_replace('/https?:\/\/[^\s<>"\']+/iu', '', $text);
	$text = preg_replace('/[<>]+/u', ' ', (string) $text);
	$text = preg_replace('/\s*\/\s*a\b/iu', ' ', (string) $text);
	$text = preg_replace('/\b[aA]\s+href\s*=\s*[^ ]+/u', ' ', (string) $text);
	$text = preg_replace('/\s+/u', ' ', (string) $text);
	return trim((string) $text);
}

function lf_ai_studio_maybe_limit_pill_text(string $line, string $field_key): string {
	if (!in_array($field_key, ['trust_badges', 'hero_proof_bullets'], true)) {
		return trim($line);
	}
	$line = trim((string) preg_replace('/\s+/u', ' ', $line));
	if ($line === '') {
		return '';
	}
	$words = preg_split('/\s+/u', $line);
	$words = is_array($words) ? array_values(array_filter($words, static function ($word): bool {
		return trim((string) $word) !== '';
	})) : [];
	if (empty($words)) {
		return '';
	}
	if (count($words) > 5) {
		$words = array_slice($words, 0, 5);
	}
	return implode(' ', $words);
}

function lf_ai_studio_maybe_title_case_heading(string $value): string {
	$value = trim((string) preg_replace('/\s+/u', ' ', $value));
	if ($value === '') {
		return '';
	}
	if (!preg_match('/[A-Z]/', $value)) {
		return ucwords($value);
	}
	return $value;
}

function lf_ai_studio_limit_cta_label(string $value, int $max_words = 6): string {
	$value = trim((string) preg_replace('/\s+/u', ' ', $value));
	if ($value === '') {
		return '';
	}
	$words = preg_split('/\s+/u', $value);
	$words = is_array($words) ? array_values(array_filter($words, static function ($word): bool {
		return trim((string) $word) !== '';
	})) : [];
	if (count($words) > $max_words) {
		$words = array_slice($words, 0, $max_words);
	}
	return implode(' ', $words);
}

function lf_ai_studio_trim_to_words(string $value, int $max_words): string {
	$value = trim((string) preg_replace('/\s+/u', ' ', $value));
	if ($value === '' || $max_words <= 0) {
		return $value;
	}
	$words = preg_split('/\s+/u', $value);
	$words = is_array($words) ? array_values(array_filter($words, static function ($word): bool {
		return trim((string) $word) !== '';
	})) : [];
	if (count($words) <= $max_words) {
		return $value;
	}
	return implode(' ', array_slice($words, 0, $max_words));
}

function lf_ai_studio_limit_list_items(string $value, int $max_items): string {
	$lines = preg_split('/\r\n|\r|\n/', (string) $value);
	$lines = is_array($lines) ? array_values(array_filter(array_map('trim', $lines), static function ($line): bool {
		return $line !== '';
	})) : [];
	if ($max_items > 0 && count($lines) > $max_items) {
		$lines = array_slice($lines, 0, $max_items);
	}
	return implode("\n", $lines);
}

function lf_ai_studio_default_process_steps(): string {
	return implode("\n", [
		'Consultation And Site Review: We review goals, current conditions, and project priorities.',
		'Scope And Plan: You receive a clear written scope with timeline expectations.',
		'Preparation: Materials, site protection, and scheduling are finalized before work starts.',
		'Execution: Work is completed with quality checks and clear progress communication.',
		'Final Walkthrough: We review results, answer questions, and confirm next steps.',
	]);
}

function lf_ai_studio_clean_value_for_field($value, string $field_type, string $field_key = '') {
	if (!is_string($value) && !is_array($value)) {
		return $value;
	}
	if ($field_type === 'richtext' || $field_type === 'url' || $field_type === 'image' || $field_type === 'number') {
		return $value;
	}
	if ($field_type === 'list') {
		$raw_lines = is_array($value) ? $value : explode("\n", (string) $value);
		$clean_lines = [];
		foreach ($raw_lines as $line) {
			$line_text = lf_ai_studio_clean_text_field_value((string) $line);
			$line_text = lf_ai_studio_maybe_limit_pill_text($line_text, $field_key);
			if ($line_text !== '') {
				$clean_lines[] = $line_text;
			}
		}
		$clean_lines = array_values(array_unique($clean_lines));
		return implode("\n", $clean_lines);
	}
	return lf_ai_studio_clean_text_field_value((string) $value);
}

/**
 * Replace common LLM/template tokens with real context before saving to the DB.
 *
 * @param array<string, string> $ctx Keys: business_name, city_region, primary_keyword, niche.
 */
function lf_ai_studio_strip_llm_placeholder_tokens(string $text, array $ctx): string {
	if ($text === '') {
		return $text;
	}
	$biz = trim((string) ($ctx['business_name'] ?? ''));
	$city = trim((string) ($ctx['city_region'] ?? ''));
	$pk = trim((string) ($ctx['primary_keyword'] ?? ''));
	$niche = trim((string) ($ctx['niche'] ?? ''));
	if ($biz !== '') {
		$text = str_ireplace('BUSINESS_NAME', $biz, $text);
	}
	if ($city !== '') {
		$text = str_ireplace('CITY_REGION', $city, $text);
		$text = str_replace('[Your City]', $city, $text);
	}
	if ($pk !== '') {
		$text = str_ireplace('PRIMARY_KEYWORD', $pk, $text);
	}
	if ($niche !== '') {
		$text = str_ireplace('NICHE_TOKEN', $niche, $text);
	}
	$text = preg_replace_callback('/\{\{\s*\$?json\.([^}]+)\}\}/i', static function (array $match) use ($biz, $city, $pk, $niche): string {
		$raw = trim((string) ($match[1] ?? ''));
		$key = strtolower($raw);
		$map = [
			'business_name' => $biz,
			'company_name' => $biz,
			'primary_keyword' => $pk,
			'city_region' => $city,
			'city' => $city,
			'niche' => $niche,
		];
		if (isset($map[$key]) && trim((string) $map[$key]) !== '') {
			return trim((string) $map[$key]);
		}
		return $raw;
	}, $text);
	return $text;
}

function lf_ai_studio_contains_json_placeholder(string $value): bool {
	return (bool) preg_match('/\{\{\s*\$?json\./i', $value);
}

/**
 * @param array<string, string> $ctx
 * @return mixed
 */
function lf_ai_studio_strip_llm_tokens_from_mixed_value($value, array $ctx, string $field_type) {
	if ($field_type === 'list' && is_string($value)) {
		$lines = preg_split('/\R/', $value);
		$out = [];
		foreach ($lines as $line) {
			$t = lf_ai_studio_strip_llm_placeholder_tokens((string) $line, $ctx);
			if (trim($t) !== '') {
				$out[] = $t;
			}
		}
		return implode("\n", $out);
	}
	if (is_string($value)) {
		return lf_ai_studio_strip_llm_placeholder_tokens($value, $ctx);
	}
	return $value;
}

/**
 * When FAQ posts exist but accordion sections have no selection, attach recent FAQs.
 */
function lf_ai_studio_autofill_empty_faq_accordion_picks(): void {
	$faq_ids = get_posts([
		'post_type' => 'lf_faq',
		'post_status' => 'publish',
		'posts_per_page' => 18,
		'orderby' => 'date',
		'order' => 'DESC',
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	if (empty($faq_ids)) {
		return;
	}
	$faq_ids = array_values(array_filter(array_map('absint', $faq_ids)));
	if (function_exists('lf_get_homepage_section_config') && defined('LF_HOMEPAGE_CONFIG_OPTION')) {
		$config = lf_get_homepage_section_config();
		if (is_array($config) && !empty($config)) {
			$changed = false;
			foreach ($config as $section_id => $settings) {
				if (!is_array($settings) || empty($settings['enabled'])) {
					continue;
				}
				$base = function_exists('lf_homepage_base_section_type') ? lf_homepage_base_section_type((string) $section_id) : '';
				if ($base !== 'faq_accordion') {
					continue;
				}
				if (trim((string) ($settings['faq_selected_ids'] ?? '')) !== '') {
					continue;
				}
				$max = (int) ($settings['faq_max_items'] ?? 5);
				$max = max(3, min(12, $max));
				$slice = array_slice($faq_ids, 0, $max);
				if (empty($slice)) {
					continue;
				}
				$settings['faq_selected_ids'] = implode(',', $slice);
				$config[ $section_id ] = $settings;
				$changed = true;
			}
			if ($changed) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
			}
		}
	}
	if (!function_exists('lf_pb_get_post_config') || !function_exists('lf_pb_get_context_for_post') || !defined('LF_PB_META_KEY')) {
		return;
	}
	foreach (['page', 'lf_service', 'lf_service_area'] as $pt) {
		$pids = get_posts([
			'post_type' => $pt,
			'post_status' => 'any',
			'posts_per_page' => 80,
			'fields' => 'ids',
			'no_found_rows' => true,
		]);
		foreach ($pids as $pid) {
			$pid = (int) $pid;
			$post = get_post($pid);
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$ctx = lf_pb_get_context_for_post($post);
			if ($ctx === '') {
				continue;
			}
			$pb = lf_pb_get_post_config($pid, $ctx);
			$sections = is_array($pb['sections'] ?? null) ? $pb['sections'] : [];
			$order = is_array($pb['order'] ?? null) ? $pb['order'] : [];
			$meta_changed = false;
			foreach ($order as $iid) {
				$section = $sections[ $iid ] ?? null;
				if (!is_array($section) || empty($section['enabled'])) {
					continue;
				}
				if ((string) ($section['type'] ?? '') !== 'faq_accordion') {
					continue;
				}
				$st = is_array($section['settings'] ?? null) ? $section['settings'] : [];
				if (trim((string) ($st['faq_selected_ids'] ?? '')) !== '') {
					continue;
				}
				$max = (int) ($st['faq_max_items'] ?? 5);
				$max = max(3, min(12, $max));
				$slice = array_slice($faq_ids, 0, $max);
				if (empty($slice)) {
					continue;
				}
				$st['faq_selected_ids'] = implode(',', $slice);
				$sections[ $iid ]['settings'] = $st;
				$meta_changed = true;
			}
			if ($meta_changed) {
				update_post_meta($pid, LF_PB_META_KEY, [
					'order' => $order,
					'sections' => $sections,
					'seo' => $pb['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
				]);
			}
		}
	}
}

function lf_ai_studio_enforce_section_quality(array $settings, string $section_type, array $registry): array {
	$schema = is_array($registry[$section_type] ?? null) ? $registry[$section_type] : [];
	$fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
	if (empty($fields)) {
		return $settings;
	}
	foreach ($fields as $field) {
		if (!is_array($field)) {
			continue;
		}
		$field_key = (string) ($field['key'] ?? '');
		$field_type = (string) ($field['type'] ?? '');
		if ($field_key === '' || !array_key_exists($field_key, $settings)) {
			continue;
		}
		$value = lf_ai_studio_normalize_value($settings[$field_key]);
		if ($field_type === 'list') {
			$value = lf_ai_studio_coerce_list_value($value);
		}
		$value = lf_ai_studio_clean_value_for_field($value, $field_type, $field_key);
		if (is_string($value)) {
			if (strpos($field_key, 'headline') !== false || strpos($field_key, 'heading') !== false) {
				$value = lf_ai_studio_maybe_title_case_heading($value);
			}
			if ($field_key === 'hero_headline') {
				$value = lf_ai_studio_trim_to_words($value, 12);
			} elseif ($field_key === 'section_heading' || $field_key === 'cta_headline') {
				$value = lf_ai_studio_trim_to_words($value, 14);
			} elseif ($field_key === 'hero_subheadline' || $field_key === 'section_intro') {
				$value = lf_ai_studio_trim_to_words($value, 32);
			}
			if (in_array($field_key, ['cta_primary_override', 'cta_secondary_override'], true)) {
				$value = lf_ai_studio_limit_cta_label($value, 6);
			}
		}
		if ($field_type === 'list' && is_string($value)) {
			if (in_array($field_key, ['trust_badges', 'hero_proof_bullets', 'cta_bullets'], true)) {
				$value = lf_ai_studio_limit_list_items($value, 4);
			}
			if ($field_key === 'process_steps') {
				$value = lf_ai_studio_limit_list_items($value, 5);
				if (trim($value) === '') {
					$value = lf_ai_studio_default_process_steps();
				}
			}
		}
		if ($section_type === 'faq_accordion' && $field_key === 'faq_selected_ids') {
			$value = lf_ai_studio_normalize_faq_selected_ids_value($value);
		}
		$settings[$field_key] = $value;
	}
	return $settings;
}

function lf_ai_studio_deduplicate_headings(array $items, array $registry, bool $is_page_builder = false): array {
	$seen = [];
	foreach ($items as $id => $row) {
		$settings = $is_page_builder ? ($row['settings'] ?? null) : $row;
		if (!is_array($settings)) {
			continue;
		}
		$type = $is_page_builder ? (string) ($row['type'] ?? '') : (string) $id;
		$label = '';
		if ($type !== '' && isset($registry[$type]['label'])) {
			$label = (string) $registry[$type]['label'];
		}
		$label = trim($label);
		$defaults = function_exists('lf_sections_defaults_for') && $type !== ''
			? lf_sections_defaults_for($type, (string) get_option('lf_homepage_niche_slug', 'general'))
			: [];
		foreach (['hero_headline', 'section_heading', 'trust_heading', 'cta_headline'] as $key) {
			$value = $settings[$key] ?? '';
			if (!is_string($value)) {
				continue;
			}
			$raw = trim($value);
			if ($raw === '') {
				continue;
			}
			$normalized = strtolower(preg_replace('/\s+/', ' ', $raw));
			if (isset($seen[$normalized])) {
				$limit = $key === 'hero_headline' ? 12 : 14;
				$fallback = '';
				if (isset($defaults[$key]) && is_string($defaults[$key])) {
					$fallback = trim((string) $defaults[$key]);
				}
				$fallback_norm = $fallback !== '' ? strtolower(preg_replace('/\s+/', ' ', $fallback)) : '';
				if ($fallback !== '' && !isset($seen[$fallback_norm])) {
					$settings[$key] = lf_ai_studio_trim_to_words($fallback, $limit);
				} elseif ($label !== '') {
					$settings[$key] = lf_ai_studio_trim_to_words($label, $limit);
				}
			}
			$final = trim((string) ($settings[$key] ?? $raw));
			if ($final !== '') {
				$seen[strtolower(preg_replace('/\s+/', ' ', $final))] = true;
			}
		}
		if ($is_page_builder) {
			$items[$id]['settings'] = $settings;
		} else {
			$items[$id] = $settings;
		}
	}
	return $items;
}

function lf_ai_studio_normalize_faq_question_key(string $question): string {
	$key = strtolower(trim((string) preg_replace('/\s+/', ' ', $question)));
	return (string) preg_replace('/[^\p{L}\p{N}\s\-_]+/u', '', $key);
}

function lf_ai_studio_build_faq_lookup_map(): array {
	$lookup = [];
	$faq_ids = get_posts([
		'post_type' => 'lf_faq',
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	foreach ($faq_ids as $faq_id) {
		$faq_id = absint($faq_id);
		if ($faq_id <= 0) {
			continue;
		}
		$title = trim((string) get_the_title($faq_id));
		if ($title !== '') {
			$lookup[lf_ai_studio_normalize_faq_question_key($title)] = $faq_id;
		}
		$meta_question = trim((string) get_post_meta($faq_id, 'lf_faq_question', true));
		if ($meta_question !== '') {
			$lookup[lf_ai_studio_normalize_faq_question_key($meta_question)] = $faq_id;
		}
		$post_name = trim((string) get_post_field('post_name', $faq_id));
		if ($post_name !== '') {
			$lookup[strtolower($post_name)] = $faq_id;
		}
	}
	return $lookup;
}

function lf_ai_studio_normalize_faq_selected_ids_value($value): string {
	$tokens = [];
	if (is_array($value)) {
		$tokens = $value;
	} else {
		$tokens = preg_split('/[\r\n,]+/', (string) $value);
	}
	$tokens = is_array($tokens) ? $tokens : [];
	$lookup = lf_ai_studio_build_faq_lookup_map();
	$ids = [];
	foreach ($tokens as $token) {
		$text = trim((string) $token);
		if ($text === '') {
			continue;
		}
		$numeric = absint($text);
		if ($numeric > 0) {
			$post = get_post($numeric);
			if ($post instanceof \WP_Post && $post->post_type === 'lf_faq') {
				$ids[] = $numeric;
				continue;
			}
		}
		$key = lf_ai_studio_normalize_faq_question_key($text);
		if ($key !== '' && !empty($lookup[$key])) {
			$ids[] = absint($lookup[$key]);
			continue;
		}
		$slug_key = strtolower(sanitize_title($text));
		if ($slug_key !== '' && !empty($lookup[$slug_key])) {
			$ids[] = absint($lookup[$slug_key]);
		}
	}
	$ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
	return !empty($ids) ? implode("\n", $ids) : '';
}

function lf_ai_studio_normalize_value($value) {
	if (is_array($value)) {
		foreach ($value as $key => $item) {
			$value[$key] = lf_ai_studio_normalize_value($item);
		}
		return $value;
	}
	if (!is_string($value)) {
		return $value;
	}
	return lf_ai_studio_normalize_text($value);
}

function lf_ai_studio_registry_field_type(array $registry, string $section_id, string $field_key): string {
	$section = $registry[$section_id] ?? null;
	if (!is_array($section)) {
		return '';
	}
	foreach ($section['fields'] ?? [] as $field) {
		if (($field['key'] ?? '') === $field_key) {
			return (string) ($field['type'] ?? '');
		}
	}
	return '';
}

function lf_ai_studio_coerce_list_value($value): string {
	if (is_array($value)) {
		$lines = [];
		foreach ($value as $item) {
			if (is_array($item)) {
				$item = wp_json_encode($item);
			}
			$item = lf_ai_studio_normalize_text((string) $item);
			$item = trim($item);
			if ($item !== '') {
				$lines[] = $item;
			}
		}
		return implode("\n", $lines);
	}
	return (string) $value;
}

function lf_ai_studio_homepage_image_context(): array {
	$keywords = get_option('lf_homepage_keywords', []);
	$primary = is_array($keywords) ? (string) ($keywords['primary'] ?? '') : '';
	$secondary = is_array($keywords) && is_array($keywords['secondary'] ?? null) ? $keywords['secondary'] : [];
	return [
		'page_type' => 'homepage',
		'service_slug' => '',
		'area_slug' => '',
		'niche' => (string) (defined('LF_HOMEPAGE_NICHE_OPTION') ? get_option(LF_HOMEPAGE_NICHE_OPTION, '') : ''),
		'city' => (string) get_option('lf_homepage_city', ''),
		'primary_keyword' => $primary,
		'secondary_keywords' => $secondary,
		'variation_seed' => (string) get_option('lf_homepage_variation_seed', ''),
		'service_name' => (string) get_bloginfo('name'),
	];
}

function lf_ai_studio_collect_missing_image_targets(int $limit = 12): array {
	$limit = max(1, min(60, $limit));
	$out = [];
	$allowed_slots = ['hero', 'content_image_a'];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];

	$push_target = static function (array $target) use (&$out, $limit): void {
		if (count($out) >= $limit) {
			return;
		}
		$out[] = $target;
	};

	$home_context = lf_ai_studio_homepage_image_context();
	$home_config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
	foreach ($home_config as $section_type => $settings) {
		if (count($out) >= $limit || !is_array($settings) || !isset($registry[$section_type]) || !function_exists('lf_image_intelligence_registry_image_fields')) {
			continue;
		}
		$image_fields = lf_image_intelligence_registry_image_fields($registry[$section_type]);
		foreach ($image_fields as $field_key) {
			if (count($out) >= $limit || !function_exists('lf_image_intelligence_slot_for_section_field') || !function_exists('lf_image_intelligence_empty_image_value')) {
				break;
			}
			$slot = lf_image_intelligence_slot_for_section_field($section_type, $field_key);
			if (!in_array($slot, $allowed_slots, true)) {
				continue;
			}
			$current = $settings[$field_key] ?? '';
			if (!lf_image_intelligence_empty_image_value($current)) {
				continue;
			}
			$push_target([
				'target' => 'homepage',
				'post_id' => (int) get_option('page_on_front'),
				'section_type' => $section_type,
				'field_key' => $field_key,
				'slot' => $slot,
				'context' => $home_context,
			]);
		}
	}

	$posts = get_posts([
		'post_type' => ['lf_service', 'lf_service_area', 'page'],
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	foreach ($posts as $post) {
		if (count($out) >= $limit || !$post instanceof \WP_Post) {
			continue;
		}
		$context_key = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
		if ($context_key === '' || !function_exists('lf_pb_get_post_config')) {
			continue;
		}
		$config = lf_pb_get_post_config((int) $post->ID, $context_key);
		$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
		$image_context = function_exists('lf_image_intelligence_build_context_for_post')
			? lf_image_intelligence_build_context_for_post($post)
			: [];
		foreach ($sections as $instance_id => $section) {
			if (count($out) >= $limit || !is_array($section)) {
				continue;
			}
			$section_type = (string) ($section['type'] ?? '');
			$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
			if ($section_type === '' || !isset($registry[$section_type]) || !function_exists('lf_image_intelligence_registry_image_fields')) {
				continue;
			}
			$image_fields = lf_image_intelligence_registry_image_fields($registry[$section_type]);
			foreach ($image_fields as $field_key) {
				if (count($out) >= $limit || !function_exists('lf_image_intelligence_slot_for_section_field') || !function_exists('lf_image_intelligence_empty_image_value')) {
					break;
				}
				$slot = lf_image_intelligence_slot_for_section_field($section_type, $field_key);
				if (!in_array($slot, $allowed_slots, true)) {
					continue;
				}
				$current = $settings[$field_key] ?? '';
				if (!lf_image_intelligence_empty_image_value($current)) {
					continue;
				}
				$push_target([
					'target' => 'post_meta',
					'post_id' => (int) $post->ID,
					'post_type' => (string) $post->post_type,
					'section_instance' => (string) $instance_id,
					'section_type' => $section_type,
					'field_key' => $field_key,
					'slot' => $slot,
					'context' => $image_context,
				]);
			}
		}
	}

	return $out;
}

function lf_ai_studio_build_hybrid_image_generation_plan(int $limit = 12): array {
	$limit = max(1, min(60, $limit));
	$targets = lf_ai_studio_collect_missing_image_targets($limit);
	return [
		'mode' => 'hybrid',
		'generate_only_missing' => true,
		'limit' => $limit,
		'targets' => $targets,
		'preferred_model' => 'flux-schnell',
		'hq_hero_model' => 'flux-dev',
	];
}

function lf_ai_studio_maybe_inject_section_images(array $registry, string $section_type, array $existing_settings, array $incoming_fields, array $matches, array $context, array &$assigned): array {
	if (!function_exists('lf_image_intelligence_registry_image_fields')) {
		return $incoming_fields;
	}
	$registry_section = $registry[$section_type] ?? null;
	if (!is_array($registry_section)) {
		return $incoming_fields;
	}
	$image_fields = lf_image_intelligence_registry_image_fields($registry_section);
	if (empty($image_fields)) {
		return $incoming_fields;
	}
	foreach ($image_fields as $field_key) {
		$current_value = $existing_settings[$field_key] ?? '';
		$incoming_value = $incoming_fields[$field_key] ?? '';
		if (!function_exists('lf_image_intelligence_empty_image_value')) {
			continue;
		}
		if (!lf_image_intelligence_empty_image_value($current_value) || !lf_image_intelligence_empty_image_value($incoming_value)) {
			continue;
		}
		if (!function_exists('lf_image_intelligence_slot_for_section_field')) {
			continue;
		}
		$slot = lf_image_intelligence_slot_for_section_field($section_type, $field_key);
		$image_id = absint($matches[$slot] ?? 0);
		if ($image_id <= 0) {
			continue;
		}
		$incoming_fields[$field_key] = $image_id;
		$assigned[] = [
			'image_id' => $image_id,
			'context' => $context,
		];
	}
	return $incoming_fields;
}

function lf_ai_studio_resolve_homepage_field_key(string $field_key, array $config, array $registry): ?array {
	if ($field_key === '' || strpos($field_key, '.') !== false) {
		return null;
	}
	foreach ($config as $section_id => $section_settings) {
		if (!is_array($section_settings) || !isset($registry[$section_id])) {
			continue;
		}
		$allowed = lf_ai_studio_homepage_allowed_field_keys((string) $section_id, $registry[$section_id]);
		if (in_array($field_key, $allowed, true)) {
			return [
				'section_id' => (string) $section_id,
				'field_key' => $field_key,
			];
		}
	}
	return null;
}

function lf_ai_studio_resolve_homepage_section_id_alias(string $section_id, array $config): string {
	$legacy_id = trim($section_id);
	if ($legacy_id === '') {
		return '';
	}
	$direct_map = [
		'hero_section' => 'hero',
		'trust_signals' => 'trust_bar',
		'trust_signals_bar' => 'trust_bar',
		'services_overview' => 'service_intro',
		'why_choose_us' => 'benefits',
		'process_steps' => 'process',
		'reviews_section' => 'trust_reviews',
		'faq_section' => 'faq_accordion',
		'additional_services' => 'related_links',
		'service_areas' => 'map_nap',
		'final_cta' => 'cta',
	];
	if (isset($direct_map[$legacy_id])) {
		return $direct_map[$legacy_id];
	}
	$type_map = [
		'intro' => ['content', 'content_centered', 'content_image', 'image_content'],
		'about' => ['content', 'content_centered', 'content_image', 'image_content'],
		'about_snippet' => ['content', 'content_centered', 'content_image', 'image_content'],
		'services' => ['service_grid', 'services_offered_here', 'service_intro', 'service_details'],
		'services_overview' => ['service_grid', 'services_offered_here', 'service_intro', 'service_details'],
		'testimonials' => ['trust_bar', 'trust_reviews', 'reviews', 'social_proof'],
		'faq' => ['faq_accordion'],
	];
	$candidates = $type_map[$legacy_id] ?? [];
	if (empty($candidates)) {
		return '';
	}
	foreach ($config as $sid => $row) {
		if (!is_string($sid)) {
			continue;
		}
		$base = function_exists('lf_homepage_base_section_type') ? lf_homepage_base_section_type($sid) : $sid;
		if ($base !== '' && in_array($base, $candidates, true)) {
			return $sid;
		}
		$type = is_array($row) ? (string) ($row['section_type'] ?? $row['type'] ?? '') : '';
		if ($type !== '' && in_array($type, $candidates, true)) {
			return $sid;
		}
	}
	return '';
}

function lf_ai_studio_resolve_post_field_key(string $field_key, array $sections, array $registry): ?array {
	if ($field_key === '' || strpos($field_key, '.') !== false) {
		return null;
	}
	$matches = [];
	foreach ($sections as $instance_id => $section) {
		if (!is_array($section)) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		if ($type === '' || !isset($registry[$type])) {
			continue;
		}
		$allowed = lf_ai_studio_homepage_allowed_field_keys($type, $registry[$type]);
		if (in_array($field_key, $allowed, true)) {
			$matches[] = [
				'instance_id' => (string) $instance_id,
				'field_key' => $field_key,
				'section_type' => $type,
			];
		}
	}
	if (empty($matches)) {
		return null;
	}
	if (count($matches) === 1) {
		return $matches[0];
	}
	$prefix = strtolower((string) strtok($field_key, '_'));
	if ($prefix !== '') {
		foreach ($matches as $match) {
			$type = strtolower((string) ($match['section_type'] ?? ''));
			if ($type !== '' && strpos($type, $prefix) !== false) {
				return $match;
			}
		}
	}
	return $matches[0];
}

function lf_ai_studio_prevalidate_orchestrator_updates(array $response): array {
	$updates = $response['updates'] ?? [];
	if (!is_array($updates)) {
		return [__('Missing updates array.', 'leadsforward-core')];
	}
	if (empty($updates)) {
		return [__('Updates array is empty.', 'leadsforward-core')];
	}
	$errors = [];
	$global_seen = [];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$homepage_config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
	$min_word_count = (int) apply_filters('lf_ai_studio_min_word_count', 0);
	$min_word_count = max(0, $min_word_count);
	$legacy_homepage_alias_types = [
		'intro' => ['content', 'content_centered', 'content_image', 'image_content'],
		'about' => ['content', 'content_centered', 'content_image', 'image_content'],
		'about_snippet' => ['content', 'content_centered', 'content_image', 'image_content'],
		'services' => ['service_grid', 'services_offered_here', 'service_intro', 'service_details'],
		'services_overview' => ['service_grid', 'services_offered_here', 'service_intro', 'service_details'],
		'testimonials' => ['trust_bar', 'trust_reviews', 'reviews', 'social_proof'],
		'faq' => ['faq_accordion'],
	];
	$resolve_legacy_homepage_section_id = static function (string $legacy_id) use ($homepage_config): string {
		$legacy_id = trim($legacy_id);
		if ($legacy_id === '') {
			return '';
		}
		$type_map = [
			'intro' => ['content', 'content_centered', 'content_image', 'image_content'],
			'about' => ['content', 'content_centered', 'content_image', 'image_content'],
			'about_snippet' => ['content', 'content_centered', 'content_image', 'image_content'],
			'services' => ['service_grid', 'services_offered_here', 'service_intro', 'service_details'],
			'services_overview' => ['service_grid', 'services_offered_here', 'service_intro', 'service_details'],
			'testimonials' => ['trust_bar', 'trust_reviews', 'reviews', 'social_proof'],
			'faq' => ['faq_accordion'],
		];
		$candidates = $type_map[$legacy_id] ?? [];
		if (empty($candidates)) {
			return '';
		}
		foreach ($homepage_config as $section_id => $section_settings) {
			$type = (string) ($section_settings['type'] ?? '');
			if ($type === '' && function_exists('lf_homepage_base_section_type')) {
				$type = (string) lf_homepage_base_section_type((string) $section_id);
			}
			if ($type !== '' && in_array($type, $candidates, true)) {
				return (string) $section_id;
			}
		}
		return '';
	};
	$normalize_legacy_field_key = static function (string $section_id, string $field_key): array {
		$section_id = trim($section_id);
		$field_key = trim($field_key);
		if ($section_id === 'cta') {
			if ($field_key === 'section_heading') {
				$field_key = 'cta_headline';
			} elseif ($field_key === 'section_intro' || $field_key === 'section_subheadline') {
				$field_key = 'cta_subheadline';
			} elseif ($field_key === 'cta_primary') {
				$field_key = 'cta_primary_override';
			} elseif ($field_key === 'cta_secondary') {
				$field_key = 'cta_secondary_override';
			}
		}
		return [$section_id, $field_key];
	};
	$page_word_counts = [];
	$page_labels = [];
	$pages_seen = [];
	$add_word_count = static function (string $page_key, string $label, $value) use (&$page_word_counts, &$page_labels): void {
		if (!is_scalar($value)) {
			return;
		}
		$text = trim(wp_strip_all_tags((string) $value));
		if ($text === '') {
			return;
		}
		$parts = preg_split('/\s+/', $text);
		$count = is_array($parts) ? count(array_filter($parts, 'strlen')) : 0;
		if ($count <= 0) {
			return;
		}
		$page_word_counts[$page_key] = ($page_word_counts[$page_key] ?? 0) + $count;
		if ($label !== '' && !isset($page_labels[$page_key])) {
			$page_labels[$page_key] = $label;
		}
	};
	foreach ($updates as $index => $update) {
		if (!is_array($update)) {
			$errors[] = sprintf(__('Update at index %d must be an object.', 'leadsforward-core'), $index);
			continue;
		}
		$target = (string) ($update['target'] ?? '');
		$id = $update['id'] ?? '';
		$fields = $update['fields'] ?? $update['data'] ?? [];
		if (!is_array($fields)) {
			$errors[] = sprintf(__('Update at index %d is missing fields.', 'leadsforward-core'), $index);
			continue;
		}
		if ($target === 'options' && $id === 'homepage') {
			$pages_seen['homepage'] = __('Homepage', 'leadsforward-core');
			$field_meta = [];
			foreach ($fields as $key => $value) {
				if (!is_string($key)) {
					continue;
				}
				$parts = explode('.', $key, 2);
				if (count($parts) !== 2) {
					$resolved = lf_ai_studio_resolve_homepage_field_key(trim($key), $homepage_config, $registry);
					if (!is_array($resolved)) {
						$errors[] = sprintf(__('Homepage field "%s" must use section.field notation.', 'leadsforward-core'), $key);
						continue;
					}
					$section_id = (string) ($resolved['section_id'] ?? '');
					$field_key = (string) ($resolved['field_key'] ?? '');
				} else {
					$section_id = trim($parts[0]);
					$field_key = trim($parts[1]);
				}
				[$section_id, $field_key] = $normalize_legacy_field_key($section_id, $field_key);
				if (!isset($homepage_config[$section_id]) || !isset($registry[$section_id])) {
					$remapped_section_id = $resolve_legacy_homepage_section_id($section_id);
					if ($remapped_section_id !== '') {
						$section_id = $remapped_section_id;
					}
				}
				if (!isset($homepage_config[$section_id]) || !isset($registry[$section_id])) {
					// Tolerate unknown/legacy section IDs from orchestrators and skip safely.
					continue;
				}
				$allowed = lf_ai_studio_homepage_allowed_field_keys($section_id, $registry[$section_id]);
				if (!in_array($field_key, $allowed, true)) {
					// Tolerate unknown/legacy field keys and skip safely.
					continue;
				}
				$add_word_count('homepage', __('Homepage', 'leadsforward-core'), $value);
			}
			continue;
		}
		if ($target === 'post_meta') {
			$post_id = absint($id);
			if (!$post_id) {
				continue;
			}
			$post = $post_id ? get_post($post_id) : null;
			if (!$post instanceof \WP_Post) {
				$errors[] = sprintf(__('Post update for id %d not found.', 'leadsforward-core'), $post_id);
				continue;
			}
			$pages_seen['post:' . $post_id] = $post->post_title ?: sprintf(__('Post %d', 'leadsforward-core'), $post_id);
			$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
			if ($context === '') {
				$errors[] = sprintf(__('Post update for id %d has no builder context.', 'leadsforward-core'), $post_id);
				continue;
			}
			$config = lf_pb_get_post_config($post_id, $context);
			$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
			foreach ($fields as $key => $value) {
				if (!is_string($key)) {
					continue;
				}
				$parts = explode('.', $key, 2);
				if (count($parts) !== 2) {
					$resolved = lf_ai_studio_resolve_post_field_key(trim($key), $sections, $registry);
					if (!is_array($resolved)) {
						$errors[] = sprintf(__('Post field "%s" must use section.field notation.', 'leadsforward-core'), $key);
						continue;
					}
					$instance_id = (string) ($resolved['instance_id'] ?? '');
					$field_key = (string) ($resolved['field_key'] ?? '');
				} else {
					$instance_id = trim($parts[0]);
					$field_key = trim($parts[1]);
				}
				$section = $sections[$instance_id] ?? null;
				if (!is_array($section)) {
					foreach ($sections as $maybe_id => $maybe_section) {
						if (is_array($maybe_section) && ($maybe_section['type'] ?? '') === $instance_id) {
							$section = $maybe_section;
							$instance_id = (string) $maybe_id;
							break;
						}
					}
				}
				$type = is_array($section) ? (string) ($section['type'] ?? '') : '';
				if ($type === '' || !isset($registry[$type])) {
					// Tolerate unknown/legacy section references and skip safely.
					continue;
				}
				$allowed = lf_ai_studio_homepage_allowed_field_keys($type, $registry[$type]);
				if (!in_array($field_key, $allowed, true)) {
					// Tolerate unknown/legacy field keys and skip safely.
					continue;
				}
				$field_meta[$key] = [
					'field_key' => $field_key,
					'section_type' => $type,
				];
				$add_word_count('post:' . $post_id, $post->post_title ?: sprintf(__('Post %d', 'leadsforward-core'), $post_id), $value);
			}
			$seen = [];
			foreach ($fields as $key => $value) {
				if (!is_string($key) || !is_scalar($value)) {
					continue;
				}
				$meta = $field_meta[$key] ?? null;
				if (!is_array($meta)) {
					// Fall back to raw key checks if registry metadata is missing.
					if (!lf_ai_studio_should_enforce_uniqueness_on_field((string) $key, 'text')) {
						continue;
					}
				} else {
					$field_type = lf_ai_studio_registry_field_type($registry, (string) ($meta['section_type'] ?? ''), (string) ($meta['field_key'] ?? ''));
					if (!lf_ai_studio_should_enforce_uniqueness_on_field((string) ($meta['field_key'] ?? ''), $field_type)) {
						continue;
					}
				}
				$text = trim((string) $value);
				if ($text === '') {
					continue;
				}
				$plain = strtolower(wp_strip_all_tags($text));
				if (strpos($plain, 'in your area') !== false) {
					$errors[] = sprintf(__('Generic phrase "in your area" found for post %d.', 'leadsforward-core'), $post_id);
					break;
				}
				if (strpos($key, 'heading') !== false || strpos($key, 'headline') !== false) {
					if (lf_ai_studio_is_generic_heading_value($text, $post)) {
						$errors[] = sprintf(__('Generic heading value found for post %d.', 'leadsforward-core'), $post_id);
						break;
					}
				}
				$norm = lf_ai_studio_normalize_for_uniqueness($text);
				if ($norm !== '' && isset($seen[$norm])) {
					$errors[] = sprintf(__('Duplicate content detected in post %d.', 'leadsforward-core'), $post_id);
					break;
				}
				if ($norm !== '' && isset($global_seen[$norm]) && $global_seen[$norm] !== $post_id) {
					$errors[] = sprintf(__('Duplicate content detected across pages (post %d).', 'leadsforward-core'), $post_id);
					break;
				}
				$seen[$norm] = true;
				if ($norm !== '') {
					$global_seen[$norm] = $post_id;
				}
			}
			continue;
		}
		if ($target === 'faq') {
			$allowed_keys = ['question', 'answer'];
			foreach (array_keys($fields) as $key) {
				if (!in_array($key, $allowed_keys, true)) {
					$errors[] = __('FAQ update contains unsupported fields.', 'leadsforward-core');
					break;
				}
			}
			continue;
		}
		if ($target === 'service_meta') {
			$post_id = absint($id);
			if (!$post_id) {
				continue;
			}
			$post = $post_id ? get_post($post_id) : null;
			if (!$post instanceof \WP_Post || $post->post_type !== 'lf_service') {
				$errors[] = sprintf(__('Service meta update for id %d not found.', 'leadsforward-core'), $post_id);
			}
			continue;
		}
		continue;
	}
	foreach ($pages_seen as $page_key => $label) {
		$count = $page_word_counts[$page_key] ?? 0;
		if ($count < $min_word_count) {
			$errors[] = sprintf(__('Page "%1$s" has only %2$d words; minimum is %3$d.', 'leadsforward-core'), $label, $count, $min_word_count);
		}
	}
	return array_values(array_unique($errors));
}

function lf_ai_studio_extract_primary_post_content(array $sections, array $order): array {
	$content = '';
	$excerpt = '';
	foreach ($order as $section_id) {
		$section = $sections[ $section_id ] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		if (!in_array($type, ['content', 'content_centered', 'content_image', 'image_content', 'content_image_a', 'image_content_b', 'content_image_c'], true)) {
			continue;
		}
		$body = trim((string) ($settings['section_body'] ?? ''));
		$body_secondary = trim((string) ($settings['section_body_secondary'] ?? ''));
		$intro = trim((string) ($settings['section_intro'] ?? ''));
		$content = $body_secondary !== '' && $body !== '' ? ( $body . "\n\n" . $body_secondary ) : ( $body !== '' ? $body : $body_secondary );
		if ($excerpt === '') {
			$excerpt = $intro;
		}
		if ($content !== '') {
			break;
		}
	}
	return [
		'content' => $content,
		'excerpt' => $excerpt,
	];
}

function lf_apply_orchestrator_updates(array $response): array {
	$updates = $response['updates'] ?? [];
	if (!is_array($updates)) {
		return ['success' => false, 'summary' => __('Missing updates array.', 'leadsforward-core'), 'changes' => [], 'errors' => [__('Missing updates array.', 'leadsforward-core')]];
	}
	$preflight_errors = lf_ai_studio_prevalidate_orchestrator_updates($response);
	if (!empty($preflight_errors)) {
		return [
			'success' => false,
			'summary' => __('Validation failed before apply.', 'leadsforward-core'),
			'changes' => [],
			'errors' => $preflight_errors,
		];
	}
	$errors = [];
	$changes = ['homepage' => false, 'posts' => [], 'faqs' => []];
	$pages_updated = 0;
	$fields_updated = 0;
	$homepage_fields_count = 0;
	$update_counts = ['homepage' => 0, 'post_meta' => 0, 'faq' => 0, 'service_meta' => 0];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$homepage_config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];

	$homepage_updates = [];
	$homepage_fields = [];
	$post_updates = [];
	$faq_updates = [];
	$service_meta_updates = [];
	$service_posts_for_short_desc = [];
	$blog_posts_for_title = [];
	$assigned_images = [];
	$staged_homepage_config = null;
	$staged_post_meta_updates = [];
	$staged_featured_updates = [];
	$debug_homepage_drops = [
		'invalid_key' => 0,
		'unknown_section' => 0,
		'unknown_field' => 0,
	];
	$debug_homepage_drop_samples = [
		'invalid_key' => [],
		'unknown_section' => [],
		'unknown_field' => [],
	];

	$manifest_for_tokens = lf_ai_studio_get_manifest();
	$tk_niche = is_array($manifest_for_tokens) ? (string) ($manifest_for_tokens['business']['niche'] ?? '') : '';
	$tk_home_pk = is_array($manifest_for_tokens) ? trim((string) ($manifest_for_tokens['homepage']['primary_keyword'] ?? '')) : '';
	$homepage_token_ctx = [
		'business_name' => trim((string) get_option('lf_business_name', get_bloginfo('name'))),
		'city_region' => trim((string) get_option('lf_city_region', get_option('lf_homepage_city', ''))),
		'primary_keyword' => $tk_home_pk !== '' ? $tk_home_pk : trim((string) get_option('lf_primary_keyword', '')),
		'niche' => $tk_niche,
	];

	foreach ($updates as $index => $update) {
		if (!is_array($update)) {
			$errors[] = sprintf(__('Update at index %d must be an object.', 'leadsforward-core'), $index);
			continue;
		}
		$target = (string) ($update['target'] ?? '');
		$id = $update['id'] ?? '';
		$fields = $update['fields'] ?? $update['data'] ?? [];
		if (!is_array($fields)) {
			$errors[] = sprintf(__('Update at index %d is missing fields.', 'leadsforward-core'), $index);
			continue;
		}
		if ($target === 'options' && $id === 'homepage') {
			$homepage_updates[] = $update;
			$update_counts['homepage']++;
			continue;
		}
		if ($target === 'post_meta') {
			$post_id = absint($id);
			if (!$post_id) {
				continue;
			}
			$post_updates[] = $update;
			$update_counts['post_meta']++;
			continue;
		}
		if ($target === 'faq') {
			$faq_updates[] = $update;
			$update_counts['faq']++;
			continue;
		}
		if ($target === 'service_meta') {
			$post_id = absint($id);
			if (!$post_id) {
				continue;
			}
			$service_meta_updates[] = $update;
			$update_counts['service_meta']++;
			continue;
		}
		continue;
	}

	if (!empty($homepage_updates) && function_exists('lf_get_homepage_section_config')) {
		$config = $homepage_config;
		$homepage_image_context = lf_ai_studio_homepage_image_context();
		$homepage_matches = function_exists('lf_match_images_for_context')
			? lf_match_images_for_context($homepage_image_context)
			: [];
		foreach ($homepage_updates as $update) {
			$fields = $update['fields'] ?? $update['data'] ?? [];
			foreach ($fields as $key => $value) {
				if (!is_string($key)) {
					continue;
				}
				if ($key === 'question' || $key === 'answer') {
					continue;
				}
				$parts = explode('.', $key, 2);
				if (count($parts) !== 2) {
					$resolved = lf_ai_studio_resolve_homepage_field_key(trim($key), $config, $registry);
					if (is_array($resolved)) {
						$section_id = (string) ($resolved['section_id'] ?? '');
						$field_key = (string) ($resolved['field_key'] ?? '');
					} else {
						if (defined('WP_DEBUG') && WP_DEBUG) {
							$debug_homepage_drops['invalid_key']++;
							if (count($debug_homepage_drop_samples['invalid_key']) < 5) {
								$debug_homepage_drop_samples['invalid_key'][] = (string) $key;
							}
						}
						$errors[] = sprintf(__('Homepage field "%s" must use section.field notation.', 'leadsforward-core'), (string) $key);
						continue;
					}
				} else {
					$section_id = trim($parts[0]);
					$field_key = trim($parts[1]);
				}
				if ($section_id === '' || $field_key === '') {
					continue;
				}
				if (!isset($config[$section_id]) || !isset($registry[$section_id])) {
					$resolved = lf_ai_studio_resolve_homepage_section_id_alias($section_id, $config);
					if ($resolved !== '' && isset($config[$resolved]) && isset($registry[$resolved])) {
						$section_id = $resolved;
					}
				}
				if (!isset($config[$section_id]) || !isset($registry[$section_id])) {
					if (defined('WP_DEBUG') && WP_DEBUG) {
						$debug_homepage_drops['unknown_section']++;
						if (count($debug_homepage_drop_samples['unknown_section']) < 5) {
							$debug_homepage_drop_samples['unknown_section'][] = (string) $section_id;
						}
					}
					// Ignore unknown/legacy homepage section ids instead of failing entire callback.
					continue;
				}
				$allowed = lf_ai_studio_homepage_allowed_field_keys($section_id, $registry[$section_id]);
				if (!in_array($field_key, $allowed, true)) {
					if (defined('WP_DEBUG') && WP_DEBUG) {
						$debug_homepage_drops['unknown_field']++;
						if (count($debug_homepage_drop_samples['unknown_field']) < 5) {
							$debug_homepage_drop_samples['unknown_field'][] = $section_id . '.' . $field_key;
						}
					}
					// Ignore unsupported homepage fields coming from older orchestrator prompts.
					continue;
				}
				if (!isset($homepage_fields[$section_id])) {
					$homepage_fields[$section_id] = [];
				}
				$normalized_value = lf_ai_studio_normalize_value($value);
				$field_type = lf_ai_studio_registry_field_type($registry, $section_id, $field_key);
				if ($field_type === 'list') {
					$normalized_value = lf_ai_studio_coerce_list_value($normalized_value);
				}
				$normalized_value = lf_ai_studio_clean_value_for_field($normalized_value, $field_type, $field_key);
				$normalized_value = lf_ai_studio_strip_llm_tokens_from_mixed_value($normalized_value, $homepage_token_ctx, $field_type);
				if ($section_id === 'faq_accordion' && $field_key === 'faq_selected_ids') {
					$normalized_value = lf_ai_studio_normalize_faq_selected_ids_value($normalized_value);
				}
				$homepage_fields[$section_id][$field_key] = $normalized_value;
				$homepage_fields_count++;
				$fields_updated++;
			}
		}
		if (defined('WP_DEBUG') && WP_DEBUG) {
			$section_counts = [];
			$field_samples = [];
			foreach ($homepage_fields as $sid => $fields) {
				if (!is_array($fields)) {
					continue;
				}
				$section_counts[$sid] = count($fields);
				foreach ($fields as $key => $value) {
					if (count($field_samples) >= 8) {
						break 2;
					}
					$preview = '';
					if (is_array($value)) {
						$preview = 'array(' . count($value) . ')';
					} else {
						$preview = sanitize_text_field((string) $value);
						if (strlen($preview) > 140) {
							$preview = substr($preview, 0, 140) . '...';
						}
					}
					$field_samples[] = [
						'section' => (string) $sid,
						'field' => (string) $key,
						'preview' => $preview,
					];
				}
			}
			error_log('LF ORCH DEBUG: homepage_field_counts ' . wp_json_encode($section_counts));
			error_log('LF ORCH DEBUG: homepage_drop_counts ' . wp_json_encode($debug_homepage_drops));
			$drop_samples_filtered = array_filter($debug_homepage_drop_samples, static function ($items): bool {
				return is_array($items) && !empty($items);
			});
			if (!empty($drop_samples_filtered)) {
				error_log('LF ORCH DEBUG: homepage_drop_samples ' . wp_json_encode($drop_samples_filtered));
			}
			if (!empty($field_samples)) {
				error_log('LF ORCH DEBUG: homepage_field_samples ' . wp_json_encode($field_samples));
			}
		}
		foreach ($homepage_fields as $section_id => $fields) {
			if (!is_array($fields) || !isset($config[$section_id])) {
				continue;
			}
			$fields = lf_ai_studio_maybe_inject_section_images(
				$registry,
				$section_id,
				is_array($config[$section_id]) ? $config[$section_id] : [],
				$fields,
				$homepage_matches,
				$homepage_image_context,
				$assigned_images
			);
			$fields = lf_ai_studio_enforce_section_quality($fields, $section_id, $registry);
			$config[$section_id] = array_merge(
				$config[$section_id],
				lf_sections_sanitize_settings($section_id, $fields)
			);
		}
		foreach ($config as $section_id => $section_settings) {
			if (!is_array($section_settings) || !isset($registry[$section_id])) {
				continue;
			}
			$existing = is_array($section_settings) ? $section_settings : [];
			$injected = lf_ai_studio_maybe_inject_section_images(
				$registry,
				(string) $section_id,
				$existing,
				[],
				$homepage_matches,
				$homepage_image_context,
				$assigned_images
			);
			if (!empty($injected)) {
				$injected = lf_ai_studio_enforce_section_quality($injected, (string) $section_id, $registry);
				$config[$section_id] = array_merge($existing, lf_sections_sanitize_settings((string) $section_id, $injected));
			}
		}
		foreach ($config as $section_id => $section_settings) {
			if (!is_array($section_settings) || !isset($registry[$section_id])) {
				continue;
			}
			$normalized_settings = lf_ai_studio_enforce_section_quality($section_settings, (string) $section_id, $registry);
			$config[$section_id] = array_merge($section_settings, lf_sections_sanitize_settings((string) $section_id, $normalized_settings));
		}
		$config = lf_ai_studio_deduplicate_headings($config, $registry, false);
		if ($config !== $homepage_config) {
			$staged_homepage_config = $config;
			$changes['homepage'] = true;
			$pages_updated++;
		}
	}

	foreach ($post_updates as $update) {
		$post_id = absint($update['id'] ?? 0);
		$post = $post_id ? get_post($post_id) : null;
		if (!$post instanceof \WP_Post) {
			$errors[] = sprintf(__('Post update for id %d not found.', 'leadsforward-core'), $post_id);
			continue;
		}
		$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
		if ($context === '') {
			$errors[] = sprintf(__('Post update for id %d has no builder context.', 'leadsforward-core'), $post_id);
			continue;
		}
		$config = lf_pb_get_post_config($post_id, $context);
		$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
		$order = is_array($config['order'] ?? null) ? $config['order'] : [];
		$incoming = $update['fields'] ?? $update['data'] ?? [];
		if (!is_array($incoming)) {
			continue;
		}
		$post_image_context = function_exists('lf_image_intelligence_build_context_for_post')
			? lf_image_intelligence_build_context_for_post($post)
			: [];
		$post_matches = (!empty($post_image_context) && function_exists('lf_match_images_for_context'))
			? lf_match_images_for_context($post_image_context)
			: [];
		$post_pk = trim((string) get_post_meta($post_id, '_lf_seo_primary_keyword', true));
		$post_token_ctx = [
			'business_name' => $homepage_token_ctx['business_name'],
			'city_region' => $post->post_type === 'lf_service_area' ? trim((string) $post->post_title) : $homepage_token_ctx['city_region'],
			'primary_keyword' => $post_pk !== '' ? $post_pk : $homepage_token_ctx['primary_keyword'],
			'niche' => $homepage_token_ctx['niche'],
		];
		$incoming_by_instance = [];
		foreach ($incoming as $key => $value) {
			if (!is_string($key)) {
				continue;
			}
			$parts = explode('.', $key, 2);
			if (count($parts) !== 2) {
				$resolved = lf_ai_studio_resolve_post_field_key(trim((string) $key), $sections, $registry);
				if (!is_array($resolved)) {
					$errors[] = sprintf(__('Post field "%s" must use section.field notation.', 'leadsforward-core'), (string) $key);
					continue;
				}
				$instance_id = (string) ($resolved['instance_id'] ?? '');
				$field_key = (string) ($resolved['field_key'] ?? '');
			} else {
				$instance_id = trim($parts[0]);
				$field_key = trim($parts[1]);
			}
			if ($instance_id === '' || $field_key === '') {
				continue;
			}
			$section = $sections[$instance_id] ?? null;
			if (!is_array($section)) {
				foreach ($sections as $maybe_id => $maybe_section) {
					if (is_array($maybe_section) && ($maybe_section['type'] ?? '') === $instance_id) {
						$section = $maybe_section;
						$instance_id = $maybe_id;
						break;
					}
				}
			}
			$type = is_array($section) ? (string) ($section['type'] ?? '') : '';
			if ($type === '' || !isset($registry[ $type ])) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log(sprintf('LF DEBUG: Dropped section "%s" on post %d (unregistered or missing type).', $instance_id, $post_id));
				}
				$errors[] = sprintf(__('Section "%s" is not registered for post %d.', 'leadsforward-core'), $instance_id, $post_id);
				continue;
			}
			$allowed = lf_ai_studio_homepage_allowed_field_keys($type, $registry[ $type ]);
			if (!in_array($field_key, $allowed, true)) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log(sprintf('LF DEBUG: Dropped field "%s" on post %d (section %s).', $field_key, $post_id, $instance_id));
				}
				$errors[] = sprintf(__('Field "%s" is not allowed for section "%s".', 'leadsforward-core'), $field_key, $instance_id);
				continue;
			}
			if (!isset($incoming_by_instance[$instance_id])) {
				$incoming_by_instance[$instance_id] = [];
			}
			$normalized_value = lf_ai_studio_normalize_value($value);
			$field_type = lf_ai_studio_registry_field_type($registry, $type, $field_key);
			if ($field_type === 'list') {
				$normalized_value = lf_ai_studio_coerce_list_value($normalized_value);
			}
			$normalized_value = lf_ai_studio_clean_value_for_field($normalized_value, $field_type, $field_key);
			$normalized_value = lf_ai_studio_strip_llm_tokens_from_mixed_value($normalized_value, $post_token_ctx, $field_type);
			if ($type === 'faq_accordion' && $field_key === 'faq_selected_ids') {
				$normalized_value = lf_ai_studio_normalize_faq_selected_ids_value($normalized_value);
			}
			$incoming_by_instance[$instance_id][$field_key] = $normalized_value;
			$fields_updated++;
		}
		foreach ($incoming_by_instance as $instance_id => $fields) {
			$section = $sections[$instance_id] ?? null;
			if (!is_array($section)) {
				continue;
			}
			$type = (string) ($section['type'] ?? '');
			$fields = lf_ai_studio_maybe_inject_section_images(
				$registry,
				$type,
				is_array($section['settings'] ?? null) ? $section['settings'] : [],
				$fields,
				$post_matches,
				$post_image_context,
				$assigned_images
			);
			$sections[$instance_id]['settings'] = array_merge(
				$section['settings'] ?? [],
				lf_sections_sanitize_settings($type, $fields)
			);
		}
		foreach ($order as $instance_id) {
			$section = $sections[$instance_id] ?? null;
			if (!is_array($section) || empty($section['enabled'])) {
				continue;
			}
			$type = (string) ($section['type'] ?? '');
			if ($type === '' || !isset($registry[$type])) {
				continue;
			}
			$current_settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
			$injected = lf_ai_studio_maybe_inject_section_images(
				$registry,
				$type,
				$current_settings,
				[],
				$post_matches,
				$post_image_context,
				$assigned_images
			);
			$merged_settings = array_merge(
				$current_settings,
				lf_sections_sanitize_settings($type, $injected)
			);
			$section_registry = isset($registry[$type]) && is_array($registry[$type]) ? $registry[$type] : [];
			$filled_settings = lf_ai_studio_fill_generic_section_copy($merged_settings, $post, $type, $section_registry);
			$link_result = lf_ai_studio_orchestrate_internal_links_for_settings($filled_settings, $type, $registry, $post);
			$final_settings = is_array($link_result['settings'] ?? null) ? $link_result['settings'] : $filled_settings;
			$final_settings = lf_ai_studio_enforce_section_quality($final_settings, $type, $registry);
			$sections[$instance_id]['settings'] = lf_sections_sanitize_settings($type, $final_settings);
		}
		$staged_post_meta_updates[$post_id] = [
			'order' => $order,
			'sections' => $sections,
			'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
		];
		$changes['posts'][] = $post_id;
		$pages_updated++;
		$featured_id = absint($post_matches['featured'] ?? 0);
		$current_thumb_id = (int) get_post_thumbnail_id($post_id);
		$current_thumb_placeholder = ($current_thumb_id > 0 && function_exists('lf_image_intelligence_is_placeholder_asset'))
			? lf_image_intelligence_is_placeholder_asset($current_thumb_id)
			: false;
		if ($featured_id > 0 && (!has_post_thumbnail($post_id) || $current_thumb_placeholder)) {
			$staged_featured_updates[$post_id] = $featured_id;
			$assigned_images[] = [
				'image_id' => $featured_id,
				'context' => $post_image_context,
			];
		}
		if ($post->post_type === 'lf_service') {
			$service_posts_for_short_desc[] = $post_id;
		}
		if ($post->post_type === 'post') {
			$blog_posts_for_title[] = $post_id;
		}
	}

	if (!empty($errors)) {
		return [
			'success' => false,
			'summary' => '',
			'changes' => $changes,
			'errors' => $errors,
		];
	}
	if (is_array($staged_homepage_config)) {
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $staged_homepage_config, true);
	}
	if (!empty($staged_post_meta_updates)) {
		foreach ($staged_post_meta_updates as $post_id => $pb_meta) {
			update_post_meta((int) $post_id, LF_PB_META_KEY, $pb_meta);
		}
		foreach ($staged_post_meta_updates as $post_id => $pb_meta) {
			$post_after = get_post((int) $post_id);
			if (!$post_after instanceof \WP_Post) {
				continue;
			}
			if (!in_array($post_after->post_type, ['post', 'lf_project'], true)) {
				continue;
			}
			$sections = is_array($pb_meta['sections'] ?? null) ? $pb_meta['sections'] : [];
			$order = is_array($pb_meta['order'] ?? null) ? $pb_meta['order'] : [];
			$derived = lf_ai_studio_extract_primary_post_content($sections, $order);
			$content = trim((string) ($derived['content'] ?? ''));
			$excerpt = trim((string) ($derived['excerpt'] ?? ''));
			if ($content !== '') {
				wp_update_post(
					[
						'ID'           => (int) $post_id,
						'post_content' => $content,
						'post_excerpt' => $excerpt,
					]
				);
			}
		}
	}
	if (!empty($staged_featured_updates)) {
		foreach ($staged_featured_updates as $post_id => $image_id) {
			set_post_thumbnail((int) $post_id, (int) $image_id);
		}
	}
	if (!empty($faq_updates)) {
		$filtered = [];
		foreach ($faq_updates as $update) {
			$fields = $update['fields'] ?? $update['data'] ?? [];
			if (!is_array($fields)) {
				continue;
			}
			$allowed_keys = ['question', 'answer'];
			$filtered_fields = array_intersect_key($fields, array_flip($allowed_keys));
			foreach ($filtered_fields as $key => $value) {
				$normalized = lf_ai_studio_normalize_value($value);
				$field_type = ($key === 'answer') ? 'richtext' : 'text';
				$filtered_fields[$key] = lf_ai_studio_clean_value_for_field($normalized, $field_type, (string) $key);
			}
			if (count($filtered_fields) !== count($fields)) {
				$errors[] = __('FAQ update contains unsupported fields.', 'leadsforward-core');
			}
			$filtered[] = array_merge($update, ['fields' => $filtered_fields]);
		}
		$faq_changes = lf_ai_studio_apply_faq_updates(['updates' => $filtered]);
		if (!empty($faq_changes)) {
			$changes['faqs'] = $faq_changes;
		}
	}
	if (!empty($service_meta_updates)) {
		foreach ($service_meta_updates as $update) {
			$post_id = absint($update['id'] ?? 0);
			$post = $post_id ? get_post($post_id) : null;
			if (!$post instanceof \WP_Post || $post->post_type !== 'lf_service') {
				$errors[] = sprintf(__('Service meta update for id %d not found.', 'leadsforward-core'), $post_id);
				continue;
			}
			$fields = $update['fields'] ?? $update['data'] ?? [];
			if (!is_array($fields)) {
				continue;
			}
			$short_desc = '';
			if (isset($fields['lf_service_short_desc'])) {
				$short_desc = sanitize_textarea_field((string) $fields['lf_service_short_desc']);
			} elseif (isset($fields['short_desc'])) {
				$short_desc = sanitize_textarea_field((string) $fields['short_desc']);
			}
			if ($short_desc !== '') {
				if (function_exists('update_field')) {
					update_field('lf_service_short_desc', $short_desc, $post_id);
				} else {
					update_post_meta($post_id, 'lf_service_short_desc', $short_desc);
				}
				$fields_updated++;
			}
		}
	}
	if (!empty($service_posts_for_short_desc)) {
		foreach (array_unique($service_posts_for_short_desc) as $service_id) {
			$short_desc = lf_ai_studio_build_service_short_desc($service_id);
			if ($short_desc !== '') {
				if (function_exists('update_field')) {
					update_field('lf_service_short_desc', $short_desc, $service_id);
				} else {
					update_post_meta($service_id, 'lf_service_short_desc', $short_desc);
				}
			}
		}
	}
	if (!empty($blog_posts_for_title)) {
		foreach (array_unique($blog_posts_for_title) as $post_id) {
			lf_ai_studio_backfill_post_title_excerpt($post_id);
			lf_ai_studio_sync_blog_post_content_from_sections($post_id);
		}
	}
	if (!empty($assigned_images) && function_exists('lf_image_intelligence_maybe_set_alt_text')) {
		foreach ($assigned_images as $assignment) {
			$image_id = absint($assignment['image_id'] ?? 0);
			$context_for_alt = is_array($assignment['context'] ?? null) ? $assignment['context'] : [];
			if ($image_id > 0) {
				lf_image_intelligence_maybe_set_alt_text($image_id, $context_for_alt);
			}
		}
	}
	lf_ai_studio_fill_site_content_without_ai();
	lf_ai_studio_autofill_empty_faq_accordion_picks();
	if (function_exists('lf_seo_refresh_metadata_for_generated_content')) {
		lf_seo_refresh_metadata_for_generated_content();
	}

	$summary_parts = [];
	if ($changes['homepage']) {
		$summary_parts[] = __('Homepage updated', 'leadsforward-core');
	}
	if (!empty($changes['posts'])) {
		$summary_parts[] = sprintf(__('Posts updated: %d', 'leadsforward-core'), count($changes['posts']));
	}
	if (!empty($changes['faqs'])) {
		$summary_parts[] = sprintf(__('FAQs updated: %d', 'leadsforward-core'), count($changes['faqs']));
	}
	error_log('LF MANIFEST: updates applied ' . wp_json_encode([
		'homepage_updates' => $update_counts['homepage'],
		'post_updates' => $update_counts['post_meta'],
		'faq_updates' => $update_counts['faq'],
		'service_meta_updates' => $update_counts['service_meta'],
		'faq_created' => count($changes['faqs']),
		'homepage_fields_updated' => $homepage_fields_count,
	]));

	$log_manifest = lf_ai_studio_get_manifest();
	$log_manifest_present = !empty($log_manifest);
	$log_manifest_hash = $log_manifest_present ? lf_ai_studio_manifest_hash($log_manifest) : '';
	$log_services_count = $log_manifest_present && is_array($log_manifest['services'] ?? null) ? count($log_manifest['services']) : 0;
	$log_service_areas_count = $log_manifest_present && is_array($log_manifest['service_areas'] ?? null) ? count($log_manifest['service_areas']) : 0;
	update_option('lf_ai_last_generation_log', [
		'time' => current_time('mysql'),
		'pages_updated' => $pages_updated,
		'fields_updated' => $fields_updated,
		'manifest_present' => $log_manifest_present,
		'manifest_hash' => $log_manifest_hash,
		'manifest_schema_version' => $log_manifest_present ? LF_MANIFEST_SCHEMA_VERSION : '',
		'manifest_services_count' => $log_services_count,
		'manifest_service_areas_count' => $log_service_areas_count,
		'services_count' => $log_services_count,
		'service_areas_count' => $log_service_areas_count,
		'errors' => $errors,
	], false);

	return [
		'success' => empty($errors),
		'summary' => implode('; ', $summary_parts),
		'changes' => $changes,
		'errors' => $errors,
	];
}

function lf_ai_studio_extract_homepage_updates(array $payload): array {
	$updates = $payload['updates'] ?? [];
	if (!is_array($updates)) {
		return [];
	}
	$out = [];
	foreach ($updates as $update) {
		if (!is_array($update)) {
			continue;
		}
		$target = $update['target'] ?? '';
		$id = $update['id'] ?? '';
		if ($target !== 'options' || $id !== 'homepage') {
			continue;
		}
		$fields = $update['fields'] ?? $update['data'] ?? [];
		if (!is_array($fields)) {
			continue;
		}
		foreach ($fields as $key => $value) {
			if (!is_string($key)) {
				continue;
			}
			$parts = explode('.', $key, 2);
			if (count($parts) !== 2) {
				continue;
			}
			$section_id = trim($parts[0]);
			$field_key = trim($parts[1]);
			if ($section_id === '' || $field_key === '') {
				continue;
			}
			if (!isset($out[$section_id])) {
				$out[$section_id] = [];
			}
			$out[$section_id][$field_key] = $value;
		}
	}
	return $out;
}

function lf_ai_studio_apply_faq_updates(array $payload): array {
	$updates = $payload['updates'] ?? [];
	if (!is_array($updates)) {
		return [];
	}
	$changed = [];
	$question_to_id = [];
	$existing_question_to_id = lf_ai_studio_build_faq_lookup_map();
	foreach ($updates as $update) {
		if (!is_array($update)) {
			continue;
		}
		if (($update['target'] ?? '') !== 'faq') {
			continue;
		}
		$fields = $update['fields'] ?? $update['data'] ?? [];
		if (!is_array($fields)) {
			continue;
		}
		$question_raw = isset($fields['question']) ? (string) $fields['question'] : '';
		$answer_raw = isset($fields['answer']) ? (string) $fields['answer'] : '';
		$faq_ctx = [
			'business_name' => trim((string) get_option('lf_business_name', get_bloginfo('name'))),
			'city_region' => trim((string) get_option('lf_city_region', get_option('lf_homepage_city', ''))),
			'primary_keyword' => trim((string) get_option('lf_primary_keyword', '')),
			'niche' => (string) (defined('LF_HOMEPAGE_NICHE_OPTION') ? get_option(LF_HOMEPAGE_NICHE_OPTION, '') : ''),
		];
		$question_raw = lf_ai_studio_strip_llm_placeholder_tokens($question_raw, $faq_ctx);
		$answer_raw = lf_ai_studio_strip_llm_placeholder_tokens($answer_raw, $faq_ctx);
		$question = sanitize_text_field(lf_ai_studio_clean_text_field_value(lf_ai_studio_normalize_text($question_raw)));
		$answer = wp_kses_post(lf_ai_studio_normalize_text($answer_raw));
		if ($question === '' && $answer === '') {
			continue;
		}
		$question_key = lf_ai_studio_normalize_faq_question_key($question);
		$faq_id = isset($update['id']) ? absint($update['id']) : 0;
		if ($faq_id <= 0 && $question_key !== '' && !empty($question_to_id[$question_key])) {
			$faq_id = (int) $question_to_id[$question_key];
		}
		if ($faq_id <= 0 && $question_key !== '' && !empty($existing_question_to_id[$question_key])) {
			$faq_id = (int) $existing_question_to_id[$question_key];
		}
		if ($faq_id) {
			$post = get_post($faq_id);
			if (!$post instanceof \WP_Post || $post->post_type !== 'lf_faq') {
				continue;
			}
		} else {
			$faq_id = wp_insert_post([
				'post_type' => 'lf_faq',
				'post_status' => 'publish',
				'post_title' => $question !== '' ? $question : __('FAQ', 'leadsforward-core'),
			]);
			if (!$faq_id || is_wp_error($faq_id)) {
				continue;
			}
		}
		if ($question_key !== '') {
			$question_to_id[$question_key] = (int) $faq_id;
			$existing_question_to_id[$question_key] = (int) $faq_id;
		}
		if ($question !== '') {
			wp_update_post(['ID' => $faq_id, 'post_title' => $question]);
			if (function_exists('update_field')) {
				update_field('lf_faq_question', $question, $faq_id);
			} else {
				update_post_meta($faq_id, 'lf_faq_question', $question);
			}
		}
		if ($answer !== '') {
			wp_update_post(['ID' => $faq_id, 'post_content' => $answer]);
			if (function_exists('update_field')) {
				update_field('lf_faq_answer', $answer, $faq_id);
			} else {
				update_post_meta($faq_id, 'lf_faq_answer', $answer);
			}
		}
		$changed[] = (int) $faq_id;
	}
	return array_values(array_unique(array_map('intval', $changed)));
}

function lf_ai_studio_run_content_audit(string $source = ''): array {
	$report = lf_ai_studio_audit_site_content();
	if ($source !== '') {
		$report['source'] = $source;
	}
	return $report;
}

function lf_ai_studio_store_audit_report(array $report, int $job_id = 0): void {
	update_option('lf_ai_studio_last_audit', $report, false);
	if ($job_id) {
		update_post_meta($job_id, 'lf_ai_job_audit', $report);
	}
}

function lf_ai_studio_maybe_requeue_from_audit(int $job_id, array $report): array {
	$auto = get_option('lf_ai_studio_auto_requeue', '1') === '1';
	if (!$auto || !$job_id) {
		return [];
	}
	$is_repair_job = (int) get_post_meta($job_id, 'lf_ai_job_repair', true) === 1;
	if ($is_repair_job) {
		return [];
	}
	$pages = $report['pages'] ?? [];
	if (!is_array($pages) || empty($pages)) {
		return [];
	}
	$has_issues = false;
	foreach ($pages as $page) {
		if (!empty($page['issues'])) {
			$has_issues = true;
			break;
		}
	}
	if (!$has_issues) {
		return [];
	}
	$request = get_post_meta($job_id, 'lf_ai_job_request', true);
	if (is_array($request) && !empty($request['repair_only'])) {
		return [];
	}
	$request_id = '';
	if (is_array($request) && !empty($request['request_id'])) {
		$request_id = sanitize_text_field((string) $request['request_id']);
	}
	if ($request_id === '') {
		$request_id = sanitize_text_field((string) get_post_meta($job_id, 'lf_ai_job_request_id', true));
	}
	if ($request_id === '') {
		return [];
	}
	$repair_lock_key = 'lf_ai_repair_lock_' . md5($request_id);
	if (get_transient($repair_lock_key)) {
		return [];
	}
	set_transient($repair_lock_key, 1, 10 * MINUTE_IN_SECONDS);
	$root_job_id = $job_id;
	$cursor = $job_id;
	$guard = 0;
	while ($cursor > 0 && $guard < 20) {
		$parent = (int) get_post_meta($cursor, 'lf_ai_job_parent', true);
		if ($parent <= 0 || $parent === $cursor) {
			break;
		}
		$root_job_id = $parent;
		$cursor = $parent;
		$guard++;
	}
	$requeue_count = (int) get_post_meta($root_job_id, 'lf_ai_job_requeue_count', true);
	if ($requeue_count >= 1) {
		delete_transient($repair_lock_key);
		return [];
	}
	$existing_repairs = get_posts([
		'post_type' => LF_AI_STUDIO_JOB_CPT,
		'post_status' => 'publish',
		'posts_per_page' => 5,
		'fields' => 'ids',
		'meta_query' => [
			[
				'key' => 'lf_ai_job_parent',
				'value' => $root_job_id,
			],
			[
				'key' => 'lf_ai_job_repair',
				'value' => 1,
			],
		],
	]);
	if (!empty($existing_repairs)) {
		foreach ($existing_repairs as $existing_repair_id) {
			$status = (string) get_post_meta((int) $existing_repair_id, 'lf_ai_job_status', true);
			if (in_array($status, ['queued', 'running', 'done'], true)) {
				delete_transient($repair_lock_key);
				return [];
			}
		}
	}
	$repair_request = lf_ai_studio_build_repair_request($report, is_array($request) ? $request : []);
	if (!is_array($repair_request) || !empty($repair_request['error'])) {
		delete_transient($repair_lock_key);
		return [];
	}
	$repair_request['request_id'] = $request_id;
	$repair_request['run_phase'] = 'repair';
	$repair_request['repair_attempt'] = 1;
	$new_job_id = lf_ai_studio_create_job($repair_request);
	if (!$new_job_id) {
		delete_transient($repair_lock_key);
		return [];
	}
	update_post_meta($new_job_id, 'lf_ai_job_parent', $root_job_id);
	update_post_meta($new_job_id, 'lf_ai_job_repair', 1);
	update_post_meta($new_job_id, 'lf_ai_job_requeue_count', $requeue_count + 1);
	update_post_meta($new_job_id, 'lf_ai_job_run_phase', 'repair');
	update_post_meta($root_job_id, 'lf_ai_job_requeue_count', $requeue_count + 1);
	update_post_meta($job_id, 'lf_ai_job_requeue_count', $requeue_count + 1);
	$result = lf_ai_studio_send_request($repair_request, $new_job_id);
	delete_transient($repair_lock_key);
	return ['job_id' => $new_job_id, 'result' => $result];
}

function lf_ai_studio_build_repair_request(array $report, array $request): array {
	if (empty($request) || empty($request['blueprints']) || !is_array($request['blueprints'])) {
		$request = lf_ai_studio_build_full_site_payload(false);
	}
	if (!is_array($request) || !empty($request['error'])) {
		return ['error' => 'Unable to build repair request.'];
	}
	$missing_post_ids = [];
	$repair_focus = [];
	$needs_homepage = false;
	foreach ((array) ($report['pages'] ?? []) as $page) {
		$issues = $page['issues'] ?? [];
		if (empty($issues)) {
			continue;
		}
		$post_id = isset($page['id']) ? absint($page['id']) : 0;
		$slug = (string) ($page['slug'] ?? '');
		if ($slug === 'home' || $slug === 'homepage') {
			$needs_homepage = true;
		}
		if ($post_id) {
			$missing_post_ids[] = $post_id;
		}
		if (!empty($issues)) {
			$focus = [];
			foreach ($issues as $issue) {
				$section = (string) ($issue['section'] ?? '');
				$field = (string) ($issue['field'] ?? '');
				if ($section !== '' && $field !== '') {
					if (!isset($focus[$section])) {
						$focus[$section] = [];
					}
					$focus[$section][] = $field;
				}
			}
			if (!empty($focus)) {
				$repair_focus[] = [
					'id' => $post_id,
					'slug' => $slug,
					'fields' => $focus,
				];
			}
		}
	}
	$missing_post_ids = array_values(array_unique($missing_post_ids));
	$filtered = [];
	foreach ($request['blueprints'] as $blueprint) {
		if (!is_array($blueprint)) {
			continue;
		}
		$page = (string) ($blueprint['page'] ?? '');
		$post_id = isset($blueprint['post_id']) ? absint($blueprint['post_id']) : 0;
		if ($page === 'homepage' && $needs_homepage) {
			$filtered[] = $blueprint;
			continue;
		}
		if ($post_id && in_array($post_id, $missing_post_ids, true)) {
			$filtered[] = $blueprint;
		}
	}
	if (empty($filtered)) {
		return ['error' => 'No repair targets found.'];
	}
	$request['blueprints'] = $filtered;
	$request['repair_only'] = true;
	$request['repair_focus'] = $repair_focus;
	$repair_note = "REPAIR MODE: Only fill missing/placeholder fields listed in repair_focus. Do not rewrite fields that already contain real content. Preserve existing content and tone.";
	$request['system_message'] = isset($request['system_message']) && is_string($request['system_message'])
		? trim($request['system_message'] . "\n\n" . $repair_note)
		: $repair_note;
	return $request;
}

function lf_ai_studio_audit_site_content(): array {
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$niche_slug = (string) get_option('lf_homepage_niche_slug', 'general');
	$report = [
		'timestamp' => time(),
		'summary' => [
			'missing_fields' => 0,
			'pages_with_issues' => 0,
			'cta_total' => 0,
			'cta_unique' => 0,
			'internal_links_total' => 0,
			'pages_with_internal_links' => 0,
			'seo_quality_avg' => 0,
		],
		'pages' => [],
		'cta_duplicates' => [],
		'quality_warnings' => array_values(array_filter((array) get_option('lf_ai_studio_quality_warnings', []))),
	];
	$cta_signatures = [];

	if (function_exists('lf_get_homepage_section_config')) {
		$config = lf_get_homepage_section_config();
		$order = function_exists('lf_homepage_controller_order') ? lf_homepage_controller_order() : array_keys($config);
		$homepage_issues = [];
		foreach ($order as $section_id) {
			$settings = $config[$section_id] ?? null;
			if (!is_array($settings)) {
				continue;
			}
			if (isset($settings['enabled']) && empty($settings['enabled'])) {
				continue;
			}
			$schema = $registry[$section_id] ?? [];
			$defaults = function_exists('lf_sections_defaults_for') ? lf_sections_defaults_for($section_id, $niche_slug) : [];
			$allowed_keys = lf_ai_studio_homepage_allowed_field_keys($section_id, $schema);
			$section_issues = lf_ai_studio_audit_section_settings($settings, $defaults, $section_id, $section_id, $allowed_keys);
			if (!empty($section_issues)) {
				$homepage_issues = array_merge($homepage_issues, $section_issues);
			}
			if ($section_id === 'cta') {
				$signature = lf_ai_studio_cta_signature($settings);
				if ($signature !== '') {
					$cta_signatures[] = [
						'signature' => $signature,
						'headline' => $settings['cta_headline'] ?? '',
						'page' => ['id' => (int) get_option('page_on_front'), 'slug' => 'home', 'title' => __('Homepage', 'leadsforward-core')],
					];
				}
			}
		}
		$report['pages'][] = [
			'id' => (int) get_option('page_on_front'),
			'slug' => 'home',
			'title' => __('Homepage', 'leadsforward-core'),
			'post_type' => 'page',
			'issues' => $homepage_issues,
		];
	}

	$required_slugs = function_exists('lf_wizard_required_page_slugs') ? lf_wizard_required_page_slugs() : [];
	foreach ($required_slugs as $slug) {
		if ($slug === 'home') {
			continue;
		}
		$page = get_page_by_path($slug);
		if (!$page instanceof \WP_Post) {
			$report['pages'][] = [
				'id' => 0,
				'slug' => $slug,
				'title' => ucfirst(str_replace('-', ' ', $slug)),
				'post_type' => 'page',
				'issues' => [['section' => 'page', 'field' => 'missing', 'reason' => 'missing_page']],
			];
			continue;
		}
		$report['pages'][] = lf_ai_studio_audit_post($page, $registry, $niche_slug, $cta_signatures);
	}

	$services = get_posts([
		'post_type' => 'lf_service',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
	]);
	foreach ($services as $service) {
		if ($service instanceof \WP_Post) {
			$report['pages'][] = lf_ai_studio_audit_post($service, $registry, $niche_slug, $cta_signatures);
		}
	}

	$areas = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
	]);
	foreach ($areas as $area) {
		if ($area instanceof \WP_Post) {
			$report['pages'][] = lf_ai_studio_audit_post($area, $registry, $niche_slug, $cta_signatures);
		}
	}

	$missing_fields = 0;
	$pages_with_issues = 0;
	foreach ($report['pages'] as $page) {
		$issues = $page['issues'] ?? [];
		if (!empty($issues)) {
			$pages_with_issues++;
			$missing_fields += count($issues);
		}
	}
	$report['summary']['missing_fields'] = $missing_fields;
	$report['summary']['pages_with_issues'] = $pages_with_issues;

	$cta_groups = [];
	foreach ($cta_signatures as $entry) {
		$signature = $entry['signature'];
		if ($signature === '') {
			continue;
		}
		if (!isset($cta_groups[$signature])) {
			$cta_groups[$signature] = ['headline' => $entry['headline'], 'pages' => []];
		}
		$cta_groups[$signature]['pages'][] = $entry['page'];
	}
	$cta_total = count($cta_groups);
	$cta_unique = 0;
	foreach ($cta_groups as $signature => $group) {
		if (count($group['pages']) > 1) {
			$report['cta_duplicates'][] = [
				'signature' => $signature,
				'headline' => $group['headline'],
				'pages' => $group['pages'],
			];
		} else {
			$cta_unique++;
		}
	}
	$report['summary']['cta_total'] = $cta_total;
	$report['summary']['cta_unique'] = $cta_unique;
	$link_stats = lf_ai_studio_internal_link_coverage_for_report($report['pages']);
	$report['summary']['internal_links_total'] = (int) ($link_stats['total_links'] ?? 0);
	$report['summary']['pages_with_internal_links'] = (int) ($link_stats['pages_with_links'] ?? 0);
	$quality_avg = lf_ai_studio_average_quality_score_for_report($report['pages']);
	$report['summary']['seo_quality_avg'] = $quality_avg;
	$report['wiring'] = function_exists('lf_ai_studio_wiring_report')
		? lf_ai_studio_wiring_report()
		: [];
	return $report;
}

function lf_ai_studio_audit_post(\WP_Post $post, array $registry, string $niche_slug, array &$cta_signatures): array {
	$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
	$config = ($context !== '' && function_exists('lf_pb_get_post_config')) ? lf_pb_get_post_config($post->ID, $context) : [];
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$issues = [];
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		if ($type === '' || !isset($registry[$type])) {
			continue;
		}
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$defaults = function_exists('lf_sections_defaults_for') ? lf_sections_defaults_for($type, $niche_slug) : [];
		$allowed_keys = lf_ai_studio_homepage_allowed_field_keys($type, $registry[$type]);
		$section_issues = lf_ai_studio_audit_section_settings($settings, $defaults, $type, $instance_id, $allowed_keys);
		if (!empty($section_issues)) {
			$issues = array_merge($issues, $section_issues);
		}
		if ($type === 'cta') {
			$signature = lf_ai_studio_cta_signature($settings);
			if ($signature !== '') {
				$cta_signatures[] = [
					'signature' => $signature,
					'headline' => $settings['cta_headline'] ?? '',
					'page' => ['id' => $post->ID, 'slug' => $post->post_name, 'title' => $post->post_title],
				];
			}
		}
	}
	return [
		'id' => $post->ID,
		'slug' => $post->post_name,
		'title' => $post->post_title,
		'post_type' => $post->post_type,
		'issues' => $issues,
	];
}

function lf_ai_studio_audit_section_settings(array $settings, array $defaults, string $section_type, string $instance_id, array $allowed_keys): array {
	$issues = [];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	foreach ($allowed_keys as $field_key) {
		$field_type = lf_ai_studio_registry_field_type($registry, $section_type, $field_key);
		$raw = $settings[$field_key] ?? '';
		$default = $defaults[$field_key] ?? '';
		if (lf_ai_studio_audit_value_empty($raw, $field_type)) {
			$issues[] = ['section' => $instance_id, 'field' => $field_key, 'reason' => 'empty'];
			continue;
		}
		if (lf_ai_studio_audit_value_matches_default($raw, $default, $field_type)) {
			$issues[] = ['section' => $instance_id, 'field' => $field_key, 'reason' => 'default'];
		}
	}
	return $issues;
}

function lf_ai_studio_audit_value_empty($value, string $type): bool {
	if ($type === 'list') {
		$lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $value)));
		if (empty($lines)) {
			return true;
		}
		foreach ($lines as $line) {
			if (lf_ai_studio_contains_json_placeholder($line)) {
				return true;
			}
		}
		return false;
	}
	$text = lf_ai_studio_audit_normalize_text($value);
	if ($text === '') {
		return true;
	}
	return lf_ai_studio_contains_json_placeholder($text);
}

function lf_ai_studio_audit_value_matches_default($value, $default, string $type): bool {
	if ($default === '' || $default === null) {
		return false;
	}
	if ($type === 'list') {
		$left = implode("\n", array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $value))));
		$right = implode("\n", array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $default))));
		return lf_ai_studio_audit_normalize_text($left) === lf_ai_studio_audit_normalize_text($right);
	}
	return lf_ai_studio_audit_normalize_text($value) === lf_ai_studio_audit_normalize_text($default);
}

function lf_ai_studio_audit_normalize_text($value): string {
	$text = wp_strip_all_tags((string) $value);
	$text = preg_replace('/\s+/', ' ', $text);
	return trim((string) $text);
}

function lf_ai_studio_internal_link_coverage_for_report(array $pages): array {
	$total_links = 0;
	$pages_with_links = 0;
	foreach ($pages as $page) {
		$post_id = isset($page['id']) ? absint($page['id']) : 0;
		if ($post_id <= 0) {
			continue;
		}
		$count = lf_ai_studio_count_internal_links_for_post($post_id);
		$total_links += $count;
		if ($count > 0) {
			$pages_with_links++;
		}
	}
	return [
		'total_links' => $total_links,
		'pages_with_links' => $pages_with_links,
	];
}

function lf_ai_studio_count_internal_links_for_post(int $post_id): int {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return 0;
	}
	$count = (int) preg_match_all('/<a\s[^>]*href=/i', (string) $post->post_content);
	$config = get_post_meta($post_id, LF_PB_META_KEY, true);
	if (!is_array($config)) {
		return $count;
	}
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	foreach ($sections as $section) {
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		foreach ($settings as $value) {
			if (!is_string($value) || $value === '') {
				continue;
			}
			$count += (int) preg_match_all('/<a\s[^>]*href=/i', $value);
		}
	}
	return $count;
}

function lf_ai_studio_average_quality_score_for_report(array $pages): int {
	$total = 0;
	$count = 0;
	foreach ($pages as $page) {
		$post_id = isset($page['id']) ? absint($page['id']) : 0;
		if ($post_id <= 0) {
			continue;
		}
		if (function_exists('lf_seo_calculate_content_quality')) {
			lf_seo_calculate_content_quality($post_id);
		}
		$score = (int) get_post_meta($post_id, '_lf_seo_quality_score', true);
		if ($score > 0) {
			$total += $score;
			$count++;
		}
	}
	if ($count === 0) {
		return 0;
	}
	return (int) round($total / $count);
}

function lf_ai_studio_build_service_short_desc(int $post_id): string {
	$current = function_exists('get_field') ? (string) get_field('lf_service_short_desc', $post_id) : (string) get_post_meta($post_id, 'lf_service_short_desc', true);
	if (trim($current) !== '') {
		return '';
	}
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || $post->post_type !== 'lf_service') {
		return '';
	}
	$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
	if ($context === '' || !function_exists('lf_pb_get_post_config')) {
		return '';
	}
	$config = lf_pb_get_post_config($post_id, $context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	if (empty($order) || empty($sections)) {
		return '';
	}
	$niche_slug = (string) get_option('lf_homepage_niche_slug', 'general');
	$preferred_keys = [
		'hero_subheadline',
		'hero_supporting_text',
		'section_intro',
		'supporting_text',
		'section_body',
		'service_details_body',
		'service_details_body_secondary',
	];
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		if ($type === '') {
			continue;
		}
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$defaults = function_exists('lf_sections_defaults_for') ? lf_sections_defaults_for($type, $niche_slug) : [];
		foreach ($preferred_keys as $key) {
			if (!array_key_exists($key, $settings)) {
				continue;
			}
			$value = $settings[$key];
			if (lf_ai_studio_audit_value_empty($value, 'text')) {
				continue;
			}
			$default = $defaults[$key] ?? '';
			if ($default !== '' && lf_ai_studio_audit_value_matches_default($value, $default, 'text')) {
				continue;
			}
			$text = lf_ai_studio_audit_normalize_text($value);
			if ($text !== '') {
				return wp_trim_words($text, 28);
			}
		}
	}
	return '';
}

function lf_ai_studio_cta_signature(array $settings): string {
	$headline = lf_ai_studio_audit_normalize_text($settings['cta_headline'] ?? '');
	$sub = lf_ai_studio_audit_normalize_text($settings['cta_subheadline'] ?? '');
	$sub2 = lf_ai_studio_audit_normalize_text($settings['cta_subheadline_secondary'] ?? '');
	$parts = array_filter([$headline, $sub, $sub2]);
	return implode('|', $parts);
}
