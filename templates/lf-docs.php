<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();
?>
<main id="main" class="site-main site-main--docs" role="main">
	<section class="lf-docs">
		<aside class="lf-docs__sidebar" aria-label="<?php esc_attr_e('Documentation navigation', 'leadsforward-core'); ?>">
			<h2 class="lf-docs__title"><?php esc_html_e('Theme Documentation', 'leadsforward-core'); ?></h2>
			<nav class="lf-docs__nav">
				<a href="#getting-started"><?php esc_html_e('Getting Started', 'leadsforward-core'); ?></a>
				<a href="#global-settings"><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></a>
				<a href="#homepage-builder"><?php esc_html_e('Homepage Builder', 'leadsforward-core'); ?></a>
				<a href="#page-builder"><?php esc_html_e('Page Builder', 'leadsforward-core'); ?></a>
				<a href="#services"><?php esc_html_e('Services', 'leadsforward-core'); ?></a>
				<a href="#service-areas"><?php esc_html_e('Service Areas', 'leadsforward-core'); ?></a>
				<a href="#projects"><?php esc_html_e('Projects', 'leadsforward-core'); ?></a>
				<a href="#reviews"><?php esc_html_e('Reviews', 'leadsforward-core'); ?></a>
				<a href="#faqs"><?php esc_html_e('FAQs', 'leadsforward-core'); ?></a>
				<a href="#seo"><?php esc_html_e('SEO', 'leadsforward-core'); ?></a>
				<a href="#ai-studio"><?php esc_html_e('AI Studio', 'leadsforward-core'); ?></a>
				<a href="#manifester"><?php esc_html_e('Manifester', 'leadsforward-core'); ?></a>
				<a href="#troubleshooting"><?php esc_html_e('Troubleshooting', 'leadsforward-core'); ?></a>
			</nav>
		</aside>
		<div class="lf-docs__content">
			<section id="getting-started" class="lf-docs__section">
				<h1><?php esc_html_e('Getting Started', 'leadsforward-core'); ?></h1>
				<p><?php esc_html_e('Use this page as the single source of truth for operating the LeadsForward theme. Each section links to the exact UI area you need.', 'leadsforward-core'); ?></p>
			</section>
			<section id="global-settings" class="lf-docs__section">
				<h2><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Configure business identity, branding, and AI settings from LeadsForward -> Global Settings.', 'leadsforward-core'); ?></p>
			</section>
			<section id="homepage-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Homepage Builder', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Reorder, enable, and edit homepage sections. These settings drive AI homepage generation.', 'leadsforward-core'); ?></p>
			</section>
			<section id="page-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Page Builder', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Each service, service area, and core page uses structured sections from the Page Builder meta box.', 'leadsforward-core'); ?></p>
			</section>
			<section id="services" class="lf-docs__section">
				<h2><?php esc_html_e('Services', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Services are managed as custom post types and generated via the AI Studio workflow.', 'leadsforward-core'); ?></p>
			</section>
			<section id="service-areas" class="lf-docs__section">
				<h2><?php esc_html_e('Service Areas', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Service Areas are managed as custom post types and support FAQs, maps, and nearby areas.', 'leadsforward-core'); ?></p>
			</section>
			<section id="projects" class="lf-docs__section">
				<h2><?php esc_html_e('Projects', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Projects appear in gallery sections and project archives. Keep before/after images and descriptions up to date.', 'leadsforward-core'); ?></p>
			</section>
			<section id="reviews" class="lf-docs__section">
				<h2><?php esc_html_e('Reviews', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Reviews are imported from Airtable and used across trust sections and schema.', 'leadsforward-core'); ?></p>
			</section>
			<section id="faqs" class="lf-docs__section">
				<h2><?php esc_html_e('FAQs', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('FAQs are generated and linked to homepage and service pages. Keep questions unique.', 'leadsforward-core'); ?></p>
			</section>
			<section id="seo" class="lf-docs__section">
				<h2><?php esc_html_e('SEO', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Configure SEO defaults, sitemap, and schema settings from LeadsForward -> SEO.', 'leadsforward-core'); ?></p>
			</section>
			<section id="ai-studio" class="lf-docs__section">
				<h2><?php esc_html_e('AI Studio', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('AI Studio orchestrates generation from the manifest and Airtable data.', 'leadsforward-core'); ?></p>
			</section>
			<section id="manifester" class="lf-docs__section">
				<h2><?php esc_html_e('Manifester', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Use the Website Manifester to trigger a full generation run. Confirm scope settings first.', 'leadsforward-core'); ?></p>
			</section>
			<section id="troubleshooting" class="lf-docs__section">
				<h2><?php esc_html_e('Troubleshooting', 'leadsforward-core'); ?></h2>
				<ul>
					<li><?php esc_html_e('If content is missing, run the content audit and wiring check.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Check AI Studio logs for duplicate or placeholder content warnings.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Verify the n8n workflow update was imported successfully.', 'leadsforward-core'); ?></li>
				</ul>
			</section>
		</div>
	</section>
</main>
<?php
get_footer();
