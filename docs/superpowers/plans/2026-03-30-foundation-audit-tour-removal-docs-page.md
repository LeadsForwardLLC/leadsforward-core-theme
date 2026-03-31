# Foundation Audit + Tour Removal + Docs Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ensure every page type and section is wired end-to-end (registry -> blueprint -> n8n -> apply -> templates), remove the guided tour feature completely, and add a logged-in-only, noindex docs page with sidebar navigation.

**Architecture:** Add a wiring report and audit hooks in the theme to surface registry and blueprint gaps, extend payload construction to include missing scope targets (projects), hard-fail n8n when updates are empty, and introduce a virtual docs route with a dedicated template and CSS. Keep existing page templates intact while ensuring AI updates write to visible content fields.

**Tech Stack:** WordPress (PHP 8+), theme PHP templates, n8n workflow JSON, WordPress rewrite/query vars, theme assets (CSS).

---

## Scope Check
This spec spans three subsystems (foundation wiring, guided tour removal, docs page). If you prefer separate plans, split into three files. This plan keeps them sequenced to match the approved spec.

## File Structure

**Create:**
- `inc/ai-studio-wiring.php` (wiring report helpers)
- `tools/lf-wiring-check.php` (CLI wiring check)
- `inc/docs-page.php` (docs route + template loader + access control)
- `templates/lf-docs.php` (docs page markup)
- `assets/css/docs-page.css` (docs styling)

**Modify:**
- `functions.php` (load new inc files, remove tour-mode)
- `inc/ai-studio.php` (audit integration, payload coverage, apply mapping, debug logs)
- `inc/page-builder.php` (projects context support)
- `docs/n8n-workflow.json` (hard-fail for empty/missing updates)
- `inc/ops/menu.php` (remove tour setting + option update)

**Delete:**
- `inc/tour-mode.php`
- `assets/js/tour-mode.js`
- `assets/css/tour-mode.css`

---

### Task 1: Add Wiring Report + CLI Check

**Files:**
- Create: `inc/ai-studio-wiring.php`
- Create: `tools/lf-wiring-check.php`
- Modify: `functions.php`
- Modify: `inc/ai-studio.php`

- [ ] **Step 1: Write the failing test (CLI wiring check stub)**

Create `tools/lf-wiring-check.php` with a temporary hard failure so the first run fails before wiring helpers exist.

```php
<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	require_once dirname(__DIR__, 4) . '/wp-load.php';
}

if (!function_exists('lf_ai_studio_wiring_report')) {
	fwrite(STDERR, "Missing lf_ai_studio_wiring_report()\n");
	exit(1);
}

exit(1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tools/lf-wiring-check.php`  
Expected: exit code `1` with "Missing lf_ai_studio_wiring_report()".

- [ ] **Step 3: Write minimal implementation (wiring report helpers)**

Create `inc/ai-studio-wiring.php`:

```php
<?php
/**
 * Wiring report: registry -> blueprint -> payload checks.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_ai_studio_wiring_check_registry(array $registry): array {
	$issues = [];
	foreach ($registry as $section_id => $section) {
		if (!is_array($section)) {
			$issues[] = "Section {$section_id} must be an array.";
			continue;
		}
		$fields = $section['fields'] ?? [];
		if (!is_array($fields) || empty($fields)) {
			$issues[] = "Section {$section_id} has no fields.";
			continue;
		}
		foreach ($fields as $field) {
			if (!is_array($field)) {
				$issues[] = "Section {$section_id} has a non-array field definition.";
				continue;
			}
			$key = (string) ($field['key'] ?? '');
			$type = (string) ($field['type'] ?? '');
			$label = (string) ($field['label'] ?? '');
			if ($key === '' || $type === '' || $label === '') {
				$issues[] = "Section {$section_id} has an incomplete field definition.";
			}
		}
		$render = (string) ($section['render'] ?? '');
		if ($render !== '' && !function_exists($render)) {
			$issues[] = "Section {$section_id} render function missing: {$render}.";
		}
	}
	return $issues;
}

function lf_ai_studio_wiring_check_blueprints(array $payload): array {
	$issues = [];
	$blueprints = $payload['blueprints'] ?? [];
	if (!is_array($blueprints) || empty($blueprints)) {
		return ['Payload missing blueprints[].'];
	}
	foreach ($blueprints as $idx => $blueprint) {
		if (!is_array($blueprint)) {
			$issues[] = "Blueprint {$idx} must be an object.";
			continue;
		}
		$sections = $blueprint['sections'] ?? [];
		if (!is_array($sections) || empty($sections)) {
			$page = (string) ($blueprint['page'] ?? $blueprint['page_type'] ?? 'unknown');
			$issues[] = "Blueprint {$idx} ({$page}) missing sections[].";
			continue;
		}
		foreach ($sections as $section) {
			$section_id = (string) ($section['section_id'] ?? '');
			$allowed = $section['allowed_field_keys'] ?? [];
			if ($section_id === '' || !is_array($allowed) || empty($allowed)) {
				$issues[] = "Blueprint {$idx} section has missing section_id or allowed_field_keys.";
			}
		}
	}
	return $issues;
}

function lf_ai_studio_wiring_payload_summary(array $payload): array {
	$blueprints = is_array($payload['blueprints'] ?? null) ? $payload['blueprints'] : [];
	$page_types = [];
	foreach ($blueprints as $bp) {
		if (!is_array($bp)) {
			continue;
		}
		$page = (string) ($bp['page'] ?? $bp['page_type'] ?? '');
		if ($page !== '') {
			$page_types[$page] = ($page_types[$page] ?? 0) + 1;
		}
	}
	return [
		'count' => count($blueprints),
		'page_types' => $page_types,
		'generation_scope' => is_array($payload['generation_scope'] ?? null) ? $payload['generation_scope'] : [],
	];
}

function lf_ai_studio_wiring_report(): array {
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	$payload = function_exists('lf_ai_studio_build_full_site_payload') ? lf_ai_studio_build_full_site_payload(true) : [];
	$registry_issues = lf_ai_studio_wiring_check_registry(is_array($registry) ? $registry : []);
	$blueprint_issues = lf_ai_studio_wiring_check_blueprints(is_array($payload) ? $payload : []);
	$payload_summary = lf_ai_studio_wiring_payload_summary(is_array($payload) ? $payload : []);

	return [
		'registry_issues' => $registry_issues,
		'blueprint_issues' => $blueprint_issues,
		'payload_summary' => $payload_summary,
		'timestamp' => time(),
	];
}
```

