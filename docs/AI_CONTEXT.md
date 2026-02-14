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
