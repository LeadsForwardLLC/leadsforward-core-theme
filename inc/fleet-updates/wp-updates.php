<?php
/**
 * Fleet updates: WordPress theme update integration + signature gating.
 *
 * @package LeadsForward_Core
 * @since 0.1.21
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_fleet_release_message(string $themeSlug, string $version, string $sha256): string {
	return $themeSlug . "\n" . $version . "\n" . strtolower($sha256);
}

add_filter('pre_set_site_transient_update_themes', static function ($transient) {
	if (!is_object($transient)) {
		return $transient;
	}
	$offer = get_site_transient(LF_FLEET_OFFER_TRANSIENT);
	if (!is_array($offer) || empty($offer['update'])) {
		return $transient;
	}

	$theme = wp_get_theme();
	$slug = (string) $theme->get_stylesheet();
	if (empty($offer['version']) || empty($offer['download_url']) || empty($offer['sha256']) || empty($offer['signature']) || empty($offer['public_key_id'])) {
		return $transient;
	}

	$installed = (string) $theme->get('Version');
	$offered = (string) $offer['version'];
	if ($installed !== '' && $offered !== '' && version_compare($installed, $offered, '>=')) {
		delete_site_transient(LF_FLEET_OFFER_TRANSIENT);
		return $transient;
	}

	$transient->response[$slug] = [
		'theme' => $slug,
		'new_version' => (string) $offer['version'],
		'url' => 'https://theme.leadsforward.com',
		'package' => (string) $offer['download_url'],
	];
	return $transient;
});

// Ensure the update offer is visible even when WordPress already has a cached `update_themes` transient.
// This makes the update show up under Appearance → Themes and ensures Theme_Upgrader sees the package.
add_filter('site_transient_update_themes', static function ($transient) {
	if (!is_object($transient)) {
		return $transient;
	}
	$offer = get_site_transient(LF_FLEET_OFFER_TRANSIENT);
	if (!is_array($offer) || empty($offer['update'])) {
		return $transient;
	}

	$theme = wp_get_theme();
	$slug = (string) $theme->get_stylesheet();
	if (empty($offer['version']) || empty($offer['download_url']) || empty($offer['sha256']) || empty($offer['signature']) || empty($offer['public_key_id'])) {
		return $transient;
	}

	$installed = (string) $theme->get('Version');
	$offered = (string) $offer['version'];
	if ($installed !== '' && $offered !== '' && version_compare($installed, $offered, '>=')) {
		delete_site_transient(LF_FLEET_OFFER_TRANSIENT);
		return $transient;
	}

	if (!isset($transient->response) || !is_array($transient->response)) {
		$transient->response = [];
	}
	$transient->response[$slug] = [
		'theme' => $slug,
		'new_version' => (string) $offer['version'],
		'url' => 'https://theme.leadsforward.com',
		'package' => (string) $offer['download_url'],
	];
	return $transient;
});

add_filter('upgrader_pre_download', static function ($reply, $package, $upgrader) {
	$offer = get_site_transient(LF_FLEET_OFFER_TRANSIENT);
	if (!is_array($offer) || empty($offer['update'])) {
		return $reply;
	}
	if ((string) $package !== (string) ($offer['download_url'] ?? '')) {
		return $reply;
	}

	$theme = wp_get_theme();
	$themeSlug = (string) $theme->get_stylesheet();
	$targetVersion = (string) ($offer['version'] ?? '');
	$sha = strtolower((string) ($offer['sha256'] ?? ''));

	$keysRaw = (string) get_option(LF_FLEET_OPT_PUBKEYS, '[]');
	$keys = json_decode($keysRaw, true);
	$kid = (string) ($offer['public_key_id'] ?? '');
	$pk = is_array($keys) && isset($keys[$kid]) ? (string) $keys[$kid] : '';
	if ($pk === '') {
		return new WP_Error('lf_fleet_no_pubkey', __('Fleet update public key missing; refusing update.', 'leadsforward-core'));
	}

	$msg = lf_fleet_release_message($themeSlug, $targetVersion, $sha);
	if (!lf_fleet_verify_ed25519($msg, (string) ($offer['signature'] ?? ''), $pk)) {
		return new WP_Error('lf_fleet_bad_signature', __('Fleet update signature verification failed; refusing update.', 'leadsforward-core'));
	}

	return $reply;
}, 10, 3);

/**
 * Download a URL to a local temporary file.
 *
 * @return array{ok:bool,path:string,error:string}
 */
function lf_fleet_download_to_temp(string $url): array {
	$tmp = wp_tempnam($url);
	if (!$tmp) {
		return ['ok' => false, 'path' => '', 'error' => 'tempnam_failed'];
	}
	$res = wp_remote_get($url, [
		'timeout' => 60,
		'stream' => true,
		'filename' => $tmp,
	]);
	if (is_wp_error($res)) {
		@unlink($tmp);
		return ['ok' => false, 'path' => '', 'error' => $res->get_error_message()];
	}
	$code = (int) wp_remote_retrieve_response_code($res);
	if ($code < 200 || $code >= 300) {
		@unlink($tmp);
		return ['ok' => false, 'path' => '', 'error' => 'http_' . $code];
	}
	return ['ok' => true, 'path' => (string) $tmp, 'error' => ''];
}

add_filter('upgrader_package_options', static function (array $options): array {
	// Controller package URLs use a one-time token; WordPress may run this filter more than once per upgrade.
	// Re-fetching the same URL returns 404 after the first successful hit, so reuse the verified temp file.
	static $lf_fleet_verified_local_package = '';

	$offer = get_site_transient(LF_FLEET_OFFER_TRANSIENT);
	if (!is_array($offer) || empty($offer['update'])) {
		return $options;
	}
	$download = (string) ($offer['download_url'] ?? '');
	$sha = strtolower((string) ($offer['sha256'] ?? ''));
	if ($download === '' || $sha === '') {
		return $options;
	}

	if ($lf_fleet_verified_local_package !== '' && is_readable($lf_fleet_verified_local_package)) {
		$options['package'] = $lf_fleet_verified_local_package;
		return $options;
	}

	$pkg = isset($options['package']) ? (string) $options['package'] : '';
	if ($pkg !== '' && !preg_match('#^https?://#i', $pkg) && is_readable($pkg)) {
		$lf_fleet_verified_local_package = $pkg;
		return $options;
	}

	$dl = lf_fleet_download_to_temp($download);
	if (!$dl['ok'] || $dl['path'] === '') {
		return $options;
	}
	$got = strtolower((string) lf_fleet_sha256_file($dl['path']));
	if (!hash_equals($sha, $got)) {
		@unlink($dl['path']);
		// Leave options untouched; upgrader will proceed with network package, but we refuse by clearing package.
		$options['package'] = '';
		return $options;
	}

	// Force upgrader to use verified local temp file.
	$lf_fleet_verified_local_package = $dl['path'];
	$options['package'] = $dl['path'];
	return $options;
});

