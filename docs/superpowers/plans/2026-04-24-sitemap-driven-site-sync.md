# Sitemap-driven Site Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Airtable `Sitemaps` (Primary View) the canonical driver for page inventory, `{city}`-templated slugs, per-page primary keywords, menu structure, publish state, and safe internal linking for both new builds and ongoing sync.

**Architecture:** Add a small Airtable sitemap client + normalizer that produces `PageSpec[]`, persist a local cache/index, then reconcile WordPress pages/CPT posts + menus from that spec. Extend AI/link guardrails to only allow internal links to sitemap-known published targets.

**Tech Stack:** WordPress (PHP), existing Airtable PAT integration, WP-Cron, options + postmeta, `Theme_Upgrader`/menus APIs, existing SEO keyword engine + internal-link guardrails.

---

## File structure (new/modified)

**Create**
- `inc/airtable/sitemaps.php` — Airtable fetch + normalize `PageSpec[]` from `Sitemaps` table/view
- `inc/sitemap-sync/types.php` — `PageSpec` helpers (slug templating, validation, key generation)
- `inc/sitemap-sync/reconcile.php` — apply PageSpecs to WP (create/update posts, statuses, keywords, index)
- `inc/sitemap-sync/menus.php` — build Header menu from PageSpecs (More always rightmost; published only)
- `inc/sitemap-sync/admin.php` — admin UI + “Sync now” action + diagnostics
- `docs/09_SITEMAP_SYNC.md` — team/user docs (how it works, settings, troubleshooting)

**Modify**
- `functions.php` — load new files + bump version
- `inc/ai-studio-airtable.php` — add settings for sitemap table/view defaults (and use existing PAT/base)
- `inc/seo/internal-link-guardrails.php` — add allowlist enforcement (sitemap-known + published)
- `inc/ai-editing/handler.php` — ensure AI-applied HTML runs allowlist stripping (already strips broken links; extend)
- `inc/niches/setup-runner.php` — optional: if sitemap sync enabled, prefer sitemap-driven menu build

---

### Task 1: Add Airtable sitemap settings + fetch/normalize client

**Files:**
- Create: `inc/airtable/sitemaps.php`
- Modify: `inc/ai-studio-airtable.php`

- [ ] **Step 1: Add options for sitemap table/view**
  - Add new options (with defaults):
    - `lf_ai_airtable_sitemaps_table` default `Sitemaps`
    - `lf_ai_airtable_sitemaps_view` default `Primary View`

- [ ] **Step 2: Implement Airtable fetch for sitemap rows**

Create `inc/airtable/sitemaps.php` with:

```php
<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * @return array{ok:bool, rows:list<array<string,mixed>>, error:string}
 */
function lf_airtable_sitemaps_fetch_rows(): array {
	$settings = function_exists('lf_ai_studio_airtable_get_settings') ? lf_ai_studio_airtable_get_settings() : [];
	$enabled = !empty($settings['enabled']);
	$pat = (string) ($settings['pat'] ?? '');
	$base_id = (string) ($settings['base_id'] ?? '');
	if (!$enabled || $pat === '' || $base_id === '') {
		return ['ok' => false, 'rows' => [], 'error' => 'airtable_not_configured'];
	}
	$table = (string) get_option('lf_ai_airtable_sitemaps_table', 'Sitemaps');
	$view = (string) get_option('lf_ai_airtable_sitemaps_view', 'Primary View');

	// Reuse schema resolution from AI Studio Airtable integration.
	$resolved = function_exists('lf_ai_studio_airtable_resolve_table_view')
		? lf_ai_studio_airtable_resolve_table_view([
			'pat' => $pat,
			'base_id' => $base_id,
			'table' => $table,
			'view' => $view,
		])
		: [];
	if (!empty($resolved['error'])) {
		return ['ok' => false, 'rows' => [], 'error' => (string) $resolved['error']];
	}
	$table_id = (string) ($resolved['table_id'] ?? $table);
	$view_id = (string) ($resolved['view'] ?? '');

	$base_url = function_exists('lf_ai_studio_airtable_base_url')
		? lf_ai_studio_airtable_base_url(['base_id' => $base_id, 'table' => $table_id])
		: '';
	if ($base_url === '') {
		return ['ok' => false, 'rows' => [], 'error' => 'airtable_base_url_failed'];
	}

	$params = ['pageSize' => 100];
	if ($view_id !== '') $params['view'] = $view_id;

	$rows = [];
	$offset = '';
	$pages = 0;
	do {
		$page_params = $params;
		if ($offset !== '') $page_params['offset'] = $offset;
		$response = function_exists('lf_ai_studio_airtable_get')
			? lf_ai_studio_airtable_get($base_url, $page_params, $pat)
			: ['error' => 'airtable_get_missing'];
		if (!empty($response['error'])) {
			return ['ok' => false, 'rows' => [], 'error' => (string) $response['error']];
		}
		$data = is_array($response['data'] ?? null) ? $response['data'] : [];
		foreach ((array) ($data['records'] ?? []) as $record) {
			if (!is_array($record)) continue;
			$fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
			$rows[] = $fields;
		}
		$offset = isset($data['offset']) ? (string) $data['offset'] : '';
		$pages++;
	} while ($offset !== '' && $pages < 50);

	return ['ok' => true, 'rows' => $rows, 'error' => ''];
}
```

