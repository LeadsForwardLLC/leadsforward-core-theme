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

## Section Controls

Each editable section now exposes hover controls:

- `⇆` Reverse media/content columns.
- `Dup` Duplicate section.
- `Hide` / `Show` Toggle section visibility.
- `Del` Delete section (requires confirmation).

All actions are logged through the AI change log and can be undone/redone.

## Keyboard Shortcuts

- `Cmd/Ctrl + K` Open command palette.
- `Alt + ArrowUp` Move selected section up.
- `Alt + ArrowDown` Move selected section down.
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
