# Page Builder, maps, navigation assist, and AI creation

Operator-oriented reference for how structured pages, business map settings, header menus, and the in-dashboard AI assistant interact. For section field keys and registry definitions, see `04_SECTION_SCHEMA.md` and `inc/sections.php`.

---

## Page Builder (Section Library)

### What it is

- **Post meta key:** `lf_pb_config` (`LF_PB_META_KEY` in `inc/page-builder.php`).
- **Shape:** `order` (array of instance IDs) + `sections` (map of instance ID → `type`, `enabled`, `deletable`, `settings`).
- **Defaults:** `lf_pb_default_config( $context )` uses niche-aware section order (`lf_niche_section_order`) and `lf_sections_defaults_for()` for each section type.
- **Contexts:** `service` (`lf_service`), `service_area` (`lf_service_area`), `page` (`page`, except special cases), `post` (`post` and `lf_project`).

### Source of truth for the front end

For these post types, **visitors see sections rendered from `lf_pb_config`**, not from the WordPress post content field. The main editor may be empty or hidden on some templates after save.

### Section Library (admin UI)

- Drag to reorder; **Add** inserts a new instance from the library (duplicates allowed).
- Save runs `lf_pb_handle_save()` which sanitizes via `lf_sections_sanitize_settings()`.

### AI Assistant — creating new drafts

When the assistant **creates** a service, service area, page, post, or project draft (`lf_ai_assistant_create_post_from_payload` in `inc/ai-editing/admin-ui.php`):

1. The model is instructed to return a **`page_builder`** object whose keys match the **default section slots** for that post type (e.g. for services: `hero`, `trust_bar`, `service_details`, `benefits`, `process`, `faq_accordion`, `cta`). If the default order repeats a section type, keys use suffixes: `content_image__2`, etc.
2. Each slot value is an object of **copy-only** fields (text, textarea, list, richtext). Unknown keys are stripped; values are merged into defaults and sanitized.
3. **Service detail body** uses `service_details_body` (richtext), not `section_body`.
4. If `page_builder` is missing or too thin but **`content`** is long enough, the theme **maps HTML into the first suitable section** (`section_body` or `service_details_body`) and sets hero headline from the title (`lf_ai_pb_apply_fallback_content()` in `inc/page-builder.php`).
5. When Page Builder data is applied successfully, **`post_content` is cleared** so operators are not misled by unused editor text.

**Validation (creation):** For Page Builder post types, either **≥60 characters** of visible text across all `page_builder` values, or **≥40 characters** in `content` (fallback path).

**Batch creation** uses the same rules per item; each item may include its own `page_builder`.

### Code references

- `lf_ai_pb_context_from_post_type()`, `lf_ai_pb_default_section_keys_for_context()`, `lf_ai_assistant_apply_creation_page_builder()` — `inc/page-builder.php`
- `lf_ai_pb_writable_field_keys_for_type()` — `inc/ai-editing/field-rules.php`
- Prompt assembly — `lf_ai_assistant_build_creation_prompt()`, `lf_ai_assistant_validate_creation_payload()` — `inc/ai-editing/admin-ui.php`

---

## Global Settings — business entity, map, reviews

### Address (NAP)

- Street, city, state, ZIP feed schema, footer, Map + NAP block text, and “directions” style links.
- **Not** taken from the map iframe; keep NAP accurate in the fields.

### Map iframe embed

- **Label:** “Map iframe embed” (primary way to show the map on the site).
- Paste the **Share → Embed a map** iframe from Google Maps.
- The **Maps JavaScript API and Places Autocomplete are not used** for this path; no API key is required for the embed itself.
- Standard embeds do not show full Google Business Profile widgets (e.g. star breakdown in-iframe); use **Google Business Profile URL** (below) for a “read reviews on Google” style link from theme sections.

### Google Maps API key (Global Settings → sensitive)

- **Optional.** Used only for **legacy** behaviors (e.g. Embed API URL fallbacks when no iframe is stored). The recommended setup is **iframe only**.

### Hidden place fields

- `lf_business_place_id`, `lf_business_place_name`, `lf_business_place_address` may still exist in the form as **hidden** inputs so manifest/sync flows do not wipe values on save; there is **no** “search business on Google Maps” admin UI.

### Wizard (manual setup)

- Mirrors the same ideas: map iframe + optional API key note; no Places search row.

---

## Header menu — “Add to header menu on save”

### Location

- **Publish** meta box (classic editor / post screen): checkbox **“Add to header menu on save”** when:
  - Post type is one of: `lf_service`, `lf_service_area`, `lf_project`, `page`, `post`
  - User has **`edit_theme_options`**
  - A menu is assigned to theme location **`header_menu`**
  - The post is **not already** in that menu

### Smart placement

Uses classes on existing menu items (from setup wizard menus):

| Post type        | Parent menu item                         |
|-----------------|-------------------------------------------|
| `lf_service`    | Item with class `lf-menu-services-parent` |
| `lf_service_area` | Item with class `lf-menu-areas-parent` |
| `post`          | Menu item linking to **Posts page** (`page_for_posts`), if present |
| `page`          | Menu item for **parent page**, if `post_parent` set and parent is in menu |
| Otherwise       | Top-level (parent 0)                      |

If the Services/Areas dropdown parents are missing, the item may be added at **top level**; fix the menu under **Appearance → Menus** (or re-run wizard menu seeding) and save again.

### Capabilities

Adding items uses `wp_update_nav_menu_item()` → requires **`edit_theme_options`**.

---

## AI Assistant — editing vs creating

### Floating assistant (front and admin)

- Proposes field-level changes; respects allowed keys and homepage vs page context.
- Inline / structure actions are logged for undo/redo where supported.

### Edit with AI (post meta box)

- Suggests edits from plain English; **URLs, slugs, and schema** are not modified by policy (see on-screen disclaimer).

### Orchestrator / AI Studio

- Full-site generation and n8n callbacks are **separate** from the metabox assistant; see `05_THEME_INTEGRATION.md` and `06_AI_PROMPT_ENGINE.md`.

---

## Troubleshooting

| Symptom | What to check |
|--------|----------------|
| New AI service draft has empty Page Builder | Model omitted `page_builder` and `content` was too short; retry with explicit section copy or longer body for fallback. |
| Main editor has text but front is blank | Theme cleared `post_content` after saving to PB; edit **Page Builder** sections. |
| Map does not show | Paste iframe in Global Settings; check Map + NAP section enabled; KSES allows iframe `src`. |
| “Add to header menu” missing | Assign a menu to **Header Menu** location; need `edit_theme_options`; item may already be in menu. |
| Service link not under Services dropdown | Ensure menu has an item with class **`lf-menu-services-parent`** (wizard-built menus include this). |

---

## Related files

| Area | File(s) |
|------|---------|
| Page Builder UI + save + nav checkbox | `inc/page-builder.php` |
| Section registry + sanitize | `inc/sections.php` |
| AI creation prompt / validate / create | `inc/ai-editing/admin-ui.php` |
| AI copy-safe field keys | `inc/ai-editing/field-rules.php` |
| Map block | `templates/blocks/map-nap.php` |
| Global Settings form | `inc/ops/menu.php` |
| Manual setup wizard | `inc/niches/wizard.php` |
