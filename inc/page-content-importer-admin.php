<?php
/**
 * Admin UI: paste Google Doc content → populate page templates.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_pci_admin_register_menu(): void {
	add_submenu_page(
		'lf-ops',
		__('Import Page Content', 'leadsforward-core'),
		__('Import Page Content', 'leadsforward-core'),
		defined('LF_OPS_CAP') ? LF_OPS_CAP : 'manage_options',
		'lf-import-page-content',
		'lf_pci_admin_render'
	);
}
add_action('admin_menu', 'lf_pci_admin_register_menu', 26);

function lf_pci_admin_handle_download(): void {
	if (!isset($_GET['page']) || $_GET['page'] !== 'lf-import-page-content') {
		return;
	}
	if (!isset($_GET['lf_pci_download']) || $_GET['lf_pci_download'] !== 'about-us-template') {
		return;
	}
	if (!current_user_can(defined('LF_OPS_CAP') ? LF_OPS_CAP : 'manage_options')) {
		wp_die(esc_html__('You do not have permission to download this template.', 'leadsforward-core'));
	}
	check_admin_referer('lf_pci_download_template');
	$body = function_exists('lf_pci_about_us_template') ? lf_pci_about_us_template() : '';
	header('Content-Type: text/plain; charset=utf-8');
	header('Content-Disposition: attachment; filename="about-us-content-template.txt"');
	header('Content-Length: ' . (string) strlen($body));
	echo $body;
	exit;
}
add_action('admin_init', 'lf_pci_admin_handle_download');

/**
 * @param array<string, mixed> $parsed
 */
