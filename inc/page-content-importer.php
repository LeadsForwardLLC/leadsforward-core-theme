<?php
/**
 * Paste / doc import → Page Builder template population.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

require_once LF_THEME_DIR . '/inc/page-content-importer-schemas.php';

/**
 * About Us page template schema (section order + field keys).
 *
 * @return array<string, mixed>
 */
function lf_pci_about_us_schema(): array {
	return lf_pci_schema_for_slug('about-us') ?? lf_pci_build_schema(
		'about-us',
		__('About Us', 'leadsforward-core'),
		['hero', 'content_image', 'benefits', 'image_content', 'process', 'faq_accordion', 'cta']
	);
}

/**
 * @return array<string, mixed>|null
 */
function lf_pci_schema_for_slug(string $slug): ?array {
	$slug = sanitize_title($slug);
	if ($slug === '') {
		return null;
	}
	$registry = lf_pci_registry();
	if (isset($registry[$slug])) {
		return $registry[$slug];
	}
	$aliases = function_exists('lf_pci_registry_slug_aliases') ? lf_pci_registry_slug_aliases() : [];
	if (isset($aliases[$slug], $registry[$aliases[$slug]])) {
		return $registry[$aliases[$slug]];
	}
	return null;
}

function lf_pci_get_page_id_for_slug(string $slug): int {
	$slug = sanitize_title($slug);
	if ($slug === '') {
		return 0;
	}
	$page = get_page_by_path($slug, OBJECT, 'page');
	return $page instanceof \WP_Post ? (int) $page->ID : 0;
}

/**
 * Resolve a CPT or page post ID from post type + slug.
 */
function lf_pci_get_post_id_for_target(string $post_type, string $slug): int {
	$post_type = sanitize_key($post_type);
	$slug = sanitize_title($slug);
	if ($post_type === '' || $slug === '') {
		return 0;
	}
	if ($post_type === 'page') {
		return lf_pci_get_page_id_for_slug($slug);
	}
	$posts = get_posts([
		'post_type' => $post_type,
		'name' => $slug,
		'post_status' => 'any',
		'posts_per_page' => 1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	]);
	return isset($posts[0]) ? (int) $posts[0] : 0;
}

/**
 * Map schema order key (e.g. service_details__2) to Page Builder instance id.
 */
function lf_pci_pb_instance_for_order_key(string $order_key): string {
	if (preg_match('/^(.+)__(\d+)$/', $order_key, $m) === 1) {
		return lf_pb_instance_id((string) $m[1], max(1, (int) $m[2]));
	}
	return lf_pb_instance_id($order_key, 1);
}

/**
 * Read PAGE target from doc header (=== PAGE === block or top "Page:" line).
 *
 * @return array{slug: string, template: string, label: string, content: string}
 */
function lf_pci_extract_page_header(string $raw): array {
	$slug = '';
	$template = '';
	$label = '';
	$body = $raw;

	if (preg_match('/^={3,}\s*PAGE\s*={3,}\s*\n([\s\S]*?)(?=^={3,}|\z)/mi', $raw, $m)) {
		$f = lf_pci_parse_fields(trim($m[1]), ['notes']);
		$template = sanitize_title((string) ($f['template'] ?? $f['type'] ?? ''));
		$slug = sanitize_title((string) ($f['slug'] ?? $f['page'] ?? $f['page_slug'] ?? ''));
		if ($slug === '') {
			$slug = sanitize_title((string) ($f['name'] ?? $f['label'] ?? $f['title'] ?? ''));
		}
		$label = trim((string) ($f['name'] ?? $f['label'] ?? $f['title'] ?? ''));
		$body = preg_replace('/^={3,}\s*PAGE\s*={3,}\s*\n[\s\S]*?(?=^={3,})/mi', '', $raw, 1);
		if (!is_string($body)) {
			$body = $raw;
		}
	} elseif (preg_match('/^Page:\s*(.+)$/mi', $raw, $m)) {
		$slug = sanitize_title(trim($m[1]));
		$body = preg_replace('/^Page:\s*.+$\n?/mi', '', $raw, 1);
		if (!is_string($body)) {
			$body = $raw;
		}
	}

	return [
		'slug' => $slug,
		'template' => $template,
		'label' => $label,
		'content' => trim($body),
	];
}

/**
 * Normalize upload basename to a slug (strips -filled, -content-template, etc.).
 */
function lf_pci_normalize_upload_basename(string $filename): string {
	$base = pathinfo(sanitize_file_name($filename), PATHINFO_FILENAME);
	$base = preg_replace('/-(filled|content-template)$/i', '', (string) $base) ?? (string) $base;
	$base = preg_replace('/-content-template$/i', '', $base) ?? $base;

	return sanitize_title($base);
}

/**
 * Loose Slug:/Template: lines GPT sometimes leaves in the body.
 *
 * @return array{template: string, slug: string}
 */
function lf_pci_extract_loose_target_fields(string $raw): array {
	$template = '';
	$slug = '';
	if (preg_match('/\bTemplate:\s*([^\n\r]+)/i', $raw, $m)) {
		$template = sanitize_title(trim($m[1]));
	}
	if (preg_match('/\bSlug:\s*([^\n\r]+)/i', $raw, $m)) {
		$slug = sanitize_title(trim($m[1]));
	}

	return ['template' => $template, 'slug' => $slug];
}

/**
 * When a generic service/area template filename is used, pick slug if exactly one CPT exists.
 */
