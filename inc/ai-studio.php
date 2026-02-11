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

add_action('init', 'lf_ai_studio_register_cpt');
add_action('admin_menu', 'lf_ai_studio_register_menu', 13);
add_action('admin_post_lf_ai_studio_save', 'lf_ai_studio_handle_save');
add_action('admin_post_lf_ai_studio_generate', 'lf_ai_studio_handle_generate');
add_action('admin_post_lf_ai_studio_retry', 'lf_ai_studio_handle_retry');

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

function lf_ai_studio_register_menu(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	add_submenu_page(
		'lf-ops',
		__('AI Studio (Advanced)', 'leadsforward-core'),
		__('AI Studio (Advanced)', 'leadsforward-core'),
		'edit_theme_options',
		'lf-ai-studio',
		'lf_ai_studio_render_page'
	);
}

function lf_ai_studio_handle_save(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_save', 'lf_ai_studio_nonce');
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
	wp_safe_redirect(admin_url('admin.php?page=lf-ai-studio&saved=1'));
	exit;
}

function lf_ai_studio_handle_generate(): void {
	if (!current_user_can('edit_theme_options')) {
		error_log('LF DEBUG: Regenerate Site blocked: insufficient permissions.');
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_generate', 'lf_ai_studio_generate_nonce');
	$result = lf_ai_studio_run_homepage_generation();
	$redirect = admin_url('admin.php?page=lf-ai-studio');
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
		wp_safe_redirect(admin_url('admin.php?page=lf-ai-studio&error=missing_job'));
		exit;
	}
	$request_payload = get_post_meta($job_id, 'lf_ai_job_request', true);
	if (!is_array($request_payload)) {
		wp_safe_redirect(admin_url('admin.php?page=lf-ai-studio&error=missing_payload'));
		exit;
	}
	$result = lf_ai_studio_send_request($request_payload, $job_id);
	$redirect = admin_url('admin.php?page=lf-ai-studio');
	if (!empty($result['error'])) {
		$redirect = add_query_arg('error', rawurlencode($result['error']), $redirect);
	} else {
		$redirect = add_query_arg('job', (string) $job_id, $redirect);
	}
	wp_safe_redirect($redirect);
	exit;
}

