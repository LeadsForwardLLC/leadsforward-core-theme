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

/**
 * About Us page template schema (section order + field keys).
 *
 * @return array{slug: string, label: string, order: list<string>, section_aliases: array<string, string>}
 */
function lf_pci_about_us_schema(): array {
	return [
		'slug' => 'about-us',
		'label' => __('About Us', 'leadsforward-core'),
		'order' => ['hero', 'content_image', 'benefits', 'image_content', 'process', 'faq_accordion', 'cta'],
		'section_aliases' => [
			'hero' => 'hero',
			'hero section' => 'hero',
			'story' => 'content_image',
			'company intro' => 'content_image',
			'company story' => 'content_image',
			'content image' => 'content_image',
			'content_image' => 'content_image',
			'intro' => 'content_image',
			'benefits' => 'benefits',
			'why choose us' => 'benefits',
			'team' => 'image_content',
			'our team' => 'image_content',
			'image content' => 'image_content',
			'image_content' => 'image_content',
			'process' => 'process',
			'our process' => 'process',
			'faq' => 'faq_accordion',
			'faqs' => 'faq_accordion',
			'faq accordion' => 'faq_accordion',
			'faq_accordion' => 'faq_accordion',
			'cta' => 'cta',
			'call to action' => 'cta',
			'seo' => 'seo',
			'meta' => 'seo',
		],
	];
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
 * Normalize pasted doc text (Google Docs, markdown headings, etc.).
 */
function lf_pci_normalize_raw(string $raw): string {
	$raw = str_replace(["\r\n", "\r"], "\n", $raw);
	$raw = preg_replace('/\x{00A0}/u', ' ', $raw) ?? $raw;
	// Markdown ## Section → === SECTION ===
	$raw = preg_replace_callback('/^#{1,3}\s+(.+?)\s*$/m', static function (array $m): string {
		return '=== ' . strtoupper(trim($m[1])) . ' ===';
	}, $raw) ?? $raw;
	return trim($raw);
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
 * Parse About Us doc into section settings + CPT payloads.
 *
 * @return array{
 *   sections: array<string, array<string, mixed>>,
 *   process_steps: list<array{title:string,body:string}>,
 *   faqs: list<array{question:string,answer:string}>,
 *   seo: array{title: string, description: string},
 *   warnings: list<string>,
 *   errors: list<string>,
 *   found_sections: list<string>
 * }
 */
function lf_pci_parse_about_us(string $raw): array {
	$schema = lf_pci_about_us_schema();
	$raw = lf_pci_normalize_raw($raw);
	$split = lf_pci_split_sections($raw, $schema['section_aliases']);
	$warnings = [];
	$errors = [];
	$sections = [];
	$process_steps = [];
	$faqs = [];
	$seo = ['title' => '', 'description' => ''];

	if ($split === []) {
		$errors[] = __('No sections found. Use headings like === HERO ===, === STORY ===, etc.', 'leadsforward-core');
		return [
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
		$f = lf_pci_parse_fields($split['hero'], ['subheadline']);
		$sections['hero'] = array_filter([
			'variant' => 'internal',
			'hero_headline' => $f['headline'] ?? $f['hero_headline'] ?? '',
			'hero_subheadline' => $f['subheadline'] ?? $f['hero_subheadline'] ?? '',
			'hero_eyebrow_text' => $f['eyebrow'] ?? $f['eyebrow_text'] ?? $f['trust_badge'] ?? '',
		]);
	}

	// Story / content_image
	if (!empty($split['content_image'])) {
		$f = lf_pci_parse_fields($split['content_image'], ['body', 'intro']);
		$checklist_raw = $f['checklist'] ?? '';
		if ($checklist_raw === '' && preg_match('/Checklist:\s*([\s\S]+)/i', $split['content_image'], $m)) {
			$checklist_raw = trim($m[1]);
		}
		$checklist = lf_pci_parse_bullet_list($checklist_raw);
		$sections['content_image'] = array_filter([
			'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
			'section_intro' => $f['intro'] ?? $f['section_intro'] ?? '',
			'service_details_body' => $f['body'] ?? $f['section_body'] ?? '',
			'service_details_checklist' => $checklist !== [] ? implode("\n", $checklist) : '',
			'service_details_media_mode' => 'image',
			'content_media_show_checklist' => $checklist !== [] ? '1' : '0',
		]);
	}

	// Benefits
	if (!empty($split['benefits'])) {
		$f = lf_pci_parse_fields($split['benefits'], ['intro', 'items']);
		$items_raw = $f['items'] ?? $f['benefits_items'] ?? '';
		if ($items_raw === '') {
			$items_raw = preg_replace('/^.*?(Items|Benefits)\s*:\s*/is', '', $split['benefits']) ?? $split['benefits'];
		}
		$items_lines = array_filter(array_map('trim', explode("\n", trim($items_raw))));
		$sections['benefits'] = array_filter([
			'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
			'section_intro' => $f['intro'] ?? $f['section_intro'] ?? '',
			'benefits_items' => implode("\n", $items_lines),
		]);
	}

	// Team / image_content
	if (!empty($split['image_content'])) {
		$f = lf_pci_parse_fields($split['image_content'], ['body', 'intro']);
		$sections['image_content'] = array_filter([
			'section_heading' => $f['heading'] ?? $f['section_heading'] ?? '',
			'section_intro' => $f['intro'] ?? $f['section_intro'] ?? '',
			'service_details_body' => $f['body'] ?? $f['section_body'] ?? '',
			'service_details_media_mode' => 'image',
			'content_media_show_checklist' => '0',
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
		$f = lf_pci_parse_fields($split['cta'], ['subheadline']);
		$sections['cta'] = array_filter([
			'cta_headline' => $f['headline'] ?? $f['cta_headline'] ?? '',
			'cta_subheadline' => $f['subheadline'] ?? $f['cta_subheadline'] ?? '',
		]);
	}

	// SEO
	if (!empty($split['seo'])) {
		$f = lf_pci_parse_fields($split['seo'], ['description', 'meta_description']);
		$seo = [
			'title' => $f['title'] ?? $f['meta_title'] ?? $f['seo_title'] ?? '',
			'description' => $f['description'] ?? $f['meta_description'] ?? $f['seo_description'] ?? '',
		];
	}

	$required = ['hero', 'content_image', 'benefits', 'cta'];
	foreach ($required as $req) {
		if (empty($sections[$req])) {
			$warnings[] = sprintf(
				/* translators: %s: section name */
				__('Section "%s" is missing or empty — template defaults will be used.', 'leadsforward-core'),
				$req
			);
		}
	}
	if ($process_steps === []) {
		$warnings[] = __('No process steps parsed — existing library steps will be kept if present.', 'leadsforward-core');
	}
	if ($faqs === []) {
		$warnings[] = __('No FAQs parsed — existing library FAQs will be kept if present.', 'leadsforward-core');
	}

	$all_text = $raw;
	if (strpos($all_text, '{business}') === false && strpos($all_text, '{city}') === false) {
		$warnings[] = __('Consider using {business} and {city} tokens for stronger local SEO.', 'leadsforward-core');
	}

	return [
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
 * @param array<string, mixed> $sections
 * @param list<array{title:string,body:string}> $process_steps
 * @param list<array{question:string,answer:string}> $faqs
 * @param array{title: string, description: string} $seo
 * @param array{update_library?: bool, sync_mode?: string} $options
 */
function lf_pci_apply_about_us(int $page_id, array $sections, array $process_steps, array $faqs, array $seo, array $options = []): array {
	$schema = lf_pci_about_us_schema();
	$vars = lf_pci_template_vars();
	$result = ['page_id' => $page_id, 'process_ids' => [], 'faq_ids' => [], 'sections_updated' => [], 'library_updated' => false];
	$update_library = !array_key_exists('update_library', $options) || !empty($options['update_library']);
	$sync_mode = (string) ($options['sync_mode'] ?? 'force');
	$overwrite = ($sync_mode === 'force');

	// Save process + FAQ blueprint to niche library (tokens preserved) before site-specific fill.
	if ($update_library && ($process_steps !== [] || $faqs !== []) && function_exists('lf_niche_save_about_library_entries')) {
		$niche_slug = (string) get_option('lf_homepage_niche_slug', 'foundation-repair');
		$lib_process = [];
		foreach ($process_steps as $row) {
			if (!is_array($row)) {
				continue;
			}
			$lib_process[] = [
				'title' => sanitize_text_field((string) ($row['title'] ?? '')),
				'body' => sanitize_textarea_field((string) ($row['body'] ?? '')),
			];
		}
		$lib_faqs = [];
		foreach ($faqs as $row) {
			if (!is_array($row)) {
				continue;
			}
			$lib_faqs[] = [
				'question' => sanitize_text_field((string) ($row['question'] ?? '')),
				'answer' => wp_kses_post((string) ($row['answer'] ?? '')),
			];
		}
		if ($lib_process !== [] || $lib_faqs !== []) {
			$result['library_updated'] = lf_niche_save_about_library_entries($niche_slug, $lib_process, $lib_faqs);
		}
	}

	// Fill tokens in all string fields.
	foreach ($sections as $type => $settings) {
		foreach ($settings as $key => $val) {
			if (is_string($val)) {
				$sections[$type][$key] = lf_pci_fill_tokens($val, $vars);
			}
		}
	}
	$seo['title'] = lf_pci_fill_tokens((string) ($seo['title'] ?? ''), $vars);
	$seo['description'] = lf_pci_fill_tokens((string) ($seo['description'] ?? ''), $vars);

	$process_group = defined('LF_NICHE_ABOUT_PROCESS_GROUP') ? LF_NICHE_ABOUT_PROCESS_GROUP : 'about-company';
	$faq_context = defined('LF_NICHE_ABOUT_FAQ_CONTEXT') ? LF_NICHE_ABOUT_FAQ_CONTEXT : 'about_company';

	if ($process_steps !== [] && function_exists('lf_niche_upsert_process_steps')) {
		$result['process_ids'] = lf_niche_upsert_process_steps($process_steps, $process_group, $vars, $overwrite);
	}
	if ($faqs !== [] && function_exists('lf_niche_upsert_context_faqs')) {
		$result['faq_ids'] = lf_niche_upsert_context_faqs($faqs, $faq_context, $vars, $overwrite);
	}

	if (!empty($result['process_ids']) && function_exists('lf_niche_ids_to_lines')) {
		$sections['process']['process_selected_ids'] = lf_niche_ids_to_lines($result['process_ids']);
	}
	if (!empty($result['faq_ids']) && function_exists('lf_niche_ids_to_lines')) {
		$sections['faq_accordion']['faq_selected_ids'] = lf_niche_ids_to_lines($result['faq_ids']);
	}

	if (!function_exists('lf_sections_defaults_for') || !function_exists('lf_pb_instance_id')) {
		return array_merge($result, ['error' => __('Page Builder is not available.', 'leadsforward-core')]);
	}

	$pb_sections = [];
	$counts = [];
	foreach ($schema['order'] as $type) {
		$counts[$type] = ($counts[$type] ?? 0) + 1;
		$instance_id = lf_pb_instance_id($type, $counts[$type]);
		$defaults = lf_sections_defaults_for($type);
		if ($type === 'hero') {
			$defaults['variant'] = 'internal';
		}
		$merged = array_merge($defaults, $sections[$type] ?? []);
		if (function_exists('lf_sections_normalize_service_details_settings')) {
			$merged = lf_sections_normalize_service_details_settings($type, $merged);
		}
		$pb_sections[$instance_id] = [
			'type' => $type,
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
 * Resolve About Us page ID (slug about-us).
 */
function lf_pci_get_about_page_id(): int {
	$page = get_page_by_path('about-us');
	return $page instanceof \WP_Post ? (int) $page->ID : 0;
}

/**
 * Blank About Us template for Google Docs / paste workflow.
 */
function lf_pci_about_us_template(): string {
	$path = LF_THEME_DIR . '/docs/templates/about-us-content-template.txt';
	if (is_readable($path)) {
		return (string) file_get_contents($path);
	}
	return lf_pci_about_us_template_fallback();
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

Step: Free structural inspection
Body: A certified evaluator documents cracks, settlement signs, moisture, and drainage patterns. You receive photos, measurements, and a plain-language explanation.

Step: Engineered repair plan
Body: We size piers, wall anchors, or waterproofing to your soil and load conditions. Scope, timeline, and pricing are documented before work begins.

Step: Protected installation
Body: Crews stage materials, protect landscaping, and maintain clean access paths with daily updates.

Step: Final walkthrough & warranty
Body: We review monitoring points, drainage improvements, and transferable warranty coverage.

=== FAQ ===
Heading: About our foundation repair team
Intro: Honest answers about inspections, warranties, crews, and what to expect on your property.

Q: Are you licensed and insured for foundation work?
A: Yes. {business} carries general liability and workers' compensation coverage{city_line}. We provide proof of insurance on request before work begins.

Q: Do you offer free foundation inspections?
A: We provide complimentary structural evaluations for homeowners concerned about cracks, sticking doors, sloping floors, or basement moisture.

Q: What warranty do you provide on foundation repairs?
A: Every project includes written warranty documentation. We explain what is covered, for how long, and what maintenance keeps protection valid.

=== CTA ===
Headline: Schedule a foundation inspection with {business}
Subheadline: Request a free structural inspection and get a clear repair plan.

=== SEO ===
Title: About {business}{city_line}
Description: Meet {business} — local foundation repair specialists{city_line}. Engineered solutions, clear inspections, and crews who protect your home.
TEMPLATE;
}