- [ ] **Step 3: Normalize fields to PageSpecs with strict validation**
  - Implement `lf_sitemap_specs_from_airtable_rows()` returning:
    - `specs: PageSpec[]`
    - `errors: string[]` (missing keyword/slug/title, unknown menu group, etc.)
  - Map columns exactly:
    - `title`: `Page title | Niche` (fallback `Page title (service)`)
    - `niche`: `Niche`
    - `priority`: `Priority`
    - `primary_keyword`: `Keyword`
    - `menu_group`: `menu group`
    - `menu_hierarchy`: `Menu hiearchy`
    - `slug_template`: `Slug`

- [ ] **Step 4: Add a small CLI-style sanity check**
  - Add a function `lf_sitemap_sync_debug_summary()` that counts:
    - specs total
    - invalid specs
    - number with `{city}` token

- [ ] **Step 5: Commit**

---

### Task 2: Add slug templating + PageSpec helpers

**Files:**
- Create: `inc/sitemap-sync/types.php`

- [ ] **Step 1: Implement token replacement**

```php
/**
 * @return array{ok:bool, slug:string, error:string}
 */
function lf_sitemap_resolve_slug_template(string $template, string $city): array {
	$template = trim($template);
	if ($template === '') return ['ok' => false, 'slug' => '', 'error' => 'missing_slug'];

	$city_slug = sanitize_title($city);
	$slug = $template;
	if (strpos($slug, '{city}') !== false) {
		if ($city_slug === '') {
			$slug = str_replace('{city}', '', $slug);
			$slug = preg_replace('#/+#', '/', (string) $slug);
			$slug = preg_replace('#-+#', '-', (string) $slug);
			$slug = trim((string) $slug, "-/ \t\n\r\0\x0B");
			$slug = '/' . trim($slug, '/') . '/';
			return ['ok' => false, 'slug' => $slug, 'error' => 'missing_city_for_template'];
		}
		$slug = str_replace('{city}', $city_slug, $slug);
	}
	$slug = '/' . trim($slug, '/') . '/';
	return ['ok' => true, 'slug' => $slug, 'error' => ''];
}
```

- [ ] **Step 2: Implement stable key**

```php
function lf_sitemap_spec_key(string $niche, string $slug_template): string {
	return hash('sha256', strtolower(trim($niche)) . ':' . strtolower(trim($slug_template)));
}
```

- [ ] **Step 3: Commit**

---

### Task 3: Reconcile PageSpecs into WordPress pages + keyword meta

**Files:**
- Create: `inc/sitemap-sync/reconcile.php`
- Modify: `inc/seo/seo-keyword-engine.php` (to prefer explicit sitemap keyword if present)

- [ ] **Step 1: Implement sitemap cache + index options**
  - `lf_airtable_sitemap_cache` JSON (the last normalized specs)
  - `lf_airtable_sitemap_cache_at` timestamp
  - `lf_sitemap_page_index` JSON map: `slug_resolved -> {post_id,status,type}`

- [ ] **Step 2: Implement upsert for pages**
  - For now, scope to WP `page` + existing CPTs `lf_service` and `lf_service_area` only when `page_type` implies them.
  - Create/update logic:
    - find by `_lf_sitemap_key` first
    - else find by path slug (page) or `post_name` (CPT)
    - update `post_title`, `post_name` (resolved), and `post_status` (publish/draft/future)
    - set `_lf_sitemap_key` and `_lf_sitemap_slug_template`
    - set `_lf_seo_primary_keyword` from sitemap keyword