function lf_pci_admin_render_preview(array $parsed): void {
	$sections = is_array($parsed['sections'] ?? null) ? $parsed['sections'] : [];
	$process = is_array($parsed['process_steps'] ?? null) ? $parsed['process_steps'] : [];
	$faqs = is_array($parsed['faqs'] ?? null) ? $parsed['faqs'] : [];
	$seo = is_array($parsed['seo'] ?? null) ? $parsed['seo'] : [];
	?>
	<div class="lf-pci-preview" style="margin:1.5rem 0;padding:1rem;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;">
		<h2><?php esc_html_e('Parse preview', 'leadsforward-core'); ?></h2>
		<p>
			<strong><?php esc_html_e('Sections found:', 'leadsforward-core'); ?></strong>
			<?php echo esc_html(implode(', ', (array) ($parsed['found_sections'] ?? []))); ?>
		</p>
		<table class="widefat striped" style="margin-top:1rem;">
			<thead>
				<tr>
					<th><?php esc_html_e('Section', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Status', 'leadsforward-core'); ?></th>
					<th><?php esc_html_e('Preview', 'leadsforward-core'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$schema = function_exists('lf_pci_about_us_schema') ? lf_pci_about_us_schema() : ['order' => []];
				foreach ($schema['order'] as $type) :
					$settings = is_array($sections[$type] ?? null) ? $sections[$type] : [];
					$preview = '';
					if ($type === 'hero') {
						$preview = (string) ($settings['hero_headline'] ?? '');
					} elseif ($type === 'cta') {
						$preview = (string) ($settings['cta_headline'] ?? '');
					} elseif (in_array($type, ['content_image', 'image_content'], true)) {
						$preview = (string) ($settings['section_heading'] ?? '');
					} else {
						$preview = (string) ($settings['section_heading'] ?? '');
					}
					$preview = function_exists('lf_pci_fill_tokens') ? lf_pci_fill_tokens($preview) : $preview;
					?>
					<tr>
						<td><code><?php echo esc_html($type); ?></code></td>
						<td><?php echo $settings !== [] ? '✓' : '—'; ?></td>
						<td><?php echo esc_html(wp_html_excerpt($preview, 80, '…')); ?></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td><code>process_steps</code></td>
					<td><?php echo $process !== [] ? '✓ ' . count($process) : '—'; ?></td>
					<td><?php echo $process !== [] ? esc_html((string) ($process[0]['title'] ?? '')) : ''; ?></td>
				</tr>
				<tr>
					<td><code>lf_faq</code></td>
					<td><?php echo $faqs !== [] ? '✓ ' . count($faqs) : '—'; ?></td>
					<td><?php echo $faqs !== [] ? esc_html((string) ($faqs[0]['question'] ?? '')) : ''; ?></td>
				</tr>
				<tr>
					<td><code>seo</code></td>
					<td><?php echo (($seo['title'] ?? '') !== '' || ($seo['description'] ?? '') !== '') ? '✓' : '—'; ?></td>
					<td><?php echo esc_html(wp_html_excerpt((string) ($seo['title'] ?? ''), 80, '…')); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}

function lf_pci_admin_render(): void {
	if (!current_user_can(defined('LF_OPS_CAP') ? LF_OPS_CAP : 'manage_options')) {
		wp_die(esc_html__('You do not have permission to access this page.', 'leadsforward-core'));
	}

	$page_id = function_exists('lf_pci_get_about_page_id') ? lf_pci_get_about_page_id() : 0;
	$raw = '';
	$parsed = null;
	$apply_result = null;
	$action = '';

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lf_pci_nonce'])) {
		check_admin_referer('lf_pci_import', 'lf_pci_nonce');
		$raw = (string) wp_unslash($_POST['lf_pci_content'] ?? '');
		$action = sanitize_key((string) ($_POST['lf_pci_action'] ?? 'preview'));

		if ($raw !== '' && function_exists('lf_pci_parse_about_us')) {
			$parsed = lf_pci_parse_about_us($raw);
		}

		if ($action === 'apply' && is_array($parsed) && empty($parsed['errors'])) {
			if ($page_id <= 0) {
				echo '<div class="notice notice-error"><p>' . esc_html__('About Us page (slug: about-us) was not found. Create the page first.', 'leadsforward-core') . '</p></div>';
			} else {
				$apply_result = lf_pci_apply_about_us(
					$page_id,
					(array) ($parsed['sections'] ?? []),
					(array) ($parsed['process_steps'] ?? []),
					(array) ($parsed['faqs'] ?? []),
					(array) ($parsed['seo'] ?? ['title' => '', 'description' => '']),
					['sync_mode' => 'force']
				);
				if (!empty($apply_result['success'])) {
					$edit_url = get_edit_post_link($page_id, 'raw');
					$view_url = get_permalink($page_id);
					echo '<div class="notice notice-success is-dismissible"><p>';
					echo esc_html__('About Us page populated successfully.', 'leadsforward-core') . ' ';
					if (is_string($view_url) && $view_url !== '') {
						echo '<a href="' . esc_url($view_url) . '" target="_blank" rel="noopener">' . esc_html__('View page', 'leadsforward-core') . '</a>';
					}
					if (is_string($edit_url) && $edit_url !== '') {
						echo ' · <a href="' . esc_url($edit_url) . '">' . esc_html__('Edit page', 'leadsforward-core') . '</a>';
					}
					echo '</p></div>';
					if (!empty($apply_result['process_ids'])) {
						$src = ($apply_result['process_source'] ?? '') === 'library'
							? __('from Niche Content Library', 'leadsforward-core')
							: __('from your doc', 'leadsforward-core');
						echo '<div class="notice notice-info"><p>' . esc_html(sprintf(
							/* translators: 1: count, 2: source label */
							__('Process: %1$d steps wired (%2$s).', 'leadsforward-core'),
							count((array) $apply_result['process_ids']),
							$src
						)) . '</p></div>';
					}
					if (!empty($apply_result['faq_ids'])) {
						$src = ($apply_result['faq_source'] ?? '') === 'library'
							? __('from Niche Content Library', 'leadsforward-core')
							: __('from your doc', 'leadsforward-core');
						echo '<div class="notice notice-info"><p>' . esc_html(sprintf(
							/* translators: 1: count, 2: source label */
							__('FAQs: %1$d items wired (%2$s).', 'leadsforward-core'),
							count((array) $apply_result['faq_ids']),
							$src
						)) . '</p></div>';
					}
				} elseif (!empty($apply_result['error'])) {
					echo '<div class="notice notice-error"><p>' . esc_html((string) $apply_result['error']) . '</p></div>';
				}
			}
		}
	}

	$vars = function_exists('lf_pci_template_vars') ? lf_pci_template_vars() : [];
	$download_url = wp_nonce_url(
		admin_url('admin.php?page=lf-import-page-content&lf_pci_download=about-us-template'),
		'lf_pci_download_template'
	);
	$template_sample = function_exists('lf_pci_about_us_template') ? lf_pci_about_us_template() : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Import Page Content', 'leadsforward-core'); ?></h1>
		<p><?php esc_html_e('Paste page copy for the About Us template. Process steps and FAQs are optional in the doc — leave those sections blank and they pull from Niche Content Library automatically.', 'leadsforward-core'); ?></p>

		<div style="margin:1rem 0;padding:1rem;background:#fff;border:1px solid #c3c4c7;border-radius:4px;max-width:900px;">
			<h2 style="margin-top:0;"><?php esc_html_e('About Us — one clean workflow', 'leadsforward-core'); ?></h2>
			<ol>
				<li><?php esc_html_e('Edit shared process steps + FAQs once per niche in LeadsForward → Niche Content Library.', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Writers paste hero, story, benefits, team, CTA, and SEO here (Google Doc template).', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('PROCESS and FAQ sections only need headings/intros — steps and Q&A come from the library unless you paste overrides.', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Preview parse → Apply to populate the About page and wire CPTs.', 'leadsforward-core'); ?></li>
			</ol>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url($download_url); ?>"><?php esc_html_e('Download About Us template (.txt)', 'leadsforward-core'); ?></a>
				<?php if ($page_id > 0) : ?>
					<a class="button" href="<?php echo esc_url((string) get_permalink($page_id)); ?>" target="_blank" rel="noopener"><?php esc_html_e('View current About page', 'leadsforward-core'); ?></a>
				<?php else : ?>
					<span class="description" style="margin-left:8px;"><?php esc_html_e('No about-us page found yet.', 'leadsforward-core'); ?></span>
				<?php endif; ?>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: 1: business name, 2: city */
					esc_html__('Token preview: {business} = %1$s, {city} = %2$s', 'leadsforward-core'),
					esc_html((string) ($vars['business'] ?? '')),
					esc_html((string) ($vars['city'] ?? ''))
				);
				?>
			</p>
		</div>

		<form method="post">
			<?php wp_nonce_field('lf_pci_import', 'lf_pci_nonce'); ?>
			<p>
				<label for="lf-pci-content"><strong><?php esc_html_e('Paste content', 'leadsforward-core'); ?></strong></label>
			</p>
			<textarea id="lf-pci-content" name="lf_pci_content" rows="22" class="large-text code" style="font-family:monospace;max-width:900px;"><?php echo esc_textarea($raw !== '' ? $raw : ''); ?></textarea>

			<p class="submit" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
				<button type="submit" name="lf_pci_action" value="preview" class="button button-secondary"><?php esc_html_e('Preview parse', 'leadsforward-core'); ?></button>
				<button type="submit" name="lf_pci_action" value="apply" class="button button-primary" <?php disabled($page_id <= 0); ?> onclick="return confirm('<?php echo esc_js(__('Replace the About Us page template content with this import?', 'leadsforward-core')); ?>');"><?php esc_html_e('Apply to About Us page', 'leadsforward-core'); ?></button>
			</p>
		</form>

		<?php
		if (is_array($parsed)) {
			foreach ((array) ($parsed['errors'] ?? []) as $err) {
				echo '<div class="notice notice-error"><p>' . esc_html((string) $err) . '</p></div>';
			}
			foreach ((array) ($parsed['warnings'] ?? []) as $warn) {
				echo '<div class="notice notice-warning"><p>' . esc_html((string) $warn) . '</p></div>';
			}
			if ($action === 'preview' || $action === 'apply') {
				lf_pci_admin_render_preview($parsed);
			}
		}
		?>

		<details style="margin-top:2rem;max-width:900px;">
			<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Show blank template (copy starting point)', 'leadsforward-core'); ?></summary>
			<textarea readonly rows="18" class="large-text code" style="font-family:monospace;margin-top:8px;"><?php echo esc_textarea($template_sample); ?></textarea>
		</details>
	</div>
	<?php
}
