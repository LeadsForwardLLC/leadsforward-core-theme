<?php
/**
 * LeadsForward Core Theme — Bootstrap
 *
 * Loads all theme logic from /inc/. No business logic in this file.
 * PHP 8+ compatible.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

define('LF_THEME_VERSION', '0.1.117');
define('LF_THEME_DIR', get_template_directory());
define('LF_THEME_URI', get_template_directory_uri());
/** Admin page slug for LeadsForward → Manifest Website (orchestrator UI). */
define('LF_MANIFEST_ADMIN_SLUG', 'lf-manifest');

/**
 * Load a single inc file by relative path from inc/.
 */
function lf_load_inc(string $path): void {
	$file = LF_THEME_DIR . '/inc/' . ltrim($path, '/');
	if (is_readable($file)) {
		require_once $file;
	}
}

// Core setup: theme support, menus, editor styles.
lf_load_inc('setup.php');

// WordPress cleanup: emojis, oEmbed, dashicons, bloat.
lf_load_inc('cleanup.php');

// Performance: defer scripts, heartbeat, head cleanup.
lf_load_inc('performance.php');
// Shared security helpers (rate limiting, client IP resolution).
lf_load_inc('security.php');

// Business entity (Local SEO single source of truth).
lf_load_inc('business-entity.php');
// Icon system (inline SVGs).
lf_load_inc('icons.php');
lf_load_inc('legal-pages.php');
lf_load_inc('duplicate-post.php');

// SEO and schema (foundation only).
lf_load_inc('seo.php');
lf_load_inc('schema.php');
lf_load_inc('seo/internal-link-guardrails.php');
lf_load_inc('blog.php');
// Heading enforcement + validation.
lf_load_inc('headings.php');
// Deterministic media indexing + matching engine.
lf_load_inc('image-intelligence.php');
// Manifest launch scheduling (service/area/blog publish windows).
lf_load_inc('launch-schedule.php');
// AI Studio core + REST endpoints (used outside admin).
lf_load_inc('ai-studio.php');
lf_load_inc('ai-studio-wiring.php');
lf_load_inc('ai-studio-rest.php');
lf_load_inc('ai-studio-airtable.php');
lf_load_inc('airtable/sitemaps.php');
// Private fleet update channel (controller-approved auto-updates).
lf_load_inc('fleet-updates.php');
// Fleet controller helpers shared with tests.
lf_load_inc('fleet-controller-helpers.php');
// Fleet controller endpoints + credential minting (theme.leadsforward.com only).
lf_load_inc('fleet-controller.php');
lf_load_inc('docs-page.php');
lf_load_inc('docs-content.php');
lf_load_inc('docs-playbook.php');
lf_load_inc('editor-design-preset.php');
lf_load_inc('team-role.php');
// Sitemap sync: shared PageSpec helpers.
lf_load_inc('sitemap-sync/types.php');
lf_load_inc('sitemap-sync/reconcile.php');
lf_load_inc('sitemap-sync/menus.php');
lf_load_inc('sitemap-sync/admin.php');

// Custom post types.
lf_load_inc('cpt/services.php');
lf_load_inc('cpt/service-areas.php');
lf_load_inc('cpt/projects.php');
lf_load_inc('cpt/testimonials.php');
lf_load_inc('cpt/faqs.php');
lf_load_inc('cpt/process-steps.php');

// ACF options + field groups (load only when ACF present; guardrails handle fallback).
lf_load_inc('acf/options-business.php');
lf_load_inc('acf/options-global.php');
lf_load_inc('acf/options-branding.php');
lf_load_inc('acf/options-ctas.php');
lf_load_inc('acf/options-schema.php');
lf_load_inc('acf/options-homepage.php');
lf_load_inc('acf/options-variation.php');
lf_load_inc('acf/field-group-service-area.php');
lf_load_inc('acf/field-group-project.php');
lf_load_inc('acf/field-group-testimonial.php');
lf_load_inc('acf/field-group-faq.php');
lf_load_inc('acf/field-group-process-step.php');

// Project gallery helpers.
lf_load_inc('projects.php');

// ACF blocks (server-rendered).
lf_load_inc('blocks/register.php');
lf_load_inc('blocks/variants.php');

// Variation tokens: body class, data-variation, CSS vars.
lf_load_inc('variation-tokens.php');
lf_load_inc('images.php');
lf_load_inc('branding.php');
lf_load_inc('global-settings.php');
lf_load_inc('header-settings.php');
lf_load_inc('menus.php');
lf_load_inc('heading-case.php');
lf_load_inc('redirects.php');
lf_load_inc('site-tools.php');
lf_load_inc('quote-builder.php');
lf_load_inc('contact-form.php');
lf_load_inc('sections.php');
lf_load_inc('page-builder.php');

// Homepage section registry, defaults, CTA resolution.
lf_load_inc('homepage.php');
lf_load_inc('variation-copy.php');

// Niche registry and setup flow.
lf_load_inc('niches/leadgen-pages.php');
lf_load_inc('niches/registry.php');
lf_load_inc('niches/setup-runner.php');
lf_load_inc('niches/wizard.php');

// Dev-only site reset (rerun setup). Always load; visibility gated inside reset-dev.php.
lf_load_inc('niches/reset-dev.php');

// Safety: CPT protect, admin notices, ACF-off fallbacks.
lf_load_inc('guardrails.php');

// AI assistant core + ajax handlers are loaded globally so admins can use the
// floating assistant on both admin screens and frontend pages.
lf_load_inc('ai-editing/field-rules.php');
lf_load_inc('ai-editing/prompt-builder.php');
lf_load_inc('ai-editing/logging.php');
lf_load_inc('ai-editing/handler.php');
lf_load_inc('ai-editing/provider-openai.php');
lf_load_inc('ai-editing/admin-ui.php');
lf_load_inc('frontend-revisions.php');
lf_load_inc('section-library-previews.php');
lf_load_inc('ai-assistant.php');

if (is_admin()) {
	// Homepage controller admin UI (must load before ops menu).
	lf_load_inc('homepage-admin.php');
	// Bulk-safe ops: export/import config, bulk actions, audit log.
	lf_load_inc('ops.php');
	// Site health: dashboard, pre-launch checks, QA audit trail.
	lf_load_inc('site-health.php');
}
