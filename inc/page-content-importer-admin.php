<?php
/**
 * Admin UI: paste / upload formatted docs → populate page templates.
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
	$which = isset($_GET['lf_pci_download']) ? sanitize_key((string) $_GET['lf_pci_download']) : '';
	$slug = isset($_GET['lf_pci_template_slug']) ? sanitize_title((string) $_GET['lf_pci_template_slug']) : '';
	$format = isset($_GET['lf_pci_format']) ? sanitize_key((string) $_GET['lf_pci_format']) : 'txt';
	if ($which === '' && $slug === '') {
		return;
	}
	if (!current_user_can(defined('LF_OPS_CAP') ? LF_OPS_CAP : 'manage_options')) {
		wp_die(esc_html__('You do not have permission to download this template.', 'leadsforward-core'));
	}
	check_admin_referer('lf_pci_download_template');

	$body = '';
	$filename = 'page-content-template.txt';
	if ($slug !== '' && function_exists('lf_pci_template_for_slug')) {
		$body = lf_pci_template_for_slug($slug);
		$filename = $slug . '-content-template.' . ($format === 'docx' ? 'docx' : 'txt');
	} elseif ($which === 'about-us-template') {
		$body = function_exists('lf_pci_template_for_slug') ? lf_pci_template_for_slug('about-us') : '';
		$filename = 'about-us-content-template.' . ($format === 'docx' ? 'docx' : 'txt');
	} elseif ($which === 'page-template' || $which === 'home-template') {
		$body = function_exists('lf_pci_universal_template') ? lf_pci_universal_template() : '';
		$filename = 'page-content-template.' . ($format === 'docx' ? 'docx' : 'txt');
	} else {
		return;
	}

	if ($format === 'docx' && function_exists('lf_pci_build_docx_bytes')) {
		$bytes = lf_pci_build_docx_bytes($body);
		if ($bytes === '') {
			wp_die(esc_html__('Could not build .docx on this server (ZipArchive required).', 'leadsforward-core'));
		}
		header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . (string) strlen($bytes));
		echo $bytes;
		exit;
	}

	header('Content-Type: text/plain; charset=utf-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
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
	$page_slug = (string) ($parsed['page_slug'] ?? '');
	$page_label = (string) ($parsed['page_label'] ?? $page_slug);
	?>
	<div class="lf-pci-preview" style="margin:1.5rem 0;padding:1rem;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;">
		<h2><?php esc_html_e('Parse preview', 'leadsforward-core'); ?></h2>
		<?php if ($page_slug !== '') : ?>
			<p><strong><?php esc_html_e('Target page:', 'leadsforward-core'); ?></strong> <?php echo esc_html($page_label . ' (' . $page_slug . ')'); ?></p>
		<?php endif; ?>
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
				$schema = function_exists('lf_pci_schema_for_slug') ? lf_pci_schema_for_slug($page_slug) : null;
				$order = is_array($schema['order'] ?? null) ? $schema['order'] : [];
				foreach ($order as $type) :
					$settings = is_array($sections[$type] ?? null) ? $sections[$type] : [];
					$preview = '';
					if ($type === 'hero') {
						$preview = (string) ($settings['hero_headline'] ?? '');
					} elseif ($type === 'cta') {
						$preview = (string) ($settings['cta_headline'] ?? '');
					} elseif ($type === 'trust_bar') {
						$preview = (string) ($settings['trust_heading'] ?? '');
					} else {
						$preview = (string) ($settings['section_heading'] ?? '');
					}
					$preview = function_exists('lf_pci_fill_tokens') ? lf_pci_fill_tokens($preview) : $preview;
					$locked = is_array($schema) && function_exists('lf_pci_section_is_locked') && lf_pci_section_is_locked($type, $schema);
					?>
					<tr>
						<td><code><?php echo esc_html($type); ?></code></td>
						<td><?php echo $locked ? esc_html__('locked', 'leadsforward-core') : ($settings !== [] ? '✓' : '—'); ?></td>
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

/**
 * @return list<array{filename: string, parsed: array<string, mixed>}>
 */
