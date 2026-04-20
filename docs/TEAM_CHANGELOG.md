# LeadsForward Core Theme — Team Changelog
2026-04-20

What changed

* Menu controls: added a Global Settings toggle to auto-build the primary menu (core pages) and never link unpublished pages; added a “Menu: include these Services” picker to include selected service pages under Services.
* Services menu support: Services CPT is now exposed to WordPress nav menus so teams can add individual services manually when needed.
* Heading case controls: added a global heading case mode (normal/title/upper/lower) plus a front-end editor switcher (saved globally; page reload applies).
* Link hover styling: added a Branding setting for internal link hover color (tokenized as `--lf-link-hover`).
* Footer CID linking: added Business settings to auto-link the footer address to the GBP URL (CID-friendly) with an explicit URL override.
* Internal link safety: AI-applied HTML now strips broken internal links to missing/unpublished internal pages before saving (keeps the text, removes the dead link).
* Reviews page resilience: when there are no testimonials yet, the Reviews page now shows a helpful placeholder instead of looking broken.
* Projects link hygiene: Projects links are hidden unless there are published Projects, reducing “surprise links” in menus/footer on new sites.

Why it matters

* Fewer 404s from menus, more control over Services navigation, and fewer broken AI-inserted links.
* Cleaner launch experience: Reviews/Projects no longer look “missing” on new sites.

Where to look / how to verify

* Menu autobuild: Global Settings → enable **Auto-build primary menu** → refresh → confirm header menu includes core pages; confirm draft pages (e.g., Financing) are not linked.
* Services in menu: Global Settings → pick a few Services in **Menu: include these Services** → refresh → confirm they appear under Services.
* Heading case: Front-end editor → Header panel → change **Heading case** → save → reload → confirm headings reflect the selected casing.
* Link hover: Branding → set **Link hover color** → refresh → hover internal links in content and confirm color updates.
* Footer address CID: Business Info → set GBP URL (or Address Link override) → refresh → confirm footer address is clickable.
* Broken link guardrail: Use AI to insert a link to a missing page → apply → confirm the link does not persist as a clickable anchor.

Version

* Shipped up to **0.1.73** (PR #393).

