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
- [ ] **Immediate updates**: besides **Check now** in wp-admin, a controller operator can **Push update** to one site, a bulk selection, or all sites matching a tag (controller Fleet Updates UI). Push uses the same **HMAC** headers (`X-LF-Site`, `X-LF-Timestamp`, `X-LF-Nonce`, `X-LF-Signature`) as other fleet API traffic; optional **override** matches “force past rollout” behavior—documented in `docs/05_THEME_INTEGRATION.md`.
- [ ] **WP-Cron / pull still matters**: heartbeat and recurring **update checks** still run on the normal fleet cron unless you always push; quiet sites should keep **system cron** → `wp-cron.php` or rely on admin **Check now** / controller push after releases.
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
