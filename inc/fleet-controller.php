<?php
/**
 * Fleet controller (theme.leadsforward.com).
 *
 * Provides credential minting + /api/v1 endpoints for fleet sites.
 *
 * @package LeadsForward_Core
 * @since 0.1.22
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_FLEET_CTRL_OPT_ENABLED = 'lf_fleet_controller_enabled';
const LF_FLEET_CTRL_OPT_SITES = 'lf_fleet_controller_sites'; // JSON map site_id -> data (includes token)
const LF_FLEET_CTRL_OPT_KEYS = 'lf_fleet_controller_keys';   // JSON map key_id -> {public,private,created_at}
const LF_FLEET_CTRL_OPT_REWRITE_FLUSHED = 'lf_fleet_controller_rewrite_flushed';
const LF_FLEET_CTRL_OPT_APPROVE_ALL = 'lf_fleet_controller_approve_all';
const LF_FLEET_CTRL_OPT_APPROVED_VERSION = 'lf_fleet_controller_approved_version';

const LF_FLEET_CTRL_DL_TRANSIENT_PREFIX = 'lf_fleet_dl_';

function lf_fleet_controller_theme_slug(): string {
	$theme = wp_get_theme();
	return (string) $theme->get_stylesheet();
}

function lf_fleet_controller_current_version(): string {
	$theme = wp_get_theme();
	return (string) $theme->get('Version');
}

function lf_fleet_controller_latest_key_id(): string {
	$keys = lf_fleet_controller_keys();
	if ($keys === []) {
		return '';
	}
	$best = '';
	$best_ts = 0;
	foreach ($keys as $kid => $row) {
		$ts = (int) ($row['created_at'] ?? 0);
		if ($ts >= $best_ts) {
			$best_ts = $ts;
			$best = (string) $kid;
		}
	}
	return $best;
}

/**
 * Build (or reuse cached) theme zip for a version.
 *
 * @return array{ok:bool,path:string,sha256:string,error:string}
 */
function lf_fleet_controller_get_theme_zip(string $slug, string $version): array {
	$slug = sanitize_title($slug);
	$version = preg_replace('/[^0-9A-Za-z\.\-\+_]/', '', $version);
	if (!is_string($version) || $slug === '' || $version === '') {
		return ['ok' => false, 'path' => '', 'sha256' => '', 'error' => 'bad_args'];
	}

	if (!class_exists('ZipArchive')) {
		return ['ok' => false, 'path' => '', 'sha256' => '', 'error' => 'zip_unavailable'];
	}

	$uploads = wp_upload_dir();
	$base = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
	if ($base === '') {
		return ['ok' => false, 'path' => '', 'sha256' => '', 'error' => 'uploads_unavailable'];
	}
	$dir = rtrim($base, '/') . '/lf-fleet';
	if (!is_dir($dir)) {
		wp_mkdir_p($dir);
	}
	$zip_path = $dir . '/' . $slug . '-' . $version . '.zip';
	if (is_readable($zip_path) && filesize($zip_path) > 1000) {
		return ['ok' => true, 'path' => $zip_path, 'sha256' => strtolower(hash_file('sha256', $zip_path)), 'error' => ''];
	}

	$src = get_template_directory();
	if (!is_dir($src)) {
		return ['ok' => false, 'path' => '', 'sha256' => '', 'error' => 'theme_dir_missing'];
	}

	$zip = new \ZipArchive();
	if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
		return ['ok' => false, 'path' => '', 'sha256' => '', 'error' => 'zip_open_failed'];
	}

	$exclude = [
		'/.git/',
		'/.worktrees/',
		'/node_modules/',
		'/vendor/',
		'/.cursor/',
		'/mcps/',
		'/terminals/',
		'/agent-transcripts/',
	];

	$it = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
		\RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ($it as $file) {
		/** @var \SplFileInfo $file */
		$path = (string) $file->getPathname();
		$rel = ltrim(str_replace('\\', '/', substr($path, strlen($src))), '/');
		if ($rel === '') {
			continue;
		}
		$rel_norm = '/' . $rel;
		$skip = false;
		foreach ($exclude as $pat) {
			if (strpos($rel_norm, $pat) !== false) {
				$skip = true;
				break;
			}
		}
		if ($skip) {
			continue;
		}
		if ($file->isDir()) {
			$zip->addEmptyDir($slug . '/' . $rel);
			continue;
		}
		if ($file->isFile()) {
			$zip->addFile($path, $slug . '/' . $rel);
		}
	}
	$zip->close();

	if (!is_readable($zip_path) || filesize($zip_path) < 1000) {
		@unlink($zip_path);
		return ['ok' => false, 'path' => '', 'sha256' => '', 'error' => 'zip_build_failed'];
	}
	return ['ok' => true, 'path' => $zip_path, 'sha256' => strtolower(hash_file('sha256', $zip_path)), 'error' => ''];
}

