<?php
/**
 * CLI tests: lf_sections_sanitize_settings for service intro IDs + service details checklists.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}
if (!defined('LF_THEME_DIR')) {
	define('LF_THEME_DIR', dirname(__DIR__));
}
if (!defined('LF_THEME_URI')) {
	define('LF_THEME_URI', 'https://example.test');
}

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
	}
}
if (!function_exists('add_filter')) {
	function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool {
		return true;
	}
}
if (!function_exists('__')) {
	function __(string $text, ?string $domain = null): string {
		return $text;
	}
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field(string $str): string {
		return trim(strip_tags($str));
	}
}
if (!function_exists('sanitize_textarea_field')) {
	function sanitize_textarea_field(string $str): string {
		return trim($str);
	}
}
if (!function_exists('wp_unslash')) {
	function wp_unslash($value) {
		return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value);
	}
}
if (!function_exists('wp_kses_post')) {
	function wp_kses_post(string $html): string {
		return $html;
	}
}
if (!function_exists('wp_kses')) {
	function wp_kses(string $html, array $allowed_html, array $allowed_protocols = []): string {
		return $html;
	}
}
if (!function_exists('wp_kses_allowed_html')) {
	function wp_kses_allowed_html(string $context = ''): array {
		return [];
	}
}
if (!function_exists('esc_url_raw')) {
	function esc_url_raw(string $url): string {
		return $url;
	}
}
if (!function_exists('absint')) {
	function absint($value): int {
		return (int) abs((int) $value);
	}
}

require __DIR__ . '/../inc/sections.php';

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(function_exists('lf_sections_sanitize_settings'), 'lf_sections_sanitize_settings exists');

$intro = lf_sections_sanitize_settings('service_intro', [
	'section_heading' => 'H',
	'section_intro' => 'Intro',
	'service_intro_service_ids' => '',
]);
expect(array_key_exists('service_intro_service_ids', $intro), 'service_intro_service_ids key preserved');
expect($intro['service_intro_service_ids'] === '', 'empty service_intro_service_ids stays empty');

$details = lf_sections_sanitize_settings('service_details', [
	'section_heading' => 'S',
	'section_intro' => '',
	'service_details_body' => 'Body',
	'service_details_checklist' => "One\nTwo\nThree\nFour\nFive",
	'service_details_checklist_secondary' => "A\nB",
]);
$lines = preg_split("/\r\n|\r|\n/", (string) ($details['service_details_checklist'] ?? ''));
$lines = array_values(array_filter(array_map('trim', $lines), static fn(string $l): bool => $l !== ''));
expect(count($lines) === 5, 'all five primary checklist lines survive sanitize');

$detailsSix = lf_sections_sanitize_settings('service_details', [
	'section_heading' => 'S',
	'section_intro' => '',
	'service_details_body' => 'Body',
	'service_details_checklist' => "One\nTwo\nThree\nFour\nFive\nSix",
]);
$lines6 = preg_split("/\r\n|\r|\n/", (string) ($detailsSix['service_details_checklist'] ?? ''));
$lines6 = array_values(array_filter(array_map('trim', $lines6), static fn(string $l): bool => $l !== ''));
expect(count($lines6) === 5, 'sixth checklist line is capped at five');

fwrite(STDOUT, "PASS\n");
