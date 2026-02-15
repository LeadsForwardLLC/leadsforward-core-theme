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
					<button type="button" class="button" data-lf-ai-revert><?php esc_html_e('Revert Last AI Change', 'leadsforward-core'); ?></button>
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
		.lf-ai-float { position: fixed; right: 20px; bottom: 20px; z-index: 99999; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; }
		.lf-ai-float__toggle { background: linear-gradient(135deg,#4f23b4,#8348f9); color:#fff; border:0; border-radius:999px; padding:10px 14px; font-weight:600; box-shadow:0 10px 30px rgba(79,35,180,.32); cursor:pointer; display:flex; gap:8px; align-items:center; }
		.lf-ai-float__dot { width:8px; height:8px; border-radius:99px; background:#22c55e; box-shadow:0 0 0 4px rgba(34,197,94,.2); }
		.lf-ai-float__panel { width:min(440px, calc(100vw - 36px)); max-height:min(80vh, 860px); background:#fff; border:1px solid #dbe3ef; border-radius:14px; box-shadow:0 18px 55px rgba(15,23,42,.25); overflow:hidden; margin-top:10px; position:relative; display:flex; flex-direction:column; }
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
		.lf-ai-float__confirm { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.4); z-index:5; padding:12px; pointer-events:auto; }
		.lf-ai-float__confirm[hidden] { display:none !important; pointer-events:none !important; }
		.lf-ai-float__confirm-card { width:100%; max-width:360px; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 10px 34px rgba(15,23,42,.28); padding:14px; }
		.lf-ai-float__confirm-text { margin:0 0 10px; color:#1e293b; font-size:13px; line-height:1.45; }
		.lf-ai-float__confirm-actions { display:flex; gap:8px; justify-content:flex-end; }
		@media (max-width: 782px) {
			.lf-ai-float { right:12px; bottom:12px; left:12px; }
			.lf-ai-float__toggle { width:100%; justify-content:center; }
			.lf-ai-float__panel { width:100%; }
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
		var $toggle = $root.find("[data-lf-ai-toggle]");
		var $panel = $root.find("#lf-ai-float-panel");
		var $prompt = $root.find("[data-lf-ai-prompt]");
		var $status = $root.find("[data-lf-ai-status]");
		var $diff = $root.find("[data-lf-ai-diff]");
		var $btnGenerate = $root.find("[data-lf-ai-generate]");
		var $btnApply = $root.find("[data-lf-ai-apply]");
		var $btnReject = $root.find("[data-lf-ai-reject]");
		var $btnRevert = $root.find("[data-lf-ai-revert]");
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
			try { window.localStorage.setItem(stateKey, open ? "open" : "closed"); } catch (e) {}
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
				context_type: lfAiFloating.context_type || "homepage",
				context_id: lfAiFloating.context_id || "homepage"
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

		$toggle.on("click", function(){ setConfirmOpen(false); setOpen($panel.prop("hidden")); });
		$root.find("[data-lf-ai-close],[data-lf-ai-minimize]").on("click", function(){ setConfirmOpen(false); setOpen(false); });
		$root.find("[data-lf-ai-preset]").on("click", function(){
			$prompt.val($(this).attr("data-lf-ai-preset") || "").trigger("focus");
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
			$confirmText.text((lfAiFloating.i18n && lfAiFloating.i18n.confirmRevert) ? lfAiFloating.i18n.confirmRevert : "Revert the most recent AI change on this page? This cannot be undone.");
			setConfirmOpen(true);
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
	})(jQuery);';
}

function lf_ai_assistant_widget_fallback_js(): string {
	return '(function(){
		"use strict";
		var roots = document.querySelectorAll("[data-lf-ai-float]");
		if (!roots || !roots.length) return;
		roots.forEach(function(root){
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
