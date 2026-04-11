# LeadsForward AI System Overview

This system turns one manifest (or Airtable project) into a deterministic WordPress site generation run, with strict schema controls and an optional self-healing repair pass.

## Core Components
- **WordPress theme**: stores inputs, builds blueprints, applies updates, tracks jobs.
- **n8n orchestrator**: runs research + per-page generation + quality gates.
- **LLM layer**: returns strict JSON updates for one blueprint at a time.
- **Frontend editor**: inline admin editing for content, images, and section structure.

## Builder Niche Scope
- **Primary default:** Foundation Repair (`foundation-repair`)
- **Secondary options:** Roofing, Pressure Washing, Tree Service, HVAC, Windows & Doors, Remodeling, Paving
- Setup wizard and Global Settings niche selectors are intentionally restricted to this list for faster, less error-prone rollout.

## End-to-End Flow
```
Manifest/Airtable -> WP payload -> n8n webhook
-> Research gate (provided or generated) -> per-page generation
-> deterministic gates/enforcement -> merged callback
-> WP apply + audit -> optional single repair pass
```

## Execution Phases
- `initial`: normal full generation run.
- `repair`: optional targeted fix pass for missing/weak fields discovered by audit.
- Requests include `run_phase`; progress/callback payloads can be phase-aware.

Expected behavior:
- 1 execution if initial pass is complete.
- Up to 2 executions if repair is needed (`initial` + one `repair`).

## Deterministic Guarantees
- One LLM run per blueprint item.
- LLM writes are constrained to `allowed_field_keys`.
- Homepage is source-of-truth for global CTA/FAQ strategy.
- CTA supporting text remains unique by page context.
- SEO/content quality enforcement runs before callback apply.
- Public lead endpoints use lightweight throttle + honeypot safeguards.

## Template Defaults (Clean Layouts)
- **Homepage:** hero → trust_bar → service_intro → benefits → service_details → process → faq_accordion → trust_reviews → related_links → map_nap → cta.
- **Services overview (our-services):** hero → service_intro → content_image → faq_accordion → cta.
- **Service areas overview:** hero → service_areas → faq_accordion → cta.
- **Service pages:** hero → trust_bar → service_details → benefits → process → faq_accordion → cta.
- **Service area pages:** hero → trust_bar → content_image → benefits → content_image → image_content → process → related_links → nearby_areas → faq_accordion → cta.
- **Core pages:** about-us (hero → content_image → benefits → cta); contact (hero → map_nap → cta); reviews (hero → trust_reviews → cta); blog (hero → blog_posts → cta); sitemap (hero → sitemap_links); privacy/terms/thank-you (hero → content).

## Storage
- Manifest: `lf_site_manifest`
- Research document: `lf_site_research_document`
- Jobs/progress: `lf_ai_job` CPT + job meta (`lf_ai_job_progress`, status, phase)
- Frontend text overrides: `__dom_override`
- Frontend image overrides: `__img_override`
- Frontend section structure overrides: section order/layout/enabled/record entries
- Per-post Page Builder: post meta `lf_pb_config` (`order` + `sections`) for services, service areas, pages, posts, and projects — see `09_PAGE_BUILDER_MAPS_NAV_AI.md`

## Related docs
- **Operators:** LeadsForward → Theme Docs (playbook in wp-admin) and `09_PAGE_BUILDER_MAPS_NAV_AI.md` for maps, menus, and AI draft creation into sections.
