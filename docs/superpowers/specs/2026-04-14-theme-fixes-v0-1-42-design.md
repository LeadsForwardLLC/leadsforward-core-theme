---
title: "Theme Fixes + Header Variants (v0.1.42) — design"
date: 2026-04-14
status: draft
---

## Goal

Ship a single `0.1.42` release that resolves the listed UX bugs (nav jitter, icon alignment, hero layout issues, save reliability, editor popup, process steps) and introduces three configurable header layouts with frontend editor controls.

## Non-goals

- No new page builder systems beyond the existing FE editor.
- No visual redesign of unrelated sections.
- No plugin/core updates.

---

## Section 1 — Header/nav fixes + multi‑menu system

### Problems
- Sticky header shakes before it locks.
- Phone icon is oversized/misaligned next to “Call now”.
- Need multiple global header styles with FE editor controls.

### Design
1. **Sticky stability**
   - Freeze header container height before and after sticky activation.
   - Avoid layout reflow by switching only visual styles (background/shadow).
   - Introduce a spacer (if needed) rather than toggling margins.

2. **Phone icon alignment**
   - Use a dedicated flex wrapper for icon + label.
   - Set icon size in `em` and align via `align-items:center`.
   - Ensure SVG is not clipped (no hidden overflow).

3. **Header layout variants (global)**
   - **Default** (current).
   - **Centered nav** (logo centered; menu split).
   - **Modern + Topbar** (pre‑menu row for address/hours/CTA).

4. **Frontend editor controls**
   - Global header layout selector.
   - Topbar toggle + fields.
   - Icon size/alignment option if needed.

---

## Section 2 — Hero + icon insertion

### Problems
- Authority Split checklist items disappear after refresh.
- Conversion Stack pills invisible.
- Icon insertion uses shortcode text and clips after render.

### Design
1. **Authority Split checklist save**
   - Ensure FE editor saves to correct hero checklist field.
   - Respect explicit empty lists (no fallback overwrite).

2. **Conversion Stack pills**
   - Normalize pill markup and classes in hero template.
   - Ensure pill CSS applies to this variant.

3. **Icon insertion**
   - Insert actual SVG markup (not shortcodes).
   - Ensure inserted icons are sized/centered and not clipped.

---

## Section 3 — Save reliability + popup + process steps

### Problems
- `service_intro` add/save not persisting.
- `service_details` checklist bottom items revert.
- Text editor dialog doesn’t close when clicking away.
- Process steps from library are fully bold.

### Design
1. **Service intro**
   - Verify FE payload and backend persistence for service IDs.
   - Treat empty selection as explicit (don’t restore defaults).

2. **Service details checklist**
   - Normalize list extraction so all items persist.
   - Ensure sanitize/save caps don’t drop bottom items.

3. **Text editor popup**
   - Tighten click‑outside logic to close on any click not inside the popup/editor.

4. **Process steps bolding**
   - Apply colon split logic consistently for library insertions.
   - Only prefix is bold; suffix remains normal.

---

## Testing

- Manual: FE editor saves for hero checklists, service intro, service details.
- Manual: Sticky header scroll test on homepage + interior page.
- Manual: Header variant switches in FE editor.
- Manual: Icon insertion in FE editor + render/refresh.

