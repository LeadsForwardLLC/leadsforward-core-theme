# LeadsForward AI System Overview

This system turns one manifest (or Airtable project) into a deterministic WordPress site generation run, with strict schema controls and an optional self-healing repair pass.

## Core Components
- **WordPress theme**: stores inputs, builds blueprints, applies updates, tracks jobs.
- **n8n orchestrator**: runs research + per-page generation + quality gates.
- **LLM layer**: returns strict JSON updates for one blueprint at a time.
- **Frontend editor**: inline admin editing for content, images, and section structure.

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

## Storage
- Manifest: `lf_site_manifest`
- Research document: `lf_site_research_document`
- Jobs/progress: `lf_ai_job` CPT + job meta (`lf_ai_job_progress`, status, phase)
- Frontend text overrides: `__dom_override`
- Frontend image overrides: `__img_override`
- Frontend section structure overrides: section order/layout/enabled/record entries