function lf_pci_infer_single_cpt_slug(string $post_type): string {
	$post_type = sanitize_key($post_type);
	if ($post_type === '') {
		return '';
	}
	$posts = get_posts([
		'post_type' => $post_type,
		'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
		'posts_per_page' => 2,
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	if (count($posts) !== 1) {
		return '';
	}
	$post = get_post((int) $posts[0]);

	return $post instanceof \WP_Post ? sanitize_title($post->post_name) : '';
}

/**
 * Infer import target from a writer filename (e.g. about-us-filled.docx).
 *
 * @return array{template: string, slug: string, label: string}
 */
function lf_pci_infer_target_from_filename(string $filename): array {
	$slug = lf_pci_normalize_upload_basename($filename);
	if ($slug === '') {
		return ['template' => '', 'slug' => '', 'label' => ''];
	}

	if (lf_pci_schema_for_slug($slug) !== null) {
		return ['template' => $slug, 'slug' => $slug, 'label' => ''];
	}

	$aliases = function_exists('lf_pci_registry_slug_aliases') ? lf_pci_registry_slug_aliases() : [];
	if (isset($aliases[$slug])) {
		$key = (string) $aliases[$slug];

		return ['template' => $key, 'slug' => $key, 'label' => ''];
	}

	if (function_exists('lf_fleet_canonical_page_slug')) {
		$canonical = lf_fleet_canonical_page_slug($slug);
		if ($canonical !== $slug && lf_pci_schema_for_slug($canonical) !== null) {
			return ['template' => $canonical, 'slug' => $canonical, 'label' => ''];
		}
	}

	if ($slug === 'our-services') {
		return ['template' => 'services', 'slug' => 'services', 'label' => ''];
	}

	if (in_array($slug, ['service', 'service-page'], true)) {
		$single = lf_pci_infer_single_cpt_slug('lf_service');

		return ['template' => 'service', 'slug' => $single, 'label' => ''];
	}
	if (in_array($slug, ['service-area', 'service-area-page', 'service-areas-page'], true)) {
		$single = lf_pci_infer_single_cpt_slug('lf_service_area');

		return ['template' => 'service-area', 'slug' => $single, 'label' => ''];
	}

	foreach (['lf_service' => 'service', 'lf_service_area' => 'service-area'] as $post_type => $template) {
		$posts = get_posts([
			'post_type' => $post_type,
			'name' => $slug,
			'post_status' => 'any',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'no_found_rows' => true,
		]);
		if (!empty($posts[0])) {
			return ['template' => $template, 'slug' => $slug, 'label' => ''];
		}
	}

	$page = get_page_by_path($slug, OBJECT, 'page');
	if ($page instanceof \WP_Post) {
		$key = lf_pci_post_template_key($page);

		return [
			'template' => $key !== '' ? $key : sanitize_title($page->post_name),
			'slug' => sanitize_title($page->post_name),
			'label' => (string) $page->post_title,
		];
	}

	return ['template' => '', 'slug' => '', 'label' => ''];
}

/**
 * Remove WRITER NOTES brief (with or without a following PAGE block).
 */
function lf_pci_strip_writer_notes(string $raw): string {
	if (!preg_match('/=== WRITER NOTES ===/i', $raw)) {
		return $raw;
	}
	$stripped = preg_replace('/=== WRITER NOTES ===[\s\S]*?(?==== PAGE ===)/i', '', $raw, 1);
	if (is_string($stripped) && $stripped !== $raw) {
		return $stripped;
	}
	$stripped = preg_replace('/=== WRITER NOTES ===[\s\S]*?(?==== (?!WRITER NOTES)[^\n=]+===)/i', '', $raw, 1);

	return is_string($stripped) ? $stripped : $raw;
}

/**
 * Put GPT/Docs-inline section headers on their own lines for the splitter.
 */
function lf_pci_normalize_section_headers(string $raw): string {
	$raw = preg_replace('/\s*(=== [^=\n]+ ===)\s*/', "\n$1\n", $raw) ?? $raw;
	$raw = preg_replace('/(=== [^=\n]+ ===)\n*([A-Za-z][A-Za-z0-9 _-]*:)/', "$1\n$2", $raw) ?? $raw;
	$raw = preg_replace("/\n{3,}/", "\n\n", $raw) ?? $raw;

	return trim($raw);
}

/**
 * Whether this post supports paste import.
 */
function lf_pci_post_supports_import(\WP_Post $post): bool {
	if ($post->post_type === 'lf_service') {
		return lf_pci_schema_for_slug('service') !== null;
	}
	if ($post->post_type === 'lf_service_area') {
		return lf_pci_schema_for_slug('service-area') !== null;
	}
	if ($post->post_type !== 'page') {
		return false;
	}
	if (lf_pci_schema_for_slug($post->post_name) !== null) {
		return true;
	}
	return function_exists('lf_pb_is_basic_page') && lf_pb_is_basic_page($post);
}

/** @deprecated Use lf_pci_post_supports_import() */
function lf_pci_page_supports_import(\WP_Post $post): bool {
	return lf_pci_post_supports_import($post);
}

/**
 * Import template registry key for a post being edited.
 */
function lf_pci_post_template_key(\WP_Post $post): string {
	if ($post->post_type === 'lf_service') {
		return 'service';
	}
	if ($post->post_type === 'lf_service_area') {
		return 'service-area';
	}
	if ($post->post_type !== 'page') {
		return '';
	}
	$schema = lf_pci_schema_for_slug($post->post_name);
	return $schema !== null ? (string) $schema['slug'] : sanitize_title($post->post_name);
}

/**
 * Best page slug for import on this editor screen.
 */
function lf_pci_page_slug_for_post(\WP_Post $post): string {
	if ($post->post_type !== 'page') {
		return '';
	}
	$schema = lf_pci_schema_for_slug($post->post_name);
	return $schema !== null ? (string) $schema['slug'] : sanitize_title($post->post_name);
}

/**
 * @return array<string, string>
 */
function lf_pci_template_vars(): array {
	$data = function_exists('lf_wizard_data_from_entity') ? lf_wizard_data_from_entity() : [];
	if ($data === []) {
		$data = [
			'business_name' => get_bloginfo('name'),
			'homepage_city' => (string) get_option('lf_homepage_city', ''),
			'business_phone' => (string) get_option('lf_business_phone', ''),
			'business_email' => (string) get_option('lf_business_email', ''),
			'business_address' => (string) get_option('lf_business_address', ''),
		];
	}
	$vars = function_exists('lf_wizard_template_vars') ? lf_wizard_template_vars($data) : [];
	return function_exists('lf_niche_content_library_fill_vars')
		? lf_niche_content_library_fill_vars($vars)
		: $vars;
}

function lf_pci_fill_tokens(string $text, ?array $vars = null): string {
	$vars = $vars ?? lf_pci_template_vars();
	if (function_exists('lf_niche_content_library_fill_string')) {
		return lf_niche_content_library_fill_string($text, $vars);
	}
	foreach ($vars as $key => $val) {
		if (is_scalar($val)) {
			$text = str_replace('{' . $key . '}', (string) $val, $text);
		}
	}
	return $text;
}

/**
 * @return list<string>
 */
function lf_pci_parse_secondary_keywords(string $raw): array {
	if ($raw === '') {
		return [];
	}
	$parts = preg_split('/\r\n|\r|\n|,/', $raw) ?: [];
	return array_values(array_unique(array_filter(array_map('trim', $parts))));
}

/**
 * Keyword context for writer templates (post meta, manifest, or keyword map).
 *
 * @return array{primary_keyword: string, secondary_keywords: string, serp_intent: string}
 */
function lf_pci_writer_keyword_context(?int $post_id = null, string $slug = ''): array {
	$primary = '';
	$secondary = [];
	$intent = 'transactional';
	$slug = sanitize_title($slug);

	if ($post_id > 0) {
		$post = get_post($post_id);
		if ($post instanceof \WP_Post) {
			if ($slug === '') {
				$slug = sanitize_title($post->post_name);
			}
			$primary = trim((string) get_post_meta($post_id, '_lf_seo_primary_keyword', true));
			$secondary = lf_pci_parse_secondary_keywords((string) get_post_meta($post_id, '_lf_seo_secondary_keywords', true));
			$stored_intent = sanitize_key((string) get_post_meta($post_id, '_lf_seo_serp_intent', true));
			if ($stored_intent !== '') {
				$intent = $stored_intent;
			}
		}
	}

	if ($primary === '' && $slug === 'home') {
		$manifest = get_option('lf_site_manifest', []);
		if (is_array($manifest)) {
			$primary = trim((string) ($manifest['homepage']['primary_keyword'] ?? ''));
			$manifest_secondary = $manifest['homepage']['secondary_keywords'] ?? [];
			if (is_string($manifest_secondary)) {
				$manifest_secondary = lf_pci_parse_secondary_keywords($manifest_secondary);
			}
			if (is_array($manifest_secondary) && $secondary === []) {
				$secondary = array_values(array_unique(array_filter(array_map('sanitize_text_field', $manifest_secondary))));
			}
		}
		if ($primary === '' && function_exists('lf_seo_get_keyword_map')) {
			$map = lf_seo_get_keyword_map();
			$primary = trim((string) ($map['primary']['homepage'] ?? ''));
		}
	}

	if ($primary === '' && $slug !== '' && $post_id <= 0) {
		$resolved_id = lf_pci_resolve_post_id_for_template($slug, 0);
		if ($resolved_id > 0) {
			return lf_pci_writer_keyword_context($resolved_id, $slug);
		}
	}

	if ($primary !== '' && function_exists('lf_seo_detect_serp_intent') && $post_id > 0) {
		$detected = lf_seo_detect_serp_intent($post_id, $primary);
		if ($detected !== '') {
			$intent = $detected;
		}
	}

	return [
		'primary_keyword' => $primary,
		'secondary_keywords' => $secondary !== [] ? implode(', ', $secondary) : '',
		'serp_intent' => $intent,
	];
}

/**
 * Resolve a post ID for keyword-aware fleet page templates.
 */
function lf_pci_resolve_post_id_for_template(string $template_slug, int $post_id = 0): int {
	if ($post_id > 0) {
		return $post_id;
	}
	$template_slug = sanitize_title($template_slug);
	$schema = lf_pci_schema_for_slug($template_slug);
	if ($schema === null) {
		return 0;
	}
	$post_type = (string) ($schema['post_type'] ?? 'page');
	if ($template_slug === 'home') {
		$front = (int) get_option('page_on_front', 0);
		if ($front > 0) {
			return $front;
		}
	}
	if ($post_type === 'page') {
		$page = get_page_by_path($template_slug);
		return $page instanceof \WP_Post ? (int) $page->ID : 0;
	}
	return 0;
}

/**
 * Replace generic PAGE header slug/name with the target post.
 */
function lf_pci_personalize_template_page_header(string $body, int $post_id): string {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return $body;
	}
	$slug = sanitize_title($post->post_name);
	$name = (string) $post->post_title;
	if ($slug !== '') {
		$body = preg_replace('/^Slug:\s*.+$/m', 'Slug: ' . $slug, $body, 1) ?? $body;
	}
	if ($name !== '') {
		$body = preg_replace('/^Name:\s*.+$/m', 'Name: ' . $name, $body, 1) ?? $body;
	}
	return $body;
}

/**
 * Normalize pasted doc text (Google Docs, markdown headings, etc.).
 */
function lf_pci_normalize_raw(string $raw): string {
	$raw = str_replace(["\r\n", "\r"], "\n", $raw);
	$raw = preg_replace('/\x{00A0}/u', ' ', $raw) ?? $raw;
	$raw = strtr($raw, [
		"\u{2018}" => "'",
		"\u{2019}" => "'",
		"\u{201C}" => '"',
		"\u{201D}" => '"',
		"\u{2013}" => '-',
		"\u{2014}" => '-',
		"\u{2026}" => '...',
		"\u{200B}" => '',
		"\u{FEFF}" => '',
	]);
	// Google Docs / Word sometimes space out === markers.
	$raw = preg_replace('/=\s{1,3}=\s{1,3}=/', '===', $raw) ?? $raw;
	// Markdown ## Section → === SECTION ===
	$raw = preg_replace_callback('/^#{1,3}\s+(.+?)\s*$/m', static function (array $m): string {
		return '=== ' . strtoupper(trim($m[1])) . ' ===';
	}, $raw) ?? $raw;

	return lf_pci_normalize_section_headers(trim($raw));
}

/**
 * @return array<string, string> section_key => body text
 */
function lf_pci_split_sections(string $raw, array $aliases): array {
	$pattern = '/^={3,}\s*(.+?)\s*={3,}\s*$/mi';
	if (!preg_match_all($pattern, $raw, $matches, PREG_OFFSET_CAPTURE)) {
		return [];
	}
	$sections = [];
	$count = count($matches[0]);
	for ($i = 0; $i < $count; $i++) {
		$label = trim((string) ($matches[1][$i][0] ?? ''));
		$start = (int) ($matches[0][$i][1] ?? 0) + strlen((string) ($matches[0][$i][0] ?? ''));
		$end = ($i + 1 < $count) ? (int) ($matches[0][$i + 1][1] ?? strlen($raw)) : strlen($raw);
		$body = trim(substr($raw, $start, max(0, $end - $start)));
		$key = strtolower($label);
		$mapped = $aliases[$key] ?? $aliases[str_replace(['_', '-'], ' ', $key)] ?? '';
		if ($mapped === '') {
			$mapped = $aliases[sanitize_title($label)] ?? '';
		}
		if ($mapped !== '') {
			$sections[$mapped] = $body;
		}
	}
	return $sections;
}

/**
 * Parse labeled fields (Key: value) with multiline support.
 *
 * @return array<string, string>
 */
function lf_pci_parse_fields(string $block, array $multiline_keys = []): array {
	$lines = explode("\n", $block);
	$fields = [];
	$current_key = '';
	$buffer = [];
	$flush = static function () use (&$fields, &$current_key, &$buffer): void {
		if ($current_key === '') {
			return;
		}
		$fields[$current_key] = trim(implode("\n", $buffer));
		$buffer = [];
	};
	foreach ($lines as $line) {
		if (preg_match('/^([A-Za-z][A-Za-z0-9 _\/-]{0,40}):\s*(.*)$/', $line, $m)) {
			$flush();
			$current_key = strtolower(str_replace([' ', '-'], '_', trim($m[1])));
			$inline = (string) ($m[2] ?? '');
			if ($inline !== '' || !in_array($current_key, $multiline_keys, true)) {
				$buffer = [$inline];
				if (!in_array($current_key, $multiline_keys, true)) {
					$flush();
					$current_key = '';
				}
			} else {
				$buffer = [];
			}
			continue;
		}
		if ($current_key !== '') {
			$buffer[] = $line;
		}
	}
	$flush();
	return $fields;
}

/**
 * @return list<string>
 */
/**
 * Parse benefits item lines (title || body), including bullet prefixes.
 *
 * @return list<string>
 */
function lf_pci_parse_benefits_item_lines(string $raw): array {
	$raw = trim($raw);
	if ($raw === '') {
		return [];
	}

	$lines = [];
	foreach (explode("\n", $raw) as $line) {
		$line = trim($line);
		if ($line === '') {
			continue;
		}
		$line = preg_replace('/^[-*•]\s+/', '', $line) ?? $line;
		$line = trim($line);
		if ($line !== '') {
			$lines[] = $line;
		}
	}

	// Repair a single run-on line that merged multiple cards (common after AI paste).
	if (count($lines) === 1 && substr_count($lines[0], '||') >= 2) {
		$chunks = preg_split('/\.\s+(?=[A-Z])/', $lines[0]) ?: [];
		$rebuilt = [];
		foreach ($chunks as $chunk) {
			$chunk = trim($chunk);
			if ($chunk === '') {
				continue;
			}
			if (strpos($chunk, '||') === false && $rebuilt !== []) {
				$rebuilt[count($rebuilt) - 1] .= '. ' . $chunk;
				continue;
			}
			$rebuilt[] = $chunk;
		}
		if (count($rebuilt) >= 2) {
			$lines = $rebuilt;
		}
	}

	return $lines;
}

/**
 * Ensure imported benefits always render as three cards in a three-column grid.
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function lf_pci_normalize_benefits_settings(array $settings): array {
	$min_cards = 3;
	$lines = lf_pci_parse_benefits_item_lines((string) ($settings['benefits_items'] ?? ''));

	$defaults = function_exists('lf_sections_defaults_for')
		? lf_sections_defaults_for('benefits')
		: [];
	$default_lines = lf_pci_parse_benefits_item_lines((string) ($defaults['benefits_items'] ?? ''));

	foreach ($default_lines as $default_line) {
		if (count($lines) >= $min_cards) {
			break;
		}
		if ($default_line === '') {
			continue;
		}
		$lines[] = $default_line;
	}

	$lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
	$settings['benefits_items'] = implode("\n", array_slice($lines, 0, max($min_cards, count($lines))));
	$settings['benefits_grid_columns'] = '3';

	return $settings;
}

function lf_pci_parse_bullet_list(string $text): array {
	$items = [];
	foreach (explode("\n", $text) as $line) {
		$line = trim($line);
		if ($line === '') {
			continue;
		}
		if (preg_match('/^[-*•]\s+(.+)$/', $line, $m)) {
			$items[] = trim($m[1]);
		} elseif ($items !== [] && !preg_match('/^[A-Za-z][A-Za-z0-9 _\/-]{0,40}:/', $line)) {
			$items[count($items) - 1] .= ' ' . $line;
		}
	}
	return $items;
}

/**
 * @return list<array{title:string,body:string}>
 */
function lf_pci_parse_process_steps(string $block): array {
	$steps = [];
	$line_steps = [];
	foreach (explode("\n", trim($block)) as $line) {
		$line = trim($line);
		if ($line === '' || !preg_match('/^Step:\s*(.+)$/i', $line, $m)) {
			continue;
		}
		$rest = trim((string) ($m[1] ?? ''));
		if ($rest === '') {
			continue;
		}
		if (strpos($rest, '||') !== false) {
			[$title, $body] = array_pad(explode('||', $rest, 2), 2, '');
			$line_steps[] = ['title' => trim($title), 'body' => trim($body)];
			continue;
		}
		$line_steps[] = ['title' => $rest, 'body' => ''];
	}
	if ($line_steps !== []) {
		return $line_steps;
	}

	$chunks = preg_split('/\n\s*\n/', trim($block)) ?: [];
	foreach ($chunks as $chunk) {
		$chunk = trim($chunk);
		if ($chunk === '') {
			continue;
		}
		if (strpos($chunk, '||') !== false && !preg_match('/^Step:/mi', $chunk)) {
			foreach (explode("\n", $chunk) as $line) {
				$line = trim($line);
				if ($line === '' || strpos($line, '||') === false) {
					continue;
				}
				[$title, $body] = array_pad(explode('||', $line, 2), 2, '');
				$title = trim($title);
				if ($title !== '') {
					$steps[] = ['title' => $title, 'body' => trim($body)];
				}
			}
			continue;
		}
		$fields = lf_pci_parse_fields($chunk, ['body', 'description']);
		$title = trim((string) ($fields['step'] ?? $fields['title'] ?? $fields['name'] ?? ''));
		$body = trim((string) ($fields['body'] ?? $fields['description'] ?? ''));
		if ($title === '' && preg_match('/^Step:\s*(.+)$/mi', $chunk, $m)) {
			$title = trim($m[1]);
		}
		if ($title !== '') {
			$steps[] = ['title' => $title, 'body' => $body];
		}
	}
	return $steps;
}

/**
 * @return list<array{question:string,answer:string}>
 */
function lf_pci_parse_faqs(string $block): array {
	$faqs = [];
	$block = trim($block);
	if ($block === '') {
		return [];
	}
	$pattern = '/(?:^|\n)\s*(?:Q|Question)\s*:\s*(.+?)(?=\n\s*(?:Q|Question)\s*:|\z)/is';
	if (preg_match_all($pattern, $block, $matches)) {
		foreach ($matches[0] as $chunk) {
			if (!preg_match('/(?:Q|Question)\s*:\s*(.+?)(?:\n\s*(?:A|Answer)\s*:\s*([\s\S]*))?$/is', trim($chunk), $m)) {
				continue;
			}
			$question = trim((string) ($m[1] ?? ''));
			$answer = trim((string) ($m[2] ?? ''));
			if ($question !== '') {
				$faqs[] = ['question' => $question, 'answer' => $answer];
			}
		}
	}
	if ($faqs === []) {
		$chunks = preg_split('/\n\s*\n/', $block) ?: [];
		foreach ($chunks as $chunk) {
			$fields = lf_pci_parse_fields(trim($chunk), ['answer']);
			$question = trim((string) ($fields['q'] ?? $fields['question'] ?? ''));
			$answer = trim((string) ($fields['a'] ?? $fields['answer'] ?? ''));
			if ($question !== '') {
				$faqs[] = ['question' => $question, 'answer' => $answer];
			}
		}
	}
	return $faqs;
}

/**
 * Parse a content / service-details style block into section settings.
 *
 * @return array<string, mixed>
 */
function lf_pci_parse_content_block(string $block, bool $with_checklist = true): array {
	$f = lf_pci_parse_fields($block, ['body', 'intro']);
	$checklist_raw = $f['checklist'] ?? '';
	if ($with_checklist && $checklist_raw === '' && preg_match('/Checklist:\s*([\s\S]+)/i', $block, $m)) {
		$checklist_raw = trim($m[1]);
	}
	$checklist = $with_checklist ? lf_pci_parse_bullet_list($checklist_raw) : [];
	return array_filter([
		'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
		'section_intro' => $f['intro'] ?? $f['section_intro'] ?? '',
		'service_details_body' => $f['body'] ?? $f['section_body'] ?? '',
		'service_details_checklist' => $checklist !== [] ? implode("\n", $checklist) : '',
		'service_details_media_mode' => 'image',
		'content_media_show_checklist' => $checklist !== [] ? '1' : '0',
	]);
}

/**
 * @return list<string>
 */
function lf_pci_parse_list_lines(string $raw): array {
	$lines = [];
	foreach (explode("\n", trim($raw)) as $line) {
		$line = trim($line);
		if ($line === '') {
			continue;
		}
		$line = preg_replace('/^[-*•]\s+/', '', $line) ?? $line;
		$line = trim($line);
		if ($line !== '') {
			$lines[] = $line;
		}
	}
	return $lines;
}

/**
 * @return array<string, mixed>
 */
function lf_pci_parse_hero_block(string $block, string $hero_variant): array {
	$f = lf_pci_parse_fields($block, [
		'subheadline',
		'left_pills',
		'chip_bullets',
		'hero_chip_bullets',
		'proof_bullets',
		'hero_proof_bullets',
	]);
	$eyebrow = trim((string) ($f['eyebrow'] ?? $f['eyebrow_text'] ?? $f['trust_badge'] ?? ''));
	$chip_raw = $f['left_pills'] ?? $f['chip_bullets'] ?? $f['hero_chip_bullets'] ?? '';
	if ($chip_raw === '' && preg_match('/(?:Left pills|Chip bullets)\s*:\s*([\s\S]+?)(?=\n[A-Za-z][A-Za-z0-9 _\/-]{0,40}:|\z)/i', $block, $m)) {
		$chip_raw = trim($m[1]);
	}
	$proof_raw = $f['proof_bullets'] ?? $f['hero_proof_bullets'] ?? '';
	if ($proof_raw === '' && preg_match('/Proof bullets\s*:\s*([\s\S]+?)(?=\n[A-Za-z][A-Za-z0-9 _\/-]{0,40}:|\z)/i', $block, $m)) {
		$proof_raw = trim($m[1]);
	}
	$chip_lines = lf_pci_parse_list_lines((string) $chip_raw);
	$proof_lines = lf_pci_parse_list_lines((string) $proof_raw);

	return array_filter([
		'variant' => $hero_variant,
		'hero_headline' => $f['headline'] ?? $f['hero_headline'] ?? '',
		'hero_subheadline' => $f['subheadline'] ?? $f['hero_subheadline'] ?? '',
		'hero_eyebrow_text' => $eyebrow,
		'hero_eyebrow_enabled' => $eyebrow !== '' ? '1' : '',
		'hero_proof_title' => $f['proof_card_title'] ?? $f['proof_title'] ?? $f['hero_proof_title'] ?? '',
		'hero_chip_bullets' => $chip_lines !== [] ? implode("\n", $chip_lines) : '',
		'hero_proof_bullets' => $proof_lines !== [] ? implode("\n", $proof_lines) : '',
		'cta_primary_override' => $f['primary_cta'] ?? $f['cta_primary_override'] ?? $f['primary_cta_label'] ?? '',
		'cta_secondary_override' => $f['secondary_cta'] ?? $f['cta_secondary_override'] ?? $f['secondary_cta_label'] ?? '',
	]);
}

/**
 * @return array<string, mixed>
 */
function lf_pci_parse_cta_block(string $block): array {
	$f = lf_pci_parse_fields($block, ['subheadline', 'bullets']);
	$bullets_raw = $f['bullets'] ?? $f['cta_bullets'] ?? '';
	if ($bullets_raw === '' && preg_match('/Bullets\s*:\s*([\s\S]+?)(?=\n[A-Za-z][A-Za-z0-9 _\/-]{0,40}:|\z)/i', $block, $m)) {
		$bullets_raw = trim($m[1]);
	}
	$bullet_lines = lf_pci_parse_list_lines((string) $bullets_raw);

	return array_filter([
		'cta_headline' => $f['headline'] ?? $f['cta_headline'] ?? '',
		'cta_subheadline' => $f['subheadline'] ?? $f['cta_subheadline'] ?? '',
		'cta_bullets' => $bullet_lines !== [] ? implode("\n", $bullet_lines) : '',
		'cta_primary_override' => $f['primary_cta'] ?? $f['cta_primary_override'] ?? $f['primary_cta_label'] ?? '',
		'cta_secondary_override' => $f['secondary_cta'] ?? $f['cta_secondary_override'] ?? $f['secondary_cta_label'] ?? '',
	]);
}

/**
 * @param array<string, mixed> $merged
 * @param array<string, mixed> $schema
 * @param array<string, mixed> $existing
 * @return array<string, mixed>
 */
function lf_pci_apply_preserved_keys(array $merged, string $type, array $schema, array $existing): array {
	$preserve = is_array($schema['preserve_keys'][$type] ?? null) ? $schema['preserve_keys'][$type] : [];
	if ($preserve === [] || !isset($existing[$type]) || !is_array($existing[$type])) {
		return $merged;
	}
	foreach ($preserve as $key) {
		if (array_key_exists($key, $existing[$type])) {
			$merged[$key] = $existing[$type][$key];
		}
	}
	return $merged;
}

/**
 * Normalize key for service-details layout (supports service_details__2).
 */
function lf_pci_normalize_section_settings(string $type, string $base_type, array $settings): array {
	if (!function_exists('lf_sections_normalize_service_details_settings')) {
		return $settings;
	}
	$normalize_id = $type;
	if ($base_type === 'service_details' && !isset(lf_sections_service_details_alias_layouts()[$type])) {
		$normalize_id = $base_type;
	}
	return lf_sections_normalize_service_details_settings($normalize_id, $settings);
}

/**
 * Parse a doc for a registered page template schema.
 *
 * @return array{
 *   page_slug: string,
 *   page_label: string,
 *   sections: array<string, array<string, mixed>>,
 *   process_steps: list<array{title:string,body:string}>,
 *   faqs: list<array{question:string,answer:string}>,
 *   seo: array{title: string, description: string},
 *   warnings: list<string>,
 *   errors: list<string>,
 *   found_sections: list<string>
 * }
 */
function lf_pci_parse_with_schema(string $raw, array $schema): array {
	$aliases = is_array($schema['section_aliases'] ?? null) ? $schema['section_aliases'] : [];
	$raw = lf_pci_normalize_raw($raw);
	$split = lf_pci_split_sections($raw, $aliases);
	$warnings = [];
	$errors = [];
	$sections = [];
	$process_steps = [];
	$faqs = [];
	$seo = ['title' => '', 'description' => ''];
	$order = is_array($schema['order'] ?? null) ? $schema['order'] : [];
	$hero_variant = (string) ($schema['hero_variant'] ?? 'page');
	$hero_variant = function_exists('lf_sections_normalize_hero_variant')
		? lf_sections_normalize_hero_variant($hero_variant, false)
		: (in_array($hero_variant, ['page', 'internal'], true) ? 'page' : 'conversion');

	foreach (array_keys($split) as $section_key) {
		if (lf_pci_section_is_locked($section_key, $schema)) {
			$warnings[] = sprintf(
				/* translators: %s: section name */
				__('Section "%s" is theme-controlled and was ignored in the doc.', 'leadsforward-core'),
				$section_key
			);
			unset($split[$section_key]);
		}
	}

	if ($split === []) {
		$errors[] = __('No sections found. Use headings like === HERO ===, === STORY ===, etc.', 'leadsforward-core');
		return [
			'page_slug' => (string) ($schema['slug'] ?? ''),
			'page_label' => (string) ($schema['label'] ?? ''),
			'sections' => $sections,
			'process_steps' => $process_steps,
			'faqs' => $faqs,
			'seo' => $seo,
			'warnings' => $warnings,
			'errors' => $errors,
			'found_sections' => [],
		];
	}

	// Hero
	if (!empty($split['hero'])) {
		$sections['hero'] = lf_pci_parse_hero_block($split['hero'], $hero_variant);
	}

	// Trust bar
	if (!empty($split['trust_bar'])) {
		$f = lf_pci_parse_fields($split['trust_bar'], ['badges', 'items']);
		$badges_raw = $f['badges'] ?? $f['trust_badges'] ?? $f['items'] ?? '';
		if ($badges_raw === '') {
			$badges_raw = preg_replace('/^.*?(Badges|Items)\s*:\s*/is', '', $split['trust_bar']) ?? $split['trust_bar'];
		}
		$badge_lines = array_filter(array_map('trim', explode("\n", trim($badges_raw))));
		$sections['trust_bar'] = array_filter([
			'trust_heading' => $f['heading'] ?? $f['trust_heading'] ?? '',
			'trust_badges' => implode("\n", $badge_lines),
		]);
	}

	// Service intro (homepage service cards header — cards stay from Services CPT)
	if (!empty($split['service_intro'])) {
		$f = lf_pci_parse_fields($split['service_intro'], ['intro']);
		$sections['service_intro'] = array_filter([
			'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
			'section_intro' => $f['intro'] ?? $f['section_intro'] ?? '',
			'service_intro_view_all_label' => $f['view_all'] ?? $f['view_all_label'] ?? $f['service_intro_view_all_label'] ?? '',
		]);
	}

	// Reviews section header (review cards stay from Reviews CPT)
	if (!empty($split['trust_reviews'])) {
		$f = lf_pci_parse_fields($split['trust_reviews'], []);
		$sections['trust_reviews'] = array_filter([
			'trust_heading' => $f['heading'] ?? $f['trust_heading'] ?? '',
		]);
	}

	// Map / NAP section header (address + map iframe stay in Global Settings)
	if (!empty($split['map_nap'])) {
		$f = lf_pci_parse_fields($split['map_nap'], ['intro']);
		$sections['map_nap'] = array_filter([
			'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
			'section_intro' => $f['intro'] ?? $f['section_intro'] ?? '',
		]);
	}

	// Story / content_image
	if (!empty($split['content_image'])) {
		$sections['content_image'] = lf_pci_parse_content_block($split['content_image']);
	}

	// Benefits
	if (!empty($split['benefits'])) {
		$f = lf_pci_parse_fields($split['benefits'], ['intro', 'items']);
		$items_raw = $f['items'] ?? $f['benefits_items'] ?? '';
		if ($items_raw === '') {
			$items_raw = preg_replace('/^.*?(Items|Benefits)\s*:\s*/is', '', $split['benefits']) ?? $split['benefits'];
		}
		$items_lines = lf_pci_parse_benefits_item_lines($items_raw);
		$benefits = array_filter([
			'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
			'section_intro' => $f['intro'] ?? $f['section_intro'] ?? '',
			'benefits_items' => implode("\n", $items_lines),
		]);
		$sections['benefits'] = lf_pci_normalize_benefits_settings($benefits);
	}

	// Team / image_content
	if (!empty($split['image_content'])) {
		$sections['image_content'] = lf_pci_parse_content_block($split['image_content'], false);
	}

	// Service details (homepage blocks A / B)
	foreach (['service_details', 'service_details__2'] as $sd_key) {
		if (!empty($split[$sd_key])) {
			$sections[$sd_key] = lf_pci_parse_content_block($split[$sd_key]);
		}
	}

	// Simple content (legal / thank-you)
	if (!empty($split['content'])) {
		$f = lf_pci_parse_fields($split['content'], ['body']);
		$sections['content'] = array_filter([
			'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
			'service_details_body' => $f['body'] ?? $f['section_body'] ?? $split['content'],
		]);
	}

	// Process
	if (!empty($split['process'])) {
		$f = lf_pci_parse_fields($split['process'], ['intro', 'steps']);
		$steps_block = $f['steps'] ?? '';
		if ($steps_block === '') {
			$steps_block = preg_replace('/^.*?(Heading|Intro)\s*:[^\n]*\n?/im', '', $split['process']) ?? $split['process'];
		}
		$process_steps = lf_pci_parse_process_steps($steps_block);
		if ($process_steps === []) {
			$process_steps = lf_pci_parse_process_steps($split['process']);
		}
		$sections['process'] = array_filter([
			'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
			'section_intro' => $f['intro'] ?? $f['section_intro'] ?? '',
		]);
	}

	// FAQ
	if (!empty($split['faq_accordion'])) {
		$f = lf_pci_parse_fields($split['faq_accordion'], ['intro']);
		$faq_block = preg_replace('/^.*?(Heading|Intro)\s*:[^\n]*\n?/im', '', $split['faq_accordion']) ?? $split['faq_accordion'];
		$faqs = lf_pci_parse_faqs($faq_block);
		if ($faqs === []) {
			$faqs = lf_pci_parse_faqs($split['faq_accordion']);
		}
		$sections['faq_accordion'] = array_filter([
			'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
			'section_intro' => $f['intro'] ?? $f['section_intro'] ?? '',
			'faq_max_items' => 12,
		]);
	}

	// CTA
	if (!empty($split['cta'])) {
		$sections['cta'] = lf_pci_parse_cta_block($split['cta']);
	}

	// SEO
	if (!empty($split['seo'])) {
		$f = lf_pci_parse_fields($split['seo'], ['description', 'meta_description']);
		$seo = [
			'title' => $f['title'] ?? $f['meta_title'] ?? $f['seo_title'] ?? '',
			'description' => $f['description'] ?? $f['meta_description'] ?? $f['seo_description'] ?? '',
		];
	}

	$required = is_array($schema['required'] ?? null) ? $schema['required'] : ['hero', 'cta'];
	foreach ($required as $req) {
		if (empty($sections[$req])) {
			$warnings[] = sprintf(
				/* translators: %s: section name */
				__('Section "%s" is missing or empty — template defaults will be used.', 'leadsforward-core'),
				$req
			);
		}
	}
	if (in_array('process', $order, true) && $process_steps === []) {
		$warnings[] = __('No process steps in doc — Niche Content Library defaults will be used on apply.', 'leadsforward-core');
	}
	if (in_array('faq_accordion', $order, true) && $faqs === []) {
		$warnings[] = __('No FAQs in doc — Niche Content Library defaults will be used on apply.', 'leadsforward-core');
	}

	$all_text = $raw;
	if (strpos($all_text, '{business}') === false && strpos($all_text, '{city}') === false) {
		$warnings[] = __('Consider using {business} and {city} tokens for stronger local SEO.', 'leadsforward-core');
	}

	return [
		'page_slug' => (string) ($schema['slug'] ?? ''),
		'page_label' => (string) ($schema['label'] ?? ''),
		'sections' => $sections,
		'process_steps' => $process_steps,
		'faqs' => $faqs,
		'seo' => $seo,
		'warnings' => $warnings,
		'errors' => $errors,
		'found_sections' => array_keys($split),
	];
}

/**
 * Parse doc; resolves target page from === PAGE ===, filename, or optional override.
 *
 * @return array<string, mixed>
 */
function lf_pci_parse_document(string $raw, ?string $force_template = null, string $source_filename = ''): array {
	$raw = lf_pci_normalize_raw($raw);
	$raw = lf_pci_strip_writer_notes($raw);
	$header = lf_pci_extract_page_header($raw);
	$parse_warnings = [];

	$template_key = '';
	$target_slug = $header['slug'];
	if ($force_template !== null && $force_template !== '') {
		$template_key = sanitize_title($force_template);
	} else {
		$template_key = $header['template'] !== '' ? $header['template'] : $header['slug'];
	}

	if ($template_key === '' || lf_pci_schema_for_slug($template_key) === null) {
		$inferred = $source_filename !== '' ? lf_pci_infer_target_from_filename($source_filename) : [];
		if ($inferred !== [] && ($inferred['template'] ?? '') !== '') {
			$template_key = (string) $inferred['template'];
			if ($target_slug === '' && ($inferred['slug'] ?? '') !== '') {
				$target_slug = (string) $inferred['slug'];
			}
			if ($header['label'] === '' && ($inferred['label'] ?? '') !== '') {
				$header['label'] = (string) $inferred['label'];
			}
			$parse_warnings[] = sprintf(
				/* translators: %s: filename */
				__('Page target inferred from filename (%s). GPT often deletes the === PAGE === block — keep it in future docs.', 'leadsforward-core'),
				sanitize_file_name($source_filename)
			);
		}
	}

	$loose = lf_pci_extract_loose_target_fields($raw);
	if ($loose['template'] !== '' && lf_pci_schema_for_slug($loose['template']) !== null) {
		$template_key = $loose['template'];
	}
	if ($loose['slug'] !== '') {
		$target_slug = $loose['slug'];
	}

	if (
		in_array($template_key, ['service', 'service-area'], true)
		&& $target_slug === ''
	) {
		$post_type = $template_key === 'service-area' ? 'lf_service_area' : 'lf_service';
		$target_slug = lf_pci_infer_single_cpt_slug($post_type);
	}

	$schema = lf_pci_schema_for_slug($template_key);
	if ($schema === null) {
		$hint = $template_key !== ''
			? sprintf(
				/* translators: %s: template key */
				__('No import template registered for "%s".', 'leadsforward-core'),
				$template_key
			)
			: __('Missing page target. Add a === PAGE === block with Slug: (pages) or Template: service + Slug: (service posts), or name files like about-us-filled.docx.', 'leadsforward-core');
		if (
			in_array($template_key, ['service', 'service-area'], true)
			&& $target_slug === ''
		) {
			$hint = __('Missing service/area slug. Add === PAGE === with Template: service + Slug: your-slug, or rename the file to {post-slug}-filled.docx.', 'leadsforward-core');
		}
		return [
			'template_key' => $template_key,
			'page_slug' => $target_slug,
			'page_label' => $header['label'],
			'post_type' => 'page',
			'sections' => [],
			'process_steps' => [],
			'faqs' => [],
			'seo' => ['title' => '', 'description' => ''],
			'warnings' => $parse_warnings,
			'errors' => [$hint],
			'found_sections' => [],
		];
	}
	$parsed = lf_pci_parse_with_schema($header['content'], $schema);
	$parsed['template_key'] = $template_key;
	$post_type = (string) ($schema['post_type'] ?? 'page');
	if ($target_slug !== '') {
		$parsed['page_slug'] = $target_slug;
	} elseif ($post_type === 'page') {
		$parsed['page_slug'] = (string) ($schema['slug'] ?? '');
	} else {
		$parsed['page_slug'] = '';
	}
	$parsed['post_type'] = $post_type;
	if ($header['label'] !== '') {
		$parsed['page_label'] = $header['label'];
	}
	if ($parse_warnings !== []) {
		$parsed['warnings'] = array_values(array_unique(array_merge(
			is_array($parsed['warnings'] ?? null) ? $parsed['warnings'] : [],
			$parse_warnings
		)));
	}

	return $parsed;
}

/** @deprecated Use lf_pci_parse_document() */
function lf_pci_parse_about_us(string $raw): array {
	return lf_pci_parse_document($raw, 'about-us');
}

/**
 * @param array<string, mixed> $sections
 * @param list<array{title:string,body:string}> $process_steps
 * @param list<array{question:string,answer:string}> $faqs
 * @param array{title: string, description: string} $seo
 * @param array{sync_mode?: string} $options
 */
function lf_pci_apply_about_us(int $page_id, array $sections, array $process_steps, array $faqs, array $seo, array $options = []): array {
	return lf_pci_apply_to_page($page_id, lf_pci_about_us_schema(), $sections, $process_steps, $faqs, $seo, $options);
}

/**
 * Apply parsed section payloads to a page.
 *
 * @param array<string, mixed> $sections
 * @param list<array{title:string,body:string}> $process_steps
 * @param list<array{question:string,answer:string}> $faqs
 * @param array{title: string, description: string} $seo
 * @param array{sync_mode?: string} $options
 */
function lf_pci_apply_to_page(int $page_id, array $schema, array $sections, array $process_steps, array $faqs, array $seo, array $options = []): array {
	$vars = lf_pci_template_vars();
	$result = [
		'page_id' => $page_id,
		'process_ids' => [],
		'faq_ids' => [],
		'sections_updated' => [],
		'sections_preserved' => [],
		'process_source' => 'import',
		'faq_source' => 'import',
	];

	$niche_slug = (string) get_option('lf_homepage_niche_slug', 'foundation-repair');
	$library = function_exists('lf_niche_content_library_get_for_niche')
		? lf_niche_content_library_get_for_niche($niche_slug)
		: ['process' => [], 'faqs' => []];

	$order = is_array($schema['order'] ?? null) ? $schema['order'] : [];
	$has_process = in_array('process', $order, true);
	$has_faq = in_array('faq_accordion', $order, true);
	$page_slug = (string) ($schema['slug'] ?? '');
	$is_faq_hub = !empty($schema['faq_hub']) || (function_exists('lf_page_template_is_faq_hub') && lf_page_template_is_faq_hub($page_slug));

	if ($has_process && $process_steps === [] && !empty($library['process'])) {
		$process_steps = $library['process'];
		$result['process_source'] = 'library';
	}
	if ($has_faq && $faqs === [] && !empty($library['faqs'])) {
		$faqs = $library['faqs'];
		$result['faq_source'] = 'library';
	}
	if ($is_faq_hub && $has_faq && $faqs === [] && function_exists('lf_niche_seed_about_content')) {
		$seeded = lf_niche_seed_about_content($niche_slug, $vars, false);
		if (!empty($seeded['faq_ids'])) {
			$result['faq_ids'] = array_values(array_filter(array_map('absint', $seeded['faq_ids'])));
			$result['faq_source'] = 'library';
		}
	}

	$sync_mode = (string) ($options['sync_mode'] ?? 'force');
	$overwrite_process = $has_process && $result['process_source'] === 'import' && $sync_mode === 'force';
	$overwrite_faq = $has_faq && $result['faq_source'] === 'import' && $sync_mode === 'force';

	// Fill tokens in all string fields.
	foreach ($sections as $type => $settings) {
		if (!is_array($settings)) {
			continue;
		}
		foreach ($settings as $key => $val) {
			if (is_string($val)) {
				$sections[$type][$key] = lf_pci_fill_tokens($val, $vars);
			}
		}
	}
	$seo['title'] = lf_pci_fill_tokens((string) ($seo['title'] ?? ''), $vars);
	$seo['description'] = lf_pci_fill_tokens((string) ($seo['description'] ?? ''), $vars);

	$process_group = (string) ($schema['process_group'] ?? (defined('LF_NICHE_ABOUT_PROCESS_GROUP') ? LF_NICHE_ABOUT_PROCESS_GROUP : 'about-company'));
	$faq_context = (string) ($schema['faq_context'] ?? (defined('LF_NICHE_ABOUT_FAQ_CONTEXT') ? LF_NICHE_ABOUT_FAQ_CONTEXT : 'about_company'));

	if ($has_process && $process_steps !== [] && function_exists('lf_niche_upsert_process_steps')) {
		$result['process_ids'] = lf_niche_upsert_process_steps($process_steps, $process_group, $vars, $overwrite_process);
	}
	if ($has_faq && $faqs !== [] && function_exists('lf_niche_upsert_context_faqs')) {
		$upsert_context = $faq_context;
		if ($is_faq_hub && $result['faq_source'] === 'library') {
			$upsert_context = defined('LF_NICHE_ABOUT_FAQ_CONTEXT') ? LF_NICHE_ABOUT_FAQ_CONTEXT : 'about_company';
		}
		$result['faq_ids'] = lf_niche_upsert_context_faqs($faqs, $upsert_context, $vars, $overwrite_faq);
	}

	if (!empty($result['process_ids']) && function_exists('lf_niche_ids_to_lines')) {
		$sections['process']['process_selected_ids'] = lf_niche_ids_to_lines($result['process_ids']);
	}

	if ($has_faq && $is_faq_hub) {
		$hub_defaults = function_exists('lf_page_template_faq_hub_accordion_settings')
			? lf_page_template_faq_hub_accordion_settings()
			: ['faq_max_items' => -1, 'faq_selected_ids' => ''];
		if (!is_array($sections['faq_accordion'] ?? null)) {
			$sections['faq_accordion'] = [];
		}
		foreach (['section_heading', 'section_intro'] as $faq_key) {
			if (trim((string) ($sections['faq_accordion'][$faq_key] ?? '')) === '' && isset($hub_defaults[$faq_key])) {
				$sections['faq_accordion'][$faq_key] = (string) $hub_defaults[$faq_key];
			}
		}
		$sections['faq_accordion']['faq_max_items'] = -1;
		$sections['faq_accordion']['faq_selected_ids'] = '';
	} elseif (!empty($result['faq_ids']) && function_exists('lf_niche_ids_to_lines')) {
		$sections['faq_accordion']['faq_selected_ids'] = lf_niche_ids_to_lines($result['faq_ids']);
		$max = (int) ($sections['faq_accordion']['faq_max_items'] ?? 0);
		if ($max <= 0 && function_exists('lf_page_template_faq_max_items_for_slug')) {
			$sections['faq_accordion']['faq_max_items'] = lf_page_template_faq_max_items_for_slug($page_slug);
		}
	}

	if (!function_exists('lf_sections_defaults_for') || !function_exists('lf_pb_instance_id')) {
		return array_merge($result, ['error' => __('Page Builder is not available.', 'leadsforward-core')]);
	}

	$existing_pb = get_post_meta($page_id, LF_PB_META_KEY, true);
	$existing_sections = is_array($existing_pb) && is_array($existing_pb['sections'] ?? null)
		? $existing_pb['sections']
		: [];
	$hero_variant = (string) ($schema['hero_variant'] ?? 'page');
	$hero_variant = function_exists('lf_sections_normalize_hero_variant')
		? lf_sections_normalize_hero_variant($hero_variant, false)
		: (in_array($hero_variant, ['page', 'internal'], true) ? 'page' : 'conversion');

	$pb_sections = [];
	foreach ($order as $type) {
		$instance_id = lf_pci_pb_instance_for_order_key($type);

		if (lf_pci_section_is_locked($type, $schema) && isset($existing_sections[$instance_id]) && is_array($existing_sections[$instance_id])) {
			$pb_sections[$instance_id] = $existing_sections[$instance_id];
			$pb_sections[$instance_id]['enabled'] = true;
			$pb_sections[$instance_id]['deletable'] = false;
			$result['sections_preserved'][] = $type;
			continue;
		}

		$base_type = function_exists('lf_homepage_base_section_type')
			? lf_homepage_base_section_type($type)
			: $type;
		$defaults = lf_sections_defaults_for($base_type);
		if ($base_type === 'hero') {
			$defaults['variant'] = $hero_variant;
		}
		$imported = is_array($sections[$type] ?? null) ? $sections[$type] : [];
		if ($imported === [] && $type === $base_type && is_array($sections[$base_type] ?? null)) {
			$imported = $sections[$base_type];
		}
		$merged = array_merge($defaults, $imported);
		if (function_exists('lf_sections_normalize_service_details_settings')) {
			$merged = lf_pci_normalize_section_settings($type, $base_type, $merged);
		}
		if ($base_type === 'benefits') {
			$merged = lf_pci_normalize_benefits_settings($merged);
		}
		$merged = lf_pci_apply_preserved_keys($merged, $type, $schema, $existing_sections);
		$pb_sections[$instance_id] = [
			'type' => $base_type,
			'enabled' => true,
			'deletable' => false,
			'settings' => $merged,
		];
		$result['sections_updated'][] = $type;
	}

	$seo_out = [
		'title' => sanitize_text_field($seo['title']),
		'description' => sanitize_textarea_field($seo['description']),
	];

	$existing_seo = is_array($existing_pb) && is_array($existing_pb['seo'] ?? null) ? $existing_pb['seo'] : [];
	if ($seo_out['title'] === '' && !empty($existing_seo['title'])) {
		$seo_out['title'] = (string) $existing_seo['title'];
	}
	if ($seo_out['description'] === '' && !empty($existing_seo['description'])) {
		$seo_out['description'] = (string) $existing_seo['description'];
	}

	update_post_meta($page_id, LF_PB_META_KEY, [
		'order' => array_keys($pb_sections),
		'sections' => $pb_sections,
		'seo' => $seo_out,
	]);

	if ($seo_out['title'] !== '') {
		update_post_meta($page_id, '_lf_seo_title', $seo_out['title']);
	}
	if ($seo_out['description'] !== '') {
		update_post_meta($page_id, '_lf_seo_meta_description', $seo_out['description']);
	}

	$result['success'] = true;
	return $result;
}

/**
 * Apply parsed homepage content to homepage section options.
 *
 * @param array<string, mixed> $sections
 * @param list<array{title:string,body:string}> $process_steps
 * @param list<array{question:string,answer:string}> $faqs
 * @param array{title: string, description: string} $seo
 * @param array{sync_mode?: string} $options
 */
function lf_pci_apply_to_homepage(array $schema, array $sections, array $process_steps, array $faqs, array $seo, array $options = []): array {
	if (!defined('LF_HOMEPAGE_CONFIG_OPTION') || !function_exists('lf_homepage_default_section_config')) {
		return ['success' => false, 'error' => __('Homepage controller is not available.', 'leadsforward-core')];
	}

	$page_id = lf_pci_get_page_id_for_slug('home');
	$vars = lf_pci_template_vars();
	$result = [
		'page_id' => $page_id,
		'process_ids' => [],
		'faq_ids' => [],
		'sections_updated' => [],
		'sections_preserved' => [],
		'process_source' => 'import',
		'faq_source' => 'import',
		'storage' => 'homepage',
	];

	$order = is_array($schema['order'] ?? null) ? $schema['order'] : [];
	$niche_slug = (string) get_option('lf_homepage_niche_slug', 'foundation-repair');
	$library = function_exists('lf_niche_content_library_get_for_niche')
		? lf_niche_content_library_get_for_niche($niche_slug)
		: ['process' => [], 'faqs' => []];

	if ($process_steps === [] && !empty($library['process'])) {
		$process_steps = $library['process'];
		$result['process_source'] = 'library';
	}
	if ($faqs === [] && !empty($library['faqs'])) {
		$faqs = $library['faqs'];
		$result['faq_source'] = 'library';
	}

	$sync_mode = (string) ($options['sync_mode'] ?? 'force');
	$overwrite_process = $result['process_source'] === 'import' && $sync_mode === 'force';
	$overwrite_faq = $result['faq_source'] === 'import' && $sync_mode === 'force';

	foreach ($sections as $type => $settings) {
		if (!is_array($settings)) {
			continue;
		}
		foreach ($settings as $key => $val) {
			if (is_string($val)) {
				$sections[$type][$key] = lf_pci_fill_tokens($val, $vars);
			}
		}
	}
	$seo['title'] = lf_pci_fill_tokens((string) ($seo['title'] ?? ''), $vars);
	$seo['description'] = lf_pci_fill_tokens((string) ($seo['description'] ?? ''), $vars);

	$process_group = (string) ($schema['process_group'] ?? (defined('LF_NICHE_ABOUT_PROCESS_GROUP') ? LF_NICHE_ABOUT_PROCESS_GROUP : 'about-company'));
	$faq_context = (string) ($schema['faq_context'] ?? (defined('LF_NICHE_ABOUT_FAQ_CONTEXT') ? LF_NICHE_ABOUT_FAQ_CONTEXT : 'about_company'));

	if ($process_steps !== [] && function_exists('lf_niche_upsert_process_steps')) {
		$result['process_ids'] = lf_niche_upsert_process_steps($process_steps, $process_group, $vars, $overwrite_process);
	}
	if ($faqs !== [] && function_exists('lf_niche_upsert_context_faqs')) {
		$result['faq_ids'] = lf_niche_upsert_context_faqs($faqs, $faq_context, $vars, $overwrite_faq);
	}
	if (!empty($result['process_ids']) && function_exists('lf_niche_ids_to_lines')) {
		$sections['process']['process_selected_ids'] = lf_niche_ids_to_lines($result['process_ids']);
	}
	if (!empty($result['faq_ids']) && function_exists('lf_niche_ids_to_lines')) {
		$sections['faq_accordion']['faq_selected_ids'] = lf_niche_ids_to_lines($result['faq_ids']);
	}

	$existing = function_exists('lf_get_homepage_section_config')
		? lf_get_homepage_section_config()
		: (array) get_option(LF_HOMEPAGE_CONFIG_OPTION, []);
	$hero_variant = (string) ($schema['hero_variant'] ?? 'conversion');
	$hero_variant = function_exists('lf_sections_normalize_hero_variant')
		? lf_sections_normalize_hero_variant($hero_variant, true)
		: 'conversion';
	$config = [];

	foreach ($order as $type) {
		if (lf_pci_section_is_locked($type, $schema) && isset($existing[$type]) && is_array($existing[$type])) {
			$config[$type] = $existing[$type];
			$config[$type]['enabled'] = true;
			$result['sections_preserved'][] = $type;
			continue;
		}

		$base_type = function_exists('lf_homepage_base_section_type')
			? lf_homepage_base_section_type($type)
			: $type;
		$defaults = lf_homepage_default_section_config($base_type, $niche_slug);
		if ($base_type === 'hero') {
			$defaults['variant'] = $hero_variant;
		}
		$imported = is_array($sections[$type] ?? null) ? $sections[$type] : [];
		if ($imported === [] && $type === $base_type && is_array($sections[$base_type] ?? null)) {
			$imported = $sections[$base_type];
		}
		$merged = array_merge($defaults, $imported);
		$merged['enabled'] = true;
		if (function_exists('lf_sections_normalize_service_details_settings')) {
			$merged = lf_pci_normalize_section_settings($type, $base_type, $merged);
		}
		if ($base_type === 'benefits') {
			$merged = lf_pci_normalize_benefits_settings($merged);
		}
		$merged = lf_pci_apply_preserved_keys($merged, $type, $schema, $existing);
		$config[$type] = $merged;
		$result['sections_updated'][] = $type;
	}

	update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, false);
	if (defined('LF_HOMEPAGE_ORDER_OPTION')) {
		update_option(LF_HOMEPAGE_ORDER_OPTION, $order, false);
	}
	if (defined('LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION')) {
		update_option(LF_HOMEPAGE_MANUAL_OVERRIDE_OPTION, true, false);
	}

	$seo_out = [
		'title' => sanitize_text_field($seo['title']),
		'description' => sanitize_textarea_field($seo['description']),
	];
	if ($page_id > 0) {
		if ($seo_out['title'] !== '') {
			update_post_meta($page_id, '_lf_seo_title', $seo_out['title']);
		}
		if ($seo_out['description'] !== '') {
			update_post_meta($page_id, '_lf_seo_meta_description', $seo_out['description']);
		}
	}

	$result['success'] = true;
	return $result;
}