Load the new file in `functions.php` near AI Studio includes:

```php
lf_load_inc('ai-studio-wiring.php');
```

Integrate into the AI audit report in `inc/ai-studio.php` (inside `lf_ai_studio_audit_site_content()` before return):

```php
$report['wiring'] = function_exists('lf_ai_studio_wiring_report')
	? lf_ai_studio_wiring_report()
	: [];
```

Update the CLI script to pass when helpers exist:

```php
<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	require_once dirname(__DIR__, 4) . '/wp-load.php';
}

if (!function_exists('lf_ai_studio_wiring_report')) {
	fwrite(STDERR, "Missing lf_ai_studio_wiring_report()\n");
	exit(1);
}

$report = lf_ai_studio_wiring_report();
$issues = array_merge(
	$report['registry_issues'] ?? [],
	$report['blueprint_issues'] ?? []
);

echo wp_json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
exit(empty($issues) ? 0 : 1);
```

- [ ] **Step 4: Run test to verify it passes (or reports issues)**

Run: `php tools/lf-wiring-check.php`  
Expected: JSON output. Exit code `0` if no issues, `1` if issues remain.

- [ ] **Step 5: Commit**

```bash
git add inc/ai-studio-wiring.php tools/lf-wiring-check.php functions.php inc/ai-studio.php
git commit -m "feat: add wiring report and CLI check"
```

---

### Task 2: Fix Payload Coverage + Apply Mapping to Visible Content

**Files:**
- Modify: `inc/page-builder.php`
- Modify: `inc/ai-studio.php`

- [ ] **Step 1: Write the failing test**

Add wiring assertions to the CLI report output that check for all required page types when scope enables them:

```php
$summary = $report['payload_summary'] ?? [];
$pageTypes = $summary['page_types'] ?? [];
$scope = $summary['generation_scope'] ?? [];
$required = [
	'homepage' => ['homepage'],
	'services' => ['service', 'services_overview'],
	'service_areas' => ['service_area', 'service_areas_overview'],
	'core_pages' => ['about', 'contact', 'reviews', 'blog', 'sitemap', 'privacy_policy', 'terms_of_service', 'thank_you'],
	'blog_posts' => ['post'],
	'projects' => ['project'],
];
foreach ($required as $scopeKey => $types) {
	if (empty($scope[$scopeKey])) {
		continue;
	}
	foreach ($types as $type) {
		if (empty($pageTypes[$type])) {
			fwrite(STDERR, "Missing {$type} blueprints\n");
			exit(1);
		}
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tools/lf-wiring-check.php`  
Expected: exit code `1` if projects are missing from payload.

- [ ] **Step 3: Enable projects in Page Builder context**

Update `inc/page-builder.php` to recognize `lf_project` as a builder context:

