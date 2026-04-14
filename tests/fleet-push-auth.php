<?php
require __DIR__ . '/../inc/fleet-updates/push-auth.php';

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
		exit(1);
	}
}

$token = 'tok-123';
$path = '/wp-json/lf/v1/fleet/push';
$body = json_encode(['action' => 'check_install', 'override' => false]);
$bodySha = hash('sha256', $body);
$ts = 1710000000;
$nonce = 'nonce-1';
$sig = lf_fleet_push_expected_sig($token, 'POST', $path, $ts, $nonce, $bodySha);

$nonce_store = [];
$seen = static function (string $key) use (&$nonce_store): bool {
	return isset($nonce_store[$key]);
};
$store = static function (string $key) use (&$nonce_store): void {
	$nonce_store[$key] = true;
};

$headers = [
	'X-LF-Site' => 'site-123',
	'X-LF-Timestamp' => (string) $ts,
	'X-LF-Nonce' => $nonce,
	'X-LF-Signature' => $sig,
];

$ok = lf_fleet_push_validate_request($headers, $body, $path, 'site-123', $token, $ts + 1, $seen, $store);
expect($ok['ok'] === true, 'valid request should pass');

$replay = lf_fleet_push_validate_request($headers, $body, $path, 'site-123', $token, $ts + 1, $seen, $store);
expect($replay['error'] === 'replay', 'replay should fail');

$expired = lf_fleet_push_validate_request($headers, $body, $path, 'site-123', $token, $ts + 400, $seen, $store);
expect($expired['error'] === 'expired', 'expired should fail');

$bad = [
	'X-LF-Site' => 'site-123',
	'X-LF-Timestamp' => (string) $ts,
	'X-LF-Nonce' => 'nonce-fresh',
	'X-LF-Signature' => 'bad',
];
$bad_sig = lf_fleet_push_validate_request($bad, $body, $path, 'site-123', $token, $ts + 1, $seen, $store);
expect($bad_sig['error'] === 'bad_sig', 'bad signature should fail');
