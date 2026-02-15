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
		'i18n' => [
			'statusReady' => __('Ready.', 'leadsforward-core'),
			'statusGenerating' => __('Generating suggestions...', 'leadsforward-core'),
			'statusApplying' => __('Applying changes...', 'leadsforward-core'),
			'statusReverting' => __('Reverting last AI change...', 'leadsforward-core'),
			'statusRedoing' => __('Re-applying last reverted change...', 'leadsforward-core'),
			'statusLayoutOn' => __('Layout mode on. Drag sections by the handle to reorder.', 'leadsforward-core'),
			'statusLayoutOff' => __('Layout mode off.', 'leadsforward-core'),
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
					<button type="button" class="lf-ai-float__icon" data-lf-ai-layout aria-label="<?php esc_attr_e('Toggle layout mode', 'leadsforward-core'); ?>" title="<?php esc_attr_e('Toggle layout mode for drag and drop section ordering', 'leadsforward-core'); ?>">⇅</button>
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
		[data-lf-section-wrap="1"] { position:relative; }
		.lf-ai-layout-handle { display:none; position:absolute; top:10px; right:10px; width:28px; height:28px; border:1px solid #d6c8fb; border-radius:8px; background:#fff; color:#6a33e8; font-weight:700; align-items:center; justify-content:center; cursor:grab; z-index:4; box-shadow:0 4px 12px rgba(15,23,42,.15); }
		body.lf-ai-layout-mode [data-lf-section-wrap="1"] { outline:2px dashed rgba(131,72,249,.45); outline-offset:3px; }
		body.lf-ai-layout-mode [data-lf-section-wrap="1"].is-dragging { opacity:.55; }
		body.lf-ai-layout-mode .lf-ai-layout-handle { display:inline-flex; }
		body.lf-ai-layout-mode [data-lf-inline-editable="1"] { outline:none !important; background:transparent !important; }
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
		var $btnLayout = $root.find("[data-lf-ai-layout]");
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
		var inlineActiveEl = null;
		var inlineOriginalText = "";
		var inlineIsSaving = false;
		var layoutMode = false;
		var activeDragSection = null;
		var inlineCandidateSelector = "main h1,main h2,main h3,main h4,main h5,main h6,main p,main li,main blockquote,main figcaption";
		var inlineImageCandidateSelector = "main img";
		var mediaFrame = null;

		function escapeHtml(text) {
			var div = document.createElement("div");
			div.textContent = String(text || "");
			return div.innerHTML;
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
				setLayoutMode(false);
			}
			try { window.localStorage.setItem(stateKey, open ? "open" : "closed"); } catch (e) {}
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
			if (!img || layoutMode || inlineIsSaving) return;
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
				var handle = wrap.querySelector(".lf-ai-layout-handle");
				if (!handle) {
					handle = document.createElement("button");
					handle.type = "button";
					handle.className = "lf-ai-layout-handle";
					handle.setAttribute("data-lf-ai-layout-handle", "1");
					handle.setAttribute("aria-label", "Drag section");
					handle.title = "Drag to reorder section";
					handle.textContent = "⋮⋮";
					wrap.appendChild(handle);
				}
				handle.onmousedown = function(e){ e.preventDefault(); };
				handle.onclick = function(e){ e.preventDefault(); e.stopPropagation(); };
				handle.onmouseenter = function(){ handle.style.cursor = layoutMode ? "grab" : "default"; };
				wrap.setAttribute("draggable", layoutMode ? "true" : "false");
				wrap.ondragstart = function(e){
					if (!layoutMode) {
						e.preventDefault();
						return;
					}
					var target = e.target;
					if (!target || !target.closest("[data-lf-ai-layout-handle=\"1\"]")) {
						e.preventDefault();
						return;
					}
					activeDragSection = wrap;
					wrap.classList.add("is-dragging");
					if (e.dataTransfer) {
						e.dataTransfer.effectAllowed = "move";
						e.dataTransfer.setData("text/plain", String(wrap.getAttribute("data-lf-section-id") || ""));
					}
				};
				wrap.ondragover = function(e){
					if (!layoutMode || !activeDragSection || wrap === activeDragSection) return;
					e.preventDefault();
					reorderSectionInDom(wrap, e.clientY);
				};
				wrap.ondrop = function(e){
					if (!layoutMode) return;
					e.preventDefault();
				};
				wrap.ondragend = function(){
					if (activeDragSection) {
						activeDragSection.classList.remove("is-dragging");
					}
					activeDragSection = null;
					if (layoutMode) {
						persistSectionOrder();
					}
				};
			});
		}
		function setLayoutMode(on) {
			layoutMode = !!on;
			$btnLayout.attr("aria-pressed", layoutMode ? "true" : "false");
			if (layoutMode) {
				document.body.classList.add("lf-ai-layout-mode");
				cancelInlineEdit(false);
				setStatus((lfAiFloating.i18n && lfAiFloating.i18n.statusLayoutOn) ? lfAiFloating.i18n.statusLayoutOn : "Layout mode on.", false);
			} else {
				document.body.classList.remove("lf-ai-layout-mode");
				setStatus((lfAiFloating.i18n && lfAiFloating.i18n.statusLayoutOff) ? lfAiFloating.i18n.statusLayoutOff : "Layout mode off.", false);
			}
			buildSectionTargets();
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

		$toggle.on("click", function(){ setConfirmOpen(false); setOpen($panel.prop("hidden")); });
		$root.find("[data-lf-ai-close],[data-lf-ai-minimize]").on("click", function(){ setConfirmOpen(false); setOpen(false); });
		$root.find("[data-lf-ai-preset]").on("click", function(){
			$prompt.val($(this).attr("data-lf-ai-preset") || "").trigger("focus");
		});
		$btnLayout.on("click", function(){
			setLayoutMode(!layoutMode);
		});
		$(document).on("click", "[data-lf-inline-image=\"1\"]", function(e){
			if (layoutMode) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			beginInlineImageReplace(this);
		});
		$(document).on("click", "[data-lf-inline-editable=\"1\"]", function(e){
			if (layoutMode) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			beginInlineEdit(this);
		});
		$(document).on("click", function(e){
			if (layoutMode) {
				return;
			}
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
			promptSnippet = prompt.length > 80 ? prompt.slice(0,77) + "..." : prompt;
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusGenerating ? lfAiFloating.i18n.statusGenerating : "Generating...", false);
			$diff.prop("hidden", true).empty();
			setProposalEnabled(false);
			proposed = null;
			current = null;
			creationPayload = null;
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
		$confirmNo.on("click", function(){ setConfirmOpen(false); });
		$confirmYes.on("click", function(){
			setConfirmOpen(false);
			runRollback();
		});
		$confirm.on("click", function(e){
			if (e.target === this) {
				setConfirmOpen(false);
			}
		});
		$(document).on("keydown", function(e){
			if (layoutMode && (e.key === "Escape" || e.keyCode === 27)) {
				e.preventDefault();
				setLayoutMode(false);
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
			if ((e.key === "Escape" || e.keyCode === 27) && !$confirm.prop("hidden")) {
				setConfirmOpen(false);
			}
		});

		try {
			var initial = window.localStorage.getItem(stateKey);
			if (initial === "open") setOpen(true);
		} catch (e) {}
		$prompt.attr("placeholder", (lfAiFloating.i18n && lfAiFloating.i18n.placeholder) ? lfAiFloating.i18n.placeholder : "Ask for specific edits...");
		setStatus((lfAiFloating.i18n && lfAiFloating.i18n.statusReady) ? lfAiFloating.i18n.statusReady : "Ready.", false);
		setConfirmOpen(false);
		try { $mode.val("auto"); } catch (e) {}
		syncModeUi();
		buildInlineTargets();
		buildInlineImageTargets();
		buildSectionTargets();
		$btnLayout.attr("aria-pressed", "false");
		setStatus("Direct edit is always available. Click page text and click away to auto-save.", false);
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