```php
function lf_pb_get_context_for_post(\WP_Post $post): string {
	if ($post->post_type === 'lf_service') {
		return 'service';
	}
	if ($post->post_type === 'lf_service_area') {
		return 'service_area';
	}
	if ($post->post_type === 'lf_project') {
		return 'post';
	}
	if ($post->post_type === 'page') {
		if ($post->post_name === 'home') {
			return '';
		}
		return 'page';
	}
	if ($post->post_type === 'post') {
		return 'post';
	}
	return '';
}
```

Add `lf_project` to the meta box and admin assets post type list:

```php
if (!$screen || !in_array($screen->post_type, ['lf_service', 'lf_service_area', 'lf_project', 'page', 'post'], true)) {
	return;
}
```

- [ ] **Step 4: Include projects in full-site payload**

In `lf_ai_studio_build_full_site_payload()` (in `inc/ai-studio.php`) add a `projects` block:

```php
if (!empty($scope['projects'])) {
	$projects = get_posts([
		'post_type' => 'lf_project',
		'post_status' => 'publish',
		'posts_per_page' => 200,
		'orderby' => 'date',
		'order' => 'DESC',
	]);
	foreach ($projects as $project) {
		if (!$project instanceof \WP_Post) {
			continue;
		}
		$blueprint = lf_ai_studio_build_post_blueprint($project, 'project', 'project_case_study', '');
		if (!empty($blueprint)) {
			$blueprints[] = $blueprint;
		}
	}
}
```

- [ ] **Step 5: Include privacy/terms in core pages**

Extend the core pages map in `lf_ai_studio_build_full_site_payload()`:

```php
$core_pages = [
	'contact' => ['page' => 'contact', 'intent' => 'contact', 'keyword' => ''],
	'reviews' => ['page' => 'reviews', 'intent' => 'reviews', 'keyword' => ''],
	'blog' => ['page' => 'blog', 'intent' => 'blog', 'keyword' => ''],
	'sitemap' => ['page' => 'sitemap', 'intent' => 'sitemap', 'keyword' => ''],
	'thank-you' => ['page' => 'thank_you', 'intent' => 'thank_you', 'keyword' => ''],
	'privacy-policy' => ['page' => 'privacy_policy', 'intent' => 'privacy_policy', 'keyword' => ''],
	'terms-of-service' => ['page' => 'terms_of_service', 'intent' => 'terms_of_service', 'keyword' => ''],
];
```

- [ ] **Step 6: Hard-fail empty updates in WP preflight**

In `lf_ai_studio_prevalidate_orchestrator_updates()`:

```php
if (empty($updates)) {
	return [__('Updates array is empty.', 'leadsforward-core')];
}
```

- [ ] **Step 7: Map PB content into post_content for posts/projects**

Add helper in `inc/ai-studio.php`:

```php
function lf_ai_studio_extract_primary_post_content(array $sections, array $order): array {
	$content = '';
	$excerpt = '';
	foreach ($order as $section_id) {
		$section = $sections[$section_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		if (!in_array($type, ['content', 'content_centered', 'content_image', 'image_content', 'content_image_a', 'image_content_b', 'content_image_c'], true)) {
			continue;
		}
		$body = trim((string) ($settings['section_body'] ?? ''));
		$body_secondary = trim((string) ($settings['section_body_secondary'] ?? ''));
		$intro = trim((string) ($settings['section_intro'] ?? ''));
		$content = $body_secondary !== '' && $body !== '' ? ($body . "\n\n" . $body_secondary) : ($body !== '' ? $body : $body_secondary);
		if ($excerpt === '') {
			$excerpt = $intro;
		}
		if ($content !== '') {
			break;
		}
	}
	return [
		'content' => $content,
		'excerpt' => $excerpt,
	];
}
```

Then in `lf_apply_orchestrator_updates()` after post-meta updates are staged/applied, update post content for `post` and `lf_project`:

```php
if (in_array($post->post_type, ['post', 'lf_project'], true)) {
	$derived = lf_ai_studio_extract_primary_post_content($sections, $order);
	$content = trim((string) ($derived['content'] ?? ''));
	$excerpt = trim((string) ($derived['excerpt'] ?? ''));
	if ($content !== '') {
		wp_update_post([
			'ID' => $post_id,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
		]);
	}
}
```

Add debug-only logging when a section or field is dropped for being unregistered/unsupported:

```php
if (defined('WP_DEBUG') && WP_DEBUG && ($type === '' || !isset($registry[$type]))) {
	error_log(sprintf('LF DEBUG: Dropped section "%s" on post %d (unregistered type).', $instance_id, $post_id));
}
if (defined('WP_DEBUG') && WP_DEBUG) {
	error_log(sprintf('LF DEBUG: Dropped field "%s" on post %d (section %s).', $field_key, $post_id, $instance_id));
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php tools/lf-wiring-check.php`  
Expected: no payload coverage failures; exit code `0` or only known issues.

