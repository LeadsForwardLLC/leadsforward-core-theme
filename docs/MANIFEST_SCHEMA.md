# Manifest Schema (Deterministic Mode)

This document defines the canonical JSON schema for deterministic Manifest Mode. Upload this JSON in **LeadsForward → AI Studio (Advanced)** to bypass setup inputs and generate a site deterministically.

## Schema Version

- **Current version:** 1.0
- **Stored in generation log:** `manifest_schema_version`

## Required vs Optional

All fields shown below are **required keys**. Values may be empty strings unless noted as **required values**.

### Top-Level

- **business** (object) — required
- **homepage** (object) — required
- **services** (array) — required, must contain at least 1 item
- **service_areas** (array) — required, must contain at least 1 item
- **global** (object) — required

## Canonical JSON Structure

```json
{
  "business": {
    "name": "",
    "legal_name": "",
    "phone": "",
    "email": "",
    "address": {
      "street": "",
      "city": "",
      "state": "",
      "zip": ""
    },
    "primary_city": "",
    "niche": "",
    "site_style": "",
    "variation_seed": "",
    "website_url": "",
    "hours": "",
    "category": "",
    "place_id": "",
    "place_name": "",
    "gbp_url": "",
    "founding_year": "",
    "social": {
      "facebook": "",
      "instagram": "",
      "youtube": "",
      "linkedin": "",
      "tiktok": "",
      "x": ""
    },
    "same_as": []
  },
  "homepage": {
    "primary_keyword": "",
    "secondary_keywords": []
  },
  "services": [
    {
      "title": "",
      "slug": "",
      "primary_keyword": "",
      "secondary_keywords": [],
      "custom_cta_context": ""
    }
  ],
  "service_areas": [
    {
      "city": "",
      "state": "",
      "slug": "",
      "primary_keyword": ""
    }
  ],
  "global": {
    "global_cta_override": false,
    "custom_global_cta": {
      "headline": "",
      "subheadline": ""
    }
  }
}
```

## Field Purpose

### business

- **name**: Public brand name used in copy.
- **legal_name**: Legal entity name (optional but must exist as a key).
- **phone / email**: Canonical contact info for copy and CTA logic.
- **address**: NAP fields; used for local context.
- **primary_city**: Primary city/region for deterministic location references.
- **niche**: Controls niche behavior and defaults (e.g., roofing).
- **site_style**: Optional style cue for orchestrator.
- **variation_seed**: Optional explicit seed; the system deterministically derives a seed from `name + primary_city + niche`.
- **website_url**: Optional canonical URL for schema `sameAs`.
- **hours**: Optional business hours string (schema `openingHours`).
- **category**: Optional schema type override (defaults to `HomeAndConstructionBusiness`).
- **place_id** / **place_name**: Optional GBP identifiers.
- **gbp_url**: Optional GBP URL, added to schema `sameAs`.
- **founding_year**: Optional founding year for schema.
- **social**: Optional social URLs (facebook, instagram, youtube, linkedin, tiktok, x).
- **same_as**: Optional array of URLs; merged with `website_url` and social URLs for schema.

### homepage

- **primary_keyword**: Required value for homepage SEO targeting.
- **secondary_keywords**: Optional array of supporting keywords.

### services[]

Each service generates a service page.

- **title**: Service name (required key).
- **slug**: Slug used to create/update the CPT post. Must be unique within services.
- **primary_keyword**: Required value for the service page.
- **secondary_keywords**: Optional array.
- **custom_cta_context**: Optional single-sentence CTA context for that service page.

### service_areas[]

Each service area generates a service area page.

- **city** / **state**: Used for display and local context.
- **slug**: Slug used to create/update the CPT post. Must be unique within service_areas.
- **primary_keyword**: Required value for the service area page.

### global

- **global_cta_override**: If true, the orchestrator should use `custom_global_cta` for the global CTA.
- **custom_global_cta**: Optional headline/subheadline override for the global CTA.

## Validation Rules (Hard Fail)

- Missing required keys at any level.
- **services** array is empty.
- **service_areas** array is empty.
- Duplicate slugs within **services** or within **service_areas**.
- Missing **primary_keyword** on:
  - homepage
  - any service item
  - any service area item

## Deterministic Generation Flow

1. Manifest is uploaded in AI Studio and stored in `lf_site_manifest`.
2. Services and service areas are created/updated using manifest slugs.
3. Blueprint generation uses manifest fields only (no setup merge).
4. Variation seed is computed from `business.name + business.primary_city + business.niche` to ensure deterministic builds.

## Variation Seed

Even if `business.variation_seed` is present, the system derives the final seed from:

```
hash(business.name + business.primary_city + business.niche)
```

This guarantees identical manifest input produces identical content behavior.

## Global CTA

When `global.global_cta_override` is true, the orchestrator should use:

- `global.custom_global_cta.headline`
- `global.custom_global_cta.subheadline`

Page-specific CTA context (if used) should be driven by each service’s `custom_cta_context`.

## FAQ Pool

FAQ generation uses the global pool strategy:

- 8–12 total FAQs for the site
- Homepage uses 5
- Service pages use 4–6 relevant
- Service area pages use 3–5 localized
- Overview pages optionally use 3–4

Reuse FAQ content across pages unless context requires variation.
