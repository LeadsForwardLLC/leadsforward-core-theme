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
    "custom_global_cta": { "headline": "", "subheadline": "" },
    "launch_schedule": {}
  }
}
```

## Launch schedule (`global.launch_schedule`)

Optional. Merged with theme defaults so you can tune **what publishes on day one** vs **what WordPress schedules for later** (`post_status` `future` + `post_date`), for service pages (`lf_service`), service-area pages (`lf_service_area`), and AI blog shells (`post`).

| Key | Default | Purpose |
| --- | --- | --- |
| `anchor` | `""` | ISO date prefix (`YYYY-MM-DD`) for scheduling math; empty = “now” (site time). |
| `services_initial_ratio` | `0.5` | Share of manifest **services** (by order) that publish immediately; remainder get staggered future dates. |
| `service_areas_initial_ratio` | `0.5` | Same for **service_areas**. |
| `deferred_mode` | `weekly_pair` | `weekly_pair`: each week publishes one deferred service **and** one deferred area on the same local datetime (good for “one service + one area per week”). `spread`: split each deferred list evenly across `spread_days`. |
| `spread_days` | `30` | Window for `spread` mode (1–365). |
| `publish_hour` | `9` | Local hour (0–23) for scheduled publishes. |
| `blog` | see below | Blog shell slots created by AI Studio (`lf_ai_post_slot` meta). |

`blog` object:

| Key | Default | Purpose |
| --- | --- | --- |
| `publish_now_count` | `3` | First N shells are `publish` (launch set: pillar + how-to + cost-style archetypes when using default topic templates). |
| `scheduled_count` | `2` | Additional shells are `future`, spaced by `scheduled_weeks_between`. |
| `scheduled_weeks_between` | `1` | Weeks between each **scheduled** shell (after the publish-now block). |

Re-running manifest sync reapplies this plan to manifest-listed CPT rows (see `lf_manifest_schedule_managed` meta).

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
