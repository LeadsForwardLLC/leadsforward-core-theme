<?php
/**
 * Manifest-driven publish scheduling for service / service-area CPTs and blog shells.
 *
 * Uses WordPress `future` status + `post_date` for deferred publishing (core scheduling).
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Default launch schedule (merged with manifest global.launch_schedule).
 *
 * @return array<string, mixed>
 */
function lf_launch_schedule_defaults(): array {
	return [
		'anchor' => '',
		'services_initial_ratio' => 0.5,
		'service_areas_initial_ratio' => 0.5,
		'deferred_mode' => 'weekly_pair',
		'spread_days' => 30,
		'publish_hour' => 9,
		'blog' => [
			'publish_now_count' => 3,
			'scheduled_count' => 2,
			'scheduled_weeks_between' => 1,
		],
	];
}

/**
 * Merge manifest launch_schedule onto defaults.
 *
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function lf_launch_schedule_normalize(array $raw): array {
	$defaults = lf_launch_schedule_defaults();
	$blog_in = isset($raw['blog']) && is_array($raw['blog']) ? $raw['blog'] : [];
	$blog = array_merge($defaults['blog'], [
			'publish_now_count' => isset($blog_in['publish_now_count']) ? max(0, min(10, (int) $blog_in['publish_now_count'])) : $defaults['blog']['publish_now_count'],
			'scheduled_count' => isset($blog_in['scheduled_count']) ? max(0, min(10, (int) $blog_in['scheduled_count'])) : $defaults['blog']['scheduled_count'],
			'scheduled_weeks_between' => isset($blog_in['scheduled_weeks_between']) ? max(1, min(12, (int) $blog_in['scheduled_weeks_between'])) : $defaults['blog']['scheduled_weeks_between'],
		]);
	$mode = isset($raw['deferred_mode']) ? sanitize_key((string) $raw['deferred_mode']) : $defaults['deferred_mode'];
	if (!in_array($mode, ['weekly_pair', 'spread'], true)) {
		$mode = $defaults['deferred_mode'];
	}
	$sr = isset($raw['services_initial_ratio']) ? (float) $raw['services_initial_ratio'] : $defaults['services_initial_ratio'];
	$ar = isset($raw['service_areas_initial_ratio']) ? (float) $raw['service_areas_initial_ratio'] : $defaults['service_areas_initial_ratio'];
	$sr = max(0.0, min(1.0, $sr));
	$ar = max(0.0, min(1.0, $ar));

	return [
		'anchor' => sanitize_text_field((string) ($raw['anchor'] ?? $defaults['anchor'])),
		'services_initial_ratio' => $sr,
		'service_areas_initial_ratio' => $ar,
		'deferred_mode' => $mode,
		'spread_days' => isset($raw['spread_days']) ? max(1, min(365, (int) $raw['spread_days'])) : $defaults['spread_days'],
		'publish_hour' => isset($raw['publish_hour']) ? max(0, min(23, (int) $raw['publish_hour'])) : $defaults['publish_hour'],
		'blog' => $blog,
	];
}

/**
 * Anchor timestamp (local) for scheduling; defaults to now.
 */
function lf_launch_schedule_anchor_ts(string $anchor_iso): int {
	$anchor_iso = trim($anchor_iso);
	if ($anchor_iso !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $anchor_iso)) {
		$ts = strtotime($anchor_iso . ' 00:00:00');
		if ($ts !== false) {
			return (int) $ts;
		}
	}
	return (int) current_time('timestamp');
}

/**
 * Ensure a local MySQL datetime is strictly in the future (WordPress `future` status).
 */
function lf_launch_schedule_bump_until_future(string $local_mysql): string {
	$tz = wp_timezone();
	try {
		$dt = new \DateTimeImmutable($local_mysql, $tz);
	} catch (\Throwable $e) {
		return $local_mysql;
	}
	$now = new \DateTimeImmutable('now', $tz);
	$guard = 0;
	while ($dt <= $now && $guard < 2000) {
		$dt = $dt->modify('+1 day');
		$guard++;
	}

	return $dt->format('Y-m-d H:i:s');
}

/**
 * Split ordered slugs into publish-now vs deferred lists.
 *
 * @param list<string> $slugs
 * @return array{0: list<string>, 1: list<string>}
 */
function lf_launch_schedule_partition_slugs(array $slugs, float $ratio): array {
	$slugs = array_values(array_filter(array_map('strval', $slugs), static function ($s): bool {
		return $s !== '';
	}));
	$n = count($slugs);
	if ($n === 0) {
		return [[], []];
	}
	$initial = (int) round($n * $ratio);
	$initial = max(0, min($n, $initial));
	$now = array_slice($slugs, 0, $initial);
	$later = array_slice($slugs, $initial);

	return [$now, $later];
}

/**
 * Build local datetime strings for deferred CPT rows.
 *
 * @param list<string> $deferred_services
 * @param list<string> $deferred_areas
 * @return array<string, array{local:string}>
 */
function lf_launch_schedule_deferred_datetimes(array $deferred_services, array $deferred_areas, array $schedule, int $anchor_ts): array {
	$out = [];
	$mode = $schedule['deferred_mode'] ?? 'weekly_pair';
	$hour = (int) ($schedule['publish_hour'] ?? 9);
	$tz = wp_timezone();

	if ($mode === 'weekly_pair') {
		$weeks = max(count($deferred_services), count($deferred_areas));
		for ($w = 0; $w < $weeks; $w++) {
			$scheduled_ts = strtotime('+' . $w . ' week', $anchor_ts);
			if ($scheduled_ts === false) {
				$scheduled_ts = $anchor_ts;
			}
			$local_date = wp_date('Y-m-d', (int) $scheduled_ts, $tz) . sprintf(' %02d:00:00', $hour);
			if (isset($deferred_services[$w])) {
				$slug = $deferred_services[$w];
				$out['lf_service:' . $slug] = ['local' => $local_date];
			}
			if (isset($deferred_areas[$w])) {
				$slug = $deferred_areas[$w];
				$out['lf_service_area:' . $slug] = ['local' => $local_date];
			}
		}
		return $out;
	}

	$spread = (int) ($schedule['spread_days'] ?? 30);
	$spread = max(1, min(365, $spread));

	$stamp = static function (int $index, int $total, int $base_ts) use ($spread, $hour, $tz): array {
		if ($total <= 0) {
			return ['local' => ''];
		}
		$day_offset = $total === 1 ? 0 : (int) floor($index * ($spread / max(1, $total - 1)));
		$ts = $base_ts + $day_offset * DAY_IN_SECONDS;
		$local = wp_date('Y-m-d', $ts, $tz) . sprintf(' %02d:00:00', $hour);

		return ['local' => $local];
	};

	foreach ($deferred_services as $i => $slug) {
		$d = $stamp($i, count($deferred_services), $anchor_ts);
		if ($d['local'] !== '') {
			$out['lf_service:' . $slug] = $d;
		}
	}
	foreach ($deferred_areas as $i => $slug) {
		$d = $stamp($i, count($deferred_areas), $anchor_ts);
		if ($d['local'] !== '') {
			$out['lf_service_area:' . $slug] = $d;
		}
	}

	return $out;
}
