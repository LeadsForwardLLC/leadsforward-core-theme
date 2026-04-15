<?php
/**
 * CLI test: lf_ai_icon_markup_for_slug() from AI editing admin UI.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}

if (!function_exists('add_action')) {
	function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
	}
}

if (!function_exists('sanitize_title')) {
	function sanitize_title(string $title): string {
		$t = strtolower($title);
		return (string) preg_replace('/[^a-z0-9\-]+/', '-', $t);
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key(string $key): string {
		$key = strtolower($key);
		return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
	}
}

if (!function_exists('lf_icon')) {
	function lf_icon(string $slug, array $args = []): string {
		return $slug === 'map-pin' ? '<svg class="lf-icon"></svg>' : '';
	}
}

require __DIR__ . '/../inc/ai-editing/admin-ui.php';

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

expect(function_exists('lf_ai_icon_markup_for_slug'), 'helper exists');
expect(lf_ai_icon_markup_for_slug('map-pin') !== '', 'icon markup returned');

fwrite(STDOUT, "PASS\n");
