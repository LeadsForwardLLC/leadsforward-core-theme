<?php
/**
 * Fleet updates: connection bundle import + last-check summary for admin UI.
 *
 * @package LeadsForward_Core
 * @since 0.1.23
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Normalize comma/space-separated tags for storage or comparison.
 *
 * @return list<string>
 */
function lf_fleet_tags_from_string(string $raw): array {
	$raw = trim($raw);
	if ($raw === '') {
		return [];
	}
	$parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
	if (!is_array($parts)) {
		return [];
	}
	$out = [];
	foreach ($parts as $p) {
		$t = strtolower(sanitize_text_field((string) $p));
		if ($t !== '' && !in_array($t, $out, true)) {
			$out[] = $t;
		}
	}
	return $out;
}

/**
 * Import JSON bundle from controller (api_base, site_id, token, public_keys_json).
 *
 * @return array{ok:bool,message:string}
 */
function lf_fleet_import_connection_bundle(string $raw): array {
	$raw = trim($raw);
	if ($raw === '') {
		return ['ok' => false, 'message' => 'empty'];
	}
	$data = json_decode($raw, true);
	if (!is_array($data)) {
		return ['ok' => false, 'message' => 'invalid_json'];
	}
	$api = isset($data['api_base']) ? esc_url_raw((string) $data['api_base']) : '';
	$sid = isset($data['site_id']) ? sanitize_text_field((string) $data['site_id']) : '';
	$tok = isset($data['token']) ? trim((string) $data['token']) : '';
	$tok = trim($tok, " \t\n\r\0\x0B\"'");
	$tok = preg_replace('/[^A-Za-z0-9\-\_\+\/=]/', '', $tok);
	$tok = is_string($tok) ? $tok : '';
	$pk = $data['public_keys_json'] ?? null;
	if ($api === '' || $sid === '' || $tok === '') {
		return ['ok' => false, 'message' => 'missing_fields'];
	}
	if (!is_array($pk)) {
		return ['ok' => false, 'message' => 'bad_public_keys'];
	}
	$clean = [];
	foreach ($pk as $kid => $val) {
		$kid = sanitize_text_field((string) $kid);
		if ($kid === '') {
			continue;
		}
		$clean[$kid] = is_string($val) ? $val : '';
	}
	update_option(LF_FLEET_OPT_API_BASE, $api);
	update_option(LF_FLEET_OPT_SITE_ID, $sid);
	update_option(LF_FLEET_OPT_TOKEN, $tok);
	update_option(LF_FLEET_OPT_PUBKEYS, wp_json_encode($clean));
	return ['ok' => true, 'message' => ''];
}

/**
 * Structured view of LF_FLEET_OPT_LAST for wp-admin.
 *
 * @return array{
 *   has_data:bool,
 *   checked_at:int,
 *   http_ok:bool,
 *   status:int,
 *   update_available:bool,
 *   reason:string,
 *   controller_version:string,
 *   network_error:string,
 *   failures:int,
 *   next_attempt_at:int,
 *   last_upgrade_error:string,
 *   raw_json:string
 * }
 */
function lf_fleet_last_check_summary(): array {
	$raw = (string) get_option(LF_FLEET_OPT_LAST, '');
	$decoded = json_decode($raw, true);
	$base = [
		'has_data' => false,
		'checked_at' => 0,
		'http_ok' => false,
		'status' => 0,
		'update_available' => false,
		'reason' => '',
		'controller_version' => '',
		'network_error' => '',
		'failures' => 0,
		'next_attempt_at' => 0,
		'last_upgrade_error' => '',
		'raw_json' => $raw,
	];
	if (!is_array($decoded)) {
		return $base;
	}
	$base['has_data'] = true;
	$base['checked_at'] = (int) ($decoded['checked_at'] ?? 0);
	$base['http_ok'] = !empty($decoded['ok']);
	$base['status'] = (int) ($decoded['status'] ?? 0);
	$base['network_error'] = (string) ($decoded['error'] ?? '');
	$base['failures'] = (int) ($decoded['failures'] ?? 0);
	$base['next_attempt_at'] = (int) ($decoded['next_attempt_at'] ?? 0);
	$base['last_upgrade_error'] = (string) ($decoded['last_upgrade_error'] ?? '');
	$d = $decoded['data'] ?? null;
	if (is_array($d)) {
		$base['update_available'] = !empty($d['update']);
		$base['reason'] = (string) ($d['reason'] ?? '');
		$base['controller_version'] = (string) ($d['controller_version'] ?? '');
	}
	return $base;
}
