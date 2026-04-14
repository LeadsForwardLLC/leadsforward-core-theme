<?php
declare(strict_types=1);
if (!defined('ABSPATH')) {
	exit;
}

function lf_fleet_should_run_auto_update(bool $via_cron, bool $via_admin, bool $via_signed_push): bool {
	return $via_cron || $via_admin || $via_signed_push;
}
