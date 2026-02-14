# AI Prompt Engine

This system uses one LLM call per blueprint (per page). The model must return strict JSON updates and never invent fields outside the schema.

## Prompt Assembly (High Level)
```
System message (theme)
-> research_context (optional)
-> blueprint (single page)
-> LLM -> JSON updates
```

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

## Ultimate Research Prompt (Contract)
The research generator must return **JSON only** with the following schema:
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
  }
}
```

## Token Logic
- The LLM runs **once per blueprint**, not once per site.
- `maxTokens` applies to a single page response.
- Typical page responses fit well under 3500 tokens with the current schema.
- We cap at **3500** to prevent runaway responses and keep the workflow stable.