function lf_fleet_controller_sign_release(string $kid, string $message): string {
	if (!function_exists('sodium_crypto_sign_detached')) {
		return '';
	}
	$keys = lf_fleet_controller_keys();
	$row = $keys[$kid] ?? null;
	$priv_b64 = is_array($row) ? (string) ($row['private'] ?? '') : '';
	if ($priv_b64 === '') {
		return '';
	}
	$priv = base64_decode($priv_b64, true);
	if ($priv === false) {
		return '';
	}
	$sig = sodium_crypto_sign_detached($message, $priv);
	return base64_encode($sig);
}

function lf_fleet_controller_issue_download_token(string $site_id, string $zip_path, string $sha256, int $ttl = 600): string {
	$t = lf_fleet_controller_token_new();
	$key = LF_FLEET_CTRL_DL_TRANSIENT_PREFIX . md5($site_id . '|' . $t);
	set_transient($key, wp_json_encode(['site_id' => $site_id, 'path' => $zip_path, 'sha256' => $sha256]), $ttl);
	return $t;
}

function lf_fleet_controller_enabled(): bool {
	return get_option(LF_FLEET_CTRL_OPT_ENABLED, '0') === '1';
}

/**
 * @return array<string, array<string, mixed>>
 */
function lf_fleet_controller_sites(): array {
	$raw = (string) get_option(LF_FLEET_CTRL_OPT_SITES, '{}');
	$decoded = json_decode($raw, true);
	return is_array($decoded) ? $decoded : [];
}

/**
 * @param array<string, array<string, mixed>> $sites
 */
function lf_fleet_controller_update_sites(array $sites): void {
	update_option(LF_FLEET_CTRL_OPT_SITES, wp_json_encode($sites));
}

/**
 * @return array<string, array{public:string,private:string,created_at:int}>
 */
function lf_fleet_controller_keys(): array {
	$raw = (string) get_option(LF_FLEET_CTRL_OPT_KEYS, '{}');
	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return [];
	}
	$out = [];
	foreach ($decoded as $kid => $row) {
		if (!is_string($kid) || !is_array($row)) {
			continue;
		}
		$pub = isset($row['public']) ? (string) $row['public'] : '';
		$priv = isset($row['private']) ? (string) $row['private'] : '';
		$ts = isset($row['created_at']) ? (int) $row['created_at'] : 0;
		if ($pub !== '' && $priv !== '') {
			$out[$kid] = ['public' => $pub, 'private' => $priv, 'created_at' => $ts > 0 ? $ts : time()];
		}
	}
	return $out;
}

/**
 * @param array<string, array{public:string,private:string,created_at:int}> $keys
 */
function lf_fleet_controller_update_keys(array $keys): void {
	update_option(LF_FLEET_CTRL_OPT_KEYS, wp_json_encode($keys));
}

function lf_fleet_controller_pubkeys_json(): string {
	$keys = lf_fleet_controller_keys();
	$map = [];
	foreach ($keys as $kid => $row) {
		$map[$kid] = (string) ($row['public'] ?? '');
	}
	return wp_json_encode($map);
}

function lf_fleet_controller_generate_ed25519_keypair(): array {
	if (!function_exists('sodium_crypto_sign_keypair')) {
		return ['ok' => false, 'kid' => '', 'public' => '', 'private' => '', 'error' => 'libsodium_unavailable'];
	}
	$kp = sodium_crypto_sign_keypair();
	$pub = sodium_crypto_sign_publickey($kp);
	$priv = sodium_crypto_sign_secretkey($kp);
	$kid = 'key_' . gmdate('Y_m_d_His');
	return [
		'ok' => true,
		'kid' => $kid,
		'public' => base64_encode($pub),
		'private' => base64_encode($priv),
		'error' => '',
	];
}

