<?php
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}
require __DIR__ . '/../inc/fleet-updates/push-context.php';

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
		exit(1);
	}
}

expect(lf_fleet_should_run_auto_update(true, false, false) === true, 'cron allows');
expect(lf_fleet_should_run_auto_update(false, true, false) === true, 'admin allows');
expect(lf_fleet_should_run_auto_update(false, false, true) === true, 'signed push allows');
expect(lf_fleet_should_run_auto_update(false, false, false) === false, 'no context blocks');
