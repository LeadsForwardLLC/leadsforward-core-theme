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
add_action('admin_menu', 'lf_ai_studio_register_menu', 13);
add_action('admin_post_lf_ai_studio_save', 'lf_ai_studio_handle_save');
add_action('admin_post_lf_ai_studio_generate', 'lf_ai_studio_handle_generate');
add_action('admin_post_lf_ai_studio_retry', 'lf_ai_studio_handle_retry');
add_action('admin_post_lf_ai_studio_manifest', 'lf_ai_studio_handle_manifest');
add_action('admin_post_lf_ai_studio_manifest_template', 'lf_ai_studio_handle_manifest_template');

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

function lf_ai_studio_handle_manifest(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_ai_studio_manifest', 'lf_ai_studio_manifest_nonce');
	$redirect = admin_url('admin.php?page=lf-ai-studio');
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
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		update_option('lf_ai_studio_manifest_errors', [__('Manifest JSON is invalid.', 'leadsforward-core')], false);
		wp_safe_redirect(add_query_arg('manifest_error', '1', $redirect));
		exit;
	}
	$errors = lf_ai_studio_validate_manifest($decoded);
	if (!empty($errors)) {
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
	wp_safe_redirect(add_query_arg('manifest', '1', $redirect));
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
	$manifest = lf_ai_studio_get_manifest();
	$manifest_errors = get_option('lf_ai_studio_manifest_errors', []);
	$manifest_saved = isset($_GET['manifest']) && $_GET['manifest'] === '1';
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
		<?php if ($manifest_saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('Manifest uploaded. Generation started.', 'leadsforward-core'); ?></p></div>
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

function lf_ai_studio_manifest_template(): array {
	return [
		'business' => [
			'name' => 'Your Business Name',
			'legal_name' => 'Your Legal Business Name',
			'phone' => '(555) 555-5555',
			'email' => 'contact@example.com',
			'address' => [
				'street' => '123 Main Street',
				'city' => 'Sarasota',
				'state' => 'FL',
				'zip' => '34232',
			],
			'primary_city' => 'Sarasota',
			'niche' => 'roofing',
			'site_style' => 'professional',
			'variation_seed' => '',
		],
		'homepage' => [
			'primary_keyword' => 'Roofing contractor Sarasota',
			'secondary_keywords' => [
				'Roof repair Sarasota',
				'Roof replacement Sarasota',
			],
		],
		'services' => [
			[
				'title' => 'Roof Repair',
				'slug' => 'roof-repair',
				'primary_keyword' => 'Roof repair Sarasota',
				'secondary_keywords' => ['Emergency roof repair', 'Leak repair'],
				'custom_cta_context' => 'Fast inspections and same-week repair slots.',
			],
		],
		'service_areas' => [
			[
				'city' => 'Sarasota',
				'state' => 'FL',
				'slug' => 'sarasota',
				'primary_keyword' => 'Roofing services Sarasota',
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
			'site_style' => sanitize_text_field((string) ($business['site_style'] ?? '')),
			'variation_seed' => sanitize_text_field((string) ($business['variation_seed'] ?? '')),
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
	$manifest = lf_ai_studio_get_manifest();
	if (!empty($manifest)) {
		$manifest_errors = lf_ai_studio_validate_manifest($manifest);
		if (!empty($manifest_errors)) {
			update_option('lf_ai_studio_manifest_errors', $manifest_errors, false);
			return ['error' => __('Manifest validation failed. Fix the uploaded manifest to continue.', 'leadsforward-core')];
		}
		$manifest = lf_ai_studio_normalize_manifest($manifest);
		$business_name = (string) ($manifest['business']['name'] ?? $business_name);
		$niche = (string) ($manifest['business']['niche'] ?? $niche);
		$city = (string) ($manifest['business']['primary_city'] ?? ($manifest['business']['address']['city'] ?? $city));
		$keywords = [
			'primary' => (string) ($manifest['homepage']['primary_keyword'] ?? ''),
			'secondary' => $manifest['homepage']['secondary_keywords'] ?? [],
		];
	}
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
			'length_targets' => lf_ai_studio_section_length_targets(lf_ai_studio_homepage_section_type($section_id), 'homepage'),
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

function lf_ai_studio_build_full_site_payload(): array {
	$manifest = lf_ai_studio_get_manifest();
	$use_manifest = !empty($manifest);
	if ($use_manifest) {
		$manifest_errors = lf_ai_studio_validate_manifest($manifest);
		if (!empty($manifest_errors)) {
			update_option('lf_ai_studio_manifest_errors', $manifest_errors, false);
			return ['error' => __('Manifest validation failed. Fix the uploaded manifest to continue.', 'leadsforward-core')];
		}
		$manifest = lf_ai_studio_normalize_manifest($manifest);
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
	if (!is_array($homepage_blueprint)) {
		return ['error' => __('Homepage blueprint is missing.', 'leadsforward-core')];
	}

	$blueprints = [];
	$blueprints[] = $homepage_blueprint;

	$service_keyword_map = $use_manifest ? lf_ai_studio_manifest_keyword_map($manifest, 'services') : [];
	$area_keyword_map = $use_manifest ? lf_ai_studio_manifest_keyword_map($manifest, 'service_areas') : [];

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

	$about = get_page_by_path('about-us');
	if ($about instanceof \WP_Post) {
		$blueprint = lf_ai_studio_build_post_blueprint($about, 'about', 'about_overview', '');
		if (!empty($blueprint)) {
			$blueprints[] = $blueprint;
		}
	}

	$business_name = (string) ($homepage_payload['business_name'] ?? '');
	$niche = (string) ($homepage_payload['niche'] ?? '');
	$city = (string) ($homepage_payload['city_region'] ?? '');
	$keywords = $homepage_payload['keywords'] ?? [];
	if ($use_manifest) {
		$business_name = (string) ($manifest['business']['name'] ?? $business_name);
		$niche = (string) ($manifest['business']['niche'] ?? $niche);
		$city = (string) ($manifest['business']['primary_city'] ?? ($manifest['business']['address']['city'] ?? $city));
		$keywords = [
			'primary' => (string) ($manifest['homepage']['primary_keyword'] ?? ''),
			'secondary' => $manifest['homepage']['secondary_keywords'] ?? [],
		];
	}
	return [
		'request_id' => (string) ($homepage_payload['request_id'] ?? ''),
		'variation_seed' => (string) ($homepage_payload['variation_seed'] ?? ''),
		'business_name' => $business_name,
		'niche' => $niche,
		'city_region' => $city,
		'keywords' => $keywords,
		'writing_samples' => lf_ai_studio_collect_writing_samples(),
		'business_entity' => $homepage_payload['business_entity'] ?? [],
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
				$homepage_fields[$section_id][$field_key] = lf_ai_studio_normalize_value($value);
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
			$incoming_by_instance[$instance_id][$field_key] = lf_ai_studio_normalize_value($value);
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