/**
 * Apply a full lf_pci_parse_document() result to the target page.
 *
 * @param array<string, mixed> $parsed
 * @param array{sync_mode?: string} $options
 * @return array<string, mixed>
 */
function lf_pci_apply_parsed(array $parsed, array $options = []): array {
	$template_key = (string) ($parsed['template_key'] ?? $parsed['page_slug'] ?? '');
	$schema = lf_pci_schema_for_slug($template_key);
	if ($schema === null) {
		return ['success' => false, 'error' => __('No template registered for this import.', 'leadsforward-core')];
	}

	$post_type = (string) ($parsed['post_type'] ?? $schema['post_type'] ?? 'page');
	$target_slug = (string) ($options['post_slug'] ?? $parsed['page_slug'] ?? $template_key);
	$page_id = (int) ($options['page_id'] ?? 0);
	if ($page_id <= 0) {
		$page_id = lf_pci_get_post_id_for_target($post_type, $target_slug);
	}

	$sections = (array) ($parsed['sections'] ?? []);
	$process_steps = (array) ($parsed['process_steps'] ?? []);
	$faqs = (array) ($parsed['faqs'] ?? []);
	$seo = (array) ($parsed['seo'] ?? ['title' => '', 'description' => '']);
	$apply_options = $options;

	if (($schema['storage'] ?? 'page_builder') === 'homepage') {
		return lf_pci_apply_to_homepage($schema, $sections, $process_steps, $faqs, $seo, $apply_options);
	}

	if ($page_id <= 0) {
		$label = $post_type === 'lf_service'
			? __('Service post', 'leadsforward-core')
			: __('WordPress page', 'leadsforward-core');
		return [
			'success' => false,
			'error' => sprintf(
				/* translators: 1: post type label, 2: slug */
				__('%1$s not found for slug "%2$s". Create the post first.', 'leadsforward-core'),
				$label,
				$target_slug
			),
			'page_slug' => $target_slug,
			'template_key' => $template_key,
		];
	}

	return lf_pci_apply_to_page($page_id, $schema, $sections, $process_steps, $faqs, $seo, $apply_options);
}

