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

require __DIR__ . '/../inc/fleet-controller.php';

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
		exit(1);
	}
}

expect(lf_fleet_controller_should_bypass_rollout('off', ['override' => true]) === false, 'override blocked when off');
expect(lf_fleet_controller_should_bypass_rollout('selected', ['override' => true]) === true, 'override allowed when selected');
expect(lf_fleet_controller_should_bypass_rollout('tag', ['override' => false]) === false, 'no override no bypass');
