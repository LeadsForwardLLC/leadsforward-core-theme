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
				<a href="#getting-started"><?php esc_html_e('Getting started', 'leadsforward-core'); ?></a>
				<a href="#manifester"><?php esc_html_e('Website Manifester', 'leadsforward-core'); ?></a>
				<a href="#global-settings"><?php esc_html_e('Global settings', 'leadsforward-core'); ?></a>
				<a href="#homepage-builder"><?php esc_html_e('Homepage Builder', 'leadsforward-core'); ?></a>
				<a href="#page-builder"><?php esc_html_e('Page Builder', 'leadsforward-core'); ?></a>
				<a href="#services-areas"><?php esc_html_e('Services & areas', 'leadsforward-core'); ?></a>
				<a href="#projects-reviews"><?php esc_html_e('Projects & reviews', 'leadsforward-core'); ?></a>
				<a href="#seo-health"><?php esc_html_e('SEO & site health', 'leadsforward-core'); ?></a>
				<a href="#ai-assistant"><?php esc_html_e('AI assistant & editor', 'leadsforward-core'); ?></a>
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

			<section id="getting-started" class="lf-docs__section">
				<h1><?php esc_html_e('Getting started', 'leadsforward-core'); ?></h1>
				<p><?php esc_html_e('LeadsForward is a conversion-focused theme for local service businesses. Content is structured around services, service areas, core pages, and AI-assisted generation that respects your business entity, keywords, and internal linking rules.', 'leadsforward-core'); ?></p>
				<ul>
					<li><?php esc_html_e('Complete Global Settings (business, branding, AI webhook) before your first manifest run.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Use the Website Manifester to sync Airtable or upload a JSON manifest, then queue generation.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Tune SEO & Site Health from the combined admin screen; use the floating SEO Health panel while editing pages.', 'leadsforward-core'); ?></li>
				</ul>
			</section>

			<section id="manifester" class="lf-docs__section">
				<h2><?php esc_html_e('Website Manifester', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('The manifester is the control room for scope: which page types are included in the orchestrator payload, research upload, media uploads, and the “Manifest your website” action.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Generation scope checkboxes', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Checkboxes (saved with “Save Scope”) drive what blueprints are built—not only the manifest JSON string. You can run a full site in one job: homepage, services, areas, core pages, blog slots, and projects are merged into a single payload so internal links and keywords stay consistent.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('AI blog posts', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('When enabled, the theme maintains five AI blog placeholders: three publish immediately and two are scheduled one and two weeks out (WordPress “scheduled” status). Your n8n workflow still must generate body content and honor the callback; the theme defines structure, slots, and keywords.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Manifest images', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Images uploaded in step 4 are optimized (resize/compress), renamed using your primary keyword and city when available, and receive alt text derived from business name, service focus, and city—so they stay relevant for SEO.', 'leadsforward-core'); ?></p>
			</section>

			<section id="global-settings" class="lf-docs__section">
				<h2><?php esc_html_e('Global settings', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Business identity, contact data, logo, AI Studio webhook/secret, and Airtable connection live here. Keep the webhook URL and shared secret aligned with your n8n instance.', 'leadsforward-core'); ?></p>
			</section>

			<section id="homepage-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Homepage Builder', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Reorder and enable homepage sections. Section order and intents feed AI generation so the hero, trust blocks, and CTAs match your niche.', 'leadsforward-core'); ?></p>
			</section>

			<section id="page-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Page Builder', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Each service, service area, and core page uses the Page Builder meta box: ordered sections with fields the theme renders on the front end. Prefer editing structure here rather than pasting unstructured HTML.', 'leadsforward-core'); ?></p>
			</section>

			<section id="services-areas" class="lf-docs__section">
				<h2><?php esc_html_e('Services & service areas', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Services and areas are custom post types. Bulk Tools can rebuild service↔area relationships after you add posts. Primary keywords per page power titles, meta templates, and internal links.', 'leadsforward-core'); ?></p>
			</section>

			<section id="projects-reviews" class="lf-docs__section">
				<h2><?php esc_html_e('Projects & reviews', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Projects feed gallery sections; testimonials support trust blocks and schema. Sync reviews from Airtable when configured under Global Settings.', 'leadsforward-core'); ?></p>
			</section>

			<section id="seo-health" class="lf-docs__section">
				<h2><?php esc_html_e('SEO & site health', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('LeadsForward → SEO & Site Health combines meta templates, indexing rules, schema toggles, sitemap options, and the pre-launch checklist. The floating “SEO Health” control near the AI assistant reuses the same scoring signals while you edit.', 'leadsforward-core'); ?></p>
				<ul>
					<li><?php esc_html_e('Run Pre-Launch Check before go-live; fix blockers for titles, descriptions, H1 usage, and internal links.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('SER intent templates (transactional, local, informational, navigational) align generated meta with search intent.', 'leadsforward-core'); ?></li>
				</ul>
			</section>

			<section id="ai-assistant" class="lf-docs__section">
				<h2><?php esc_html_e('AI assistant & block editor', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('The floating assistant helps rewrite copy in context. In the block editor, open the LeadsForward sidebar (⋮ menu → LeadsForward design) to change the global design preset without leaving the page.', 'leadsforward-core'); ?></p>
			</section>

			<section id="bulk-backup" class="lf-docs__section">
				<h2><?php esc_html_e('Bulk tools, activity log, backup', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Bulk Tools apply site-wide design preset, CTAs, schema toggles, and internal relationship rebuilds—with preview and confirmation. The activity log records those actions plus manifest runs and imports. Backup & Restore exports allowed options only (no raw customer data).', 'leadsforward-core'); ?></p>
			</section>

			<section id="troubleshooting" class="lf-docs__section">
				<h2><?php esc_html_e('Troubleshooting', 'leadsforward-core'); ?></h2>
				<ul>
					<li><?php esc_html_e('Generation stuck: confirm webhook HTTP 2xx, shared secret, and n8n workflow import.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Blank or 404 docs URL: flush permalinks (Settings → Permalinks → Save) or use Theme Docs in admin.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Missing fields after import: run content audit from Site Health and re-save affected pages.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Enable WP_DEBUG_LOG temporarily to capture manifest and webhook lines in wp-content/debug.log.', 'leadsforward-core'); ?></li>
				</ul>
			</section>
		</div>
	</section>
	<?php
}
