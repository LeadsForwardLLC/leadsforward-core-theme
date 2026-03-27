# Manifest Schema

The manifest is the canonical input for deterministic site generation. It can be uploaded directly or derived from Airtable.

## Required Top-Level Keys
- `business` (object)
- `homepage` (object)
- `services` (array, at least 1)
- `service_areas` (array, at least 1)
- `global` (object)

## Canonical Structure (Minimal)
```json
{
  "business": {
    "name": "",
    "legal_name": "",
    "phone": "",
    "email": "",
    "address": { "street": "", "city": "", "state": "", "zip": "" },
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
    "custom_global_cta": { "headline": "", "subheadline": "" }
  }
}
```

## Key Rules
- All keys must exist (values may be empty strings unless noted).
- `services[]` and `service_areas[]` must contain at least 1 item.
- Slugs must be unique within their arrays.
- `primary_keyword` is required for homepage, services, and service areas.
- For builder consistency, prefer `business.niche` / `business.niche_slug` values from the supported set:
  - `foundation-repair` (default)
  - `roofing`, `pressure-washing`, `tree-service`, `hvac`, `windows-doors`, `remodeling`, `paving`

## Deterministic Behavior
- The system computes a deterministic seed from `business.name + business.primary_city + business.niche`.
- Service titles are normalized during manifest scaffold for clean, location-aware names.
- The manifest is stored in `lf_site_manifest` and used to build blueprints.