/**
 * Resolve About Us page ID (slug about-us).
 */
function lf_pci_get_about_page_id(): int {
	return lf_pci_get_page_id_for_slug('about-us');
}

/**
 * Resolve a downloadable paste template for a registered page slug.
 */
function lf_pci_template_for_slug(string $slug, bool $include_legend = true, ?int $post_id = null): string {
	$slug = sanitize_title($slug);
	$schema = lf_pci_schema_for_slug($slug);
	if ($schema === null) {
		return '';
	}
	$file = LF_THEME_DIR . '/docs/templates/' . $slug . '-content-template.txt';
	if (is_readable($file)) {
		$body = (string) file_get_contents($file);
	} elseif ($slug === 'about-us') {
		$body = lf_pci_about_us_template_fallback();
	} else {
		$body = lf_pci_template_fallback_for_schema($schema);
	}

	$resolved_post_id = $post_id > 0 ? $post_id : lf_pci_resolve_post_id_for_template($slug, 0);
	if ($resolved_post_id > 0) {
		$body = lf_pci_personalize_template_page_header($body, $resolved_post_id);
	}
	$keyword_ctx = lf_pci_writer_keyword_context($resolved_post_id > 0 ? $resolved_post_id : null, $slug);
	$vars = array_merge(lf_pci_template_vars(), $keyword_ctx);

	return lf_pci_prepare_template_body($body, $include_legend, $keyword_ctx, $vars);
}

