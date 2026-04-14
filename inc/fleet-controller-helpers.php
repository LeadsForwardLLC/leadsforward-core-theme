<?php
declare(strict_types=1);

/**
 * Build a package download URL without relying on rewrite rules.
 */
function lf_fleet_controller_build_download_url(
	string $base,
	string $site_id,
	string $token,
	int $ts = 0,
	string $nonce = '',
	string $sig = ''
): string {
	$base = rtrim($base, '/');
	$args = [
		'lf_fleet_api' => '1',
		'lf_fleet_route' => 'updates_package',
		'site_id' => $site_id,
		't' => $token,
	];
	if ($sig !== '' && $ts > 0 && $nonce !== '') {
		$args['ts'] = (string) $ts;
		$args['nonce'] = $nonce;
		$args['sig'] = $sig;
	}
	return $base . '/index.php?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986);
}
