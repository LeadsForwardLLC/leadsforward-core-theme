<?php
/**
 * AI editing: admin-only UI. "Edit with AI" meta box, diff preview, apply/rollback.
 * No frontend scripts; no API keys. All AI calls server-side.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_AI_CAP = 'edit_posts';

add_action('add_meta_boxes', 'lf_ai_editing_meta_box');
add_action('admin_enqueue_scripts', 'lf_ai_editing_scripts');
add_action('wp_ajax_lf_ai_generate', 'lf_ai_ajax_generate');
add_action('wp_ajax_lf_ai_apply', 'lf_ai_ajax_apply');
add_action('wp_ajax_lf_ai_rollback', 'lf_ai_ajax_rollback');
add_action('wp_ajax_lf_ai_rollback_latest', 'lf_ai_ajax_rollback_latest');

function lf_ai_editing_meta_box(): void {
	if (!current_user_can(LF_AI_CAP)) {
		return;
	}
	$screen = get_current_screen();
	if (!$screen || $screen->base !== 'post') {
		return;
	}
	$post = get_post();
	if (!$post) {
		return;
	}
	$context_type = lf_ai_editing_context_type($post);
	$context_id   = lf_ai_editing_context_id($post);
	$editable     = lf_get_ai_editable_fields($context_id);
	if (empty($editable)) {
		return;
	}
	add_meta_box(
		'lf_ai_editing',
		__('Edit with AI', 'leadsforward-core'),
		'lf_ai_editing_meta_box_callback',
		$screen->post_type,
		'side',
		'default',
		['context_type' => $context_type, 'context_id' => $context_id, 'editable' => $editable]
	);
}

function lf_ai_editing_context_type(\WP_Post $post): string {
	$front_id = (int) get_option('page_on_front');
	if ($post->post_type === 'page' && (int) $post->ID === $front_id) {
		return 'homepage';
	}
	return $post->post_type;
}

function lf_ai_editing_context_id(\WP_Post $post): string|int {
	$front_id = (int) get_option('page_on_front');
	if ($post->post_type === 'page' && (int) $post->ID === $front_id) {
		return 'homepage';
	}
	return $post->ID;
}

function lf_ai_editing_meta_box_callback(\WP_Post $post, array $box): void {
	$context_type = $box['args']['context_type'] ?? '';
	$context_id   = $box['args']['context_id'] ?? '';
	$editable     = $box['args']['editable'] ?? [];
	$labels       = $editable;
	?>
	<div class="lf-ai-editing" data-context-type="<?php echo esc_attr($context_type); ?>" data-context-id="<?php echo esc_attr((string) $context_id); ?>">
		<p class="lf-ai-description"><?php esc_html_e('Suggest edits using plain English. Only conversion copy and allowed fields will be changed. URLs, slugs, and schema are never modified.', 'leadsforward-core'); ?></p>
		<label for="lf-ai-prompt" class="screen-reader-text"><?php esc_html_e('Edit prompt', 'leadsforward-core'); ?></label>
		<textarea id="lf-ai-prompt" class="widefat" rows="3" placeholder="<?php esc_attr_e('e.g. Make this more urgent for emergency roofing customers', 'leadsforward-core'); ?>"></textarea>
		<p>
			<button type="button" class="button button-primary" id="lf-ai-submit"><?php esc_html_e('Generate suggestions', 'leadsforward-core'); ?></button>
		</p>
		<div id="lf-ai-status" class="lf-ai-status" aria-live="polite"></div>
		<div id="lf-ai-diff" class="lf-ai-diff" style="display:none;">
			<h4><?php esc_html_e('Review suggestions', 'leadsforward-core'); ?></h4>
			<table class="widefat striped" id="lf-ai-diff-table"></table>
			<p>
				<button type="button" class="button button-primary" id="lf-ai-apply"><?php esc_html_e('Apply', 'leadsforward-core'); ?></button>
				<button type="button" class="button" id="lf-ai-reject"><?php esc_html_e('Reject', 'leadsforward-core'); ?></button>
			</p>
		</div>
		<?php
		$log = lf_ai_get_log();
		$relevant = array_filter($log, function ($e) use ($context_type, $context_id) {
			return ($e['context_type'] ?? '') === $context_type && (string) ($e['context_id'] ?? '') === (string) $context_id;
		});
		$relevant = array_slice($relevant, 0, 5);
		if (!empty($relevant)) {
			?>
			<div class="lf-ai-log" style="margin-top:1em;">
				<h4><?php esc_html_e('Recent AI edits', 'leadsforward-core'); ?></h4>
				<ul class="lf-ai-log-list">
					<?php foreach ($relevant as $entry) {
						$id = $entry['id'] ?? '';
						$rolled = !empty($entry['rolled_back']);
						$time = isset($entry['time']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $entry['time']) : '';
						?>
						<li>
							<?php echo esc_html($time); ?>
							<?php if (!$rolled && $id) { ?>
								<button type="button" class="button button-small lf-ai-rollback" data-id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Rollback', 'leadsforward-core'); ?></button>
							<?php } elseif ($rolled) { ?>
								<span class="lf-ai-rolled"><?php esc_html_e('Rolled back', 'leadsforward-core'); ?></span>
							<?php } ?>
						</li>
					<?php } ?>
				</ul>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}

function lf_ai_editing_scripts(string $hook): void {
	if (!current_user_can(LF_AI_CAP)) {
		return;
	}
	if ($hook !== 'post.php' && $hook !== 'post-new.php') {
		return;
	}
	$post = get_post();
	if (!$post) {
		return;
	}
	$context_id = $post->post_type === 'page' && (int) $post->ID === (int) get_option('page_on_front') ? 'homepage' : $post->ID;
	$editable = lf_get_ai_editable_fields($context_id);
	if (empty($editable)) {
		return;
	}
	wp_enqueue_script(
		'lf-ai-editing',
		LF_THEME_URI . '/inc/ai-editing/admin-ui.js',
		['jquery'],
		LF_THEME_VERSION,
		true
	);
	wp_localize_script('lf-ai-editing', 'lfAiEditing', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('lf_ai_editing'),
		'labels'   => $editable,
	]);
	wp_register_style('lf-ai-editing', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-ai-editing');
	wp_add_inline_style('lf-ai-editing', '
		.lf-ai-diff table { table-layout: fixed; }
		.lf-ai-diff pre { white-space: pre-wrap; word-wrap: break-word; margin: 0; font-size: 12px; max-height: 120px; overflow: auto; }
		.lf-ai-status.error { color: #b32d2e; }
	');
}

function lf_ai_ajax_generate(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id   = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$prompt       = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
	if ($prompt === '' || $context_type === '') {
		wp_send_json_error(['message' => __('Invalid request.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$result = lf_ai_generate_proposal($context_type, $context_id_use, $prompt);
	if (!$result['success']) {
		wp_send_json_error(['message' => $result['error']]);
	}
	$current = lf_ai_get_current_values($context_type, $context_id_use, array_keys($result['proposed']));
	wp_send_json_success([
		'proposed' => $result['proposed'],
		'current'  => $current,
		'labels'   => array_intersect_key(lf_get_ai_editable_fields($context_id_use), $result['proposed']),
	]);
}

function lf_ai_ajax_apply(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id   = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	$prompt_snippet = isset($_POST['prompt_snippet']) ? sanitize_textarea_field(wp_unslash($_POST['prompt_snippet'])) : '';
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$stored = lf_ai_get_stored_proposal($context_type, $context_id_use);
	if (!$stored || empty($stored['proposed'])) {
		wp_send_json_error(['message' => __('No pending suggestions. Generate again.', 'leadsforward-core')]);
	}
	$result = lf_ai_apply_proposal($context_type, $context_id_use, $stored['proposed'], $prompt_snippet);
	if (!$result['success']) {
		wp_send_json_error(['message' => __('Apply failed.', 'leadsforward-core')]);
	}
	wp_send_json_success(['log_id' => $result['log_id'], 'reload' => true]);
}

function lf_ai_ajax_rollback(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
	if ($id === '') {
		wp_send_json_error(['message' => __('Invalid request.', 'leadsforward-core')]);
	}
	$ok = lf_ai_rollback($id);
	if (!$ok) {
		wp_send_json_error(['message' => __('Rollback failed or already rolled back.', 'leadsforward-core')]);
	}
	wp_send_json_success(['reload' => true]);
}

function lf_ai_ajax_rollback_latest(): void {
	check_ajax_referer('lf_ai_editing', 'nonce');
	if (!current_user_can(LF_AI_CAP)) {
		wp_send_json_error(['message' => __('Permission denied.', 'leadsforward-core')]);
	}
	$context_type = isset($_POST['context_type']) ? sanitize_text_field(wp_unslash($_POST['context_type'])) : '';
	$context_id   = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
	if ($context_type === '' || $context_id === '') {
		wp_send_json_error(['message' => __('Invalid request.', 'leadsforward-core')]);
	}
	$context_id_use = $context_id === 'homepage' ? 'homepage' : (int) $context_id;
	$log_id = lf_ai_latest_rollback_candidate($context_type, $context_id_use, get_current_user_id());
	if ($log_id === '') {
		wp_send_json_error(['message' => __('No recent AI change found for this page.', 'leadsforward-core')]);
	}
	$ok = lf_ai_rollback($log_id);
	if (!$ok) {
		wp_send_json_error(['message' => __('Rollback failed or already rolled back.', 'leadsforward-core')]);
	}
	wp_send_json_success(['reload' => true, 'log_id' => $log_id]);
}