- [ ] **Step 9: Commit**

```bash
git add inc/page-builder.php inc/ai-studio.php tools/lf-wiring-check.php
git commit -m "feat: include projects and map PB content to posts"
```

---

### Task 3: n8n Workflow Hard-Fail on Empty/Missing Updates

**Files:**
- Modify: `docs/n8n-workflow.json`

- [ ] **Step 1: Write the failing test**

In `docs/n8n-workflow.json`, add throw statements (see below). This ensures the workflow fails instead of silently succeeding when updates are empty.

- [ ] **Step 2: Update "Split Blueprints + Deterministic Metadata"**

Replace its `jsCode` with this full version (hard-fails on missing blueprints or sections):

```js
// Split FULL payload into ONE-blueprint-per-item for the LLM
// Hardened: preserves full blueprint structure including sections

const raw = $json.body ?? $json;
let payload = raw.body ?? raw;

function tryParse(value) {
  if (typeof value !== 'string') return value;
  try { return JSON.parse(value); } catch (e) { return value; }
}

payload = tryParse(payload);
if (payload && typeof payload === 'object' && typeof payload.body === 'string') {
  payload = tryParse(payload.body);
}

if (!payload || !Array.isArray(payload.blueprints)) {
  throw new Error("Invalid payload: missing blueprints[]");
}

const homepageBlueprint = payload.blueprints.find(bp => {
  const pageType = bp?.page_type || bp?.page;
  return pageType === "homepage";
});

const blueprintFaqPool = Array.isArray(homepageBlueprint?.faqs)
  ? homepageBlueprint.faqs
  : [];
const payloadFaqPool = Array.isArray(payload.global_faq_pool)
  ? payload.global_faq_pool
  : [];

function inferPageType(bp, idx) {
  const raw = [bp?.page_type, bp?.page, bp?.type, bp?.template, bp?.slug, bp?.name, bp?.title]
    .filter(Boolean)
    .map(v => String(v).toLowerCase());

  const joined = raw.join(' ');
  const postType = String(bp?.post_type || '').toLowerCase();
  const slug = String(bp?.slug || '').toLowerCase();

  if (joined.includes('homepage') || slug === 'home' || slug === 'homepage' || idx === 0) return 'homepage';
  if (joined.includes('services_overview') || slug === 'services') return 'services_overview';
  if (joined.includes('service_area') || postType === 'service_area') return 'service_area';
  if (joined.includes('service') || postType === 'service') return 'service';
  if (joined.includes('about')) return 'about';
  if (joined.includes('reviews')) return 'reviews';
  if (joined.includes('projects') || postType === 'project') return 'projects';
  if (joined.includes('contact')) return 'contact';
  if (joined.includes('privacy')) return 'privacy_policy';
  if (joined.includes('terms')) return 'terms_of_service';
  if (joined.includes('thank')) return 'thank_you';
  if (joined.includes('sitemap')) return 'sitemap';
  if (joined.includes('blog') || postType === 'post') return 'post';

  return postType === 'page' ? 'about' : 'post';
}

const system_message = typeof payload.system_message === "string"
  ? payload.system_message
  : "";
const faq_strategy = payload.faq_strategy ?? {};
const cta_strategy = payload.cta_strategy ?? {};
const workflowData = $getWorkflowStaticData('global') || {};
const research_document = workflowData.research_document && typeof workflowData.research_document === 'object'
  ? workflowData.research_document
  : {};

function buildResearchContext(doc) {
  if (!doc || typeof doc !== 'object') return {};
  const allowed = [
    'brand_positioning',
    'conversion_strategy',
    'voice_guidelines',
    'seo_strategy',
    'faq_strategy',
    'content_expansion_guidelines'
  ];
  const out = {};
  for (const key of allowed) {
    if (doc[key]) out[key] = doc[key];
  }
  return out;
}

const research_context = buildResearchContext(research_document);

function buildProgressUrl(payload) {
  const callbackUrl = payload.callback_url || (payload.body ? payload.body.callback_url : "");
  if (!callbackUrl) return "";
  return callbackUrl.replace('/orchestrator', '/progress');
}

function sendProgress(payload, percent, step, message) {
  const progressUrl = buildProgressUrl(payload);
  const jobId = payload.job_id || (payload.body ? payload.body.job_id : null);
  if (!progressUrl || !jobId) {
    return Promise.resolve();
  }
  return this.helpers.httpRequest({
    method: 'POST',
    url: progressUrl,
    json: true,
    body: {
      job_id: jobId,
      status: 'running',
      percent,
      step: step || "",
      message: message || ""
    }
  }).then(() => null).catch(() => null);
}

const styleProfiles = [
  {
    id: "authority",
    label: "Authority",
    guidance: "Confident, expert, precise. Short openers, concrete claims, strong trust signals."
  },
  {
    id: "warm_local",
    label: "Warm Local",
    guidance: "Neighborly, reassuring, practical. Emphasize care for the home and clear next steps."
  },
  {
    id: "premium",
    label: "Premium",
    guidance: "Polished, premium, quality-first. Focus on craftsmanship, clean process, concierge experience."
  },
  {
    id: "direct",
    label: "Direct",
    guidance: "Straightforward, action-oriented. Fast reads, benefit-first, minimal fluff."
  }
];

function seedToInt(seed) {
  const str = String(seed || "");
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = ((hash << 5) - hash) + str.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash);
}

function pickStyleProfile(pageType, seedInt, idx) {
  const typeWeight = pageType ? pageType.length : 0;
  const index = (seedInt + (idx * 13) + typeWeight) % styleProfiles.length;
  return styleProfiles[index];
}

// Deterministic seed
function generateDeterministicSeed(input) {
  const base = `${input.business_name || ""}|${input.city_region || ""}|${input.niche || ""}`;
  let hash = 0;
  for (let i = 0; i < base.length; i++) {
    hash = ((hash << 5) - hash) + base.charCodeAt(i);
    hash |= 0;
  }
  return Math.abs(hash).toString();
}

const variation_seed =
  payload.variation_seed ||
  generateDeterministicSeed(payload);

const seedInt = seedToInt(variation_seed);

return sendProgress(payload, 40, 'Generating content', 'Content generation started').then(() => payload.blueprints.map((bp, idx) => {
  if (!bp || !Array.isArray(bp.sections)) {
    throw new Error(`Blueprint missing sections array (index ${idx})`);
  }

  const page_type = inferPageType(bp, idx);

  let primary_keyword = String(bp.primary_keyword || "").trim();
  if (!primary_keyword) {
    primary_keyword = String(payload.keywords?.primary || "").trim();
  }
  if (!primary_keyword) {
    primary_keyword = String(bp.page_title || bp.primary || "").trim();
  }
  let secondary_keywords = Array.isArray(bp.secondary_keywords)
    ? bp.secondary_keywords
    : [];
  if (!secondary_keywords.length) {
    secondary_keywords = Array.isArray(payload.keywords?.secondary) ? payload.keywords.secondary : [];
  }

  const style_profile = pickStyleProfile(page_type, seedInt, idx);

  return {
    json: {
      request_id: payload.request_id || null,
      variation_seed,
      business_name: payload.business_name || "",
      niche: payload.niche || "",
      city_region: payload.city_region || "",
      keywords: payload.keywords || {},
      primary_keyword,
      secondary_keywords,
      style_profile,
      writing_samples: payload.writing_samples || [],
      business_entity: payload.business_entity || {},
      global_faq_pool: payloadFaqPool.length
        ? payloadFaqPool
        : blueprintFaqPool,
      system_message,
      faq_strategy,
      cta_strategy,
      research_context,
      blueprint_index: idx,
      page_type,
      global_write_rules: {
        cta_global_write_allowed: page_type === "homepage",
        faq_global_write_allowed: page_type === "homepage"
      },
      blueprint: bp // PASS FULL OBJECT
    }
  };
}));
```