function lf_ai_studio_render_page(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
	$error = isset($_GET['error']) ? sanitize_text_field(wp_unslash((string) $_GET['error'])) : '';
	$enabled = get_option('lf_ai_studio_enabled', '0') === '1';
	$webhook = (string) get_option('lf_ai_studio_webhook', '');
	$secret = (string) get_option('lf_ai_studio_secret', '');
	$keywords = (string) get_option('lf_ai_studio_keywords', '');
	$scope = (string) get_option('lf_ai_studio_scope', 'all');
	$scope_types = get_option('lf_ai_studio_scope_types', []);
	$scope_types = is_array($scope_types) ? $scope_types : [];
	$style = (string) get_option('lf_ai_studio_style', 'professional');
	$homepage_keywords = function_exists('lf_homepage_keywords') ? lf_homepage_keywords() : ['primary' => '', 'secondary' => []];
	$homepage_city = (string) get_option('lf_homepage_city', '');
	$jobs = get_posts([
		'post_type' => LF_AI_STUDIO_JOB_CPT,
		'post_status' => 'any',
		'posts_per_page' => 5,
		'orderby' => 'date',
		'order' => 'DESC',
	]);
	$blueprint_preview = lf_ai_studio_build_homepage_blueprint();
	$blueprint_json = wp_json_encode($blueprint_preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	$wizard_complete = (bool) get_option('lf_setup_wizard_complete', false);
	$last_log = get_option('lf_ai_last_generation_log', []);
	if (!is_array($last_log)) {
		$last_log = [];
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e('AI Studio (Advanced)', 'leadsforward-core'); ?></h1>
		<p class="description"><?php esc_html_e('Regenerate full site content via the orchestrator. Setup Wizard handles first‑run inputs.', 'leadsforward-core'); ?></p>
		<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0; border-left: 4px solid #3b82f6;">
			<h2 style="margin-top:0;"><?php esc_html_e('Setup Wizard Connection', 'leadsforward-core'); ?></h2>
			<p class="description">
				<?php if ($wizard_complete) : ?>
					<?php esc_html_e('AI Studio uses the business info and keywords saved in the Setup Wizard.', 'leadsforward-core'); ?>
				<?php else : ?>
					<?php esc_html_e('Run the Setup Wizard first to store business info and keywords used by AI Studio.', 'leadsforward-core'); ?>
				<?php endif; ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=lf-ops')); ?>">
					<?php echo $wizard_complete ? esc_html__('Review Setup Wizard', 'leadsforward-core') : esc_html__('Run Setup Wizard', 'leadsforward-core'); ?>
				</a>
				<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lf-homepage-settings')); ?>">
					<?php esc_html_e('Open Homepage Builder', 'leadsforward-core'); ?>
				</a>
			</p>
		</div>
		<?php if (!empty($last_log)) : ?>
			<div class="card" style="max-width: 980px; padding: 16px; margin: 16px 0;">
				<h2 style="margin-top:0;"><?php esc_html_e('Last Full-Site Generation', 'leadsforward-core'); ?></h2>
				<p class="description">
					<?php
					$time = isset($last_log['time']) ? (string) $last_log['time'] : '';
					$pages = isset($last_log['pages_updated']) ? (int) $last_log['pages_updated'] : 0;
					$fields = isset($last_log['fields_updated']) ? (int) $last_log['fields_updated'] : 0;
					echo esc_html($time ? $time : __('No timestamp recorded.', 'leadsforward-core'));
					?>
				</p>
				<p>
					<?php echo esc_html(sprintf(__('Pages updated: %d', 'leadsforward-core'), $pages)); ?><br />
					<?php echo esc_html(sprintf(__('Fields updated: %d', 'leadsforward-core'), $fields)); ?>
				</p>
				<?php if (!empty($last_log['errors']) && is_array($last_log['errors'])) : ?>
					<p class="description"><strong><?php esc_html_e('Errors:', 'leadsforward-core'); ?></strong></p>
					<ul>
						<?php foreach ($last_log['errors'] as $err) : ?>
							<li><?php echo esc_html((string) $err); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ($saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('AI Studio settings saved.', 'leadsforward-core'); ?></p></div>
		<?php endif; ?>
		<?php if ($error) : ?>
			<div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('lf_ai_studio_save', 'lf_ai_studio_nonce'); ?>
			<input type="hidden" name="action" value="lf_ai_studio_save" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Enable AI', 'leadsforward-core'); ?></th>
					<td><label><input type="checkbox" name="lf_ai_studio_enabled" value="1" <?php checked($enabled); ?> /> <?php esc_html_e('Allow AI Studio runs', 'leadsforward-core'); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_ai_studio_webhook"><?php esc_html_e('Orchestrator Webhook URL', 'leadsforward-core'); ?></label></th>
					<td><input type="url" class="large-text" name="lf_ai_studio_webhook" id="lf_ai_studio_webhook" value="<?php echo esc_attr($webhook); ?>" placeholder="https://n8n.example.com/webhook/..." required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_ai_studio_secret"><?php esc_html_e('Orchestrator Shared Secret', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="large-text" name="lf_ai_studio_secret" id="lf_ai_studio_secret" value="<?php echo esc_attr($secret); ?>" required /></td>
				</tr>
				<tr>
					<th colspan="2" style="padding-top: 16px;"><?php esc_html_e('Site Inputs (used for regeneration)', 'leadsforward-core'); ?></th>
				</tr>
				<tr>
					<th scope="row"><label for="lf_homepage_city"><?php esc_html_e('City / Region', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="large-text" name="lf_homepage_city" id="lf_homepage_city" value="<?php echo esc_attr($homepage_city); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_homepage_keyword_primary"><?php esc_html_e('Primary homepage keyword', 'leadsforward-core'); ?></label></th>
					<td><input type="text" class="large-text" name="lf_homepage_keyword_primary" id="lf_homepage_keyword_primary" value="<?php echo esc_attr($homepage_keywords['primary']); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="lf_homepage_keyword_secondary"><?php esc_html_e('Secondary homepage keywords (optional)', 'leadsforward-core'); ?></label></th>
					<td><textarea class="large-text" name="lf_homepage_keyword_secondary" id="lf_homepage_keyword_secondary" rows="3"><?php echo esc_textarea(implode("\n", $homepage_keywords['secondary'])); ?></textarea></td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'leadsforward-core'); ?></button></p>
		</form>

		<hr />
		<h2><?php esc_html_e('Regenerate Site', 'leadsforward-core'); ?></h2>
		<p class="description"><?php esc_html_e('Uses inputs stored from the Setup Wizard (keywords).', 'leadsforward-core'); ?></p>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('lf_ai_studio_generate', 'lf_ai_studio_generate_nonce'); ?>
			<input type="hidden" name="action" value="lf_ai_studio_generate" />
			<p><button type="submit" class="button button-primary"><?php esc_html_e('Regenerate Site', 'leadsforward-core'); ?></button></p>
		</form>
		<p class="description"><?php esc_html_e('Advanced/debug only. Setup Wizard handles first‑run generation.', 'leadsforward-core'); ?></p>

		<h2><?php esc_html_e('Blueprint Preview', 'leadsforward-core'); ?></h2>
		<p class="description"><?php esc_html_e('Read-only snapshot of the homepage blueprint sent to the orchestrator.', 'leadsforward-core'); ?></p>
		<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea((string) $blueprint_json); ?></textarea>

		<h2><?php esc_html_e('Recent Jobs', 'leadsforward-core'); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Time', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Status', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('User', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Summary', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Actions', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($jobs)) : ?>
					<?php foreach ($jobs as $job) :
						$status = get_post_meta($job->ID, 'lf_ai_job_status', true);
						$status = $status ?: 'queued';
						$user_id = (int) get_post_meta($job->ID, 'lf_ai_job_user', true);
						$user = $user_id ? get_user_by('id', $user_id) : null;
						$summary = get_post_meta($job->ID, 'lf_ai_job_summary', true);
						?>
						<tr>
							<td><?php echo esc_html(get_date_from_gmt($job->post_date_gmt, get_option('date_format') . ' ' . get_option('time_format'))); ?></td>
							<td><?php echo esc_html($status); ?></td>
							<td><?php echo esc_html($user ? $user->display_name : ''); ?></td>
							<td><?php echo esc_html(is_string($summary) ? $summary : ''); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
									<?php wp_nonce_field('lf_ai_studio_retry', 'lf_ai_studio_retry_nonce'); ?>
									<input type="hidden" name="action" value="lf_ai_studio_retry" />
									<input type="hidden" name="job_id" value="<?php echo esc_attr((string) $job->ID); ?>" />
									<button type="submit" class="button button-small"><?php esc_html_e('Retry', 'leadsforward-core'); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5"><?php esc_html_e('No jobs yet.', 'leadsforward-core'); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function lf_ai_studio_run_generation(): array {
	$enabled = get_option('lf_ai_studio_enabled', '0') === '1';
	$webhook = (string) get_option('lf_ai_studio_webhook', '');
	$secret = (string) get_option('lf_ai_studio_secret', '');
	if (!$enabled) {
		return ['error' => __('AI Studio is disabled.', 'leadsforward-core')];
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
		return ['error' => __('AI Studio is disabled.', 'leadsforward-core')];
	}
	if ($webhook === '' || $secret === '') {
		error_log('LF DEBUG: Regenerate Site blocked: missing webhook or secret.');
		return ['error' => __('Webhook URL and shared secret are required.', 'leadsforward-core')];
	}
	$keywords = lf_homepage_keywords();
	if (empty($keywords['primary'])) {
		error_log('LF DEBUG: Regenerate Site blocked: missing primary keyword.');
		return ['error' => __('Homepage primary keyword is required.', 'leadsforward-core')];
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
	update_post_meta($job_id, 'lf_ai_job_status', 'running');
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
		'headers' => [
			'Authorization' => 'Bearer ' . $secret,
			'Content-Type' => 'application/json',
		],
		'timeout' => 45,
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
	if (!is_array($payload)) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', 'invalid_json');
		update_post_meta($job_id, 'lf_ai_job_response', $body);
		error_log('LF DEBUG: Regenerate Site failed: invalid JSON response.');
		return ['error' => __('Invalid JSON response from orchestrator.', 'leadsforward-core'), 'job_id' => $job_id];
	}
	if (!empty($payload['request_id'])) {
		update_post_meta($job_id, 'lf_ai_job_request_id', sanitize_text_field((string) $payload['request_id']));
	}
	update_post_meta($job_id, 'lf_ai_job_response', $payload);
	$apply_payload = $payload['apply'] ?? $payload;
	$errors = lf_ai_studio_validate_payload($apply_payload);
	if (!empty($errors)) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', implode('; ', $errors));
		return ['error' => __('Payload validation failed.', 'leadsforward-core'), 'job_id' => $job_id];
	}
	$apply_result = lf_apply_orchestrator_updates($apply_payload);
	update_post_meta($job_id, 'lf_ai_job_status', $apply_result['success'] ? 'done' : 'failed');
	update_post_meta($job_id, 'lf_ai_job_summary', $apply_result['summary'] ?? '');
	update_post_meta($job_id, 'lf_ai_job_changes', $apply_result['changes'] ?? []);
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

function lf_ai_studio_collect_writing_samples(): array {
	return [];
}

// Long-form density expansion – Step 3
function lf_ai_studio_section_length_targets(string $section_type): array {
	switch ($section_type) {
		case 'hero':
			return ['headline_subheadline_words' => ['min' => 20, 'max' => 40]];
		case 'benefits':
			return ['min_items' => 5, 'item_words' => ['min' => 40, 'max' => 80]];
		case 'process':
			return ['min_items' => 4, 'item_words' => ['min' => 40, 'max' => 80]];
		case 'service_details':
			return ['body_words' => ['min' => 600, 'max' => 1200]];
		case 'content_image':
		case 'image_content':
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
	$config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
	$order = function_exists('lf_homepage_controller_order') ? lf_homepage_controller_order() : [];
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
			'length_targets' => lf_ai_studio_section_length_targets(lf_ai_studio_homepage_section_type($section_id)),
			'allowed_field_keys' => $allowed_keys,
		];
	}
	if (empty($order) || empty($blueprint_sections)) {
		return ['error' => __('Homepage blueprint could not be built. Check homepage configuration.', 'leadsforward-core')];
	}

	$base = [
		'variation_seed' => $variation_seed,
		'business_name' => $business_name,
		'business_entity' => $entity,
		'niche' => $niche,
		'city_region' => $city,
		'keywords' => $keywords,
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
		],
	];
	$request_id = lf_ai_studio_homepage_request_id($base);

	$base['blueprint']['request_id'] = $request_id;
	return ['request_id' => $request_id] + $base;
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
	$max = 6;
	if (!empty($config['faq_accordion']['faq_max_items'])) {
		$max = (int) $config['faq_accordion']['faq_max_items'];
	}
	if ($max < 1) {
		$max = 6;
	}
	return $max;
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

function lf_ai_studio_build_post_blueprint(\WP_Post $post, string $page, string $page_intent): array {
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
			'length_targets' => lf_ai_studio_section_length_targets($type),
			'allowed_field_keys' => lf_ai_studio_homepage_allowed_field_keys($type, $registry[$type]),
		];
		$out_order[] = $instance_id;
	}
	return [
		'page' => $page,
		'post_id' => $post->ID,
		'page_intent' => $page_intent,
		'sections' => $out_sections,
		'order' => $out_order,
	];
}

