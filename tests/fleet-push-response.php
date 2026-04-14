<?php
declare(strict_types=1);

/**
 * Minimal stubs so push-endpoint.php can load in CLI without WordPress.
 */
class WP_REST_Request {
	public function get_headers(): array {
		return [];
	}

	public function get_body(): string {
		return '';
	}
}

class WP_REST_Response {
	public function __construct(
		public mixed $data = null,
		public int $status = 200,
	) {
	}
}

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
expect(array_key_exists('error_code', $res), 'error_code present');