/**
 * Blank section blocks for generated .docx templates.
 *
 * @return array<string, string>
 */
function lf_pci_section_doc_templates(string $template_slug = ''): array {
	$hero = "=== HERO ===\nHeadline: \nSubheadline: \nEyebrow: ";
	if ($template_slug === 'home') {
		$hero = "=== HERO ===\nHeadline: \nSubheadline: \nEyebrow: \nLeft pills:\n- \nProof card title: \nProof bullets:\n- \nPrimary CTA: \nSecondary CTA: ";
	}

	$cta = "=== CTA ===\nHeadline: \nSubheadline: ";
	if ($template_slug === 'home') {
		$cta = "=== CTA ===\nHeadline: \nSubheadline: \nPrimary CTA: \nSecondary CTA: ";
	}

	return [
		'hero' => $hero,
		'trust_bar' => "=== TRUST BAR ===\nHeading: \nBadges:\n- Licensed & Insured\n- 5-Star Rated",
		'service_intro' => "=== SERVICES ===\nHeading: \nIntro: \nView all label: ",
		'service_areas' => "=== SERVICE AREAS ===\nHeading: \nIntro: ",
		'trust_reviews' => "=== REVIEWS ===\nHeading: ",
		'map_nap' => "=== MAP ===\nHeading: \nIntro: ",
		'content_image' => "=== STORY ===\nHeading: \nIntro: \nBody: \nChecklist:\n- ",
		'content_image_a' => "=== STORY A ===\nHeading: \nIntro: \nBody: \nChecklist:\n- ",
		'content_image_c' => "=== STORY C ===\nHeading: \nIntro: \nBody: ",
		'benefits' => "=== BENEFITS ===\nHeading: \nIntro: \nItems:\nBenefit one title || Benefit one body.\nBenefit two title || Benefit two body.\nBenefit three title || Benefit three body.",
		'image_content' => "=== TEAM ===\nHeading: \nIntro: \nBody: ",
		'image_content_b' => "=== MENTOR ===\nHeading: \nIntro: \nBody: ",
		'service_details' => "=== SERVICE DETAILS ===\nHeading: \nIntro: \nBody: \nChecklist:\n- ",
		'service_details__2' => "=== SERVICE DETAILS 2 ===\nHeading: \nIntro: \nBody: ",
		'process' => "=== PROCESS ===\nHeading: \nIntro: \nStep: Step title || Step body.",
		'project_gallery' => "=== PROJECT GALLERY ===\nHeading: \nIntro: ",
		'services_offered_here' => "=== SERVICES HERE ===\nHeading: \nIntro: ",
		'nearby_areas' => "=== NEARBY AREAS ===\nHeading: \nIntro: ",
		'related_links' => "=== RELATED LINKS ===\nHeading: \nIntro: ",
		'pricing' => "=== PRICING ===\nHeading: \nIntro: \nBody: ",
		'faq_accordion' => "=== FAQ ===\nHeading: \nIntro: \nQ: Question?\nA: Answer.",
		'blog_posts' => "=== BLOG ===\nHeading: \nIntro: ",
		'cta' => $cta,
		'content' => "=== CONTENT ===\nHeading: \nBody: ",
		'seo' => "=== SEO ===\nTitle: \nDescription: ",
	];
}

