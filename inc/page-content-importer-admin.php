<?php
/**
 * Admin UI: AI-assisted .docx import → populate page templates.
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

function lf_pci_admin_template_download_url(string $template_key, int $post_id = 0): string {
	$args = [
		'page' => 'lf-import-page-content',
		'lf_pci_template_slug' => $template_key,
	];
	if ($post_id > 0) {
		$args['lf_pci_post_id'] = $post_id;
	}
	return wp_nonce_url(admin_url(add_query_arg($args, 'admin.php')), 'lf_pci_download_template');
}

function lf_pci_admin_template_download_all_url(): string {
	return wp_nonce_url(
		admin_url('admin.php?page=lf-import-page-content&lf_pci_download_all=1'),
		'lf_pci_download_template'
	);
}

function lf_pci_admin_upload_error_message(int $code): string {
	return match ($code) {
		UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('File exceeds server upload size limit.', 'leadsforward-core'),
		UPLOAD_ERR_PARTIAL => __('File was only partially uploaded — try again.', 'leadsforward-core'),
		UPLOAD_ERR_NO_FILE => __('No file received.', 'leadsforward-core'),
		UPLOAD_ERR_NO_TMP_DIR => __('Server temp folder missing.', 'leadsforward-core'),
		UPLOAD_ERR_CANT_WRITE => __('Server could not write uploaded file.', 'leadsforward-core'),
		UPLOAD_ERR_EXTENSION => __('Upload blocked by server extension.', 'leadsforward-core'),
		default => __('Upload failed.', 'leadsforward-core'),
	};
}

function lf_pci_admin_handle_download(): void {
	if (!isset($_GET['page']) || $_GET['page'] !== 'lf-import-page-content') {
		return;
	}
	$download_all = isset($_GET['lf_pci_download_all']) && (string) wp_unslash((string) $_GET['lf_pci_download_all']) === '1';
	$slug = isset($_GET['lf_pci_template_slug']) ? sanitize_title((string) $_GET['lf_pci_template_slug']) : '';
	if (!$download_all && $slug === '') {
		return;
	}
	if (!current_user_can(defined('LF_OPS_CAP') ? LF_OPS_CAP : 'manage_options')) {
		wp_die(esc_html__('You do not have permission to download this template.', 'leadsforward-core'));
	}
	check_admin_referer('lf_pci_download_template');

	if (!function_exists('lf_pci_template_for_slug') || !function_exists('lf_pci_build_docx_bytes')) {
		wp_die(esc_html__('Template builder is not available.', 'leadsforward-core'));
	}

	if ($download_all) {
		if (!function_exists('lf_pci_build_templates_zip_bytes')) {
			wp_die(esc_html__('Bulk template download is not available.', 'leadsforward-core'));
		}
		$bytes = lf_pci_build_templates_zip_bytes();
		if ($bytes === '') {
			wp_die(esc_html__('Could not build template ZIP on this server (ZipArchive required).', 'leadsforward-core'));
		}
		$vars = function_exists('lf_pci_template_vars') ? lf_pci_template_vars() : [];
		$business = sanitize_file_name((string) ($vars['business'] ?? 'site'));
		if ($business === '') {
			$business = 'site';
		}
		$filename = $business . '-writer-templates.zip';
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . (string) strlen($bytes));
		echo $bytes;
		exit;
	}

	$post_id = isset($_GET['lf_pci_post_id']) ? (int) $_GET['lf_pci_post_id'] : 0;
	$body = lf_pci_template_for_slug($slug, true, $post_id > 0 ? $post_id : null);
	if ($body === '') {
		wp_die(esc_html__('Unknown template.', 'leadsforward-core'));
	}

	$filename = $slug . '-content-template.docx';
	if ($post_id > 0) {
		$post = get_post($post_id);
		if ($post instanceof \WP_Post && $post->post_name !== '') {
			$filename = sanitize_file_name($post->post_name . '-content-template.docx');
		}
	}
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
add_action('admin_init', 'lf_pci_admin_handle_download');

/**
 * @param array<string, mixed> $parsed
 */