- [ ] **Step 3: Update "Merge Blueprint Results"**

Replace its `jsCode` with this full version (hard-fails for empty updates or missing homepage updates):

```js
// Merge all blueprint results into one final payload
const items = $input.all();
if (!items.length) {
  throw new Error('No items received to merge');
}

let mergedUpdates = [];
let requestId = null;
let warnings = [];
let hadFailure = false;
const pageTypeCounts = {};
const updateTargetCounts = {};
const blueprintMeta = {};

for (const item of items) {
  const json = item.json || {};
  if (json.request_id) requestId = json.request_id;
  if (json.ok === false) hadFailure = true;
  if (Array.isArray(json.quality_warnings)) warnings.push(...json.quality_warnings);
  if (Array.isArray(json.updates)) mergedUpdates = mergedUpdates.concat(json.updates);
  const pageType = String(json.page_type || '').trim();
  if (pageType) pageTypeCounts[pageType] = (pageTypeCounts[pageType] || 0) + 1;
  if (json.page_type || json.blueprint_index !== undefined) {
    const key = String(json.blueprint_index ?? pageTypeCounts[pageType] ?? '');
    if (key !== '') {
      blueprintMeta[key] = {
        page_type: json.page_type || null,
        blueprint_index: json.blueprint_index ?? null,
        post_id: json.meta?.post_id || json.blueprint?.post_id || null,
      };
    }
  }
}

for (const update of mergedUpdates) {
  const target = String(update?.target || 'unknown').trim() || 'unknown';
  updateTargetCounts[target] = (updateTargetCounts[target] || 0) + 1;
}

const hasHomepage = mergedUpdates.some(u => String(u?.target || '') === 'options' && String(u?.id || '') === 'homepage');

warnings = Array.from(new Set(warnings));

if (!mergedUpdates.length) {
  throw new Error('No updates produced');
}

if (!hasHomepage) {
  throw new Error('Homepage updates missing');
}

const webhook = $node["Webhook"].json || {};
const progressPayload = webhook.body ?? webhook;

function buildProgressUrl(payload) {
  const callbackUrl = payload.callback_url || (payload.body ? payload.body.callback_url : "");
  if (!callbackUrl) return "";
  return callbackUrl.replace('/orchestrator', '/progress');
}

function sendProgress(payload, percent, step, message, requestId) {
  const progressUrl = buildProgressUrl(payload);
  const jobId = payload.job_id || (payload.body ? payload.body.job_id : null);
  const runPhase = payload.run_phase || (payload.body ? payload.body.run_phase : '') || 'initial';
  if (!progressUrl || !jobId || !requestId) {
    return Promise.resolve();
  }
  return this.helpers.httpRequest({
    method: 'POST',
    url: progressUrl,
    json: true,
    body: {
      job_id: jobId,
      request_id: requestId,
      status: 'running',
      percent,
      step: step || '',
      message: message || '',
      run_phase: runPhase
    }
  }).then(() => null).catch(() => null);
}

return sendProgress(progressPayload, 85, 'Finalizing', 'Merging results', requestId).then(() => [{
  json: {
    ok: true,
    request_id: requestId,
    run_phase: progressPayload.run_phase || 'initial',
    updates: mergedUpdates,
    page_type_counts: pageTypeCounts,
    update_target_counts: updateTargetCounts,
    quality_warnings: warnings,
    blueprint_meta: blueprintMeta,
    had_failures: hadFailure
  }
}]);
```