/**
 * @param array<string, mixed> $schema
 */
function lf_pci_template_fallback_for_schema(array $schema): string {
	$slug = (string) ($schema['slug'] ?? '');
	$label = (string) ($schema['label'] ?? $slug);
	$post_type = (string) ($schema['post_type'] ?? 'page');
	$locked = lf_pci_schema_locked_types($schema);
	$lines = ['=== PAGE ==='];
	if ($post_type === 'lf_service') {
		$lines[] = 'Template: service';
		$lines[] = 'Slug: your-service-slug';
		$lines[] = 'Name: ' . $label;
	} elseif ($post_type === 'lf_service_area') {
		$lines[] = 'Template: service-area';
		$lines[] = 'Slug: your-area-slug';
		$lines[] = 'Name: ' . $label;
	} else {
		$lines[] = 'Slug: ' . $slug;
		$lines[] = 'Name: ' . $label;
	}
	$lines[] = '';
	if ($locked !== []) {
		$lines[] = 'Notes: Theme-controlled (leave blank in doc): ' . implode(', ', $locked);
		$lines[] = '';
	}

	$section_docs = lf_pci_section_doc_templates((string) ($schema['slug'] ?? ''));
	foreach ((array) ($schema['order'] ?? []) as $type) {
		if (lf_pci_section_is_locked($type, $schema)) {
			continue;
		}
		$key = $type;
		if (!isset($section_docs[$key]) && function_exists('lf_homepage_base_section_type')) {
			$key = lf_homepage_base_section_type($type);
		}
		if (isset($section_docs[$key])) {
			$heading = $section_docs[$key];
			if ($type === 'service_details__2' && strpos($heading, 'SERVICE DETAILS 2') === false) {
				$heading = $section_docs['service_details__2'];
			}
			$lines[] = $heading;
			$lines[] = '';
		}
	}
	$lines[] = $section_docs['seo'];
	return implode("\n", $lines);
}

