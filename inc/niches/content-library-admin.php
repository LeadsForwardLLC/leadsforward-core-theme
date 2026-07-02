<?php
/**
 * Admin UI for per-niche About page process + FAQ libraries.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_niche_content_library_admin_register_menu(): void {
	add_submenu_page(
		'lf-ops',
		__('Niche Content Library', 'leadsforward-core'),
		__('Niche Content Library', 'leadsforward-core'),
		defined('LF_OPS_CAP') ? LF_OPS_CAP : 'manage_options',
		'lf-niche-content-library',
		'lf_niche_content_library_admin_render'
	);
}
add_action('admin_menu', 'lf_niche_content_library_admin_register_menu', 25);

function lf_niche_content_library_admin_render(): void {
	if (!current_user_can(defined('LF_OPS_CAP') ? LF_OPS_CAP : 'manage_options')) {
		wp_die(esc_html__('You do not have permission to access this page.', 'leadsforward-core'));
	}

	$registry = function_exists('lf_get_niche_registry') ? lf_get_niche_registry() : [];
	$niche_slug = sanitize_title((string) ($_GET['niche'] ?? 'foundation-repair'));
	if ($niche_slug === '' || !isset($registry[$niche_slug])) {
		$niche_slug = array_key_first($registry) ?: 'foundation-repair';
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lf_niche_content_library_nonce'])) {
		check_admin_referer('lf_niche_content_library_save', 'lf_niche_content_library_nonce');
		$posted_slug = sanitize_title((string) ($_POST['niche_slug'] ?? ''));
		if ($posted_slug !== '') {
			$process = [];
			$titles = (array) ($_POST['process_title'] ?? []);
			$bodies = (array) ($_POST['process_body'] ?? []);
			foreach ($titles as $i => $title) {
				$body = $bodies[$i] ?? '';
				$process[] = [
					'title' => sanitize_text_field((string) $title),
					'body' => sanitize_textarea_field((string) $body),
				];
			}
			$faqs = [];
			$questions = (array) ($_POST['faq_question'] ?? []);
			$answers = (array) ($_POST['faq_answer'] ?? []);
			foreach ($questions as $i => $question) {
				$answer = $answers[$i] ?? '';
				$faqs[] = [
					'question' => sanitize_text_field((string) $question),
					'answer' => wp_kses_post((string) $answer),
				];
			}
			$stored = get_option(LF_NICHE_CONTENT_LIBRARY_OPTION, []);
			$stored = is_array($stored) ? $stored : [];
			$stored[$posted_slug] = ['about' => ['process' => $process, 'faqs' => $faqs]];
			lf_niche_content_library_save($stored);
			$niche_slug = $posted_slug;
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Niche content library saved.', 'leadsforward-core') . '</p></div>';
		}
	}

	$library = lf_niche_content_library_get_for_niche($niche_slug);
	$process_rows = $library['process'] ?? [];
	$faq_rows = $library['faqs'] ?? [];
	$niche_name = (string) ($registry[$niche_slug]['name'] ?? $niche_slug);
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Niche Content Library', 'leadsforward-core'); ?></h1>
		<p><?php esc_html_e('Edit reusable About page process steps and FAQs per niche. When a site is generated or reseeded, these become Process Step and FAQ posts that can still be edited individually afterward.', 'leadsforward-core'); ?></p>

		<form method="get" style="margin: 1rem 0;">
			<input type="hidden" name="page" value="lf-niche-content-library" />
			<label for="lf-niche-select"><strong><?php esc_html_e('Niche', 'leadsforward-core'); ?></strong></label>
			<select id="lf-niche-select" name="niche" onchange="this.form.submit()">
				<?php foreach ($registry as $slug => $entry) : ?>
					<option value="<?php echo esc_attr((string) $slug); ?>" <?php selected($niche_slug, $slug); ?>>
						<?php echo esc_html((string) ($entry['name'] ?? $slug)); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>

		<form method="post">
			<?php wp_nonce_field('lf_niche_content_library_save', 'lf_niche_content_library_nonce'); ?>
			<input type="hidden" name="niche_slug" value="<?php echo esc_attr($niche_slug); ?>" />

			<h2><?php echo esc_html(sprintf(__('About page — %s', 'leadsforward-core'), $niche_name)); ?></h2>
			<p class="description"><?php esc_html_e('Tokens: {business}, {city}, {city_line}, {phone}, {email}, {address}', 'leadsforward-core'); ?></p>

			<h3><?php esc_html_e('Process steps', 'leadsforward-core'); ?></h3>
			<table class="widefat striped" id="lf-process-rows">
				<thead>
					<tr>
						<th style="width:28%"><?php esc_html_e('Step title', 'leadsforward-core'); ?></th>
						<th><?php esc_html_e('Step description', 'leadsforward-core'); ?></th>
						<th style="width:60px"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($process_rows as $row) : ?>
						<tr>
							<td><input type="text" class="large-text" name="process_title[]" value="<?php echo esc_attr((string) ($row['title'] ?? '')); ?>" /></td>
							<td><textarea class="large-text" rows="3" name="process_body[]"><?php echo esc_textarea((string) ($row['body'] ?? '')); ?></textarea></td>
							<td><button type="button" class="button lf-remove-row"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="lf-add-process-row"><?php esc_html_e('Add process step', 'leadsforward-core'); ?></button></p>

			<h3><?php esc_html_e('FAQs', 'leadsforward-core'); ?></h3>
			<table class="widefat striped" id="lf-faq-rows">
				<thead>
					<tr>
						<th style="width:32%"><?php esc_html_e('Question', 'leadsforward-core'); ?></th>
						<th><?php esc_html_e('Answer', 'leadsforward-core'); ?></th>
						<th style="width:60px"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($faq_rows as $row) : ?>
						<tr>
							<td><input type="text" class="large-text" name="faq_question[]" value="<?php echo esc_attr((string) ($row['question'] ?? '')); ?>" /></td>
							<td><textarea class="large-text" rows="4" name="faq_answer[]"><?php echo esc_textarea((string) ($row['answer'] ?? '')); ?></textarea></td>
							<td><button type="button" class="button lf-remove-row"><?php esc_html_e('Remove', 'leadsforward-core'); ?></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="lf-add-faq-row"><?php esc_html_e('Add FAQ', 'leadsforward-core'); ?></button></p>

			<?php submit_button(__('Save library', 'leadsforward-core')); ?>
		</form>
	</div>
	<script>
	(function () {
		function bindRemoveButtons(scope) {
			(scope || document).querySelectorAll('.lf-remove-row').forEach(function (btn) {
				if (btn.dataset.bound) return;
				btn.dataset.bound = '1';
				btn.addEventListener('click', function () {
					var row = btn.closest('tr');
					if (row) row.remove();
				});
			});
		}
		bindRemoveButtons();
		document.getElementById('lf-add-process-row')?.addEventListener('click', function () {
			var tbody = document.querySelector('#lf-process-rows tbody');
			if (!tbody) return;
			var tr = document.createElement('tr');
			tr.innerHTML = '<td><input type="text" class="large-text" name="process_title[]" value="" /></td>'
				+ '<td><textarea class="large-text" rows="3" name="process_body[]"></textarea></td>'
				+ '<td><button type="button" class="button lf-remove-row"><?php echo esc_js(__('Remove', 'leadsforward-core')); ?></button></td>';
			tbody.appendChild(tr);
			bindRemoveButtons(tr);
		});
		document.getElementById('lf-add-faq-row')?.addEventListener('click', function () {
			var tbody = document.querySelector('#lf-faq-rows tbody');
			if (!tbody) return;
			var tr = document.createElement('tr');
			tr.innerHTML = '<td><input type="text" class="large-text" name="faq_question[]" value="" /></td>'
				+ '<td><textarea class="large-text" rows="4" name="faq_answer[]"></textarea></td>'
				+ '<td><button type="button" class="button lf-remove-row"><?php echo esc_js(__('Remove', 'leadsforward-core')); ?></button></td>';
			tbody.appendChild(tr);
			bindRemoveButtons(tr);
		});
	})();
	</script>
	<?php
}
