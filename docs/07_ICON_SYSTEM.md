# Icon System (Sprite-Only Runtime)

The theme icon runtime is intentionally simple and deterministic:

- Runtime source: `assets/icons/sprite.svg`
- Renderer: `inc/icons/icon-render.php` via `lf_icon()`
- Injection points: `wp_footer` and `admin_footer`
- Consumers: header, footer, section blocks, and admin UI icon helpers

## How Icons Render

1. PHP helper `lf_icon('map-pin')` normalizes and validates the slug.
2. It renders `<svg><use href="#lf-icon-map-pin"></use></svg>`.
3. Symbol definitions come from the inlined `assets/icons/sprite.svg`.

No per-request file lookups are performed for individual icon files.

## Cleanup Policy

- Keep: `assets/icons/sprite.svg`
- Remove/avoid shipping duplicate raw icon files in `assets/icons/*.svg`
  when they are not referenced at runtime.

This keeps the theme lighter and prevents confusion about which icon source is authoritative.

## Updating Icon Set

If you need to add or replace icons:

1. Update `assets/icons/sprite.svg` with the required `<symbol id="lf-icon-...">` entries.
2. If needed, update alias/pack mappings in:
   - `inc/icons.php`
   - `inc/icons/icon-packs.php`
3. Use `lf_icon('slug')` in templates/components.

## Important Note

The icon architecture is Lucide-style sprite usage at runtime. The theme does not rely on `data-lucide` JS hydration in production templates.