- [ ] **Step 3: Publishing strategy (recommended default)**
  - Publish core hubs always.
  - For “detail” pages: publish top 50% by priority; rest draft.
  - Store settings:
    - `lf_sitemap_publish_ratio` default `0.5`
    - `lf_sitemap_unpublished_mode` default `draft`

- [ ] **Step 4: Commit**

---

### Task 4: Airtable-driven menu build (Header menu)

**Files:**
- Create: `inc/sitemap-sync/menus.php`

- [ ] **Step 1: Build a normalized menu tree from PageSpecs**
  - Group by `menu_group` (Home/About/Services/Service Areas/More)
  - Sort within group by `priority`
  - Apply `menu_hierarchy` to nest children

- [ ] **Step 2: Enforce “More” rightmost**
  - Always render “More” last if it has ≥1 published child.

- [ ] **Step 3: Only include published posts**
  - Use the index built in Task 3 to look up status and URL.

- [ ] **Step 4: Apply to WP menu**
  - Reuse `wp_update_nav_menu_item` patterns from `inc/niches/setup-runner.php` but driven by PageSpecs.

- [ ] **Step 5: Commit**

---

### Task 5: Admin UI + Cron job for sitemap sync

**Files:**
- Create: `inc/sitemap-sync/admin.php`
- Modify: `functions.php`

- [ ] **Step 1: Add cron hook**
  - Hook: `lf_sitemap_sync_cron`
  - Schedule: hourly by default (configurable later)

- [ ] **Step 2: Add “Sync now” admin action**
  - Admin page under LeadsForward: “Sitemap Sync”
  - Button triggers reconcile, shows:
    - counts (created/updated/skipped/errors)
    - list of validation errors
    - last run timestamp

- [ ] **Step 3: Commit**

---

### Task 6: Internal link guardrails (sitemap allowlist)

**Files:**
- Modify: `inc/seo/internal-link-guardrails.php`
- Modify: `inc/ai-editing/handler.php`

- [ ] **Step 1: Build “allowed internal URLs” list**
  - From `lf_sitemap_page_index` include only published targets.

- [ ] **Step 2: Strip internal links not in allowlist**
  - Extend existing `lf_strip_broken_internal_links_from_html()` behavior:
    - if internal href is not published OR not in sitemap allowlist → replace `<a>` with plain text.

- [ ] **Step 3: Ensure this runs on AI apply path**
  - `lf_ai_apply_proposal()` already calls broken-link stripper; update it to call the allowlist-aware version (same function, enhanced).

- [ ] **Step 4: Commit**

---

### Task 7: AI keyword placement rules + city pairing (prompt plumbing)

**Files:**
- Modify: `inc/ai-studio.php` and/or prompt builder location used for page generation (exact prompt assembly file to be found during execution)
- Modify: `inc/seo/seo-settings.php` (add toggle for “strict keyword placement”)

- [ ] **Step 1: Inject per-page keyword + city into generation context**
  - Prefer sitemap keyword (post meta `_lf_seo_primary_keyword` set from Task 3).

- [ ] **Step 2: Add explicit SEO placement checklist to prompts**
  - H1 / first paragraph / H2 / CTA / FAQ rule list.

- [ ] **Step 3: Add a post-generation compliance pass**
  - If generated content misses required placements, run a constrained “patch pass”.

- [ ] **Step 4: Commit**

---

### Task 8: Documentation

**Files:**
- Create: `docs/09_SITEMAP_SYNC.md`

- [ ] **Step 1: Document Airtable expectations**
  - Required columns
  - `{city}` token behavior
  - Suggested new columns (`Page Type`, `Publish Mode`)

- [ ] **Step 2: Document WP settings + troubleshooting**
  - Where to set table/view
  - How to run “Sync now”
  - How to interpret validation errors

- [ ] **Step 3: Commit**

---

## Verification checklist (per ship)

- [ ] Run PHP lint on touched files: `php -l <file>`
- [ ] In WP admin on controller and one fleet site:
  - Run “Sync now” and verify sitemap cache populates
  - Verify at least one `{city}` slug resolves correctly
  - Verify Header menu matches Airtable ordering and “More” is rightmost
  - Verify AI-applied HTML cannot link to non-sitemap pages

