<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}
if (!function_exists('add_filter')) {
	function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true {
		return true;
	}
}
if (!function_exists('add_action')) {
	function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true {
		return true;
	}
}
if (!function_exists('wp_parse_url')) {
	function wp_parse_url(string $url, int $component = -1) {
		return parse_url($url, $component);
	}
}
if (!function_exists('wp_json_encode')) {
	function wp_json_encode(mixed $data, int $flags = 0, int $depth = 512): string|false {
		return json_encode($data, $flags, $depth);
	}
}
if (!function_exists('wp_rand')) {
	function wp_rand(): int {
		return 42;
	}
}

require __DIR__ . '/../inc/fleet-updates/http.php';
require __DIR__ . '/../inc/fleet-controller.php';

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
		exit(1);
	}
}

$req = lf_fleet_controller_build_push_request('https://example.com', 'site-1', 'tok');
expect(strpos($req['url'], '/wp-json/lf/v1/fleet/push') !== false, 'url uses push endpoint');
expect(isset($req['args']['headers']['X-LF-Signature']), 'signature set');
