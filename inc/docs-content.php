<?php
/**
 * Shared theme documentation markup (admin Theme Docs + public /theme-docs/ page).
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

require_once LF_THEME_DIR . '/inc/docs-dev.php';

/**
 * @param string $context 'admin' adds a permalink tip; 'public' is neutral.
 */
function lf_docs_render_main_sections(string $context = 'public'): void {
	$is_admin = $context === 'admin';
	?>
	<section class="lf-docs" data-lf-docs-root="1">
		<div class="lf-docs__tabs" role="tablist" aria-label="<?php esc_attr_e('Documentation tabs', 'leadsforward-core'); ?>">
			<button type="button" class="lf-docs__tab is-active" role="tab" aria-selected="true" aria-controls="lf-docs-tab-operator" id="lf-docs-tab-operator-btn" data-lf-docs-tab="operator"><?php esc_html_e('Operator docs', 'leadsforward-core'); ?></button>
			<button type="button" class="lf-docs__tab" role="tab" aria-selected="false" aria-controls="lf-docs-tab-developer" id="lf-docs-tab-developer-btn" data-lf-docs-tab="developer"><?php esc_html_e('Developer docs', 'leadsforward-core'); ?></button>
		</div>
		<aside class="lf-docs__sidebar" aria-label="<?php esc_attr_e('Documentation navigation', 'leadsforward-core'); ?>">
			<h2 class="lf-docs__title"><?php esc_html_e('Knowledge base', 'leadsforward-core'); ?></h2>
			<nav class="lf-docs__nav">
				<?php if ($is_admin && current_user_can('edit_theme_options')) : ?>
					<a href="#admin-shortcuts"><?php esc_html_e('Admin quick links', 'leadsforward-core'); ?></a>
				<?php endif; ?>
				<a href="#getting-started"><?php esc_html_e('Playbook overview', 'leadsforward-core'); ?></a>
				<a href="#roadmap"><?php esc_html_e('Order of work', 'leadsforward-core'); ?></a>
				<a href="#admin-map"><?php esc_html_e('Admin map', 'leadsforward-core'); ?></a>
				<a href="#manifester"><?php esc_html_e('Manifest Website', 'leadsforward-core'); ?></a>
				<a href="#global-settings"><?php esc_html_e('Global settings & GTM', 'leadsforward-core'); ?></a>
				<a href="#homepage-builder"><?php esc_html_e('Homepage sections', 'leadsforward-core'); ?></a>
				<a href="#page-builder"><?php esc_html_e('Page Builder & editor', 'leadsforward-core'); ?></a>
				<a href="#frontend-editor"><?php esc_html_e('Front-end editor (live site)', 'leadsforward-core'); ?></a>
				<a href="#services-areas"><?php esc_html_e('Services & areas', 'leadsforward-core'); ?></a>
				<a href="#projects-reviews"><?php esc_html_e('Projects & reviews', 'leadsforward-core'); ?></a>
				<a href="#seo-health"><?php esc_html_e('SEO & Performance', 'leadsforward-core'); ?></a>
				<a href="#ai-assistant"><?php esc_html_e('AI assistant', 'leadsforward-core'); ?></a>
				<a href="#fleet-updates"><?php esc_html_e('Fleet theme updates', 'leadsforward-core'); ?></a>
				<a href="#developer-reference"><?php esc_html_e('Developer docs in repo', 'leadsforward-core'); ?></a>
				<a href="#bulk-backup"><?php esc_html_e('Bulk tools & backup', 'leadsforward-core'); ?></a>
				<a href="#troubleshooting"><?php esc_html_e('Troubleshooting', 'leadsforward-core'); ?></a>
			</nav>
		</aside>
		<div class="lf-docs__content" id="lf-docs-tab-operator" role="tabpanel" aria-labelledby="lf-docs-tab-operator-btn">
			<?php if ($is_admin) : ?>
				<div class="lf-docs__callout" style="background:#f0f6fc;border:1px solid #c3d9ed;border-radius:8px;padding:12px 16px;margin-bottom:1.5rem;">
					<p style="margin:0 0 8px;"><strong><?php esc_html_e('Also available on the front end', 'leadsforward-core'); ?></strong></p>
					<p style="margin:0;">
						<?php esc_html_e('Logged-in users can open the same guide at', 'leadsforward-core'); ?>
						<a href="<?php echo esc_url(home_url('/' . LF_DOCS_SLUG . '/')); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(home_url('/' . LF_DOCS_SLUG . '/')); ?></a>.
						<?php esc_html_e('If that URL shows a 404, open Settings → Permalinks and click Save once to flush rewrite rules.', 'leadsforward-core'); ?>
					</p>
				</div>
			<?php endif; ?>
			<?php
			if (function_exists('lf_docs_render_playbook_sections')) {
				lf_docs_render_playbook_sections();
			} else {
				echo '<p>' . esc_html__('Extended documentation failed to load.', 'leadsforward-core') . '</p>';
			}
			?>
		</div>

		<div class="lf-docs__content" id="lf-docs-tab-developer" role="tabpanel" aria-labelledby="lf-docs-tab-developer-btn" hidden>
			<?php if (function_exists('lf_docs_render_dev_sections')) : ?>
				<?php lf_docs_render_dev_sections(); ?>
			<?php else : ?>
				<p><?php esc_html_e('Developer docs are unavailable right now.', 'leadsforward-core'); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<script>
		(function(){
			var root = document.querySelector('[data-lf-docs-root="1"]');
			if (!root) return;
			var btns = root.querySelectorAll('[data-lf-docs-tab]');
			var operator = document.getElementById('lf-docs-tab-operator');
			var developer = document.getElementById('lf-docs-tab-developer');
			if (!btns.length || !operator || !developer) return;
			function setTab(which){
				var isDev = which === 'developer';
				operator.hidden = isDev;
				developer.hidden = !isDev;
				btns.forEach(function(b){
					var on = String(b.getAttribute('data-lf-docs-tab') || '') === which;
					b.classList.toggle('is-active', on);
					b.setAttribute('aria-selected', on ? 'true' : 'false');
				});
				try { window.localStorage.setItem('lfDocsTab', which); } catch (e) {}
			}
			btns.forEach(function(b){
				b.addEventListener('click', function(){
					setTab(String(b.getAttribute('data-lf-docs-tab') || 'operator'));
				});
			});
			try {
				var saved = window.localStorage.getItem('lfDocsTab');
				if (saved === 'developer') setTab('developer');
			} catch (e2) {}
		})();
	</script>
	<?php
}
