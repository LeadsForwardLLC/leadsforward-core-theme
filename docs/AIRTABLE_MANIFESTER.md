# Airtable → Website Manifester Mapping

This document lists the Airtable field names used by the Website Manifester and how they map into the manifest schema and business entity fields.

## Airtable Base Settings

- Configure these in **LeadsForward → Global Settings → Website Manifester Settings**.
- **Base ID**: `app...` from the Airtable URL or API docs.
- **Table**: name shown in the left sidebar (example: `Business Info`).
- **View**: name shown in the top dropdown (example: `Global Sync View`).

## Required Airtable Fields (default mapping)

These fields must exist or the manifest will fail validation:

- **Project** → `business.name` (also used for `business.legal_name`)
- **Client Email** (fallback: **Google Account** or **Gmails**) → `business.email`
- **Phone Number** → `business.phone`
- **Street Address** → `business.address.street` (optional)
- **City** → `business.address.city` (required)
- **State** → `business.address.state` (required)
- **Zip** → `business.address.zip` (optional)
- **Niche** → `business.niche`
- **Primary KWs** → `homepage.primary_keyword` (first item)

## Keywords (pull as much as possible)

The manifester merges keywords from multiple columns:

- **Primary KWs** → primary keyword (first item)
- **KW-Top 10**, **KW-All**, **KW-Focus** → combined into `homepage.secondary_keywords`

## Service Areas

- **Service Areas** (comma or newline list) → `service_areas[]`
- If **Service Areas JSON** is present, it overrides the list.
- If both are missing, the primary city is used.

## Services

Preferred options (highest to lowest):

1. **Services JSON** (array) → `services[]`
2. Niche defaults from theme registry (if Niche matches a known slug)

## Optional Business / Schema Fields

These improve schema and business info but are not required:

- **Website URL** → `business.website_url` and schema `sameAs`
- **Business Category** → `business.category`
- **Hours** → `business.hours`
- **Google Name** → `business.place_name`
- **GMB CID Primary** (fallback: **GMB CID**) → `business.place_id`
- **Foundation Year** → `business.founding_year`

Social / listings:

- **Facebook** → `business.social.facebook`
- **Instagram** → `business.social.instagram`
- **YouTube** → `business.social.youtube`
- **X** → `business.social.x`
- **Pinterest**, **Houzz**, **Tumblr**, **Yelp**, **Bing** → added to `business.same_as`

## JSON Override (Optional)

If the Airtable field **Manifest JSON** contains valid JSON, it overrides all mappings and is used directly.
