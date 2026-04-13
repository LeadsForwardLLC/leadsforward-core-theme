# Production readiness (LeadsForward Core)

Use this checklist before launch and when auditing a fleet or staging site. **Theme version** is defined in `functions.php` as `LF_THEME_VERSION` and must match `Version` in `style.css`.

## Version and integrity

- [ ] `LF_THEME_VERSION` in `functions.php` equals `Version` in `style.css`.
- [ ] No PHP syntax errors in custom overlays (run `php -l` on changed files).
- [ ] Advanced Custom Fields (ACF) active if the site uses options pages, CPT field groups, or blocks.

## WordPress baseline

- [ ] **Settings → Permalinks**: “Post name” saved once (flushes rewrites for `/theme-docs/` and fleet controller routes if applicable).
- [ ] **Settings → Reading**: static front page set when the theme expects a marketing homepage.
- [ ] **Appearance → Menus**: primary navigation assigned to the **Header Menu** theme location (not only a menu *named* “Header Menu”).
- [ ] **Users**: only trusted roles get `edit_theme_options` (LeadsForward admin, Fleet Updates, front-end editor).

## Fleet theme updates (if used)

- [ ] **LeadsForward → Fleet Updates**: connection bundle saved; last check shows success when the controller is reachable.
- [ ] **Rollout** on the controller matches this site (scope, selected sites, or tag).
- [ ] **WP-Cron**: low-traffic sites should use a **system cron** hitting `wp-cron.php` on a steady interval so the fleet schedule actually runs; otherwise use **Check now** after publishing a theme release. For faster rollouts (within 5–60 minutes), a developer can filter `lf_fleet_updates_cron_interval` in seconds — see `docs/05_THEME_INTEGRATION.md`.
- [ ] After a failed manual theme update: confirm the theme directory is intact; re-save **Header Menu** location if navigation disappeared (see Menus → Manage Locations).

See `docs/05_THEME_INTEGRATION.md` (Fleet section) for architecture and security notes.

## Front-end editor and AI

- [ ] OpenAI key (if used) in Global Settings; restrict who can manage theme options.
- [ ] Editors understand: **Layout history** is server-backed revision snapshots; **refresh** reloads the list; **Live** marks the row matching the current layout version.
- [ ] **Rich Text** sections: **Insert icon** inserts `[lf_icon name="slug"]`; icons render server-side from Tabler SVGs (`docs/07_ICON_SYSTEM.md`).

## SEO and launch

- [ ] **SEO & Performance**: meta templates, header scripts (e.g. GTM), sitemap toggles.
- [ ] **Site health** tab: pre-launch check run; GTM/manifester warnings understood or resolved.
- [ ] Legal pages and business entity (NAP) complete for schema.

## Documentation map

- Operator guide: **LeadsForward → Theme Documentation** (wp-admin) and logged-in **`/theme-docs/`**.
- Developer index: `docs/README.md`.
