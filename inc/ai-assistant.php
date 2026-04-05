<?php
/**
 * LeadsForward AI Assistant floating admin widget.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_enqueue_scripts', 'lf_ai_assistant_assets');
add_action('wp_enqueue_scripts', 'lf_ai_assistant_assets');
add_action('admin_footer', 'lf_ai_assistant_render_floating_widget');
add_action('wp_footer', 'lf_ai_assistant_render_floating_widget');
add_action('wp_footer', 'lf_ai_inline_overrides_frontend_script', 5);
add_filter('lf_keep_jquery', 'lf_ai_assistant_keep_jquery_for_frontend_admins');

function lf_ai_assistant_keep_jquery_for_frontend_admins($keep): bool {
	if (is_admin()) {
		return (bool) $keep;
	}
	if (current_user_can('edit_theme_options')) {
		return true;
	}
	return (bool) $keep;
}

function lf_ai_assistant_section_library(array $context): array {
	if (!function_exists('lf_sections_get_context_sections')) {
		return [];
	}
	$type = (string) ($context['type'] ?? '');
	$id = (string) ($context['id'] ?? '');
	$context_key = '';
	if ($type === 'homepage' || $id === 'homepage') {
		$context_key = 'homepage';
	} elseif ($type === 'page') {
		$context_key = 'page';
	} elseif ($type === 'post') {
		$context_key = 'post';
	} elseif ($type === 'lf_service') {
		$context_key = 'service';
	} elseif ($type === 'lf_service_area') {
		$context_key = 'service_area';
	}
	if ($context_key === '' && is_numeric($id) && function_exists('lf_ai_pb_context_for_post')) {
		$post = get_post((int) $id);
		if ($post instanceof \WP_Post) {
			$context_key = (string) lf_ai_pb_context_for_post($post);
		}
	}
	if ($context_key === '') {
		return [];
	}
	$sections = lf_sections_get_context_sections($context_key);
	if (!is_array($sections) || empty($sections)) {
		return [];
	}
	$rows = [];
	foreach ($sections as $section_id => $row) {
		$sid = sanitize_text_field((string) $section_id);
		if ($sid === '') {
			continue;
		}
		$label = sanitize_text_field((string) ($row['label'] ?? $sid));
		$rows[] = ['id' => $sid, 'label' => $label];
	}
	usort($rows, static function (array $a, array $b): int {
		return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
	});
	return $rows;
}

function lf_ai_assistant_assets(string $hook = ''): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}

	wp_register_script('lf-ai-floating-assistant', '', ['jquery'], LF_THEME_VERSION, true);
	wp_enqueue_script('lf-ai-floating-assistant');
	if (function_exists('wp_enqueue_media')) {
		wp_enqueue_media();
	}

	$context = lf_ai_assistant_widget_context();
	$target_label = __('Homepage', 'leadsforward-core');
	if (($context['id'] ?? '') !== 'homepage') {
		$target_post = get_post((int) ($context['id'] ?? 0));
		if ($target_post instanceof \WP_Post) {
			$target_label = sprintf('%s (%s)', $target_post->post_title, strtoupper((string) $target_post->post_type));
		}
	}
	$editable = function_exists('lf_get_ai_editable_fields') ? lf_get_ai_editable_fields($context['id']) : [];
	if (empty($editable) && function_exists('lf_get_ai_editable_fields')) {
		$editable = lf_get_ai_editable_fields('homepage');
	}
	$homepage_enabled = [];
	if ((string) ($context['type'] ?? '') === 'homepage' || (string) ($context['id'] ?? '') === 'homepage') {
		if (function_exists('lf_get_homepage_section_config')) {
			$hp = lf_get_homepage_section_config();
			if (is_array($hp)) {
				foreach ($hp as $sid => $row) {
					if (!is_string($sid) || !is_array($row)) {
						continue;
					}
					$homepage_enabled[$sid] = !empty($row['enabled']);
				}
			}
		}
	}

	wp_localize_script('lf-ai-floating-assistant', 'lfAiFloating', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('lf_ai_editing'),
		'context_type' => (string) ($context['type'] ?? 'homepage'),
		'context_id' => (string) ($context['id'] ?? 'homepage'),
		'target_label' => $target_label,
		'labels' => $editable,
		'section_library' => lf_ai_assistant_section_library($context),
		'icon_slugs' => function_exists('lf_icon_list') ? array_values(array_map('sanitize_text_field', lf_icon_list())) : [],
		'homepage_enabled' => $homepage_enabled,
		'i18n' => [
			'statusReady' => __('Ready.', 'leadsforward-core'),
			'statusGenerating' => __('Generating suggestions...', 'leadsforward-core'),
			'statusApplying' => __('Applying changes...', 'leadsforward-core'),
			'statusReverting' => __('Reverting last AI change...', 'leadsforward-core'),
			'statusRedoing' => __('Re-applying last reverted change...', 'leadsforward-core'),
			'statusReordering' => __('Saving section order...', 'leadsforward-core'),
			'statusParsingDoc' => __('Reading document context...', 'leadsforward-core'),
			'confirmRevert' => __('Revert the most recent AI change on this page? This cannot be undone.', 'leadsforward-core'),
			'placeholder' => __('Ask for precise copy edits, SEO rewrites, CTA improvements, or schema-safe content upgrades...', 'leadsforward-core'),
		],
	]);

	wp_add_inline_script('lf-ai-floating-assistant', lf_ai_assistant_widget_js());
	wp_add_inline_script('lf-ai-floating-assistant', lf_ai_assistant_widget_fallback_js());
	wp_register_style('lf-ai-floating-assistant', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-ai-floating-assistant');
	wp_add_inline_style('lf-ai-floating-assistant', lf_ai_assistant_widget_css());
}

function lf_ai_assistant_widget_context(): array {
	if (!is_admin()) {
		if (is_front_page()) {
			return ['type' => 'homepage', 'id' => 'homepage'];
		}
		$queried_id = get_queried_object_id();
		if ($queried_id > 0) {
			$post = get_post($queried_id);
			if ($post instanceof \WP_Post) {
				$front_id = (int) get_option('page_on_front');
				if ($post->post_type === 'page' && (int) $post->ID === $front_id) {
					return ['type' => 'homepage', 'id' => 'homepage'];
				}
				return ['type' => (string) $post->post_type, 'id' => (string) $post->ID];
			}
		}
		return ['type' => 'homepage', 'id' => 'homepage'];
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ($screen && $screen->base === 'post') {
		$post_id = 0;
		if (isset($_GET['post'])) {
			$post_id = absint($_GET['post']);
		} elseif (isset($_POST['post_ID'])) {
			$post_id = absint($_POST['post_ID']);
		}
		$post = $post_id > 0 ? get_post($post_id) : null;
		if ($post instanceof \WP_Post) {
			$front_id = (int) get_option('page_on_front');
			if ($post->post_type === 'page' && (int) $post->ID === $front_id) {
				return ['type' => 'homepage', 'id' => 'homepage'];
			}
			return ['type' => $post->post_type, 'id' => (string) $post->ID];
		}
	}
	return ['type' => 'homepage', 'id' => 'homepage'];
}

function lf_ai_inline_overrides_frontend_script(): void {
	if (is_admin()) {
		return;
	}
	if (!function_exists('lf_ai_get_inline_dom_overrides') || !function_exists('lf_ai_get_inline_image_overrides')) {
		return;
	}
	$context = lf_ai_assistant_widget_context();
	$context_type = (string) ($context['type'] ?? 'homepage');
	$context_id = $context['id'] ?? 'homepage';
	$text_overrides = lf_ai_get_inline_dom_overrides($context_type, $context_id);
	$image_overrides = lf_ai_get_inline_image_overrides($context_type, $context_id);
	$text_payload = [];
	foreach ((array) $text_overrides as $selector => $value) {
		$selector_clean = sanitize_text_field((string) $selector);
		$text_clean = sanitize_textarea_field((string) $value);
		if ($selector_clean === '' || $text_clean === '') {
			continue;
		}
		$text_payload[$selector_clean] = $text_clean;
	}
	$image_payload = [];
	foreach ((array) $image_overrides as $selector => $row) {
		$selector_clean = sanitize_text_field((string) $selector);
		if ($selector_clean === '' || !is_array($row)) {
			continue;
		}
		$attachment_id = (int) ($row['attachment_id'] ?? 0);
		$url = esc_url_raw((string) ($row['url'] ?? ''));
		$alt = sanitize_text_field((string) ($row['alt'] ?? ''));
		if ($attachment_id <= 0 || $url === '') {
			continue;
		}
		$image_payload[$selector_clean] = [
			'attachment_id' => $attachment_id,
			'url' => $url,
			'alt' => $alt,
		];
	}
	if (empty($text_payload) && empty($image_payload)) {
		return;
	}

// AJAX handler for trust reviews layout switching
add_action('wp_ajax_lf_ai_set_trust_layout', 'lf_ai_set_trust_layout_handler');
add_action('wp_ajax_nopriv_lf_ai_set_trust_layout', 'lf_ai_set_trust_layout_handler');

function lf_ai_set_trust_layout_handler(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die('Unauthorized');
	}
	
	$nonce = $_POST['nonce'] ?? '';
	if (!wp_verify_nonce($nonce, 'lf_ai_assistant')) {
		wp_die('Invalid nonce');
	}
	
	$context_type = sanitize_text_field($_POST['context_type'] ?? '');
	$context_id = absint($_POST['context_id'] ?? 0);
	$section_id = sanitize_text_field($_POST['section_id'] ?? '');
	$layout = sanitize_text_field($_POST['layout'] ?? '');
	
	if (!in_array($layout, ['grid', 'slider'], true)) {
		wp_die('Invalid layout');
	}
	
	// Update the section config
	if ($context_type === 'homepage' && function_exists('lf_get_homepage_section_config')) {
		$config = lf_get_homepage_section_config();
		if (isset($config['trust_reviews'])) {
			$config['trust_reviews']['trust_layout'] = $layout;
			update_option('lf_homepage_section_config', $config);
			
			wp_send_json_success([
				'message' => "Layout changed to {$layout}",
				'reload' => true
			]);
		}
	}
	
	wp_send_json_error('Could not update layout');
}

	$text_json = wp_json_encode($text_payload);
	$image_json = wp_json_encode($image_payload);
	if (!is_string($text_json) || !is_string($image_json)) {
		return;
	}
	?>
	<script>
	(function(){
		var textMap = <?php echo $text_json; ?>;
		var imageMap = <?php echo $image_json; ?>;
		function applyMaps(){
			Object.keys(textMap || {}).forEach(function(selector){
				try {
					var node = document.querySelector(selector);
					if (node) {
						node.textContent = String(textMap[selector] || "");
					}
				} catch (e) {}
			});
			Object.keys(imageMap || {}).forEach(function(selector){
				try {
					var img = document.querySelector(selector);
					var row = imageMap[selector] || {};
					if (img && img.tagName && img.tagName.toLowerCase() === "img" && row.url) {
						img.setAttribute("src", String(row.url));
						img.removeAttribute("srcset");
						if (row.alt !== undefined) {
							img.setAttribute("alt", String(row.alt || ""));
						}
					}
				} catch (e) {}
			});
		}
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", applyMaps);
		} else {
			applyMaps();
		}
	})();
	</script>
	<?php
}

function lf_ai_assistant_render_floating_widget(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$context = lf_ai_assistant_widget_context();
	$target_label = __('Homepage', 'leadsforward-core');
	if (($context['id'] ?? '') !== 'homepage') {
		$target_post = get_post((int) ($context['id'] ?? 0));
		if ($target_post instanceof \WP_Post) {
			$target_label = sprintf('%s (%s)', $target_post->post_title, strtoupper((string) $target_post->post_type));
		}
	}
	?>
	<div class="lf-ai-float" data-lf-ai-float>
		<button type="button" class="lf-ai-float__toggle" data-lf-ai-toggle aria-expanded="false" aria-controls="lf-ai-float-panel">
			<span class="lf-ai-float__dot" aria-hidden="true"></span>
			<?php esc_html_e('AI Assistant', 'leadsforward-core'); ?>
		</button>
		<div class="lf-ai-float__panel" id="lf-ai-float-panel" hidden>
			<div class="lf-ai-float__header">
				<strong><?php esc_html_e('LeadsForward AI Assistant', 'leadsforward-core'); ?></strong>
				<div class="lf-ai-float__header-actions">
					<button type="button" class="lf-ai-float__icon" data-lf-ai-undo aria-label="<?php esc_attr_e('Undo last change', 'leadsforward-core'); ?>" title="<?php esc_attr_e('Undo last change (click repeatedly to step back)', 'leadsforward-core'); ?>">↶</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-redo aria-label="<?php esc_attr_e('Redo last reverted change', 'leadsforward-core'); ?>" title="<?php esc_attr_e('Redo last reverted change (click repeatedly to step forward)', 'leadsforward-core'); ?>">↷</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-editor-toggle aria-label="<?php esc_attr_e('Toggle editor mode', 'leadsforward-core'); ?>" title="<?php esc_attr_e('Toggle editor mode on/off', 'leadsforward-core'); ?>">Ed</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-minimize aria-label="<?php esc_attr_e('Minimize', 'leadsforward-core'); ?>">−</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-close aria-label="<?php esc_attr_e('Close', 'leadsforward-core'); ?>">×</button>
				</div>
			</div>
			<div class="lf-ai-float__body">
				<div class="lf-ai-float__target" data-lf-ai-target><?php echo esc_html(sprintf(__('Target: %s (editable target)', 'leadsforward-core'), $target_label)); ?></div>
				<div class="lf-ai-float__mode">
					<label>
						<span><?php esc_html_e('Mode', 'leadsforward-core'); ?></span>
						<select data-lf-ai-mode>
							<option value="auto"><?php esc_html_e('Auto (Infer Action)', 'leadsforward-core'); ?></option>
							<option value="edit_existing"><?php esc_html_e('Edit Current Page', 'leadsforward-core'); ?></option>
							<option value="create_page"><?php esc_html_e('Create New Page (Draft)', 'leadsforward-core'); ?></option>
							<option value="create_cpt"><?php esc_html_e('Create New CPT Item (Draft)', 'leadsforward-core'); ?></option>
							<option value="create_blog_post"><?php esc_html_e('Create New Blog Post (Draft)', 'leadsforward-core'); ?></option>
							<option value="create_batch"><?php esc_html_e('Create Batch (Drafts)', 'leadsforward-core'); ?></option>
						</select>
					</label>
					<label data-lf-ai-cpt-wrap hidden>
						<span><?php esc_html_e('CPT Type', 'leadsforward-core'); ?></span>
						<select data-lf-ai-cpt-type>
							<option value="lf_service"><?php esc_html_e('Service', 'leadsforward-core'); ?></option>
							<option value="lf_service_area"><?php esc_html_e('Service Area', 'leadsforward-core'); ?></option>
							<option value="lf_faq"><?php esc_html_e('FAQ', 'leadsforward-core'); ?></option>
							<option value="lf_project"><?php esc_html_e('Project', 'leadsforward-core'); ?></option>
							<option value="lf_testimonial"><?php esc_html_e('Review/Testimonial', 'leadsforward-core'); ?></option>
						</select>
					</label>
					<label data-lf-ai-batch-wrap hidden>
						<span><?php esc_html_e('Batch Type', 'leadsforward-core'); ?></span>
						<select data-lf-ai-batch-type>
							<option value="post"><?php esc_html_e('Blog Posts', 'leadsforward-core'); ?></option>
							<option value="page"><?php esc_html_e('Pages', 'leadsforward-core'); ?></option>
							<option value="lf_service"><?php esc_html_e('Services', 'leadsforward-core'); ?></option>
							<option value="lf_service_area"><?php esc_html_e('Service Areas', 'leadsforward-core'); ?></option>
							<option value="lf_faq"><?php esc_html_e('FAQs', 'leadsforward-core'); ?></option>
							<option value="lf_project"><?php esc_html_e('Projects', 'leadsforward-core'); ?></option>
							<option value="lf_testimonial"><?php esc_html_e('Reviews/Testimonials', 'leadsforward-core'); ?></option>
						</select>
					</label>
					<label data-lf-ai-batch-count-wrap hidden>
						<span><?php esc_html_e('Count', 'leadsforward-core'); ?></span>
						<input type="number" min="1" max="20" value="5" data-lf-ai-batch-count />
					</label>
				</div>
				<div class="lf-ai-float__target-ref">
					<label>
						<span><?php esc_html_e('Reference target page/post (optional)', 'leadsforward-core'); ?></span>
						<input type="text" data-lf-ai-target-ref placeholder="<?php esc_attr_e('e.g. contact page, /about-us, full URL', 'leadsforward-core'); ?>" />
					</label>
				</div>
				<div class="lf-ai-float__presets">
					<button type="button" class="button button-small" data-lf-ai-preset="<?php esc_attr_e('Tighten this page copy for higher conversions and local trust signals.', 'leadsforward-core'); ?>"><?php esc_html_e('Optimize Copy', 'leadsforward-core'); ?></button>
					<button type="button" class="button button-small" data-lf-ai-preset="<?php esc_attr_e('Rewrite metadata and opening copy to better match transactional local intent.', 'leadsforward-core'); ?>"><?php esc_html_e('SERP Intent', 'leadsforward-core'); ?></button>
					<button type="button" class="button button-small" data-lf-ai-preset="<?php esc_attr_e('Improve CTA language for urgency, clarity, and lead quality.', 'leadsforward-core'); ?>"><?php esc_html_e('Improve CTA', 'leadsforward-core'); ?></button>
				</div>
				<textarea class="lf-ai-float__prompt" rows="4" data-lf-ai-prompt placeholder="<?php esc_attr_e('Ask for specific edits...', 'leadsforward-core'); ?>"></textarea>
				<div class="lf-ai-float__doc">
					<input type="file" accept=".txt,.md,.csv,.json,.html,.htm,.rtf,.docx" data-lf-ai-doc-input hidden />
					<button type="button" class="button button-small" data-lf-ai-doc-attach><?php esc_html_e('Attach Document Context', 'leadsforward-core'); ?></button>
					<button type="button" class="button button-small" data-lf-ai-doc-clear hidden><?php esc_html_e('Remove', 'leadsforward-core'); ?></button>
					<span class="lf-ai-float__doc-name" data-lf-ai-doc-name></span>
				</div>
				<div class="lf-ai-float__actions">
					<button type="button" class="button button-primary" data-lf-ai-generate><?php esc_html_e('Generate', 'leadsforward-core'); ?></button>
					<button type="button" class="button" data-lf-ai-apply disabled><?php esc_html_e('Apply', 'leadsforward-core'); ?></button>
					<button type="button" class="button" data-lf-ai-reject disabled><?php esc_html_e('Reject', 'leadsforward-core'); ?></button>
					<button type="button" class="button" data-lf-ai-revert><?php esc_html_e('Undo Last Change', 'leadsforward-core'); ?></button>
				</div>
				<div class="lf-ai-float__status" data-lf-ai-status><?php esc_html_e('Ready.', 'leadsforward-core'); ?></div>
				<div class="lf-ai-float__diff" data-lf-ai-diff hidden></div>
			</div>
			<div class="lf-ai-float__confirm" data-lf-ai-confirm hidden>
				<div class="lf-ai-float__confirm-card">
					<p class="lf-ai-float__confirm-text" data-lf-ai-confirm-text><?php esc_html_e('Revert the most recent AI change on this page? This cannot be undone.', 'leadsforward-core'); ?></p>
					<div class="lf-ai-float__confirm-actions">
						<button type="button" class="button button-primary" data-lf-ai-confirm-yes><?php esc_html_e('Yes, Revert', 'leadsforward-core'); ?></button>
						<button type="button" class="button" data-lf-ai-confirm-no><?php esc_html_e('Cancel', 'leadsforward-core'); ?></button>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="lf-ai-float lf-ai-float--seo" data-lf-ai-seo-float>
		<button type="button" class="lf-ai-float__toggle lf-ai-float__toggle--seo" data-lf-ai-seo-toggle aria-expanded="false" aria-controls="lf-ai-seo-panel">
			<span class="lf-ai-float__dot" aria-hidden="true"></span>
			<?php esc_html_e('SEO Health', 'leadsforward-core'); ?>
		</button>
		<div class="lf-ai-float__panel lf-ai-float__panel--seo" id="lf-ai-seo-panel" hidden>
			<div class="lf-ai-float__header">
				<strong><?php esc_html_e('LeadsForward SEO Report', 'leadsforward-core'); ?></strong>
				<div class="lf-ai-float__header-actions">
					<button type="button" class="lf-ai-float__icon" data-lf-ai-seo-refresh aria-label="<?php esc_attr_e('Refresh SEO report', 'leadsforward-core'); ?>"><?php esc_html_e('↻', 'leadsforward-core'); ?></button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-seo-minimize aria-label="<?php esc_attr_e('Minimize', 'leadsforward-core'); ?>">−</button>
					<button type="button" class="lf-ai-float__icon" data-lf-ai-seo-close aria-label="<?php esc_attr_e('Close', 'leadsforward-core'); ?>">×</button>
				</div>
			</div>
			<div class="lf-ai-float__body">
				<div class="lf-ai-seo" data-lf-ai-seo>
					<div class="lf-ai-seo__score" data-lf-ai-seo-score><?php esc_html_e('Overall: --', 'leadsforward-core'); ?></div>
					<div class="lf-ai-seo__perf-chip lf-ai-seo__perf-chip--pending" data-lf-ai-seo-perf-chip><?php esc_html_e('Perf --', 'leadsforward-core'); ?></div>
					<div class="lf-ai-seo__meter"><span data-lf-ai-seo-overall-fill style="width:0%;"></span></div>
					<div class="lf-ai-seo__subscores">
						<div class="lf-ai-seo__subscore">
							<span><?php esc_html_e('SEO', 'leadsforward-core'); ?></span>
							<b data-lf-ai-seo-sub-seo>--</b>
						</div>
						<div class="lf-ai-seo__subscore">
							<span><?php esc_html_e('Conversion', 'leadsforward-core'); ?></span>
							<b data-lf-ai-seo-sub-conv>--</b>
						</div>
						<div class="lf-ai-seo__subscore">
							<span><?php esc_html_e('Performance', 'leadsforward-core'); ?></span>
							<b data-lf-ai-seo-sub-perf>--</b>
						</div>
					</div>
					<div class="lf-ai-seo__list" data-lf-ai-seo-list></div>
					<div class="lf-ai-seo__queue" data-lf-ai-seo-queue>
						<div class="lf-ai-seo__section-title"><?php esc_html_e('Priority Actions', 'leadsforward-core'); ?></div>
						<div class="lf-ai-seo__queue-list" data-lf-ai-seo-tasks></div>
					</div>
					<div class="lf-ai-seo__serp" data-lf-ai-seo-serp>
						<div class="lf-ai-seo__section-title"><?php esc_html_e('SERP Preview', 'leadsforward-core'); ?></div>
						<div class="lf-ai-seo__serp-url" data-lf-ai-seo-serp-url></div>
						<div class="lf-ai-seo__serp-title" data-lf-ai-seo-serp-title></div>
						<div class="lf-ai-seo__serp-desc" data-lf-ai-seo-serp-desc></div>
					</div>
					<div class="lf-ai-seo__coverage" data-lf-ai-seo-coverage-wrap>
						<div class="lf-ai-seo__section-title"><?php esc_html_e('Keyword Coverage', 'leadsforward-core'); ?></div>
						<div class="lf-ai-seo__coverage-list" data-lf-ai-seo-coverage></div>
					</div>
					<div class="lf-ai-seo__vitals" data-lf-ai-seo-vitals-wrap>
						<div class="lf-ai-seo__section-title"><?php esc_html_e('CWV-Oriented Checks', 'leadsforward-core'); ?></div>
						<div class="lf-ai-seo__vitals-list" data-lf-ai-seo-vitals></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

function lf_ai_assistant_widget_css(): string {
	return '
		.lf-ai-float { position: fixed; right: 20px; bottom: 20px; z-index: 99999; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; display:flex; flex-direction:column; align-items:flex-end; }
		.lf-ai-float--seo { right: 188px; z-index: 99998; }
		.lf-ai-float__toggle { background:#6a3be8; color:#fff; border:0; border-radius:999px; padding:10px 14px; font-weight:600; box-shadow:none; cursor:pointer; display:flex; gap:8px; align-items:center; }
		.lf-ai-float__toggle--seo { background:#6a3be8; box-shadow:none; }
		.lf-ai-float__dot { width:8px; height:8px; border-radius:99px; background:#22c55e; box-shadow:0 0 0 4px rgba(34,197,94,.2); }
		.lf-ai-float__toggle--seo .lf-ai-float__dot { background:#a7f3d0; box-shadow:0 0 0 4px rgba(167,243,208,.25); }
		.lf-ai-float__panel { width:min(440px, calc(100vw - 36px)); max-height:min(80vh, 860px); background:#fff; border:1px solid #dbe3ef; border-radius:14px; box-shadow:0 18px 55px rgba(15,23,42,.25); overflow:hidden; position:absolute; right:0; bottom:calc(100% + 10px); display:flex; flex-direction:column; }
		.lf-ai-float__panel--seo { width:min(460px, calc(100vw - 36px)); }
		.lf-ai-float__panel[hidden] { display:none !important; }
		.lf-ai-float__header { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
		.lf-ai-float__header-actions { display:flex; gap:6px; }
		.lf-ai-float__icon { border:1px solid #d6c8fb; background:#fff; width:28px; height:28px; border-radius:8px; cursor:pointer; font-size:16px; line-height:1; color:#6a33e8; }
		.lf-ai-float__icon.is-active { background:#f5f0ff; border-color:#8348f9; color:#5b21b6; }
		.lf-ai-float__body { padding:12px; display:flex; flex-direction:column; gap:10px; flex:1; min-height:0; overflow:auto; overflow-x:hidden; }
		.lf-ai-float__body, .lf-ai-float__body * { box-sizing:border-box; }
		.lf-ai-float .button { appearance:none; border:1px solid #8c8f94; border-radius:4px; background:#f6f7f7; color:#2c3338; min-height:30px; line-height:2.15384615; padding:0 10px; font-size:13px; cursor:pointer; text-decoration:none; }
		.lf-ai-float .button:hover { background:#f0f0f1; border-color:#0a4b78; color:#0a4b78; }
		.lf-ai-float .button.button-primary { background:#2271b1; border-color:#2271b1; color:#fff; }
		.lf-ai-float .button.button-primary:hover { background:#135e96; border-color:#135e96; color:#fff; }
		.lf-ai-float .button[disabled] { background:#f6f7f7; border-color:#dcdcde; color:#a7aaad; cursor:default; }
		.lf-ai-float__target { font-size:12px; color:#475569; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 8px; }
		.lf-ai-float__mode { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
		.lf-ai-float__mode label { display:flex; flex-direction:column; gap:4px; font-size:12px; color:#475569; }
		.lf-ai-float__mode label[hidden] { display:none !important; }
		.lf-ai-float__mode select { border:1px solid #d6c8fb; border-radius:8px; padding:6px 8px; font-size:13px; color:#0f172a; background:#fff; width:100%; max-width:100%; font-family:inherit; }
		.lf-ai-float__mode input[type="number"] { border:1px solid #d6c8fb; border-radius:8px; padding:6px 8px; font-size:13px; color:#0f172a; background:#fff; width:100%; max-width:100%; box-sizing:border-box; font-family:inherit; }
		.lf-ai-float__target-ref label { display:flex; flex-direction:column; gap:4px; font-size:12px; color:#475569; }
		.lf-ai-float__target-ref input { border:1px solid #d6c8fb; border-radius:8px; padding:6px 8px; font-size:13px; color:#0f172a; background:#fff; width:100%; max-width:100%; box-sizing:border-box; font-family:inherit; }
		.lf-ai-float__presets { display:flex; flex-wrap:wrap; gap:6px; }
		.lf-ai-float__prompt { width:100%; max-width:100%; resize:vertical; min-height:88px; border:1px solid #d6c8fb; border-radius:10px; padding:10px; font-size:13px; font-family:inherit; }
		.lf-ai-float__prompt:focus { border-color:#8348f9; box-shadow:0 0 0 1px #8348f9; outline:none; }
		.lf-ai-float__doc { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
		.lf-ai-float__doc-name { font-size:12px; color:#475569; overflow-wrap:anywhere; }
		.lf-ai-float__actions { display:flex; gap:8px; align-items:center; }
		.lf-ai-float__actions [data-lf-ai-revert] { margin-left:auto; }
		.lf-ai-seo { border:1px solid #c7f0eb; border-radius:12px; background:linear-gradient(180deg,#f4fffd 0%,#f8fafc 100%); padding:10px; display:flex; flex-direction:column; gap:7px; }
		.lf-ai-seo__head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
		.lf-ai-seo__title { font-size:12px; font-weight:700; color:#0f172a; }
		.lf-ai-seo__score { font-size:12px; color:#334155; }
		.lf-ai-seo__score strong { color:#0f172a; }
		.lf-ai-seo__perf-chip { align-self:flex-start; font-size:11px; font-weight:700; border-radius:999px; padding:2px 8px; line-height:1.5; border:1px solid transparent; }
		.lf-ai-seo__perf-chip--pending { color:#475569; background:#f1f5f9; border-color:#e2e8f0; }
		.lf-ai-seo__perf-chip--good { color:#166534; background:#dcfce7; border-color:#86efac; }
		.lf-ai-seo__perf-chip--warn { color:#92400e; background:#fef3c7; border-color:#fcd34d; }
		.lf-ai-seo__perf-chip--bad { color:#b91c1c; background:#fee2e2; border-color:#fca5a5; }
		.lf-ai-seo__meter { width:100%; height:8px; border-radius:999px; background:#e2e8f0; overflow:hidden; }
		.lf-ai-seo__meter > span { display:block; height:100%; width:0; background:linear-gradient(90deg,#ef4444,#f59e0b,#22c55e); transition:width .2s ease; }
		.lf-ai-seo__subscores { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:6px; }
		.lf-ai-seo__subscore { border:1px solid #e2e8f0; border-radius:8px; background:#fff; padding:5px 6px; display:flex; flex-direction:column; gap:2px; }
		.lf-ai-seo__subscore span { font-size:10px; color:#64748b; }
		.lf-ai-seo__subscore b { font-size:12px; color:#0f172a; line-height:1.1; }
		.lf-ai-seo__list { display:flex; flex-direction:column; gap:4px; }
		.lf-ai-seo__item { font-size:11px; color:#334155; display:flex; align-items:flex-start; gap:6px; }
		.lf-ai-seo__item-dot { font-weight:700; min-width:12px; line-height:1.2; }
		.lf-ai-seo__item--ok .lf-ai-seo__item-dot { color:#16a34a; }
		.lf-ai-seo__item--warn .lf-ai-seo__item-dot { color:#d97706; }
		.lf-ai-seo__item--error .lf-ai-seo__item-dot { color:#dc2626; }
		.lf-ai-seo__section-title { font-size:11px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.03em; margin-top:2px; }
		.lf-ai-seo__queue, .lf-ai-seo__serp, .lf-ai-seo__coverage, .lf-ai-seo__vitals { border:1px solid #e2e8f0; border-radius:8px; background:#fff; padding:6px; display:flex; flex-direction:column; gap:4px; }
		.lf-ai-seo__queue-list, .lf-ai-seo__coverage-list, .lf-ai-seo__vitals-list { display:flex; flex-direction:column; gap:3px; }
		.lf-ai-seo__task { font-size:11px; color:#334155; display:flex; gap:6px; align-items:flex-start; }
		.lf-ai-seo__task-priority { font-size:10px; font-weight:700; border-radius:999px; padding:1px 6px; line-height:1.6; }
		.lf-ai-seo__task-priority--high { background:#fee2e2; color:#b91c1c; }
		.lf-ai-seo__task-priority--med { background:#fef3c7; color:#92400e; }
		.lf-ai-seo__task-priority--low { background:#dcfce7; color:#166534; }
		.lf-ai-seo__serp-url { font-size:11px; color:#166534; line-height:1.25; word-break:break-all; }
		.lf-ai-seo__serp-title { font-size:13px; color:#1a0dab; line-height:1.25; }
		.lf-ai-seo__serp-desc { font-size:11px; color:#4b5563; line-height:1.35; }
		.lf-ai-seo__coverage-row, .lf-ai-seo__vital-row { font-size:11px; color:#334155; display:flex; align-items:flex-start; gap:6px; }
		.lf-ai-seo__badge { font-size:10px; border-radius:999px; padding:1px 6px; line-height:1.5; font-weight:700; }
		.lf-ai-seo__badge--ok { background:#dcfce7; color:#166534; }
		.lf-ai-seo__badge--warn { background:#fef3c7; color:#92400e; }
		.lf-ai-seo__badge--error { background:#fee2e2; color:#b91c1c; }
		.lf-ai-float__status { font-size:12px; color:#475569; min-height:16px; }
		.lf-ai-float__status.is-error { color:#b91c1c; }
		.lf-ai-float__diff { border:1px solid #e2e8f0; border-radius:10px; max-height:280px; overflow:auto; background:#f8fafc; font-size:12px; }
		.lf-ai-float__row { border-bottom:1px solid #e2e8f0; padding:8px; }
		.lf-ai-float__row:last-child { border-bottom:0; }
		.lf-ai-float__field { font-weight:600; margin-bottom:6px; color:#0f172a; }
		.lf-ai-float__cols { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
		.lf-ai-float__col { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:6px; }
		.lf-ai-float__col b { display:block; margin-bottom:4px; color:#334155; }
		[data-lf-inline-editable="1"] { cursor:text; transition:outline-color .15s ease, background-color .15s ease; }
		[data-lf-inline-editable="1"]:hover { outline:2px dashed rgba(131,72,249,.45); outline-offset:2px; }
		[data-lf-inline-active="1"] { outline:2px solid #8348f9 !important; outline-offset:2px; background:rgba(131,72,249,.08); }
		[data-lf-inline-saving="1"] { opacity:.72; pointer-events:none; }
		[data-lf-inline-image="1"] { cursor:pointer; transition:outline-color .15s ease, box-shadow .15s ease; }
		[data-lf-inline-image="1"]:hover { outline:2px dashed rgba(131,72,249,.45); outline-offset:3px; box-shadow:0 8px 20px rgba(15,23,42,.12); }
		[data-lf-inline-image-active="1"] { outline:2px solid #8348f9 !important; outline-offset:3px; }
		[data-lf-section-wrap="1"] { position:relative; cursor:grab; transition:outline-color .15s ease, box-shadow .15s ease; }
		[data-lf-section-wrap="1"]:hover { outline:2px dashed rgba(131,72,249,.35); outline-offset:3px; }
		[data-lf-section-wrap="1"].is-dragging { opacity:.55; outline:2px solid #8348f9 !important; outline-offset:3px; cursor:grabbing; }
		.lf-ai-section-controls { position:absolute; top:8px; right:8px; display:flex; gap:6px; z-index:4; opacity:0; transition:opacity .15s ease; }
		[data-lf-section-wrap="1"]:hover .lf-ai-section-controls, [data-lf-section-wrap="1"].lf-ai-section-is-hidden .lf-ai-section-controls { opacity:1; }
		.lf-ai-section-btn { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-width:28px; height:28px; padding:0 7px; font-size:12px; line-height:26px; cursor:pointer; }
		.lf-ai-section-btn:hover { background:#f5f0ff; }
		.lf-ai-section-btn--danger { border-color:#fecaca; color:#b91c1c; }
		[data-lf-section-wrap="1"].lf-ai-section-is-hidden { min-height:56px; background:rgba(131,72,249,.06); outline:2px dashed rgba(131,72,249,.35); outline-offset:3px; }
		[data-lf-section-wrap="1"].lf-ai-section-is-hidden > :not(.lf-ai-section-controls) { display:none !important; }
		[data-lf-section-wrap="1"].lf-ai-section-active { outline:2px solid #8348f9 !important; outline-offset:3px; box-shadow:0 0 0 4px rgba(131,72,249,.12); }
		.lf-ai-checklist-controls { margin-top:8px; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
		.lf-ai-checklist-add { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:28px; padding:0 10px; font-size:12px; cursor:pointer; }
		.lf-ai-checklist-add:hover { background:#f5f0ff; }
		.lf-ai-checklist-remove { border:1px solid #e2e8f0; background:#fff; color:#64748b; border-radius:6px; min-width:20px; height:20px; padding:0 5px; font-size:11px; line-height:18px; margin-left:8px; cursor:pointer; vertical-align:middle; }
		.lf-ai-checklist-remove:hover { border-color:#fecaca; color:#b91c1c; background:#fff5f5; }
		.lf-ai-hero-pills-controls { margin-top:8px; display:flex; gap:8px; align-items:center; }
		.lf-ai-hero-pill-add { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:28px; padding:0 10px; font-size:12px; cursor:pointer; }
		.lf-ai-hero-pill-add:hover { background:#f5f0ff; }
		.lf-ai-hero-pill-remove { border:1px solid rgba(255,255,255,.65); background:rgba(255,255,255,.2); color:inherit; border-radius:999px; min-width:18px; height:18px; padding:0 5px; font-size:11px; line-height:16px; margin-left:8px; cursor:pointer; vertical-align:middle; }
		.lf-ai-hero-pill-remove:hover { background:rgba(255,255,255,.38); }
		.lf-ai-list-remove { border:1px solid #e2e8f0; background:#fff; color:#64748b; border-radius:999px; min-width:18px; height:18px; padding:0 5px; font-size:11px; line-height:16px; margin-left:8px; cursor:pointer; vertical-align:middle; }
		.lf-ai-list-remove:hover { border-color:#fecaca; color:#b91c1c; background:#fff5f5; }
		.lf-ai-process-step.is-dragging { opacity:.6; outline:2px dashed rgba(131,72,249,.4); outline-offset:2px; }
		[data-lf-ai-cta-editable="1"] { position:relative; }
		[data-lf-ai-cta-editable="1"]:hover { outline:2px dashed rgba(131,72,249,.45); outline-offset:2px; }
		.lf-ai-column-draggable { cursor:ew-resize; transition:outline-color .15s ease; }
		.lf-ai-column-draggable:hover { outline:2px dashed rgba(131,72,249,.3); outline-offset:3px; }
		.lf-ai-column-draggable.is-dragging { outline:2px solid #8348f9 !important; outline-offset:3px; opacity:.85; }
		.lf-ai-rail { position:fixed; left:14px; top:54px; z-index:99997; width:248px; max-height:calc(100vh - 84px); overflow:auto; background:rgba(255,255,255,.98); border:1px solid #ddd6fe; border-radius:16px; box-shadow:0 18px 44px rgba(79,35,180,.2); padding:10px; backdrop-filter:blur(8px); }
		.lf-ai-rail.is-collapsed { width:auto; background:transparent; border:0; box-shadow:none; padding:0; max-height:none; }
		.lf-ai-rail__head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin:0 0 8px; }
		.lf-ai-rail__title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#5b21b6; margin:0; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__title { display:none; }
		.lf-ai-rail__head-actions { display:flex; gap:6px; }
		.lf-ai-rail__toggle { border:1px solid #c4b5fd; background:#fff; color:#5b21b6; border-radius:10px; min-width:30px; height:30px; cursor:pointer; font-size:14px; line-height:1; font-weight:700; }
		.lf-ai-rail__add { border:1px solid #c4b5fd; background:#fff; color:#5b21b6; border-radius:10px; width:30px; height:30px; cursor:pointer; font-size:16px; line-height:1; }
		.lf-ai-rail__toggle:hover, .lf-ai-rail__add:hover { background:#f5f3ff; border-color:#a78bfa; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__head { margin:0; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__toggle { width:auto; min-width:0; height:44px; border-radius:999px; padding:0 16px; border:0; color:#fff; background:#6a3be8; box-shadow:none; font-size:22px; font-weight:700; letter-spacing:.02em; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__toggle::before { content:"●"; color:#22c55e; font-size:12px; margin-right:8px; vertical-align:middle; text-shadow:0 0 0 4px rgba(34,197,94,.2); }
		.lf-ai-rail.is-collapsed .lf-ai-rail__add { display:none; }
		.lf-ai-rail__list { display:flex; flex-direction:column; gap:7px; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__list { display:none; }
		.lf-ai-rail__item { border:1px solid #e9e1ff; border-radius:10px; padding:7px 9px; font-size:12px; cursor:pointer; color:#0f172a; background:#fff; display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
		.lf-ai-rail__item:hover { border-color:#a78bfa; background:#faf7ff; box-shadow:0 4px 12px rgba(131,72,249,.12); }
		.lf-ai-rail__item.is-active { border-color:#8348f9; background:#f5f0ff; box-shadow:0 0 0 2px rgba(131,72,249,.15); }
		.lf-ai-rail__item small { display:block; color:#6b7280; margin-top:2px; }
		.lf-ai-rail__item-body { min-width:0; flex:1; }
		.lf-ai-rail__drag { border:1px solid #e9e1ff; border-radius:7px; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; color:#6b7280; cursor:grab; user-select:none; background:#fff; font-size:12px; line-height:1; }
		.lf-ai-rail__drag:active { cursor:grabbing; }
		.lf-ai-rail__item.is-dragging { opacity:.55; border-color:#8348f9; }
		.lf-ai-rail__item.is-drop-before { box-shadow: inset 0 3px 0 #8348f9; }
		.lf-ai-rail__item.is-drop-after { box-shadow: inset 0 -3px 0 #8348f9; }
		body.lf-ai-rail-dragging { user-select:none; -webkit-user-select:none; }
		.lf-ai-rail__library { border:1px solid #ddd6fe; border-radius:12px; padding:7px; margin:0 0 9px; background:#f6f1ff; display:flex; flex-direction:column; gap:6px; }
		.lf-ai-rail__library[hidden] { display:none !important; }
		.lf-ai-rail__library-search { width:100%; border:1px solid #c4b5fd; border-radius:9px; padding:7px 8px; font-size:12px; }
		.lf-ai-rail__library-search:focus { outline:none; border-color:#8348f9; box-shadow:0 0 0 3px rgba(131,72,249,.18); }
		.lf-ai-rail__library-list { max-height:180px; overflow:auto; display:flex; flex-direction:column; gap:4px; }
		.lf-ai-rail__library-item { border:1px solid #e9e1ff; border-radius:9px; background:#fff; padding:6px 8px; font-size:12px; cursor:pointer; text-align:left; color:#0f172a; }
		.lf-ai-rail__library-item:hover { border-color:#a78bfa; background:#faf7ff; }
		.lf-ai-rail__library-item[disabled] { opacity:.5; cursor:default; }
		.lf-ai-command { position:fixed; left:50%; top:16%; transform:translateX(-50%); z-index:100001; width:min(560px, calc(100vw - 26px)); background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); padding:10px; }
		.lf-ai-command[hidden] { display:none !important; }
		.lf-ai-command__input { width:100%; border:1px solid #d6c8fb; border-radius:8px; padding:10px; font-size:14px; }
		.lf-ai-command__list { margin-top:8px; max-height:300px; overflow:auto; display:flex; flex-direction:column; gap:6px; }
		.lf-ai-command__row { border:1px solid #e2e8f0; border-radius:8px; padding:8px; font-size:13px; cursor:pointer; }
		.lf-ai-command__row:hover, .lf-ai-command__row.is-active { border-color:#8348f9; background:#f6f2ff; }
		.lf-ai-command__hint { margin-top:8px; font-size:11px; color:#64748b; }
		.lf-ai-icon-picker { position:fixed; inset:0; z-index:100002; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-icon-picker[hidden] { display:none !important; }
		.lf-ai-icon-picker__card { width:min(560px, calc(100vw - 30px)); max-height:80vh; overflow:hidden; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); display:flex; flex-direction:column; }
		.lf-ai-icon-picker__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
		.lf-ai-icon-picker__title { font-size:13px; font-weight:700; color:#0f172a; }
		.lf-ai-icon-picker__close { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:15px; line-height:1; }
		.lf-ai-icon-picker__search { border:1px solid #d6c8fb; border-radius:8px; padding:8px 10px; margin:10px 12px; font-size:13px; }
		.lf-ai-icon-picker__list { padding:0 12px 12px; overflow:auto; display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px; }
		.lf-ai-icon-picker__item { border:1px solid #e2e8f0; border-radius:8px; background:#fff; color:#0f172a; text-align:left; padding:8px 10px; cursor:pointer; font-size:12px; }
		.lf-ai-icon-picker__item:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-icon-picker__item.is-active { border-color:#8348f9; background:#f5f0ff; color:#5b21b6; }
		.lf-ai-icon-picker__empty { padding:10px; color:#64748b; font-size:12px; }
		.lf-ai-media-add-wrap { margin-top:10px; }
		.lf-ai-media-add { border:1px dashed #bba5f8; background:#faf7ff; color:#6a33e8; border-radius:10px; min-height:34px; padding:0 12px; font-size:12px; cursor:pointer; }
		.lf-ai-media-add:hover { background:#f5f0ff; border-color:#8348f9; }
		.lf-service-details__media .lf-ai-media-add-wrap { position:absolute; top:10px; left:10px; margin-top:0; z-index:3; }
		.lf-service-details__media, .lf-media-section__media { position:relative; }
		.lf-ai-media-picker { position:fixed; inset:0; z-index:100003; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-media-picker[hidden] { display:none !important; }
		.lf-ai-media-picker__card { width:min(560px, calc(100vw - 30px)); max-height:82vh; overflow:hidden; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); display:flex; flex-direction:column; }
		.lf-ai-media-picker__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
		.lf-ai-media-picker__title { font-size:13px; font-weight:700; color:#0f172a; }
		.lf-ai-media-picker__close { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:15px; line-height:1; }
		.lf-ai-media-picker__body { padding:12px; display:flex; flex-direction:column; gap:8px; }
		.lf-ai-media-picker__actions { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px; }
		.lf-ai-media-picker__actions button { border:1px solid #e2e8f0; background:#fff; color:#0f172a; border-radius:8px; min-height:34px; padding:0 10px; font-size:12px; cursor:pointer; }
		.lf-ai-media-picker__actions button:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-media-picker__hint { font-size:12px; color:#64748b; }
		.lf-ai-faq-controls { margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
		.lf-ai-faq-add { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:28px; padding:0 10px; font-size:12px; cursor:pointer; }
		.lf-ai-faq-add:hover { background:#f5f0ff; }
		.lf-ai-faq-picker { position:fixed; inset:0; z-index:100004; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:18px; }
		.lf-ai-faq-picker[hidden] { display:none !important; }
		.lf-ai-faq-picker__card { width:min(720px, calc(100vw - 30px)); max-height:82vh; overflow:hidden; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); display:flex; flex-direction:column; }
		.lf-ai-faq-picker__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
		.lf-ai-faq-picker__title { font-size:13px; font-weight:700; color:#0f172a; }
		.lf-ai-faq-picker__close { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:15px; line-height:1; }
		.lf-ai-faq-picker__search { border:1px solid #d6c8fb; border-radius:8px; padding:8px 10px; margin:10px 12px; font-size:13px; }
		.lf-ai-faq-picker__list { padding:0 12px 12px; overflow:auto; display:flex; flex-direction:column; gap:8px; }
		.lf-ai-faq-picker__item { border:1px solid #e2e8f0; border-radius:8px; background:#fff; padding:8px 10px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
		.lf-ai-faq-picker__item:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-faq-picker__meta b { display:block; font-size:12px; color:#0f172a; margin-bottom:3px; }
		.lf-ai-faq-picker__meta small { display:block; font-size:11px; color:#64748b; line-height:1.35; }
		.lf-ai-faq-picker__add { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; min-height:28px; padding:0 10px; font-size:12px; cursor:pointer; white-space:nowrap; }
		.lf-ai-faq-picker__add:hover { background:#f5f0ff; }
		.lf-ai-faq-picker__add[disabled] { opacity:.5; cursor:default; background:#f8fafc; }
		.lf-ai-faq-picker__empty { padding:10px; color:#64748b; font-size:12px; }
		.lf-ai-float__confirm { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.4); z-index:5; padding:12px; pointer-events:auto; }
		.lf-ai-float__confirm[hidden] { display:none !important; pointer-events:none !important; }
		.lf-ai-float__confirm-card { width:100%; max-width:360px; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 10px 34px rgba(15,23,42,.28); padding:14px; }
		.lf-ai-float__confirm-text { margin:0 0 10px; color:#1e293b; font-size:13px; line-height:1.45; }
		.lf-ai-float__confirm-actions { display:flex; gap:8px; justify-content:flex-end; }
		@media (max-width: 782px) {
			.lf-ai-float { right:12px; bottom:12px; left:12px; }
			.lf-ai-float--seo { right:12px; bottom:68px; left:12px; }
			.lf-ai-float__toggle { width:100%; justify-content:center; }
			.lf-ai-float__panel { width:100%; left:0; right:0; }
			.lf-ai-float__mode { grid-template-columns:1fr; }
			.lf-ai-rail { display:none; }
		}
	';
}

function lf_ai_assistant_widget_js(): string {
	return '(function($){
		"use strict";
		var stateKey = "lfAiFloatState";
		var seoStateKey = "lfAiSeoFloatState";
		var $root = $("[data-lf-ai-float]");
		var $seoRoot = $("[data-lf-ai-seo-float]");
		if (!$root.length || typeof lfAiFloating === "undefined") return;
		$root.attr("data-lf-ai-js-init", "1");
		var $toggle = $root.find("[data-lf-ai-toggle]");
		var $panel = $root.find("#lf-ai-float-panel");
		var $seoToggle = $seoRoot.find("[data-lf-ai-seo-toggle]");
		var $seoPanel = $seoRoot.find("#lf-ai-seo-panel");
		var $prompt = $root.find("[data-lf-ai-prompt]");
		var $status = $root.find("[data-lf-ai-status]");
		var $diff = $root.find("[data-lf-ai-diff]");
		var $btnGenerate = $root.find("[data-lf-ai-generate]");
		var $btnApply = $root.find("[data-lf-ai-apply]");
		var $btnReject = $root.find("[data-lf-ai-reject]");
		var $btnRevert = $root.find("[data-lf-ai-revert]");
		var $btnUndo = $root.find("[data-lf-ai-undo]");
		var $btnRedo = $root.find("[data-lf-ai-redo]");
		var $btnEditorToggle = $root.find("[data-lf-ai-editor-toggle]");
		var $target = $root.find("[data-lf-ai-target]");
		var $targetRef = $root.find("[data-lf-ai-target-ref]");
		var $mode = $root.find("[data-lf-ai-mode]");
		var $cptWrap = $root.find("[data-lf-ai-cpt-wrap]");
		var $cptType = $root.find("[data-lf-ai-cpt-type]");
		var $batchWrap = $root.find("[data-lf-ai-batch-wrap]");
		var $batchType = $root.find("[data-lf-ai-batch-type]");
		var $batchCountWrap = $root.find("[data-lf-ai-batch-count-wrap]");
		var $batchCount = $root.find("[data-lf-ai-batch-count]");
		var $docInput = $root.find("[data-lf-ai-doc-input]");
		var $docAttach = $root.find("[data-lf-ai-doc-attach]");
		var $docClear = $root.find("[data-lf-ai-doc-clear]");
		var $docName = $root.find("[data-lf-ai-doc-name]");
		var $seo = $seoRoot.find("[data-lf-ai-seo]");
		var $seoScore = $seoRoot.find("[data-lf-ai-seo-score]");
		var $seoPerfChip = $seoRoot.find("[data-lf-ai-seo-perf-chip]");
		var $seoList = $seoRoot.find("[data-lf-ai-seo-list]");
		var $seoRefresh = $seoRoot.find("[data-lf-ai-seo-refresh]");
		var $seoOverallFill = $seoRoot.find("[data-lf-ai-seo-overall-fill]");
		var $seoSubSeo = $seoRoot.find("[data-lf-ai-seo-sub-seo]");
		var $seoSubConv = $seoRoot.find("[data-lf-ai-seo-sub-conv]");
		var $seoSubPerf = $seoRoot.find("[data-lf-ai-seo-sub-perf]");
		var $seoTasks = $seoRoot.find("[data-lf-ai-seo-tasks]");
		var $seoSerpUrl = $seoRoot.find("[data-lf-ai-seo-serp-url]");
		var $seoSerpTitle = $seoRoot.find("[data-lf-ai-seo-serp-title]");
		var $seoSerpDesc = $seoRoot.find("[data-lf-ai-seo-serp-desc]");
		var $seoCoverage = $seoRoot.find("[data-lf-ai-seo-coverage]");
		var $seoVitals = $seoRoot.find("[data-lf-ai-seo-vitals]");
		var $confirm = $root.find("[data-lf-ai-confirm]");
		var $confirmText = $root.find("[data-lf-ai-confirm-text]");
		var $confirmYes = $root.find("[data-lf-ai-confirm-yes]");
		var $confirmNo = $root.find("[data-lf-ai-confirm-no]");
		var defaultConfirmText = String($confirmText.text() || "");
		var defaultConfirmYesText = String($confirmYes.text() || "");
		var pendingConfirmAction = null;
		var proposed = null;
		var current = null;
		var creationPayload = null;
		var lastMode = "auto";
		var activeAssistantCptType = String($cptType.val() || "lf_service");
		var activeAssistantBatchType = String($batchType.val() || "post");
		var activeAssistantBatchCount = 5;
		var activeContextType = String(lfAiFloating.context_type || "homepage");
		var activeContextId = String(lfAiFloating.context_id || "homepage");
		var activeTargetLabel = String(lfAiFloating.target_label || "Homepage");
		var labels = lfAiFloating.labels || {};
		var homepageEnabledMap = (lfAiFloating.homepage_enabled && typeof lfAiFloating.homepage_enabled === "object") ? lfAiFloating.homepage_enabled : {};
		var promptSnippet = "";
		var docContext = "";
		var docLabel = "";
		var inlineQuickEdit = null;
		var inlineActiveEl = null;
		var inlineOriginalText = "";
		var inlineIsSaving = false;
		var activeDragSection = null;
		var activeColumnDrag = null;
		var selectedSectionWrap = null;
		var commandPaletteEl = null;
		var commandInputEl = null;
		var commandListEl = null;
		var commandRows = [];
		var commandActiveIndex = 0;
		var sectionRailEl = null;
		var railCollapsed = false;
		var railStateKey = "lfAiRailState";
		var editorModeKey = "lfAiEditorMode";
		var editingEnabled = true;
		var activeRailDragSectionId = "";
		var railPointerDrag = null;
		var activeLibraryDragSectionType = "";
		var activeProcessDragEl = null;
		var activeFaqDragEl = null;
		var railLibraryOpen = false;
		var suppressInlineClickUntil = 0;
		var iconSlugs = Array.isArray(lfAiFloating.icon_slugs) ? lfAiFloating.icon_slugs : [];
		var iconPickerEl = null;
		var iconPickerSearchEl = null;
		var iconPickerListEl = null;
		var iconPickerCurrentSlug = "";
		var iconPickerOnSelect = null;
		var mediaPickerEl = null;
		var mediaPickerWrap = null;
		var faqPickerEl = null;
		var faqPickerSearchEl = null;
		var faqPickerListEl = null;
		var faqPickerWrap = null;
		var faqPickerList = null;
		var faqLibraryCache = null;
		var inlineCandidateSelector = "main h1,main h2,main h3,main h4,main h5,main h6,main p,main li,main blockquote,main figcaption,#primary h1,#primary h2,#primary h3,#primary h4,#primary h5,#primary h6,#primary p,#primary li,#primary blockquote,#primary figcaption,.site-main h1,.site-main h2,.site-main h3,.site-main h4,.site-main h5,.site-main h6,.site-main p,.site-main li,.site-main blockquote,.site-main figcaption,.site-content h1,.site-content h2,.site-content h3,.site-content h4,.site-content h5,.site-content h6,.site-content p,.site-content li,.site-content blockquote,.site-content figcaption,article h1,article h2,article h3,article h4,article h5,article h6,article p,article li,article blockquote,article figcaption";
		var inlineImageCandidateSelector = "main img,#primary img,.site-main img,.site-content img,article img";
		var mediaFrame = null;
		var launcherGapPx = 8;
		var lastPerfScore = null;

		function escapeHtml(text) {
			var div = document.createElement("div");
			div.textContent = String(text || "");
			return div.innerHTML;
		}
		function normalizeInlineText(text) {
			return String(text || "").replace(/\s+/g, " ").trim().toLowerCase();
		}
		function parseInlineReplacePrompt(prompt) {
			var p = String(prompt || "");
			var patterns = [
				/(?:change|replace|update|rewrite)[^"\n]*["“]([^"”]+)["”]\s*(?:to|with)\s*["“]([^"”]+)["”]/i,
				/(?:where\s+it\s+says|where\s+it\s+reads)\s*["“]([^"”]+)["”]\s*(?:to|with)\s*["“]([^"”]+)["”]/i,
				/(?:replace)\s*["“]([^"”]+)["”]\s*(?:with)\s*["“]([^"”]+)["”]/i
			];
			for (var i = 0; i < patterns.length; i++) {
				var m = p.match(patterns[i]);
				if (m && m[1] && m[2]) {
					return { from: String(m[1]).trim(), to: String(m[2]).trim() };
				}
			}
			return null;
		}
		function parseInlineTargetPrompt(prompt) {
			var p = String(prompt || "");
			var patterns = [
				/(?:where\s+it\s+says|where\s+it\s+reads)\s*["“]([^"”]+)["”]/i,
				/(?:change|replace|update|rewrite)\s*["“]([^"”]+)["”]/i
			];
			for (var i = 0; i < patterns.length; i++) {
				var m = p.match(patterns[i]);
				if (m && m[1]) {
					return { from: String(m[1]).trim() };
				}
			}
			return null;
		}
		function resolveInlineSelectorByText(fromText) {
			var needle = normalizeInlineText(fromText);
			if (needle === "") return null;
			var nodes = document.querySelectorAll("[data-lf-inline-editable=\"1\"]");
			if (!nodes || !nodes.length) return null;
			var best = null;
			nodes.forEach(function(node){
				var current = String(node.textContent || "").trim();
				var normalized = normalizeInlineText(current);
				if (normalized === "") return;
				var idx = normalized.indexOf(needle);
				if (idx === -1) return;
				var selector = String(node.getAttribute("data-lf-inline-selector") || "");
				if (selector === "") return;
				var score = 0;
				if (normalized === needle) score += 50;
				if (idx === 0) score += 15;
				score -= Math.abs(normalized.length - needle.length) / 100;
				if (!best || score > best.score) {
					best = { selector: selector, current: current, score: score };
				}
			});
			return best;
		}
		function countWords(text) {
			var clean = String(text || "").replace(/\s+/g, " ").trim();
			if (!clean) return 0;
			return clean.split(" ").filter(function(token){ return token !== ""; }).length;
		}
		function seoMainRoot() {
			return document.querySelector("main") || document.querySelector("#primary") || document.querySelector(".site-main") || document.querySelector("article") || document.body;
		}
		function seoMainText() {
			var root = seoMainRoot();
			if (!root) return "";
			var clone = root.cloneNode(true);
			Array.prototype.slice.call(clone.querySelectorAll("script,style,noscript,nav,footer,.lf-ai-float,.lf-ai-rail,.lf-ai-command,.lf-ai-seo-float")).forEach(function(node){
				if (node && node.parentNode) node.parentNode.removeChild(node);
			});
			return String(clone.textContent || "").replace(/\s+/g, " ").trim();
		}
		function normalizeKeywordTokens(value) {
			return String(value || "")
				.split(/[\r\n,]+/)
				.map(function(v){ return String(v || "").trim(); })
				.filter(function(v){ return v !== ""; });
		}
		function containsPhrase(haystack, needle) {
			var h = normalizeInlineText(haystack);
			var n = normalizeInlineText(needle);
			if (!h || !n) return false;
			return h.indexOf(n) !== -1;
		}
		function statusClass(status) {
			return status === "ok" ? "ok" : (status === "error" ? "error" : "warn");
		}
		function perfGrade(score) {
			var s = parseInt(String(score || "0"), 10);
			if (isNaN(s)) s = 0;
			if (s >= 92) return "A";
			if (s >= 82) return "B";
			if (s >= 70) return "C";
			if (s >= 58) return "D";
			return "F";
		}
		function renderPerfChip(score) {
			if (!$seoPerfChip.length) return;
			var s = parseInt(String(score || "0"), 10);
			if (isNaN(s)) s = 0;
			var grade = perfGrade(s);
			var delta = (lastPerfScore === null || !isFinite(lastPerfScore)) ? null : (s - lastPerfScore);
			var trend = "";
			if (delta !== null) {
				if (delta > 0) trend = " ▲+" + delta;
				else if (delta < 0) trend = " ▼" + delta;
				else trend = " •0";
			}
			$seoPerfChip.removeClass("lf-ai-seo__perf-chip--pending lf-ai-seo__perf-chip--good lf-ai-seo__perf-chip--warn lf-ai-seo__perf-chip--bad");
			var cls = s >= 85 ? "good" : (s >= 70 ? "warn" : "bad");
			$seoPerfChip.addClass("lf-ai-seo__perf-chip--" + cls);
			$seoPerfChip.text("Perf " + grade + " (" + s + ")" + trend);
			lastPerfScore = s;
		}
		function renderSeoSnapshot() {
			if (!$seo.length || !$seoScore.length || !$seoList.length) return;
			var title = String(document.title || "").trim();
			var titleLen = title.length;
			var metaDescNode = document.querySelector("meta[name=\"description\"]");
			var metaDesc = metaDescNode ? String(metaDescNode.getAttribute("content") || "").trim() : "";
			var metaDescLen = metaDesc.length;
			var canonical = !!document.querySelector("link[rel=\"canonical\"]");
			var robotsNode = document.querySelector("meta[name=\"robots\"]");
			var robots = robotsNode ? String(robotsNode.getAttribute("content") || "").toLowerCase() : "";
			var noindex = robots.indexOf("noindex") !== -1;
			var h1Count = document.querySelectorAll("main h1,#primary h1,.site-main h1,article h1,h1.entry-title,h1.wp-block-post-title").length;
			var wordCount = countWords(seoMainText());
			var root = seoMainRoot();
			var ctaCount = root ? root.querySelectorAll("a.lf-btn,button.lf-btn,a[href^=\"tel:\"],a[href*=\"contact\"],a[href*=\"quote\"],a[href*=\"book\"],button[type=\"submit\"]").length : 0;
			var formCount = root ? root.querySelectorAll("form").length : 0;
			var imgs = root ? Array.prototype.slice.call(root.querySelectorAll("img")) : [];
			var missingAlt = imgs.filter(function(img){
				return String(img.getAttribute("alt") || "").trim() === "";
			}).length;
			var missingDimensions = imgs.filter(function(img){
				var hasW = !!img.getAttribute("width");
				var hasH = !!img.getAttribute("height");
				return !(hasW && hasH);
			}).length;
			var lazyImgs = imgs.filter(function(img){
				return String(img.getAttribute("loading") || "").toLowerCase() === "lazy";
			}).length;
			var iframeCount = root ? root.querySelectorAll("iframe").length : 0;
			var videoCount = root ? root.querySelectorAll("video").length : 0;
			var domNodes = root ? root.querySelectorAll("*").length : 0;
			var scriptCount = document.querySelectorAll("script[src]").length;
			var requestCount = null;
			var totalTransferKb = null;
			var scriptTransferKb = null;
			var imageTransferKb = null;
			var perfApi = window.performance || null;
			var navEntry = null;
			var ttfbMs = null;
			var dclMs = null;
			var loadMs = null;
			var fcpMs = null;
			var lcpMs = null;
			var clsScore = null;
			var serverTimingRows = [];
			try {
				var resourceEntries = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("resource") : [];
				if (resourceEntries && resourceEntries.length) {
					requestCount = resourceEntries.length;
					var totalBytes = 0;
					var scriptBytes = 0;
					var imageBytes = 0;
					var hasByteSignals = false;
					resourceEntries.forEach(function(entry){
						if (!entry) return;
						var transfer = isFinite(entry.transferSize) && entry.transferSize > 0 ? entry.transferSize : (isFinite(entry.encodedBodySize) && entry.encodedBodySize > 0 ? entry.encodedBodySize : 0);
						if (transfer > 0) hasByteSignals = true;
						totalBytes += transfer;
						if (entry.initiatorType === "script") scriptBytes += transfer;
						if (entry.initiatorType === "img") imageBytes += transfer;
					});
					if (hasByteSignals) {
						totalTransferKb = Math.round(totalBytes / 1024);
						scriptTransferKb = Math.round(scriptBytes / 1024);
						imageTransferKb = Math.round(imageBytes / 1024);
					}
				}
			} catch (e) {}
			try {
				var navEntries = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("navigation") : [];
				navEntry = navEntries && navEntries.length ? navEntries[0] : null;
				if (navEntry) {
					ttfbMs = isFinite(navEntry.responseStart) ? Math.round(navEntry.responseStart) : null;
					dclMs = isFinite(navEntry.domContentLoadedEventEnd) ? Math.round(navEntry.domContentLoadedEventEnd) : null;
					loadMs = isFinite(navEntry.loadEventEnd) ? Math.round(navEntry.loadEventEnd) : null;
					var serverTiming = Array.isArray(navEntry.serverTiming) ? navEntry.serverTiming : [];
					serverTimingRows = serverTiming.slice(0, 3).map(function(metric){
						var name = String(metric && metric.name ? metric.name : "metric");
						var dur = (metric && isFinite(metric.duration)) ? (Math.round(metric.duration * 10) / 10) : null;
						var desc = String(metric && metric.description ? metric.description : "");
						var status = dur === null ? "warn" : (dur <= 150 ? "ok" : (dur <= 500 ? "warn" : "error"));
						return { label: "Server timing (" + name + "): " + (dur === null ? "unavailable" : (dur + " ms")) + (desc ? " - " + desc : ""), status: status };
					});
				}
			} catch (e) {}
			try {
				var paints = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("paint") : [];
				paints.forEach(function(entry){
					if (entry && entry.name === "first-contentful-paint" && isFinite(entry.startTime)) {
						fcpMs = Math.round(entry.startTime);
					}
				});
			} catch (e) {}
			try {
				var lcpEntries = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("largest-contentful-paint") : [];
				if (lcpEntries && lcpEntries.length) {
					var lcp = lcpEntries[lcpEntries.length - 1];
					if (lcp && isFinite(lcp.startTime)) lcpMs = Math.round(lcp.startTime);
				}
			} catch (e) {}
			try {
				var clsEntries = perfApi && perfApi.getEntriesByType ? perfApi.getEntriesByType("layout-shift") : [];
				if (clsEntries && clsEntries.length) {
					var clsTotal = 0;
					clsEntries.forEach(function(entry){
						if (entry && !entry.hadRecentInput && isFinite(entry.value)) clsTotal += entry.value;
					});
					clsScore = Number(clsTotal.toFixed(3));
				}
			} catch (e) {}
			var internalLinks = root ? Array.prototype.slice.call(root.querySelectorAll("a[href]")).filter(function(link){
				var href = String(link.getAttribute("href") || "");
				return href && href.indexOf("#") !== 0 && href.indexOf("mailto:") !== 0 && href.indexOf("tel:") !== 0 && (href.indexOf("http") !== 0 || href.indexOf(window.location.origin) === 0);
			}).length : 0;
			var qHeadings = root ? root.querySelectorAll("h2,h3").length : 0;
			var faqCount = root ? root.querySelectorAll(".lf-block-faq-accordion__item").length : 0;
			var seoScoreClient = 100;
			if (!title) seoScoreClient -= 20;
			else if (titleLen < 35 || titleLen > 65) seoScoreClient -= 8;
			if (!metaDesc) seoScoreClient -= 12;
			else if (metaDescLen < 120 || metaDescLen > 165) seoScoreClient -= 6;
			if (h1Count === 0) seoScoreClient -= 14;
			if (h1Count > 1) seoScoreClient -= 8;
			if (!canonical) seoScoreClient -= 6;
			if (noindex) seoScoreClient -= 24;
			if (wordCount < 250) seoScoreClient -= 12;
			if (internalLinks < 2) seoScoreClient -= 8;
			if (missingAlt > 0) seoScoreClient -= Math.min(14, missingAlt * 2);
			seoScoreClient = Math.max(0, Math.min(100, seoScoreClient));

			var convScore = 100;
			if (ctaCount === 0) convScore -= 25;
			if (formCount === 0 && ctaCount < 2) convScore -= 15;
			if (faqCount === 0) convScore -= 10;
			if (qHeadings < 2) convScore -= 8;
			if (wordCount < 250) convScore -= 12;
			convScore = Math.max(0, Math.min(100, convScore));

			var perfScore = 100;
			if (domNodes > 1800) perfScore -= 18;
			else if (domNodes > 1200) perfScore -= 10;
			if (iframeCount > 1) perfScore -= 8;
			if ((iframeCount + videoCount) > 2) perfScore -= 6;
			if (missingDimensions > 0) perfScore -= Math.min(15, missingDimensions * 2);
			if (imgs.length > 2 && lazyImgs < Math.max(1, imgs.length - 1)) perfScore -= 10;
			if (scriptCount > 25) perfScore -= 12;
			else if (scriptCount > 18) perfScore -= 6;
			if (requestCount !== null) {
				if (requestCount > 120) perfScore -= 10;
				else if (requestCount > 80) perfScore -= 5;
			}
			if (totalTransferKb !== null) {
				if (totalTransferKb > 3500) perfScore -= 10;
				else if (totalTransferKb > 2200) perfScore -= 5;
			}
			if (loadMs !== null) {
				if (loadMs > 5000) perfScore -= 12;
				else if (loadMs > 3000) perfScore -= 6;
			}
			if (lcpMs !== null) {
				if (lcpMs > 4000) perfScore -= 10;
				else if (lcpMs > 2500) perfScore -= 5;
			}
			perfScore = Math.max(0, Math.min(100, perfScore));

			$seoScore.html("Overall: <strong>Analyzing...</strong>");
			if ($seoPerfChip.length) {
				$seoPerfChip.removeClass("lf-ai-seo__perf-chip--good lf-ai-seo__perf-chip--warn lf-ai-seo__perf-chip--bad").addClass("lf-ai-seo__perf-chip--pending");
				$seoPerfChip.text("Perf " + perfGrade(perfScore) + " (" + perfScore + ")");
			}
			if ($seoOverallFill.length) $seoOverallFill.css("width", "0%");
			if ($seoSubSeo.length) $seoSubSeo.text(seoScoreClient + "/100");
			if ($seoSubConv.length) $seoSubConv.text(convScore + "/100");
			if ($seoSubPerf.length) $seoSubPerf.text(perfScore + "/100");

			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_seo_snapshot",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId
			}).done(function(res){
				var backendSeo = seoScoreClient;
				var backendChecks = [];
				var intent = "";
				if (res && res.success && res.data) {
					backendSeo = parseInt(String(res.data.backend_seo_score || seoScoreClient), 10);
					if (isNaN(backendSeo)) backendSeo = seoScoreClient;
					backendChecks = Array.isArray(res.data.checks) ? res.data.checks : [];
					intent = String(res.data.intent || "");
				}
				var seoScore = Math.round((seoScoreClient * 0.45) + (backendSeo * 0.55));
				var overall = Math.round((seoScore * 0.58) + (convScore * 0.25) + (perfScore * 0.17));
				overall = Math.max(0, Math.min(100, overall));
				$seoScore.html("Overall: <strong>" + overall + "/100</strong>" + (intent ? " • Intent: " + escapeHtml(intent) : ""));
				if ($seoOverallFill.length) $seoOverallFill.css("width", overall + "%");
				if ($seoSubSeo.length) $seoSubSeo.text(seoScore + "/100");
				if ($seoSubConv.length) $seoSubConv.text(convScore + "/100");
				if ($seoSubPerf.length) $seoSubPerf.text(perfScore + "/100");
				renderPerfChip(perfScore);

				var rows = [
					{ status: (!!title && titleLen >= 35 && titleLen <= 65) ? "ok" : "warn", label: "Title length: " + titleLen + " chars (35-65)." },
					{ status: (!!metaDesc && metaDescLen >= 120 && metaDescLen <= 165) ? "ok" : "warn", label: "Meta description: " + metaDescLen + " chars (120-165)." },
					{ status: (h1Count === 1) ? "ok" : (h1Count > 1 ? "warn" : "error"), label: "H1 count: " + h1Count + " (target 1)." },
					{ status: (internalLinks >= 2) ? "ok" : "warn", label: "Internal links in content: " + internalLinks + " (target 2+)." },
					{ status: (ctaCount >= 1) ? "ok" : "error", label: "Primary CTA presence: " + ctaCount + " found." },
					{ status: (missingAlt === 0) ? "ok" : (missingAlt < 3 ? "warn" : "error"), label: "Images missing alt text: " + missingAlt + "." },
					{ status: (perfScore >= 85) ? "ok" : (perfScore >= 70 ? "warn" : "error"), label: "Performance hygiene score: " + perfScore + "/100." }
				];
				backendChecks.forEach(function(ch){
					if (!ch || !ch.label) return;
					var st = String(ch.status || "warn");
					var msg = String(ch.message || "");
					var action = String(ch.action || "");
					rows.push({ status: st, label: String(ch.label) + ": " + msg + (action ? " " + action : "") });
				});
				var actionable = rows.filter(function(row){ return row.status !== "ok"; });
				var taskRows = actionable.slice(0, 8).map(function(row, idx){
					var pr = row.status === "error" ? "high" : (idx < 3 ? "med" : "low");
					return { priority: pr, label: row.label };
				});
				var taskHtml = "";
				if (!taskRows.length) {
					taskHtml = "<div class=\"lf-ai-seo__task\"><span class=\"lf-ai-seo__task-priority lf-ai-seo__task-priority--low\">OK</span><span>No urgent SEO actions. Keep refining for conversions.</span></div>";
				} else {
					taskRows.forEach(function(task){
						taskHtml += "<div class=\"lf-ai-seo__task\"><span class=\"lf-ai-seo__task-priority lf-ai-seo__task-priority--" + task.priority + "\">" + (task.priority === "high" ? "HIGH" : (task.priority === "med" ? "MED" : "LOW")) + "</span><span>" + escapeHtml(task.label) + "</span></div>";
					});
				}
				if ($seoTasks.length) $seoTasks.html(taskHtml);

				var meta = (res && res.success && res.data && res.data.meta) ? res.data.meta : {};
				var backendTitle = (res && res.success && res.data) ? String(res.data.post_title || "") : "";
				var backendPermalink = (res && res.success && res.data) ? String(res.data.permalink || "") : "";
				var serpTitle = String((meta.meta_title || "") || title || backendTitle || "").trim();
				var serpDesc = String((meta.meta_description || "") || metaDesc || "").trim();
				var serpUrl = String((meta.canonical || "") || backendPermalink || window.location.href || "").trim();
				if ($seoSerpUrl.length) $seoSerpUrl.text(serpUrl);
				if ($seoSerpTitle.length) $seoSerpTitle.text(serpTitle || "(missing title)");
				if ($seoSerpDesc.length) $seoSerpDesc.text(serpDesc || "(missing meta description)");

				var primaryKw = String(meta.primary_keyword || "").trim();
				var secondaryKws = normalizeKeywordTokens(meta.secondary_keywords || "");
				var h1TextNode = document.querySelector("main h1,#primary h1,.site-main h1,article h1,h1.entry-title,h1.wp-block-post-title");
				var h1Text = h1TextNode ? String(h1TextNode.textContent || "") : "";
				var introNode = document.querySelector("main p,#primary p,.site-main p,article p");
				var introText = introNode ? String(introNode.textContent || "") : "";
				var coverageRows = [];
				if (primaryKw) {
					coverageRows.push({ label: "Primary keyword in title", status: containsPhrase(serpTitle, primaryKw) ? "ok" : "warn" });
					coverageRows.push({ label: "Primary keyword in H1", status: containsPhrase(h1Text, primaryKw) ? "ok" : "warn" });
					coverageRows.push({ label: "Primary keyword in intro", status: containsPhrase(introText, primaryKw) ? "ok" : "warn" });
				} else {
					coverageRows.push({ label: "Primary keyword missing in backend SEO settings", status: "error" });
				}
				secondaryKws.slice(0, 3).forEach(function(kw){
					coverageRows.push({ label: "Secondary keyword \"" + kw + "\" in page copy", status: containsPhrase(seoMainText(), kw) ? "ok" : "warn" });
				});
				var coverageHtml = "";
				coverageRows.forEach(function(row){
					var cls = statusClass(row.status);
					coverageHtml += "<div class=\"lf-ai-seo__coverage-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + cls + "\">" + (cls === "ok" ? "OK" : (cls === "error" ? "FIX" : "CHECK")) + "</span><span>" + escapeHtml(row.label) + "</span></div>";
				});
				if ($seoCoverage.length) $seoCoverage.html(coverageHtml);

				function metricStatus(value, good, warn) {
					if (value === null || value === undefined || !isFinite(value)) return "warn";
					return value <= good ? "ok" : (value <= warn ? "warn" : "error");
				}
				var vitalsRows = [
					{ label: "TTFB (" + (ttfbMs === null ? "unavailable" : (ttfbMs + " ms")) + ")", status: metricStatus(ttfbMs, 800, 1800) },
					{ label: "DOM content loaded (" + (dclMs === null ? "unavailable" : (dclMs + " ms")) + ")", status: metricStatus(dclMs, 1500, 3000) },
					{ label: "Window load (" + (loadMs === null ? "unavailable" : (loadMs + " ms")) + ")", status: metricStatus(loadMs, 2500, 5000) },
					{ label: "First contentful paint (" + (fcpMs === null ? "unavailable" : (fcpMs + " ms")) + ")", status: metricStatus(fcpMs, 1800, 3000) },
					{ label: "Largest contentful paint (" + (lcpMs === null ? "unavailable" : (lcpMs + " ms")) + ")", status: metricStatus(lcpMs, 2500, 4000) },
					{ label: "Cumulative layout shift (" + (clsScore === null ? "unavailable" : clsScore) + ")", status: clsScore === null ? "warn" : (clsScore <= 0.1 ? "ok" : (clsScore <= 0.25 ? "warn" : "error")) },
					{ label: "Requests (" + (requestCount === null ? "unavailable" : requestCount) + ")", status: requestCount === null ? "warn" : (requestCount <= 80 ? "ok" : (requestCount <= 120 ? "warn" : "error")) },
					{ label: "Transferred bytes (" + (totalTransferKb === null ? "unavailable" : (totalTransferKb + " KB")) + ")", status: totalTransferKb === null ? "warn" : (totalTransferKb <= 2200 ? "ok" : (totalTransferKb <= 3500 ? "warn" : "error")) },
					{ label: "Script bytes (" + (scriptTransferKb === null ? "unavailable" : (scriptTransferKb + " KB")) + ")", status: scriptTransferKb === null ? "warn" : (scriptTransferKb <= 600 ? "ok" : (scriptTransferKb <= 1100 ? "warn" : "error")) },
					{ label: "Image bytes (" + (imageTransferKb === null ? "unavailable" : (imageTransferKb + " KB")) + ")", status: imageTransferKb === null ? "warn" : (imageTransferKb <= 1000 ? "ok" : (imageTransferKb <= 1800 ? "warn" : "error")) },
					{ label: "DOM nodes (" + domNodes + ")", status: domNodes <= 1200 ? "ok" : (domNodes <= 1800 ? "warn" : "error") },
					{ label: "Lazy-loaded images (" + lazyImgs + "/" + imgs.length + ")", status: imgs.length === 0 || lazyImgs >= Math.max(1, imgs.length - 1) ? "ok" : "warn" },
					{ label: "Images with dimensions missing (" + missingDimensions + ")", status: missingDimensions === 0 ? "ok" : (missingDimensions < 3 ? "warn" : "error") },
					{ label: "Embedded heavy media (iframes/videos: " + (iframeCount + videoCount) + ")", status: (iframeCount + videoCount) <= 2 ? "ok" : "warn" },
					{ label: "External script count (" + scriptCount + ")", status: scriptCount <= 18 ? "ok" : (scriptCount <= 25 ? "warn" : "error") }
				];
				serverTimingRows.forEach(function(row){
					vitalsRows.push(row);
				});
				var vitalsHtml = "";
				vitalsRows.forEach(function(row){
					var cls = statusClass(row.status);
					vitalsHtml += "<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + cls + "\">" + (cls === "ok" ? "GOOD" : (cls === "error" ? "HIGH" : "MED")) + "</span><span>" + escapeHtml(row.label) + "</span></div>";
				});
				if ($seoVitals.length) $seoVitals.html(vitalsHtml);
				var html = "";
				rows.slice(0, 12).forEach(function(row){
					var cls = row.status === "ok" ? "ok" : (row.status === "error" ? "error" : "warn");
					var dot = cls === "ok" ? "●" : (cls === "error" ? "■" : "▲");
					html += "<div class=\"lf-ai-seo__item lf-ai-seo__item--" + cls + "\"><span class=\"lf-ai-seo__item-dot\">" + dot + "</span><span>" + escapeHtml(row.label) + "</span></div>";
				});
				$seoList.html(html);
			}).fail(function(){
				var overallFallback = Math.round((seoScoreClient * 0.58) + (convScore * 0.25) + (perfScore * 0.17));
				overallFallback = Math.max(0, Math.min(100, overallFallback));
				$seoScore.html("Overall: <strong>" + overallFallback + "/100</strong>");
				renderPerfChip(perfScore);
				if ($seoOverallFill.length) $seoOverallFill.css("width", overallFallback + "%");
				$seoList.html("<div class=\"lf-ai-seo__item lf-ai-seo__item--warn\"><span class=\"lf-ai-seo__item-dot\">▲</span><span>Backend SEO snapshot unavailable right now. Showing client-side diagnostics only.</span></div>");
				if ($seoTasks.length) $seoTasks.html("<div class=\"lf-ai-seo__task\"><span class=\"lf-ai-seo__task-priority lf-ai-seo__task-priority--med\">MED</span><span>Reconnect backend SEO snapshot for full guidance; continue fixing on-page warnings first.</span></div>");
				if ($seoSerpUrl.length) $seoSerpUrl.text(window.location.href || "");
				if ($seoSerpTitle.length) $seoSerpTitle.text(title || "(missing title)");
				if ($seoSerpDesc.length) $seoSerpDesc.text(metaDesc || "(missing meta description)");
				if ($seoCoverage.length) $seoCoverage.html("<div class=\"lf-ai-seo__coverage-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--warn\">CHECK</span><span>Set primary/secondary keywords in SEO meta box to enable full keyword coverage mapping.</span></div>");
				if ($seoVitals.length) $seoVitals.html(
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + ((loadMs !== null && loadMs <= 2500) ? "ok" : ((loadMs !== null && loadMs <= 5000) ? "warn" : "error")) + "\">" + ((loadMs !== null && loadMs <= 2500) ? "GOOD" : ((loadMs !== null && loadMs <= 5000) ? "MED" : "HIGH")) + "</span><span>Window load: " + (loadMs === null ? "unavailable" : (loadMs + " ms")) + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + ((lcpMs !== null && lcpMs <= 2500) ? "ok" : ((lcpMs !== null && lcpMs <= 4000) ? "warn" : "error")) + "\">" + ((lcpMs !== null && lcpMs <= 2500) ? "GOOD" : ((lcpMs !== null && lcpMs <= 4000) ? "MED" : "HIGH")) + "</span><span>LCP: " + (lcpMs === null ? "unavailable" : (lcpMs + " ms")) + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + ((requestCount !== null && requestCount <= 80) ? "ok" : ((requestCount !== null && requestCount <= 120) ? "warn" : "error")) + "\">" + ((requestCount !== null && requestCount <= 80) ? "GOOD" : ((requestCount !== null && requestCount <= 120) ? "MED" : "HIGH")) + "</span><span>Requests: " + (requestCount === null ? "unavailable" : requestCount) + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + ((totalTransferKb !== null && totalTransferKb <= 2200) ? "ok" : ((totalTransferKb !== null && totalTransferKb <= 3500) ? "warn" : "error")) + "\">" + ((totalTransferKb !== null && totalTransferKb <= 2200) ? "GOOD" : ((totalTransferKb !== null && totalTransferKb <= 3500) ? "MED" : "HIGH")) + "</span><span>Transferred bytes: " + (totalTransferKb === null ? "unavailable" : (totalTransferKb + " KB")) + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + (domNodes <= 1200 ? "ok" : "warn") + "\">" + (domNodes <= 1200 ? "GOOD" : "MED") + "</span><span>DOM nodes: " + domNodes + "</span></div>" +
					"<div class=\"lf-ai-seo__vital-row\"><span class=\"lf-ai-seo__badge lf-ai-seo__badge--" + (missingDimensions === 0 ? "ok" : "warn") + "\">" + (missingDimensions === 0 ? "GOOD" : "MED") + "</span><span>Images missing dimensions: " + missingDimensions + "</span></div>"
				);
			});
		}
		function setStatus(msg, isError) {
			$status.text(msg || "");
			$status.toggleClass("is-error", !!isError);
		}
		function updateLauncherOffsets() {
			if (!$seoRoot.length || !$toggle.length) return;
			var narrow = false;
			try { narrow = !!(window.matchMedia && window.matchMedia("(max-width: 782px)").matches); } catch (e) {}
			if (narrow) {
				$seoRoot.css("right", "12px");
				return;
			}
			var aiWidth = Math.ceil($toggle.outerWidth() || 0);
			var aiRight = 20;
			var seoRight = aiRight + aiWidth + launcherGapPx;
			$seoRoot.css("right", Math.max(130, seoRight) + "px");
		}
		function setAiOpen(open) {
			$panel.prop("hidden", !open);
			$toggle.attr("aria-expanded", open ? "true" : "false");
			if (open && $seoPanel.length) {
				$seoPanel.prop("hidden", true);
				$seoToggle.attr("aria-expanded", "false");
				try { window.localStorage.setItem(seoStateKey, "closed"); } catch (e) {}
			}
			if (!open) {
				saveInlineEdit();
			}
			try { window.localStorage.setItem(stateKey, open ? "open" : "closed"); } catch (e) {}
		}
		function setSeoOpen(open) {
			if (!$seoPanel.length || !$seoToggle.length) return;
			$seoPanel.prop("hidden", !open);
			$seoToggle.attr("aria-expanded", open ? "true" : "false");
			if (open) {
				setConfirmOpen(false);
				$panel.prop("hidden", true);
				$toggle.attr("aria-expanded", "false");
				saveInlineEdit();
				try { window.localStorage.setItem(stateKey, "closed"); } catch (e) {}
				renderSeoSnapshot();
			}
			try { window.localStorage.setItem(seoStateKey, open ? "open" : "closed"); } catch (e) {}
		}
		function setEditorToggleUi() {
			if (!$btnEditorToggle || !$btnEditorToggle.length) return;
			$btnEditorToggle.toggleClass("is-active", editingEnabled);
			$btnEditorToggle.text(editingEnabled ? "✎" : "👁");
			$btnEditorToggle.attr("title", editingEnabled ? "Editor ON (click for live mode)" : "Live mode ON (click for editor)");
		}
		function clearEditorUi() {
			saveInlineEdit();
			cancelInlineEdit(false);
			selectedSectionWrap = null;
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap) return;
				wrap.classList.remove("lf-ai-section-active");
				wrap.setAttribute("draggable", "false");
				wrap.onmousedown = null;
				wrap.ondragstart = null;
				wrap.ondragover = null;
				wrap.ondrop = null;
				wrap.ondragend = null;
			});
			Array.prototype.slice.call(document.querySelectorAll("[data-lf-inline-editable=\"1\"],[data-lf-inline-selector]")).forEach(function(node){
				node.removeAttribute("data-lf-inline-editable");
				node.removeAttribute("data-lf-inline-selector");
				node.removeAttribute("data-lf-inline-field-key");
			});
			Array.prototype.slice.call(document.querySelectorAll("[data-lf-inline-image=\"1\"],[data-lf-inline-image-selector]")).forEach(function(node){
				node.removeAttribute("data-lf-inline-image");
				node.removeAttribute("data-lf-inline-image-selector");
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-ai-section-controls,[data-lf-ai-hero-pills-controls=\"1\"],[data-lf-ai-hero-proof-controls=\"1\"],[data-lf-ai-trust-pill-controls=\"1\"],[data-lf-ai-process-controls=\"1\"],[data-lf-ai-checklist-controls=\"1\"],[data-lf-ai-faq-controls=\"1\"],[data-lf-ai-list-remove=\"1\"],[data-lf-ai-checklist-remove=\"1\"],[data-lf-ai-hero-pill-remove=\"1\"],[data-lf-ai-media-add=\"1\"]")).forEach(function(node){
				if (node && node.parentNode) node.parentNode.removeChild(node);
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-service-details__text")).forEach(function(node){
				if (!node) return;
				node.onmousedown = null;
				node.onclick = null;
				node.onblur = null;
				node.onkeydown = null;
				node.removeAttribute("contenteditable");
				node.removeAttribute("spellcheck");
				node.removeAttribute("data-lf-ai-editing");
				node.removeAttribute("data-lf-ai-original-text");
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-block-hero__card-item-text")).forEach(function(node){
				if (!node) return;
				node.onmousedown = null;
				node.onclick = null;
				node.onblur = null;
				node.onkeydown = null;
				node.removeAttribute("contenteditable");
				node.removeAttribute("spellcheck");
				node.removeAttribute("data-lf-ai-editing");
				node.removeAttribute("data-lf-ai-original-text");
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-benefits__icon[data-lf-benefit-icon-index]")).forEach(function(node){
				if (!node) return;
				node.onclick = null;
				node.style.cursor = "";
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-process__step,.lf-block-faq-accordion__item[data-lf-faq-id],.lf-ai-column-draggable")).forEach(function(node){
				if (!node) return;
				node.setAttribute("draggable", "false");
				node.ondragstart = null;
				node.ondragover = null;
				node.ondrop = null;
				node.ondragend = null;
				node.ondblclick = null;
				node.classList.remove("is-dragging", "lf-ai-process-step", "lf-ai-column-draggable");
			});
			Array.prototype.slice.call(document.querySelectorAll(".lf-service-details--media")).forEach(function(node){
				if (!node) return;
				node.ondragover = null;
				node.ondrop = null;
			});
			if (sectionRailEl && sectionRailEl.parentNode) {
				sectionRailEl.parentNode.removeChild(sectionRailEl);
			}
			sectionRailEl = null;
			if (iconPickerEl) {
				iconPickerEl.hidden = true;
			}
			if (mediaPickerEl) {
				mediaPickerEl.hidden = true;
			}
			if (faqPickerEl) {
				faqPickerEl.hidden = true;
			}
			faqPickerWrap = null;
			faqPickerList = null;
		}
		function closeIconPicker() {
			if (!iconPickerEl) return;
			iconPickerEl.hidden = true;
			iconPickerOnSelect = null;
			try { if (iconPickerSearchEl) iconPickerSearchEl.value = ""; } catch (e) {}
		}
		function renderIconPickerList(query) {
			if (!iconPickerListEl) return;
			var q = String(query || "").trim().toLowerCase();
			var rows = iconSlugs.filter(function(slug){
				var s = String(slug || "").toLowerCase();
				return !q || s.indexOf(q) !== -1;
			});
			iconPickerListEl.innerHTML = "";
			var clearBtn = document.createElement("button");
			clearBtn.type = "button";
			clearBtn.className = "lf-ai-icon-picker__item" + (iconPickerCurrentSlug === "" ? " is-active" : "");
			clearBtn.textContent = "Use default icon";
			clearBtn.addEventListener("click", function(){
				if (typeof iconPickerOnSelect === "function") iconPickerOnSelect("");
				closeIconPicker();
			});
			iconPickerListEl.appendChild(clearBtn);
			if (!rows.length) {
				var empty = document.createElement("div");
				empty.className = "lf-ai-icon-picker__empty";
				empty.textContent = "No matching icons in library.";
				iconPickerListEl.appendChild(empty);
				return;
			}
			rows.forEach(function(slug){
				var btn = document.createElement("button");
				btn.type = "button";
				btn.className = "lf-ai-icon-picker__item" + (String(slug) === iconPickerCurrentSlug ? " is-active" : "");
				btn.textContent = String(slug);
				btn.addEventListener("click", function(){
					if (typeof iconPickerOnSelect === "function") iconPickerOnSelect(String(slug));
					closeIconPicker();
				});
				iconPickerListEl.appendChild(btn);
			});
		}
		function ensureIconPicker() {
			if (iconPickerEl) return iconPickerEl;
			iconPickerEl = document.createElement("div");
			iconPickerEl.className = "lf-ai-icon-picker lf-ai-inline-editor-ignore";
			iconPickerEl.hidden = true;
			iconPickerEl.innerHTML = "<div class=\"lf-ai-icon-picker__card\"><div class=\"lf-ai-icon-picker__head\"><div class=\"lf-ai-icon-picker__title\">Choose an icon</div><button type=\"button\" class=\"lf-ai-icon-picker__close\" data-lf-ai-icon-picker-close aria-label=\"Close icon picker\">×</button></div><input type=\"text\" class=\"lf-ai-icon-picker__search\" data-lf-ai-icon-picker-search placeholder=\"Search icon slug...\" /><div class=\"lf-ai-icon-picker__list\" data-lf-ai-icon-picker-list></div></div>";
			iconPickerSearchEl = iconPickerEl.querySelector("[data-lf-ai-icon-picker-search]");
			iconPickerListEl = iconPickerEl.querySelector("[data-lf-ai-icon-picker-list]");
			var closeBtn = iconPickerEl.querySelector("[data-lf-ai-icon-picker-close]");
			if (closeBtn) {
				closeBtn.addEventListener("click", function(e){
					e.preventDefault();
					closeIconPicker();
				});
			}
			if (iconPickerSearchEl) {
				iconPickerSearchEl.addEventListener("input", function(){
					renderIconPickerList(iconPickerSearchEl.value);
				});
			}
			iconPickerEl.addEventListener("click", function(e){
				if (e.target === iconPickerEl) closeIconPicker();
			});
			document.body.appendChild(iconPickerEl);
			return iconPickerEl;
		}
		function openIconPicker(currentSlug, onSelect) {
			if (!iconSlugs.length) {
				setStatus("Icon library is unavailable on this screen.", true);
				return;
			}
			ensureIconPicker();
			iconPickerCurrentSlug = String(currentSlug || "");
			iconPickerOnSelect = typeof onSelect === "function" ? onSelect : null;
			if (!iconPickerEl) return;
			iconPickerEl.hidden = false;
			if (iconPickerSearchEl) iconPickerSearchEl.value = "";
			renderIconPickerList("");
			try { if (iconPickerSearchEl) iconPickerSearchEl.focus(); } catch (e) {}
		}
		function sectionSupportsMediaEditor(sectionType) {
			var type = String(sectionType || "");
			return ["service_details", "content_image", "content_image_a", "image_content", "image_content_b", "content_image_c"].indexOf(type) !== -1;
		}
		function persistSectionMediaSettings(wrap, payload) {
			if (!wrap || !payload) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			if (!sectionId || !sectionSupportsMediaEditor(sectionType)) return;
			setStatus("Saving section media...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_media",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				mode: String(payload.mode || "image"),
				image_id: parseInt(String(payload.image_id || "0"), 10) || 0,
				video_url: String(payload.video_url || ""),
				embed_code: String(payload.embed_code || "")
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Section media updated.", false);
					if (res.data && res.data.reload) {
						window.location.reload();
					}
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Section media update failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section media update failed.";
				setStatus(msg, true);
			});
		}
		function closeMediaPicker() {
			if (!mediaPickerEl) return;
			mediaPickerEl.hidden = true;
			mediaPickerWrap = null;
		}
		function openMediaLibraryForWrap(wrap) {
			if (!wrap || !(window.wp && wp.media)) {
				setStatus("Media Library is unavailable on this screen.", true);
				return;
			}
			var frame = wp.media({
				title: "Select section media image",
				button: { text: "Use image" },
				library: { type: "image" },
				multiple: false
			});
			frame.on("select", function(){
				var selection = frame.state().get("selection");
				var attachment = selection && selection.first ? selection.first().toJSON() : null;
				var attachmentId = parseInt(String(attachment && attachment.id ? attachment.id : "0"), 10);
				if (!attachmentId) return;
				persistSectionMediaSettings(wrap, { mode: "image", image_id: attachmentId, video_url: "", embed_code: "" });
				closeMediaPicker();
			});
			frame.open();
		}
		function ensureMediaPicker() {
			if (mediaPickerEl) return mediaPickerEl;
			mediaPickerEl = document.createElement("div");
			mediaPickerEl.className = "lf-ai-media-picker lf-ai-inline-editor-ignore";
			mediaPickerEl.hidden = true;
			mediaPickerEl.innerHTML = "<div class=\"lf-ai-media-picker__card\"><div class=\"lf-ai-media-picker__head\"><div class=\"lf-ai-media-picker__title\">Section media</div><button type=\"button\" class=\"lf-ai-media-picker__close\" data-lf-ai-media-picker-close aria-label=\"Close media picker\">×</button></div><div class=\"lf-ai-media-picker__body\"><div class=\"lf-ai-media-picker__actions\"><button type=\"button\" data-lf-ai-media-image>+ Add / replace image</button><button type=\"button\" data-lf-ai-media-video-url>Video URL (YouTube/Vimeo/MP4)</button><button type=\"button\" data-lf-ai-media-embed>Embed code (iframe)</button><button type=\"button\" data-lf-ai-media-none>Remove media</button></div><div class=\"lf-ai-media-picker__hint\">Tip: choose Video URL for YouTube links, or Embed code for a custom iframe.</div></div></div>";
			var closeBtn = mediaPickerEl.querySelector("[data-lf-ai-media-picker-close]");
			var imageBtn = mediaPickerEl.querySelector("[data-lf-ai-media-image]");
			var videoBtn = mediaPickerEl.querySelector("[data-lf-ai-media-video-url]");
			var embedBtn = mediaPickerEl.querySelector("[data-lf-ai-media-embed]");
			var noneBtn = mediaPickerEl.querySelector("[data-lf-ai-media-none]");
			if (closeBtn) {
				closeBtn.addEventListener("click", function(e){
					e.preventDefault();
					closeMediaPicker();
				});
			}
			if (imageBtn) {
				imageBtn.addEventListener("click", function(e){
					e.preventDefault();
					if (!mediaPickerWrap) return;
					openMediaLibraryForWrap(mediaPickerWrap);
				});
			}
			if (videoBtn) {
				videoBtn.addEventListener("click", function(e){
					e.preventDefault();
					if (!mediaPickerWrap) return;
					var url = "";
					try {
						url = String(window.prompt("Enter a video URL (YouTube/Vimeo/MP4):", "") || "").trim();
					} catch (err) {
						url = "";
					}
					if (!url) return;
					persistSectionMediaSettings(mediaPickerWrap, { mode: "video", image_id: 0, video_url: url, embed_code: "" });
					closeMediaPicker();
				});
			}
			if (embedBtn) {
				embedBtn.addEventListener("click", function(e){
					e.preventDefault();
					if (!mediaPickerWrap) return;
					var code = "";
					try {
						code = String(window.prompt("Paste embed code (iframe):", "") || "").trim();
					} catch (err) {
						code = "";
					}
					if (!code) return;
					persistSectionMediaSettings(mediaPickerWrap, { mode: "video", image_id: 0, video_url: "", embed_code: code });
					closeMediaPicker();
				});
			}
			if (noneBtn) {
				noneBtn.addEventListener("click", function(e){
					e.preventDefault();
					if (!mediaPickerWrap) return;
					persistSectionMediaSettings(mediaPickerWrap, { mode: "none", image_id: 0, video_url: "", embed_code: "" });
					closeMediaPicker();
				});
			}
			mediaPickerEl.addEventListener("click", function(e){
				if (e.target === mediaPickerEl) closeMediaPicker();
			});
			document.body.appendChild(mediaPickerEl);
			return mediaPickerEl;
		}
		function openSectionMediaPicker(wrap) {
			if (!wrap) return;
			ensureMediaPicker();
			mediaPickerWrap = wrap;
			mediaPickerEl.hidden = false;
		}
		function buildSectionMediaEditors() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (!sectionSupportsMediaEditor(sectionType)) return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-media-add=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var mediaHost = wrap.querySelector(".lf-service-details__media,.lf-media-section__media");
				var container = mediaHost;
				if (!container) {
					container = wrap.querySelector(".lf-service-details__content,.lf-media-section__content");
					if (!container) return;
				}
				var addWrap = document.createElement("div");
				addWrap.className = "lf-ai-media-add-wrap lf-ai-inline-editor-ignore";
				addWrap.setAttribute("data-lf-ai-media-add", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-media-add";
				addBtn.textContent = mediaHost ? "+ Media" : "+ Add media";
				addBtn.setAttribute("title", "Add or replace image/video");
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					openSectionMediaPicker(wrap);
				});
				addWrap.appendChild(addBtn);
				container.appendChild(addWrap);
			});
		}
		function buildEditorUi() {
			buildInlineTargets();
			buildInlineImageTargets();
			buildSectionTargets();
			buildSectionControls();
			buildSectionButtonEditors();
			buildHeroPillsControls();
			buildHeroProofChecklistControls();
			buildTrustBadgePillsControls();
			buildChecklistControls();
			buildProcessStepControls();
			buildSectionColumnSwapTargets();
			buildSectionMediaEditors();
			buildFaqReorderControls();
			buildBenefitsIconEditors();
			refreshSectionRail();
			renderSeoSnapshot();
			var firstWrap = collectSectionWrappers()[0] || null;
			if (firstWrap) {
				setSelectedSection(firstWrap);
			}
		}
		function setEditorEnabled(enabled) {
			editingEnabled = !!enabled;
			setEditorToggleUi();
			try { window.localStorage.setItem(editorModeKey, editingEnabled ? "on" : "off"); } catch (e) {}
			if (!editingEnabled) {
				clearEditorUi();
				setStatus("Live mode enabled. Editing is off.", false);
				renderSeoSnapshot();
				return;
			}
			buildEditorUi();
			setStatus("Editor mode enabled.", false);
			renderSeoSnapshot();
		}
		function isDragBlockedTarget(target) {
			if (!target) return false;
			if (target.closest(".lf-ai-float,.lf-ai-seo-float")) return true;
			if (target.closest("a,button,input,textarea,select,label,[contenteditable=\"true\"],.lf-ai-inline-editor-ignore")) return true;
			return false;
		}
		function inlineNodeEligible(node) {
			if (!node || !node.textContent) return false;
			if (node.closest(".lf-ai-float,.lf-ai-seo-float")) return false;
			if (node.closest("nav, footer, [aria-hidden=\"true\"]")) return false;
			if (node.closest(".site-header, .site-footer, #masthead, #colophon")) return false;
			if (node.closest("button, a, label, script, style, noscript")) return false;
			// Managed lists/pills use dedicated controls; block generic inline editing for their rows.
			if (node.closest(".lf-hero-chips,.lf-block-hero__card-list,.lf-trust-bar__badges,.lf-service-details__checklist,.lf-process,.lf-block-faq-accordion__list")) return false;
			// Reviews content is source-of-truth data; do not edit testimonial copy inline.
			if (node.closest(".lf-block-trust-reviews__item,.lf-block-trust-reviews__summary")) return false;
			var tag = node.tagName ? node.tagName.toLowerCase() : "";
			var isHeading = /^h[1-6]$/.test(tag);
			// SEO safety: do not allow inline editing of entity/archive titles.
			if (node.closest(".lf-blog-hero__title,.lf-post-card__title,.entry-title,[class*=\"project\"][class*=\"title\"],[class*=\"service\"][class*=\"title\"],[class*=\"blog\"][class*=\"title\"]")) return false;
			if (isHeading) {
				var entity = node.closest("article,.hentry,.type-post,.type-page,.type-lf_project,.type-lf_service,.type-lf_service_area,.type-lf_faq");
				if (entity) {
					var lockedTitle = entity.querySelector(".entry-title,h1.entry-title,h1.page-title,h1.post-title,h1.wp-block-post-title,[data-lf-seo-title=\"1\"]");
					if (lockedTitle && lockedTitle === node) return false;
					if (node.closest(".entry-header")) return false;
				}
			}
			var text = String(node.textContent || "").trim();
			return text !== "";
		}
		function nodeSegment(node) {
			var parent = node.parentElement;
			if (!parent) {
				return node.tagName.toLowerCase();
			}
			var tag = node.tagName.toLowerCase();
			var index = 1;
			var sibling = node.previousElementSibling;
			while (sibling) {
				if (sibling.tagName && sibling.tagName.toLowerCase() === tag) {
					index++;
				}
				sibling = sibling.previousElementSibling;
			}
			return tag + ":nth-of-type(" + index + ")";
		}
		function buildInlineSelector(node) {
			var root = node.closest("main, .site-main, #primary, .site-content");
			if (!root) {
				root = document.querySelector("main") || document.body;
			}
			var segments = [];
			var cursor = node;
			while (cursor && cursor !== root && cursor.nodeType === 1) {
				segments.unshift(nodeSegment(cursor));
				cursor = cursor.parentElement;
			}
			var rootPrefix = "main";
			if (root && root.id) {
				rootPrefix = "#" + root.id;
			} else if (root && root.classList && root.classList.length) {
				rootPrefix = root.tagName.toLowerCase() + "." + root.classList[0];
			}
			return rootPrefix + " > " + segments.join(" > ");
		}
		function buildInlineTargets() {
			var nodes = document.querySelectorAll(inlineCandidateSelector);
			if (!nodes || !nodes.length) {
				return;
			}
			nodes.forEach(function(node){
				if (!inlineNodeEligible(node)) return;
				var selector = buildInlineSelector(node);
				if (!selector) return;
				node.setAttribute("data-lf-inline-editable", "1");
				node.setAttribute("data-lf-inline-selector", selector);
				var cls = String(node.className || "");
				if (/\blf-hero-split__subtitle\b/.test(cls) || /\blf-hero-basic__subtitle\b/.test(cls)) {
					node.setAttribute("data-lf-inline-field-key", "hero_subheadline");
				} else {
					node.removeAttribute("data-lf-inline-field-key");
				}
			});
		}
		function inlineImageEligible(img) {
			if (!img || !img.getAttribute) return false;
			if (img.closest(".lf-ai-float,.lf-ai-seo-float")) return false;
			if (img.closest("nav, footer, .site-header, .site-footer, #masthead, #colophon")) return false;
			var src = String(img.getAttribute("src") || "").trim();
			if (src === "") return false;
			var width = img.naturalWidth || img.clientWidth || 0;
			var height = img.naturalHeight || img.clientHeight || 0;
			if (width > 0 && height > 0 && (width < 40 || height < 40)) {
				return false;
			}
			return true;
		}
		function buildInlineImageTargets() {
			var nodes = document.querySelectorAll(inlineImageCandidateSelector);
			if (!nodes || !nodes.length) return;
			nodes.forEach(function(img){
				if (!inlineImageEligible(img)) return;
				var selector = buildInlineSelector(img);
				if (!selector) return;
				img.setAttribute("data-lf-inline-image", "1");
				img.setAttribute("data-lf-inline-image-selector", selector);
			});
		}
		function saveInlineImageOverride(img, attachment) {
			var selector = String(img.getAttribute("data-lf-inline-image-selector") || "");
			if (!selector) {
				setStatus("Invalid image target.", true);
				return;
			}
			var attachmentId = parseInt(String(attachment && attachment.id ? attachment.id : "0"), 10);
			var url = "";
			if (attachment && attachment.sizes && attachment.sizes.large && attachment.sizes.large.url) {
				url = String(attachment.sizes.large.url);
			} else if (attachment && attachment.sizes && attachment.sizes.full && attachment.sizes.full.url) {
				url = String(attachment.sizes.full.url);
			} else if (attachment && attachment.url) {
				url = String(attachment.url);
			}
			if (!attachmentId || !url) {
				setStatus("Unable to use selected image.", true);
				return;
			}
			var alt = String((attachment && attachment.alt) ? attachment.alt : "");
			setStatus("Saving image replacement...", false);
			img.setAttribute("data-lf-inline-image-active", "1");
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_inline_image_save",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				selector: selector,
				attachment_id: attachmentId,
				image_url: url,
				image_alt: alt
			}).done(function(res){
				if (res && res.success) {
					img.setAttribute("src", url);
					img.removeAttribute("srcset");
					img.setAttribute("alt", alt);
					setStatus("Image replaced. Use ↶ / ↷ to undo/redo.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Image replace failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Image replace failed.";
				setStatus(msg, true);
			}).always(function(){
				img.removeAttribute("data-lf-inline-image-active");
			});
		}
		function beginInlineImageReplace(img) {
			if (!img || inlineIsSaving) return;
			if (!(window.wp && wp.media)) {
				setStatus("Media Library is unavailable on this screen.", true);
				return;
			}
			if (mediaFrame) {
				mediaFrame.off("select");
			}
			mediaFrame = wp.media({
				title: "Replace image",
				button: { text: "Use this image" },
				library: { type: "image" },
				multiple: false
			});
			mediaFrame.on("select", function(){
				var selection = mediaFrame.state().get("selection");
				var attachment = selection && selection.first ? selection.first().toJSON() : null;
				if (!attachment) return;
				saveInlineImageOverride(img, attachment);
			});
			mediaFrame.open();
		}
		function collectSectionWrappers() {
			return Array.prototype.slice.call(document.querySelectorAll("[data-lf-section-wrap=\"1\"][data-lf-section-id]"));
		}
		function sectionLabelForWrap(wrap) {
			if (!wrap) return "Section";
			var heading = wrap.querySelector("h1,h2,h3,.lf-section__title,.lf-media-section__title");
			var text = heading ? String(heading.textContent || "").trim() : "";
			if (!text) {
				text = String(wrap.getAttribute("data-lf-section-type") || "section");
			}
			return text.length > 42 ? (text.slice(0, 39) + "...") : text;
		}
		function setSelectedSection(wrap) {
			if (!editingEnabled) {
				selectedSectionWrap = null;
				return;
			}
			collectSectionWrappers().forEach(function(node){
				node.classList.remove("lf-ai-section-active");
			});
			selectedSectionWrap = wrap || null;
			if (selectedSectionWrap) {
				selectedSectionWrap.classList.add("lf-ai-section-active");
				// Self-heal list/pill controls in case a row lost its remove button.
				buildHeroPillsControls();
				buildHeroProofChecklistControls();
				buildTrustBadgePillsControls();
				buildChecklistControls();
				buildProcessStepControls();
				buildSectionMediaEditors();
				buildFaqReorderControls();
				buildBenefitsIconEditors();
				try {
					var sid = String(selectedSectionWrap.getAttribute("data-lf-section-id") || "");
					var stype = String(selectedSectionWrap.getAttribute("data-lf-section-type") || "");
					var label = sectionLabelForWrap(selectedSectionWrap);
					if (sid || stype) {
						setStatus("Selected section target: " + label + " (" + (stype || sid) + ")", false);
					}
				} catch (e) {}
			}
			refreshSectionRail();
		}
		function scrollToSectionWrap(wrap) {
			if (!wrap || !wrap.getBoundingClientRect) return;
			try {
				wrap.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
			} catch (e) {
				// no-op, fallback below
			}
			try {
				var rect = wrap.getBoundingClientRect();
				var pageTop = window.pageYOffset || document.documentElement.scrollTop || 0;
				var desired = pageTop + rect.top - 120;
				if (desired < 0) desired = 0;
				window.scrollTo({ top: desired, behavior: "smooth" });
			} catch (e) {}
		}
		function ensureSectionRail() {
			if (sectionRailEl) return sectionRailEl;
			sectionRailEl = document.createElement("aside");
			sectionRailEl.className = "lf-ai-rail lf-ai-inline-editor-ignore";
			sectionRailEl.innerHTML = "<div class=\"lf-ai-rail__head\"><div class=\"lf-ai-rail__title\">Page Structure</div><div class=\"lf-ai-rail__head-actions\"><button type=\"button\" class=\"lf-ai-rail__add\" data-lf-ai-rail-add aria-label=\"Add section\" title=\"Add section\">+</button><button type=\"button\" class=\"lf-ai-rail__toggle\" data-lf-ai-rail-toggle aria-label=\"Minimize structure rail\" title=\"Minimize structure rail\">\u2212</button></div></div><div class=\"lf-ai-rail__library\" data-lf-ai-rail-library hidden><input type=\"text\" class=\"lf-ai-rail__library-search\" data-lf-ai-rail-library-search placeholder=\"Search sections...\" /><div class=\"lf-ai-rail__library-list\" data-lf-ai-rail-library-list></div></div><div class=\"lf-ai-rail__list\" data-lf-ai-rail-list></div>";
			var toggle = sectionRailEl.querySelector("[data-lf-ai-rail-toggle]");
			var addBtn = sectionRailEl.querySelector("[data-lf-ai-rail-add]");
			var library = sectionRailEl.querySelector("[data-lf-ai-rail-library]");
			var librarySearch = sectionRailEl.querySelector("[data-lf-ai-rail-library-search]");
			if (toggle) {
				toggle.addEventListener("click", function(){
					railCollapsed = !railCollapsed;
					sectionRailEl.classList.toggle("is-collapsed", railCollapsed);
					toggle.textContent = railCollapsed ? "\u2630 Structure" : "\u2212";
					toggle.setAttribute("aria-label", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
					toggle.setAttribute("title", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
					try { window.localStorage.setItem(railStateKey, railCollapsed ? "collapsed" : "expanded"); } catch (e) {}
				});
			}
			if (addBtn && library) {
				addBtn.addEventListener("click", function(){
					railLibraryOpen = !railLibraryOpen;
					library.hidden = !railLibraryOpen;
					if (railLibraryOpen) {
						refreshRailLibrary();
						try { if (librarySearch) librarySearch.focus(); } catch (e) {}
					}
				});
			}
			if (librarySearch) {
				librarySearch.addEventListener("input", refreshRailLibrary);
			}
			document.body.appendChild(sectionRailEl);
			sectionRailEl.classList.toggle("is-collapsed", railCollapsed);
			if (toggle) {
				toggle.textContent = railCollapsed ? "\u2630 Structure" : "\u2212";
				toggle.setAttribute("aria-label", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
				toggle.setAttribute("title", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
			}
			return sectionRailEl;
		}
		function addSectionFromLibrary(sectionType, afterSectionId) {
			var type = String(sectionType || "");
			if (!type) return;
			setStatus("Adding section...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_add_section",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_type: type,
				after_section_id: String(afterSectionId || (selectedSectionWrap ? String(selectedSectionWrap.getAttribute("data-lf-section-id") || "") : ""))
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
					return;
				}
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Section added.", false);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : "Section add failed.";
					setStatus(msg, true);
					try { window.alert(msg); } catch (e) {}
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section add failed.";
				setStatus(msg, true);
				try { window.alert(msg); } catch (e) {}
			});
		}
		function refreshRailLibrary() {
			if (!sectionRailEl) return;
			var library = sectionRailEl.querySelector("[data-lf-ai-rail-library]");
			var list = sectionRailEl.querySelector("[data-lf-ai-rail-library-list]");
			var search = sectionRailEl.querySelector("[data-lf-ai-rail-library-search]");
			if (!library || !list) return;
			var visibleIds = {};
			collectSectionWrappers().forEach(function(w){
				visibleIds[String(w.getAttribute("data-lf-section-id") || "")] = true;
			});
			var q = String(search && search.value ? search.value : "").trim().toLowerCase();
			var rows = Array.isArray(lfAiFloating.section_library) ? lfAiFloating.section_library : [];
			list.innerHTML = "";
			rows.forEach(function(row){
				var id = String(row && row.id ? row.id : "");
				var label = String(row && row.label ? row.label : id);
				if (!id) return;
				if (q && label.toLowerCase().indexOf(q) === -1 && id.toLowerCase().indexOf(q) === -1) return;
				var btn = document.createElement("button");
				btn.type = "button";
				btn.className = "lf-ai-rail__library-item";
				btn.textContent = label + " (" + id + ")";
				btn.setAttribute("draggable", "true");
				btn.setAttribute("data-lf-library-section-type", id);
				btn.addEventListener("dragstart", function(e){
					activeLibraryDragSectionType = id;
					btn.classList.add("is-dragging");
					if (e.dataTransfer) {
						e.dataTransfer.effectAllowed = "copyMove";
						e.dataTransfer.setData("text/plain", id);
					}
				});
				btn.addEventListener("dragend", function(){
					btn.classList.remove("is-dragging");
					activeLibraryDragSectionType = "";
				});
				btn.addEventListener("click", function(){
					addSectionFromLibrary(id);
				});
				list.appendChild(btn);
			});
		}
		function sectionWrapById(sectionId) {
			var id = String(sectionId || "");
			if (!id) return null;
			var wraps = collectSectionWrappers();
			for (var i = 0; i < wraps.length; i++) {
				if (String(wraps[i].getAttribute("data-lf-section-id") || "") === id) {
					return wraps[i];
				}
			}
			return null;
		}
		function refreshSectionRail() {
			if (!editingEnabled) {
				if (sectionRailEl && sectionRailEl.parentNode) {
					sectionRailEl.parentNode.removeChild(sectionRailEl);
				}
				sectionRailEl = null;
				return;
			}
			var wraps = collectSectionWrappers();
			if (!wraps.length) {
				if (sectionRailEl && sectionRailEl.parentNode) {
					sectionRailEl.parentNode.removeChild(sectionRailEl);
				}
				sectionRailEl = null;
				return;
			}
			var rail = ensureSectionRail();
			var list = rail.querySelector("[data-lf-ai-rail-list]");
			if (!list) return;
			function clearRailDropMarkers() {
				Array.prototype.slice.call(list.querySelectorAll(".lf-ai-rail__item.is-drop-before,.lf-ai-rail__item.is-drop-after")).forEach(function(node){
					node.classList.remove("is-drop-before", "is-drop-after");
				});
			}
			function reorderFromRailDrop(sourceId, targetId, after) {
				var dragWrap = sectionWrapById(sourceId);
				var targetWrap = sectionWrapById(targetId);
				if (!dragWrap || !targetWrap || !dragWrap.parentNode) return false;
				if (after) {
					targetWrap.parentNode.insertBefore(dragWrap, targetWrap.nextSibling);
				} else {
					targetWrap.parentNode.insertBefore(dragWrap, targetWrap);
				}
				setSelectedSection(dragWrap);
				persistSectionOrder();
				return true;
			}
			function railRowFromPoint(clientX, clientY) {
				var el = document.elementFromPoint(clientX, clientY);
				if (!el || !el.closest) return null;
				var row = el.closest(".lf-ai-rail__item[data-lf-rail-section-id]");
				if (!row || !list.contains(row)) return null;
				return row;
			}
			list.innerHTML = "";
			wraps.forEach(function(wrap){
				var row = document.createElement("div");
				var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
				var isHidden = String(wrap.getAttribute("data-lf-section-visible") || "1") === "0";
				row.className = "lf-ai-rail__item" + (wrap === selectedSectionWrap ? " is-active" : "");
				row.setAttribute("draggable", "true");
				row.setAttribute("data-lf-rail-section-id", sectionId);
				row.setAttribute("tabindex", "0");
				row.innerHTML = "<div class=\"lf-ai-rail__item-body\">" + escapeHtml(sectionLabelForWrap(wrap)) + "<small>" + escapeHtml(String(wrap.getAttribute("data-lf-section-type") || "section")) + (isHidden ? " • hidden" : "") + "</small></div><span class=\"lf-ai-rail__drag\" data-lf-rail-drag title=\"Drag to reorder\" aria-label=\"Drag to reorder\">⋮⋮</span>";
				row.addEventListener("click", function(){
					if (row.__draggedOnce) {
						row.__draggedOnce = false;
						return;
					}
					setSelectedSection(wrap);
					scrollToSectionWrap(wrap);
				});
				row.addEventListener("focus", function(){
					setSelectedSection(wrap);
				});
				row.addEventListener("keydown", function(e){
					var key = String(e.key || "");
					if (key === "Backspace" || key === "Delete") {
						e.preventDefault();
						setSelectedSection(wrap);
						selectedSectionDelete();
						return;
					}
					if (key === "ArrowUp" && (e.altKey || e.metaKey || e.shiftKey)) {
						e.preventDefault();
						setSelectedSection(wrap);
						moveSelectedSection(-1);
						return;
					}
					if (key === "ArrowDown" && (e.altKey || e.metaKey || e.shiftKey)) {
						e.preventDefault();
						setSelectedSection(wrap);
						moveSelectedSection(1);
						return;
					}
				});
				row.addEventListener("dragstart", function(e){
					// Allow drag from the full row for reliability across browsers.
					activeRailDragSectionId = sectionId;
					row.classList.add("is-dragging");
					if (e.dataTransfer) {
						e.dataTransfer.effectAllowed = "move";
						e.dataTransfer.setData("text/plain", sectionId);
					}
				});
				row.addEventListener("mousedown", function(e){
					if (!e || e.button !== 0) return;
					var target = e.target && e.target.nodeType === 1 ? e.target : null;
					if (!target || (target.closest && target.closest("a,button,input,textarea,select,label"))) {
						return;
					}
					railPointerDrag = { sectionId: sectionId, row: row, targetId: "", after: false };
					activeRailDragSectionId = sectionId;
					row.classList.add("is-dragging");
					document.body.classList.add("lf-ai-rail-dragging");
					e.preventDefault();
				});
				row.addEventListener("dragover", function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
						return;
					}
					if (!activeRailDragSectionId || activeRailDragSectionId === sectionId) return;
					e.preventDefault();
					clearRailDropMarkers();
					var rect = row.getBoundingClientRect();
					var after = e.clientY > (rect.top + rect.height / 2);
					row.classList.add(after ? "is-drop-after" : "is-drop-before");
				});
				row.addEventListener("dragleave", function(){
					row.classList.remove("is-drop-before", "is-drop-after");
				});
				row.addEventListener("drop", function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
						addSectionFromLibrary(activeLibraryDragSectionType, sectionId);
						activeLibraryDragSectionType = "";
						return;
					}
					if (!activeRailDragSectionId || activeRailDragSectionId === sectionId) return;
					e.preventDefault();
					var rect = row.getBoundingClientRect();
					var after = e.clientY > (rect.top + rect.height / 2);
					if (!reorderFromRailDrop(activeRailDragSectionId, sectionId, after)) return;
					row.__draggedOnce = true;
					clearRailDropMarkers();
				});
				row.addEventListener("dragend", function(){
					row.classList.remove("is-dragging");
					activeRailDragSectionId = "";
					railPointerDrag = null;
					document.body.classList.remove("lf-ai-rail-dragging");
					clearRailDropMarkers();
				});
				list.appendChild(row);
			});
			if (!list.__lfRailDropBound) {
				list.__lfRailDropBound = true;
				list.addEventListener("dragover", function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
					}
				});
				list.addEventListener("drop", function(e){
					if (!activeLibraryDragSectionType) return;
					e.preventDefault();
					addSectionFromLibrary(activeLibraryDragSectionType, "");
					activeLibraryDragSectionType = "";
				});
				document.addEventListener("mousemove", function(e){
					if (!railPointerDrag || !railPointerDrag.row) return;
					var row = railRowFromPoint(e.clientX, e.clientY);
					clearRailDropMarkers();
					if (!row) {
						railPointerDrag.targetId = "";
						return;
					}
					var targetId = String(row.getAttribute("data-lf-rail-section-id") || "");
					if (!targetId || targetId === railPointerDrag.sectionId) {
						railPointerDrag.targetId = "";
						return;
					}
					var rect = row.getBoundingClientRect();
					var after = e.clientY > (rect.top + rect.height / 2);
					row.classList.add(after ? "is-drop-after" : "is-drop-before");
					railPointerDrag.targetId = targetId;
					railPointerDrag.after = after;
				});
				document.addEventListener("mouseup", function(){
					if (!railPointerDrag || !railPointerDrag.row) return;
					var didReorder = false;
					if (railPointerDrag.targetId && railPointerDrag.targetId !== railPointerDrag.sectionId) {
						didReorder = reorderFromRailDrop(railPointerDrag.sectionId, railPointerDrag.targetId, !!railPointerDrag.after);
					}
					if (didReorder) {
						railPointerDrag.row.__draggedOnce = true;
					}
					railPointerDrag.row.classList.remove("is-dragging");
					railPointerDrag = null;
					activeRailDragSectionId = "";
					document.body.classList.remove("lf-ai-rail-dragging");
					clearRailDropMarkers();
				});
			}
		}
		function moveSelectedSection(delta) {
			if (!selectedSectionWrap || !delta) return;
			var wraps = collectSectionWrappers();
			var idx = wraps.indexOf(selectedSectionWrap);
			if (idx < 0) return;
			var targetIdx = idx + delta;
			if (targetIdx < 0 || targetIdx >= wraps.length) return;
			var target = wraps[targetIdx];
			if (!target || !target.parentNode) return;
			if (delta < 0) {
				target.parentNode.insertBefore(selectedSectionWrap, target);
			} else {
				target.parentNode.insertBefore(selectedSectionWrap, target.nextSibling);
			}
			persistSectionOrder();
			refreshSectionRail();
		}
		function sectionSupportsDuplicate(sectionType, sectionId) {
			var type = String(sectionType || "");
			if (type === "" || type === "hero" || type === "hero_1") {
				return false;
			}
			return true;
		}
		function baseSectionType(sectionType, sectionId) {
			var type = String(sectionType || "").trim();
			var id = String(sectionId || "").trim();
			var heroLike = function(value) {
				return /^hero(?:_\d+)?$/i.test(String(value || ""));
			};
			if (heroLike(type) || heroLike(id)) return "hero";
			return type !== "" ? type : id;
		}
		function sectionSupportsColumnSwap(sectionType) {
			var type = String(sectionType || "");
			return ["service_details", "content_image", "content_image_a", "image_content", "image_content_b", "content_image_c"].indexOf(type) !== -1;
		}
		function sectionSupportsChecklistEditor(sectionType) {
			var type = String(sectionType || "");
			return ["service_details", "content_image", "content_image_a", "image_content", "image_content_b", "content_image_c"].indexOf(type) !== -1;
		}
		function checklistItemsFromWrap(wrap) {
			var list = wrap ? wrap.querySelector(".lf-service-details__checklist") : null;
			if (!list) return [];
			return Array.prototype.slice.call(list.querySelectorAll("li")).map(function(li){
				var textNode = li.querySelector(".lf-service-details__text");
				var raw = textNode ? String(textNode.textContent || "") : String(li.textContent || "");
				return raw.trim();
			}).filter(function(value){ return value !== ""; });
		}
		function persistSectionChecklist(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			if (!sectionId || !sectionSupportsChecklistEditor(sectionType)) return;
			var items = checklistItemsFromWrap(wrap);
			setStatus("Saving checklist...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_checklist",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				items: JSON.stringify(items)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Checklist saved.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Checklist save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Checklist save failed.";
				setStatus(msg, true);
			});
		}
		function buildChecklistControls() {
			function focusNodeEnd(node) {
				if (!node || !window.getSelection || !document.createRange) return;
				try {
					var range = document.createRange();
					range.selectNodeContents(node);
					range.collapse(false);
					var sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(range);
				} catch (e) {}
			}
			function checklistTextNodeFromLi(li) {
				if (!li) return null;
				var textNode = ensureManagedListItemTextNode(li, ".lf-service-details__text", "lf-service-details__text");
				textNode.removeAttribute("data-lf-inline-editable");
				textNode.removeAttribute("data-lf-inline-selector");
				li.removeAttribute("data-lf-inline-editable");
				li.removeAttribute("data-lf-inline-selector");
				return textNode;
			}
			function finishChecklistTextEdit(textNode, li, wrap, commit) {
				if (!textNode) return;
				var original = String(textNode.getAttribute("data-lf-ai-original-text") || "").trim();
				var next = String(textNode.textContent || "").replace(/\s+/g, " ").trim();
				textNode.removeAttribute("contenteditable");
				textNode.removeAttribute("spellcheck");
				textNode.removeAttribute("data-lf-ai-editing");
				textNode.removeAttribute("data-lf-ai-original-text");
				if (!commit) {
					textNode.textContent = original || next || "Checklist item";
					return;
				}
				if (next === "") {
					textNode.textContent = original || "Checklist item";
					return;
				}
				textNode.textContent = next;
				if (next !== original) {
					persistSectionChecklist(wrap);
				}
			}
			function startChecklistTextEdit(textNode, li, wrap) {
				if (!textNode) return;
				if (String(textNode.getAttribute("data-lf-ai-editing") || "0") === "1") return;
				textNode.setAttribute("data-lf-ai-original-text", String(textNode.textContent || "").trim());
				textNode.setAttribute("data-lf-ai-editing", "1");
				textNode.setAttribute("contenteditable", "true");
				textNode.setAttribute("spellcheck", "true");
				try { textNode.focus(); } catch (e) {}
				focusNodeEnd(textNode);
			}
			function bindChecklistItemEditor(li, wrap) {
				if (!li || !wrap) return;
				var textNode = checklistTextNodeFromLi(li);
				if (!textNode) return;
				textNode.classList.add("lf-ai-inline-editor-ignore");
				textNode.setAttribute("title", "Click to edit checklist item");
				textNode.onmousedown = function(e){
					if (e) e.stopPropagation();
				};
				textNode.onclick = function(e){
					if (!editingEnabled) return;
					if (e) {
						e.preventDefault();
						e.stopPropagation();
					}
					startChecklistTextEdit(textNode, li, wrap);
				};
				textNode.onblur = function(){
					finishChecklistTextEdit(textNode, li, wrap, true);
				};
				textNode.onkeydown = function(e){
					var key = String((e && e.key) || "");
					if (key === "Enter") {
						e.preventDefault();
						finishChecklistTextEdit(textNode, li, wrap, true);
						return;
					}
					if (key === "Escape") {
						e.preventDefault();
						finishChecklistTextEdit(textNode, li, wrap, false);
					}
				};
			}
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-checklist-controls=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-checklist-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (!sectionSupportsChecklistEditor(sectionType)) return;
				var content = wrap.querySelector(".lf-service-details__content");
				if (!content) return;
				var list = wrap.querySelector(".lf-service-details__checklist");
				if (!list) {
					list = document.createElement("ul");
					list.className = "lf-service-details__checklist";
					list.setAttribute("role", "list");
					content.appendChild(list);
				}
				Array.prototype.slice.call(list.querySelectorAll("li")).forEach(function(li){
					var textNode = checklistTextNodeFromLi(li);
					Array.prototype.slice.call(li.querySelectorAll("[data-lf-ai-checklist-remove=\"1\"]")).forEach(function(node){
						if (node && node.parentNode) node.parentNode.removeChild(node);
					});
					var removeBtn = document.createElement("button");
					removeBtn.type = "button";
					removeBtn.className = "lf-ai-checklist-remove lf-ai-inline-editor-ignore";
					removeBtn.setAttribute("data-lf-ai-checklist-remove", "1");
					removeBtn.textContent = "x";
					removeBtn.setAttribute("title", "Remove checklist item");
					removeBtn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						if (li && li.parentNode) {
							li.parentNode.removeChild(li);
						}
						persistSectionChecklist(wrap);
					});
					li.appendChild(removeBtn);
					bindChecklistItemEditor(li, wrap);
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-checklist-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-checklist-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-checklist-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Add item";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var li = document.createElement("li");
					var textNode = document.createElement("span");
					textNode.className = "lf-service-details__text";
					textNode.textContent = "New checklist item";
					li.appendChild(textNode);
					var removeBtn = document.createElement("button");
					removeBtn.type = "button";
					removeBtn.className = "lf-ai-checklist-remove lf-ai-inline-editor-ignore";
					removeBtn.setAttribute("data-lf-ai-checklist-remove", "1");
					removeBtn.textContent = "x";
					removeBtn.setAttribute("title", "Remove checklist item");
					removeBtn.addEventListener("click", function(ev){
						ev.preventDefault();
						ev.stopPropagation();
						if (li && li.parentNode) {
							li.parentNode.removeChild(li);
						}
						persistSectionChecklist(wrap);
					});
					li.appendChild(removeBtn);
					list.appendChild(li);
					bindChecklistItemEditor(li, wrap);
					persistSectionChecklist(wrap);
					startChecklistTextEdit(textNode, li, wrap);
				});
				controls.appendChild(addBtn);
				content.appendChild(controls);
			});
		}
		function heroPillsFromWrap(wrap) {
			var list = wrap ? wrap.querySelector(".lf-hero-chips") : null;
			if (!list) return [];
			function cleanPillText(raw) {
				var text = String(raw || "").replace(/\s+/g, " ").trim();
				text = text.replace(/\s*[xX×]\s*$/, "").trim();
				return text;
			}
			return Array.prototype.slice.call(list.querySelectorAll(".lf-hero-chip")).map(function(chip){
				var textNode = chip.querySelector("[data-lf-hero-pill-text]");
				var raw = textNode ? String(textNode.textContent || "") : String(chip.textContent || "");
				return cleanPillText(raw);
			}).filter(function(value){ return value !== ""; });
		}
		function persistHeroPills(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			var baseType = baseSectionType(sectionType, sectionId);
			if (!sectionId && baseType === "hero") {
				sectionId = "hero";
			}
			if (!sectionId || baseType !== "hero") return;
			var items = heroPillsFromWrap(wrap);
			syncHeroListsFromItems(wrap, items, "chips");
			setStatus("Saving hero pills...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_hero_pills",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				items: JSON.stringify(items)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Hero pills saved.", false);
				} else {
					persistSectionLineItems(wrap, "hero_proof_bullets", items, "Saving checklist...");
				}
			}).fail(function(xhr){
				persistSectionLineItems(wrap, "hero_proof_bullets", items, "Saving checklist...");
			});
		}
		function textFromNodeWithoutAiControls(node) {
			if (!node) return "";
			var clone = node.cloneNode(true);
			Array.prototype.slice.call(clone.querySelectorAll("[data-lf-ai-hero-pill-remove=\"1\"],[data-lf-ai-checklist-remove=\"1\"],[data-lf-ai-list-remove=\"1\"],.lf-ai-inline-editor-ignore")).forEach(function(btn){
				if (btn && btn.parentNode) btn.parentNode.removeChild(btn);
			});
			return String(clone.textContent || "").replace(/\s+/g, " ").trim();
		}
		function ensureManagedListItemTextNode(li, selector, className) {
			if (!li) return null;
			var textNode = li.querySelector(selector);
			var normalized = textFromNodeWithoutAiControls(li);
			if (!textNode) {
				textNode = document.createElement("span");
				textNode.className = className;
				li.insertBefore(textNode, li.firstChild || null);
			}
			textNode.textContent = normalized || String(textNode.textContent || "").trim();
			Array.prototype.slice.call(li.childNodes || []).forEach(function(child){
				if (!child || child === textNode) return;
				if (child.nodeType === 3 && String(child.textContent || "").trim() !== "") {
					try { li.removeChild(child); } catch (e) {}
				}
			});
			return textNode;
		}
		function persistSectionLineItems(wrap, fieldKey, items, savingLabel) {
			if (!wrap || !fieldKey) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			var lines = Array.isArray(items) ? items.map(function(v){ return String(v || "").trim(); }).filter(function(v){ return v !== ""; }) : [];
			setStatus(savingLabel || "Saving list...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_lines",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				field_key: String(fieldKey),
				items: JSON.stringify(lines)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "List saved.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "List save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "List save failed.";
				setStatus(msg, true);
			});
		}
		function simpleListItemsFromContainer(container, itemSelector) {
			if (!container) return [];
			return Array.prototype.slice.call(container.querySelectorAll(itemSelector)).map(function(node){
				return textFromNodeWithoutAiControls(node);
			}).filter(function(text){ return text !== ""; });
		}
		function syncHeroListsFromItems(wrap, items, source) {
			if (!wrap) return;
			var normalizedItems = Array.isArray(items)
				? items.map(function(v){ return String(v || "").replace(/\s+/g, " ").trim(); }).filter(function(v){ return v !== ""; })
				: [];
			var chipsList = wrap.querySelector(".lf-hero-chips");
			var proofList = wrap.querySelector(".lf-block-hero__card-list");
			if (source !== "chips" && chipsList) {
				chipsList.innerHTML = "";
				normalizedItems.forEach(function(item){
					var chip = document.createElement("li");
					chip.className = "lf-hero-chip";
					var textNode = document.createElement("span");
					textNode.setAttribute("data-lf-hero-pill-text", "1");
					textNode.textContent = item;
					chip.appendChild(textNode);
					chipsList.appendChild(chip);
				});
			}
			if (source !== "proof" && proofList) {
				proofList.innerHTML = "";
				normalizedItems.forEach(function(item){
					var li = document.createElement("li");
					var textNode = document.createElement("span");
					textNode.className = "lf-block-hero__card-item-text";
					textNode.textContent = item;
					li.appendChild(textNode);
					proofList.appendChild(li);
				});
			}
			buildHeroPillsControls();
			buildHeroProofChecklistControls();
		}
		function persistHeroProofItems(wrap, list) {
			if (!wrap || !list) return;
			var items = simpleListItemsFromContainer(list, "li");
			syncHeroListsFromItems(wrap, items, "proof");
			setStatus("Saving checklist...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_hero_pills",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: String(wrap.getAttribute("data-lf-section-id") || ""),
				items: JSON.stringify(items)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Checklist saved.", false);
				} else {
					persistSectionLineItems(wrap, "hero_proof_bullets", items, "Saving checklist...");
				}
			}).fail(function(){
				persistSectionLineItems(wrap, "hero_proof_bullets", items, "Saving checklist...");
			});
		}
		function createGenericRemoveButton(onClick) {
			var btn = document.createElement("button");
			btn.type = "button";
			btn.className = "lf-ai-list-remove lf-ai-inline-editor-ignore";
			btn.setAttribute("data-lf-ai-list-remove", "1");
			btn.setAttribute("title", "Remove item");
			btn.textContent = "x";
			btn.addEventListener("click", function(e){
				e.preventDefault();
				e.stopPropagation();
				if (typeof onClick === "function") onClick();
			});
			return btn;
		}
		function buildHeroPillsControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-hero-pills-controls=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-hero-pill-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
				if (baseSectionType(sectionType, sectionId) !== "hero") return;
				var list = wrap.querySelector(".lf-hero-chips");
				if (!list) return;
				Array.prototype.slice.call(list.querySelectorAll(".lf-hero-chip,[data-lf-hero-pill-text]")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-hero-chip")).forEach(function(chip){
					var textNode = chip.querySelector("[data-lf-hero-pill-text]");
					var normalized = textFromNodeWithoutAiControls(chip);
					if (!textNode) {
						textNode = document.createElement("span");
						textNode.setAttribute("data-lf-hero-pill-text", "1");
						chip.textContent = "";
						chip.appendChild(textNode);
					}
					textNode.textContent = normalized || String(textNode.textContent || "").trim();
					Array.prototype.slice.call(chip.childNodes || []).forEach(function(child){
						if (!child || child === textNode) return;
						if (child.nodeType === 3 && String(child.textContent || "").trim() !== "") {
							try { chip.removeChild(child); } catch (e) {}
						}
					});
					if (!chip.querySelector("[data-lf-ai-hero-pill-remove=\"1\"]")) {
						var removeBtn = document.createElement("button");
						removeBtn.type = "button";
						removeBtn.className = "lf-ai-hero-pill-remove lf-ai-inline-editor-ignore";
						removeBtn.setAttribute("data-lf-ai-hero-pill-remove", "1");
						removeBtn.setAttribute("title", "Remove pill");
						removeBtn.textContent = "x";
						removeBtn.addEventListener("click", function(e){
							e.preventDefault();
							e.stopPropagation();
							if (chip && chip.parentNode) {
								chip.parentNode.removeChild(chip);
							}
							persistHeroPills(wrap);
						});
						chip.appendChild(removeBtn);
					}
					chip.ondblclick = function(e){
						e.preventDefault();
						e.stopPropagation();
						if (chip && chip.parentNode) {
							chip.parentNode.removeChild(chip);
						}
						persistHeroPills(wrap);
					};
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-hero-pills-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-hero-pills-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-hero-pill-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Add pill";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var text = "";
					try {
						text = String(window.prompt("New pill text:", "") || "").trim();
					} catch (err) {
						text = "";
					}
					if (!text) return;
					var tag = list.tagName && list.tagName.toLowerCase() === "ul" ? "li" : "span";
					var chip = document.createElement(tag);
					chip.className = "lf-hero-chip";
					var textNode = document.createElement("span");
					textNode.setAttribute("data-lf-hero-pill-text", "1");
					textNode.textContent = text;
					chip.appendChild(textNode);
					var removeBtn = document.createElement("button");
					removeBtn.type = "button";
					removeBtn.className = "lf-ai-hero-pill-remove lf-ai-inline-editor-ignore";
					removeBtn.setAttribute("data-lf-ai-hero-pill-remove", "1");
					removeBtn.setAttribute("title", "Remove pill");
					removeBtn.textContent = "x";
					removeBtn.addEventListener("click", function(ev){
						ev.preventDefault();
						ev.stopPropagation();
						if (chip && chip.parentNode) {
							chip.parentNode.removeChild(chip);
						}
						persistHeroPills(wrap);
					});
					chip.appendChild(removeBtn);
					chip.ondblclick = function(ev2){
						ev2.preventDefault();
						ev2.stopPropagation();
						if (chip && chip.parentNode) {
							chip.parentNode.removeChild(chip);
						}
						persistHeroPills(wrap);
					};
					list.appendChild(chip);
					persistHeroPills(wrap);
				});
				controls.appendChild(addBtn);
				var parent = list.parentNode;
				if (parent) {
					parent.appendChild(controls);
				}
			});
		}
		function buildHeroProofChecklistControls() {
			function focusNodeEnd(node) {
				if (!node || !window.getSelection || !document.createRange) return;
				try {
					var range = document.createRange();
					range.selectNodeContents(node);
					range.collapse(false);
					var sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(range);
				} catch (e) {}
			}
			function heroProofTextNodeFromLi(li) {
				if (!li) return null;
				var textNode = ensureManagedListItemTextNode(li, ".lf-block-hero__card-item-text", "lf-block-hero__card-item-text");
				textNode.removeAttribute("data-lf-inline-editable");
				textNode.removeAttribute("data-lf-inline-selector");
				li.removeAttribute("data-lf-inline-editable");
				li.removeAttribute("data-lf-inline-selector");
				return textNode;
			}
			function finishHeroProofTextEdit(textNode, wrap, list, commit) {
				if (!textNode || !wrap || !list) return;
				var original = String(textNode.getAttribute("data-lf-ai-original-text") || "").trim();
				var next = String(textNode.textContent || "").replace(/\s+/g, " ").trim();
				textNode.removeAttribute("contenteditable");
				textNode.removeAttribute("spellcheck");
				textNode.removeAttribute("data-lf-ai-editing");
				textNode.removeAttribute("data-lf-ai-original-text");
				if (!commit) {
					textNode.textContent = original || next || "New item";
					return;
				}
				if (next === "") {
					textNode.textContent = original || "New item";
					return;
				}
				textNode.textContent = next;
				if (next !== original) {
					persistHeroProofItems(wrap, list);
				}
			}
			function startHeroProofTextEdit(textNode) {
				if (!textNode) return;
				if (String(textNode.getAttribute("data-lf-ai-editing") || "0") === "1") return;
				textNode.setAttribute("data-lf-ai-original-text", String(textNode.textContent || "").trim());
				textNode.setAttribute("data-lf-ai-editing", "1");
				textNode.setAttribute("contenteditable", "true");
				textNode.setAttribute("spellcheck", "true");
				try { textNode.focus(); } catch (e) {}
				focusNodeEnd(textNode);
			}
			function bindHeroProofItemEditor(li, wrap, list) {
				if (!li || !wrap || !list) return;
				var textNode = heroProofTextNodeFromLi(li);
				if (!textNode) return;
				textNode.classList.add("lf-ai-inline-editor-ignore");
				textNode.setAttribute("title", "Click to edit checklist item");
				textNode.onmousedown = function(e){
					if (e) e.stopPropagation();
				};
				textNode.onclick = function(e){
					if (!editingEnabled) return;
					if (e) {
						e.preventDefault();
						e.stopPropagation();
					}
					startHeroProofTextEdit(textNode);
				};
				textNode.onblur = function(){
					finishHeroProofTextEdit(textNode, wrap, list, true);
				};
				textNode.onkeydown = function(e){
					var key = String((e && e.key) || "");
					if (key === "Enter") {
						e.preventDefault();
						finishHeroProofTextEdit(textNode, wrap, list, true);
						return;
					}
					if (key === "Escape") {
						e.preventDefault();
						finishHeroProofTextEdit(textNode, wrap, list, false);
					}
				};
			}
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
				if (baseSectionType(sectionType, sectionId) !== "hero") return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-hero-proof-controls=\"1\"],[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var list = wrap.querySelector(".lf-block-hero__card-list");
				if (!list) return;
				Array.prototype.slice.call(list.querySelectorAll("li")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll("li")).forEach(function(li){
					heroProofTextNodeFromLi(li);
					Array.prototype.slice.call(li.querySelectorAll("[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
						if (node && node.parentNode) node.parentNode.removeChild(node);
					});
					var btn = createGenericRemoveButton(function(){
						if (li && li.parentNode) li.parentNode.removeChild(li);
						persistHeroProofItems(wrap, list);
					});
					li.appendChild(btn);
					bindHeroProofItemEditor(li, wrap, list);
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-checklist-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-hero-proof-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-checklist-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Add item";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var li = document.createElement("li");
					var textNode = document.createElement("span");
					textNode.className = "lf-block-hero__card-item-text";
					textNode.textContent = "New item";
					li.appendChild(textNode);
					li.appendChild(createGenericRemoveButton(function(){
						if (li && li.parentNode) li.parentNode.removeChild(li);
						persistHeroProofItems(wrap, list);
					}));
					list.appendChild(li);
					bindHeroProofItemEditor(li, wrap, list);
					persistHeroProofItems(wrap, list);
					startHeroProofTextEdit(textNode);
				});
				controls.appendChild(addBtn);
				if (list.parentNode) {
					list.parentNode.appendChild(controls);
				}
			});
		}
		function buildTrustBadgePillsControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (sectionType !== "trust_bar") return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-trust-pill-controls=\"1\"],[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var list = wrap.querySelector(".lf-trust-bar__badges");
				if (!list) return;
				Array.prototype.slice.call(list.querySelectorAll(".lf-trust-bar__badge")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-trust-bar__badge")).forEach(function(badge){
					var btn = createGenericRemoveButton(function(){
						if (badge && badge.parentNode) badge.parentNode.removeChild(badge);
						persistSectionLineItems(wrap, "trust_badges", simpleListItemsFromContainer(list, ".lf-trust-bar__badge"), "Saving badges...");
					});
					badge.appendChild(btn);
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-hero-pills-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-trust-pill-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-hero-pill-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Add pill";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var text = "";
					try { text = String(window.prompt("New badge text:", "") || "").trim(); } catch (err) { text = ""; }
					if (!text) return;
					var badge = document.createElement("span");
					badge.className = "lf-trust-bar__badge";
					badge.textContent = text;
					badge.appendChild(createGenericRemoveButton(function(){
						if (badge && badge.parentNode) badge.parentNode.removeChild(badge);
						persistSectionLineItems(wrap, "trust_badges", simpleListItemsFromContainer(list, ".lf-trust-bar__badge"), "Saving badges...");
					}));
					list.appendChild(badge);
					persistSectionLineItems(wrap, "trust_badges", simpleListItemsFromContainer(list, ".lf-trust-bar__badge"), "Saving badges...");
				});
				controls.appendChild(addBtn);
				if (list.parentNode) {
					list.parentNode.appendChild(controls);
				}
			});
		}
		function processStepValueFromLi(li) {
			if (!li) return "";
			var title = li.querySelector(".lf-process__step-title");
			var body = li.querySelector(".lf-process__step-body");
			if (title) {
				var titleText = String(title.textContent || "").trim();
				var bodyText = body ? String(body.textContent || "").trim() : "";
				return bodyText ? (titleText + " || " + bodyText) : titleText;
			}
			var plain = li.querySelector(".lf-process__text");
			return plain ? String(plain.textContent || "").trim() : textFromNodeWithoutAiControls(li);
		}
		function processValuesFromList(list) {
			if (!list) return [];
			return Array.prototype.slice.call(list.querySelectorAll(".lf-process__step")).map(function(li){
				return processStepValueFromLi(li);
			}).filter(function(v){ return v !== ""; });
		}
		function processPromptEditStep(li, wrap, list) {
			if (!li || !wrap || !list) return;
			var current = processStepValueFromLi(li);
			var next = "";
			try {
				next = String(window.prompt("Edit step text (use \"Title || Body\" for two-line step):", current) || "").trim();
			} catch (err) {
				next = "";
			}
			if (!next) return;
			var removeBtn = li.querySelector("[data-lf-ai-list-remove=\"1\"]");
			Array.prototype.slice.call(li.querySelectorAll(".lf-process__step-title,.lf-process__step-body,.lf-process__text")).forEach(function(node){
				if (node && node.parentNode) node.parentNode.removeChild(node);
			});
			var parts = next.split("||");
			if (parts.length > 1) {
				var title = document.createElement("span");
				title.className = "lf-process__step-title";
				title.textContent = String(parts[0] || "").trim();
				li.insertBefore(title, removeBtn || null);
				var bodyText = String(parts.slice(1).join("||") || "").trim();
				if (bodyText) {
					var body = document.createElement("span");
					body.className = "lf-process__step-body";
					body.textContent = bodyText;
					li.insertBefore(body, removeBtn || null);
				}
			} else {
				var plain = document.createElement("span");
				plain.className = "lf-process__text";
				plain.textContent = next;
				li.insertBefore(plain, removeBtn || null);
			}
			persistSectionLineItems(wrap, "process_steps", processValuesFromList(list), "Saving process steps...");
		}
		function buildProcessStepControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (sectionType !== "process") return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-process-controls=\"1\"],[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				var list = wrap.querySelector(".lf-process");
				if (!list) return;
				Array.prototype.slice.call(list.querySelectorAll(".lf-process__step,.lf-process__step-title,.lf-process__step-body,.lf-process__text")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-process__step")).forEach(function(li){
					li.classList.add("lf-ai-process-step");
					li.setAttribute("draggable", "true");
					li.ondragstart = function(e){
						activeProcessDragEl = li;
						li.classList.add("is-dragging");
						if (e.dataTransfer) {
							e.dataTransfer.effectAllowed = "move";
							e.dataTransfer.setData("text/plain", "process-step");
						}
					};
					li.ondragover = function(e){
						if (!activeProcessDragEl || activeProcessDragEl === li) return;
						e.preventDefault();
					};
					li.ondrop = function(e){
						if (!activeProcessDragEl || activeProcessDragEl === li) return;
						e.preventDefault();
						var rect = li.getBoundingClientRect();
						var after = e.clientY > (rect.top + rect.height / 2);
						if (after) {
							li.parentNode.insertBefore(activeProcessDragEl, li.nextSibling);
						} else {
							li.parentNode.insertBefore(activeProcessDragEl, li);
						}
						persistSectionLineItems(wrap, "process_steps", processValuesFromList(list), "Saving process steps...");
					};
					li.ondragend = function(){
						li.classList.remove("is-dragging");
						activeProcessDragEl = null;
					};
					li.appendChild(createGenericRemoveButton(function(){
						if (li && li.parentNode) li.parentNode.removeChild(li);
						persistSectionLineItems(wrap, "process_steps", processValuesFromList(list), "Saving process steps...");
					}));
					li.setAttribute("title", "Double-click to edit step text");
					li.ondblclick = function(e){
						var target = e && e.target && e.target.nodeType === 1 ? e.target : null;
						if (target && target.closest && target.closest("[data-lf-ai-list-remove=\"1\"]")) {
							return;
						}
						e.preventDefault();
						e.stopPropagation();
						processPromptEditStep(li, wrap, list);
					};
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-checklist-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-process-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-checklist-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Add step";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var li = document.createElement("li");
					li.className = "lf-process__step";
					var text = document.createElement("span");
					text.className = "lf-process__text";
					text.textContent = "New step";
					li.appendChild(text);
					list.appendChild(li);
					buildProcessStepControls();
					processPromptEditStep(li, wrap, list);
				});
				controls.appendChild(addBtn);
				if (list.parentNode) {
					list.parentNode.insertBefore(controls, list.nextSibling);
				}
			});
		}
		function faqIdsFromList(list) {
			if (!list) return [];
			return Array.prototype.slice.call(list.querySelectorAll(".lf-block-faq-accordion__item[data-lf-faq-id]")).map(function(node){
				return parseInt(String(node.getAttribute("data-lf-faq-id") || "0"), 10);
			}).filter(function(id){ return id > 0; });
		}
		function persistFaqSelection(list, savingLabel) {
			if (!list) return;
			var wrap = list.closest("[data-lf-section-wrap=\"1\"]");
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			if (!sectionId || sectionType !== "faq_accordion") return;
			var ids = faqIdsFromList(list);
			setStatus(savingLabel || "Saving selected FAQs...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_lines",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				field_key: "faq_selected_ids",
				items: JSON.stringify(ids.map(function(id){ return String(id); }))
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Selected FAQs saved.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "FAQ selection save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "FAQ selection save failed.";
				setStatus(msg, true);
			});
		}
		function closeFaqPicker() {
			if (!faqPickerEl) return;
			faqPickerEl.hidden = true;
			faqPickerWrap = null;
			faqPickerList = null;
			try { if (faqPickerSearchEl) faqPickerSearchEl.value = ""; } catch (e) {}
		}
		function addFaqItemToList(list, row) {
			if (!list || !row) return;
			var faqId = parseInt(String(row.id || "0"), 10);
			if (!faqId) return;
			var exists = list.querySelector(".lf-block-faq-accordion__item[data-lf-faq-id=\"" + faqId + "\"]");
			if (exists) return;
			var item = document.createElement("details");
			item.className = "lf-block-faq-accordion__item";
			item.setAttribute("data-lf-faq-id", String(faqId));
			var q = document.createElement("summary");
			q.className = "lf-block-faq-accordion__question";
			q.textContent = String(row.question || "FAQ");
			var a = document.createElement("div");
			a.className = "lf-block-faq-accordion__answer";
			a.textContent = String(row.answer || "");
			item.appendChild(q);
			item.appendChild(a);
			list.appendChild(item);
			buildFaqReorderControls();
			persistFaqSelection(list, "Saving selected FAQs...");
		}
		function renderFaqPickerList(query) {
			if (!faqPickerListEl) return;
			var rows = Array.isArray(faqLibraryCache) ? faqLibraryCache : [];
			var q = String(query || "").trim().toLowerCase();
			var selectedMap = {};
			if (faqPickerList) {
				faqIdsFromList(faqPickerList).forEach(function(id){
					selectedMap[String(id)] = true;
				});
			}
			faqPickerListEl.innerHTML = "";
			var filtered = rows.filter(function(row){
				var text = (String(row.question || "") + " " + String(row.answer || "")).toLowerCase();
				return !q || text.indexOf(q) !== -1;
			});
			if (!filtered.length) {
				var empty = document.createElement("div");
				empty.className = "lf-ai-faq-picker__empty";
				empty.textContent = "No FAQs match this search.";
				faqPickerListEl.appendChild(empty);
				return;
			}
			filtered.forEach(function(row){
				var item = document.createElement("div");
				item.className = "lf-ai-faq-picker__item";
				var meta = document.createElement("div");
				meta.className = "lf-ai-faq-picker__meta";
				var title = document.createElement("b");
				title.textContent = String(row.question || "FAQ");
				var preview = document.createElement("small");
				var previewText = String(row.answer || "").replace(/\s+/g, " ").trim();
				preview.textContent = previewText.length > 140 ? (previewText.slice(0, 137) + "...") : previewText;
				meta.appendChild(title);
				meta.appendChild(preview);
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-faq-picker__add lf-ai-inline-editor-ignore";
				var selected = !!selectedMap[String(row.id || "")];
				addBtn.textContent = selected ? "Added" : "Add";
				addBtn.disabled = selected;
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					if (!faqPickerList) return;
					addFaqItemToList(faqPickerList, row);
					renderFaqPickerList(faqPickerSearchEl ? faqPickerSearchEl.value : "");
				});
				item.appendChild(meta);
				item.appendChild(addBtn);
				faqPickerListEl.appendChild(item);
			});
		}
		function loadFaqLibrary(done) {
			if (Array.isArray(faqLibraryCache)) {
				if (typeof done === "function") done(faqLibraryCache);
				return;
			}
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_faq_library",
				nonce: lfAiFloating.nonce
			}).done(function(res){
				faqLibraryCache = (res && res.success && res.data && Array.isArray(res.data.items)) ? res.data.items : [];
				if (typeof done === "function") done(faqLibraryCache);
			}).fail(function(){
				faqLibraryCache = [];
				setStatus("FAQ library unavailable right now.", true);
				if (typeof done === "function") done(faqLibraryCache);
			});
		}
		function ensureFaqPicker() {
			if (faqPickerEl) return faqPickerEl;
			faqPickerEl = document.createElement("div");
			faqPickerEl.className = "lf-ai-faq-picker lf-ai-inline-editor-ignore";
			faqPickerEl.hidden = true;
			faqPickerEl.innerHTML = "<div class=\"lf-ai-faq-picker__card\"><div class=\"lf-ai-faq-picker__head\"><div class=\"lf-ai-faq-picker__title\">Select FAQs from library</div><button type=\"button\" class=\"lf-ai-faq-picker__close\" data-lf-ai-faq-picker-close aria-label=\"Close FAQ picker\">×</button></div><input type=\"text\" class=\"lf-ai-faq-picker__search\" data-lf-ai-faq-picker-search placeholder=\"Search FAQs...\" /><div class=\"lf-ai-faq-picker__list\" data-lf-ai-faq-picker-list></div></div>";
			faqPickerSearchEl = faqPickerEl.querySelector("[data-lf-ai-faq-picker-search]");
			faqPickerListEl = faqPickerEl.querySelector("[data-lf-ai-faq-picker-list]");
			var closeBtn = faqPickerEl.querySelector("[data-lf-ai-faq-picker-close]");
			if (closeBtn) {
				closeBtn.addEventListener("click", function(e){
					e.preventDefault();
					closeFaqPicker();
				});
			}
			if (faqPickerSearchEl) {
				faqPickerSearchEl.addEventListener("input", function(){
					renderFaqPickerList(faqPickerSearchEl.value);
				});
			}
			faqPickerEl.addEventListener("click", function(e){
				if (e.target === faqPickerEl) closeFaqPicker();
			});
			document.body.appendChild(faqPickerEl);
			return faqPickerEl;
		}
		function openFaqPicker(wrap, list) {
			if (!wrap || !list) return;
			ensureFaqPicker();
			faqPickerWrap = wrap;
			faqPickerList = list;
			faqPickerEl.hidden = false;
			if (faqPickerSearchEl) faqPickerSearchEl.value = "";
			loadFaqLibrary(function(){
				renderFaqPickerList("");
				try { if (faqPickerSearchEl) faqPickerSearchEl.focus(); } catch (e) {}
			});
		}
		function buildFaqReorderControls() {
			Array.prototype.slice.call(document.querySelectorAll(".lf-block-faq-accordion__list")).forEach(function(list){
				var wrap = list.closest("[data-lf-section-wrap=\"1\"]");
				var sectionType = String(wrap && wrap.getAttribute ? (wrap.getAttribute("data-lf-section-type") || "") : "");
				if (!wrap || sectionType !== "faq_accordion") return;
				Array.prototype.slice.call(wrap.querySelectorAll("[data-lf-ai-faq-controls=\"1\"]")).forEach(function(node){
					if (node && node.parentNode) node.parentNode.removeChild(node);
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-block-faq-accordion__item,.lf-block-faq-accordion__question,.lf-block-faq-accordion__answer")).forEach(function(node){
					node.removeAttribute("data-lf-inline-editable");
					node.removeAttribute("data-lf-inline-selector");
				});
				Array.prototype.slice.call(list.querySelectorAll(".lf-block-faq-accordion__item[data-lf-faq-id]")).forEach(function(item){
					Array.prototype.slice.call(item.querySelectorAll("[data-lf-ai-list-remove=\"1\"]")).forEach(function(node){
						if (node && node.parentNode) node.parentNode.removeChild(node);
					});
					item.setAttribute("draggable", "true");
					item.ondragstart = function(e){
						activeFaqDragEl = item;
						item.classList.add("is-dragging");
						if (e.dataTransfer) {
							e.dataTransfer.effectAllowed = "move";
							e.dataTransfer.setData("text/plain", String(item.getAttribute("data-lf-faq-id") || ""));
						}
					};
					item.ondragover = function(e){
						if (!activeFaqDragEl || activeFaqDragEl === item) return;
						e.preventDefault();
					};
					item.ondrop = function(e){
						if (!activeFaqDragEl || activeFaqDragEl === item) return;
						e.preventDefault();
						var rect = item.getBoundingClientRect();
						var after = e.clientY > (rect.top + rect.height / 2);
						if (after) {
							item.parentNode.insertBefore(activeFaqDragEl, item.nextSibling);
						} else {
							item.parentNode.insertBefore(activeFaqDragEl, item);
						}
						persistFaqSelection(list, "Saving selected FAQs...");
					};
					item.ondragend = function(){
						item.classList.remove("is-dragging");
						activeFaqDragEl = null;
					};
					var removeBtn = createGenericRemoveButton(function(){
						if (item && item.parentNode) item.parentNode.removeChild(item);
						persistFaqSelection(list, "Saving selected FAQs...");
					});
					item.appendChild(removeBtn);
				});
				var controls = document.createElement("div");
				controls.className = "lf-ai-faq-controls lf-ai-inline-editor-ignore";
				controls.setAttribute("data-lf-ai-faq-controls", "1");
				var addBtn = document.createElement("button");
				addBtn.type = "button";
				addBtn.className = "lf-ai-faq-add lf-ai-inline-editor-ignore";
				addBtn.textContent = "+ Select FAQs";
				addBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					openFaqPicker(wrap, list);
				});
				controls.appendChild(addBtn);
				if (list.parentNode) {
					list.parentNode.insertBefore(controls, list.nextSibling);
				}
			});
		}
		function trustLayoutFromWrap(wrap) {
			var block = wrap ? wrap.querySelector(".lf-block-trust-reviews") : null;
			if (!block || !block.classList) return "slider";
			
			// Check for layout-specific classes (more specific than variant classes)
			var classes = block.className.split(' ');
			if (classes.indexOf("lf-block-trust-reviews--slider") !== -1) return "slider";
			if (classes.indexOf("lf-block-trust-reviews--grid") !== -1) return "grid";
			
			// Fallback removed due to syntax issues
			
			// Default to grid
			return "grid";
		}
		function nextTrustLayout(current) {
			var cycle = ["slider", "grid"];
			var idx = cycle.indexOf(String(current || "slider"));
			if (idx < 0) idx = 0;
			return cycle[(idx + 1) % cycle.length];
		}
		function persistTrustLayout(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			var current = trustLayoutFromWrap(wrap);
			var layout = nextTrustLayout(current);
			setStatus("Switching review layout...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_set_trust_layout",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				layout: layout
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
					return;
				}
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Review layout updated.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Review layout update failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Review layout update failed.";
				setStatus(msg, true);
			});
		}
		function buildBenefitsIconEditors() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (sectionType !== "benefits") return;
				var nodes = Array.prototype.slice.call(wrap.querySelectorAll(".lf-benefits__icon[data-lf-benefit-icon-index]"));
				if (!nodes.length || !iconSlugs.length) return;
				var currentOverrides = [];
				function parseCurrentOverrides() {
					currentOverrides = [];
					nodes.forEach(function(iconNode){
						var svg = iconNode.querySelector("svg");
						if (!svg) {
							currentOverrides.push("");
							return;
						}
						var cls = String(svg.getAttribute("class") || "");
						var m = cls.match(/\blf-icon--([a-z0-9-]+)\b/i);
						currentOverrides.push(m && m[1] ? String(m[1]) : "");
					});
				}
				function persistOverrides() {
					persistSectionLineItems(wrap, "benefits_icon_overrides", currentOverrides, "Saving icons...");
					setTimeout(function(){ window.location.reload(); }, 220);
				}
				parseCurrentOverrides();
				nodes.forEach(function(iconNode){
					iconNode.classList.add("lf-ai-inline-editor-ignore");
					iconNode.setAttribute("title", "Click to change icon");
					iconNode.style.cursor = "pointer";
					iconNode.onclick = function(e){
						if (!editingEnabled) return;
						e.preventDefault();
						e.stopPropagation();
						var idx = parseInt(String(iconNode.getAttribute("data-lf-benefit-icon-index") || "0"), 10);
						if (isNaN(idx) || idx < 0) idx = 0;
						while (currentOverrides.length <= idx) currentOverrides.push("");
						var currentSlug = String(currentOverrides[idx] || "");
						openIconPicker(currentSlug, function(selectedSlug){
							currentOverrides[idx] = String(selectedSlug || "");
							persistOverrides();
						});
					};
				});
			});
		}
		function ctaSlotForButton(node) {
			if (!node || !node.classList) return "primary";
			if (node.classList.contains("lf-btn--secondary") || /secondary/i.test(String(node.className || ""))) {
				return "secondary";
			}
			return "primary";
		}
		function ctaActionForButton(node) {
			if (!node) return "quote";
			if (node.getAttribute("data-lf-quote-trigger") === "1") return "quote";
			if (node.tagName && node.tagName.toLowerCase() === "a") {
				var href = String(node.getAttribute("href") || "").trim().toLowerCase();
				if (href.indexOf("tel:") === 0) return "call";
				if (href !== "") return "link";
			}
			return "quote";
		}
		function ctaUrlForButton(node) {
			if (!node || !node.getAttribute) return "";
			var href = String(node.getAttribute("href") || "").trim();
			if (!href || /^tel:/i.test(href)) return "";
			return href;
		}
		function persistSectionButtonCta(wrap, slot, text, action, url) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			setStatus("Saving button...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_update_section_cta",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				slot: String(slot || "primary"),
				text: String(text || ""),
				cta_action: String(action || "quote"),
				url: String(url || "")
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Button saved.", false);
					if (res.data && res.data.reload) {
						window.location.reload();
					}
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Button save failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Button save failed.";
				setStatus(msg, true);
			});
		}
		function openCtaButtonEditor(wrap, node) {
			if (!wrap || !node) return;
			var currentText = String(node.textContent || "").trim();
			var slot = ctaSlotForButton(node);
			var currentAction = ctaActionForButton(node);
			var currentUrl = ctaUrlForButton(node);
			var actionInput = "";
			try {
				actionInput = String(window.prompt("Button action (quote, call, link):", currentAction) || "").trim().toLowerCase();
			} catch (e) {
				actionInput = "";
			}
			if (!actionInput) return;
			if (["quote", "call", "link"].indexOf(actionInput) === -1) {
				setStatus("Invalid action. Use quote, call, or link.", true);
				return;
			}
			var newText = "";
			try {
				newText = String(window.prompt("Button text:", currentText) || "").trim();
			} catch (err) {
				newText = "";
			}
			if (!newText) {
				setStatus("Button text cannot be empty.", true);
				return;
			}
			var newUrl = "";
			if (actionInput === "link") {
				try {
					newUrl = String(window.prompt("Button link URL:", currentUrl || "https://") || "").trim();
				} catch (err2) {
					newUrl = "";
				}
				if (!newUrl) {
					setStatus("Link URL is required for link action.", true);
					return;
				}
			}
			persistSectionButtonCta(wrap, slot, newText, actionInput, newUrl);
		}
		function buildSectionButtonEditors() {
			if (!editingEnabled) return;
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var nodes = Array.prototype.slice.call(wrap.querySelectorAll("a.lf-btn,button.lf-btn,span.lf-btn"));
				nodes.forEach(function(node){
					if (!node || (node.closest && node.closest(".lf-ai-float"))) return;
					if (node.getAttribute("data-lf-ai-cta-editable") === "1") return;
					node.setAttribute("data-lf-ai-cta-editable", "1");
					node.setAttribute("title", "Click to edit button text and action/link");
					node.addEventListener("click", function(e){
						if (!editingEnabled) return;
						e.preventDefault();
						e.stopPropagation();
						openCtaButtonEditor(wrap, node);
					});
					node.addEventListener("dblclick", function(e){
						if (!editingEnabled) return;
						e.preventDefault();
						e.stopPropagation();
						openCtaButtonEditor(wrap, node);
					});
				});
			});
		}
		function toggleSectionColumnsInDom(wrap, newLayout) {
			if (!wrap) return;
			var details = wrap.querySelector(".lf-service-details--media");
			if (!details) return;
			var contentNode = null;
			var mediaNode = null;
			Array.prototype.slice.call(details.children || []).forEach(function(child){
				if (!child || !child.classList) return;
				if (child.classList.contains("lf-service-details__content")) contentNode = child;
				if (child.classList.contains("lf-service-details__media")) mediaNode = child;
			});
			if (!contentNode || !mediaNode) return;
			var mediaLeft = String(newLayout || "") === "media_content";
			if (mediaLeft) {
				details.classList.add("lf-service-details--media-left");
				if (details.firstElementChild !== mediaNode) {
					details.insertBefore(mediaNode, contentNode);
				}
			} else {
				details.classList.remove("lf-service-details--media-left");
				if (details.firstElementChild !== contentNode) {
					details.insertBefore(contentNode, mediaNode);
				}
			}
		}
		function persistSectionColumnSwap(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
			if (!sectionId || !sectionSupportsColumnSwap(sectionType)) return;
			setStatus("Reversing section columns...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_toggle_section_columns",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId
			}).done(function(res){
				if (res && res.success && res.data) {
					toggleSectionColumnsInDom(wrap, String(res.data.new_layout || ""));
					setStatus((res.data && res.data.message) ? res.data.message : "Section columns reversed.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Column reversal failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Column reversal failed.";
				setStatus(msg, true);
			});
		}
		function applySectionVisibilityUi(wrap, visible) {
			if (!wrap) return;
			var isVisible = !!visible;
			var sid = String(wrap.getAttribute("data-lf-section-id") || "");
			if (sid) {
				homepageEnabledMap[sid] = isVisible;
			}
			wrap.setAttribute("data-lf-section-visible", isVisible ? "1" : "0");
			wrap.classList.toggle("lf-ai-section-is-hidden", !isVisible);
			var btn = wrap.querySelector("[data-lf-section-toggle]");
			if (btn) {
				btn.textContent = isVisible ? "Hide" : "Show";
				btn.setAttribute("aria-label", isVisible ? "Hide section" : "Show section");
			}
		}
		function persistSectionVisibility(wrap, visible) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			setStatus(visible ? "Showing section..." : "Hiding section...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_toggle_section_visibility",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId,
				visible: visible ? "1" : "0"
			}).done(function(res){
				if (res && res.success) {
					applySectionVisibilityUi(wrap, !!(res.data && res.data.visible));
					setStatus((res.data && res.data.message) ? res.data.message : "Section visibility updated.", false);
					refreshSectionRail();
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Section visibility update failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section visibility update failed.";
				setStatus(msg, true);
			});
		}
		function persistSectionDelete(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			setStatus("Deleting section...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_delete_section",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId
			}).done(function(res){
				if (res && res.success) {
					if (selectedSectionWrap === wrap) {
						selectedSectionWrap = null;
					}
					var sid = String(wrap.getAttribute("data-lf-section-id") || "");
					if (sid) {
						homepageEnabledMap[sid] = false;
					}
					wrap.remove();
					setStatus((res.data && res.data.message) ? res.data.message : "Section deleted. Use undo to restore.", false);
					refreshSectionRail();
				} else {
					var failMsg = (res && res.data && res.data.message) ? res.data.message : "Section delete failed.";
					setStatus(failMsg, true);
					try { window.alert(failMsg); } catch (e) {}
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section delete failed.";
				setStatus(msg, true);
				try { window.alert(msg); } catch (e) {}
			});
		}
		function persistSectionDuplicate(wrap) {
			if (!wrap) return;
			var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
			if (!sectionId) return;
			setStatus("Duplicating section...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_duplicate_section",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_id: sectionId
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
					return;
				}
				if (res && res.success && res.data && res.data.new_section_id) {
					var clone = wrap.cloneNode(true);
					clone.classList.remove("is-dragging", "lf-ai-section-active");
					clone.setAttribute("data-lf-section-id", String(res.data.new_section_id));
					clone.removeAttribute("data-lf-section-visible");
					homepageEnabledMap[String(res.data.new_section_id)] = true;
					wrap.parentNode.insertBefore(clone, wrap.nextSibling);
					buildSectionTargets();
					buildSectionControls();
					buildSectionButtonEditors();
					buildHeroPillsControls();
					buildHeroProofChecklistControls();
					buildTrustBadgePillsControls();
					buildChecklistControls();
					buildProcessStepControls();
					buildSectionColumnSwapTargets();
					buildSectionMediaEditors();
					buildFaqReorderControls();
					buildBenefitsIconEditors();
					setSelectedSection(clone);
					setStatus((res.data && res.data.message) ? res.data.message : "Section duplicated.", false);
				} else {
					var failMsg = (res && res.data && res.data.message) ? res.data.message : "Section duplicate failed.";
					setStatus(failMsg, true);
					try { window.alert(failMsg); } catch (e) {}
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section duplicate failed.";
				setStatus(msg, true);
				try { window.alert(msg); } catch (e) {}
			});
		}
		function moveSectionByStep(wrap, delta) {
			if (!wrap || !delta) return;
			var wraps = collectSectionWrappers();
			var idx = wraps.indexOf(wrap);
			if (idx < 0) return;
			var targetIdx = idx + delta;
			if (targetIdx < 0 || targetIdx >= wraps.length) return;
			var target = wraps[targetIdx];
			if (!target || !target.parentNode) return;
			if (delta < 0) {
				target.parentNode.insertBefore(wrap, target);
			} else {
				target.parentNode.insertBefore(wrap, target.nextSibling);
			}
			setSelectedSection(wrap);
			persistSectionOrder();
		}
		function buildSectionControls() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				if (wrap.querySelector(".lf-ai-section-controls")) return;
				var controls = document.createElement("div");
				controls.className = "lf-ai-section-controls lf-ai-inline-editor-ignore";
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (sectionSupportsColumnSwap(sectionType)) {
					var swapBtn = document.createElement("button");
					swapBtn.type = "button";
					swapBtn.className = "lf-ai-section-btn";
					swapBtn.textContent = "⇆";
					swapBtn.setAttribute("title", "Reverse image/content columns");
					swapBtn.setAttribute("aria-label", "Reverse image/content columns");
					swapBtn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						persistSectionColumnSwap(wrap);
					});
					controls.appendChild(swapBtn);
				}
				if (sectionType === "trust_reviews") {
					var layoutBtn = document.createElement("button");
					layoutBtn.type = "button";
					layoutBtn.className = "lf-ai-section-btn";
					layoutBtn.textContent = "Layout";
					layoutBtn.setAttribute("title", "Cycle review layout (slider, grid)");
					layoutBtn.setAttribute("aria-label", "Cycle review layout");
					layoutBtn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						persistTrustLayout(wrap);
					});
					controls.appendChild(layoutBtn);
				}
				var upBtn = document.createElement("button");
				upBtn.type = "button";
				upBtn.className = "lf-ai-section-btn";
				upBtn.textContent = "↑";
				upBtn.setAttribute("title", "Move section up");
				upBtn.setAttribute("aria-label", "Move section up");
				upBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					moveSectionByStep(wrap, -1);
				});
				controls.appendChild(upBtn);
				var downBtn = document.createElement("button");
				downBtn.type = "button";
				downBtn.className = "lf-ai-section-btn";
				downBtn.textContent = "↓";
				downBtn.setAttribute("title", "Move section down");
				downBtn.setAttribute("aria-label", "Move section down");
				downBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					moveSectionByStep(wrap, 1);
				});
				controls.appendChild(downBtn);
				if (sectionSupportsDuplicate(sectionType, String(wrap.getAttribute("data-lf-section-id") || ""))) {
					var dupBtn = document.createElement("button");
					dupBtn.type = "button";
					dupBtn.className = "lf-ai-section-btn";
					dupBtn.textContent = "Dup";
					dupBtn.setAttribute("title", "Duplicate section");
					dupBtn.addEventListener("click", function(e){
						e.preventDefault();
						e.stopPropagation();
						persistSectionDuplicate(wrap);
					});
					controls.appendChild(dupBtn);
				}
				var toggleBtn = document.createElement("button");
				toggleBtn.type = "button";
				toggleBtn.className = "lf-ai-section-btn";
				toggleBtn.setAttribute("data-lf-section-toggle", "1");
				toggleBtn.textContent = "Hide";
				toggleBtn.setAttribute("title", "Hide or show section");
				toggleBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var isVisible = String(wrap.getAttribute("data-lf-section-visible") || "1") !== "0";
					persistSectionVisibility(wrap, !isVisible);
				});
				controls.appendChild(toggleBtn);
				var deleteBtn = document.createElement("button");
				deleteBtn.type = "button";
				deleteBtn.className = "lf-ai-section-btn lf-ai-section-btn--danger";
				deleteBtn.textContent = "Del";
				deleteBtn.setAttribute("title", "Delete section");
				deleteBtn.addEventListener("click", function(e){
					e.preventDefault();
					e.stopPropagation();
					var ok = false;
					try {
						ok = window.confirm("Delete this section? You can undo after deleting.");
					} catch (err) {
						ok = true;
					}
					if (ok) {
						persistSectionDelete(wrap);
					}
				});
				controls.appendChild(deleteBtn);
				wrap.appendChild(controls);
				applySectionVisibilityUi(wrap, String(wrap.getAttribute("data-lf-section-visible") || "1") !== "0");
			});
		}
		function buildSectionColumnSwapTargets() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				var sectionType = String(wrap.getAttribute("data-lf-section-type") || "");
				if (!sectionSupportsColumnSwap(sectionType)) return;
				var details = wrap.querySelector(".lf-service-details--media");
				if (!details) return;
				var contentNode = null;
				var mediaNode = null;
				Array.prototype.slice.call(details.children || []).forEach(function(child){
					if (!child || !child.classList) return;
					if (child.classList.contains("lf-service-details__content")) contentNode = child;
					if (child.classList.contains("lf-service-details__media")) mediaNode = child;
				});
				if (!contentNode || !mediaNode) return;
				[contentNode, mediaNode].forEach(function(col){
					col.classList.add("lf-ai-column-draggable", "lf-ai-inline-editor-ignore");
					col.setAttribute("draggable", "true");
					col.ondragstart = function(e){
						var role = col.classList.contains("lf-service-details__media") ? "media" : "content";
						activeColumnDrag = { wrap: wrap, role: role };
						col.classList.add("is-dragging");
						if (e.dataTransfer) {
							e.dataTransfer.effectAllowed = "move";
							e.dataTransfer.setData("text/plain", role);
						}
						suppressInlineClickUntil = Date.now() + 350;
					};
					col.ondragover = function(e){
						if (!activeColumnDrag || activeColumnDrag.wrap !== wrap) return;
						e.preventDefault();
					};
					col.ondragend = function(){
						col.classList.remove("is-dragging");
						activeColumnDrag = null;
					};
				});
				details.ondragover = function(e){
					if (!activeColumnDrag || activeColumnDrag.wrap !== wrap) return;
					e.preventDefault();
				};
				details.ondrop = function(e){
					if (!activeColumnDrag || activeColumnDrag.wrap !== wrap) return;
					e.preventDefault();
					var rect = details.getBoundingClientRect();
					var dropOnLeftHalf = e.clientX < (rect.left + rect.width / 2);
					var currentMediaLeft = details.classList.contains("lf-service-details--media-left");
					var desiredMediaLeft = dropOnLeftHalf;
					if (desiredMediaLeft !== currentMediaLeft) {
						persistSectionColumnSwap(wrap);
					}
				};
			});
		}
		function reorderSectionInDom(targetWrap, clientY) {
			if (!activeDragSection || !targetWrap || targetWrap === activeDragSection) {
				return;
			}
			var rect = targetWrap.getBoundingClientRect();
			var after = clientY > (rect.top + rect.height / 2);
			if (after) {
				if (targetWrap.nextSibling !== activeDragSection) {
					targetWrap.parentNode.insertBefore(activeDragSection, targetWrap.nextSibling);
				}
			} else if (targetWrap.previousSibling !== activeDragSection) {
				targetWrap.parentNode.insertBefore(activeDragSection, targetWrap);
			}
		}
		function persistSectionOrder() {
			var ids = collectSectionWrappers().map(function(node){
				return String(node.getAttribute("data-lf-section-id") || "");
			}).filter(function(id){ return id !== ""; });
			if (!ids.length) {
				return;
			}
			setStatus((lfAiFloating.i18n && lfAiFloating.i18n.statusReordering) ? lfAiFloating.i18n.statusReordering : "Saving section order...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_reorder_sections",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				ordered_ids: JSON.stringify(ids)
			}).done(function(res){
				if (res && res.success) {
					setStatus((res.data && res.data.message) ? res.data.message : "Section order saved.", false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Section reorder failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Section reorder failed.";
				setStatus(msg, true);
			});
		}
		function buildSectionTargets() {
			collectSectionWrappers().forEach(function(wrap){
				if (!wrap || wrap.closest(".lf-ai-float")) return;
				wrap.onmousedown = function(e){
					if (!e || !e.target) return;
					var t = e.target.nodeType === 1 ? e.target : e.target.parentElement;
					if (!t) return;
					// Do not reselect/rebuild when clicking inline editor controls.
					if (isDragBlockedTarget(t)) return;
					if ((t.closest && t.closest(".lf-ai-section-controls")) || (t.closest && t.closest(".lf-ai-float"))) return;
					setSelectedSection(wrap);
				};
				wrap.setAttribute("draggable", "true");
				wrap.ondragstart = function(e){
					if (isDragBlockedTarget(e.target)) {
						e.preventDefault();
						return;
					}
					if (activeColumnDrag) {
						e.preventDefault();
						return;
					}
					suppressInlineClickUntil = Date.now() + 350;
					if (inlineActiveEl) {
						saveInlineEdit();
					}
					activeDragSection = wrap;
					wrap.classList.add("is-dragging");
					if (e.dataTransfer) {
						e.dataTransfer.effectAllowed = "move";
						e.dataTransfer.setData("text/plain", String(wrap.getAttribute("data-lf-section-id") || ""));
					}
				};
				wrap.ondragover = function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
						return;
					}
					if (!activeDragSection || wrap === activeDragSection) return;
					e.preventDefault();
					reorderSectionInDom(wrap, e.clientY);
				};
				wrap.ondrop = function(e){
					if (activeLibraryDragSectionType) {
						e.preventDefault();
						addSectionFromLibrary(activeLibraryDragSectionType, String(wrap.getAttribute("data-lf-section-id") || ""));
						activeLibraryDragSectionType = "";
						return;
					}
					e.preventDefault();
				};
				wrap.ondragend = function(){
					if (activeDragSection) {
						activeDragSection.classList.remove("is-dragging");
					}
					activeDragSection = null;
					persistSectionOrder();
				};
			});
		}
		function beginInlineEdit(el) {
			if (!el || inlineIsSaving) return;
			var selector = String(el.getAttribute("data-lf-inline-selector") || "");
			if (!selector) return;
			if (inlineActiveEl && inlineActiveEl !== el) {
				saveInlineEdit(function(){
					beginInlineEdit(el);
				});
				return;
			}
			if (inlineActiveEl === el) {
				return;
			}
			inlineActiveEl = el;
			inlineOriginalText = String(el.textContent || "").trim();
			el.setAttribute("data-lf-inline-active", "1");
			el.setAttribute("contenteditable", "true");
			el.setAttribute("spellcheck", "true");
			try { el.focus(); } catch (e) {}
			setStatus("Editing text. Click away to auto-save.", false);
			el.addEventListener("blur", function onBlur(){
				setTimeout(function(){
					if (inlineActiveEl === el) {
						saveInlineEdit();
					}
				}, 0);
			}, { once: true });
		}
		function cancelInlineEdit(showStatus) {
			if (!inlineActiveEl) {
				return;
			}
			inlineActiveEl.textContent = inlineOriginalText;
			inlineActiveEl.removeAttribute("contenteditable");
			inlineActiveEl.removeAttribute("spellcheck");
			inlineActiveEl.removeAttribute("data-lf-inline-active");
			inlineActiveEl.removeAttribute("data-lf-inline-saving");
			inlineActiveEl = null;
			inlineOriginalText = "";
			if (showStatus !== false) {
				setStatus("Inline edit cancelled.", false);
			}
		}
		function saveInlineEdit(done) {
			if (!inlineActiveEl || inlineIsSaving) {
				if (typeof done === "function") done();
				return;
			}
			var el = inlineActiveEl;
			var selector = String(el.getAttribute("data-lf-inline-selector") || "");
			var fieldKey = String(el.getAttribute("data-lf-inline-field-key") || "");
			var value = String(el.textContent || "").trim();
			if (!selector && !fieldKey) {
				setStatus("Invalid inline target.", true);
				if (typeof done === "function") done();
				return;
			}
			if (value === "") {
				setStatus("Text cannot be empty.", true);
				if (typeof done === "function") done();
				return;
			}
			if (value === inlineOriginalText) {
				el.removeAttribute("contenteditable");
				el.removeAttribute("spellcheck");
				el.removeAttribute("data-lf-inline-active");
				el.removeAttribute("data-lf-inline-saving");
				inlineActiveEl = null;
				inlineOriginalText = "";
				if (typeof done === "function") done();
				return;
			}
			inlineIsSaving = true;
			el.setAttribute("data-lf-inline-saving", "1");
			setStatus("Saving inline edit...", false);
			var payload = {
				action: "lf_ai_inline_save",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				value: value
			};
			if (fieldKey) {
				payload.field_key = fieldKey;
			} else {
				payload.selector = selector;
			}
			$.post(lfAiFloating.ajax_url, payload).done(function(res){
				if (res && res.success) {
					el.removeAttribute("contenteditable");
					el.removeAttribute("spellcheck");
					el.removeAttribute("data-lf-inline-active");
					el.removeAttribute("data-lf-inline-saving");
					if (inlineActiveEl === el) {
						inlineActiveEl = null;
						inlineOriginalText = "";
					}
					setStatus("Saved. Use the ↶ icon to undo (repeat to go back further).", false);
				} else {
					el.removeAttribute("data-lf-inline-saving");
					setStatus((res && res.data && res.data.message) ? res.data.message : "Inline save failed.", true);
				}
			}).fail(function(xhr){
				el.removeAttribute("data-lf-inline-saving");
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Inline save failed.";
				setStatus(msg, true);
			}).always(function(){
				inlineIsSaving = false;
				if (typeof done === "function") done();
			});
		}
		function renderDiff() {
			if (!proposed) return;
			var html = "";
			Object.keys(proposed).forEach(function(key){
				var fieldLabel = labels[key] || key;
				var oldVal = current && current[key] !== undefined ? current[key] : "";
				var newVal = proposed[key] !== undefined ? proposed[key] : "";
				html += "<div class=\"lf-ai-float__row\">"
					+ "<div class=\"lf-ai-float__field\">" + escapeHtml(fieldLabel) + "</div>"
					+ "<div class=\"lf-ai-float__cols\">"
					+ "<div class=\"lf-ai-float__col\"><b>Current</b>" + escapeHtml(oldVal) + "</div>"
					+ "<div class=\"lf-ai-float__col\"><b>Suggested</b>" + escapeHtml(newVal) + "</div>"
					+ "</div></div>";
			});
			$diff.html(html).prop("hidden", false);
		}
		function renderInlineQuickPreview(currentText, newText) {
			var html = "<div class=\"lf-ai-float__row\">"
				+ "<div class=\"lf-ai-float__field\">Matched on-page text</div>"
				+ "<div class=\"lf-ai-float__cols\">"
				+ "<div class=\"lf-ai-float__col\"><b>Current</b>" + escapeHtml(currentText) + "</div>"
				+ "<div class=\"lf-ai-float__col\"><b>Suggested</b>" + escapeHtml(newText) + "</div>"
				+ "</div></div>";
			$diff.html(html).prop("hidden", false);
		}
		function renderQaAnswer(answer, snapshot) {
			var text = String(answer || "").trim();
			var data = snapshot && typeof snapshot === "object" ? snapshot : {};
			var score = parseInt(String(data.backend_seo_score || "0"), 10);
			if (isNaN(score)) score = 0;
			var intent = String(data.intent || "");
			var primary = data.meta && data.meta.primary_keyword ? String(data.meta.primary_keyword) : "";
			var html = "<div class=\"lf-ai-float__row\">"
				+ "<div class=\"lf-ai-float__field\">AI Answer</div>"
				+ "<div>" + escapeHtml(text || "No answer returned.") + "</div>";
			if (score > 0 || intent || primary) {
				html += "<div style=\"margin-top:8px;font-size:11px;color:#475569;\">"
					+ (score > 0 ? ("SEO score: <b>" + score + "/100</b>. ") : "")
					+ (intent ? ("Intent: <b>" + escapeHtml(intent) + "</b>. ") : "")
					+ (primary ? ("Primary keyword: <b>" + escapeHtml(primary) + "</b>.") : "")
					+ "</div>";
			}
			html += "</div>";
			$diff.html(html).prop("hidden", false);
		}
		function renderCreationPreview(data) {
			var html = "";
			var title = data && data.title ? data.title : "";
			var type = data && data.type ? data.type : "";
			var status = data && data.status ? data.status : "draft";
			var notes = data && data.notes && Array.isArray(data.notes) ? data.notes : [];
			html += "<div class=\"lf-ai-float__row\">"
				+ "<div class=\"lf-ai-float__field\">Draft Preview</div>"
				+ "<div><b>Title:</b> " + escapeHtml(title) + "</div>"
				+ "<div><b>Type:</b> " + escapeHtml(type) + "</div>"
				+ "<div><b>Status:</b> " + escapeHtml(status) + "</div>";
			if (notes.length) {
				html += "<div style=\"margin-top:6px;\"><b>Notes:</b><ul style=\"margin:6px 0 0 16px;\">";
				notes.forEach(function(note){
					html += "<li>" + escapeHtml(note) + "</li>";
				});
				html += "</ul></div>";
			}
			html += "</div>";
			$diff.html(html).prop("hidden", false);
		}
		function renderCreationQueue(items) {
			var rows = Array.isArray(items) ? items : [];
			if (!rows.length) {
				$diff.html("").prop("hidden", true);
				return;
			}
			var html = "<div class=\"lf-ai-float__row\"><div class=\"lf-ai-float__field\">Batch Draft Queue (" + rows.length + ")</div></div>";
			rows.forEach(function(item, idx){
				var notes = item && item.notes && Array.isArray(item.notes) ? item.notes : [];
				html += "<div class=\"lf-ai-float__row\">"
					+ "<div class=\"lf-ai-float__field\">#" + (idx + 1) + " " + escapeHtml(item && item.title ? item.title : "") + "</div>"
					+ "<div><b>Type:</b> " + escapeHtml(item && item.type ? item.type : "") + " | <b>Status:</b> draft</div>";
				if (notes.length) {
					html += "<div style=\"margin-top:6px;\"><ul style=\"margin:0 0 0 16px;\">";
					notes.forEach(function(note){ html += "<li>" + escapeHtml(note) + "</li>"; });
					html += "</ul></div>";
				}
				html += "</div>";
			});
			$diff.html(html).prop("hidden", false);
		}
		function setProposalEnabled(enabled){
			$btnApply.prop("disabled", !enabled);
			$btnReject.prop("disabled", !enabled);
		}
		function modeValue() {
			return String($mode.val() || "edit_existing");
		}
		function cptTypeValue() {
			return String($cptType.val() || "lf_service");
		}
		function batchTypeValue() {
			return String($batchType.val() || "post");
		}
		function batchCountValue() {
			var n = parseInt(String($batchCount.val() || "5"), 10);
			if (!n || n < 1) n = 1;
			if (n > 20) n = 20;
			activeAssistantBatchCount = n;
			return n;
		}
		function syncModeUi() {
			var mode = modeValue();
			$cptWrap.prop("hidden", mode !== "create_cpt");
			$batchWrap.prop("hidden", mode !== "create_batch");
			$batchCountWrap.prop("hidden", mode !== "create_batch");
			var targetSuffix = (mode === "edit_existing" || mode === "auto") ? " (editable target)" : " (context source)";
			$target.text("Target: " + (activeTargetLabel || "Homepage") + targetSuffix);
		}
		function setDocState(name, content) {
			docLabel = String(name || "");
			docContext = String(content || "");
			$docName.text(docLabel !== "" ? ("Attached: " + docLabel) : "");
			$docClear.prop("hidden", docLabel === "");
		}
		function parseDoc(file) {
			var form = new FormData();
			form.append("action", "lf_ai_extract_context_doc");
			form.append("nonce", lfAiFloating.nonce);
			form.append("document", file);
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusParsingDoc ? lfAiFloating.i18n.statusParsingDoc : "Reading document...", false);
			$.ajax({
				url: lfAiFloating.ajax_url,
				type: "POST",
				data: form,
				processData: false,
				contentType: false
			}).done(function(res){
				if (res && res.success && res.data) {
					setDocState(res.data.name || file.name || "document", res.data.context || "");
					setStatus("Document context attached.", false);
				} else {
					setDocState("", "");
					setStatus((res && res.data && res.data.message) ? res.data.message : "Unable to parse document.", true);
				}
			}).fail(function(xhr){
				setDocState("", "");
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Document upload failed.";
				setStatus(msg, true);
			});
		}
		function setConfirmOpen(open) {
			$confirm.prop("hidden", !open);
		}
		function openConfirm(message, yesLabel, onYes) {
			pendingConfirmAction = typeof onYes === "function" ? onYes : null;
			$confirmText.text(String(message || defaultConfirmText));
			$confirmYes.text(String(yesLabel || defaultConfirmYesText));
			setConfirmOpen(true);
		}
		function runRollback() {
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusReverting ? lfAiFloating.i18n.statusReverting : "Reverting...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_rollback_latest",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType || "homepage",
				context_id: activeContextId || "homepage"
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "No rollback available.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Rollback failed.";
				setStatus(msg, true);
			});
		}
		function runRedo() {
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusRedoing ? lfAiFloating.i18n.statusRedoing : "Redoing...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_redo_latest",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType || "homepage",
				context_id: activeContextId || "homepage"
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "No redo available.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Redo failed.";
				setStatus(msg, true);
			});
		}
		function selectedSectionCanReverse() {
			if (!selectedSectionWrap) return false;
			return sectionSupportsColumnSwap(String(selectedSectionWrap.getAttribute("data-lf-section-type") || ""));
		}
		function selectedSectionToggleVisibility() {
			if (!selectedSectionWrap) return;
			var isVisible = String(selectedSectionWrap.getAttribute("data-lf-section-visible") || "1") !== "0";
			persistSectionVisibility(selectedSectionWrap, !isVisible);
		}
		function selectedSectionDelete() {
			if (!selectedSectionWrap) return;
			var targetWrap = selectedSectionWrap;
			var ok = false;
			try {
				ok = window.confirm("Delete this section? You can undo after deleting.");
			} catch (e) {
				ok = true;
			}
			if (!ok) return;
			persistSectionDelete(targetWrap);
		}
		function selectedSectionReverseColumns() {
			if (!selectedSectionWrap || !selectedSectionCanReverse()) return;
			persistSectionColumnSwap(selectedSectionWrap);
		}
		function selectedSectionDuplicate() {
			if (!selectedSectionWrap) return;
			var type = String(selectedSectionWrap.getAttribute("data-lf-section-type") || "");
			var id = String(selectedSectionWrap.getAttribute("data-lf-section-id") || "");
			if (!sectionSupportsDuplicate(type, id)) return;
			persistSectionDuplicate(selectedSectionWrap);
		}
		function ensureCommandPalette() {
			if (commandPaletteEl) return commandPaletteEl;
			commandPaletteEl = document.createElement("div");
			commandPaletteEl.className = "lf-ai-command lf-ai-inline-editor-ignore";
			commandPaletteEl.hidden = true;
			commandPaletteEl.innerHTML = "<input type=\"text\" class=\"lf-ai-command__input\" placeholder=\"Type a command...\" /><div class=\"lf-ai-command__list\"></div><div class=\"lf-ai-command__hint\">Enter to run • Esc to close • \u2318/\u2303+K to toggle</div>";
			document.body.appendChild(commandPaletteEl);
			commandInputEl = commandPaletteEl.querySelector(".lf-ai-command__input");
			commandListEl = commandPaletteEl.querySelector(".lf-ai-command__list");
			commandInputEl.addEventListener("input", refreshCommandPalette);
			commandInputEl.addEventListener("keydown", function(e){
				if (e.key === "ArrowDown") {
					e.preventDefault();
					commandActiveIndex = Math.min(commandRows.length - 1, commandActiveIndex + 1);
					refreshCommandPalette();
				} else if (e.key === "ArrowUp") {
					e.preventDefault();
					commandActiveIndex = Math.max(0, commandActiveIndex - 1);
					refreshCommandPalette();
				} else if (e.key === "Enter") {
					e.preventDefault();
					executeCommandAt(commandActiveIndex);
				} else if (e.key === "Escape") {
					e.preventDefault();
					toggleCommandPalette(false);
				}
			});
			commandInputEl.addEventListener("keyup", function(e){
				if (e.key === "Enter") {
					e.preventDefault();
					executeCommandAt(commandActiveIndex);
				}
			});
			commandPaletteEl.addEventListener("click", function(e){
				if (e.target === commandPaletteEl) {
					toggleCommandPalette(false);
				}
			});
			return commandPaletteEl;
		}
		function commandItems() {
			var hasSel = !!selectedSectionWrap;
			var visible = hasSel ? (String(selectedSectionWrap.getAttribute("data-lf-section-visible") || "1") !== "0") : true;
			return [
				{ label: "Focus AI prompt", enabled: true, run: function(){ setAiOpen(true); setSeoOpen(false); try { $prompt.trigger("focus"); } catch (e) {} } },
				{ label: "Undo last action", enabled: true, run: runRollback },
				{ label: "Redo last action", enabled: true, run: runRedo },
				{ label: "Move selected section up", enabled: hasSel, run: function(){ moveSelectedSection(-1); } },
				{ label: "Move selected section down", enabled: hasSel, run: function(){ moveSelectedSection(1); } },
				{ label: visible ? "Hide selected section" : "Show selected section", enabled: hasSel, run: selectedSectionToggleVisibility },
				{ label: "Delete selected section", enabled: hasSel, run: selectedSectionDelete },
				{ label: "Duplicate selected section", enabled: hasSel && sectionSupportsDuplicate(selectedSectionWrap ? selectedSectionWrap.getAttribute("data-lf-section-type") : "", selectedSectionWrap ? selectedSectionWrap.getAttribute("data-lf-section-id") : ""), run: selectedSectionDuplicate },
				{ label: "Reverse selected columns", enabled: hasSel && selectedSectionCanReverse(), run: selectedSectionReverseColumns }
			];
		}
		function refreshCommandPalette() {
			if (!commandPaletteEl || !commandListEl || commandPaletteEl.hidden) return;
			var q = String(commandInputEl && commandInputEl.value ? commandInputEl.value : "").trim().toLowerCase();
			commandRows = commandItems().filter(function(item){
				if (!item.enabled) return false;
				if (!q) return true;
				return String(item.label || "").toLowerCase().indexOf(q) !== -1;
			});
			if (commandActiveIndex >= commandRows.length) {
				commandActiveIndex = Math.max(0, commandRows.length - 1);
			}
			commandListEl.innerHTML = "";
			commandRows.forEach(function(item, idx){
				var row = document.createElement("div");
				row.className = "lf-ai-command__row" + (idx === commandActiveIndex ? " is-active" : "");
				row.setAttribute("tabindex", "0");
				row.textContent = String(item.label || "");
				row.addEventListener("mouseenter", function(){
					commandActiveIndex = idx;
					refreshCommandPalette();
				});
				row.addEventListener("click", function(){
					executeCommandAt(idx);
				});
				row.addEventListener("keydown", function(e){
					if (e.key === "Enter" || e.key === " ") {
						e.preventDefault();
						executeCommandAt(idx);
					}
				});
				commandListEl.appendChild(row);
			});
		}
		function executeCommandAt(index) {
			var idx = parseInt(String(index), 10);
			if (isNaN(idx) || idx < 0 || idx >= commandRows.length) {
				setStatus("No command selected.", true);
				return;
			}
			var cmd = commandRows[idx] || null;
			if (!cmd || typeof cmd.run !== "function") {
				setStatus("Selected command is unavailable.", true);
				return;
			}
			try {
				cmd.run();
				setStatus("Ran command: " + String(cmd.label || "action"), false);
			} catch (err) {
				setStatus("Command failed.", true);
			}
			toggleCommandPalette(false);
		}
		function toggleCommandPalette(open) {
			ensureCommandPalette();
			commandPaletteEl.hidden = !open;
			if (open) {
				commandActiveIndex = 0;
				commandInputEl.value = "";
				refreshCommandPalette();
				try { commandInputEl.focus(); } catch (e) {}
			}
		}
		function showShortcutHelp() {
			var lines = [
				"Shortcuts:",
				"Cmd/Ctrl+Z = Undo",
				"Cmd/Ctrl+Shift+Z or Ctrl+Y = Redo",
				"Cmd/Ctrl+K = Command Palette",
				"/ = Command Palette",
				"D = Duplicate selected section",
				"H = Hide/Show selected section",
				"Delete/Backspace = Delete selected section",
				"Alt/Shift+Arrow Up/Down = Move section"
			];
			var msg = lines.join("\n");
			setStatus("Shortcut help opened.", false);
			try { window.alert(msg); } catch (e) {}
		}

		$toggle.on("click", function(){
			var willOpen = $panel.prop("hidden");
			setConfirmOpen(false);
			setAiOpen(willOpen);
			if (willOpen) setSeoOpen(false);
			updateLauncherOffsets();
		});
		$seoToggle.on("click", function(){
			var willOpen = $seoPanel.prop("hidden");
			setConfirmOpen(false);
			setSeoOpen(willOpen);
			if (willOpen) setAiOpen(false);
			updateLauncherOffsets();
		});
		$root.find("[data-lf-ai-close],[data-lf-ai-minimize]").on("click", function(){ setConfirmOpen(false); setAiOpen(false); });
		$seoRoot.find("[data-lf-ai-seo-close],[data-lf-ai-seo-minimize]").on("click", function(){ setSeoOpen(false); });
		$(window).on("resize", function(){ updateLauncherOffsets(); });
		$btnEditorToggle.on("click", function(){
			setEditorEnabled(!editingEnabled);
		});
		$root.find("[data-lf-ai-preset]").on("click", function(){
			$prompt.val($(this).attr("data-lf-ai-preset") || "").trigger("focus");
		});
		$seoRefresh.on("click", function(e){
			e.preventDefault();
			renderSeoSnapshot();
			setStatus("SEO snapshot refreshed.", false);
		});
		$(document).on("click", "[data-lf-inline-image=\"1\"]", function(e){
			if (!editingEnabled) return;
			if (Date.now() < suppressInlineClickUntil) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			beginInlineImageReplace(this);
		});
		$(document).on("click", "[data-lf-inline-editable=\"1\"]", function(e){
			if (!editingEnabled) return;
			if (Date.now() < suppressInlineClickUntil) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			beginInlineEdit(this);
		});
		$(document).on("click", function(e){
			if (!inlineActiveEl || inlineIsSaving) {
				return;
			}
			var target = e.target;
			if (target === inlineActiveEl || inlineActiveEl.contains(target)) {
				return;
			}
			if ($(target).closest("[data-lf-ai-float],[data-lf-ai-seo-float]").length) {
				return;
			}
			saveInlineEdit();
		});
		$mode.on("change", function(){
			syncModeUi();
		});
		$docAttach.on("click", function(){
			$docInput.trigger("click");
		});
		$docInput.on("change", function(){
			var file = this.files && this.files[0] ? this.files[0] : null;
			if (!file) {
				return;
			}
			parseDoc(file);
		});
		$docClear.on("click", function(){
			setDocState("", "");
			try { $docInput.val(""); } catch (e) {}
			setStatus("Document context removed.", false);
		});

		$btnGenerate.on("click", function(){
			var prompt = String($prompt.val() || "").trim();
			if (!prompt) {
				setStatus("Please enter a prompt.", true);
				return;
			}
			inlineQuickEdit = null;
			promptSnippet = prompt.length > 80 ? prompt.slice(0,77) + "..." : prompt;
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusGenerating ? lfAiFloating.i18n.statusGenerating : "Generating...", false);
			$diff.prop("hidden", true).empty();
			setProposalEnabled(false);
			proposed = null;
			current = null;
			creationPayload = null;
			var parsedInlineReplace = parseInlineReplacePrompt(prompt);
			if (parsedInlineReplace && parsedInlineReplace.from && parsedInlineReplace.to) {
				var resolvedInline = resolveInlineSelectorByText(parsedInlineReplace.from);
				if (resolvedInline && resolvedInline.selector) {
					inlineQuickEdit = {
						selector: resolvedInline.selector,
						value: parsedInlineReplace.to,
						current: resolvedInline.current || parsedInlineReplace.from
					};
					renderInlineQuickPreview(inlineQuickEdit.current, inlineQuickEdit.value);
					setStatus("Direct text replacement ready. Review and apply.", false);
					setProposalEnabled(true);
					return;
				}
			}
			var parsedInlineTarget = parseInlineTargetPrompt(prompt);
			if (parsedInlineTarget && parsedInlineTarget.from) {
				var resolvedTarget = resolveInlineSelectorByText(parsedInlineTarget.from);
				if (resolvedTarget && resolvedTarget.selector) {
					setStatus("Rewriting targeted text only...", false);
					$.post(lfAiFloating.ajax_url, {
						action: "lf_ai_inline_rewrite",
						nonce: lfAiFloating.nonce,
						context_type: activeContextType,
						context_id: activeContextId,
						selector: resolvedTarget.selector,
						current_text: resolvedTarget.current || parsedInlineTarget.from,
						prompt: prompt
					}).done(function(rewriteRes){
						if (rewriteRes && rewriteRes.success && rewriteRes.data && rewriteRes.data.rewritten_text) {
							inlineQuickEdit = {
								selector: resolvedTarget.selector,
								value: String(rewriteRes.data.rewritten_text),
								current: resolvedTarget.current || parsedInlineTarget.from
							};
							renderInlineQuickPreview(inlineQuickEdit.current, inlineQuickEdit.value);
							setStatus("Targeted rewrite ready. Review and apply.", false);
							setProposalEnabled(true);
						} else {
							setStatus((rewriteRes && rewriteRes.data && rewriteRes.data.message) ? rewriteRes.data.message : "Targeted rewrite failed.", true);
						}
					}).fail(function(xhr){
						var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Targeted rewrite failed.";
						setStatus(msg, true);
					});
					return;
				}
			}
			lastMode = modeValue();
			activeAssistantCptType = cptTypeValue();
			activeAssistantBatchType = batchTypeValue();
			activeAssistantBatchCount = batchCountValue();
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_generate",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				prompt: prompt,
				document_context: docContext,
				document_name: docLabel,
				assistant_mode: lastMode,
				assistant_cpt_type: activeAssistantCptType,
				assistant_batch_type: activeAssistantBatchType,
				assistant_batch_count: activeAssistantBatchCount,
				target_reference: String($targetRef.val() || "").trim(),
				selected_section_id: selectedSectionWrap ? String(selectedSectionWrap.getAttribute("data-lf-section-id") || "") : "",
				selected_section_type: selectedSectionWrap ? String(selectedSectionWrap.getAttribute("data-lf-section-type") || "") : ""
			}).done(function(res){
				if (res && res.success && res.data) {
					if (res.data.context_type) activeContextType = String(res.data.context_type);
					if (res.data.context_id !== undefined) activeContextId = String(res.data.context_id);
					if (res.data.target_label) activeTargetLabel = String(res.data.target_label);
					if (res.data.mode) lastMode = String(res.data.mode);
					if (res.data.assistant_cpt_type) {
						activeAssistantCptType = String(res.data.assistant_cpt_type);
						try { $cptType.val(activeAssistantCptType); } catch (e) {}
					}
					if (res.data.assistant_batch_type) {
						activeAssistantBatchType = String(res.data.assistant_batch_type);
						try { $batchType.val(activeAssistantBatchType); } catch (e) {}
					}
					if (res.data.assistant_batch_count) {
						activeAssistantBatchCount = parseInt(String(res.data.assistant_batch_count), 10) || activeAssistantBatchCount;
						try { $batchCount.val(String(activeAssistantBatchCount)); } catch (e) {}
					}
					syncModeUi();
				}
				if (res && res.success && res.data && res.data.mode === "qa" && res.data.answer) {
					renderQaAnswer(res.data.answer, res.data.snapshot || {});
					setStatus("Answer ready. Ask follow-up questions anytime.", false);
					setProposalEnabled(false);
				} else if (res && res.success && res.data && res.data.mode === "edit_existing" && res.data.proposed) {
					proposed = res.data.proposed;
					current = res.data.current || {};
					labels = res.data.labels || labels;
					renderDiff();
					setStatus("Suggestions ready. Review and apply.", false);
					setProposalEnabled(true);
				} else if (res && res.success && res.data && res.data.creation_payload) {
					creationPayload = res.data.creation_payload;
					if (res.data.creation_queue) {
						renderCreationQueue(res.data.creation_queue || []);
					} else {
						renderCreationPreview(res.data.creation_preview || {});
					}
					setStatus("Draft plan ready. Review and apply.", false);
					setProposalEnabled(true);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "No suggestions returned.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Request failed.";
				setStatus(msg, true);
			});
		});

		$btnApply.on("click", function(){
			if (inlineQuickEdit && inlineQuickEdit.selector) {
				setStatus("Applying direct text replacement...", false);
				$.post(lfAiFloating.ajax_url, {
					action: "lf_ai_inline_save",
					nonce: lfAiFloating.nonce,
					context_type: activeContextType,
					context_id: activeContextId,
					selector: inlineQuickEdit.selector,
					value: inlineQuickEdit.value
				}).done(function(res){
					if (res && res.success) {
						try {
							var node = document.querySelector(inlineQuickEdit.selector);
							if (node) node.textContent = String(inlineQuickEdit.value || "");
						} catch (e) {}
						setStatus((res.data && res.data.message) ? res.data.message : "Change applied.", false);
						inlineQuickEdit = null;
						setProposalEnabled(false);
					} else {
						setStatus((res && res.data && res.data.message) ? res.data.message : "Apply failed.", true);
					}
				}).fail(function(xhr){
					var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Apply failed.";
					setStatus(msg, true);
				});
				return;
			}
			if (lastMode === "edit_existing" && !proposed) {
				setStatus("No suggestions to apply.", true);
				return;
			}
			if (lastMode !== "edit_existing" && !creationPayload) {
				setStatus("No draft plan to apply.", true);
				return;
			}
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusApplying ? lfAiFloating.i18n.statusApplying : "Applying...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_apply",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				prompt_snippet: promptSnippet,
				proposed: JSON.stringify(proposed || {}),
				creation_payload: JSON.stringify(creationPayload || {}),
				assistant_mode: lastMode,
				assistant_cpt_type: activeAssistantCptType,
				assistant_batch_type: activeAssistantBatchType
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
				} else if (res && res.success && res.data && res.data.created) {
					var links = "";
					if (res.data.edit_link) {
						links += "<a href=\"" + escapeHtml(res.data.edit_link) + "\" target=\"_blank\" rel=\"noopener\">Open Draft</a>";
					}
					if (res.data.view_link) {
						links += (links ? " | " : "") + "<a href=\"" + escapeHtml(res.data.view_link) + "\" target=\"_blank\" rel=\"noopener\">View</a>";
					}
					$diff.html("<div class=\"lf-ai-float__row\"><div class=\"lf-ai-float__field\">Draft Created</div><div>" + links + "</div></div>").prop("hidden", false);
					setStatus((res.data.message || "Draft created successfully."), false);
					setProposalEnabled(false);
				} else if (res && res.success && res.data && res.data.created_batch) {
					var items = Array.isArray(res.data.created_items) ? res.data.created_items : [];
					var html = "<div class=\"lf-ai-float__row\"><div class=\"lf-ai-float__field\">Batch Drafts Created (" + items.length + ")</div></div>";
					items.forEach(function(item, idx){
						var links = "";
						if (item.edit_link) {
							links += "<a href=\"" + escapeHtml(item.edit_link) + "\" target=\"_blank\" rel=\"noopener\">Open Draft</a>";
						}
						if (item.view_link) {
							links += (links ? " | " : "") + "<a href=\"" + escapeHtml(item.view_link) + "\" target=\"_blank\" rel=\"noopener\">View</a>";
						}
						html += "<div class=\"lf-ai-float__row\"><div class=\"lf-ai-float__field\">#" + (idx + 1) + " " + escapeHtml(item.title || "") + "</div><div>" + links + "</div></div>";
					});
					$diff.html(html).prop("hidden", false);
					setStatus((res.data.message || "Batch drafts created."), false);
					setProposalEnabled(false);
				} else {
					setStatus((res && res.data && res.data.message) ? res.data.message : "Apply failed.", true);
				}
			}).fail(function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : "Apply failed.";
				setStatus(msg, true);
			});
		});

		$btnReject.on("click", function(){
			inlineQuickEdit = null;
			proposed = null;
			current = null;
			creationPayload = null;
			$diff.prop("hidden", true).empty();
			setProposalEnabled(false);
			setStatus("Suggestions rejected.", false);
		});

		$btnRevert.on("click", function(){
			runRollback();
		});
		$btnUndo.on("click", function(){
			runRollback();
		});
		$btnRedo.on("click", function(){
			runRedo();
		});
		$confirmNo.on("click", function(){
			pendingConfirmAction = null;
			$confirmText.text(defaultConfirmText);
			$confirmYes.text(defaultConfirmYesText);
			setConfirmOpen(false);
		});
		$confirmYes.on("click", function(){
			var action = pendingConfirmAction;
			pendingConfirmAction = null;
			$confirmText.text(defaultConfirmText);
			$confirmYes.text(defaultConfirmYesText);
			setConfirmOpen(false);
			if (typeof action === "function") {
				action();
				return;
			}
			runRollback();
		});
		$confirm.on("click", function(e){
			if (e.target === this) {
				setConfirmOpen(false);
			}
		});
		$(document).on("keydown", function(e){
			var target = e.target;
			var targetTag = target && target.tagName ? String(target.tagName).toLowerCase() : "";
			var isTypingTarget = !!(target && (target.isContentEditable || targetTag === "input" || targetTag === "textarea" || targetTag === "select"));
			var key = String(e.key || "");
			var keyLower = key.toLowerCase();
			if (!isTypingTarget && (e.metaKey || e.ctrlKey) && keyLower === "z" && !e.shiftKey) {
				e.preventDefault();
				runRollback();
				return;
			}
			if (!isTypingTarget && ((e.metaKey || e.ctrlKey) && e.shiftKey && keyLower === "z")) {
				e.preventDefault();
				runRedo();
				return;
			}
			if (!isTypingTarget && e.ctrlKey && keyLower === "y") {
				e.preventDefault();
				runRedo();
				return;
			}
			if (!isTypingTarget && (e.metaKey || e.ctrlKey) && keyLower === "s") {
				e.preventDefault();
				if (inlineActiveEl) {
					saveInlineEdit();
				} else {
					setStatus("No active inline edit to save.", false);
				}
				return;
			}
			if ((e.metaKey || e.ctrlKey) && (keyLower === "k" || ((e.shiftKey && keyLower === "p")))) {
				e.preventDefault();
				ensureCommandPalette();
				toggleCommandPalette(commandPaletteEl.hidden);
				return;
			}
			if (!isTypingTarget && ((e.shiftKey && key === "?") || key === "F1")) {
				e.preventDefault();
				showShortcutHelp();
				return;
			}
			if (!isTypingTarget && key === "/") {
				e.preventDefault();
				ensureCommandPalette();
				toggleCommandPalette(true);
				return;
			}
			if (inlineActiveEl) {
				if (e.key === "Escape" || e.keyCode === 27) {
					e.preventDefault();
					cancelInlineEdit(true);
					return;
				}
				if ((e.ctrlKey || e.metaKey) && (e.key === "Enter" || e.keyCode === 13)) {
					e.preventDefault();
					saveInlineEdit();
					return;
				}
			}
			if (!selectedSectionWrap && !isTypingTarget) {
				var first = collectSectionWrappers()[0] || null;
				if (first) {
					setSelectedSection(first);
				}
			}
			if (!isTypingTarget && selectedSectionWrap && $confirm.prop("hidden") && (!commandPaletteEl || commandPaletteEl.hidden)) {
				if ((e.altKey || e.metaKey || e.shiftKey) && key === "ArrowUp") {
					e.preventDefault();
					moveSelectedSection(-1);
					return;
				}
				if ((e.altKey || e.metaKey || e.shiftKey) && key === "ArrowDown") {
					e.preventDefault();
					moveSelectedSection(1);
					return;
				}
				if (keyLower === "k" && !e.metaKey && !e.ctrlKey) {
					e.preventDefault();
					moveSelectedSection(-1);
					return;
				}
				if (keyLower === "j" && !e.metaKey && !e.ctrlKey) {
					e.preventDefault();
					moveSelectedSection(1);
					return;
				}
				if (keyLower === "d" && !e.metaKey && !e.ctrlKey) {
					e.preventDefault();
					selectedSectionDuplicate();
					return;
				}
				if (keyLower === "h" && !e.metaKey && !e.ctrlKey) {
					e.preventDefault();
					selectedSectionToggleVisibility();
					return;
				}
				if (e.key === 'Backspace' || e.key === 'Delete') {
					e.preventDefault();
					selectedSectionDelete();
					return;
				}
			}
			if ((e.key === 'Escape' || e.keyCode === 27) && !$confirm.prop('hidden')) {
				pendingConfirmAction = null;
				$confirmText.text(defaultConfirmText);
				$confirmYes.text(defaultConfirmYesText);
				setConfirmOpen(false);
			}
			if ((e.key === 'Escape' || e.keyCode === 27) && commandPaletteEl && !commandPaletteEl.hidden) {
				toggleCommandPalette(false);
			}
		});

		try {
			var initial = window.localStorage.getItem(stateKey);
			if (initial === 'open') setAiOpen(true);
		} catch (e) {}
		try {
			var seoInitial = window.localStorage.getItem(seoStateKey);
			if (seoInitial === 'open') {
				setSeoOpen(true);
				setAiOpen(false);
			}
		} catch (e) {}
		try {
			railCollapsed = window.localStorage.getItem(railStateKey) === 'collapsed';
		} catch (e) {}
		try {
			editingEnabled = window.localStorage.getItem(editorModeKey) !== 'off';
		} catch (e) {}
		setEditorToggleUi();
		updateLauncherOffsets();
		try { window.setTimeout(updateLauncherOffsets, 180); } catch (e) {}
		$prompt.attr('placeholder', (lfAiFloating.i18n && lfAiFloating.i18n.placeholder) ? lfAiFloating.i18n.placeholder : 'Ask for specific edits...');
		setStatus((lfAiFloating.i18n && lfAiFloating.i18n.statusReady) ? lfAiFloating.i18n.statusReady : 'Ready.', false);
		setConfirmOpen(false);
		try { $mode.val('auto'); } catch (e) {}
		syncModeUi();
		if (editingEnabled) {
			buildEditorUi();
			setStatus('Click to edit text/images, drag sections to reorder, reverse columns, hide/show, duplicate, or delete.', false);
		} else {
			clearEditorUi();
			setStatus('Live mode enabled. Toggle ✎ to edit.', false);
		}
		renderSeoSnapshot();
	})(jQuery);';
}

function lf_ai_assistant_widget_fallback_js(): string {
	return '(function(){
		"use strict";
		var roots = document.querySelectorAll("[data-lf-ai-float]");
		if (!roots || !roots.length) return;
		roots.forEach(function(root){
			// If the primary jQuery controller initialized, skip fallback bindings
			// to avoid double-toggle behavior (open then immediate close).
			if (root.getAttribute("data-lf-ai-js-init") === "1") {
				return;
			}
			var panel = root.querySelector("#lf-ai-float-panel");
			var toggle = root.querySelector("[data-lf-ai-toggle]");
			var closeButtons = root.querySelectorAll("[data-lf-ai-close],[data-lf-ai-minimize]");
			if (!panel || !toggle) return;
			var key = "lfAiFloatState";
			function setOpen(open){
				panel.hidden = !open;
				toggle.setAttribute("aria-expanded", open ? "true" : "false");
				try { window.localStorage.setItem(key, open ? "open" : "closed"); } catch (e) {}
			}
			toggle.addEventListener("click", function(){
				setOpen(panel.hidden);
			});
			closeButtons.forEach(function(btn){
				btn.addEventListener("click", function(){
					setOpen(false);
				});
			});
			document.addEventListener("keydown", function(e){
				if ((e.key === "Escape" || e.keyCode === 27) && !panel.hidden) {
					setOpen(false);
				}
			});
			try {
				var current = window.localStorage.getItem(key);
				if (current === "open") setOpen(true);
			} catch (e) {}
		});
		})();';
}
