<?php
/**
 * Smoke test: homepage section mapping (MENTOR, hero pills, service intro cards).
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

$GLOBALS['lf_pci_test_services'] = [
	'basement-wall-repair' => 101,
	'basement-waterproofing' => 102,
];

if (!function_exists('get_posts')) {
	function get_posts($args) {
		$name = $args['name'] ?? '';
		$id = $GLOBALS['lf_pci_test_services'][$name] ?? 0;
		return $id > 0 ? [$id] : [];
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

$home_schema = lf_pci_schema_for_slug('home');
expect($home_schema !== null, 'home schema exists');

$doc = <<<'DOC'
=== PAGE ===
Slug: home
Name: Homepage

=== HERO ===
Headline: Foundation Repair in Ann Arbor
Subheadline: Clear inspections and honest repair plans.
Eyebrow: Ann Arbor basement and foundation repair
Chip bullets: Local inspections | Structural repair options | Waterproofing and sump pump help Proof card title: Why homeowners choose us
Proof card title: Why homeowners choose us
Proof bullets:
- Local foundation specialists
- Clear inspection process
Primary CTA: Get a Free Inspection
Secondary CTA: See Our Work

=== SERVICE DETAILS ===
Heading: Foundation Problems Don't Fix Themselves
Intro: Small warning signs can turn into expensive structural issues.
Checklist:
- Cracks in walls or brick
- Uneven or sloping floors

=== MENTOR ===
Heading: We Help Homeowners Fix the Problem Without the Guesswork
Intro: Foundation issues are stressful because most homeowners don't know how serious the damage is.
Checklist:
- Local foundation specialists
- Clear inspection process
- Workmanship warranty

=== SERVICES ===
Heading: Foundation Repair Services Built for Long-Term Stability
Intro: Engineered solutions for settlement and structural movement.
View all label: View all services
Cards:
basement-wall-repair | Short overview of basement wall repair and what to expect.
basement-waterproofing | Waterproofing scoped clearly before work starts.

=== PROJECT GALLERY ===
Heading: Real Repairs. Real Homes. Real Results.
Intro: See how we've helped homeowners across Ann Arbor.

=== PRICING ===
Heading: Foundation Repair Is a Big Decision. We Make It Easier.
Intro: Every home is different, which is why we start with a clear inspection.
Financing text: Financing options may be available for qualified homeowners.
CTA: Ask About Financing

=== BENEFITS ===
Heading: Why Homeowners Choose Us
Intro: Calm, clear guidance from inspection through warranty.
Items:
Engineered solutions || Piers and anchors sized to your soil.
Plain-language inspections || Photos and options explained clearly.

=== CTA ===
Headline: Worried About Your Foundation?
Subheadline: Schedule a free inspection today.
Primary CTA: Get a Free Inspection
Secondary CTA: Call Now
DOC;

$parsed = lf_pci_parse_with_schema($doc, $home_schema);

$chips = (string) ($parsed['sections']['hero']['hero_chip_bullets'] ?? '');
expect(substr_count($chips, "\n") >= 2, 'hero chips split into multiple lines');
expect(strpos($chips, 'Local inspections') !== false, 'first hero chip present');
expect(strpos($chips, 'Structural repair options') !== false, 'second hero chip present');
expect(strpos($chips, 'Waterproofing and sump pump help') !== false, 'third hero chip present');
expect(strpos($chips, 'Proof card') === false, 'proof card label stripped from chips');
expect(strpos($chips, '||') !== false, 'hero chips include icon delimiter');

$mentor = $parsed['sections']['image_content_b'] ?? [];
expect(($mentor['section_heading'] ?? '') === 'We Help Homeowners Fix the Problem Without the Guesswork', 'MENTOR maps to image_content_b heading');
expect(strpos((string) ($mentor['section_intro'] ?? ''), 'Foundation issues are stressful') !== false, 'MENTOR intro imported');
expect(strpos((string) ($mentor['service_details_checklist'] ?? ''), 'Workmanship warranty') !== false, 'MENTOR checklist imported');

$cards = (string) ($parsed['sections']['service_intro']['service_intro_card_desc_overrides'] ?? '');
expect(strpos($cards, '101|') !== false, 'service card override resolved slug to ID 101');
expect(strpos($cards, '102|') !== false, 'service card override resolved slug to ID 102');
expect(strpos($cards, 'basement-wall-repair') === false, 'overrides stored as numeric IDs');

$gallery = $parsed['sections']['project_gallery'] ?? [];
expect(($gallery['section_heading'] ?? '') === 'Real Repairs. Real Homes. Real Results.', 'project gallery heading imported');

$pricing = $parsed['sections']['pricing'] ?? [];
expect(strpos((string) ($pricing['section_heading'] ?? ''), 'Big Decision') !== false, 'pricing heading imported');
expect(($pricing['pricing_cta_text'] ?? '') === 'Ask About Financing', 'pricing CTA imported');
expect(($pricing['financing_enabled'] ?? '') === '1', 'financing enabled when text present');

$merged = lf_pci_apply_preserved_keys(
	['service_intro_card_desc_overrides' => '99|Imported copy'],
	'service_intro',
	$home_schema,
	['service_intro' => ['service_intro_card_desc_overrides' => '88|Old copy']]
);
expect(strpos((string) ($merged['service_intro_card_desc_overrides'] ?? ''), '99|') !== false, 'imported card overrides win over preserved');

$merged_empty = lf_pci_apply_preserved_keys(
	['service_intro_card_desc_overrides' => ''],
	'service_intro',
	array_merge($home_schema, ['preserve_keys' => ['service_intro' => ['service_intro_card_desc_overrides']]]),
	['service_intro' => ['service_intro_card_desc_overrides' => '88|Old copy']]
);
expect(strpos((string) ($merged_empty['service_intro_card_desc_overrides'] ?? ''), '88|') !== false, 'empty import keeps preserved overrides');

fwrite(STDOUT, "OK: pci-homepage-sections\n");
