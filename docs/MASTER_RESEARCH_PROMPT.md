# Master Research Prompt

Use this prompt to generate a deterministic research document for the Website Manifester. The output must be valid JSON and match the schema below.

## Ultimate Research Prompt

```
MASTER RESEARCH PROMPT

You are a senior SEO strategist, CRO specialist, and competitive intelligence analyst.

Conduct deep research for the following project and return ONLY valid JSON matching the required schema.

INPUT:

Business name

Niche

Primary city

Target services

Target service areas (if provided)

RESEARCH OBJECTIVES:

Identify top 5 local competitors (organic + map pack).

Extract content structure patterns from top-ranking sites.

Identify messaging gaps and overused patterns.

Extract differentiators.

Determine conversion positioning strategy.

Identify tone patterns that appear human and authoritative.

Identify semantic keyword clusters.

Identify trust signal patterns.

Identify objections and FAQ angles.

Identify content weaknesses to exploit.

OUTPUT FORMAT (STRICT JSON):

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

Return JSON only.
```

## Required JSON Schema

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

## How to Generate (ChatGPT or Internal AI)

1. Paste the prompt above into ChatGPT or your internal AI tool.
2. Provide the input values: business name, niche, primary city, target services, and target service areas (optional).
3. Ensure the output is **JSON only** and matches the schema exactly.
4. Save the output as a `.json` file.
5. Upload the JSON file in **LeadsForward → Website Manifester → Upload Research File**.

## Example Output Structure

```json
{
  "brand_positioning": {
    "market_angle": "Homeowner-first remodels with clear timelines and transparent pricing.",
    "primary_differentiator": "Fixed-scope project management with daily communication.",
    "secondary_differentiators": [
      "Dedicated project manager",
      "Warranty-backed workmanship"
    ],
    "authority_positioning": "Licensed local experts with a documented process.",
    "local_positioning_strategy": "Emphasize neighborhood knowledge and local referrals."
  },
  "competitor_analysis": [
    {
      "competitor_name": "Example Remodeling Co.",
      "strengths": ["Strong photo galleries", "Prominent testimonials"],
      "weaknesses": ["Vague pricing language", "Thin service pages"],
      "content_patterns": ["Hero + gallery + CTA", "Short FAQs"],
      "seo_patterns": ["City in H1", "Service + city in title"]
    }
  ],
  "conversion_strategy": {
    "primary_cta_style": "Schedule a free in-home consultation",
    "emotional_drivers": ["Confidence", "Clarity", "Low risk"],
    "trust_elements_required": ["Licensing badges", "Warranty mention", "Review count"],
    "risk_reduction_elements": ["Clear scope checklist", "No-surprise pricing note"]
  },
  "voice_guidelines": {
    "tone": "Warm, confident, professional",
    "sentence_style": "Short to medium sentences, active voice",
    "avoid_phrases": ["best in town", "cheap"],
    "preferred_phrases": ["clear pricing", "dedicated project manager"],
    "reading_level_target": "8th–9th grade"
  },
  "seo_strategy": {
    "primary_keyword_clusters": ["kitchen remodeling", "bathroom remodeling"],
    "semantic_entities": ["general contractor", "home renovation"],
    "supporting_topics": ["permit handling", "project timelines"],
    "internal_linking_angles": ["Service to gallery", "Service to reviews"]
  },
  "faq_strategy": {
    "objection_clusters": ["Pricing clarity", "Timeline expectations"],
    "high_intent_questions": ["How much does a kitchen remodel cost?"],
    "authority_questions": ["Are you licensed and insured?"]
  },
  "image_strategy": {
    "recommended_image_types": ["Before/after", "On-site team photos"],
    "placement_guidelines": ["Hero and service detail sections"],
    "alt_text_style": "Service + city + outcome"
  },
  "content_expansion_guidelines": {
    "homepage_depth_strategy": "Layer proof, process, and CTA without repetition.",
    "service_page_depth_strategy": "Add scope, timeline, and pricing guidance.",
    "service_area_localization_strategy": "Include city-specific pain points and proof."
  }
}
```
