<?php
declare(strict_types=1);

/**
 * Do not define ABSPATH here so push-endpoint.php exits after defining CLI-testable helpers
 * (same pattern as tests/fleet-push-response.php).
 */
require __DIR__ . '/../inc/fleet-updates/push-endpoint.php';

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
		exit(1);
	}
}

$res = lf_fleet_push_build_update_response('0.1.0', '0.1.1', ['version' => '0.1.1'], '');
expect($res['ok'] === true, 'updated ok');
expect($res['message'] === 'updated', 'updated message');

$res = lf_fleet_push_build_update_response('0.1.0', '0.1.0', ['version' => '0.1.1'], '');
expect($res['ok'] === false, 'failed ok false');
expect($res['error_code'] === 'install_failed', 'error_code install_failed');

$res = lf_fleet_push_build_update_response('0.1.0', '0.1.0', ['version' => '0.1.1'], 'Disk full');
expect($res['message'] === 'Disk full', 'error message propagated');
