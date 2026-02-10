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
	update_option('lf_ai_studio_samples', isset($_POST['lf_ai_studio_samples']) ? sanitize_textarea_field(wp_unslash($_POST['lf_ai_studio_samples'])) : '');
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
	wp_safe_redirect(admin_url('admin.php?page=lf-ai-studio&saved=1'));
	exit;
}

function lf_ai_studio_handle_generate(): void {
	if (!current_user_can('edit_theme_options')) {
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
	$samples = (string) get_option('lf_ai_studio_samples', '');
	$scope = (string) get_option('lf_ai_studio_scope', 'all');
	$scope_types = get_option('lf_ai_studio_scope_types', []);
	$scope_types = is_array($scope_types) ? $scope_types : [];
	$style = (string) get_option('lf_ai_studio_style', 'professional');
	$jobs = get_posts([
		'post_type' => LF_AI_STUDIO_JOB_CPT,
		'post_status' => 'any',
		'posts_per_page' => 5,
		'orderby' => 'date',
		'order' => 'DESC',
	]);
	$samples_files = lf_ai_studio_get_sample_files();
	$blueprint_preview = lf_ai_studio_build_homepage_blueprint();
	$blueprint_json = wp_json_encode($blueprint_preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	?>
	<div class="wrap">
		<h1><?php esc_html_e('AI Studio (Advanced)', 'leadsforward-core'); ?></h1>
		<p class="description"><?php esc_html_e('Regenerate homepage content via the orchestrator. Setup Wizard handles first‑run inputs.', 'leadsforward-core'); ?></p>
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
			</table>
			<p><button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'leadsforward-core'); ?></button></p>
		</form>

		<hr />
		<h2><?php esc_html_e('Regenerate Homepage', 'leadsforward-core'); ?></h2>
		<p class="description"><?php esc_html_e('Uses homepage inputs stored from the Setup Wizard (keywords + samples).', 'leadsforward-core'); ?></p>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('lf_ai_studio_generate', 'lf_ai_studio_generate_nonce'); ?>
			<input type="hidden" name="action" value="lf_ai_studio_generate" />
			<p><button type="submit" class="button button-primary"><?php esc_html_e('Regenerate Homepage', 'leadsforward-core'); ?></button></p>
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
	$samples = lf_ai_studio_collect_samples();
	if (empty($samples)) {
		return ['error' => __('No writing samples found in /docs/content-samples/*.md.', 'leadsforward-core')];
	}
	$request = lf_ai_studio_build_blueprint();
	$job_id = lf_ai_studio_create_job($request);
	return lf_ai_studio_send_request($request, $job_id);
}

function lf_ai_studio_run_homepage_generation(): array {
	$enabled = get_option('lf_ai_studio_enabled', '0') === '1';
	$webhook = (string) get_option('lf_ai_studio_webhook', '');
	$secret = (string) get_option('lf_ai_studio_secret', '');
	if (!$enabled) {
		return ['error' => __('AI Studio is disabled.', 'leadsforward-core')];
	}
	if ($webhook === '' || $secret === '') {
		return ['error' => __('Webhook URL and shared secret are required.', 'leadsforward-core')];
	}
	$keywords = lf_homepage_keywords();
	if (empty($keywords['primary'])) {
		return ['error' => __('Homepage primary keyword is required.', 'leadsforward-core')];
	}
	$samples = lf_ai_studio_collect_selected_samples();
	if (empty($samples)) {
		return ['error' => __('Select 1–3 writing samples in the Setup Wizard.', 'leadsforward-core')];
	}
	$request = lf_ai_studio_build_homepage_blueprint();
	$job_id = lf_ai_studio_create_job($request);
	return lf_ai_studio_send_request($request, $job_id);
}

function lf_ai_studio_send_request(array $request, int $job_id): array {
	$webhook = (string) get_option('lf_ai_studio_webhook', '');
	$secret = (string) get_option('lf_ai_studio_secret', '');
	update_post_meta($job_id, 'lf_ai_job_status', 'running');
	$response = wp_remote_post($webhook, [
		'headers' => [
			'Authorization' => 'Bearer ' . $secret,
			'Content-Type' => 'application/json',
		],
		'timeout' => 45,
		'body' => wp_json_encode($request),
	]);
	if (is_wp_error($response)) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', $response->get_error_message());
		return ['error' => $response->get_error_message(), 'job_id' => $job_id];
	}
	$body = wp_remote_retrieve_body($response);
	$payload = json_decode($body, true);
	if (!is_array($payload)) {
		update_post_meta($job_id, 'lf_ai_job_status', 'failed');
		update_post_meta($job_id, 'lf_ai_job_error', 'invalid_json');
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
	$apply_result = lf_ai_studio_apply_payload($apply_payload);
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

function lf_ai_studio_get_sample_files(): array {
	$dir = LF_THEME_DIR . '/docs/content-samples';
	$files = glob($dir . '/*.md');
	if (!$files) {
		return [];
	}
	$names = array_map(function ($file) use ($dir) {
		return ltrim(str_replace($dir, '', (string) $file), '/');
	}, $files);
	return array_values($names);
}

function lf_ai_studio_collect_selected_samples(): array {
	$selected = get_option('lf_homepage_writing_samples', []);
	$selected = is_array($selected) ? array_values($selected) : [];
	$selected = array_values(array_filter(array_map('sanitize_file_name', $selected)));
	if (empty($selected)) {
		return [];
	}
	$dir = LF_THEME_DIR . '/docs/content-samples';
	$out = [];
	foreach ($selected as $file) {
		if (!str_ends_with($file, '.md')) {
			continue;
		}
		$path = $dir . '/' . $file;
		if (!is_readable($path)) {
			continue;
		}
		$content = file_get_contents($path);
		if (is_string($content) && trim($content) !== '') {
			$out[] = trim(wp_strip_all_tags($content));
		}
	}
	$admin = (string) get_option('lf_ai_studio_samples', '');
	if (trim($admin) !== '') {
		$out[] = trim(wp_strip_all_tags($admin));
	}
	return $out;
}

function lf_ai_studio_collect_samples(): array {
	$dir = LF_THEME_DIR . '/docs/content-samples';
	$files = glob($dir . '/*.md');
	$out = [];
	if ($files) {
		foreach ($files as $file) {
			$content = file_get_contents($file);
			if (is_string($content) && trim($content) !== '') {
				$out[] = trim($content);
			}
		}
	}
	$admin = (string) get_option('lf_ai_studio_samples', '');
	if (trim($admin) !== '') {
		$out[] = trim($admin);
	}
	return $out;
}

function lf_ai_studio_keywords(): array {
	$raw = (string) get_option('lf_ai_studio_keywords', '');
	$lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
	return array_values(array_map('sanitize_text_field', $lines));
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
	$samples = lf_ai_studio_collect_selected_samples();
	$config = function_exists('lf_get_homepage_section_config') ? lf_get_homepage_section_config() : [];
	$order = function_exists('lf_homepage_controller_order') ? lf_homepage_controller_order() : [];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$hero_variant = isset($config['hero']['variant']) ? (string) $config['hero']['variant'] : 'default';

	$sections = [];
	foreach ($order as $section_id) {
		$section = $config[$section_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$schema = $registry[$section_id] ?? [];
		$allowed = lf_ai_studio_homepage_allowed_fields($section_id, $schema);
		$sections[] = [
			'section_id' => $section_id,
			'enabled' => true,
			'allowed_fields' => $allowed,
		];
	}

	$internal_links = lf_ai_studio_homepage_internal_links();

	$base = [
		'business_name' => $business_name,
		'niche' => $niche,
		'city_region' => $city,
		'keywords' => $keywords,
		'hero_variant' => $hero_variant,
		'writing_samples' => $samples,
		'section_order' => $order,
		'sections' => $sections,
		'internal_links' => $internal_links,
	];
	$request_id = lf_ai_studio_homepage_request_id($base);

	return [
		'request_id' => $request_id,
	] + $base;
}

function lf_ai_studio_homepage_allowed_fields(string $section_id, array $schema): array {
	$fields = $schema['fields'] ?? [];
	$allowed_types = ['text', 'textarea', 'list', 'richtext'];
	$blocked_keys = [
		'section_background',
		'variant',
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
		$out[] = $section_id . '.' . $key;
	}
	return $out;
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
	$samples = lf_ai_studio_collect_samples();
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
		'writing_samples' => $samples,
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
	if (isset($payload['homepage']) && !is_array($payload['homepage'])) {
		$errors[] = __('Homepage payload must be an object.', 'leadsforward-core');
	}
	if (isset($payload['posts']) && !is_array($payload['posts'])) {
		$errors[] = __('Posts payload must be an array.', 'leadsforward-core');
	}
	if (!empty($payload['posts']) && is_array($payload['posts'])) {
		foreach ($payload['posts'] as $index => $post_payload) {
			if (!is_array($post_payload)) {
				$errors[] = sprintf(__('Post payload at index %d must be an object.', 'leadsforward-core'), $index);
				continue;
			}
			if (empty($post_payload['id'])) {
				$errors[] = sprintf(__('Post payload at index %d is missing id.', 'leadsforward-core'), $index);
			}
		}
	}
	return $errors;
}

function lf_ai_studio_apply_payload(array $payload): array {
	$changes = ['homepage' => false, 'posts' => []];
	if (!empty($payload['homepage']) && is_array($payload['homepage']) && function_exists('lf_get_homepage_section_config')) {
		$config = lf_get_homepage_section_config();
		$updated = $payload['homepage']['config'] ?? $payload['homepage'];
		if (is_array($updated)) {
			foreach ($config as $type => $settings) {
				if (!isset($updated[$type]) || !is_array($updated[$type])) {
					continue;
				}
				$config[$type] = array_merge($settings, lf_sections_sanitize_settings($type, $updated[$type]));
			}
			update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
			$changes['homepage'] = true;
		}
	}
	if (!empty($payload['posts']) && is_array($payload['posts'])) {
		foreach ($payload['posts'] as $post_payload) {
			if (!is_array($post_payload)) {
				continue;
			}
			$post_id = isset($post_payload['id']) ? absint($post_payload['id']) : 0;
			if (!$post_id) {
				continue;
			}
			$post = get_post($post_id);
			if (!$post instanceof \WP_Post) {
				continue;
			}
			$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : '';
			if ($context === '') {
				continue;
			}
			$config = lf_pb_get_post_config($post_id, $context);
			$sections = $config['sections'] ?? [];
			$order = $config['order'] ?? [];
			$incoming = $post_payload['config']['sections'] ?? $post_payload['sections'] ?? [];
			if (is_array($incoming)) {
				foreach ($sections as $instance_id => $section) {
					$type = $section['type'] ?? '';
					if ($type === '' || !isset($incoming[$instance_id]) || !is_array($incoming[$instance_id])) {
						continue;
					}
					$settings = $incoming[$instance_id]['settings'] ?? $incoming[$instance_id];
					if (!is_array($settings)) {
						continue;
					}
					$sections[$instance_id]['settings'] = array_merge(
						$section['settings'] ?? [],
						lf_sections_sanitize_settings($type, $settings)
					);
				}
			}
			$seo = $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false];
			if (!empty($post_payload['config']['seo']) && is_array($post_payload['config']['seo'])) {
				$seo = array_merge($seo, $post_payload['config']['seo']);
			}
			update_post_meta($post_id, LF_PB_META_KEY, ['order' => $order, 'sections' => $sections, 'seo' => $seo]);
			if (isset($post_payload['post_content'])) {
				wp_update_post(['ID' => $post_id, 'post_content' => wp_kses_post((string) $post_payload['post_content'])]);
			}
			$changes['posts'][] = $post_id;
		}
	}
	$summary = '';
	$summary_parts = [];
	if ($changes['homepage']) {
		$summary_parts[] = __('Homepage updated', 'leadsforward-core');
	}
	if (!empty($changes['posts'])) {
		$summary_parts[] = sprintf(__('Posts updated: %d', 'leadsforward-core'), count($changes['posts']));
	}
	$summary = implode('; ', $summary_parts);
	return ['success' => true, 'summary' => $summary, 'changes' => $changes];
}
