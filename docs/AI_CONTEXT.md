# AI Context

## Image Naming Strategy

Use descriptive filenames so deterministic matching can reliably map Media Library assets to sections.

Recommended patterns:

- `roof-repair-kansas-city-1.jpg`
- `kitchen-remodel-sarasota-modern.jpg`
- `bathroom-remodel-before-after.jpg`
- `general-contractor-team.jpg`

How matching works:

1. Filenames are normalized to lowercase tokens (extension removed, punctuation collapsed to dashes).
2. Images are matched in deterministic priority order:
   - exact service slug token match
   - exact city token match
   - exact niche token match
   - primary keyword token match
   - secondary keyword token match
   - generic fallback images containing `general`
3. Tie-breaks use a stable seed + sorted filename hash, so results are repeatable.

Best practices:

- Include the core service (`roof-repair`, `kitchen-remodel`).
- Include the target city when relevant (`kansas-city`, `sarasota`).
- Include niche-specific descriptors when possible (`contractor`, `plumber`, `hvac`).
- Use `general` in broadly reusable images.

Automation note:

- The theme now auto-renames uploaded images, optimizes quality, and sets missing ALT text.
- Descriptive naming still improves matching confidence, but manual naming is no longer required for baseline operation.

## Frontend Editor Context

The AI Assistant includes an always-on frontend editor for admins:

- Inline text editing and inline image replacement.
- Section-level structure controls (reorder, reverse columns, hide/show, delete, duplicate where supported).
- Command palette (`Cmd/Ctrl + K`) and keyboard-first structural actions.
- Context-aware section navigator rail with active section focus.

All structural operations are persisted and undo/redo safe via the AI action log:

- `__section_order`
- `__section_layout::<section_id>`
- `__section_enabled::<section_id>`
- `__section_record::<section_id>`
