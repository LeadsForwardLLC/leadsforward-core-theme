# LeadsForward AI System Overview

This system turns a single manifest (or Airtable project) into a fully generated, deterministic WordPress site. It is designed for repeatability, clarity, and strict schema compliance.

## Components
- WordPress theme: stores inputs, builds blueprints, applies updates, persists jobs.
- n8n orchestrator: generates research, runs page-level LLM calls, enforces determinism.
- LLM: produces JSON updates for one blueprint at a time.

## End-to-End Flow (Simplified)
```
Manifest/Airtable -> WP payload -> n8n orchestrator
-> Research (optional) -> Blueprint split -> LLM per page
-> Deterministic enforcement -> Merge -> WP callback -> Apply updates
```

## Deterministic Guarantees
- One LLM run per page (one blueprint at a time).
- LLM may only write fields listed in `allowed_field_keys`.
- FAQ content is generated once on homepage, then deterministically reused.
- CTA supporting text is forced to be unique across pages.
- SEO quality gate injects missing primary/secondary keywords when needed.

## Storage
- Manifest: `lf_site_manifest`
- Research document: `lf_site_research_document`
- Jobs + progress: `lf_ai_job` CPT + `lf_ai_job_progress` meta
