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
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-ops')); ?>"><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></a></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-import-page-content')); ?>"><?php esc_html_e('Import Page Content', 'leadsforward-core'); ?></a> — <?php esc_html_e('paste Google Doc → Page Builder sections', 'leadsforward-core'); ?></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-niche-content-library')); ?>"><?php esc_html_e('Niche Content Library', 'leadsforward-core'); ?></a> — <?php esc_html_e('shared process steps & FAQs', 'leadsforward-core'); ?></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-seo&tab=settings')); ?>"><?php esc_html_e('SEO & Performance', 'leadsforward-core'); ?></a></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-sitemap-sync')); ?>"><?php esc_html_e('Sitemap Sync', 'leadsforward-core'); ?></a></li>
					<?php
					$m_slug = defined('LF_MANIFEST_ADMIN_SLUG') ? LF_MANIFEST_ADMIN_SLUG : 'lf-manifest';
					?>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=' . $m_slug)); ?>"><?php esc_html_e('AI Manifester (n8n)', 'leadsforward-core'); ?></a> — <?php esc_html_e('orchestrated generation; engineers only for day-to-day writing', 'leadsforward-core'); ?></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-setup')); ?>"><?php esc_html_e('Manual setup (no Airtable)', 'leadsforward-core'); ?></a> — <?php esc_html_e('hidden URL; linked from Manifester', 'leadsforward-core'); ?></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-homepage-settings')); ?>"><?php esc_html_e('Homepage sections (hidden menu URL)', 'leadsforward-core'); ?></a></li>
					<li><a href="<?php echo esc_url(admin_url('admin.php?page=lf-fleet-updates')); ?>"><?php esc_html_e('Fleet Updates', 'leadsforward-core'); ?></a></li>
				</ul>
			</section>
			<?php endif; ?>
			<section id="getting-started" class="lf-docs__section">
				<h1><?php esc_html_e('LeadsForward: theme builder playbook', 'leadsforward-core'); ?></h1>
				<p><?php esc_html_e('This guide is organized for content writers first. After a site is scaffolded (template build or AI Manifester), you build and polish pages with Import Page Content, the Niche Content Library, Page Builder, and the front-end editor. Engineers use AI Manifester (n8n) for orchestrated generation—that lives in a separate admin screen at the bottom of the LeadsForward menu.', 'leadsforward-core'); ?></p>
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

			<section id="writer-workflow" class="lf-docs__section">
				<h2><?php esc_html_e('Writer workflow (fast path)', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Use this when the site structure already exists (services, areas, About page shell). You do not need the AI Manifester for day-to-day copy.', 'leadsforward-core'); ?></p>
				<ol>
					<li><strong><?php esc_html_e('Niche Content Library', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Edit shared process steps and FAQs once per niche (tokens like {business}, {city}). Sync to site CPTs when ready.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('Import Page Content', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Paste the Google Doc template into About Us (and future page types). Process/FAQ sections pull from the library automatically when left blank.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('Page Builder', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Fine-tune sections on any page: reorder, add blocks from the Section Library, set SEO meta on each URL.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('Front-end editor', 'leadsforward-core'); ?></strong> — <?php esc_html_e('View the live site while logged in; edit copy, images, and section order without opening every meta box.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('SEO & Performance → Coverage', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Refresh the report and fix advisory items before launch.', 'leadsforward-core'); ?></li>
				</ol>
				<p class="description"><?php esc_html_e('Template file: docs/templates/about-us-content-template.txt in the theme repo.', 'leadsforward-core'); ?></p>
			</section>

			<section id="roadmap" class="lf-docs__section">
				<h2><?php esc_html_e('Full launch order (operators)', 'leadsforward-core'); ?></h2>
				<ol>
					<li><?php esc_html_e('Install and activate Advanced Custom Fields (ACF) or ACF Pro.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Global Settings: logo, business entity (NAP), branding, OpenAI key (for front-end assistant).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Scaffold the site: template build or AI Manifester (n8n) from Airtable—one path only for initial baseline.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Writer workflow: Niche Content Library → Import Page Content → Page Builder / front-end editor.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Sitemap Sync (if using Airtable sitemaps) for keywords and header menu.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('SEO & Performance: meta templates, GTM in header scripts, pre-launch check.', 'leadsforward-core'); ?></li>
				</ol>
			</section>

			<section id="admin-map" class="lf-docs__section">
				<h2><?php esc_html_e('Admin map (where everything lives)', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('The sidebar is ordered for writers first. AI Manifester (n8n) is at the bottom—use it to scaffold or regenerate via orchestration, not for routine copy edits.', 'leadsforward-core'); ?></p>
				<ul>
					<li><strong><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Business entity, logo, branding, design tokens, OpenAI assistant key, site tools.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('Import Page Content', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Paste Google Doc → Page Builder (About Us today).', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('Niche Content Library', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Master process steps + FAQs per niche; sync to CPTs.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('Quote Builder / Contact Form', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Lead capture.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('SEO & Performance', 'leadsforward-core'); ?></strong> — <?php esc_html_e('SEO settings, site health, coverage, internal link map.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('Sitemap Sync', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Airtable sitemaps → pages, keywords, header menu.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('Bulk Tools / Backup & Restore', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Batch ops and config export.', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('AI Manifester (n8n)', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Airtable project picker, scope, orchestrator generate. Code lives in inc/manifester/ (future plugin).', 'leadsforward-core'); ?></li>
					<li><strong><?php esc_html_e('Fleet Updates', 'leadsforward-core'); ?></strong> — <?php esc_html_e('Private theme update channel.', 'leadsforward-core'); ?></li>
				</ul>
				<p class="description"><?php esc_html_e('Hidden URLs: Manual setup (lf-setup), Homepage sections (lf-homepage-settings). Legacy ACF submenus (Business Info, CTAs, Schema) are hidden—use Global Settings instead.', 'leadsforward-core'); ?></p>
			</section>

			<section id="manifester" class="lf-docs__section">
				<h2><?php esc_html_e('AI Manifester (n8n) — orchestration', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Separate from the theme builder. Connect webhook + Airtable in Global Settings (collapsed advanced panel) or on the Manifester screen. Pick scope, select an Airtable project, run generation. n8n calls back to WordPress REST /orchestrator. Technical docs: inc/manifester/docs/ in the theme repo.', 'leadsforward-core'); ?></p>
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
				<p><?php esc_html_e('This is the operational hub: legal business data feeds schema, footer, and Map + NAP text; webhook/secret power generation; Airtable PAT/base/table IDs power project pickers and optional review import.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Business entity & map', 'leadsforward-core'); ?></h3>
				<ul>
					<li><?php esc_html_e('Address (NAP): use the structured fields. The name and address shown under the map come from here—not from the iframe.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Google Business Profile URL: add the public profile link so sections can send visitors to read Google reviews (iframes do not show full profile widgets).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Map iframe embed: paste Share → Embed a map from Google Maps. This is the normal way to show the map; the Maps JavaScript and Places APIs are not required for the embed.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Google Maps API key (sensitive settings): optional. Only used for legacy embed fallbacks when no iframe is set. Prefer the iframe so you do not depend on API billing or domain restrictions for the visible map.', 'leadsforward-core'); ?></li>
				</ul>
				<h3><?php esc_html_e('Analytics & tags', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Google Tag Manager: paste the full GTM snippet (or gtag) into SEO & Site Health → SEO settings → Header scripts—not only in a plugin—so Site Health can detect it.', 'leadsforward-core'); ?></p>
			</section>

			<section id="homepage-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Homepage sections', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Controls which sections appear on the static front page and in what order. Service grid, reviews, map, FAQs, and CTAs all read from here plus their underlying content (services, testimonials, NAP). The menu link was removed to reduce clutter; use Pages → Home or the direct admin URL documented in quick links.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('After changing order, view the homepage on the front end and in the block editor (if you edit the front page block template) to confirm layout.', 'leadsforward-core'); ?></p>
			</section>

			<section id="page-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Page Builder (Section Library) & editor', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('For services, service areas, standard pages, blog posts, and projects, the live layout and copy for theme sections live in the Page Builder meta box: drag to reorder, use Section Library → Add to insert rows (duplicates allowed). Each row has type-specific fields (hero, service details, benefits, FAQ, CTA, etc.). Output is server-rendered for consistent HTML and SEO.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('What visitors actually see', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('The front end uses these sections—not the WordPress “main content” field—when Page Builder is active for that post type. After AI creates a draft with section copy, the main editor may be empty on purpose.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Block editor', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Use the block editor when it is visible for auxiliary content or legacy flows. Open Options (⋮) → LeadsForward design to change the global design preset (requires edit theme options).', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Site Editor (FSE): template changes are separate from Page Builder fields—prefer Page Builder for LeadsForward section copy unless your team standardized on FSE patterns.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Add to header menu on save', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('In the Publish box, if you can manage menus (edit theme options) and a menu is assigned to the Header Menu location, you may see “Add to header menu on save.” Checking it adds this post to that menu once, in a smart place: services under the Services dropdown parent, service areas under Areas, blog posts under the Posts page item when that page is already in the menu, child pages under their parent’s menu item when possible—otherwise top level. If the dropdown parents are missing, add them under Appearance → Menus (wizard-built sites include special menu classes for Services and Areas).', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('SEO meta box', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Each page/post/service/area has an SEO box: primary keyword, meta title/description, intent, and an on-page depth checklist (lengths, internal links, images, featured image). Save to refresh the quality score.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Why the main editor is sometimes hidden or empty', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Some templates hide the large block canvas so editors use Page Builder only. The static front page section order is configured via the homepage sections screen (direct URL) or the Home page’s Page Builder where applicable. If you expected text in the main editor after AI created a page, open Page Builder—the copy was saved into sections.', 'leadsforward-core'); ?></p>
			</section>

			<section id="frontend-editor" class="lf-docs__section">
				<h2><?php esc_html_e('Front-end editor (live site, admins only)', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('When you are logged in and view the public site, LeadsForward loads an inline editing layer tied to the AI Assistant. Turn editing on from the assistant UI to change copy and layout without opening every field in wp-admin.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('What you can do on the front end', 'leadsforward-core'); ?></h3>
				<ul>
					<li><?php esc_html_e('Click text to edit inline; changes save on blur (or use the documented save shortcut while focused).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Inline toolbar closes on outside click or the ✕ close button; icon picker includes Small/Medium/Large presets.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Click images to swap from the Media Library.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Drag sections to reorder; use hover controls to reverse columns, duplicate, hide/show, or delete (delete asks for confirmation).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Use the Structure rail (☰) to jump between sections, reorder from a list, or add sections from the library.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Open the separate “SEO Health” floater for SERP preview, keyword coverage, vitals-oriented hints, and refresh—without leaving the page.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Open “History” for layout restore points (who/when). Use Preview to view a restore point non-destructively before restoring. Use the header reload icon to refresh the list after a teammate saves. “Live” marks the snapshot that matches the current server version.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('In Rich Text sections, use Insert icon in the toolbar to add [lf_icon name="slug"] shortcodes at the text caret; click inside the paragraph first if the picker reports it cannot find a cursor. Icons render as inline Tabler SVG on the front end and in sanitized previews.', 'leadsforward-core'); ?></li>
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
				<p><?php esc_html_e('Open the static front page or any URL that renders LeadsForward section wrappers (Page Builder). If no sections are detected, the UI explains that you need a page with theme sections—use wp-admin Page Builder or the homepage sections screen instead.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Structural actions are logged for undo/redo. For legal/schema slug changes, new CPT posts, or manifest-scale generation, stay in wp-admin and the Manifester workflow.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Site header (front-end)', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Open the Header floater on the public site (same layer as History and SEO Health). Choose a global layout (modern or centered). Optionally enable the promo top bar, enter its text, and choose a top bar background color, then Save header. Values are stored site-wide (Global Settings options) and the page reloads. Header CTA label and URL still live under LeadsForward → Global Settings.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Hero Authority Split: proof card list', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('The right-hand checklist uses hero_proof_bullets, stored in the homepage section option. Inline saves on the static front page always use the homepage target even if the assistant recently ran against another URL, so proof lines persist after refresh. Conversion Stack pill rows on the homepage hero use the same homepage-scoped save path.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Service details: checklist columns', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('On service (and similar) pages, the service story section can show one or two checklist columns. Inline add, edit, remove, and reorder write back to service_details_checklist and service_details_checklist_secondary so the correct column survives reload. Page Builder remains the source of truth for bulk edits.', 'leadsforward-core'); ?></p>
			</section>

			<section id="services-areas" class="lf-docs__section">
				<h2><?php esc_html_e('Services & service areas', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Create lf_service and lf_service_area posts; link areas to services in the area’s ACF field. After bulk imports, run Bulk Tools → Rebuild internal linking relationships.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Assign a primary keyword per URL in the SEO meta box; use related anchors when linking between sibling services and parent areas.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('When tuning the service story checklist from the front-end editor, edits in the primary and secondary columns map to separate saved fields; if a line seems to revert, confirm you saved from the checklist area and refresh once.', 'leadsforward-core'); ?></p>
			</section>

			<section id="projects-reviews" class="lf-docs__section">
				<h2><?php esc_html_e('Projects, reviews, FAQs, process steps', 'leadsforward-core'); ?></h2>
					<p><?php esc_html_e('Projects power gallery sections; testimonials feed trust blocks and review schema. FAQs can be generated and surfaced in accordion sections via faq_selected_ids. Process steps are reusable posts: use Assigned services on each step for organization and auto-loading on service pages, plus Process context terms (e.g. homepage-primary) when needed. You can still pin steps with process_selected_ids; plain process_steps lines remain a fallback.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Sync reviews from Airtable when Global Settings lists the reviews table.', 'leadsforward-core'); ?></p>
			</section>

			<section id="seo-health" class="lf-docs__section">
				<h2><?php esc_html_e('SEO, performance, and launch', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('SEO settings: title/description templates, SERP intent templates, indexing rules, default OG image, schema toggles (also under ACF Schema), XML sitemap switches.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Site health tab: live status (theme, ACF, setup flag, variation, NAP, GTM header snippet, manifester config), automated pre-launch report, QA audit trail, and the printable focused QA checklist for humans.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('Coverage tab: read-only table of published pages, posts, services, and service areas with checklist pass counts, open advisory items (same logic as each post SEO box), cached quality scores, and links to edit or view—use Refresh report to rebuild the cache.', 'leadsforward-core'); ?></p>
				<p><?php esc_html_e('While editing, the floating SEO Health panel summarizes the same quality concepts; links open full SEO settings and Site health.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Airtable sitemap → keywords & meta (what runs automatically)', 'leadsforward-core'); ?></h3>
				<ul>
					<li><?php esc_html_e('The Airtable Sitemaps table “Keyword” column (and common aliases such as Primary Keyword / Target Keyword) is the page-level target phrase. Sitemap sync writes it to each matching WordPress page as Primary Target Keyword and registers it in the keyword map.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('After sync, the theme can regenerate meta title/description for transactional and local intent when the phrase is missing from saved snippets—so Airtable stays the source of truth without you retyping meta on every URL.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Service and service-area rows use the resolved URL’s last path segment to find the lf_service or lf_service_area post by slug. If the CPT does not exist yet or the slug does not match, keyword push is skipped until the post exists.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Blog posts are not created from the sitemap sync path the same way as marketing pages; assign keywords via the SEO meta box, manifest/AI flows, or the keyword engine so posts stay in the same SEO system.', 'leadsforward-core'); ?></li>
				</ul>
				<h3><?php esc_html_e('On-page depth checklist (advisory, no auto-edits)', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('In the SEO meta box, the checklist scores alignment of the primary keyword with opening copy, internal links, image alt attributes in stored HTML, featured images, and—after theme updates—meta title/description, visible H1, H2 coverage, and (for local/transactional URLs) the primary city in the opening text. It does not rewrite headings, slugs, or body copy; it only highlights gaps so you or AI Studio can fix them deliberately.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Internal links and broken hrefs', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('AI output is scrubbed so internal links do not point at missing or unpublished posts when sitemap enforcement is on. For strategic coverage (which pages should link to which), use LeadsForward → SEO & Performance → Internal Link Map: it scans content and Page Builder HTML and can apply a suggested link when you approve it.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('URLs, slugs, and headings', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('The theme enforces a single logical H1 (hero vs document title) and structured data, but it does not automatically rename slugs when you change a keyword in Airtable—keep slug templates and keywords in sync in the sheet. H2/H3 wording stays in your Page Builder and editorial process; use the checklist and SEO Health to verify coverage.', 'leadsforward-core'); ?></p>
			</section>

			<section id="ai-assistant" class="lf-docs__section">
				<h2><?php esc_html_e('AI assistant', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Two layers: (1) Manifest Website / n8n for full-site generation, (2) the in-dashboard assistant for targeted edits and draft creation. Store the OpenAI key in Global Settings; restrict who can manage theme options.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Floating assistant', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('On the front end (and in admin where enabled), proposes bounded copy and layout changes. Pair it with the front-end editor for inline saves, section reorder, and SEO Health. It does not change URLs, slugs, or schema by policy.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Edit with AI (post screen)', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Meta box with a prompt field: suggest edits in plain English. Same guardrails as the floater for conversion copy and allowed fields.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Creating new pages, services, posts, or CPT drafts', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('When you ask for a new service page, blog post, etc., the assistant returns structured JSON. For types that use Page Builder, the model should fill a page_builder object: one key per default section slot (hero, service_details, benefits, …) with copy fields inside each. The theme merges that into lf_pb_config and clears the main editor when successful. If the model only returns a long content string, the theme maps it into the first appropriate body field (e.g. service_details_body) as a fallback. See repo doc docs/09_PAGE_BUILDER_MAPS_NAV_AI.md for the exact contract.', 'leadsforward-core'); ?></p>
				<h3><?php esc_html_e('Rollback', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('Recent AI-applied changes can be rolled back from the assistant / AI editing UI where exposed; full-site runs are tracked via AI Studio jobs.', 'leadsforward-core'); ?></p>
			</section>

			<section id="fleet-updates" class="lf-docs__section">
				<h2><?php esc_html_e('Fleet theme updates (private channel)', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('For sites that should receive theme ZIPs from a central controller (for example theme.leadsforward.com) without logging into each install:', 'leadsforward-core'); ?></p>
				<ul>
					<li><?php esc_html_e('Go to LeadsForward → Fleet Updates and paste the connection bundle: API base, site ID, token, and controller public keys JSON.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('The theme checks about every 15 minutes when WordPress cron runs. Quiet sites may need system cron hitting wp-cron.php, or use Check now after a release.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Check now contacts the controller immediately and attempts install when an update is offered (for users who can manage theme options).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('If an auto-install fails, the Fleet Updates screen will show an “Auto-install issue” message with the most recent error (filesystem permissions, disabled file mods, or download failures).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Controller download tokens include a signed fallback and will rebuild the zip on demand if the cached package is missing (multi-instance hosts).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Rollout (all sites, selected sites, or tag) is configured on the controller site; a site that is not eligible will see a reason in the last check summary.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('If navigation disappears after a theme upgrade, open Appearance → Menus → Manage Locations and assign your menu to the Header Menu display location.', 'leadsforward-core'); ?></li>
				</ul>
				<h3><?php esc_html_e('Controller mode: push update to fleet sites', 'leadsforward-core'); ?></h3>
				<p><?php esc_html_e('When the same screen is running as the controller (fleet controller enabled), the sites table adds push actions so you can trigger a signed remote check-and-install without opening each client’s wp-admin.', 'leadsforward-core'); ?></p>
				<ul>
					<li><?php esc_html_e('Per site: use Push update on that row.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Bulk: tick the checkboxes for the sites you want, then Push update to selected sites.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('By tag: enter a tag (lowercase, as stored on the site row) and use Push sites with tag to push every connected site that lists that tag.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Optional checkbox Force install (override rollout): when checked, pushes behave like Check now with rollout override so the controller can offer an update even when normal rollout scope would skip the site. Use deliberately.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Last push time/status/message on each row reflects the HTTP result and JSON message from the client site.', 'leadsforward-core'); ?></li>
				</ul>
				<p><?php esc_html_e('Technical detail for developers: signed downloads, HMAC (including the inbound push route), and apply path are documented in docs/05_THEME_INTEGRATION.md.', 'leadsforward-core'); ?></p>
			</section>

			<section id="developer-reference" class="lf-docs__section">
				<h2><?php esc_html_e('Developer documentation (theme repository)', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('This playbook is the operator guide. For architecture, n8n contracts, manifest schema, section registry details, and the Page Builder / maps / menu / AI creation reference, use the Markdown files in the theme’s docs/ folder (same copy shipped with the theme ZIP or Git clone).', 'leadsforward-core'); ?></p>
				<ul>
					<li><code>docs/README.md</code> — <?php esc_html_e('index of all topics', 'leadsforward-core'); ?></li>
					<li><code>docs/00_PRODUCTION_READINESS.md</code> — <?php esc_html_e('pre-launch checklist and fleet notes', 'leadsforward-core'); ?></li>
					<li><code>docs/01_SYSTEM_OVERVIEW.md</code> — <?php esc_html_e('orchestrator phases, template defaults, storage keys', 'leadsforward-core'); ?></li>
					<li><code>docs/04_SECTION_SCHEMA.md</code> — <?php esc_html_e('section types and fields', 'leadsforward-core'); ?></li>
					<li><code>docs/05_THEME_INTEGRATION.md</code> — <?php esc_html_e('WordPress apply path, identity guard, SEO', 'leadsforward-core'); ?></li>
					<li><code>docs/06_AI_PROMPT_ENGINE.md</code> — <?php esc_html_e('orchestrator LLM blueprint rules', 'leadsforward-core'); ?></li>
					<li><code>docs/08_FRONTEND_EDITOR.md</code> — <?php esc_html_e('inline editing and assistant UI', 'leadsforward-core'); ?></li>
					<li><code>docs/09_PAGE_BUILDER_MAPS_NAV_AI.md</code> — <?php esc_html_e('lf_pb_config, map iframe, header menu checkbox, AI page_builder JSON', 'leadsforward-core'); ?></li>
				</ul>
			</section>

			<section id="bulk-backup" class="lf-docs__section">
				<h2><?php esc_html_e('Bulk tools and backup', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Bulk Tools: design preset (also in block editor), global CTA strings, schema booleans, service–area rebuild. Backup exports whitelisted options only.', 'leadsforward-core'); ?></p>
			</section>

			<section id="troubleshooting" class="lf-docs__section">
				<h2><?php esc_html_e('Troubleshooting', 'leadsforward-core'); ?></h2>
				<ul>
					<li><?php esc_html_e('Fix links 404: ensure you are logged in with edit_theme_options; Manual setup (no Airtable) is registered under LeadsForward.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Pre-launch never updates: run “Run pre-launch check” on the Site health tab; confirm you are not blocked by a security plugin on admin-post.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('GTM always warns: add the snippet to SEO header scripts or ignore if you load tags exclusively elsewhere (plugin).', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Scores stuck at zero: update the page once so the SEO scorer runs on save.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('SEO checklist warns about H2s but sections look fine: the scanner only reads actual &lt;h2&gt; HTML in Page Builder/content fields—not bold lines styled to look like headings.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('AI created a service but the main editor is empty: open Page Builder—the copy is in sections. Retry the prompt asking for page_builder section fields if a section stayed default.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Map missing on contact/home: paste the iframe under Global Settings → Business entity; confirm the Map + NAP section is enabled on that template.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('“Add to header menu” missing or service link lands top-level: assign a menu to Header Menu; you need edit theme options; if already in menu the checkbox hides. For Services/Areas submenus, the parent items need the wizard menu classes—fix under Appearance → Menus.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Fleet update never arrives: confirm rollout on the controller, run Check now or a controller Push update, and ensure WP-Cron still runs for routine pulls and heartbeats. If manual update says download failed, verify controller rewrites and try again after a fresh check.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Header nav missing after an update: Appearance → Menus → Manage Locations → assign your menu to Header Menu.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Rich Text Insert icon does nothing: click inside the paragraph first so the caret is in the prose, then open the picker.', 'leadsforward-core'); ?></li>
				</ul>
			</section>
	<?php
}
