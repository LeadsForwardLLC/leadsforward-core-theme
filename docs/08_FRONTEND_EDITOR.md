# Frontend Editor (AI Assistant)

The LeadsForward frontend editor is an always-on, inline editing layer for admins. It is designed to stay minimal in UI while exposing high-power operations through direct manipulation and shortcuts.

## Core Capabilities

- Click-to-edit text with auto-save on blur.
- Click-to-replace images using the WordPress Media Library.
- Drag sections to reorder and persist order.
- Reverse media/content columns for supported split sections.
- Hide/show sections inline.
- Delete sections with confirmation (undo/redo safe).
- Duplicate sections (page-builder contexts).
- Selected section targeting: AI Generate requests are scoped to the currently selected section when possible.

## Section Controls

Each editable section now exposes hover controls:

- `⇆` Reverse media/content columns.
- `Dup` Duplicate section.
- `Hide` / `Show` Toggle section visibility.
- `Del` Delete section (requires confirmation).

All actions are logged through the AI change log and can be undone/redone.

Notes on duplication:

- Page-builder contexts: creates a true duplicated section instance.
- Homepage context: duplicates into the next available predefined homepage slot for that section family.

## Keyboard Shortcuts

- `Cmd/Ctrl + Z` Undo.
- `Cmd/Ctrl + Shift + Z` Redo.
- `Ctrl + Y` Redo (Windows-style fallback).
- `Cmd/Ctrl + K` Open command palette.
- `Cmd/Ctrl + Shift + P` Alternate command palette shortcut.
- `/` Open command palette when not typing in an input.
- `Cmd/Ctrl + S` Save active inline text edit.
- `Shift + ?` or `F1` Show shortcut help.
- `Alt + ArrowUp` Move selected section up.
- `Alt + ArrowDown` Move selected section down.
- `Shift + ArrowUp` Move selected section up (alternate).
- `Shift + ArrowDown` Move selected section down (alternate).
- `D` Duplicate selected section.
- `H` Hide/show selected section.
- `Delete` or `Backspace` Delete selected section (confirmation required).

Notes:

- Shortcuts ignore active text inputs/contenteditable fields.
- Inline text editing still supports `Esc` to cancel and `Cmd/Ctrl + Enter` to save.

## Command Palette

The command palette provides fast access to common operations:

- Focus AI prompt
- Undo/redo
- Move section up/down
- Hide/show selected section
- Duplicate selected section
- Reverse selected columns
- Delete selected section

The palette is context-aware and hides commands when they are not valid for the selected section.

## Structure Rail

A compact left-side "Page Structure" rail lists currently rendered sections and supports:

- Quick jump/scroll to section
- Active section highlighting
- Hidden-state visibility in the list
- Drag-and-drop reordering directly from the rail
- Collapse/expand toggle (state persisted in local storage)
- Add section from global section library (with search)

### Branded UI Shell

The rail now shares the AI Assistant visual system so the editor feels like one unified LeadsForward product:

- Premium purple gradient floating trigger in collapsed mode (`☰ Structure`) with the same visual language as the AI Assistant launcher.
- Glass-style panel treatment, softer corners, and elevated shadows for a cleaner, more modern sidepanel.
- Consistent interactive states (hover, focus, active) across rail controls and section items.
- Branded section-library search and action controls for a tighter, more polished editing workflow.

## Dedicated SEO Window

SEO is now decoupled from the AI assistant body and launched from its own floating button beside `AI Assistant`.

- Separate floating launcher and panel for SEO (`SEO Health`) with the same visual system and interaction model.
- Mutual exclusivity: opening the SEO window closes the AI window, and opening AI closes SEO.
- SEO panel uses the same backend-connected snapshot pipeline and keeps `Refresh`, priority actions, SERP preview, keyword coverage, and CWV-oriented checks.
- SEO panel state persists with its own local storage key (`lfAiSeoFloatState`) without conflicting with AI panel state (`lfAiFloatState`).
- Runtime performance diagnostics are now included in the SEO vitals module via lightweight browser `Performance` API reads:
  - Core timings: `TTFB`, `DOMContentLoaded`, `window load`, `FCP`, `LCP`, `CLS`
  - Network budgets: request count, transferred bytes, script bytes, image bytes
  - Optional backend visibility: `Server-Timing` metrics (when exposed by the stack/server)

### Launcher Positioning + Brand Rules

- `AI Assistant` and `SEO Health` launchers use a flat LeadsForward purple style (no gradient / no drop shadow).
- Launcher spacing is dynamically computed on desktop so both buttons stay visually tight and aligned.
- Structure rail collapsed launcher is positioned higher for faster access while editing.
- SEO vitals also include request/transfer-size budget signals plus available `Server-Timing` metrics from the current page response.
- Launchers use a flat purple branded style and dynamic spacing so `SEO Health` sits tighter to `AI Assistant`.

### Performance Grade Chip

- SEO window now includes a compact performance grade chip (`Perf A/B/C...`) with trend delta vs the previous refresh in the same session.
- Grade and trend are computed from the live performance subscore and update on each snapshot refresh.

## Hero Lists Persistence Guardrails

Hero pills (`.lf-hero-chips`) and hero proof checklist (`.lf-block-hero__card-list`) are two visual editors for the same source field (`hero_proof_bullets`).

- Both editors now normalize text-node structure before save to prevent duplicate text artifacts.
- Saves are synchronized so pill edits update proof checklist DOM and vice versa before persistence.
- A single canonical payload is persisted to backend settings, ensuring reliable reload behavior.
- The collapsed Structure launcher is positioned higher for faster access while editing.
- Homepage hero persistence now resolves hero section IDs by base type (`hero`, `hero_1`, etc.) so subheadline/pills/checklist edits save reliably across legacy homepage configs.
- Homepage hero wrappers now harden `section_id` fallback (empty IDs are normalized to section type), preventing silent no-op saves for hero pills/checklist on reload.
- Hero subheadline inline edits now prefer explicit `hero_subheadline` field-key persistence for better homepage reliability.
- Homepage save endpoints now use the same resolver for checklist, generic list fields, CTA, and media updates, so legacy homepage IDs cannot drift writes into missing rows.

## FAQ Library Selection

FAQ sections now support curated, per-section FAQ selection instead of only global FAQ order.

- Frontend editor can open an FAQ picker, search the FAQ database, and add selected FAQs into the section.
- Editors can remove and reorder selected FAQs directly in the section; selection persists per page/section.
- The section stores selected FAQ IDs in `faq_selected_ids` (line list) through section settings.
- If no explicit selection exists, theme-side auto-selection chooses best-fit FAQs using section/page intent overlap.

## Persistence + Safety

The frontend editor persists through AJAX endpoints and logs all structural operations:

- `lf_ai_reorder_sections`
- `lf_ai_toggle_section_columns`
- `lf_ai_toggle_section_visibility`
- `lf_ai_delete_section`
- `lf_ai_duplicate_section`

Undo/redo is powered by change keys in the logging layer, including:

- `__section_order`
- `__section_layout::<section_id>`
- `__section_enabled::<section_id>`
- `__section_record::<section_id>`