function lf_fleet_controller_token_new(): string {
	// URL-safe base64 (no + / =) to avoid sanitization/paste issues.
	$raw = base64_encode(random_bytes(32));
	$safe = strtr($raw, '+/', '-_');
	return rtrim($safe, '=');
}

function lf_fleet_controller_mint_site(string $site_url = '', string $label = ''): array {
	$site_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : (string) wp_rand();
	$token = lf_fleet_controller_token_new();
	$site_url = esc_url_raw($site_url);
	$label = sanitize_text_field($label);
	return [
		'site_id' => $site_id,
		'token' => $token,
		'site_url' => $site_url,
		'label' => $label,
		'created_at' => time(),
		'last_seen_at' => 0,
		'current_version' => '',
	];
}

/**
 * Compute expected request signature using the same scheme fleet sites use.
 */
function lf_fleet_controller_expected_sig(string $token, string $method, string $path, int $ts, string $nonce, string $bodySha): string {
	$payload = strtoupper($method) . "\n" . $path . "\n" . $ts . "\n" . $nonce . "\n" . $bodySha;
	return base64_encode(hash_hmac('sha256', $payload, $token, true));
}

function lf_fleet_controller_verify_request(string $path): array {
	$site_id = isset($_SERVER['HTTP_X_LF_SITE']) ? (string) $_SERVER['HTTP_X_LF_SITE'] : '';
	$ts_raw = isset($_SERVER['HTTP_X_LF_TIMESTAMP']) ? (string) $_SERVER['HTTP_X_LF_TIMESTAMP'] : '';
	$nonce = isset($_SERVER['HTTP_X_LF_NONCE']) ? (string) $_SERVER['HTTP_X_LF_NONCE'] : '';
	$sig = isset($_SERVER['HTTP_X_LF_SIGNATURE']) ? (string) $_SERVER['HTTP_X_LF_SIGNATURE'] : '';
	$site_id = sanitize_text_field($site_id);
	$nonce = sanitize_text_field($nonce);
	$sig = trim($sig);
	$ts = (int) $ts_raw;

	if ($site_id === '' || $nonce === '' || $sig === '' || $ts <= 0) {
		return ['ok' => false, 'site_id' => '', 'error' => 'missing_headers'];
	}

	// TTL 5 minutes.
	if (abs(time() - $ts) > 300) {
		return ['ok' => false, 'site_id' => '', 'error' => 'expired'];
	}

	// Replay protection (nonce).
	$nonce_key = 'lf_fleet_nonce_' . md5($site_id . '|' . $nonce);
	if (get_transient($nonce_key)) {
		return ['ok' => false, 'site_id' => '', 'error' => 'replay'];
	}

	$sites = lf_fleet_controller_sites();
	$row = $sites[$site_id] ?? null;
	$token = is_array($row) ? trim((string) ($row['token'] ?? '')) : '';
	if ($token === '') {
		return ['ok' => false, 'site_id' => '', 'error' => 'unknown_site'];
	}

	$method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
	// Normalize the signed path to the controller's home_url() path prefix.
	// This prevents signature mismatches on installs where WordPress is not at "/".
	$home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
	$home_path = $home_path !== '' ? rtrim($home_path, '/') : '';
	$req_path = isset($_SERVER['REQUEST_URI']) ? (string) wp_parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
	if ($req_path !== '' && $home_path !== '' && str_starts_with($req_path, $home_path . '/')) {
		$req_path = substr($req_path, strlen($home_path));
	}
	$req_path = '/' . ltrim($req_path, '/');
	$req_path = rtrim($req_path, '/');
	$path = rtrim($path, '/');

	$body = file_get_contents('php://input');
	$body = is_string($body) ? $body : '';
	$body_sha = hash('sha256', $body);

	// Accept a small set of equivalent path forms (slashes/subdir edge cases).
	$candidates = array_values(array_unique(array_filter([
		$path,
		$req_path !== '' && $req_path !== '/' ? $req_path : '',
		'/' . ltrim($path, '/') . '/',
		$req_path !== '' && $req_path !== '/' ? ('/' . ltrim($req_path, '/') . '/') : '',
	])));
	$ok_sig = false;
	foreach ($candidates as $cand_path) {
		$cand_path = (string) $cand_path;
		$cand_path = $cand_path === '/' ? '/' : rtrim($cand_path, '/');
		$expected = lf_fleet_controller_expected_sig($token, $method, $cand_path, $ts, $nonce, $body_sha);
		if (hash_equals($expected, $sig)) {
			$ok_sig = true;
			break;
		}
	}
	if (!$ok_sig) {
		return ['ok' => false, 'site_id' => '', 'error' => 'bad_sig'];
	}

	set_transient($nonce_key, '1', 10 * MINUTE_IN_SECONDS);
	return ['ok' => true, 'site_id' => $site_id, 'error' => ''];
}

