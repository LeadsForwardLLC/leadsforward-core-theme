<?php
/**
 * Smoke test: writer AI prompt, field specs, and hint stripping.
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

$prompt = lf_pci_ai_prompt_body(['primary_keyword' => 'foundation repair']);
expect(strpos($prompt, 'FIELD LENGTHS & LIST COUNTS') !== false, 'prompt includes field specs');
expect(strpos($prompt, 'CONTENT QUALITY') !== false, 'prompt includes quality block');
expect(strpos($prompt, 'first major section Heading') !== false, 'prompt includes keyword placement rules');
expect(strpos($prompt, '50–60 characters') !== false, 'prompt includes SEO title length');
expect(strpos($prompt, 'contractor talking to a customer') !== false, 'prompt includes contractor voice');

$user_msg = lf_pci_ai_user_message_sample('Homepage');
expect(strpos($user_msg, 'TEMPLATE START') !== false, 'user message has template placeholder');
expect(strpos($user_msg, 'read the full page aloud') !== false, 'user message includes self-check');

$blank = "=== HERO ===\nHeadline: \nSubheadline: \n";
$hinted = lf_pci_apply_writer_field_hints($blank);
expect(strpos($hinted, '>> H1.') !== false, 'hints added before empty headline');
expect(strpos($hinted, '>> 1–2 sentences') !== false, 'hints added before empty subheadline');

$filled = "=== HERO ===\nHeadline: Real Foundation Repair in Ann Arbor\n";
$hinted_filled = lf_pci_apply_writer_field_hints($filled);
expect(strpos($hinted_filled, '>> H1.') === false, 'no hints before filled headline');

$import_raw = "=== WRITER NOTES ===\nNotes\n=== PAGE ===\nSlug: home\n=== HERO ===\n>> H1. hint\nHeadline: Test\n";
$normalized = lf_pci_normalize_raw($import_raw);
expect(strpos($normalized, '>> H1.') === false, 'hint lines stripped on import');
expect(strpos($normalized, 'Headline: Test') !== false, 'content preserved after hint strip');

fwrite(STDOUT, "OK: pci-writer-prompt\n");