- [ ] **Step 4: Enforce FAQ output when targets are present**

Update `Global Completeness + Blog Gate` `jsCode` to hard-fail if any blueprint with `faq_target_count` or `faq_target_range` produces zero `faq` updates:

```js
const faqTargets = Array.isArray(payload.blueprints)
  ? payload.blueprints.filter(bp => bp?.faq_target_count || bp?.faq_target_range)
  : [];
const faqUpdates = updates.filter(update => String(update?.target || '') === 'faq');
if (faqTargets.length && faqUpdates.length === 0) {
  throw new Error('FAQ targets present but no FAQ updates produced');
}
```

- [ ] **Step 5: Run test to verify it fails as expected**

Run the workflow with an empty or malformed payload. Expected: n8n execution fails with the thrown error.

- [ ] **Step 6: Commit**

```bash
git add docs/n8n-workflow.json
git commit -m "fix: hard-fail n8n when updates are empty"
```

---

### Task 4: Remove Guided Tour Feature

**Files:**
- Delete: `inc/tour-mode.php`
- Delete: `assets/js/tour-mode.js`
- Delete: `assets/css/tour-mode.css`
- Modify: `functions.php`
- Modify: `inc/ops/menu.php`

- [ ] **Step 1: Write the failing test**

Confirm the Guided Tour toggle still appears in Global Settings (baseline).

- [ ] **Step 2: Remove Guided Tour logic and assets**

Remove the include in `functions.php`:

```php
// lf_load_inc('tour-mode.php');
```

Remove the option from `inc/ops/menu.php`:

```php
// update_option('lf_tour_mode_admin', isset($_POST['lf_tour_mode_admin']) ? '1' : '0');
```

Remove the UI row (tour checkbox) from Global Settings HTML.

Delete `inc/tour-mode.php`, `assets/js/tour-mode.js`, `assets/css/tour-mode.css`.

- [ ] **Step 3: Run test to verify it passes**

Open Global Settings; confirm the tour toggle is gone. Confirm no tour assets are loaded in admin or frontend.

- [ ] **Step 4: Commit**

```bash
git add functions.php inc/ops/menu.php
git rm inc/tour-mode.php assets/js/tour-mode.js assets/css/tour-mode.css
git commit -m "chore: remove guided tour feature"
```

---

### Task 5: Add Logged-In Docs Page (Virtual Route)

**Files:**
- Create: `inc/docs-page.php`
- Create: `templates/lf-docs.php`
- Create: `assets/css/docs-page.css`
- Modify: `functions.php`

- [ ] **Step 1: Choose docs slug**

Confirm slug (default `theme-docs`). Use this slug consistently in rewrite rules and links.

- [ ] **Step 2: Write the failing test**

Try visiting `/<slug>`. Expect 404.

- [ ] **Step 3: Implement docs route + access control**

Create `inc/docs-page.php`:

