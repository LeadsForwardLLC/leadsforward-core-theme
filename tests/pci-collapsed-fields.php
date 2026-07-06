<?php
/**
 * Smoke test: collapsed GPT field lines split into separate Key: value pairs.
 */
define('ABSPATH', true);
define('LF_THEME_DIR', dirname(__DIR__));

if (!function_exists('__')) {
	function __($text, $domain = '') {
		return $text;
	}
}
if (!function_exists('sanitize_title')) {
	function sanitize_title($title) {
		return strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $title)), '-'));
	}
}
if (!function_exists('get_option')) {
	function get_option($key, $default = false) {
		return $default;
	}
}

require_once LF_THEME_DIR . '/inc/page-content-importer-schemas.php';
require_once LF_THEME_DIR . '/inc/page-content-importer.php';

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

$collapsed_hero = '=== HERO ===Headline: About Ann Arbor Foundation Repair ExpertsSubheadline: Local foundation repair specialists in Ann Arbor.Eyebrow: Licensed • Insured';
$normalized = lf_pci_normalize_raw($collapsed_hero);
expect(strpos($normalized, "Headline: About Ann Arbor Foundation Repair Experts\n") !== false, 'hero headline on own line');
expect(strpos($normalized, "Subheadline: Local foundation repair specialists") !== false, 'hero subheadline split');
expect(strpos($normalized, 'Subheadline:') === false || strpos($normalized, "Experts\nSubheadline:") !== false, 'no glued ExpertsSubheadline');

$hero_fields = lf_pci_parse_fields($collapsed_hero);
expect(($hero_fields['headline'] ?? '') === 'About Ann Arbor Foundation Repair Experts', 'parsed headline value');
expect(strpos((string) ($hero_fields['subheadline'] ?? ''), 'Subheadline:') === false, 'subheadline not stuffed in headline');
expect(strpos((string) ($hero_fields['headline'] ?? ''), 'Subheadline') === false, 'headline does not contain Subheadline label');

$collapsed_story = '=== STORY ===Heading: Built on trust, not quick fixesIntro: Foundation problems can feel overwhelming.Body: Ann Arbor Foundation Repair Experts gives homeowners a straightforward way.';
$story_fields = lf_pci_parse_fields(lf_pci_split_sections(lf_pci_normalize_raw($collapsed_story), lf_pci_common_section_aliases())['content_image'] ?? '');
if ($story_fields === []) {
	$split = lf_pci_split_sections(lf_pci_normalize_raw($collapsed_story), lf_pci_common_section_aliases());
	$story_fields = lf_pci_parse_fields((string) ($split['content_image'] ?? ''));
}
expect(($story_fields['heading'] ?? '') === 'Built on trust, not quick fixes', 'story heading parsed');
expect(strpos((string) ($story_fields['intro'] ?? ''), 'Intro:') === false, 'intro label not in intro value');
expect(strpos((string) ($story_fields['heading'] ?? ''), 'Intro:') === false, 'intro label not in heading');

$collapsed_benefits = "=== BENEFITS ===Heading: Why homeowners trust usIntro: Structural decisions deserve guidance.Items: Practical repair planning || We match the repair.Clear communication || You get photos.";
$benefits_body = lf_pci_split_sections(lf_pci_normalize_raw($collapsed_benefits), lf_pci_common_section_aliases())['benefits'] ?? '';
$benefits_fields = lf_pci_parse_fields((string) $benefits_body, ['intro', 'items']);
expect(($benefits_fields['heading'] ?? '') === 'Why homeowners trust us', 'benefits heading parsed');
expect(strpos((string) ($benefits_fields['items'] ?? ''), '||') !== false, 'benefits items retain pipe delimiter');
expect(strpos((string) ($benefits_fields['heading'] ?? ''), 'Items:') === false, 'items not in heading');

$about_schema = lf_pci_schema_for_slug('about-us');
expect($about_schema !== null, 'about-us schema exists');
expect(in_array('process', $about_schema['locked'] ?? [], true), 'process locked in schema');
expect(in_array('faq_accordion', $about_schema['locked'] ?? [], true), 'faq locked in schema');

$user_doc = <<<'DOC'
=== HERO ===Headline: About Ann Arbor Foundation Repair ExpertsSubheadline: Local foundation repair specialists in Ann Arbor with clear inspections.Eyebrow: Licensed • Insured • Local Structural Crews
=== STORY ===Heading: Built on trust, not quick fixesIntro: Foundation problems can feel overwhelming.Body: Ann Arbor Foundation Repair Experts gives homeowners a straightforward way.
=== BENEFITS ===Heading: Why homeowners trust us with their foundationIntro: Structural decisions deserve steady guidance.Items: Practical repair planning || We match the repair to the foundation.Clear communication || You get photos, findings, scope details.
=== PROCESS ===Heading: How foundation repair works with usIntro: A documented path.Step: Inspection and measurements || We review the foundation.Step: Repair options || We explain what we found.
=== FAQ ===Heading: Frequently Asked QuestionsIntro: Quick answers.Q: Do you only handle major structural repairs?A: No. We inspect and repair cracks.
=== CTA ===Headline: Schedule a foundation inspectionSubheadline: Request a free inspection.
DOC;

$parsed = lf_pci_parse_with_schema($user_doc, $about_schema);
expect(empty($parsed['sections']['process'] ?? null) || !isset($parsed['sections']['process']['section_heading']), 'process section not imported from doc');
expect(($parsed['sections']['hero']['hero_headline'] ?? '') === 'About Ann Arbor Foundation Repair Experts', 'full doc hero headline');
expect(strpos((string) ($parsed['sections']['hero']['hero_headline'] ?? ''), 'Subheadline') === false, 'full doc hero not stuffed');
expect(strpos((string) ($parsed['sections']['benefits']['section_heading'] ?? ''), 'Intro:') === false, 'benefits heading clean');
expect($parsed['process_steps'] === [], 'process steps ignored from doc');
expect($parsed['faqs'] === [], 'faqs ignored from doc');

fwrite(STDOUT, "OK: pci-collapsed-fields\n");
