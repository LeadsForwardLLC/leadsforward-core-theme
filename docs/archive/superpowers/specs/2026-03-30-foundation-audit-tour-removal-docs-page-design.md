 # Foundation Audit + Tour Removal + Docs Page (Design)
 
 ## Summary
 Perform a full foundation pass to ensure the theme’s blueprint/payload/apply pipeline is correctly wired for all page types and sections, remove the WordPress admin Guided Tour UI, and add a logged-in-only frontend documentation page with sidebar navigation.
 
 ## Goals
 - Verify every page type and section is correctly wired from registry → blueprint → n8n → apply → templates.
 - Ensure the n8n workflow cannot “succeed” with empty updates.
 - Remove the admin Guided Tour UI entry.
 - Add a simple, logged-in-only, noindex docs page with sidebar navigation.
 - Prepare changes to commit/push and provide guidance on replacing the n8n workflow and running a test.
 
 ## Non-Goals
 - New design system work or visual redesign of existing pages.
 - Changing the manifest schema (unless a wiring gap requires a minimal addition and is approved explicitly).
 - Adding new section types or new page types (unless a wiring gap requires a minimal addition).
 - Replacing the orchestration stack (n8n remains).
 
 ## Scope (Full Foundation Pass)
 - Homepage
 - Services (single)
 - Service Areas (single)
 - Services Overview
 - Service Areas Overview
 - Core pages (About, Contact, Reviews, Blog, Sitemap, Privacy, Terms, Thank You)
 - Blog posts (AI blog CPT items, not just the blog page)
 - Projects
 - FAQs
 
 ## Current Architecture (Source of Truth Order)
 1. Section registry: `inc/sections.php` (section types + allowed field keys)
 2. Blueprint builders: `lf_ai_studio_build_*_blueprint()` (section instances + metadata)
 3. Payload builder: `lf_ai_studio_build_full_site_payload()`
 4. n8n workflow: `docs/n8n-workflow.json`
 5. Apply layer: `lf_apply_orchestrator_updates()`
 6. Templates: `templates/blocks/*`, `front-page.php`, `page.php`, `single-*`
 
 ## Audit Plan (Concrete Checks)
 - Section registry: every section has complete `allowed_field_keys`, labels/types.
 - Blueprints: every page includes its intended sections with correct `section_id`, `section_type`, `allowed_field_keys`.
 - Payload: includes all page types listed above (including blog posts), with consistent `page_type` labels.
 - n8n: per-blueprint LLM output is parsed, normalized, and merged; empty updates must hard-fail with explicit error.
 - Apply: target/id mapping matches CPT options/meta; no silent drops.
 - Templates: render all allowed fields (no gaps); list parsing uses newline-delimited fields.
 
 ## n8n Workflow Requirements
 - Must fail clearly if:
   - `blueprints[]` missing
   - any blueprint lacks `sections[]`
   - merged updates empty
   - homepage updates missing
 - Must preserve `page_type`, `blueprint_index`, and metadata through parsing/merge.
 - Must produce FAQ updates when schema requires it (honor `faq_target_count` / `faq_target_range`).
 
## Guided Tour Removal
- Remove the Guided Tour feature entirely (UI entry + settings + enqueues/assets).
- Ensure no assets or hooks for the tour remain active.
 
## Documentation Page (Frontend)
- Route: `/<slug>` (slug will be chosen during implementation).
- Access: all logged-in users; redirect non-logged-in to login.
- SEO: `noindex, nofollow`.
- Mechanism: virtual route (custom rewrite + template loader), not a database Page.
- Template: dedicated template file with static HTML sections.
 - Navigation: left sidebar with anchor links to each topic.
 - Topics: Getting Started, Global Settings, Homepage Builder, Page Builder, Services, Service Areas, Projects, Reviews, FAQs, SEO, AI Studio, Manifester, Troubleshooting.
 - Keep-updated: single source file in the theme for easy edits.
 
 ## Error Handling + Observability
 - Add explicit error returns for empty or invalid update sets in n8n.
 - Ensure quality warnings are preserved and surfaced to WP.
 - Add targeted logging (WP debug-only) when a blueprint section or field is dropped.
 
 ## Testing / Verification
 - Run a full Manifester test after changes:
   - Confirm updates applied for every page type (including blog posts).
   - Confirm FAQs created and linked.
   - Confirm no “succeeded with empty updates.”
 - Open the docs page logged in and confirm sidebar nav works.
 - Confirm the Guided Tour UI entry is gone.
 
 ## Deliverables
 - Code changes to theme wiring (registry → blueprint → apply → templates).
 - Updated `docs/n8n-workflow.json`.
 - New docs page template and route.
 - Guided Tour UI removed.
 - Clear instruction on when to replace the workflow and re-run the test.
