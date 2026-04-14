<?php
/**
 * CLI tests for lf_ai_ajax_update_header_settings (admin-ajax handler).
 *
 * Stubs WordPress so inc/ai-editing/admin-ui.php can load; exercises nonce, capability,
 * and option writes via lf_update_global_option_value.
 */

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}

final class LfTestAjaxExit extends RuntimeException {
}

$GLOBALS['lf_test_ajax_response'] = null;
$GLOBALS['lf_test_options_written'] = [];
$GLOBALS['lf_test_current_user_can'] = true;
$GLOBALS['lf_test_ajax_referer_ok'] = true;

if (!function_exists('add_action')) {
	function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void {
	}
}
if (!function_exists('add_filter')) {
	function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool {
		return true;
	}
}
if (!function_exists('sanitize_key')) {
	function sanitize_key(string $key): string {
		return strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', $key));
	}
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field(string $str): string {
		$str = strip_tags($str);
		return trim(preg_replace('/\s+/u', ' ', $str));
	}
}
if (!function_exists('wp_unslash')) {
	function wp_unslash($value) {
		return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value);
	}
}
if (!function_exists('__')) {
	function __(string $text, ?string $domain = null): string {
		return $text;
	}
}
if (!function_exists('check_ajax_referer')) {
	function check_ajax_referer(string $action = '-1', string|false $query_arg = false, bool $stop = true): int|false {
		if (empty($GLOBALS['lf_test_ajax_referer_ok'])) {
			throw new LfTestAjaxExit('bad_nonce');
		}
		return 1;
	}
}
if (!function_exists('current_user_can')) {
	function current_user_can(string $capability, ...$args): bool {
		return !empty($GLOBALS['lf_test_current_user_can']);
	}
}
if (!function_exists('wp_send_json_success')) {
	function wp_send_json_success($data = null): void {
		$GLOBALS['lf_test_ajax_response'] = ['success' => true, 'data' => $data];
		throw new LfTestAjaxExit('success');
	}
}
if (!function_exists('wp_send_json_error')) {
	function wp_send_json_error($data = null, ?int $status_code = null): void {
		$GLOBALS['lf_test_ajax_response'] = ['success' => false, 'data' => $data];
		throw new LfTestAjaxExit('error');
	}
}

if (!function_exists('lf_update_global_option_value')) {
	function lf_update_global_option_value(string $key, string $value): void {
		$GLOBALS['lf_test_options_written'][$key] = $value;
	}
}

require __DIR__ . '/../inc/header-settings.php';
require __DIR__ . '/../inc/ai-editing/admin-ui.php';

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

function run_handler(): void {
	$GLOBALS['lf_test_ajax_response'] = null;
	try {
		lf_ai_ajax_update_header_settings();
	} catch (LfTestAjaxExit $e) {
	}
}

// Deny when nonce check fails.
$GLOBALS['lf_test_ajax_referer_ok'] = false;
$_POST = ['nonce' => 'bad'];
$GLOBALS['lf_test_current_user_can'] = true;
try {
	lf_ai_ajax_update_header_settings();
	expect(false, 'expected exception on bad nonce');
} catch (LfTestAjaxExit $e) {
	expect($e->getMessage() === 'bad_nonce', 'bad nonce should throw from check_ajax_referer');
}
$GLOBALS['lf_test_ajax_referer_ok'] = true;

// Deny without capability.
$GLOBALS['lf_test_current_user_can'] = false;
$_POST = [
	'nonce' => '1',
	'header_layout' => 'centered',
	'header_topbar_enabled' => '1',
	'header_topbar_text' => 'Hello',
];
$GLOBALS['lf_test_options_written'] = [];
run_handler();
expect(
	is_array($GLOBALS['lf_test_ajax_response']) && empty($GLOBALS['lf_test_ajax_response']['success']),
	'permission denied should send json error'
);
$msg = $GLOBALS['lf_test_ajax_response']['data']['message'] ?? '';
expect(str_contains((string) $msg, 'Permission'), 'error message mentions permission');

// Happy path: writes sanitized options.
$GLOBALS['lf_test_current_user_can'] = true;
$_POST = [
	'nonce' => '1',
	'header_layout' => 'centered',
	'header_topbar_enabled' => '1',
	'header_topbar_text' => '  Promo <b>x</b>  ',
];
$GLOBALS['lf_test_options_written'] = [];
run_handler();
expect(!empty($GLOBALS['lf_test_ajax_response']['success']), 'success response');
$data = $GLOBALS['lf_test_ajax_response']['data'] ?? [];
expect(($data['header_layout'] ?? '') === 'centered', 'response layout');
expect(($data['header_topbar_enabled'] ?? null) === true, 'response topbar enabled bool');
expect(($data['header_topbar_text'] ?? '') === 'Promo x', 'topbar text sanitized');
expect(($GLOBALS['lf_test_options_written']['lf_header_layout'] ?? '') === 'centered', 'option layout');
expect(($GLOBALS['lf_test_options_written']['lf_header_topbar_enabled'] ?? '') === '1', 'option topbar flag');
expect(($GLOBALS['lf_test_options_written']['lf_header_topbar_text'] ?? '') === 'Promo x', 'option topbar text');

// Invalid layout slug falls back to modern.
$_POST['header_layout'] = 'hax';
$_POST['header_topbar_enabled'] = '0';
$_POST['header_topbar_text'] = 'Plain';
$GLOBALS['lf_test_options_written'] = [];
run_handler();
$data = $GLOBALS['lf_test_ajax_response']['data'] ?? [];
expect(($data['header_layout'] ?? '') === 'modern', 'invalid layout becomes modern');
expect($data['header_topbar_enabled'] === false, 'topbar off');
expect(($GLOBALS['lf_test_options_written']['lf_header_layout'] ?? '') === 'modern', 'stored modern');

fwrite(STDOUT, "PASS: header-settings-save\n");
