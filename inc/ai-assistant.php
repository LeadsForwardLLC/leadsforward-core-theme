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
add_action('admin_footer', 'lf_ai_assistant_render_floating_widget');

function lf_ai_assistant_assets(string $hook): void {
	if (!is_admin() || !current_user_can('edit_theme_options')) {
		return;
	}

	wp_register_script('lf-ai-floating-assistant', '', ['jquery'], LF_THEME_VERSION, true);
	wp_enqueue_script('lf-ai-floating-assistant');

	$context = lf_ai_assistant_widget_context();
	$editable = function_exists('lf_get_ai_editable_fields') ? lf_get_ai_editable_fields($context['id']) : [];
	if (empty($editable) && function_exists('lf_get_ai_editable_fields')) {
		$editable = lf_get_ai_editable_fields('homepage');
	}

	wp_localize_script('lf-ai-floating-assistant', 'lfAiFloating', [
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('lf_ai_editing'),
		'context_type' => (string) ($context['type'] ?? 'homepage'),
		'context_id' => (string) ($context['id'] ?? 'homepage'),
		'labels' => $editable,
		'i18n' => [
			'statusReady' => __('Ready.', 'leadsforward-core'),
			'statusGenerating' => __('Generating suggestions...', 'leadsforward-core'),
			'statusApplying' => __('Applying changes...', 'leadsforward-core'),
			'statusReverting' => __('Reverting last AI change...', 'leadsforward-core'),
			'confirmRevert' => __('Revert the most recent AI change on this page? This cannot be undone.', 'leadsforward-core'),
			'placeholder' => __('Ask for precise copy edits, SEO rewrites, CTA improvements, or schema-safe content upgrades...', 'leadsforward-core'),
		],
	]);

	wp_add_inline_script('lf-ai-floating-assistant', lf_ai_assistant_widget_js());
	wp_register_style('lf-ai-floating-assistant', false, [], LF_THEME_VERSION);
	wp_enqueue_style('lf-ai-floating-assistant');
	wp_add_inline_style('lf-ai-floating-assistant', lf_ai_assistant_widget_css());
}

function lf_ai_assistant_widget_context(): array {
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
	if (!is_admin() || !current_user_can('edit_theme_options')) {
		return;
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
				<div class="lf-ai-float__presets">
					<button type="button" class="button button-small" data-lf-ai-preset="<?php esc_attr_e('Tighten this page copy for higher conversions and local trust signals.', 'leadsforward-core'); ?>"><?php esc_html_e('Optimize Copy', 'leadsforward-core'); ?></button>
					<button type="button" class="button button-small" data-lf-ai-preset="<?php esc_attr_e('Rewrite metadata and opening copy to better match transactional local intent.', 'leadsforward-core'); ?>"><?php esc_html_e('SERP Intent', 'leadsforward-core'); ?></button>
					<button type="button" class="button button-small" data-lf-ai-preset="<?php esc_attr_e('Improve CTA language for urgency, clarity, and lead quality.', 'leadsforward-core'); ?>"><?php esc_html_e('Improve CTA', 'leadsforward-core'); ?></button>
				</div>
				<textarea class="lf-ai-float__prompt" rows="4" data-lf-ai-prompt placeholder="<?php esc_attr_e('Ask for specific edits...', 'leadsforward-core'); ?>"></textarea>
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
		.lf-ai-float__panel { width:min(440px, calc(100vw - 36px)); max-height:min(70vh, 760px); background:#fff; border:1px solid #dbe3ef; border-radius:14px; box-shadow:0 18px 55px rgba(15,23,42,.25); overflow:hidden; margin-top:10px; position:relative; }
		.lf-ai-float__header { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
		.lf-ai-float__header-actions { display:flex; gap:6px; }
		.lf-ai-float__icon { border:1px solid #d6c8fb; background:#fff; width:28px; height:28px; border-radius:8px; cursor:pointer; font-size:16px; line-height:1; color:#6a33e8; }
		.lf-ai-float__body { padding:12px; display:flex; flex-direction:column; gap:10px; }
		.lf-ai-float__presets { display:flex; flex-wrap:wrap; gap:6px; }
		.lf-ai-float__prompt { width:100%; resize:vertical; min-height:88px; border:1px solid #d6c8fb; border-radius:10px; padding:10px; font-size:13px; }
		.lf-ai-float__prompt:focus { border-color:#8348f9; box-shadow:0 0 0 1px #8348f9; outline:none; }
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
		.lf-ai-float__confirm { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.4); z-index:5; padding:12px; }
		.lf-ai-float__confirm[hidden] { display:none !important; }
		.lf-ai-float__confirm-card { width:100%; max-width:360px; background:#fff; border:1px solid #dbe3ef; border-radius:12px; box-shadow:0 10px 34px rgba(15,23,42,.28); padding:14px; }
		.lf-ai-float__confirm-text { margin:0 0 10px; color:#1e293b; font-size:13px; line-height:1.45; }
		.lf-ai-float__confirm-actions { display:flex; gap:8px; justify-content:flex-end; }
		@media (max-width: 782px) {
			.lf-ai-float { right:12px; bottom:12px; left:12px; }
			.lf-ai-float__toggle { width:100%; justify-content:center; }
			.lf-ai-float__panel { width:100%; }
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
		var $confirm = $root.find("[data-lf-ai-confirm]");
		var $confirmText = $root.find("[data-lf-ai-confirm-text]");
		var $confirmYes = $root.find("[data-lf-ai-confirm-yes]");
		var $confirmNo = $root.find("[data-lf-ai-confirm-no]");
		var proposed = null;
		var current = null;
		var labels = lfAiFloating.labels || {};
		var promptSnippet = "";

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
		function setProposalEnabled(enabled){
			$btnApply.prop("disabled", !enabled);
			$btnReject.prop("disabled", !enabled);
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
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_generate",
				nonce: lfAiFloating.nonce,
				context_type: lfAiFloating.context_type || "homepage",
				context_id: lfAiFloating.context_id || "homepage",
				prompt: prompt
			}).done(function(res){
				if (res && res.success && res.data && res.data.proposed) {
					proposed = res.data.proposed;
					current = res.data.current || {};
					labels = res.data.labels || labels;
					renderDiff();
					setStatus("Suggestions ready. Review and apply.", false);
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
			if (!proposed) {
				setStatus("No suggestions to apply.", true);
				return;
			}
			setStatus(lfAiFloating.i18n && lfAiFloating.i18n.statusApplying ? lfAiFloating.i18n.statusApplying : "Applying...", false);
			$.post(lfAiFloating.ajax_url, {
				action: "lf_ai_apply",
				nonce: lfAiFloating.nonce,
				context_type: lfAiFloating.context_type || "homepage",
				context_id: lfAiFloating.context_id || "homepage",
				prompt_snippet: promptSnippet
			}).done(function(res){
				if (res && res.success && res.data && res.data.reload) {
					window.location.reload();
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
	})(jQuery);';
}
