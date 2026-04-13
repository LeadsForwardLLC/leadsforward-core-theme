# Icon System (Tabler SVG Runtime)

The theme icon runtime is intentionally simple and deterministic:

- Runtime source: `assets/icons/tabler/outline/*.svg` (Tabler Icons, MIT licensed)
- Renderer: `inc/icons/icon-render.php` via `lf_icon()`
- Consumers: header, footer, section blocks, and admin UI icon helpers

## How Icons Render

1. PHP helper `lf_icon('map-pin')` normalizes and validates the slug.
2. It loads the matching Tabler SVG file (cached in-memory per request).
3. It injects theme classes/ARIA and outputs inline `<svg>…</svg>` markup.

## Cleanup Policy

Keep the Tabler SVG files under `assets/icons/tabler/`. They are the source of truth.

## Updating Icon Set

If you need to add or replace icons:

1. Update alias/pack mappings in:
   - `inc/icons.php`
   - `inc/icons/icon-packs.php`
3. Use `lf_icon('slug')` in templates/components.

## Important Note

The theme does not rely on JavaScript hydration (`data-*` icon replacement). Icons render server-side as inline SVG.