```php
<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_DOCS_SLUG = 'theme-docs';

add_action('init', 'lf_docs_register_route');
add_filter('query_vars', 'lf_docs_register_query_var');
add_action('template_redirect', 'lf_docs_template_loader');
add_action('after_switch_theme', 'lf_docs_flush_rewrite');
add_filter('wp_robots', 'lf_docs_add_noindex', 10, 2);
add_action('wp_enqueue_scripts', 'lf_docs_enqueue_assets');

function lf_docs_register_route(): void {
	add_rewrite_rule('^' . LF_DOCS_SLUG . '/?$', 'index.php?lf_docs=1', 'top');
}

function lf_docs_register_query_var(array $vars): array {
	$vars[] = 'lf_docs';
	return $vars;
}

function lf_docs_flush_rewrite(): void {
	lf_docs_register_route();
	flush_rewrite_rules();
}

function lf_docs_is_request(): bool {
	return (int) get_query_var('lf_docs') === 1;
}

function lf_docs_template_loader(): void {
	if (!lf_docs_is_request()) {
		return;
	}
	if (!is_user_logged_in()) {
		$login_url = wp_login_url(home_url('/' . LF_DOCS_SLUG . '/'));
		wp_safe_redirect($login_url);
		exit;
	}
	$template = LF_THEME_DIR . '/templates/lf-docs.php';
	if (!is_readable($template)) {
		status_header(404);
		exit;
	}
	include $template;
	exit;
}

function lf_docs_add_noindex(array $robots, $context): array {
	if (!lf_docs_is_request()) {
		return $robots;
	}
	$robots['noindex'] = true;
	$robots['nofollow'] = true;
	return $robots;
}

function lf_docs_enqueue_assets(): void {
	if (!lf_docs_is_request()) {
		return;
	}
	wp_enqueue_style('lf-docs', LF_THEME_URI . '/assets/css/docs-page.css', [], LF_THEME_VERSION);
}
```

Load in `functions.php`:

```php
lf_load_inc('docs-page.php');
```

- [ ] **Step 4: Add docs template and CSS**

Create `templates/lf-docs.php`:

```php
<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

get_header();
?>
<main id="main" class="site-main site-main--docs" role="main">
	<section class="lf-docs">
		<aside class="lf-docs__sidebar" aria-label="Documentation navigation">
			<h2 class="lf-docs__title"><?php esc_html_e('Theme Documentation', 'leadsforward-core'); ?></h2>
			<nav class="lf-docs__nav">
				<a href="#getting-started">Getting Started</a>
				<a href="#global-settings">Global Settings</a>
				<a href="#homepage-builder">Homepage Builder</a>
				<a href="#page-builder">Page Builder</a>
				<a href="#services">Services</a>
				<a href="#service-areas">Service Areas</a>
				<a href="#projects">Projects</a>
				<a href="#reviews">Reviews</a>
				<a href="#faqs">FAQs</a>
				<a href="#seo">SEO</a>
				<a href="#ai-studio">AI Studio</a>
				<a href="#manifester">Manifester</a>
				<a href="#troubleshooting">Troubleshooting</a>
			</nav>
		</aside>
		<div class="lf-docs__content">
			<section id="getting-started" class="lf-docs__section">
				<h1><?php esc_html_e('Getting Started', 'leadsforward-core'); ?></h1>
				<p><?php esc_html_e('Use this page as the single source of truth for operating the LeadsForward theme. Each section links to the exact UI area you need.', 'leadsforward-core'); ?></p>
			</section>
			<section id="global-settings" class="lf-docs__section">
				<h2><?php esc_html_e('Global Settings', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Configure business identity, branding, and AI settings from LeadsForward -> Global Settings.', 'leadsforward-core'); ?></p>
			</section>
			<section id="homepage-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Homepage Builder', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Reorder, enable, and edit homepage sections. These settings drive AI homepage generation.', 'leadsforward-core'); ?></p>
			</section>
			<section id="page-builder" class="lf-docs__section">
				<h2><?php esc_html_e('Page Builder', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Each service, service area, and core page uses structured sections from the Page Builder meta box.', 'leadsforward-core'); ?></p>
			</section>
			<section id="services" class="lf-docs__section">
				<h2><?php esc_html_e('Services', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Services are managed as custom post types and generated via the AI Studio workflow.', 'leadsforward-core'); ?></p>
			</section>
			<section id="service-areas" class="lf-docs__section">
				<h2><?php esc_html_e('Service Areas', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Service Areas are managed as custom post types and support FAQs, maps, and nearby areas.', 'leadsforward-core'); ?></p>
			</section>
			<section id="projects" class="lf-docs__section">
				<h2><?php esc_html_e('Projects', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Projects appear in gallery sections and project archives. Keep before/after images and descriptions up to date.', 'leadsforward-core'); ?></p>
			</section>
			<section id="reviews" class="lf-docs__section">
				<h2><?php esc_html_e('Reviews', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Reviews are imported from Airtable and used across trust sections and schema.', 'leadsforward-core'); ?></p>
			</section>
			<section id="faqs" class="lf-docs__section">
				<h2><?php esc_html_e('FAQs', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('FAQs are generated and linked to homepage and service pages. Keep questions unique.', 'leadsforward-core'); ?></p>
			</section>
			<section id="seo" class="lf-docs__section">
				<h2><?php esc_html_e('SEO', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Configure SEO defaults, sitemap, and schema settings from LeadsForward -> SEO.', 'leadsforward-core'); ?></p>
			</section>
			<section id="ai-studio" class="lf-docs__section">
				<h2><?php esc_html_e('AI Studio', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('AI Studio orchestrates generation from the manifest and Airtable data.', 'leadsforward-core'); ?></p>
			</section>
			<section id="manifester" class="lf-docs__section">
				<h2><?php esc_html_e('Manifester', 'leadsforward-core'); ?></h2>
				<p><?php esc_html_e('Use the Website Manifester to trigger a full generation run. Confirm scope settings first.', 'leadsforward-core'); ?></p>
			</section>
			<section id="troubleshooting" class="lf-docs__section">
				<h2><?php esc_html_e('Troubleshooting', 'leadsforward-core'); ?></h2>
				<ul>
					<li><?php esc_html_e('If content is missing, run the content audit and wiring check.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Check AI Studio logs for duplicate or placeholder content warnings.', 'leadsforward-core'); ?></li>
					<li><?php esc_html_e('Verify the n8n workflow update was imported successfully.', 'leadsforward-core'); ?></li>
				</ul>
			</section>
		</div>
	</section>
</main>
<?php
get_footer();
```

