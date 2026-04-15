<?php
/**
 * Developer docs content (technical reference + changelog pointers).
 *
 * This is intentionally separate from the operator playbook so we can grow a
 * deep technical reference without bloating the main docs flow.
 *
 * @package LeadsForward_Core
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_docs_render_dev_sections(): void {
	?>
	<section class="lf-docs__section" id="dev-overview">
		<h2><?php esc_html_e('Developer docs', 'leadsforward-core'); ?></h2>
		<p><?php esc_html_e('This tab is for theme developers and advanced operators: internal storage keys, update channels, and implementation details that power the UI.', 'leadsforward-core'); ?></p>
	</section>

	<section class="lf-docs__section" id="dev-recent-changes">
		<h2><?php esc_html_e('Recent changes (high level)', 'leadsforward-core'); ?></h2>
		<ul>
			<li><?php esc_html_e('v0.1.46: Header layouts now have real CSS variants (centered layout), the promo top bar is shown based on enabled + text (not layout), and the Header floater adds a top bar background color picker (brand swatches + custom).', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.46: Service Intro empty-state keeps “+ Add service” available and allows saving an explicit empty selection.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.46: Layout History adds Preview for non-destructive viewing of a restore point before restoring.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.46: Front-end assistant boot is hardened against wp-i18n ordering issues; i18n inline stubs prevent fatal init failures under some optimizers.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.45: Inline editor toolbar can be dismissed via outside click or the new ✕ button; AI assistant init captures errors so fallback toggles still open when JS fails.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.45: Rich-text icon picker adds size presets; inline SVG sizing rules prevent clipping in tight line boxes.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.45: Header phone icon alignment tightened; service intro selection re-saves on picker close; hero proof list prefers the Authority Split column; process titles split on colon/dash for partial bolding.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.42: Global header layout variants (modern, centered, topbar) and optional promo top bar in inc/header-settings.php; front-end Header floater persists via wp_ajax_lf_ai_update_header_settings in inc/ai-editing/admin-ui.php.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.42: Homepage-scoped saves for hero proof bullets and Conversion Stack hero pills; service_details_checklist / _secondary persistence aligned between inc/ai-editing/admin-ui.php and the front-end checklist DOM in inc/ai-assistant.php.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.42: Rich-text Insert icon inserts at caret; lf_icon() used for inline preview markup after sanitization (see inc/ai-editing/admin-ui.php helpers).', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('Fleet controller push: optional POST to each client at /wp-json/lf/v1/fleet/push (HMAC headers, JSON body with optional override) for immediate check-and-install; see docs/05_THEME_INTEGRATION.md.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('Front-end inline editor: rich-text toolbar outside-click dismissal, assistant boot hardening, and per-section list persistence guardrails.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('Service Intro: selection empty-state is now respected; service library returns short descriptions so add/re-add is deterministic.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('Service details checklist: capped to 5 items across UI + persistence to prevent odd layouts on older generations.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('Process steps: CPT titles written as “Label: details” now render with only the label bold, matching line-based behavior.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('Header/menu phone icon: CSS adjustments to prevent SVG clipping in tight line-box contexts.', 'leadsforward-core'); ?></li>
		</ul>
	</section>

	<section class="lf-docs__section" id="dev-reference-files">
		<h2><?php esc_html_e('Reference (repo files)', 'leadsforward-core'); ?></h2>
		<ul>
			<li><code>inc/ai-assistant.php</code> — <?php esc_html_e('floating assistant + front-end editor controls + inline persistence', 'leadsforward-core'); ?></li>
			<li><code>inc/ai-editing/admin-ui.php</code> — <?php esc_html_e('AJAX persistence endpoints (inline save, lists, checklists, libraries)', 'leadsforward-core'); ?></li>
			<li><code>inc/sections.php</code> — <?php esc_html_e('section registry + sanitization + render functions', 'leadsforward-core'); ?></li>
			<li><code>inc/fleet-updates.php</code> — <?php esc_html_e('private controller update channel, cron, signed push REST entrypoint (via inc/fleet-updates/push-*.php)', 'leadsforward-core'); ?></li>
			<li><code>inc/fleet-controller.php</code> — <?php esc_html_e('controller-side API + wp_remote_request push helper', 'leadsforward-core'); ?></li>
			<li><code>docs/README.md</code> — <?php esc_html_e('full developer docs index (Git repo)', 'leadsforward-core'); ?></li>
		</ul>
	</section>
	<?php
}