function lf_fleet_controller_json($payload, int $status = 200): void {
	status_header($status);
	header('Content-Type: application/json; charset=' . get_option('blog_charset'));
	echo wp_json_encode($payload);
	exit;
}

function lf_fleet_controller_register_query_vars(array $vars): array {
	$vars[] = 'lf_fleet_api';
	$vars[] = 'lf_fleet_route';
	return $vars;
}
add_filter('query_vars', 'lf_fleet_controller_register_query_vars');

function lf_fleet_controller_add_rewrite_rules(): void {
	add_rewrite_rule('^api/v1/sites/heartbeat/?$', 'index.php?lf_fleet_api=1&lf_fleet_route=sites_heartbeat', 'top');
	add_rewrite_rule('^api/v1/updates/check/?$', 'index.php?lf_fleet_api=1&lf_fleet_route=updates_check', 'top');
	add_rewrite_rule('^api/v1/updates/package/?$', 'index.php?lf_fleet_api=1&lf_fleet_route=updates_package', 'top');
	add_rewrite_rule('^api/v1/controller/public-keys/?$', 'index.php?lf_fleet_api=1&lf_fleet_route=public_keys', 'top');
}
add_action('init', 'lf_fleet_controller_add_rewrite_rules');

function lf_fleet_controller_maybe_flush_rules(): void {
	if (!is_admin() || !current_user_can(LF_OPS_CAP)) {
		return;
	}
	if (!lf_fleet_controller_enabled()) {
		return;
	}
	if (get_option(LF_FLEET_CTRL_OPT_REWRITE_FLUSHED, '0') === '1') {
		return;
	}
	flush_rewrite_rules(false);
	update_option(LF_FLEET_CTRL_OPT_REWRITE_FLUSHED, '1');
}
add_action('admin_init', 'lf_fleet_controller_maybe_flush_rules');

