<?php
/**
 * Standalone theme docs: full HTML document (no theme header/footer, no WP Page).
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

header('Content-Type: text/html; charset=UTF-8');

$stylesheet = esc_url(LF_THEME_URI . '/assets/css/docs-page.css');
$ver = rawurlencode((string) LF_THEME_VERSION);
$home = esc_url(home_url('/'));
$logout = esc_url(wp_logout_url($home));
$name = esc_html(get_bloginfo('name'));
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html(sprintf(__('Theme documentation — %s', 'leadsforward-core'), get_bloginfo('name'))); ?></title>
	<link rel="stylesheet" href="<?php echo $stylesheet; ?>?ver=<?php echo $ver; ?>">
	<style id="lf-docs-critical">
		/* Visible even if theme CSS URL is blocked */
		html { box-sizing: border-box; }
		*, *::before, *::after { box-sizing: inherit; }
		body.lf-docs-standalone { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #f1f5f9; line-height: 1.5; }
		.lf-docs-topbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.75rem 1.25rem; background: #fff; border-bottom: 1px solid #e2e8f0; }
		.lf-docs-topbar a { color: #0f172a; }
	</style>
</head>
<body class="lf-docs-standalone">
	<header class="lf-docs-topbar">
		<strong><?php echo $name; ?></strong>
		<nav aria-label="<?php esc_attr_e('Account', 'leadsforward-core'); ?>">
			<a href="<?php echo $home; ?>"><?php esc_html_e('← Site home', 'leadsforward-core'); ?></a>
			&nbsp;·&nbsp;
			<a href="<?php echo $logout; ?>"><?php esc_html_e('Log out', 'leadsforward-core'); ?></a>
		</nav>
	</header>
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
					<p><?php esc_html_e('This page is served only by the theme. You do not need a WordPress Page with this URL.', 'leadsforward-core'); ?></p>
					<p><?php esc_html_e('Use it as the single source of truth for operating the LeadsForward theme.', 'leadsforward-core'); ?></p>
				</section>
				<section id="global-settings" class="lf-docs__section">
					<h2><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></h2>
					<p><?php esc_html_e('Configure business identity, branding, and AI settings from LeadsForward → Global Settings.', 'leadsforward-core'); ?></p>
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
					<p><?php esc_html_e('Services are custom post types and can be generated via the AI Studio workflow.', 'leadsforward-core'); ?></p>
				</section>
				<section id="service-areas" class="lf-docs__section">
					<h2><?php esc_html_e('Service Areas', 'leadsforward-core'); ?></h2>
					<p><?php esc_html_e('Service areas support FAQs, maps, and nearby areas.', 'leadsforward-core'); ?></p>
				</section>
				<section id="projects" class="lf-docs__section">
					<h2><?php esc_html_e('Projects', 'leadsforward-core'); ?></h2>
					<p><?php esc_html_e('Projects appear in gallery sections and archives. Keep media and descriptions current.', 'leadsforward-core'); ?></p>
				</section>
				<section id="reviews" class="lf-docs__section">
					<h2><?php esc_html_e('Reviews', 'leadsforward-core'); ?></h2>
					<p><?php esc_html_e('Reviews are used across trust sections and schema.', 'leadsforward-core'); ?></p>
				</section>
				<section id="faqs" class="lf-docs__section">
					<h2><?php esc_html_e('FAQs', 'leadsforward-core'); ?></h2>
					<p><?php esc_html_e('FAQs can be generated and linked to homepage and service pages.', 'leadsforward-core'); ?></p>
				</section>
				<section id="seo" class="lf-docs__section">
					<h2><?php esc_html_e('SEO', 'leadsforward-core'); ?></h2>
					<p><?php esc_html_e('Configure SEO defaults, sitemap, and schema from LeadsForward → SEO.', 'leadsforward-core'); ?></p>
				</section>
				<section id="ai-studio" class="lf-docs__section">
					<h2><?php esc_html_e('AI Studio', 'leadsforward-core'); ?></h2>
					<p><?php esc_html_e('AI Studio orchestrates generation from the manifest and related data.', 'leadsforward-core'); ?></p>
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
						<li><?php esc_html_e('Verify the n8n workflow JSON is imported on your n8n instance.', 'leadsforward-core'); ?></li>
					</ul>
				</section>
			</div>
		</section>
	</main>
</body>
</html>