Create `assets/css/docs-page.css`:

```css
.site-main--docs {
	padding: 2rem 0 4rem;
}

.lf-docs {
	display: grid;
	grid-template-columns: 260px minmax(0, 1fr);
	gap: 2rem;
	max-width: 1200px;
	margin: 0 auto;
	padding: 0 1.5rem;
}

.lf-docs__sidebar {
	position: sticky;
	top: 2rem;
	align-self: start;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 1.25rem;
	background: #fff;
}

.lf-docs__title {
	margin: 0 0 1rem;
	font-size: 1.1rem;
}

.lf-docs__nav {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.lf-docs__nav a {
	color: #0f172a;
	text-decoration: none;
	padding: 0.35rem 0.5rem;
	border-radius: 6px;
	background: #f8fafc;
}

.lf-docs__nav a:hover {
	background: #e2e8f0;
}

.lf-docs__content {
	display: flex;
	flex-direction: column;
	gap: 2rem;
}

.lf-docs__section {
	background: #fff;
	border: 1px solid #e2e8f0;
	border-radius: 12px;
	padding: 1.5rem;
}

.lf-docs__section h1,
.lf-docs__section h2 {
	margin-top: 0;
}
```

- [ ] **Step 5: Run test to verify it passes**

Visit `/theme-docs` while logged in. Confirm sidebar navigation works. Visit while logged out and confirm redirect to login.

- [ ] **Step 6: Commit**

```bash
git add inc/docs-page.php templates/lf-docs.php assets/css/docs-page.css functions.php
git commit -m "feat: add logged-in docs page"
```

---

### Task 6: Verification Pass + Handoff

**Files:**
- None

- [ ] **Step 1: Run Manifester**

Trigger a full Manifester run. Confirm updates apply to:
- Homepage
- Services and service areas
- Core pages (About, Contact, Reviews, Blog, Sitemap, Privacy, Terms, Thank You)
- Blog posts
- Projects
- FAQs

- [ ] **Step 2: Verify n8n quality warnings surface**

Confirm `quality_warnings` from the callback appear in AI Studio audit UI or logs.

- [ ] **Step 3: Validate templates render allowed fields**

Spot-check `templates/blocks/*` to confirm list fields use newline splitting and all allowed fields are rendered for their section types.

- [ ] **Step 4: Validate docs page + noindex**

Open `/theme-docs` logged in. Confirm noindex and nofollow via page source or SEO tooling.

- [ ] **Step 5: Validate Guided Tour removal**

Confirm no Guided Tour toggle exists and no tour assets load.

- [ ] **Step 6: Commit final fixes (if any)**

```bash
git add .
git commit -m "fix: finalize foundation wiring pass"
```

- [ ] **Step 7: Replace n8n workflow and re-run test**

Replace the workflow in n8n with `docs/n8n-workflow.json`, then run a full test and confirm non-empty updates.

