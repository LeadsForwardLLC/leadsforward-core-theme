<?php
/**
 * User-testing feedback submission UI (admin).
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_post_lf_feedback_submit', 'lf_handle_feedback_submit');

function lf_handle_feedback_submit(): void {
	if (!is_user_logged_in()) {
		wp_die(__('You must be logged in to submit feedback.', 'leadsforward-core'));
	}
	if (!current_user_can('edit_posts')) {
		wp_die(__('Insufficient permissions.', 'leadsforward-core'));
	}
	check_admin_referer('lf_feedback_submit', 'lf_feedback_nonce');

	$user_id = get_current_user_id();
	$summary = isset($_POST['lf_feedback_summary']) ? sanitize_text_field(wp_unslash((string) $_POST['lf_feedback_summary'])) : '';
	$page_url = isset($_POST['lf_feedback_page_url']) ? esc_url_raw(wp_unslash((string) $_POST['lf_feedback_page_url'])) : '';
	$category = isset($_POST['lf_feedback_category']) ? sanitize_key(wp_unslash((string) $_POST['lf_feedback_category'])) : 'ux';
	$severity = isset($_POST['lf_feedback_severity']) ? sanitize_key(wp_unslash((string) $_POST['lf_feedback_severity'])) : 'med';
	$expected = isset($_POST['lf_feedback_expected']) ? sanitize_textarea_field(wp_unslash((string) $_POST['lf_feedback_expected'])) : '';
	$actual = isset($_POST['lf_feedback_actual']) ? sanitize_textarea_field(wp_unslash((string) $_POST['lf_feedback_actual'])) : '';
	$repro = isset($_POST['lf_feedback_repro_steps']) ? sanitize_textarea_field(wp_unslash((string) $_POST['lf_feedback_repro_steps'])) : '';
	$details = isset($_POST['lf_feedback_details']) ? wp_kses_post(wp_unslash((string) $_POST['lf_feedback_details'])) : '';

	if ($summary === '') {
		$redirect = add_query_arg(['submitted' => '0', 'error' => 'missing_summary'], admin_url('admin.php?page=lf-user-testing'));
		wp_safe_redirect($redirect);
		exit;
	}

	$post_id = wp_insert_post([
		'post_type' => 'lf_feedback',
		'post_status' => 'publish',
		'post_title' => $summary,
		'post_content' => $details,
		'post_author' => $user_id,
	]);
	if (is_wp_error($post_id) || !$post_id) {
		$redirect = add_query_arg(['submitted' => '0', 'error' => 'insert_failed'], admin_url('admin.php?page=lf-user-testing'));
		wp_safe_redirect($redirect);
		exit;
	}
	$post_id = (int) $post_id;
	update_post_meta($post_id, 'lf_feedback_user_id', $user_id);
	update_post_meta($post_id, 'lf_feedback_page_url', $page_url);
	update_post_meta($post_id, 'lf_feedback_category', $category);
	update_post_meta($post_id, 'lf_feedback_severity', $severity);
	update_post_meta($post_id, 'lf_feedback_expected', $expected);
	update_post_meta($post_id, 'lf_feedback_actual', $actual);
	update_post_meta($post_id, 'lf_feedback_repro_steps', $repro);
	update_post_meta($post_id, 'lf_feedback_status', 'new');

	// Attachments: accept media IDs (already uploaded via WP media modal).
	$attachments_raw = isset($_POST['lf_feedback_attachments']) ? (string) wp_unslash($_POST['lf_feedback_attachments']) : '';
	$attachments = [];
	if ($attachments_raw !== '') {
		foreach (preg_split('/\s*,\s*/', $attachments_raw) as $part) {
			$id = absint($part);
			if ($id > 0) {
				$attachments[] = $id;
			}
		}
	}
	if ($attachments) {
		update_post_meta($post_id, 'lf_feedback_attachments', array_values(array_unique($attachments)));
	}

	$redirect = add_query_arg(['submitted' => '1'], admin_url('admin.php?page=lf-user-testing'));
	wp_safe_redirect($redirect);
	exit;
}

