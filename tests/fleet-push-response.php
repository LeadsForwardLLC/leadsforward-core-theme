<?php
require __DIR__ . '/../inc/fleet-updates/push-endpoint.php';

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
		exit(1);
	}
}

$res = lf_fleet_push_build_response(false, 'no_update_available', '');
expect($res['ok'] === false, 'ok false');
expect($res['message'] === 'no_update_available', 'message set');
expect($res['updated_to'] === '', 'updated_to empty');
