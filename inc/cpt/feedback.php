<?php
/**
 * Feedback CPT for internal user testing.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_register_cpt_feedback(): void {
	$labels = [
		'name'               => _x('Feedback', 'post type general name', 'leadsforward-core'),
		'singular_name'      => _x('Feedback item', 'post type singular name', 'leadsforward-core'),
		'menu_name'          => _x('Feedback', 'admin menu', 'leadsforward-core'),
		'add_new'            => _x('Add New', 'feedback', 'leadsforward-core'),
		'add_new_item'       => __('Add feedback', 'leadsforward-core'),
		'edit_item'          => __('Edit feedback', 'leadsforward-core'),
		'new_item'           => __('New feedback', 'leadsforward-core'),
		'view_item'          => __('View feedback', 'leadsforward-core'),
		'search_items'       => __('Search feedback', 'leadsforward-core'),
		'not_found'          => __('No feedback found.', 'leadsforward-core'),
		'not_found_in_trash' => __('No feedback found in Trash.', 'leadsforward-core'),
	];

	$args = [
		'labels'              => $labels,
		'public'              => false,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_menu'        => false, // shown under LeadsForward menu.
		'show_in_rest'        => true,
		'rest_base'           => 'feedback',
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'hierarchical'        => false,
		'supports'            => ['title', 'editor', 'author', 'revisions'],
	];
	register_post_type('lf_feedback', $args);
}
add_action('init', 'lf_register_cpt_feedback');

function lf_feedback_statuses(): array {
	return [
		'new' => __('New', 'leadsforward-core'),
		'approved' => __('Approved', 'leadsforward-core'),
		'rejected' => __('Rejected', 'leadsforward-core'),
	];
}

function lf_feedback_get_status(int $post_id): string {
	$status = sanitize_key((string) get_post_meta($post_id, 'lf_feedback_status', true));
	return array_key_exists($status, lf_feedback_statuses()) ? $status : 'new';
}

function lf_feedback_set_status(int $post_id, string $status, string $admin_note = ''): void {
	$status = sanitize_key($status);
	if (!array_key_exists($status, lf_feedback_statuses())) {
		$status = 'new';
	}
	update_post_meta($post_id, 'lf_feedback_status', $status);
	if ($admin_note !== '') {
		update_post_meta($post_id, 'lf_feedback_admin_note', wp_kses_post($admin_note));
	}
}

function lf_feedback_add_meta_boxes(): void {
	add_meta_box(
		'lf_feedback_moderation',
		__('Moderation', 'leadsforward-core'),
		'lf_feedback_render_moderation_box',
		'lf_feedback',
		'side',
		'high'
	);
	add_meta_box(
		'lf_feedback_details',
		__('Details', 'leadsforward-core'),
		'lf_feedback_render_details_box',
		'lf_feedback',
		'normal',
		'default'
	);
}
add_action('add_meta_boxes', 'lf_feedback_add_meta_boxes');

function lf_feedback_render_moderation_box(\WP_Post $post): void {
	$status = lf_feedback_get_status((int) $post->ID);
	$statuses = lf_feedback_statuses();
	$note = (string) get_post_meta((int) $post->ID, 'lf_feedback_admin_note', true);
	wp_nonce_field('lf_feedback_moderation_save', 'lf_feedback_moderation_nonce');
	?>
	<p>
		<label for="lf_feedback_status"><strong><?php esc_html_e('Status', 'leadsforward-core'); ?></strong></label>
		<select name="lf_feedback_status" id="lf_feedback_status" class="widefat">
			<?php foreach ($statuses as $key => $label) : ?>
				<option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="lf_feedback_admin_note"><strong><?php esc_html_e('Admin note (optional)', 'leadsforward-core'); ?></strong></label>
		<textarea class="widefat" rows="4" name="lf_feedback_admin_note" id="lf_feedback_admin_note"><?php echo esc_textarea($note); ?></textarea>
	</p>
	<?php
}

function lf_feedback_render_details_box(\WP_Post $post): void {
	$user_id = (int) get_post_meta((int) $post->ID, 'lf_feedback_user_id', true);
	$page_url = (string) get_post_meta((int) $post->ID, 'lf_feedback_page_url', true);
	$category = (string) get_post_meta((int) $post->ID, 'lf_feedback_category', true);
	$severity = (string) get_post_meta((int) $post->ID, 'lf_feedback_severity', true);
	$expected = (string) get_post_meta((int) $post->ID, 'lf_feedback_expected', true);
	$actual = (string) get_post_meta((int) $post->ID, 'lf_feedback_actual', true);
	$repro = (string) get_post_meta((int) $post->ID, 'lf_feedback_repro_steps', true);
	$attachments = get_post_meta((int) $post->ID, 'lf_feedback_attachments', true);
	$attachments = is_array($attachments) ? array_values(array_filter(array_map('absint', $attachments))) : [];

	$user_label = $user_id > 0 ? ('#' . $user_id) : __('(unknown)', 'leadsforward-core');
	if ($user_id > 0) {
		$user = get_user_by('id', $user_id);
		if ($user instanceof \WP_User) {
			$user_label = sprintf('%s (#%d)', $user->user_login, $user_id);
		}
	}
	?>
	<table class="widefat striped">
		<tbody>
			<tr><th><?php esc_html_e('Submitted by', 'leadsforward-core'); ?></th><td><?php echo esc_html($user_label); ?></td></tr>
			<tr><th><?php esc_html_e('Page URL', 'leadsforward-core'); ?></th><td><?php echo $page_url ? '<a href="' . esc_url($page_url) . '" target="_blank" rel="noopener">' . esc_html($page_url) . '</a>' : ''; ?></td></tr>
			<tr><th><?php esc_html_e('Category', 'leadsforward-core'); ?></th><td><?php echo esc_html($category); ?></td></tr>
			<tr><th><?php esc_html_e('Severity', 'leadsforward-core'); ?></th><td><?php echo esc_html($severity); ?></td></tr>
			<tr><th><?php esc_html_e('Expected', 'leadsforward-core'); ?></th><td><?php echo esc_html($expected); ?></td></tr>
			<tr><th><?php esc_html_e('Actual', 'leadsforward-core'); ?></th><td><?php echo esc_html($actual); ?></td></tr>
			<tr><th><?php esc_html_e('Repro steps', 'leadsforward-core'); ?></th><td><?php echo esc_html($repro); ?></td></tr>
			<tr>
				<th><?php esc_html_e('Attachments', 'leadsforward-core'); ?></th>
				<td>
					<?php if ($attachments) : ?>
						<ul>
							<?php foreach ($attachments as $aid) : ?>
								<li><a href="<?php echo esc_url((string) wp_get_attachment_url($aid)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($aid) ?: ('Attachment #' . $aid)); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}

function lf_feedback_save_post(int $post_id, \WP_Post $post): void {
	if ($post->post_type !== 'lf_feedback') {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!isset($_POST['lf_feedback_moderation_nonce']) || !wp_verify_nonce((string) $_POST['lf_feedback_moderation_nonce'], 'lf_feedback_moderation_save')) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}
	$prev_status = lf_feedback_get_status($post_id);
	$status = isset($_POST['lf_feedback_status']) ? sanitize_key((string) wp_unslash($_POST['lf_feedback_status'])) : 'new';
	$note = isset($_POST['lf_feedback_admin_note']) ? (string) wp_unslash($_POST['lf_feedback_admin_note']) : '';
	$note = wp_kses_post($note);
	lf_feedback_set_status($post_id, $status, $note);

	$next_status = lf_feedback_get_status($post_id);
	if ($next_status !== $prev_status && in_array($next_status, ['approved', 'rejected'], true)) {
		$webhook = trim((string) get_option('options_lf_feedback_webhook_url', ''));
		if ($webhook !== '') {
			$payload = [
				'event' => 'lf_feedback_status_changed',
				'post_id' => $post_id,
				'status_from' => $prev_status,
				'status_to' => $next_status,
				'admin_note' => $note,
				'summary' => (string) get_the_title($post_id),
				'page_url' => (string) get_post_meta($post_id, 'lf_feedback_page_url', true),
				'submitted_user_id' => (int) get_post_meta($post_id, 'lf_feedback_user_id', true),
				'edit_url' => (string) get_edit_post_link($post_id, 'raw'),
				'time' => current_time('mysql'),
			];
			$headers = ['Content-Type' => 'application/json'];
			$secret = trim((string) get_option('options_lf_feedback_webhook_secret', ''));
			if ($secret !== '') {
				$headers['Authorization'] = 'Bearer ' . $secret;
			}
			wp_remote_post($webhook, [
				'timeout' => 8,
				'headers' => $headers,
				'body' => wp_json_encode($payload),
			]);
		}
	}
}
add_action('save_post', 'lf_feedback_save_post', 10, 2);

function lf_feedback_admin_columns(array $cols): array {
	$out = [];
	$out['cb'] = $cols['cb'] ?? '';
	$out['title'] = __('Summary', 'leadsforward-core');
	$out['lf_status'] = __('Status', 'leadsforward-core');
	$out['author'] = __('User', 'leadsforward-core');
	$out['date'] = __('Date', 'leadsforward-core');
	return $out;
}
add_filter('manage_lf_feedback_posts_columns', 'lf_feedback_admin_columns');

function lf_feedback_render_admin_column(string $col, int $post_id): void {
	if ($col === 'lf_status') {
		$status = lf_feedback_get_status($post_id);
		$labels = lf_feedback_statuses();
		echo esc_html($labels[$status] ?? $status);
	}
}
add_action('manage_lf_feedback_posts_custom_column', 'lf_feedback_render_admin_column', 10, 2);

