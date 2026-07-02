<?php
/**
 * Page editor meta box: paste import on the page you're editing.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('add_meta_boxes', 'lf_pci_register_page_meta_box');
add_action('save_post_page', 'lf_pci_handle_page_meta_box_apply', 20, 2);

function lf_pci_register_page_meta_box(): void {
	if (!function_exists('lf_pci_page_supports_import')) {
		return;
	}
	add_meta_box(
		'lf-pci-import',
		__('Import page content', 'leadsforward-core'),
		'lf_pci_render_page_meta_box',
		'page',
		'normal',
		'high'
	);
}

function lf_pci_render_page_meta_box(\WP_Post $post): void {
	if (!function_exists('lf_pci_page_supports_import') || !lf_pci_page_supports_import($post)) {
		echo '<p class="description">' . esc_html__('Paste import is not configured for this page slug yet.', 'leadsforward-core') . '</p>';
		return;
	}
	if (!current_user_can(defined('LF_OPS_CAP') ? LF_OPS_CAP : 'edit_theme_options')) {
		echo '<p class="description">' . esc_html__('You need permission to manage theme options to import content.', 'leadsforward-core') . '</p>';
		return;
	}

	$page_slug = lf_pci_page_slug_for_post($post);
	$schema = function_exists('lf_pci_schema_for_slug') ? lf_pci_schema_for_slug($page_slug) : null;
	$has_template = $schema !== null;
	$bulk_url = admin_url('admin.php?page=lf-import-page-content');
	$download_url = wp_nonce_url(
		admin_url('admin.php?page=lf-import-page-content&lf_pci_template_slug=' . rawurlencode($page_slug)),
		'lf_pci_download_template'
	);
	$is_homepage = ($schema['storage'] ?? '') === 'homepage';
	$locked = is_array($schema['locked'] ?? null) ? $schema['locked'] : [];

	wp_nonce_field('lf_pci_page_import', 'lf_pci_page_nonce');
	?>
	<p class="description" style="margin-top:0;">
		<?php
		if ($has_template) {
			echo esc_html(sprintf(
				/* translators: 1: page slug, 2: template label */
				__('Paste the Google Doc for %2$s (slug: %1$s). Process/FAQ pull from Niche Content Library when left blank.', 'leadsforward-core'),
				$page_slug,
				(string) ($schema['label'] ?? $page_slug)
			));
			if ($locked !== []) {
				echo ' ' . esc_html(sprintf(
					/* translators: %s: comma-separated section types */
					__('Theme-controlled sections are preserved: %s.', 'leadsforward-core'),
					implode(', ', $locked)
				));
			}
			if ($is_homepage) {
				echo ' ' . esc_html__('Homepage content is saved to Homepage Builder settings (not Page Builder meta).', 'leadsforward-core');
			}
		} else {
			esc_html_e('This page slug is not in the import registry yet.', 'leadsforward-core');
		}
		?>
		<a href="<?php echo esc_url($bulk_url); ?>"><?php esc_html_e('Batch import', 'leadsforward-core'); ?></a>
		· <a href="<?php echo esc_url($download_url); ?>"><?php esc_html_e('Download template', 'leadsforward-core'); ?></a>
	</p>
	<textarea name="lf_pci_page_content" rows="12" class="widefat code" style="font-family:monospace;" placeholder="<?php esc_attr_e('Paste doc content here, or leave empty and use Import Page Content for batch uploads.', 'leadsforward-core'); ?>"><?php echo esc_textarea((string) get_post_meta($post->ID, '_lf_pci_draft_paste', true)); ?></textarea>
	<p style="margin:8px 0 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
		<button type="submit" class="button button-secondary" name="lf_pci_page_action" value="preview"><?php esc_html_e('Preview parse', 'leadsforward-core'); ?></button>
		<button type="submit" class="button button-primary" name="lf_pci_page_action" value="apply" <?php disabled(!$has_template); ?> onclick="return confirm('<?php echo esc_js($is_homepage ? __('Replace homepage sections with the pasted import? Service cards, reviews, and map stay theme-controlled.', 'leadsforward-core') : __('Replace this page\'s Page Builder sections with the pasted import?', 'leadsforward-core')); ?>');"><?php esc_html_e('Apply to this page', 'leadsforward-core'); ?></button>
	</p>
	<?php
	$notice = get_post_meta($post->ID, '_lf_pci_last_notice', true);
	if (is_array($notice) && !empty($notice['message'])) {
		$class = ($notice['type'] ?? '') === 'error' ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr($class) . ' inline" style="margin:12px 0 0;"><p>' . esc_html((string) $notice['message']) . '</p></div>';
		delete_post_meta($post->ID, '_lf_pci_last_notice');
	}
}

function lf_pci_handle_page_meta_box_apply(int $post_id, \WP_Post $post): void {
	if (!isset($_POST['lf_pci_page_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['lf_pci_page_nonce'])), 'lf_pci_page_import')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!current_user_can(defined('LF_OPS_CAP') ? LF_OPS_CAP : 'edit_theme_options')) {
		return;
	}
	$action = sanitize_key((string) ($_POST['lf_pci_page_action'] ?? ''));
	$raw = (string) wp_unslash($_POST['lf_pci_page_content'] ?? '');
	update_post_meta($post_id, '_lf_pci_draft_paste', $raw);

	if ($action === '' || $raw === '') {
		return;
	}

	$page_slug = lf_pci_page_slug_for_post($post);
	$parsed = lf_pci_parse_document($raw, $page_slug);
	$notice = ['type' => 'error', 'message' => ''];

	if ($action === 'preview') {
		if (!empty($parsed['errors'])) {
			$notice['message'] = (string) $parsed['errors'][0];
		} else {
			$found = implode(', ', (array) ($parsed['found_sections'] ?? []));
			$notice = ['type' => 'success', 'message' => sprintf(
				/* translators: %s: comma-separated section list */
				__('Parse OK — sections: %s. Click Apply to write to this page.', 'leadsforward-core'),
				$found !== '' ? $found : __('(none)', 'leadsforward-core')
			)];
		}
		update_post_meta($post_id, '_lf_pci_last_notice', $notice);
		return;
	}

	if ($action === 'apply') {
		if (!empty($parsed['errors'])) {
			$notice['message'] = (string) $parsed['errors'][0];
			update_post_meta($post_id, '_lf_pci_last_notice', $notice);
			return;
		}
		$result = lf_pci_apply_parsed($parsed, ['sync_mode' => 'force', 'page_id' => $post_id]);
		if (!empty($result['success'])) {
			$notice = ['type' => 'success', 'message' => __('Page content imported successfully.', 'leadsforward-core')];
			delete_post_meta($post_id, '_lf_pci_draft_paste');
		} else {
			$notice['message'] = (string) ($result['error'] ?? __('Import failed.', 'leadsforward-core'));
		}
		update_post_meta($post_id, '_lf_pci_last_notice', $notice);
	}
}
