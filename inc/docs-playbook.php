<?php
/**
 * Long-form Theme Docs playbook (start-to-finish). Loaded after docs-content.php.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Detailed documentation sections (IDs must match sidebar anchors in docs-content.php).
 */
function lf_docs_render_playbook_sections(): void {
	$can_ops = function_exists('current_user_can') && current_user_can('edit_theme_options');
	?>
			<?php if ($can_ops) : ?>
			<section id="admin-shortcuts" class="lf-docs__section">
				<h2><?php esc_html_e('Quick links (wp-admin)', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Open common screens directly (requires permission to manage theme options):', 'leadsforward-core'); ?></p>
				<ul>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-ops')); ?>"><?php esc_html_e('Website Manifester', 'leadsforward-core'); ?></a></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-setup')); ?>"><?php esc_html_e('Site setup wizard', 'leadsforward-core'); ?></a></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-global')); ?>"><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></a></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-homepage-settings')); ?>"><?php esc_html_e('Homepage Builder', 'leadsforward-core'); ?></a></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-seo&tab=settings')); ?>"><?php esc_html_e('SEO & Site Health (SEO tab)', 'leadsforward-core'); ?></a> — <a href="<?php echo esc_url(admin_url('admin.php?page=lf-seo&tab=health')); ?>"><?php esc_html_e('Health tab', 'leadsforward-core'); ?></a></li>
					<li><a href="<?php echo esc_url(admin_url('edit.php?post_type=lf_process_step')); ?>"><?php esc_html_e('Process steps (CPT)', 'leadsforward-core'); ?></a></li>
					<li><a href="<?php echo esc_url(admin_url('edit.php?post_type=lf_faq')); ?>"><?php esc_html_e('FAQs', 'leadsforward-core'); ?></a></li>
				</ul>
			</section>
			<?php endif; ?>
			<section id="getting-started" class="lf-docs__section">
				<h1><?php esc_html_e('LeadsForward playbook: build a site start to finish', 'leadsforward-core'); ?></h1>
				<p><?php esc_html_e('This guide mirrors how production sites are launched: install → setup wizard → global + AI wiring → manifest generation → tune homepage and templates → polish SEO → verify in Site Health → go live.', 'leadsforward-core'); ?></p>
				<p><strong><?php esc_html_e('WordPress basics you need:', 'leadsforward-core'); ?></strong> <?php esc_html_e('Pages vs posts; Appearance → Menus; Settings → Reading (static front page); Settings → Permalinks (Post name); users with Administrator or a role that includes “edit theme options” for LeadsForward screens.', 'leadsforward-core'); ?></p>
				<h2><?php esc_html_e('Before you publish: core WordPress setup', 'leadsforward-core'); ?></h2>
				<ol>
					<li><?php esc_html_e('Settings → General: site title, tagline, timezone, admin email.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Settings → Permalinks: choose “Post name” and click Save (also fixes pretty URLs for /theme-docs/ if that page 404s).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Settings → Reading: set “Your homepage displays” to A static page; pick your Home page and (optionally) a Posts page for the blog index.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Appearance → Menus: assign your primary menu to the theme location the preview uses; include Home, Services hub, Contact, and other pillars.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Visit the site while logged in: use the front-end editor (below) for fast copy and layout tweaks; use wp-admin for heavy structure, SEO meta, and bulk tools.', 'leadsforward-core'); ?></li>
				</ol>
			</section>

			<section id="roadmap" class="lf-docs__section">
				<h2><?php esc_html_e('Recommended order of work', 'leadsforward-core'); ?></h2>
				<ol>
					<li><?php esc_html_e('Install and activate Advanced Custom Fields (ACF) or ACF Pro.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Run LeadsForward → Site setup through all five steps (niche, areas, homepage inputs, business NAP, generate).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Open Global Settings: logo, branding colors if needed, OpenAI key (for assistant), Airtable + Website Manifester webhook/secret.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Use Website Manifester: choose scope checkboxes, upload manifest or pick Airtable project, images, then Manifest your website.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Tune LeadsForward → Homepage Builder and edit core pages via Page Builder meta boxes.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Edit services/areas/projects in the block editor; use the LeadsForward design sidebar for global preset when needed.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Configure LeadsForward → SEO & Site Health (meta templates, header scripts for GTM, sitemap). Run Pre-launch check and the manual QA checklist.', 'leadsforward-core'); ?></li>
				</ol>
			</section>

			<section id="admin-map" class="lf-docs__section">
				<h2><?php esc_html_e('Admin map (where everything lives)', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Site setup and Website Manifester work together: the wizard captures niche, NAP-style business fields, and inputs the orchestrator can use. Manifester runs generation from manifest/Airtable and applies content. If much of your truth lives in Airtable, you can lean on Manifester after Global Settings—still run Site setup at least once so niche and baseline options are stored.', 'leadsforward-core'); ?></p>
				<ul>
					<li><strong>Website Manifester</strong> — <?php esc_html_e('Orchestrator scope, Airtable, manifest upload, research, images, generate.', 'leadsforward-core'); ?></li>
					<li><strong>Global Settings</strong> — <?php esc_html_e('Business entity, phones, map, APIs, manifester enable, reviews sync.', 'leadsforward-core'); ?></li>
					<li><strong>Site setup</strong> — <?php esc_html_e('First-time wizard (5 steps); stores niche and business/homepage-related inputs. Reopen anytime from LeadsForward → Site setup. Step 1 “Next” must stay on Site setup (step 2)—if you land on Manifester, update the theme.', 'leadsforward-core'); ?></li>
					<li><strong>Homepage Builder</strong> — <?php esc_html_e('Section order/on-off for the front page.', 'leadsforward-core'); ?></li>
					<li><strong>Quote Builder / Contact Form</strong> — <?php esc_html_e('Lead capture configuration.', 'leadsforward-core'); ?></li>
					<li><strong>SEO & Site Health</strong> — <?php esc_html_e('Tab: SEO settings. Tab: Site health (status, GTM check, manifester check, pre-launch run, QA checklist).', 'leadsforward-core'); ?></li>
					<li><strong>Bulk Tools / Activity log / Backup & Restore</strong> — <?php esc_html_e('Batch preset, CTAs, schema toggles, linking rebuild; audit trail; config export/import.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('ACF submenus (CTAs, Schema, Variation, etc.) when ACF options pages are active.', 'leadsforward-core'); ?></li>
				</ul>
			</section>

			<section id="manifester" class="lf-docs__section">
				<h2><?php esc_html_e('Website Manifester (deep dive)', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Scope checkboxes are saved independently of the manifest JSON’s generation_scope string—what you check is what the theme sends. A full-site run is recommended so services, areas, blog placeholders, and core pages share one keyword and internal-link graph.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Prerequisites', 'leadsforward-core'); ?></h3>
				<ul>
					<li><?php esc_html_e('Manifester enabled, webhook URL, and shared secret match your n8n (or other) orchestrator.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Callback URL reachable from the orchestrator (no basic-auth surprises on production).', 'leadsforward-core'); ?></li>
				</ul>
				<h3><?php esc_html_e('Images step', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Uploads are optimized, renamed with business/keyword context when a manifest exists, and get alt text when fields are empty or generic.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Blog slots', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Five AI-managed posts: three published, two scheduled weekly. Bodies are filled when your workflow returns content to WordPress.', 'leadsforward-core'); ?></p>
			</section>

			<section id="global-settings" class="lf-docs__section">
				<h2><?php esc_html_e('Global settings & integrations', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('This is the operational hub: legal business data feeds schema, map, and footer; webhook/secret power generation; Airtable PAT/base/table IDs power project pickers and optional review import.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Google Tag Manager: paste the full GTM snippet (or gtag) into SEO & Site Health → SEO settings → Header scripts—not only in a plugin—so Site Health can detect it.', 'leadsforward-core'); ?></p>
			</section>

			<section id="homepage-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Homepage Builder', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Controls which sections appear on the static front page and in what order. Service grid, reviews, map, FAQs, and CTAs all read from here plus their underlying content (services, testimonials, NAP).', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('After changing order, view the homepage on the front end and in the block editor (if you edit the front page block template) to confirm layout.', 'leadsforward-core'); ?></p>
			</section>

			<section id="page-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Page Builder & block editor', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('For services, service areas, and most core pages, structured content lives in the Page Builder meta box: sections with fields (headlines, rich text, images). The theme renders these server-side for consistent HTML and SEO.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Block editor (posts, pages, CPTs)', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Use the block editor normally for body content. Open the top-right Options (⋮) → LeadsForward design to change the global design preset (requires edit theme options).', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Site Editor (full site editing): if your host setup uses it, template changes are separate from Page Builder fields—prefer Page Builder for LeadsForward section content unless your team standardized on FSE patterns.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('SEO meta box', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Each page/post/service/area has an SEO box: primary keyword, meta title/description, intent, and an on-page depth checklist (lengths, internal links, images, featured image). Save to refresh the quality score.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Why the main block editor is sometimes hidden', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Core template pages that use only the Page Builder (no “content” section) hide the big block canvas on purpose—you edit those URLs in the Page Builder meta box. The home page is driven by Homepage Builder; wp-admin shows a notice pointing you there. Posts, services, and areas typically keep the block editor for narrative body copy plus Page Builder sections where configured.', 'leadsforward-core'); ?></p>
			</section>

			<section id="frontend-editor" class="lf-docs__section">
				<h2><?php esc_html_e('Front-end editor (live site, admins only)', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('When you are logged in and view the public site, LeadsForward loads an inline editing layer tied to the AI Assistant. Turn editing on from the assistant UI to change copy and layout without opening every field in wp-admin.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('What you can do on the front end', 'leadsforward-core'); ?></h3>
				<ul>
					<li><?php esc_html_e('Click text to edit inline; changes save on blur (or use the documented save shortcut while focused).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Click images to swap from the Media Library.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Drag sections to reorder; use hover controls to reverse columns, duplicate, hide/show, or delete (delete asks for confirmation).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Use the Structure rail (☰) to jump between sections, reorder from a list, or add sections from the library.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Open the separate “SEO Health” floater for SERP preview, keyword coverage, vitals-oriented hints, and refresh—without leaving the page.', 'leadsforward-core'); ?></li>
				</ul>
				<h3><?php esc_html_e('Keyboard shortcuts (when not typing in a field)', 'leadsforward-core'); ?></h3>
				<ul>
					<li><?php esc_html_e('Undo / redo: Cmd or Ctrl+Z, Cmd or Ctrl+Shift+Z (Ctrl+Y on Windows-style setups).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Command palette: Cmd or Ctrl+K, Cmd or Ctrl+Shift+P, or / when focus is not inside an input.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Save active inline edit: Cmd or Ctrl+S. Cancel inline edit: Esc. Save from textarea: Cmd or Ctrl+Enter.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Move section: Alt or Shift with arrow keys. Duplicate: D. Hide/show: H. Delete section: Delete or Backspace (with confirmation).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Shortcut help: Shift+? or F1.', 'leadsforward-core'); ?></li>
				</ul>
				<h3><?php esc_html_e('Where it works best', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Open the static front page or any URL that renders LeadsForward section wrappers (Page Builder). If no sections are detected, the UI explains that you need a page with theme sections—use wp-admin Page Builder or Homepage Builder instead.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Structural actions are logged for undo/redo. For legal/schema slug changes, new CPT posts, or manifest-scale generation, stay in wp-admin and the Manifester workflow.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Hero Authority Split: proof card list', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('The right-hand checklist uses hero_proof_bullets, stored in the homepage section option. Inline saves on the static front page always use the homepage target even if the assistant recently ran against another URL, so proof lines persist after refresh.', 'leadsforward-core'); ?></p>
			</section>

			<section id="services-areas" class="lf-docs__section">
				<h2><?php esc_html_e('Services & service areas', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Create lf_service and lf_service_area posts; link areas to services in the area’s ACF field. After bulk imports, run Bulk Tools → Rebuild internal linking relationships.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Assign a primary keyword per URL in the SEO meta box; use related anchors when linking between sibling services and parent areas.', 'leadsforward-core'); ?></p>
			</section>

			<section id="projects-reviews" class="lf-docs__section">
				<h2><?php esc_html_e('Projects, reviews, FAQs, process steps', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Projects power gallery sections; testimonials feed trust blocks and review schema. FAQs can be generated and surfaced in accordion sections via faq_selected_ids. Process steps are reusable posts (Process steps in the admin menu): add IDs under process_selected_ids on the Process section, or keep using plain process_steps lines as a fallback.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Sync reviews from Airtable when Global Settings lists the reviews table.', 'leadsforward-core'); ?></p>
			</section>

			<section id="seo-health" class="lf-docs__section">
				<h2><?php esc_html_e('SEO, Site Health, launch', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('SEO settings: title/description templates, SERP intent templates, indexing rules, default OG image, schema toggles (also under ACF Schema), XML sitemap switches.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Site health tab: live status (theme, ACF, setup flag, variation, NAP, GTM header snippet, manifester config), automated pre-launch report, QA audit trail, and the printable focused QA checklist for humans.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('While editing, the floating SEO Health panel summarizes the same quality concepts; links open full SEO settings and Site health.', 'leadsforward-core'); ?></p>
			</section>

			<section id="ai-assistant" class="lf-docs__section">
				<h2><?php esc_html_e('AI assistant', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('The floating assistant proposes copy edits in context. It does not replace the orchestrator for full-page generation. Keep API keys in Global Settings restricted to trusted roles.', 'leadsforward-core'); ?></p>
			</section>

			<section id="bulk-backup" class="lf-docs__section">
				<h2><?php esc_html_e('Bulk tools, activity log, backup', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Bulk Tools: design preset (also in block editor), global CTA strings, schema booleans, service–area rebuild. Activity log records bulk actions, imports, manifest queues, and editor preset saves. Backup exports whitelisted options only.', 'leadsforward-core'); ?></p>
			</section>

			<section id="troubleshooting" class="lf-docs__section">
				<h2><?php esc_html_e('Troubleshooting', 'leadsforward-core'); ?></h2>
				<ul>
					<li><?php esc_html_e('Fix links 404: ensure you are logged in with edit_theme_options; Site setup is registered under LeadsForward.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Pre-launch never updates: run “Run pre-launch check” on the Site health tab; confirm you are not blocked by a security plugin on admin-post.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('GTM always warns: add the snippet to SEO header scripts or ignore if you load tags exclusively elsewhere (plugin).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Scores stuck at zero: update the page once so the SEO scorer runs on save.', 'leadsforward-core'); ?></li>
				</ul>
			</section>
	<?php
}