function lf_user_testing_render_page(): void {
	if (!current_user_can('edit_posts')) {
		echo '<div class="wrap"><h1>' . esc_html__('User testing', 'leadsforward-core') . '</h1><p>' . esc_html__('Permission denied.', 'leadsforward-core') . '</p></div>';
		return;
	}
	$user_id = get_current_user_id();
	$submitted = isset($_GET['submitted']) ? sanitize_key((string) $_GET['submitted']) : '';
	$error = isset($_GET['error']) ? sanitize_key((string) $_GET['error']) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e('User testing feedback', 'leadsforward-core'); ?></h1>
		<?php if ($submitted === '1') : ?>
			<div class="notice notice-success"><p><?php esc_html_e('Feedback submitted. Thank you!', 'leadsforward-core'); ?></p></div>
		<?php elseif ($submitted === '0') : ?>
			<div class="notice notice-error"><p><?php echo esc_html($error === 'missing_summary' ? __('Please add a short summary.', 'leadsforward-core') : __('Feedback submission failed.', 'leadsforward-core')); ?></p></div>
		<?php endif; ?>

		<div class="card" style="max-width: 980px;">
			<h2><?php esc_html_e('Submit feedback', 'leadsforward-core'); ?></h2>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('lf_feedback_submit', 'lf_feedback_nonce'); ?>
				<input type="hidden" name="action" value="lf_feedback_submit" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="lf_feedback_summary"><?php esc_html_e('Summary', 'leadsforward-core'); ?></label></th>
						<td><input type="text" class="regular-text" style="width:100%;" name="lf_feedback_summary" id="lf_feedback_summary" required placeholder="<?php esc_attr_e('Short summary (e.g., “Process steps picker missing on Service pages”)', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_feedback_page_url"><?php esc_html_e('Page URL', 'leadsforward-core'); ?></label></th>
						<td><input type="url" class="regular-text" style="width:100%;" name="lf_feedback_page_url" id="lf_feedback_page_url" placeholder="<?php esc_attr_e('Where you saw the issue (optional)', 'leadsforward-core'); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Category', 'leadsforward-core'); ?></th>
						<td>
							<select name="lf_feedback_category">
								<option value="bug"><?php esc_html_e('Bug', 'leadsforward-core'); ?></option>
								<option value="ux" selected><?php esc_html_e('UX', 'leadsforward-core'); ?></option>
								<option value="content"><?php esc_html_e('Content', 'leadsforward-core'); ?></option>
								<option value="other"><?php esc_html_e('Other', 'leadsforward-core'); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Severity', 'leadsforward-core'); ?></th>
						<td>
							<select name="lf_feedback_severity">
								<option value="low"><?php esc_html_e('Low', 'leadsforward-core'); ?></option>
								<option value="med" selected><?php esc_html_e('Medium', 'leadsforward-core'); ?></option>
								<option value="high"><?php esc_html_e('High', 'leadsforward-core'); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_feedback_expected"><?php esc_html_e('Expected', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" rows="3" name="lf_feedback_expected" id="lf_feedback_expected" placeholder="<?php esc_attr_e('What you expected to happen', 'leadsforward-core'); ?>"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_feedback_actual"><?php esc_html_e('Actual', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" rows="3" name="lf_feedback_actual" id="lf_feedback_actual" placeholder="<?php esc_attr_e('What actually happened', 'leadsforward-core'); ?>"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_feedback_repro_steps"><?php esc_html_e('Repro steps', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" rows="3" name="lf_feedback_repro_steps" id="lf_feedback_repro_steps" placeholder="<?php esc_attr_e('Steps to reproduce (optional but helpful)', 'leadsforward-core'); ?>"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_feedback_details"><?php esc_html_e('Details', 'leadsforward-core'); ?></label></th>
						<td><textarea class="large-text" rows="5" name="lf_feedback_details" id="lf_feedback_details" placeholder="<?php esc_attr_e('Anything else (screenshots can be linked below via attachment IDs)', 'leadsforward-core'); ?>"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="lf_feedback_attachments"><?php esc_html_e('Attachment IDs', 'leadsforward-core'); ?></label></th>
						<td>
							<input type="text" class="regular-text" style="width:100%;" name="lf_feedback_attachments" id="lf_feedback_attachments" placeholder="<?php esc_attr_e('Comma-separated Media IDs (optional)', 'leadsforward-core'); ?>" />
							<p class="description"><?php esc_html_e('Tip: upload screenshots in Media, then paste their IDs here.', 'leadsforward-core'); ?></p>
						</td>
					</tr>
				</table>

				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e('Submit feedback', 'leadsforward-core'); ?></button>
					<a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=lf_feedback')); ?>"><?php esc_html_e('View all feedback', 'leadsforward-core'); ?></a>
				</p>
			</form>
		</div>

		<h2><?php esc_html_e('Your recent submissions', 'leadsforward-core'); ?></h2>
		<?php
		$recent = get_posts([
			'post_type' => 'lf_feedback',
			'post_status' => 'publish',
			'posts_per_page' => 10,
			'author' => $user_id,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
		]);
		if (!$recent) :
			?>
			<p><?php esc_html_e('No submissions yet.', 'leadsforward-core'); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width: 980px;">
				<thead><tr><th><?php esc_html_e('Summary', 'leadsforward-core'); ?></th><th><?php esc_html_e('Status', 'leadsforward-core'); ?></th><th><?php esc_html_e('Date', 'leadsforward-core'); ?></th></tr></thead>
				<tbody>
					<?php foreach ($recent as $item) : ?>
						<?php
						$status = function_exists('lf_feedback_get_status') ? lf_feedback_get_status((int) $item->ID) : 'new';
						$labels = function_exists('lf_feedback_statuses') ? lf_feedback_statuses() : ['new' => 'New', 'approved' => 'Approved', 'rejected' => 'Rejected'];
						?>
						<tr>
							<td><a href="<?php echo esc_url(get_edit_post_link((int) $item->ID) ?: ''); ?>"><?php echo esc_html((string) $item->post_title); ?></a></td>
							<td><?php echo esc_html($labels[$status] ?? $status); ?></td>
							<td><?php echo esc_html(get_the_date('', $item)); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

