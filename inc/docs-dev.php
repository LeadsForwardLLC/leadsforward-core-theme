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
		<p><?php esc_html_e('Full release notes live in docs/TEAM_CHANGELOG.md in the theme repo. Highlights for recent fleet work:', 'leadsforward-core'); ?></p>
		<ul>
			<li><?php esc_html_e('v0.1.178: Full documentation audit — DOCUMENTATION_MAP, 10_SITEMAP_SYNC renumber, Theme Documentation label aligned, 05_THEME_INTEGRATION structure fix.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.177: Documentation cleanup — archived superpowers, slim README, path fixes, removed orphan lf-docs.php.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.176: Fleet page templates, header nav contract, canonical slugs, reviews gating, expanded wp-admin playbook.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('v0.1.176: Testimonial save/trash immediately publishes or drafts the Reviews page and refreshes the More menu.', 'leadsforward-core'); ?></li>
			<li><?php esc_html_e('Fleet controller push: POST to /wp-json/lf/v1/fleet/push; see docs/05_THEME_INTEGRATION.md.', 'leadsforward-core'); ?></li>
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
			<li><code>docs/DOCUMENTATION_MAP.md</code> — <?php esc_html_e('which doc is canonical per topic (avoid redundancy)', 'leadsforward-core'); ?></li>
			<li><code>docs/README.md</code> — <?php esc_html_e('developer docs index (Git repo)', 'leadsforward-core'); ?></li>
			<li><code>inc/manifester/docs/</code> — <?php esc_html_e('n8n workflow JSON, manifest schema, vision spec', 'leadsforward-core'); ?></li>
		</ul>
	</section>
	<?php
}