function lf_ai_studio_build_full_site_payload(): array {
	$homepage_payload = lf_ai_studio_build_homepage_blueprint();
	if (!is_array($homepage_payload)) {
		return ['error' => __('Full site payload build failed.', 'leadsforward-core')];
	}
	if (!empty($homepage_payload['error'])) {
		return ['error' => (string) $homepage_payload['error']];
	}
	$homepage_blueprint = $homepage_payload['blueprint'] ?? [];
	if (!is_array($homepage_blueprint)) {
		return ['error' => __('Homepage blueprint is missing.', 'leadsforward-core')];
	}

	$blueprints = [];
	$blueprints[] = $homepage_blueprint;

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
		$blueprint = lf_ai_studio_build_post_blueprint($service, 'service', 'service_detail');
		if (!empty($blueprint)) {
			$blueprints[] = $blueprint;
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
		if (!$area instanceof \WP_Post) {
			continue;
		}
		$blueprint = lf_ai_studio_build_post_blueprint($area, 'service_area', 'service_area_detail');
		if (!empty($blueprint)) {
			$blueprints[] = $blueprint;
		}
	}

	$about = get_page_by_path('about-us');
	if ($about instanceof \WP_Post) {
		$blueprint = lf_ai_studio_build_post_blueprint($about, 'about', 'about_overview');
		if (!empty($blueprint)) {
			$blueprints[] = $blueprint;
		}
	}

	return [
		'request_id' => (string) ($homepage_payload['request_id'] ?? ''),
		'variation_seed' => (string) ($homepage_payload['variation_seed'] ?? ''),
		'business_name' => (string) ($homepage_payload['business_name'] ?? ''),
		'niche' => (string) ($homepage_payload['niche'] ?? ''),
		'city_region' => (string) ($homepage_payload['city_region'] ?? ''),
		'keywords' => $homepage_payload['keywords'] ?? [],
		'writing_samples' => lf_ai_studio_collect_writing_samples(),
		'business_entity' => $homepage_payload['business_entity'] ?? [],
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

function lf_apply_orchestrator_updates(array $response): array {
	$updates = $response['updates'] ?? [];
	if (!is_array($updates)) {
		return ['success' => false, 'summary' => __('Missing updates array.', 'leadsforward-core'), 'changes' => [], 'errors' => [__('Missing updates array.', 'leadsforward-core')]];
	}
	$errors = [];
	$changes = ['homepage' => false, 'posts' => [], 'faqs' => []];
	$pages_updated = 0;
	$fields_updated = 0;
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$homepage_config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];

	$homepage_updates = [];
	$homepage_fields = [];
	$post_updates = [];
	$faq_updates = [];

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
			continue;
		}
		if ($target === 'post_meta') {
			$post_id = absint($id);
			if (!$post_id) {
				$errors[] = sprintf(__('Post update at index %d is missing id.', 'leadsforward-core'), $index);
				continue;
			}
			$post_updates[] = $update;
			continue;
		}
		if ($target === 'faq') {
			$faq_updates[] = $update;
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
				$homepage_fields[$section_id][$field_key] = $value;
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
			$incoming_by_instance[$instance_id][$field_key] = $value;
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

	update_option('lf_ai_last_generation_log', [
		'time' => current_time('mysql'),
		'pages_updated' => $pages_updated,
		'fields_updated' => $fields_updated,
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
		$question = isset($fields['question']) ? sanitize_text_field((string) $fields['question']) : '';
		$answer = isset($fields['answer']) ? wp_kses_post((string) $fields['answer']) : '';
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
