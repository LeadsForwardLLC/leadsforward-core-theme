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

$override = lf_fleet_controller_override_allowed(['override' => true]);
expect($override === true, 'override true allowed');
$override = lf_fleet_controller_override_allowed(['override' => false]);
expect($override === false, 'override false not allowed');
