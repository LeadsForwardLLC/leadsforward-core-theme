# AI Prompt Engine

This document describes the **orchestrator / n8n** blueprint path: one LLM call per blueprint (per page), strict JSON updates, and `allowed_field_keys` per section.

**Different flow:** the **in-dashboard AI Assistant** draft-creation prompts (`lf_ai_assistant_build_creation_prompt` in `inc/ai-editing/admin-ui.php`) use a separate JSON shape for Page Builder post types, including a top-level `page_builder` object keyed by default section slots. See **`09_PAGE_BUILDER_MAPS_NAV_AI.md`** for that contract.

## Prompt Assembly (High Level)
```
System message (theme)
-> research_context (optional)
-> blueprint (single page)
-> LLM -> JSON updates
```

### Blueprint Context Enhancements
- Each section now includes `section_label`, `intent`, optional `purpose`, plus `field_labels` and `field_types` for every `allowed_field_keys`.
- Page-level context includes `page_title`, `page_slug`, `page_excerpt`, and optional `page_template` for pages.
- Service/service-area blueprints include `service` or `service_area` context (title/slug + local metadata).

## System Message
The system message is generated in `lf_ai_studio_llm_system_message()` and enforces:
- JSON-only output
- allowed fields only (`allowed_field_keys`)
- per-page generation (no cross-page content)
- strict CTA + FAQ rules

## Research Context
When a research document is present, a concise `research_context` object is injected into each blueprint. It includes:
- `brand_positioning`
- `conversion_strategy`
- `voice_guidelines`
- `seo_strategy`
- `faq_strategy`
- `content_expansion_guidelines`
- `redundancy_risk_model`
- `keyword_map`
- `page_type_playbooks`
- `image_intelligence`
- `pre_publish_quality_gates`

## Ultimate Research Prompt (Contract)
The research generator must return **JSON only** with the following schema. The first section is the backwards-compatible core that downstream nodes expect. The second section extends the schema to support non-redundancy planning, per-URL keyword mapping, image intelligence, and pre-publish quality gates.
```json
{
  "brand_positioning": {
    "market_angle": "",
    "primary_differentiator": "",
    "secondary_differentiators": [],
    "authority_positioning": "",
    "local_positioning_strategy": ""
  },
  "competitor_analysis": [
    {
      "competitor_name": "",
      "strengths": [],
      "weaknesses": [],
      "content_patterns": [],
      "seo_patterns": []
    }
  ],
  "conversion_strategy": {
    "primary_cta_style": "",
    "emotional_drivers": [],
    "trust_elements_required": [],
    "risk_reduction_elements": []
  },
  "voice_guidelines": {
    "tone": "",
    "sentence_style": "",
    "avoid_phrases": [],
    "preferred_phrases": [],
    "reading_level_target": ""
  },
  "seo_strategy": {
    "primary_keyword_clusters": [],
    "semantic_entities": [],
    "supporting_topics": [],
    "internal_linking_angles": []
  },
  "faq_strategy": {
    "objection_clusters": [],
    "high_intent_questions": [],
    "authority_questions": []
  },
  "image_strategy": {
    "recommended_image_types": [],
    "placement_guidelines": [],
    "alt_text_style": ""
  },
  "content_expansion_guidelines": {
    "homepage_depth_strategy": "",
    "service_page_depth_strategy": "",
    "service_area_localization_strategy": ""
  },
  "redundancy_risk_model": {
    "what_google_already_has": [],
    "how_we_will_not_be_redundant": [],
    "unique_signal_checklist": []
  },
  "keyword_map": {
    "rules": {
      "one_primary_keyword_per_url": true,
      "no_homepage_keyword_bleed": true
    },
    "pages": [
      {
        "page_hint": "Use blueprint slug/title/post_id to identify the page",
        "page_type": "",
        "url_slug": "",
        "target_location": "",
        "primary_keyword": "",
        "secondary_keywords": [],
        "intent": "transactional | informational | navigational",
        "differentiation_angle": "",
        "must_include_proof": [],
        "avoid_topics": []
      }
    ]
  },
  "page_type_playbooks": {
    "home": { "purpose": "", "section_briefs": [] },
    "service": { "purpose": "", "required_sections": [], "section_briefs": [] },
    "service_area": { "purpose": "", "required_sections": [], "section_briefs": [] },
    "about": { "purpose": "", "section_briefs": [] },
    "why": { "purpose": "", "section_briefs": [] },
    "blog_post": { "purpose": "", "section_briefs": [], "rules": {} }
  },
  "image_intelligence": {
    "selection_rules": [],
    "library_analysis": [
      {
        "media_id": "",
        "filename": "",
        "inferred_subject": "",
        "best_use_cases": [],
        "avoid_use_cases": [],
        "metadata_fixes": {
          "recommended_alt": "",
          "recommended_title": "",
          "recommended_caption": ""
        }
      }
    ],
    "per_page_image_plan": [
      {
        "page_hint": "Use blueprint slug/title/post_id to identify the page",
        "recommended_images_by_section": [
          { "section_type_or_id": "", "media_id": "", "reason": "" }
        ]
      }
    ]
  },
  "pre_publish_quality_gates": {
    "block_if": [],
    "warn_if": [],
    "human_review_checklist": []
  }
}
```

## Token Logic
- The LLM runs **once per blueprint**, not once per site.
- `maxTokens` applies to a single page response.
- Typical page responses fit well under 3500 tokens with the current schema.
- We cap at **3500** to prevent runaway responses and keep the workflow stable.

## Post-LLM Guard Rails
After the LLM returns per-blueprint JSON, the workflow runs layered code-node gates:

1. **Parse + Normalize + CTA Guard**
   - strict JSON parsing and cleanup
   - strips global CTA fields from non-homepage pages
2. **Quality Gate + SEO Enforcement**
   - validates keyword presence and minimum page-level density
   - injects missing keyword context when possible
3. **Deterministic FAQ Enforcement**
   - normalizes homepage FAQ output and deterministic FAQ reuse
4. **Global Completeness + Blog Gate**
   - validates scope coverage and page-type presence
   - enforces blog quality depth and minimum blog blueprint volume
   - blocks common placeholder/generic phrasing