function lf_fleet_controller_handle_api(): void {
	if ((int) get_query_var('lf_fleet_api') !== 1) {
		return;
	}
	if (!lf_fleet_controller_enabled()) {
		lf_fleet_controller_json(['error' => 'not_found'], 404);
	}
	$route = (string) get_query_var('lf_fleet_route');
	if ($route === 'public_keys') {
		lf_fleet_controller_json(['public_keys' => json_decode(lf_fleet_controller_pubkeys_json(), true) ?: []]);
	}

	if ($route === 'updates_package') {
		// Public download endpoint using one-time token (no HMAC headers).
		$t = isset($_GET['t']) ? sanitize_text_field((string) wp_unslash($_GET['t'])) : '';
		$s = isset($_GET['site_id']) ? sanitize_text_field((string) wp_unslash($_GET['site_id'])) : '';
		if ($t === '' || $s === '') {
			status_header(404);
			exit;
		}
		$key = LF_FLEET_CTRL_DL_TRANSIENT_PREFIX . md5($s . '|' . $t);
		$payload = (string) get_transient($key);
		if ($payload === '') {
			status_header(404);
			exit;
		}
		delete_transient($key);
		$row = json_decode($payload, true);
		$path = is_array($row) ? (string) ($row['path'] ?? '') : '';
		$sha = is_array($row) ? (string) ($row['sha256'] ?? '') : '';
		if ($path === '' || !is_readable($path)) {
			status_header(404);
			exit;
		}
		// Quick integrity check before streaming.
		if ($sha !== '' && strtolower(hash_file('sha256', $path)) !== strtolower($sha)) {
			status_header(500);
			exit;
		}
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . basename($path) . '"');
		header('Content-Length: ' . (string) filesize($path));
		readfile($path);
		exit;
	}

	$verify = lf_fleet_controller_verify_request(
		$route === 'sites_heartbeat' ? '/api/v1/sites/heartbeat' : '/api/v1/updates/check'
	);
	if (empty($verify['ok'])) {
		lf_fleet_controller_json(['error' => (string) ($verify['error'] ?? 'unauthorized')], 401);
	}
	$site_id = (string) ($verify['site_id'] ?? '');
	$sites = lf_fleet_controller_sites();
	if (!isset($sites[$site_id]) || !is_array($sites[$site_id])) {
		lf_fleet_controller_json(['error' => 'unknown_site'], 401);
	}

	if ($route === 'sites_heartbeat') {
		$body = json_decode((string) file_get_contents('php://input'), true);
		$body = is_array($body) ? $body : [];
		$sites[$site_id]['last_seen_at'] = time();
		$sites[$site_id]['current_version'] = sanitize_text_field((string) ($body['current_version'] ?? ''));
		$sites[$site_id]['theme_slug'] = sanitize_text_field((string) ($body['theme_slug'] ?? ''));
		$sites[$site_id]['wp_version'] = sanitize_text_field((string) ($body['wp_version'] ?? ''));
		$sites[$site_id]['php_version'] = sanitize_text_field((string) ($body['php_version'] ?? ''));
		lf_fleet_controller_update_sites($sites);
		lf_fleet_controller_json(['ok' => true]);
	}

	if ($route === 'updates_check') {
		$theme_slug = isset($_GET['theme_slug']) ? sanitize_text_field((string) wp_unslash($_GET['theme_slug'])) : '';
		$current = isset($_GET['current']) ? sanitize_text_field((string) wp_unslash($_GET['current'])) : '';
		// Treat updates_check as a "seen" ping too (useful when heartbeat is delayed by cron).
		$sites[$site_id]['last_seen_at'] = time();
		if ($current !== '') {
			$sites[$site_id]['current_version'] = $current;
		}
		if ($theme_slug !== '') {
			$sites[$site_id]['theme_slug'] = $theme_slug;
		}
		lf_fleet_controller_update_sites($sites);
		$approved_all = get_option(LF_FLEET_CTRL_OPT_APPROVE_ALL, '0') === '1';
		$approved_version = (string) get_option(LF_FLEET_CTRL_OPT_APPROVED_VERSION, '');
		if ($approved_version === '') {
			$approved_version = lf_fleet_controller_current_version();
		}

		// Only serve updates for the controller's current theme slug.
		$controller_slug = lf_fleet_controller_theme_slug();
		if ($theme_slug === '' || $theme_slug !== $controller_slug) {
			lf_fleet_controller_json(['update' => false]);
		}
		if (!$approved_all) {
			lf_fleet_controller_json(['update' => false]);
		}
		if ($current !== '' && version_compare($approved_version, $current, '<=')) {
			lf_fleet_controller_json(['update' => false]);
		}

		$kid = lf_fleet_controller_latest_key_id();
		if ($kid === '') {
			lf_fleet_controller_json(['update' => false]);
		}
		$zip = lf_fleet_controller_get_theme_zip($controller_slug, $approved_version);
		if (empty($zip['ok']) || $zip['path'] === '' || $zip['sha256'] === '') {
			lf_fleet_controller_json(['update' => false]);
		}
		$msg = $controller_slug . "\n" . $approved_version . "\n" . strtolower((string) $zip['sha256']);
		$sig = lf_fleet_controller_sign_release($kid, $msg);
		if ($sig === '') {
			lf_fleet_controller_json(['update' => false]);
		}
		$t = lf_fleet_controller_issue_download_token($site_id, (string) $zip['path'], (string) $zip['sha256']);
		$download_url = home_url('/api/v1/updates/package') . '?site_id=' . rawurlencode($site_id) . '&t=' . rawurlencode($t);

		lf_fleet_controller_json([
			'update' => true,
			'version' => $approved_version,
			'download_url' => $download_url,
			'sha256' => (string) $zip['sha256'],
			'signature' => $sig,
			'public_key_id' => $kid,
		]);
	}

	lf_fleet_controller_json(['error' => 'not_found'], 404);
}
add_action('template_redirect', 'lf_fleet_controller_handle_api', 0);

