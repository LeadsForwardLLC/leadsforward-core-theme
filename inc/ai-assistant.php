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

	wp_localize_script('lf-ai-floating-assistant', 'lfAiFloating', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('lf_ai_editing'),
		'context_type' => (string) ($context['type'] ?? 'homepage'),
		'context_id' => (string) ($context['id'] ?? 'homepage'),
		'target_label' => $target_label,
		'labels' => $editable,
		'section_library' => lf_ai_assistant_section_library($context),
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
	<?php
}

function lf_ai_assistant_widget_css(): string {
	return '
		.lf-ai-float { position: fixed; right: 20px; bottom: 20px; z-index: 99999; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; display:flex; flex-direction:column; align-items:flex-end; }
		.lf-ai-float__toggle { background: linear-gradient(135deg,#4f23b4,#8348f9); color:#fff; border:0; border-radius:999px; padding:10px 14px; font-weight:600; box-shadow:0 10px 30px rgba(79,35,180,.32); cursor:pointer; display:flex; gap:8px; align-items:center; }
		.lf-ai-float__dot { width:8px; height:8px; border-radius:99px; background:#22c55e; box-shadow:0 0 0 4px rgba(34,197,94,.2); }
		.lf-ai-float__panel { width:min(440px, calc(100vw - 36px)); max-height:min(80vh, 860px); background:#fff; border:1px solid #dbe3ef; border-radius:14px; box-shadow:0 18px 55px rgba(15,23,42,.25); overflow:hidden; position:absolute; right:0; bottom:calc(100% + 10px); display:flex; flex-direction:column; }
		.lf-ai-float__panel[hidden] { display:none !important; }
		.lf-ai-float__header { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
		.lf-ai-float__header-actions { display:flex; gap:6px; }
		.lf-ai-float__icon { border:1px solid #d6c8fb; background:#fff; width:28px; height:28px; border-radius:8px; cursor:pointer; font-size:16px; line-height:1; color:#6a33e8; }
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
		.lf-ai-column-draggable { cursor:ew-resize; transition:outline-color .15s ease; }
		.lf-ai-column-draggable:hover { outline:2px dashed rgba(131,72,249,.3); outline-offset:3px; }
		.lf-ai-column-draggable.is-dragging { outline:2px solid #8348f9 !important; outline-offset:3px; opacity:.85; }
		.lf-ai-rail { position:fixed; left:12px; top:72px; z-index:99997; width:220px; max-height:calc(100vh - 94px); overflow:auto; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 12px 36px rgba(15,23,42,.18); padding:8px; }
		.lf-ai-rail.is-collapsed { width:46px; overflow:visible; }
		.lf-ai-rail__head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin:0 0 6px; }
		.lf-ai-rail__title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; margin:0; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__title { display:none; }
		.lf-ai-rail__head-actions { display:flex; gap:6px; }
		.lf-ai-rail__toggle { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:14px; line-height:1; }
		.lf-ai-rail__add { border:1px solid #d6c8fb; background:#fff; color:#6a33e8; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:16px; line-height:1; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__toggle { width:30px; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__add { display:none; }
		.lf-ai-rail__list { display:flex; flex-direction:column; gap:6px; }
		.lf-ai-rail.is-collapsed .lf-ai-rail__list { display:none; }
		.lf-ai-rail__item { border:1px solid #e2e8f0; border-radius:8px; padding:6px 8px; font-size:12px; cursor:pointer; color:#0f172a; background:#fff; }
		.lf-ai-rail__item:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-rail__item.is-active { border-color:#8348f9; background:#f5f0ff; }
		.lf-ai-rail__item small { display:block; color:#64748b; margin-top:2px; }
		.lf-ai-rail__item.is-dragging { opacity:.55; border-color:#8348f9; }
		.lf-ai-rail__library { border:1px solid #e2e8f0; border-radius:10px; padding:6px; margin:0 0 8px; background:#f8fafc; display:flex; flex-direction:column; gap:6px; }
		.lf-ai-rail__library[hidden] { display:none !important; }
		.lf-ai-rail__library-search { width:100%; border:1px solid #d6c8fb; border-radius:8px; padding:6px 8px; font-size:12px; }
		.lf-ai-rail__library-list { max-height:180px; overflow:auto; display:flex; flex-direction:column; gap:4px; }
		.lf-ai-rail__library-item { border:1px solid #e2e8f0; border-radius:8px; background:#fff; padding:6px 8px; font-size:12px; cursor:pointer; text-align:left; color:#0f172a; }
		.lf-ai-rail__library-item:hover { border-color:#c4b5fd; background:#faf7ff; }
		.lf-ai-rail__library-item[disabled] { opacity:.5; cursor:default; }
		.lf-ai-command { position:fixed; left:50%; top:16%; transform:translateX(-50%); z-index:100001; width:min(560px, calc(100vw - 26px)); background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 20px 50px rgba(15,23,42,.28); padding:10px; }
		.lf-ai-command[hidden] { display:none !important; }
		.lf-ai-command__input { width:100%; border:1px solid #d6c8fb; border-radius:8px; padding:10px; font-size:14px; }
		.lf-ai-command__list { margin-top:8px; max-height:300px; overflow:auto; display:flex; flex-direction:column; gap:6px; }
		.lf-ai-command__row { border:1px solid #e2e8f0; border-radius:8px; padding:8px; font-size:13px; cursor:pointer; }
		.lf-ai-command__row:hover, .lf-ai-command__row.is-active { border-color:#8348f9; background:#f6f2ff; }
		.lf-ai-command__hint { margin-top:8px; font-size:11px; color:#64748b; }
		.lf-ai-float__confirm { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.4); z-index:5; padding:12px; pointer-events:auto; }
		.lf-ai-float__confirm[hidden] { display:none !important; pointer-events:none !important; }
		.lf-ai-float__confirm-card { width:100%; max-width:360px; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 10px 34px rgba(15,23,42,.28); padding:14px; }
		.lf-ai-float__confirm-text { margin:0 0 10px; color:#1e293b; font-size:13px; line-height:1.45; }
		.lf-ai-float__confirm-actions { display:flex; gap:8px; justify-content:flex-end; }
		@media (max-width: 782px) {
			.lf-ai-float { right:12px; bottom:12px; left:12px; }
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
		var $root = $("[data-lf-ai-float]");
		if (!$root.length || typeof lfAiFloating === "undefined") return;
		$root.attr("data-lf-ai-js-init", "1");
		var $toggle = $root.find("[data-lf-ai-toggle]");
		var $panel = $root.find("#lf-ai-float-panel");
		var $prompt = $root.find("[data-lf-ai-prompt]");
		var $status = $root.find("[data-lf-ai-status]");
		var $diff = $root.find("[data-lf-ai-diff]");
		var $btnGenerate = $root.find("[data-lf-ai-generate]");
		var $btnApply = $root.find("[data-lf-ai-apply]");
		var $btnReject = $root.find("[data-lf-ai-reject]");
		var $btnRevert = $root.find("[data-lf-ai-revert]");
		var $btnUndo = $root.find("[data-lf-ai-undo]");
		var $btnRedo = $root.find("[data-lf-ai-redo]");
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
		var activeRailDragSectionId = "";
		var railLibraryOpen = false;
		var suppressInlineClickUntil = 0;
		var inlineCandidateSelector = "main h1,main h2,main h3,main h4,main h5,main h6,main p,main li,main blockquote,main figcaption";
		var inlineImageCandidateSelector = "main img";
		var mediaFrame = null;

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
		function setStatus(msg, isError) {
			$status.text(msg || "");
			$status.toggleClass("is-error", !!isError);
		}
		function setOpen(open) {
			$panel.prop("hidden", !open);
			$toggle.attr("aria-expanded", open ? "true" : "false");
			if (!open) {
				saveInlineEdit();
			}
			try { window.localStorage.setItem(stateKey, open ? "open" : "closed"); } catch (e) {}
		}
		function isDragBlockedTarget(target) {
			if (!target) return false;
			if (target.closest(".lf-ai-float")) return true;
			if (target.closest("a,button,input,textarea,select,label,[contenteditable=\"true\"],.lf-ai-inline-editor-ignore")) return true;
			return false;
		}
		function inlineNodeEligible(node) {
			if (!node || !node.textContent) return false;
			if (node.closest(".lf-ai-float")) return false;
			if (node.closest("nav, footer, [aria-hidden=\"true\"]")) return false;
			if (node.closest(".site-header, .site-footer, #masthead, #colophon")) return false;
			if (node.closest("button, a, label, script, style, noscript")) return false;
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
			});
		}
		function inlineImageEligible(img) {
			if (!img || !img.getAttribute) return false;
			if (img.closest(".lf-ai-float")) return false;
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
			collectSectionWrappers().forEach(function(node){
				node.classList.remove("lf-ai-section-active");
			});
			selectedSectionWrap = wrap || null;
			if (selectedSectionWrap) {
				selectedSectionWrap.classList.add("lf-ai-section-active");
			}
			refreshSectionRail();
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
					toggle.textContent = railCollapsed ? "\u2261" : "\u2212";
					toggle.setAttribute("aria-label", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
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
				toggle.textContent = railCollapsed ? "\u2261" : "\u2212";
				toggle.setAttribute("aria-label", railCollapsed ? "Expand structure rail" : "Minimize structure rail");
			}
			return sectionRailEl;
		}
		function addSectionFromLibrary(sectionType) {
			var type = String(sectionType || "");
			if (!type) return;
			setStatus("Adding section...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_add_section",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				section_type: type,
				after_section_id: selectedSectionWrap ? String(selectedSectionWrap.getAttribute("data-lf-section-id") || "") : ""
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
				if (visibleIds[id] && activeContextType === "homepage") {
					btn.disabled = true;
					btn.title = "Already on this homepage";
				}
				btn.addEventListener("click", function(){
					if (btn.disabled) return;
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
			list.innerHTML = "";
			wraps.forEach(function(wrap){
				var row = document.createElement("div");
				var sectionId = String(wrap.getAttribute("data-lf-section-id") || "");
				var isHidden = String(wrap.getAttribute("data-lf-section-visible") || "1") === "0";
				row.className = "lf-ai-rail__item" + (wrap === selectedSectionWrap ? " is-active" : "");
				row.setAttribute("draggable", "true");
				row.setAttribute("data-lf-rail-section-id", sectionId);
				row.innerHTML = escapeHtml(sectionLabelForWrap(wrap)) + "<small>" + escapeHtml(String(wrap.getAttribute("data-lf-section-type") || "section")) + (isHidden ? " • hidden" : "") + "</small>";
				row.addEventListener("click", function(){
					setSelectedSection(wrap);
					try { wrap.scrollIntoView({ behavior: "smooth", block: "center" }); } catch (e) {}
				});
				row.addEventListener("dragstart", function(e){
					activeRailDragSectionId = sectionId;
					row.classList.add("is-dragging");
					if (e.dataTransfer) {
						e.dataTransfer.effectAllowed = "move";
						e.dataTransfer.setData("text/plain", sectionId);
					}
				});
				row.addEventListener("dragover", function(e){
					if (!activeRailDragSectionId || activeRailDragSectionId === sectionId) return;
					e.preventDefault();
				});
				row.addEventListener("drop", function(e){
					if (!activeRailDragSectionId || activeRailDragSectionId === sectionId) return;
					e.preventDefault();
					var dragWrap = sectionWrapById(activeRailDragSectionId);
					var targetWrap = sectionWrapById(sectionId);
					if (!dragWrap || !targetWrap || !dragWrap.parentNode) return;
					var rect = row.getBoundingClientRect();
					var after = e.clientY > (rect.top + rect.height / 2);
					if (after) {
						targetWrap.parentNode.insertBefore(dragWrap, targetWrap.nextSibling);
					} else {
						targetWrap.parentNode.insertBefore(dragWrap, targetWrap);
					}
					setSelectedSection(dragWrap);
					persistSectionOrder();
				});
				row.addEventListener("dragend", function(){
					row.classList.remove("is-dragging");
					activeRailDragSectionId = "";
				});
				list.appendChild(row);
			});
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
		function sectionSupportsColumnSwap(sectionType) {
			var type = String(sectionType || "");
			return ["service_details", "content_image", "content_image_a", "image_content", "image_content_b", "content_image_c"].indexOf(type) !== -1;
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
					wrap.parentNode.insertBefore(clone, wrap.nextSibling);
					buildSectionTargets();
					buildSectionControls();
					buildSectionColumnSwapTargets();
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
					if (!activeDragSection || wrap === activeDragSection) return;
					e.preventDefault();
					reorderSectionInDom(wrap, e.clientY);
				};
				wrap.ondrop = function(e){
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
			var value = String(el.textContent || "").trim();
			if (!selector) {
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
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_inline_save",
				nonce: lfAiFloating.nonce,
				context_type: activeContextType,
				context_id: activeContextId,
				selector: selector,
				value: value
			}).done(function(res){
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
			openConfirm("Delete this section? You can undo after deleting.", "Delete Section", function(){
				persistSectionDelete(targetWrap);
			});
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
					if (commandRows[commandActiveIndex] && typeof commandRows[commandActiveIndex].run === "function") {
						commandRows[commandActiveIndex].run();
						toggleCommandPalette(false);
					}
				} else if (e.key === "Escape") {
					e.preventDefault();
					toggleCommandPalette(false);
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
				{ label: "Focus AI prompt", enabled: true, run: function(){ setOpen(true); try { $prompt.trigger("focus"); } catch (e) {} } },
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
				row.textContent = String(item.label || "");
				row.addEventListener("mouseenter", function(){
					commandActiveIndex = idx;
					refreshCommandPalette();
				});
				row.addEventListener("click", function(){
					item.run();
					toggleCommandPalette(false);
				});
				commandListEl.appendChild(row);
			});
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

		$toggle.on("click", function(){ setConfirmOpen(false); setOpen($panel.prop("hidden")); });
		$root.find("[data-lf-ai-close],[data-lf-ai-minimize]").on("click", function(){ setConfirmOpen(false); setOpen(false); });
		$root.find("[data-lf-ai-preset]").on("click", function(){
			$prompt.val($(this).attr("data-lf-ai-preset") || "").trigger("focus");
		});
		$(document).on("click", "[data-lf-inline-image=\"1\"]", function(e){
			if (Date.now() < suppressInlineClickUntil) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			beginInlineImageReplace(this);
		});
		$(document).on("click", "[data-lf-inline-editable=\"1\"]", function(e){
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
			if ($(target).closest("[data-lf-ai-float]").length) {
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
				target_reference: String($targetRef.val() || "").trim()
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
				if (res && res.success && res.data && res.data.mode === "edit_existing" && res.data.proposed) {
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
			if ((e.metaKey || e.ctrlKey) && (keyLower === "k" || ((e.shiftKey && keyLower === "p")))) {
				e.preventDefault();
				ensureCommandPalette();
				toggleCommandPalette(commandPaletteEl.hidden);
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
				if (e.key === "Backspace" || e.key === "Delete") {
					e.preventDefault();
					selectedSectionDelete();
					return;
				}
			}
			if ((e.key === "Escape" || e.keyCode === 27) && !$confirm.prop("hidden")) {
				pendingConfirmAction = null;
				$confirmText.text(defaultConfirmText);
				$confirmYes.text(defaultConfirmYesText);
				setConfirmOpen(false);
			}
			if ((e.key === "Escape" || e.keyCode === 27) && commandPaletteEl && !commandPaletteEl.hidden) {
				toggleCommandPalette(false);
			}
		});

		try {
			var initial = window.localStorage.getItem(stateKey);
			if (initial === "open") setOpen(true);
		} catch (e) {}
		try {
			railCollapsed = window.localStorage.getItem(railStateKey) === "collapsed";
		} catch (e) {}
		$prompt.attr("placeholder", (lfAiFloating.i18n && lfAiFloating.i18n.placeholder) ? lfAiFloating.i18n.placeholder : "Ask for specific edits...");
		setStatus((lfAiFloating.i18n && lfAiFloating.i18n.statusReady) ? lfAiFloating.i18n.statusReady : "Ready.", false);
		setConfirmOpen(false);
		try { $mode.val("auto"); } catch (e) {}
		syncModeUi();
		buildInlineTargets();
		buildInlineImageTargets();
		buildSectionTargets();
		buildSectionControls();
		buildSectionColumnSwapTargets();
		refreshSectionRail();
		var firstWrap = collectSectionWrappers()[0] || null;
		if (firstWrap) {
			setSelectedSection(firstWrap);
		}
		setStatus("Click to edit text/images, drag sections to reorder, reverse columns, hide/show, duplicate, or delete.", false);
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
