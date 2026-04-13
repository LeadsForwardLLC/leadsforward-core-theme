<?php
/**
 * Fleet updates: crypto helpers (checksum + Ed25519 verify).
 *
 * @package LeadsForward_Core
 * @since 0.1.21
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_fleet_sha256_file(string $path): string {
	return hash_file('sha256', $path);
}

/**
 * Verify Ed25519 detached signature.
 *
 * @param string $message Canonical signed message.
 * @param string $signatureB64 Base64-encoded detached signature.
 * @param string $publicKeyB64 Base64-encoded 32-byte public key.
 */
function lf_fleet_verify_ed25519(string $message, string $signatureB64, string $publicKeyB64): bool {
	if (!function_exists('sodium_crypto_sign_verify_detached')) {
		return false;
	}
	$sig = base64_decode($signatureB64, true);
	$pk = base64_decode($publicKeyB64, true);
	if ($sig === false || $pk === false || strlen($pk) !== 32) {
		return false;
	}
	return sodium_crypto_sign_verify_detached($sig, $message, $pk);
}

