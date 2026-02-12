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
add_action('admin_post_lf_ai_studio_generate', 'lf_ai_studio_handle_generate');
add_action('admin_post_lf_ai_studio_retry', 'lf_ai_studio_handle_retry');
add_action('admin_post_lf_ai_studio_manifest', 'lf_ai_studio_handle_manifest');
add_action('admin_post_lf_ai_studio_manifest_template', 'lf_ai_studio_handle_manifest_template');
add_action('admin_enqueue_scripts', 'lf_ai_studio_assets');

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
		'enabled' => !empty($airtable_settings['enabled']),
		'strings' => [
			'searchPlaceholder' => __('Search Airtable projects…', 'leadsforward-core'),
			'noResults' => __('No projects found.', 'leadsforward-core'),
			'notConfigured' => __('Airtable is not configured.', 'leadsforward-core'),
			'selectPrompt' => __('Select a project to preview before generating.', 'leadsforward-core'),
			'generating' => __('Generating from Airtable…', 'leadsforward-core'),
		],
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
	update_option('lf_ai_studio_enabled', isset($_POST['lf_ai_studio_enabled']) ? '1' : '0');
	update_option('lf_ai_studio_webhook', isset($_POST['lf_ai_studio_webhook']) ? esc_url_raw(wp_unslash($_POST['lf_ai_studio_webhook'])) : '');
	update_option('lf_ai_studio_secret', isset($_POST['lf_ai_studio_secret']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_studio_secret'])) : '');
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

	update_option('lf_ai_airtable_enabled', isset($_POST['lf_ai_airtable_enabled']) ? '1' : '0');
	update_option('lf_ai_airtable_pat', isset($_POST['lf_ai_airtable_pat']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_pat'])) : '');
	update_option('lf_ai_airtable_base', isset($_POST['lf_ai_airtable_base']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_base'])) : '');
	update_option('lf_ai_airtable_table', isset($_POST['lf_ai_airtable_table']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_table'])) : '');
	update_option('lf_ai_airtable_view', isset($_POST['lf_ai_airtable_view']) ? sanitize_text_field(wp_unslash($_POST['lf_ai_airtable_view'])) : '');
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

	wp_safe_redirect(admin_url('admin.php?page=lf-ops&saved=1'));
	exit;
}

function lf_ai_studio_handle_generate(): void {
	if (!current_user_can('edit_theme_options')) {
		error_log('LF DEBUG: Regenerate Site blocked: insufficient permissions.');
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_generate', 'lf_ai_studio_generate_nonce');
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
		<?php if ($reset_done) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Site reset complete.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($reset_error === 'confirm') : ?>
			<div class="notice notice-error"><p><?php esc_html_e('Reset confirmation did not match. Type RESET to continue.', 'leadsforward-core'); ?></p></div>
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
		<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0;">
			<h2 style="margin-top:0;"><?php esc_html_e('Manifest Upload (Deterministic)', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Upload a JSON manifest to bypass wizard inputs and run deterministic generation.', 'leadsforward-core'); ?></p>
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
			<?php $template_url = wp_nonce_url(admin_url('admin-post.php?action=lf_ai_studio_manifest_template'), 'lf_ai_studio_manifest_template', 'lf_ai_studio_manifest_template_nonce'); ?>
			<form id="lf-ai-manifest-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field('lf_ai_studio_manifest', 'lf_ai_studio_manifest_nonce'); ?>
				<input type="hidden" name="action" value="lf_ai_studio_manifest" />
				<input type="file" name="lf_site_manifest" accept="application/json,.json" required />
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e('Upload Manifest & Generate', 'leadsforward-core'); ?></button>
					<a class="button" href="<?php echo esc_url($template_url); ?>"><?php esc_html_e('Download Manifest Template', 'leadsforward-core'); ?></a>
				</p>
			</form>
		</div>
		<div class="card" id="lf-airtable-picker" style="max-width: 980px; padding: 16px; margin: 16px 0;">
			<h2 style="margin-top:0;"><?php esc_html_e('Airtable Projects', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Search your Airtable projects and generate from a single record.', 'leadsforward-core'); ?></p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 12px;">
				<?php wp_nonce_field('lf_ai_studio_scope_save', 'lf_ai_studio_scope_nonce'); ?>
				<input type="hidden" name="action" value="lf_ai_studio_scope_save" />
				<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
					<strong><?php esc_html_e('Generate:', 'leadsforward-core'); ?></strong>
					<label><input type="checkbox" name="lf_ai_gen_homepage" value="1" <?php checked($gen_homepage); ?> /> <?php esc_html_e('Homepage', 'leadsforward-core'); ?></label>
					<label><input type="checkbox" name="lf_ai_gen_services" value="1" <?php checked($gen_services); ?> /> <?php esc_html_e('Service pages', 'leadsforward-core'); ?></label>
					<label><input type="checkbox" name="lf_ai_gen_service_areas" value="1" <?php checked($gen_service_areas); ?> /> <?php esc_html_e('Service area pages', 'leadsforward-core'); ?></label>
					<label><input type="checkbox" name="lf_ai_gen_core_pages" value="1" <?php checked($gen_core_pages); ?> /> <?php esc_html_e('Core pages', 'leadsforward-core'); ?></label>
					<button type="submit" class="button"><?php esc_html_e('Save Scope', 'leadsforward-core'); ?></button>
				</div>
				<p class="description" style="margin-top:8px;"><?php esc_html_e('Defaults to everything. Manifest can override with generation_scope=homepage_only.', 'leadsforward-core'); ?></p>
			</form>
			<?php if (!$airtable_ready) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e('Airtable is not configured yet. Add your PAT, Base ID, and Table in Orchestrator Settings below, then save.', 'leadsforward-core'); ?></p>
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
					<button type="button" class="button button-primary" id="lf-airtable-generate" disabled>
						<?php esc_html_e('Use Project & Generate', 'leadsforward-core'); ?>
					</button>
					<div id="lf-airtable-status" class="lf-airtable-status" role="status" aria-live="polite"></div>
				</div>
			</div>
		</div>
		<?php if (function_exists('lf_dev_reset_allowed') && lf_dev_reset_allowed() && current_user_can('manage_options')) : ?>
			<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0; border: 1px solid #f87171;">
				<h2 style="margin-top:0;"><?php esc_html_e('Reset site (dev only)', 'leadsforward-core'); ?></h2>
				<p class="description"><?php esc_html_e('Deletes wizard-created pages, services, and areas. This is irreversible.', 'leadsforward-core'); ?></p>
				<form method="post">
					<?php wp_nonce_field('lf_dev_reset', 'lf_dev_reset_nonce'); ?>
					<input type="hidden" name="lf_dev_reset" value="1" />
					<p>
						<label for="lf_dev_reset_confirm"><?php esc_html_e('Type RESET to confirm:', 'leadsforward-core'); ?></label><br />
						<input type="text" id="lf_dev_reset_confirm" name="lf_dev_reset_confirm" class="regular-text" />
					</p>
					<p><button type="submit" class="button button-secondary"><?php esc_html_e('Reset Site', 'leadsforward-core'); ?></button></p>
				</form>
			</div>
		<?php endif; ?>
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
	$request = lf_ai_studio_build_full_site_payload();
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
	$request = lf_ai_studio_build_full_site_payload();
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
	$callback_url = rest_url('leadsforward/v1/orchestrator');
	if ($secret !== '') {
		$callback_url = add_query_arg('token', rawurlencode($secret), $callback_url);
	}
	$request['job_id'] = $job_id;
	$request['callback_url'] = $callback_url;
	update_post_meta($job_id, 'lf_ai_job_status', 'queued');
	update_post_meta($job_id, 'lf_ai_job_request', $request);
	$log_payload = [
		'keys' => array_keys($request),
		'blueprints' => isset($request['blueprints']) && is_array($request['blueprints'])
			? ['count' => count($request['blueprints'])]
			: ['count' => 0],
	];
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
	error_log('LF DEBUG: Webhook call returned' . print_r($response, true));
	if (is_wp_error($response)) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', $response->get_error_message());
		error_log('LF DEBUG: Regenerate Site failed: WP error on webhook call: ' . $response->get_error_message());
		return ['error' => $response->get_error_message(), 'job_id' => $job_id];
	}
	$body = wp_remote_retrieve_body($response);
	$status = (int) wp_remote_retrieve_response_code($response);
	if ($status < 200 || $status >= 300) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', 'http_' . $status);
		update_post_meta($job_id, 'lf_ai_job_response', $body);
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
	return ['job_id' => $job_id];
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
	if (!empty($existing)) {
		return;
	}
	$label = $business_name !== '' ? $business_name : __('your home', 'leadsforward-core');
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
	foreach ($items as $item) {
		if ((int) $item->menu_item_parent === (int) $more_item->ID) {
			return;
		}
	}
	$slugs = ['about-us', 'blog', 'contact'];
	foreach ($slugs as $slug) {
		$page = get_page_by_path($slug);
		if (!$page instanceof \WP_Post) {
			continue;
		}
		wp_update_nav_menu_item($menu_id, 0, [
			'menu-item-title' => get_the_title($page->ID),
			'menu-item-url' => get_permalink($page->ID),
			'menu-item-type' => 'post_type',
			'menu-item-object' => 'page',
			'menu-item-object-id' => $page->ID,
			'menu-item-parent-id' => (int) $more_item->ID,
			'menu-item-status' => 'publish',
		]);
	}
}

function lf_ai_studio_get_manifest(): array {
	$manifest = get_option('lf_site_manifest', []);
	return is_array($manifest) ? $manifest : [];
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
		'services' => array_values(array_map('lf_ai_studio_normalize_service_item', $services)),
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
		'Return JSON only. No markdown, no commentary, no HTML.',
		'Use only allowed_field_keys. Do not invent fields.',
		'Headlines: never use dash or hyphen separators. Sentence case or title case only. No trailing punctuation unless a question mark. Hero headline max 12 words.',
		'Benefits: 15-35 words each, max 2 sentences per benefit. No dash separators in benefit titles.',
		'Content separation by page type:',
		'Homepage: broad positioning; do not reuse service or area copy verbatim.',
		'Services overview: broad authority content; no detailed process repetition; avoid excessive city modifiers.',
		'Service page: deep service-specific content; do not reuse homepage hero copy; reference the exact service.',
		'Service areas overview: broad coverage explanation; no detailed local signals per city.',
		'Service area page: localized content; do not repeat service overview intro verbatim.',
		'Never reuse sentences across page types.',
		'FAQ strategy: create one global pool of 8-12 evergreen FAQs. Reuse across pages unless contextual variation is required. Homepage shows 5. Service pages show 4-6 relevant. Service area pages show 3-5 localized. Overview pages optionally 3-4.',
		'CTA strategy: treat the homepage CTA section as the canonical global CTA copy. For each page, add exactly one contextual sentence in cta_subheadline_secondary. Never duplicate CTA sentences across pages.',
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
		$blueprint_sections[] = [
			'section_id' => $section_id,
			'section_type' => lf_ai_studio_homepage_section_type($section_id),
			'intent' => (string) ($section['section_intent'] ?? ''),
			// Long-form density expansion – Step 3
			'length_targets' => lf_ai_studio_section_length_targets(lf_ai_studio_homepage_section_type($section_id), 'homepage'),
			'allowed_field_keys' => $allowed_keys,
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
	foreach ($order as $section_id) {
		$schema = $registry[$section_id] ?? null;
		if (!is_array($schema)) {
			continue;
		}
		$section = $config[$section_id] ?? [];
		$allowed_keys = lf_ai_studio_homepage_allowed_field_keys($section_id, $schema);
		$blueprint_sections[] = [
			'section_id' => $section_id,
			'section_type' => lf_ai_studio_homepage_section_type($section_id),
			'intent' => (string) ($section['section_intent'] ?? ''),
			'length_targets' => lf_ai_studio_section_length_targets(lf_ai_studio_homepage_section_type($section_id), 'homepage'),
			'allowed_field_keys' => $allowed_keys,
		];
	}
	if (empty($order) || empty($blueprint_sections)) {
		return [];
	}
	return [
		'page' => 'homepage',
		'page_intent' => 'homepage',
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
		$out_sections[] = [
			'section_id' => $instance_id,
			'section_type' => $type,
			'intent' => (string) ($settings['section_intent'] ?? ''),
			// Long-form density expansion – Step 3
			'length_targets' => lf_ai_studio_section_length_targets($type, $page),
			'allowed_field_keys' => lf_ai_studio_homepage_allowed_field_keys($type, $registry[$type]),
		];
		$out_order[] = $instance_id;
	}
	return [
		'page' => $page,
		'post_id' => $post->ID,
		'page_intent' => $page_intent,
		'primary_keyword' => $primary_keyword,
		'sections' => $out_sections,
		'order' => $out_order,
		'faq_target_range' => lf_ai_studio_faq_target_range($page),
	];
}

function lf_ai_studio_get_generation_scope(array $manifest): array {
	$default = [
		'homepage' => true,
		'services' => true,
		'service_areas' => true,
		'core_pages' => true,
	];
	$scope = [
		'homepage' => get_option('lf_ai_gen_homepage', '1') === '1',
		'services' => get_option('lf_ai_gen_services', '1') === '1',
		'service_areas' => get_option('lf_ai_gen_service_areas', '1') === '1',
		'core_pages' => get_option('lf_ai_gen_core_pages', '1') === '1',
	];
	foreach ($default as $key => $val) {
		if (!isset($scope[$key])) {
			$scope[$key] = $val;
		}
	}
	$manifest_scope = (string) ($manifest['generation_scope'] ?? '');
	if ($manifest_scope === 'homepage_only') {
		$scope = [
			'homepage' => true,
			'services' => false,
			'service_areas' => false,
			'core_pages' => false,
		];
	}
	return $scope;
}

function lf_ai_studio_build_full_site_payload(): array {
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

	$scope = lf_ai_studio_get_generation_scope($manifest);

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
			'privacy-policy' => ['page' => 'privacy', 'intent' => 'privacy', 'keyword' => ''],
			'terms-of-service' => ['page' => 'terms', 'intent' => 'terms', 'keyword' => ''],
			'thank-you' => ['page' => 'thank_you', 'intent' => 'thank_you', 'keyword' => ''],
		];
		if ($scope['services']) {
			$core_pages['our-services'] = ['page' => 'services_overview', 'intent' => 'services_overview', 'keyword' => $overview_keyword];
		}
		if ($scope['service_areas']) {
			$core_pages['our-service-areas'] = ['page' => 'service_areas_overview', 'intent' => 'service_areas_overview', 'keyword' => $overview_keyword];
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
	return [
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
		'blueprints' => $blueprints,
	];
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

function lf_apply_orchestrator_updates(array $response): array {
	$updates = $response['updates'] ?? [];
	if (!is_array($updates)) {
		return ['success' => false, 'summary' => __('Missing updates array.', 'leadsforward-core'), 'changes' => [], 'errors' => [__('Missing updates array.', 'leadsforward-core')]];
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
				$errors[] = sprintf(__('Post update at index %d is missing id.', 'leadsforward-core'), $index);
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
				$errors[] = sprintf(__('Service meta update at index %d is missing id.', 'leadsforward-core'), $index);
				continue;
			}
			$service_meta_updates[] = $update;
			$update_counts['service_meta']++;
			continue;
		}
		$errors[] = sprintf(__('Update at index %d has unknown target.', 'leadsforward-core'), $index);
	}

	if (!empty($homepage_updates) && function_exists('lf_get_homepage_section_config')) {
		$config = $homepage_config;
		foreach ($homepage_updates as $update) {
			$fields = $update['fields'] ?? $update['data'] ?? [];
			foreach ($fields as $key => $value) {
				if (!is_string($key)) {
					continue;
				}
				$parts = explode('.', $key, 2);
				if (count($parts) !== 2) {
					$errors[] = sprintf(__('Homepage field "%s" must use section.field notation.', 'leadsforward-core'), (string) $key);
					continue;
				}
				$section_id = trim($parts[0]);
				$field_key = trim($parts[1]);
				if ($section_id === '' || $field_key === '') {
					continue;
				}
				if (!isset($config[$section_id]) || !isset($registry[$section_id])) {
					$errors[] = sprintf(__('Homepage section "%s" is not registered.', 'leadsforward-core'), $section_id);
					continue;
				}
				$allowed = lf_ai_studio_homepage_allowed_field_keys($section_id, $registry[$section_id]);
				if (!in_array($field_key, $allowed, true)) {
					$errors[] = sprintf(__('Homepage field "%s" is not allowed.', 'leadsforward-core'), $key);
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
				$homepage_fields[$section_id][$field_key] = $normalized_value;
				$homepage_fields_count++;
				$fields_updated++;
			}
		}
		foreach ($homepage_fields as $section_id => $fields) {
			if (!is_array($fields) || !isset($config[$section_id])) {
				continue;
			}
			$config[$section_id] = array_merge(
				$config[$section_id],
				lf_sections_sanitize_settings($section_id, $fields)
			);
		}
		if ($config !== $homepage_config) {
			update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
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
		$incoming_by_instance = [];
		foreach ($incoming as $key => $value) {
			if (!is_string($key)) {
				continue;
			}
			$parts = explode('.', $key, 2);
			if (count($parts) !== 2) {
				$errors[] = sprintf(__('Post field "%s" must use section.field notation.', 'leadsforward-core'), (string) $key);
				continue;
			}
			$instance_id = trim($parts[0]);
			$field_key = trim($parts[1]);
			if ($instance_id === '' || $field_key === '') {
				continue;
			}
			$section = $sections[$instance_id] ?? null;
			$type = is_array($section) ? (string) ($section['type'] ?? '') : '';
			if ($type === '' || !isset($registry[$type])) {
				$errors[] = sprintf(__('Section "%s" is not registered for post %d.', 'leadsforward-core'), $instance_id, $post_id);
				continue;
			}
			$allowed = lf_ai_studio_homepage_allowed_field_keys($type, $registry[$type]);
			if (!in_array($field_key, $allowed, true)) {
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
			$incoming_by_instance[$instance_id][$field_key] = $normalized_value;
			$fields_updated++;
		}
		foreach ($incoming_by_instance as $instance_id => $fields) {
			$section = $sections[$instance_id] ?? null;
			if (!is_array($section)) {
				continue;
			}
			$type = (string) ($section['type'] ?? '');
			$sections[$instance_id]['settings'] = array_merge(
				$section['settings'] ?? [],
				lf_sections_sanitize_settings($type, $fields)
			);
		}
		update_post_meta($post_id, LF_PB_META_KEY, [
			'order' => $order,
			'sections' => $sections,
			'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
		]);
		$changes['posts'][] = $post_id;
		$pages_updated++;
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
				$filtered_fields[$key] = lf_ai_studio_normalize_value($value);
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
		$question = sanitize_text_field(lf_ai_studio_normalize_text($question_raw));
		$answer = wp_kses_post(lf_ai_studio_normalize_text($answer_raw));
		if ($question === '' && $answer === '') {
			continue;
		}
		$faq_id = isset($update['id']) ? absint($update['id']) : 0;
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
	return $changed;
}
