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

/**
 * @param string $context 'admin' adds a permalink tip; 'public' is neutral.
 */
function lf_docs_render_main_sections(string $context = 'public'): void {
	$is_admin = $context === 'admin';
	?>
	<section class="lf-docs">
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
				<a href="#bulk-backup"><?php esc_html_e('Bulk tools & backup', 'leadsforward-core'); ?></a>
				<a href="#troubleshooting"><?php esc_html_e('Troubleshooting', 'leadsforward-core'); ?></a>
			</nav>
		</aside>
		<div class="lf-docs__content">
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
	</section>
	<?php
}
