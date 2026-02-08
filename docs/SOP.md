LeadsForward SOP — Site Build Checklist (Append-Only)
====================================================

This SOP is the official, step-by-step process for building a complete, SEO-optimized,
high-converting home-service site with the LeadsForward theme.

Do not rewrite this SOP. Append new steps or notes at the bottom when the product changes.

---

1) What the theme is
-------------------
LeadsForward is a productized WordPress theme for local home‑service businesses.
It builds a full lead‑generation site with a conversion-focused homepage, service pages,
service-area pages, and a Quote Builder.

---

2) What it builds
----------------
The theme builds a complete local‑service website with:
- A premium homepage (controlled by the Homepage Builder)
- Service pages (for each service)
- Service‑area pages (for each location)
- Core pages (About, Contact, Reviews, Blog, Sitemap, Legal, Thank You)

All core pages use the same Page Builder Framework and shared section library.

---

3) What the setup wizard does
-----------------------------
When you run Setup:
- Creates all required pages for the selected niche
- Creates default services and service areas
- Seeds the Homepage Builder with a high‑converting layout
- Seeds the Page Builder for services, service areas, and core pages
- Creates header and footer menus
- Applies branding, CTA defaults, and schema settings

---

4) How to create a site from scratch (step by step)
---------------------------------------------------
Step 1: Install the theme and required plugins (ACF).
Step 2: Go to LeadsForward → Setup.
Step 3: Choose “General (Local Services)” and complete the wizard.
Step 4: Review the Homepage Builder and confirm section order and copy.
Step 5: Review Page Builder for:
        - Services (each service page)
        - Service Areas (each area page)
        - Core pages (About, Contact, Reviews, Blog, Sitemap, Legal, Thank You)
Step 6: Update business info, branding colors, and logo in Global Settings.
Step 7: Publish (no manual page building required).

---

5) Default install blueprint (General Local Services)
-----------------------------------------------------
When “General (Local Services)” is selected, the theme creates:

Required pages:
- Home
- Our Services (overview)
- Our Service Areas (overview)
- About Us
- Contact
- Reviews
- Blog
- Sitemap
- Privacy Policy
- Terms of Service
- Thank You

Each page uses the Page Builder Framework and pre‑configured sections:

Home (Homepage Builder)
- Hero
- Trust Bar
- Benefits
- Process
- FAQ
- CTA
- Related Links
- Map + Service Areas

About Us (Page Builder)
- Hero
- Content with Image
- Benefits
- CTA

Our Services (Page Builder)
- Hero
- Services Grid
- CTA

Our Service Areas (Page Builder)
- Hero
- Service Areas Grid
- Map + Service Areas
- CTA

Reviews (Page Builder)
- Hero
- Reviews
- CTA

Blog (Page Builder)
- Hero
- Blog Posts
- CTA

Sitemap (Page Builder)
- Hero
- Content with Image (link list)

Contact (Page Builder)
- Hero
- Content with Image
- Map + Service Areas
- CTA

Privacy Policy (Page Builder)
- Hero
- Content with Image

Terms of Service (Page Builder)
- Hero
- Content with Image

Thank You (Page Builder)
- Hero
- Content with Image
- CTA

---

6) What NOT to touch
-------------------
Do not edit:
- Theme PHP files
- CSS files
- Section templates
- Page templates

Use ONLY the structured settings in:
- LeadsForward → Homepage
- LeadsForward → Global Settings
- Page Builder meta box (on core pages, services, and service areas)
- LeadsForward → Quote Builder

This keeps the site consistent, fast, and safe.

---

7) How updates work safely
--------------------------
- Theme updates are safe because all content lives in settings and structured fields.
- Re‑running the Setup Wizard does NOT delete content; it only fills missing items.
- Use “Reset” only when you want a clean start.
- If you need a change, update settings — do not edit templates.

---

Append‑Only Change Log
----------------------
2026‑02‑08 — SOP created.

2026‑02‑08 — Niche page matrices added.

---

Niche Selection Addendum (Append‑Only)
-------------------------------------
When you select a niche, the wizard adds niche‑specific service pages in addition to the General pages.
These are created automatically and pre‑configured with the Page Builder Framework.

Roofing adds:
- Roof Repair
- Roof Replacement
- Storm Damage
- Emergency Roofing
- Commercial Roofing

Plumbing adds:
- Drain Cleaning
- Water Heater Repair
- Leak Detection
- Emergency Plumbing

HVAC adds:
- AC Repair
- AC Installation
- Heating Repair
- Maintenance Plans

Landscaping adds:
- Lawn Care
- Landscape Design
- Hardscaping
- Seasonal Cleanup

Operator customization rules:
- You MAY reorder sections, edit copy, and toggle sections ON/OFF.
- You MAY update branding, CTAs, and global business info.
- You MAY add/remove services and service areas through the structured UI.
- You MUST NOT edit theme templates, PHP, or CSS files.
- You MUST NOT change core page slugs or delete core pages.

Internal linking rules:
- Service pages link to service areas (via the Map + Areas section).
- Service areas link back to services (via Services Offered Here).
- Homepage links to top services and areas (Related Links + Map section).

---

Core Pages Editing & SEO Addendum (Append‑Only)
-----------------------------------------------
Editing core pages safely:
- Use the Page Builder panel on each core page.
- Adjust section order, headings, and copy only in the structured fields.
- Do NOT use Gutenberg blocks for layout or custom HTML.

SEO best practices (per core page):
- **About Us:** Keep the H1 clear (About {Business}); describe what makes you credible.
- **Services:** Use a short H1, then list services; keep the meta description concise.
- **Service Areas:** Mention the primary city/area in the hero subheadline.
- **Reviews:** Emphasize trust; keep descriptions factual and short.
- **Blog:** Focus on helpful, local advice; keep titles readable.
- **Contact:** Include phone and service area in the hero subheadline.
- **Privacy/Terms:** Use clear legal titles; keep these pages simple.
- **Thank You:** Confirm next steps; avoid aggressive sales copy.

Conversion best practices (per core page):
- Keep one primary CTA near the end of each core page.
- Use the Quote Builder CTA on About, Services, Areas, Reviews, and Blog.
- Include internal links to services and areas where relevant.

Final QA checklist before launch:
- All core pages exist and use the Page Builder.
- H1 appears only once per page (hero section).
- Meta titles/descriptions are set or have sensible defaults.
- CTAs open the Quote Builder or call the business phone.
- Service pages and service areas link to each other.
- Homepage links to top services and areas.
