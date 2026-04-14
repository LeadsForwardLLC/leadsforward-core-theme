<?php
$helper = __DIR__ . '/../inc/fleet-controller-helpers.php';

function expect($cond, $msg) {
	if (!$cond) {
		fwrite(STDERR, "FAIL: " . $msg . PHP_EOL);
		exit(1);
	}
}

if (!file_exists($helper)) {
	expect(false, 'missing fleet-controller-helpers.php');
}

require $helper;

expect(function_exists('lf_fleet_controller_build_download_url'), 'missing lf_fleet_controller_build_download_url()');

$base = 'https://theme.leadsforward.com';
$site_id = 'site-123';
$token = 'token-abc';
$ts = 1710000000;
$nonce = 'abc123';
$sig = 'sig==';

$url = lf_fleet_controller_build_download_url($base, $site_id, $token, $ts, $nonce, $sig);
expect(strpos($url, '/index.php?') !== false, 'uses index.php base');
expect(strpos($url, 'lf_fleet_api=1') !== false, 'includes lf_fleet_api param');
expect(strpos($url, 'lf_fleet_route=updates_package') !== false, 'includes updates_package route');
expect(strpos($url, 'site_id=site-123') !== false, 'includes site_id');
expect(strpos($url, 't=token-abc') !== false, 'includes token');
expect(strpos($url, 'ts=1710000000') !== false, 'includes ts');
expect(strpos($url, 'nonce=abc123') !== false, 'includes nonce');
expect(strpos($url, 'sig=sig%3D%3D') !== false, 'includes sig');

$url_no_sig = lf_fleet_controller_build_download_url($base, $site_id, $token);
expect(strpos($url_no_sig, 'sig=') === false, 'omits signature params when missing');