function lf_pci_admin_read_uploaded_files(): array {
	if (empty($_FILES['lf_pci_files']) || !is_array($_FILES['lf_pci_files'])) {
		return [];
	}
	$files = $_FILES['lf_pci_files'];
	$names = is_array($files['name'] ?? null) ? $files['name'] : [];
	$tmp = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [];
	$errors = is_array($files['error'] ?? null) ? $files['error'] : [];
	$out = [];
	foreach ($names as $i => $name) {
		if ((int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			continue;
		}
		$path = (string) ($tmp[$i] ?? '');
		if ($path === '' || !is_readable($path)) {
			continue;
		}
		$raw = function_exists('lf_pci_read_upload_file_contents')
			? lf_pci_read_upload_file_contents($path, (string) $name)
			: (string) file_get_contents($path);
		if ($raw === '') {
			continue;
		}
		$parsed = lf_pci_parse_document($raw);
		$out[] = [
			'filename' => sanitize_file_name((string) $name),
			'parsed' => $parsed,
		];
	}
	return $out;
}

function lf_pci_admin_render_apply_notice(array $apply_result, int $page_id): void {
	if (!empty($apply_result['success'])) {
		$edit_url = get_edit_post_link($page_id, 'raw');
		$view_url = get_permalink($page_id);
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html__('Page populated successfully.', 'leadsforward-core') . ' ';
		if (is_string($view_url) && $view_url !== '') {
			echo '<a href="' . esc_url($view_url) . '" target="_blank" rel="noopener">' . esc_html__('View page', 'leadsforward-core') . '</a>';
		}
		if (is_string($edit_url) && $edit_url !== '') {
			echo ' · <a href="' . esc_url($edit_url) . '">' . esc_html__('Edit page', 'leadsforward-core') . '</a>';
		}
		echo '</p></div>';
	} elseif (!empty($apply_result['error'])) {
		echo '<div class="notice notice-error"><p>' . esc_html((string) $apply_result['error']) . '</p></div>';
	}
}

function lf_pci_admin_render(): void {
	if (!current_user_can(defined('LF_OPS_CAP') ? LF_OPS_CAP : 'manage_options')) {
		wp_die(esc_html__('You do not have permission to access this page.', 'leadsforward-core'));
	}

	$raw = '';
	$parsed = null;
	$batch = [];
	$action = '';
	$target_slug = '';

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lf_pci_nonce'])) {
		check_admin_referer('lf_pci_import', 'lf_pci_nonce');
		$raw = (string) wp_unslash($_POST['lf_pci_content'] ?? '');
		$action = sanitize_key((string) ($_POST['lf_pci_action'] ?? 'preview'));
		$target_slug = sanitize_title((string) wp_unslash($_POST['lf_pci_target_slug'] ?? ''));

		if (!empty($_FILES['lf_pci_files']['name'][0] ?? '')) {
			$batch = lf_pci_admin_read_uploaded_files();
		} elseif ($raw !== '' && function_exists('lf_pci_parse_document')) {
			$parsed = lf_pci_parse_document($raw, $target_slug !== '' ? $target_slug : null);
		}

		if ($action === 'apply' && $batch !== []) {
			foreach ($batch as $item) {
				$p = (array) ($item['parsed'] ?? []);
				if (!empty($p['errors'])) {
					echo '<div class="notice notice-error"><p><strong>' . esc_html((string) ($item['filename'] ?? 'file')) . ':</strong> ' . esc_html((string) $p['errors'][0]) . '</p></div>';
					continue;
				}
				$result = lf_pci_apply_parsed($p, ['sync_mode' => 'force']);
				echo '<div class="notice ' . (!empty($result['success']) ? 'notice-success' : 'notice-error') . '"><p><strong>' . esc_html((string) ($item['filename'] ?? 'file')) . ' → ' . esc_html((string) ($p['page_slug'] ?? '')) . ':</strong> ';
				echo esc_html(!empty($result['success']) ? __('Imported', 'leadsforward-core') : (string) ($result['error'] ?? __('Failed', 'leadsforward-core')));
				echo '</p></div>';
			}
		} elseif ($action === 'apply' && is_array($parsed) && empty($parsed['errors'])) {
			$result = lf_pci_apply_parsed($parsed, ['sync_mode' => 'force']);
			$page_id = (int) ($result['page_id'] ?? lf_pci_get_page_id_for_slug((string) ($parsed['page_slug'] ?? '')));
			lf_pci_admin_render_apply_notice($result, $page_id);
			if (!empty($result['process_ids'])) {
				$src = ($result['process_source'] ?? '') === 'library' ? __('library', 'leadsforward-core') : __('doc', 'leadsforward-core');
				echo '<div class="notice notice-info"><p>' . esc_html(sprintf(__('Process: %1$d steps (%2$s).', 'leadsforward-core'), count((array) $result['process_ids']), $src)) . '</p></div>';
			}
			if (!empty($result['faq_ids'])) {
				$src = ($result['faq_source'] ?? '') === 'library' ? __('library', 'leadsforward-core') : __('doc', 'leadsforward-core');
				echo '<div class="notice notice-info"><p>' . esc_html(sprintf(__('FAQs: %1$d items (%2$s).', 'leadsforward-core'), count((array) $result['faq_ids']), $src)) . '</p></div>';
			}
		}
	}

	$vars = function_exists('lf_pci_template_vars') ? lf_pci_template_vars() : [];
	$download_url = wp_nonce_url(
		admin_url('admin.php?page=lf-import-page-content&lf_pci_download=page-template'),
		'lf_pci_download_template'
	);
	$template_sample = function_exists('lf_pci_universal_template') ? lf_pci_universal_template() : '';
	$registry = function_exists('lf_pci_registry') ? lf_pci_registry() : [];
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Import Page Content', 'leadsforward-core'); ?></h1>
		<p><?php esc_html_e('Paste or upload Google Docs exports (.docx) or plain text. Each file needs a === PAGE === block with Slug:. Process/FAQ can stay blank — they pull from Niche Content Library.', 'leadsforward-core'); ?></p>

		<div style="margin:1rem 0;padding:1rem;background:#fff;border:1px solid #c3c4c7;border-radius:4px;max-width:960px;">
			<h2 style="margin-top:0;"><?php esc_html_e('Writer workflow', 'leadsforward-core'); ?></h2>
			<ol>
				<li><?php esc_html_e('One doc per page — set Slug: in the === PAGE === header (e.g. about-us).', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Edit shared process + FAQs in Niche Content Library (once per niche).', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Paste on this screen for batch upload, or open any page editor and use the Import page content box.', 'leadsforward-core'); ?></li>
			</ol>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url($download_url); ?>"><?php esc_html_e('Download universal template (.txt)', 'leadsforward-core'); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url(add_query_arg('lf_pci_format', 'docx', $download_url)); ?>"><?php esc_html_e('Download universal template (.docx for Google Drive)', 'leadsforward-core'); ?></a>
			</p>
			<?php if ($registry !== []) : ?>
				<p class="description"><strong><?php esc_html_e('Registered page slugs:', 'leadsforward-core'); ?></strong>
					<?php echo esc_html(implode(', ', array_keys($registry))); ?>
				</p>
				<p class="description"><strong><?php esc_html_e('Download templates:', 'leadsforward-core'); ?></strong>
					<?php
					$links = [];
					foreach ($registry as $reg_slug => $reg_schema) {
						$url = wp_nonce_url(
							admin_url('admin.php?page=lf-import-page-content&lf_pci_template_slug=' . rawurlencode((string) $reg_slug)),
							'lf_pci_download_template'
						);
						$docx_url = add_query_arg('lf_pci_format', 'docx', $url);
						$links[] = '<a href="' . esc_url($url) . '">' . esc_html((string) ($reg_schema['label'] ?? $reg_slug)) . '</a>'
							. ' <a href="' . esc_url($docx_url) . '" class="description">(.docx)</a>';
					}
					echo implode(' · ', $links);
					?>
				</p>
			<?php endif; ?>
			<p class="description">
				<?php
				printf(
					esc_html__('Tokens: {business} = %1$s, {city} = %2$s', 'leadsforward-core'),
					esc_html((string) ($vars['business'] ?? '')),
					esc_html((string) ($vars['city'] ?? ''))
				);
				?>
			</p>
		</div>

		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field('lf_pci_import', 'lf_pci_nonce'); ?>
			<h2><?php esc_html_e('Batch upload', 'leadsforward-core'); ?></h2>
			<p class="description"><?php esc_html_e('Upload one or more .docx (Google Docs export) or .txt files (each with its own === PAGE === header).', 'leadsforward-core'); ?></p>
			<input type="file" name="lf_pci_files[]" accept=".txt,.md,.docx,text/plain,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple />

			<h2 style="margin-top:2rem;"><?php esc_html_e('Or paste a single doc', 'leadsforward-core'); ?></h2>
			<p>
				<label for="lf-pci-target-slug"><?php esc_html_e('Override page slug (optional)', 'leadsforward-core'); ?></label>
				<input type="text" class="regular-text" id="lf-pci-target-slug" name="lf_pci_target_slug" value="<?php echo esc_attr($target_slug); ?>" placeholder="about-us" />
				<span class="description"><?php esc_html_e('Leave empty to use the Slug from the === PAGE === block.', 'leadsforward-core'); ?></span>
			</p>
			<textarea id="lf-pci-content" name="lf_pci_content" rows="18" class="large-text code" style="font-family:monospace;max-width:960px;"><?php echo esc_textarea($raw); ?></textarea>

			<p class="submit" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
				<button type="submit" name="lf_pci_action" value="preview" class="button button-secondary"><?php esc_html_e('Preview parse', 'leadsforward-core'); ?></button>
				<button type="submit" name="lf_pci_action" value="apply" class="button button-primary" onclick="return confirm('<?php echo esc_js(__('Import content to the target page(s)? Existing Page Builder sections on those pages will be replaced.', 'leadsforward-core')); ?>');"><?php esc_html_e('Apply import', 'leadsforward-core'); ?></button>
			</p>
		</form>

		<?php
		if ($batch !== [] && $action === 'preview') {
			foreach ($batch as $item) {
				$p = (array) ($item['parsed'] ?? []);
				echo '<h2>' . esc_html((string) ($item['filename'] ?? 'file')) . '</h2>';
				foreach ((array) ($p['errors'] ?? []) as $err) {
					echo '<div class="notice notice-error"><p>' . esc_html((string) $err) . '</p></div>';
				}
				foreach ((array) ($p['warnings'] ?? []) as $warn) {
					echo '<div class="notice notice-warning"><p>' . esc_html((string) $warn) . '</p></div>';
				}
				lf_pci_admin_render_preview($p);
			}
		} elseif (is_array($parsed)) {
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

		<details style="margin-top:2rem;max-width:960px;">
			<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Show blank template', 'leadsforward-core'); ?></summary>
			<textarea readonly rows="20" class="large-text code" style="font-family:monospace;margin-top:8px;"><?php echo esc_textarea($template_sample); ?></textarea>
		</details>
	</div>
	<?php
}
