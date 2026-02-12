# AI Collaboration Guide

This document keeps collaborating AI models and humans aligned on the current LeadsForward Core Theme architecture, constraints, and sources of truth. Update this file whenever system behavior or contracts change.

## Purpose

- Keep multiple assistants synchronized on requirements and constraints.
- Provide a single place to confirm what is authoritative.
- Reduce regressions caused by stale assumptions.

## Sources of Truth

- **Section registry**: `inc/sections.php`
- **Homepage builder config/order**: `inc/homepage.php`
- **Page builder**: `inc/page-builder.php`
- **AI Studio orchestration**: `inc/ai-studio.php`
- **Manifest schema**: `docs/MANIFEST_SCHEMA.md`
- **Section schema reference**: `docs/SECTION_SCHEMA.json`
- **SOP**: `docs/SOP.md`
- **README**: `README.md`

## Current Constraints (High Priority)

- Do not modify templates, section registry, rendering logic, or allowed_field_keys unless explicitly requested.
- Manifest Mode is the authoritative generation path when a manifest exists.
- Homepage blueprint must always be included in orchestrator payloads.

## Deterministic Manifest Mode

- Canonical schema: `docs/MANIFEST_SCHEMA.md`
- Manifest validation is hard‑fail (missing required keys, empty arrays, duplicate slugs, missing primary_keyword).
- Manifest values override wizard values; no merging when manifest exists.
- Variation seed is derived from business name + primary city + niche.
- Manifest generation runs the same scaffold as the setup wizard (pages, menus, business entity) using manifest values only.

## AI Studio Orchestration

- Webhook POST is executed via `lf_ai_studio_send_request()` in `inc/ai-studio.php`.
- Payload includes system rules, FAQ strategy, CTA strategy, and blueprints array.
- Apply logic routes updates via `lf_apply_orchestrator_updates()`.
- List fields are coerced to newline‑delimited strings before storage.
- Post‑apply content audit runs after orchestrator callbacks and stores a QA report.
- Auto‑repair will requeue one focused pass when missing/default fields remain.
- Manual QA audits queue a repair job when missing fields remain.

## Reset Behavior (Dev)

- Reset wipes pages, posts, CPTs (services, service areas, FAQs, testimonials, AI jobs), manifest, keywords, and generation logs.
- AI Studio settings **persist** (enable flag, webhook URL, shared secret).
- Triggered from Setup Wizard → Advanced settings (dev‑only visibility).
- Site title/description are cleared to remove business evidence.

## Dummy Content Generation

- On successful generation, dummy blog posts are created if none exist (placeholder content).

## Blog Post Generation

- When core page generation runs, up to three AI blog posts are created if no posts exist.
- Blog posts are filled via Page Builder sections; titles/excerpts are auto-backfilled from hero copy.
- Manifester includes a blog-only regeneration action for AI posts.

## Section Rendering Updates

- `content` section now renders heading + intro + body fields (no longer empty).
- `process` section renders expectations + trust block when provided.
- `service_details` section supports an optional media column (video embed default).
- `related_links` defaults to services-only in core templates.

## Quote Builder Dynamics

- Quote Builder services auto‑populate from created Service pages.
- If no services exist, it falls back to niche defaults.
- Niche‑specific fields are injected into the project details step.

## Service Intro Short Descriptions

- Homepage service cards use `lf_service_short_desc` only.
- After AI applies, the short description is auto‑backfilled from service page content if empty.

## Known UI Locations

- AI Studio (Advanced): manifest upload + download template + generation controls.
- Setup Wizard: advanced settings (API keys, legal pages, dev reset).

## Update Checklist

When you change core behavior, update:

- `README.md` (high‑level summary)
- `docs/SOP.md` (operator steps)
- `docs/AI_CONTEXT.md` (assistant sync)

