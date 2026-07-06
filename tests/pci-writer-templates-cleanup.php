<?php
/**
 * Smoke test: writer .docx templates exclude theme-controlled sections.
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

$home = (string) file_get_contents(LF_THEME_DIR . '/docs/templates/home-content-template.txt');
expect(strpos($home, '=== TRUST BAR ===') === false, 'home template has no trust bar block');
expect(strpos($home, 'Proof card title') === false, 'home template has no proof card title');
expect(strpos($home, 'Proof bullets') === false, 'home template has no proof bullets');
expect(strpos($home, 'Primary CTA:') === false, 'home template has no primary CTA label');
expect(strpos($home, 'Secondary CTA:') === false, 'home template has no secondary CTA label');
expect(strpos($home, 'Chip bullets:') !== false, 'home template keeps chip bullets');

$service = (string) file_get_contents(LF_THEME_DIR . '/docs/templates/service-content-template.txt');
expect(strpos($service, '=== TRUST BAR ===') === false, 'service template has no trust bar block');

$service_area = (string) file_get_contents(LF_THEME_DIR . '/docs/templates/service-area-content-template.txt');
expect(strpos($service_area, '=== TRUST BAR ===') === false, 'service area template has no trust bar block');

$generated_home = lf_pci_section_doc_templates('home');
expect(strpos($generated_home['hero'], 'Proof card title') === false, 'generated home hero has no proof card');
expect(strpos($generated_home['hero'], 'Primary CTA:') === false, 'generated home hero has no CTA labels');
expect(!isset($generated_home['trust_bar']), 'generated templates omit trust bar block');
expect(strpos($generated_home['cta'], 'Primary CTA:') === false, 'generated CTA has no button labels');

$home_schema = lf_pci_schema_for_slug('home');
expect($home_schema !== null, 'home schema exists');
expect(in_array('trust_bar', $home_schema['locked'] ?? [], true), 'trust_bar locked in home schema');

$doc = "=== PAGE ===\nSlug: home\n=== TRUST BAR ===\nHeading: Bad\nBadges:\n- One\n=== HERO ===\nHeadline: Test Hero\n";
$parsed = lf_pci_parse_with_schema($doc, $home_schema);
expect(empty($parsed['sections']['trust_bar'] ?? null), 'trust bar ignored on import');
expect(($parsed['sections']['hero']['hero_headline'] ?? '') === 'Test Hero', 'hero still imports when trust bar present in doc');

$prompt = lf_pci_ai_prompt_body();
expect(strpos($prompt, '=== TRUST BAR ===') !== false, 'AI prompt documents trust bar exclusion');
expect(strpos($prompt, 'Primary CTA / Secondary CTA') !== false, 'AI prompt documents CTA label exclusion');

fwrite(STDOUT, "OK: pci-writer-templates-cleanup\n");