function lf_pci_admin_render_preview(array $parsed): void {
	$sections = is_array($parsed['sections'] ?? null) ? $parsed['sections'] : [];
	$process = is_array($parsed['process_steps'] ?? null) ? $parsed['process_steps'] : [];
	$faqs = is_array($parsed['faqs'] ?? null) ? $parsed['faqs'] : [];
	$seo = is_array($parsed['seo'] ?? null) ? $parsed['seo'] : [];
	$template_key = (string) ($parsed['template_key'] ?? $parsed['page_slug'] ?? '');
	$page_slug = (string) ($parsed['page_slug'] ?? '');
	$page_label = (string) ($parsed['page_label'] ?? $page_slug);
	$post_type = (string) ($parsed['post_type'] ?? 'page');
	?>
	<div class="lf-pci-preview" style="margin:1.5rem 0;padding:1rem;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;">
		<h2><?php esc_html_e('Parse preview', 'leadsforward-core'); ?></h2>
		<?php if ($template_key !== '') : ?>
			<p><strong><?php esc_html_e('Template:', 'leadsforward-core'); ?></strong> <?php echo esc_html($template_key); ?></p>
		<?php endif; ?>
		<?php if ($page_slug !== '') : ?>
			<p><strong><?php esc_html_e('Target slug:', 'leadsforward-core'); ?></strong> <?php echo esc_html($page_label . ' (' . $page_slug . ')'); ?>
				<span class="description">— <?php echo esc_html($post_type); ?></span></p>
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
				$schema = function_exists('lf_pci_schema_for_slug') ? lf_pci_schema_for_slug($template_key) : null;
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
 * @return array{items: list<array{filename: string, parsed: array<string, mixed>}>, notices: list<string>}
 */
function lf_pci_admin_read_uploaded_files(): array {
	$notices = [];
	$items = [];
	if (empty($_FILES['lf_pci_files']) || !is_array($_FILES['lf_pci_files'])) {
		return ['items' => [], 'notices' => []];
	}
	$files = $_FILES['lf_pci_files'];
	$names = is_array($files['name'] ?? null) ? $files['name'] : [];
	$tmp = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [];
	$errors = is_array($files['error'] ?? null) ? $files['error'] : [];
	$attempted = 0;
	foreach ($names as $i => $name) {
		$filename = sanitize_file_name((string) $name);
		if ($filename === '') {
			continue;
		}
		++$attempted;
		$upload_err = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
		if ($upload_err !== UPLOAD_ERR_OK) {
			$notices[] = sprintf(
				/* translators: 1: filename, 2: error message */
				__('%1$s: %2$s', 'leadsforward-core'),
				$filename,
				lf_pci_admin_upload_error_message($upload_err)
			);
			continue;
		}
		$path = (string) ($tmp[$i] ?? '');
		if ($path === '' || !is_readable($path)) {
			$notices[] = sprintf(
				/* translators: %s: filename */
				__('%s: could not read uploaded file.', 'leadsforward-core'),
				$filename
			);
			continue;
		}
		if (!str_ends_with(strtolower($filename), '.docx')) {
			$notices[] = sprintf(
				/* translators: %s: filename */
				__('%s: skipped (only .docx files are supported — export from Google Docs as Microsoft Word).', 'leadsforward-core'),
				$filename
			);
			continue;
		}
		$raw = function_exists('lf_pci_read_upload_file_contents')
			? lf_pci_read_upload_file_contents($path, $filename)
			: '';
		if ($raw === '') {
			$notices[] = sprintf(
				/* translators: %s: filename */
				__('%s: no readable text found (re-export as .docx from Google Docs or Word).', 'leadsforward-core'),
				$filename
			);
			continue;
		}
		$parsed = lf_pci_parse_document($raw, null, $filename);
		$items[] = [
			'filename' => $filename,
			'parsed' => $parsed,
		];
	}
	if ($attempted > 0 && $items === [] && $notices === []) {
		$notices[] = __('No .docx files could be processed. Export finished docs as Microsoft Word (.docx), not PDF.', 'leadsforward-core');
	}
	$max_uploads = (int) ini_get('max_file_uploads');
	if ($max_uploads > 0 && $attempted >= $max_uploads) {
		$notices[] = sprintf(
			/* translators: %d: PHP max_file_uploads */
			__('Server limit: max %d files per upload. Import in batches if you have more.', 'leadsforward-core'),
			$max_uploads
		);
	}

	return ['items' => $items, 'notices' => $notices];
}

function lf_pci_admin_render_apply_notice(array $apply_result, int $page_id): void {
	if (!empty($apply_result['success'])) {
		$edit_url = get_edit_post_link($page_id, 'raw');
		$view_url = get_permalink($page_id);
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html__('Content imported successfully.', 'leadsforward-core') . ' ';
		if (is_string($view_url) && $view_url !== '') {
			echo '<a href="' . esc_url($view_url) . '" target="_blank" rel="noopener">' . esc_html__('View', 'leadsforward-core') . '</a>';
		}
		if (is_string($edit_url) && $edit_url !== '') {
			echo ' · <a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'leadsforward-core') . '</a>';
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
	$batch_notices = [];
	$action = '';
	$target_slug = '';

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lf_pci_nonce'])) {
		check_admin_referer('lf_pci_import', 'lf_pci_nonce');
		$raw = (string) wp_unslash($_POST['lf_pci_content'] ?? '');
		$action = sanitize_key((string) ($_POST['lf_pci_action'] ?? 'preview'));
		$target_slug = sanitize_title((string) wp_unslash($_POST['lf_pci_target_slug'] ?? ''));

		if (!empty($_FILES['lf_pci_files']['name'][0] ?? '')) {
			$batch_result = lf_pci_admin_read_uploaded_files();
			$batch = (array) ($batch_result['items'] ?? []);
			$batch_notices = (array) ($batch_result['notices'] ?? []);
		} elseif ($raw !== '' && function_exists('lf_pci_parse_document')) {
			$parsed = lf_pci_parse_document($raw, $target_slug !== '' ? $target_slug : null);
		}

		if ($action === 'apply' && $batch !== []) {
			$imported = 0;
			$failed = 0;
			foreach ($batch as $item) {
				$p = (array) ($item['parsed'] ?? []);
				if (!empty($p['errors'])) {
					++$failed;
					echo '<div class="notice notice-error"><p><strong>' . esc_html((string) ($item['filename'] ?? 'file')) . ':</strong> ' . esc_html((string) $p['errors'][0]) . '</p></div>';
					continue;
				}
				$result = lf_pci_apply_parsed($p, ['sync_mode' => 'force']);
				$target = (string) ($p['page_slug'] ?? $p['template_key'] ?? '');
				if (!empty($result['success'])) {
					++$imported;
				} else {
					++$failed;
				}
				echo '<div class="notice ' . (!empty($result['success']) ? 'notice-success' : 'notice-error') . '"><p><strong>' . esc_html((string) ($item['filename'] ?? 'file')) . ' → ' . esc_html($target) . ':</strong> ';
				echo esc_html(!empty($result['success']) ? __('Imported', 'leadsforward-core') : (string) ($result['error'] ?? __('Failed', 'leadsforward-core')));
				echo '</p></div>';
			}
			$summary_class = $failed === 0 ? 'notice-success' : ($imported > 0 ? 'notice-warning' : 'notice-error');
			echo '<div class="notice ' . esc_attr($summary_class) . ' is-dismissible"><p><strong>' . esc_html__('Batch import complete', 'leadsforward-core') . ':</strong> ';
			echo esc_html(sprintf(
				/* translators: 1: imported count, 2: failed count, 3: total count */
				__('%1$d imported, %2$d failed (%3$d files).', 'leadsforward-core'),
				$imported,
				$failed,
				count($batch)
			));
			echo '</p></div>';
		} elseif ($action === 'apply' && is_array($parsed) && empty($parsed['errors'])) {
			$result = lf_pci_apply_parsed($parsed, ['sync_mode' => 'force']);
			$page_id = (int) ($result['page_id'] ?? 0);
			if ($page_id <= 0) {
				$post_type = (string) ($parsed['post_type'] ?? 'page');
				$slug = (string) ($parsed['page_slug'] ?? '');
				$page_id = function_exists('lf_pci_get_post_id_for_target')
					? lf_pci_get_post_id_for_target($post_type, $slug)
					: lf_pci_get_page_id_for_slug($slug);
			}
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
	$groups = function_exists('lf_pci_writer_template_groups') ? lf_pci_writer_template_groups() : [];
	$template_jobs = function_exists('lf_pci_collect_downloadable_template_jobs') ? lf_pci_collect_downloadable_template_jobs() : [];
	$max_file_uploads = (int) ini_get('max_file_uploads');
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Import Page Content', 'leadsforward-core'); ?></h1>
		<p><?php esc_html_e('Download a keyword-aware .docx template, use AI to fill it, then upload the finished file here. Each doc must include a === PAGE === header so the importer knows which page or service post to update.', 'leadsforward-core'); ?></p>

		<div style="margin:1rem 0;padding:1.25rem;background:#fff;border:1px solid #c3c4c7;border-radius:4px;max-width:960px;">
			<h2 style="margin-top:0;"><?php esc_html_e('Writer workflow', 'leadsforward-core'); ?></h2>
			<ol style="margin-left:1.25rem;">
				<li><?php esc_html_e('Assign keywords in SEO & Performance → Keywords (or per-post SEO box / Airtable Sitemap Sync).', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Download templates (.docx) — use Download all for the full site pack, or grab individual pages below.', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Paste the WRITER NOTES block into your AI, then ask it to fill every section using the exact === SECTION === headers.', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Upload one or more finished .docx files below (batch) — or paste a single doc for preview.', 'leadsforward-core'); ?></li>
			</ol>

			<h3 style="margin:1.25rem 0 0.5rem;font-size:14px;"><?php esc_html_e('Google Docs workflow', 'leadsforward-core'); ?></h3>
			<ol style="margin:0 0 1rem 1.25rem;">
				<li><?php esc_html_e('Download all templates (ZIP) or individual .docx files.', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Upload each .docx to Google Drive → Open with Google Docs (or drag into a shared writer folder).', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Writers edit in Google Docs — do not remove or restyle the === PAGE === and === SECTION === lines.', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('When done: File → Download → Microsoft Word (.docx). Do not use PDF.', 'leadsforward-core'); ?></li>
				<li><?php esc_html_e('Select all finished .docx files and Apply import in one batch below.', 'leadsforward-core'); ?></li>
			</ol>

			<p style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 1rem;">
				<a class="button button-primary" href="<?php echo esc_url(lf_pci_admin_template_download_all_url()); ?>">
					<?php
					printf(
						/* translators: %d: template count */
						esc_html__('Download all templates (%d)', 'leadsforward-core'),
						count($template_jobs)
					);
					?>
				</a>
				<span class="description"><?php esc_html_e('ZIP includes site pages, every service, and every service area — with keyword targets pre-filled.', 'leadsforward-core'); ?></span>
			</p>

			<p class="description" style="margin-bottom:1rem;">
				<?php esc_html_e('Privacy Policy, Terms, Sitemap, and Blog are theme-controlled — no writer templates. Process + FAQ sections can stay blank; they pull from Niche Content Library on import.', 'leadsforward-core'); ?>
			</p>

			<?php foreach ($groups as $group) : ?>
				<h3 style="margin:1.25rem 0 0.5rem;font-size:14px;"><?php echo esc_html((string) ($group['label'] ?? '')); ?></h3>
				<p style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 0.5rem;">
					<?php foreach ((array) ($group['items'] ?? []) as $item) : ?>
						<a class="button button-secondary" href="<?php echo esc_url(lf_pci_admin_template_download_url((string) ($item['key'] ?? ''))); ?>">
							<?php
							echo esc_html(sprintf(
								/* translators: %s: template label */
								__('%s (.docx)', 'leadsforward-core'),
								(string) ($item['label'] ?? '')
							));
							?>
						</a>
					<?php endforeach; ?>
				</p>
			<?php endforeach; ?>

			<?php
			$service_posts = get_posts([
				'post_type' => 'lf_service',
				'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
				'posts_per_page' => 200,
				'orderby' => 'title',
				'order' => 'ASC',
				'no_found_rows' => true,
			]);
			if ($service_posts !== []) :
				?>
				<h3 style="margin:1.25rem 0 0.5rem;font-size:14px;"><?php esc_html_e('Per-service templates (keyword-aware)', 'leadsforward-core'); ?></h3>
				<p class="description" style="margin:0 0 0.5rem;"><?php esc_html_e('Each download pre-fills slug, page name, and keyword targets from SEO assignments.', 'leadsforward-core'); ?></p>
				<p style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 0.5rem;">
					<?php foreach ($service_posts as $service_post) : ?>
						<a class="button button-secondary" href="<?php echo esc_url(lf_pci_admin_template_download_url('service', (int) $service_post->ID)); ?>">
							<?php echo esc_html((string) $service_post->post_title); ?>
						</a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<?php
			$area_posts = get_posts([
				'post_type' => 'lf_service_area',
				'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
				'posts_per_page' => 200,
				'orderby' => 'title',
				'order' => 'ASC',
				'no_found_rows' => true,
			]);
			if ($area_posts !== []) :
				?>
				<h3 style="margin:1.25rem 0 0.5rem;font-size:14px;"><?php esc_html_e('Per-service-area templates (keyword-aware)', 'leadsforward-core'); ?></h3>
				<p class="description" style="margin:0 0 0.5rem;"><?php esc_html_e('Each download pre-fills slug, area name, and keyword targets from SEO assignments.', 'leadsforward-core'); ?></p>
				<p style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 0.5rem;">
					<?php foreach ($area_posts as $area_post) : ?>
						<a class="button button-secondary" href="<?php echo esc_url(lf_pci_admin_template_download_url('service-area', (int) $area_post->ID)); ?>">
							<?php echo esc_html((string) $area_post->post_title); ?>
						</a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<p class="description" style="margin-top:1rem;">
				<?php
				printf(
					esc_html__('Tokens filled on download/import: {business} = %1$s, {city} = %2$s, {primary_keyword} from SEO assignments', 'leadsforward-core'),
					esc_html((string) ($vars['business'] ?? '')),
					esc_html((string) ($vars['city'] ?? ''))
				);
				?>
			</p>
		</div>

		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field('lf_pci_import', 'lf_pci_nonce'); ?>
			<h2><?php esc_html_e('Upload finished .docx files', 'leadsforward-core'); ?></h2>
			<p class="description">
				<?php esc_html_e('Each file needs its own === PAGE === block (Slug: for site pages, or Template: service + Slug: for service posts). If GPT removed PAGE, name files like about-us-filled.docx or basement-waterproofing-filled.docx so the importer can infer the target. Select multiple files for a bulk import.', 'leadsforward-core'); ?>
				<?php if ($max_file_uploads > 0) : ?>
					<?php
					echo ' ';
					printf(
						/* translators: %d: PHP max_file_uploads */
						esc_html__('Server allows up to %d files per upload.', 'leadsforward-core'),
						$max_file_uploads
					);
					?>
				<?php endif; ?>
			</p>
			<input type="file" name="lf_pci_files[]" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" multiple />

			<h2 style="margin-top:2rem;"><?php esc_html_e('Or paste a single doc', 'leadsforward-core'); ?></h2>
			<p>
				<label for="lf-pci-target-slug"><?php esc_html_e('Override template key (optional)', 'leadsforward-core'); ?></label>
				<input type="text" class="regular-text" id="lf-pci-target-slug" name="lf_pci_target_slug" value="<?php echo esc_attr($target_slug); ?>" placeholder="about-us or service" />
				<span class="description"><?php esc_html_e('Leave empty to use Template:/Slug: from the === PAGE === block.', 'leadsforward-core'); ?></span>
			</p>
			<textarea id="lf-pci-content" name="lf_pci_content" rows="18" class="large-text code" style="font-family:monospace;max-width:960px;"><?php echo esc_textarea($raw); ?></textarea>

			<p class="submit" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
				<button type="submit" name="lf_pci_action" value="preview" class="button button-secondary"><?php esc_html_e('Preview parse', 'leadsforward-core'); ?></button>
				<button type="submit" name="lf_pci_action" value="apply" class="button button-primary" onclick="return confirm('<?php echo esc_js(__('Import content to the target page(s)? Existing Page Builder sections will be replaced.', 'leadsforward-core')); ?>');"><?php esc_html_e('Apply import', 'leadsforward-core'); ?></button>
			</p>
		</form>

		<?php
		foreach ($batch_notices as $notice) {
			echo '<div class="notice notice-warning"><p>' . esc_html((string) $notice) . '</p></div>';
		}
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
	</div>
	<?php
}
