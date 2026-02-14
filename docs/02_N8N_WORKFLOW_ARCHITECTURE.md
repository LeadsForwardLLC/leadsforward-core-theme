# n8n Workflow Architecture

This workflow is the deterministic content orchestrator. It never writes directly to WordPress; it always returns a strict JSON payload for the WP callback to apply.

## Flow Diagram
```
Webhook
-> Research Document Gate
   -> Use Provided Research
   -> Research Generator (LLM)
-> Store Research Document
-> Split Blueprints + Deterministic Metadata
-> Basic LLM Chain (per page)
-> Parse + Normalize + CTA Guard
-> Quality Gate + SEO Enforcement
-> Deterministic FAQ Enforcement
-> Global Completeness + Blog Gate
-> Merge Blueprint Results
-> Attach Callback Metadata
-> Callback to WP
```

## Step-by-Step
1. **Webhook entry** receives the full payload from WordPress.
2. **Research Document Gate** checks for `research_document`.
3. **Research Generator (LLM)** runs only if no research was provided.
4. **Store Research Document** saves research to workflow static data.
5. **Split Blueprints** creates one item per page and injects:
   - `research_context` (subset of research_document)
   - deterministic `variation_seed`
   - a single `style_profile`
   - `primary_keyword` and `secondary_keywords` for that page
6. **Basic LLM Chain** generates JSON for one page blueprint.
7. **Parse + Normalize + CTA Guard** enforces JSON validity and strips global CTA fields from non-homepage pages.
8. **Quality Gate + SEO Enforcement** ensures primary keyword coverage, injects one secondary keyword if missing, and enforces minimum word counts.
9. **Deterministic FAQ Enforcement**:
   - Only homepage generates FAQs.
   - Non-homepage pages receive a deterministic slice from the homepage FAQ pool.
10. **Global Completeness + Blog Gate** (run once for all generated items):
   - validates generation scope coverage (services, service areas, core pages, blog scope).
   - enforces minimum content volume per page type.
   - rejects placeholder/generic phrases.
   - enforces blog blueprint count floor (`>= 5` when blog scope is enabled).
   - emits workflow warnings instead of hard-failing callback (WordPress fallback repair remains authoritative).
11. **Merge Blueprint Results** collects all page updates.
12. **Callback to WP** posts the merged updates to the WP orchestrator endpoint.

## Model Settings
- **Page generation LLM**: `gpt-5.2-chat-latest`, `maxTokens=3500`, `temperature=0.5`
- **Research generation LLM**: `gpt-5.2-chat-latest`, `maxTokens=3000`, `temperature=0.5`

## Progress Reporting
Progress updates are sent to the WP `/progress` endpoint at key milestones (research ready, content generation start, merge).

## Why This Is Layered
- n8n is the first quality gate and catches low-quality output before callback.
- WordPress is still authoritative and applies deterministic fallback logic server-side.
- If n8n is unavailable, theme-side scaffold + fallback copy/image/SEO systems still populate the site without AI.