/** @deprecated Universal template removed — use per-page .docx downloads. */
function lf_pci_universal_template(bool $include_legend = true): string {
	return lf_pci_template_for_slug('home', $include_legend);
}

/**
 * Blank About Us template for Google Docs / paste workflow.
 */
function lf_pci_about_us_template(): string {
	return lf_pci_template_for_slug('about-us');
}

function lf_pci_about_us_template_fallback(): string {
	return <<<'TEMPLATE'
=== HERO ===
Headline: About {business}
Subheadline: Local foundation repair specialists{city_line} — structural solutions backed by clear communication, engineered plans, and transferable warranties.
Eyebrow: Licensed • Insured • Engineered Repairs

=== STORY ===
Heading: Built on trust, not quick fixes
Intro: Foundation problems do not wait — and neither should your contractor.
Body: {business} was founded to give homeowners an honest path through foundation repair{city_line}. We combine structural evaluations, engineered solutions, and crews who respect your home. No scare tactics — just documented findings, clear pricing, and work you can stand behind when it is time to sell or refinance.
Checklist:
- Licensed, insured structural crews
- Engineered repair plans before work starts
- Transferable warranty documentation
- Clean, protected job sites

=== BENEFITS ===
Heading: Why homeowners trust us with their foundation
Intro: Structural decisions deserve clarity, engineering, and a crew that shows up prepared.
Items:
Engineered solutions || Piers, anchors, and waterproofing sized to your soil — not one-size-fits-all kits.
Plain-language inspections || Photos, measurements, and options explained so you can make an informed decision.
Local accountability || Project managers who answer the phone and stand behind the work{city_line}.
Protected job sites || Landscaping, floors, and access paths treated with the same care we give our own homes.

=== TEAM ===
Heading: Meet the team behind your project
Intro: Structural repair is a team sport — inspectors, engineers, and installers working from the same plan.
Body: At {business}, your project is led by a dedicated manager who coordinates inspections, permits, and crew scheduling. Installers are trained on piering, wall stabilization, and waterproofing systems — not general handyman shortcuts.

=== PROCESS ===
Heading: How foundation repair works with us
Intro: A documented path from inspection to warranty — so you always know what happens next.
(Leave steps blank — auto-filled from LeadsForward → Niche Content Library on import.)

=== FAQ ===
Heading: Frequently Asked Questions
Intro: Quick answers about our company, process, and what to expect.
(Leave Q&A blank — auto-filled from LeadsForward → Niche Content Library on import.)

=== CTA ===
Headline: Schedule a foundation inspection with {business}
Subheadline: Request a free structural inspection and get a clear repair plan.

=== SEO ===
Title: About {business}{city_line}
Description: Meet {business} — local foundation repair specialists{city_line}. Engineered solutions, clear inspections, and crews who protect your home.
TEMPLATE;
}

/**
 * Keyword brief block inside writer notes (stripped on import).
 *
 * @param array{primary_keyword?: string, secondary_keywords?: string, serp_intent?: string} $ctx
 */
function lf_pci_template_keyword_targets_block(array $ctx): string {
	$primary = trim((string) ($ctx['primary_keyword'] ?? ''));
	$secondary = trim((string) ($ctx['secondary_keywords'] ?? ''));
	$intent = trim((string) ($ctx['serp_intent'] ?? 'transactional'));
	if ($primary === '' && $secondary === '') {
		return "KEYWORD TARGETS\nNot assigned yet. Set Primary Target Keyword on the post (or run Airtable Sitemap Sync / manifest assignment), then re-download this template.\n";
	}
	$lines = ["KEYWORD TARGETS"];
	if ($primary !== '') {
		$lines[] = 'Primary: ' . $primary;
	}
	if ($secondary !== '') {
		$lines[] = 'Secondary: ' . $secondary;
	}
	$lines[] = 'SERP intent: ' . ($intent !== '' ? $intent : 'transactional');
	$lines[] = 'Placement: Work the primary keyword into the hero headline (once, naturally), the first service-details paragraph, and the SEO title. Use secondary terms in subheads and body — never stuff or repeat awkwardly.';
	return implode("\n", $lines) . "\n";
}

/**
 * Token legend prepended to downloadable writer templates.
 *
 * @param array{primary_keyword?: string, secondary_keywords?: string, serp_intent?: string} $keyword_ctx
 */
function lf_pci_template_token_legend(array $keyword_ctx = []): string {
	$kw_block = lf_pci_template_keyword_targets_block($keyword_ctx);
	return <<<LEGEND
=== WRITER NOTES ===
(Removed automatically on import — paste this entire block into your AI as the system brief.)

ROLE
You are a senior local SEO copywriter for contractor and home-service companies ({business} in {city}). Write highly engaging, conversion-focused, SEO-smart copy that sounds human — never generic AI filler.

AUDIENCE & GOAL
Homeowners who need trust fast: licensed crews, clear process, honest pricing, local proof. Every section should reduce anxiety and drive one action (call, form, inspection).

VOICE
Confident, calm, specific. Short sentences. Real trade language. No hype, no "In today's world", no em-dash spam, no bullet-only pages.

{$kw_block}
FORMAT RULES
- Never delete the === PAGE === block (Slug: / Template: + Slug:). The importer needs it — or use filenames like about-us-filled.docx.
- Keep every other === SECTION === header and Key: value line exactly as written.
- Benefits / process steps: Title || body on one line per item.
- FAQs: Q: / A: pairs unless the section says to leave blank.
- Process + FAQ on service pages: leave blank when noted — theme fills from Niche Content Library.

WORKFLOW
1. Fill every writer-editable field below for this URL.
2. Upload the finished .docx at LeadsForward → Import Page Content.
Tokens auto-filled on import: {business}, {city}, {city_line}, {niche}, {phone}, {primary_keyword}
Process + FAQ: leave blank → Niche Content Library on import.

LEGEND;
}

/**
 * @param array{primary_keyword?: string, secondary_keywords?: string, serp_intent?: string} $keyword_ctx
 * @param array<string, string>|null $vars
 */
function lf_pci_prepare_template_body(string $body, bool $include_legend = true, array $keyword_ctx = [], ?array $vars = null): string {
	$body = trim($body);
	$vars = $vars ?? array_merge(lf_pci_template_vars(), $keyword_ctx);
	$body = lf_pci_fill_tokens($body, $vars);
	if (!$include_legend) {
		return $body;
	}
	$legend = lf_pci_fill_tokens(lf_pci_template_token_legend($keyword_ctx), $vars);
	return trim($legend . "\n" . $body);
}

/**
 * Extract plain text from a .docx upload (WordprocessingML).
 */
function lf_pci_extract_text_from_docx(string $path): string {
	if (!class_exists('ZipArchive') || !is_readable($path)) {
		return '';
	}
	$zip = new ZipArchive();
	if ($zip->open($path) !== true) {
		return '';
	}
	$xml = $zip->getFromName('word/document.xml');
	$zip->close();
	if (!is_string($xml) || $xml === '') {
		return '';
	}
	$xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml) ?? $xml;
	$xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
	$text = wp_strip_all_tags($xml);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = preg_replace("/\r\n|\r/", "\n", $text) ?? $text;
	$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

	return trim($text);
}

/**
 * Build a minimal Word .docx containing plain text (for Google Drive workflow).
 */
function lf_pci_build_docx_bytes(string $text): string {
	if (!class_exists('ZipArchive')) {
		return '';
	}
	$tmp = wp_tempnam('pci-template.docx');
	if ($tmp === '') {
		return '';
	}
	$escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	$paragraphs = '';
	foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
		$line = htmlspecialchars((string) $line, ENT_XML1 | ENT_QUOTES, 'UTF-8');
		$paragraphs .= '<w:p><w:r><w:t xml:space="preserve">' . $line . '</w:t></w:r></w:p>';
	}
	if ($paragraphs === '') {
		$paragraphs = '<w:p><w:r><w:t xml:space="preserve">' . $escaped . '</w:t></w:r></w:p>';
	}
	$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
		. '<w:body>' . $paragraphs . '</w:body></w:document>';
	$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
		. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
		. '<Default Extension="xml" ContentType="application/xml"/>'
		. '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
		. '</Types>';
	$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
		. '</Relationships>';

	$zip = new ZipArchive();
	if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
		@unlink($tmp);
		return '';
	}
	$zip->addFromString('[Content_Types].xml', $content_types);
	$zip->addFromString('_rels/.rels', $rels);
	$zip->addFromString('word/document.xml', $document);
	$zip->close();
	$bytes = (string) file_get_contents($tmp);
	@unlink($tmp);

	return $bytes;
}

/**
 * Read uploaded paste file (.txt, .md, .docx) into plain text for the parser.
 */
function lf_pci_read_upload_file_contents(string $path, string $filename): string {
	$filename = strtolower($filename);
	if (str_ends_with($filename, '.docx')) {
		return lf_pci_extract_text_from_docx($path);
	}

	return (string) file_get_contents($path);
}

/**
 * Jobs for single or bulk template downloads.
 *
 * @return list<array{key: string, post_id: int, filename: string, zip_path: string}>
 */
function lf_pci_collect_downloadable_template_jobs(): array {
	$jobs = [];
	$used_names = [];
	$groups = function_exists('lf_pci_writer_template_groups') ? lf_pci_writer_template_groups() : [];

	foreach ($groups as $group) {
		foreach ((array) ($group['items'] ?? []) as $item) {
			$key = sanitize_title((string) ($item['key'] ?? ''));
			if ($key === '' || in_array($key, ['service', 'service-area'], true)) {
				continue;
			}
			$filename = sanitize_file_name($key . '-content-template.docx');
			$zip_path = 'site-pages/' . $filename;
			$jobs[] = [
				'key' => $key,
				'post_id' => 0,
				'filename' => $filename,
				'zip_path' => $zip_path,
			];
			$used_names[ $zip_path ] = true;
		}
	}

	foreach ($groups as $group_id => $group) {
		foreach ((array) ($group['items'] ?? []) as $item) {
			$key = sanitize_title((string) ($item['key'] ?? ''));
			if (!in_array($key, ['service', 'service-area'], true)) {
				continue;
			}
			$filename = sanitize_file_name($key . '-content-template.docx');
			$zip_path = $group_id . '/' . $filename;
			if (!isset($used_names[ $zip_path ])) {
				$jobs[] = [
					'key' => $key,
					'post_id' => 0,
					'filename' => $filename,
					'zip_path' => $zip_path,
				];
				$used_names[ $zip_path ] = true;
			}
		}
	}

	$service_posts = get_posts([
		'post_type' => 'lf_service',
		'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
		'posts_per_page' => 500,
		'orderby' => 'title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	foreach ($service_posts as $post) {
		if (!$post instanceof \WP_Post || $post->post_name === '') {
			continue;
		}
		$filename = sanitize_file_name($post->post_name . '-content-template.docx');
		$zip_path = 'services/' . $filename;
		$jobs[] = [
			'key' => 'service',
			'post_id' => (int) $post->ID,
			'filename' => $filename,
			'zip_path' => $zip_path,
		];
	}

	$area_posts = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
		'posts_per_page' => 500,
		'orderby' => 'title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	foreach ($area_posts as $post) {
		if (!$post instanceof \WP_Post || $post->post_name === '') {
			continue;
		}
		$filename = sanitize_file_name($post->post_name . '-content-template.docx');
		$zip_path = 'service-areas/' . $filename;
		$jobs[] = [
			'key' => 'service-area',
			'post_id' => (int) $post->ID,
			'filename' => $filename,
			'zip_path' => $zip_path,
		];
	}

	return $jobs;
}

/**
 * Build a ZIP archive of every downloadable writer template.
 */
function lf_pci_build_templates_zip_bytes(): string {
	if (!class_exists('ZipArchive')) {
		return '';
	}
	$jobs = lf_pci_collect_downloadable_template_jobs();
	if ($jobs === []) {
		return '';
	}
	$tmp = wp_tempnam('pci-all-templates.zip');
	if ($tmp === '') {
		return '';
	}
	$zip = new ZipArchive();
	if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
		@unlink($tmp);
		return '';
	}
	$readme = implode("\n", [
		'LeadsForward writer templates',
		'',
		'1. Fill each .docx (Google Docs: upload, edit, then File → Download → Microsoft Word).',
		'2. Keep every === SECTION === header and Key: line exactly as written.',
		'3. Bulk-upload finished .docx files at LeadsForward → Import Page Content.',
		'',
		'Folders:',
		'- site-pages/ — Homepage, About, Contact, etc.',
		'- services/ — one file per service CPT post',
		'- service-areas/ — one file per service area CPT post',
	]);
	$zip->addFromString('README.txt', $readme);

	foreach ($jobs as $job) {
		$post_id = (int) ($job['post_id'] ?? 0);
		$key = (string) ($job['key'] ?? '');
		$body = lf_pci_template_for_slug($key, true, $post_id > 0 ? $post_id : null);
		if ($body === '') {
			continue;
		}
		$bytes = lf_pci_build_docx_bytes($body);
		if ($bytes === '') {
			continue;
		}
		$zip->addFromString((string) ($job['zip_path'] ?? $job['filename'] ?? 'template.docx'), $bytes);
	}
	$zip->close();
	$out = (string) file_get_contents($tmp);
	@unlink($tmp);

	return $out;
}
