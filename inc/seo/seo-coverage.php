<?php
/**
 * Per-site SEO coverage dashboard (LeadsForward → SEO & Performance → Coverage).
 *
 * Read-only aggregate of the same on-page checklist used in the post SEO meta box.
 *
 * @package LeadsForward_Core
 * @since 0.1.160
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_SEO_COVERAGE_MAX_POSTS = 500;

const LF_SEO_COVERAGE_TRANSIENT = 'lf_seo_coverage_report_v1';

/**
 * @return array{generated_at:int,posts:list<array<string,mixed>>,truncated:bool,total_queried:int}
 */
function lf_seo_coverage_build_report(): array {
	$ids = get_posts([
		'post_type' => ['page', 'post', 'lf_service', 'lf_service_area'],
		'post_status' => 'publish',
		'posts_per_page' => LF_SEO_COVERAGE_MAX_POSTS,
		'fields' => 'ids',
		'no_found_rows' => true,
		'orderby' => 'modified',
		'order' => 'DESC',
		'suppress_filters' => false,
	]);
	$ids = is_array($ids) ? array_values(array_map('absint', $ids)) : [];
	$truncated = count($ids) >= LF_SEO_COVERAGE_MAX_POSTS;
	$posts = [];
	foreach ($ids as $post_id) {
		if ($post_id <= 0 || !function_exists('lf_seo_get_onpage_checklist_rows')) {
			continue;
		}
		$rows = lf_seo_get_onpage_checklist_rows($post_id);
		$failed_labels = [];
		foreach ($rows as $r) {
			if (empty($r['ok'])) {
				$failed_labels[] = (string) ($r['label'] ?? '');
			}
		}
		$ptype = (string) get_post_type($post_id);
		$posts[] = [
			'id' => $post_id,
			'type' => $ptype,
			'title' => (string) get_the_title($post_id),
			'edit_url' => (string) get_edit_post_link($post_id, 'raw'),
			'view_url' => (string) get_permalink($post_id),
			'score' => (int) get_post_meta($post_id, '_lf_seo_quality_score', true),
			'grade' => (string) get_post_meta($post_id, '_lf_seo_quality_grade', true),
			'failed_count' => count($failed_labels),
			'checks_total' => count($rows),
			'failed_labels' => $failed_labels,
		];
	}
	usort($posts, static function (array $a, array $b): int {
		if ($a['failed_count'] !== $b['failed_count']) {
			return $b['failed_count'] <=> $a['failed_count'];
		}
		if ($a['score'] !== $b['score']) {
			return $a['score'] <=> $b['score'];
		}
		return strcasecmp((string) $a['title'], (string) $b['title']);
	});
	return [
		'generated_at' => (int) time(),
		'posts' => $posts,
		'truncated' => $truncated,
		'total_queried' => count($ids),
	];
}

/**
 * @return array{generated_at:int,posts:list<array<string,mixed>>,truncated:bool,total_queried:int}
 */
function lf_seo_coverage_get_report(bool $force_refresh = false): array {
	if (!$force_refresh) {
		$cached = get_transient(LF_SEO_COVERAGE_TRANSIENT);
		if (is_array($cached) && isset($cached['generated_at'], $cached['posts']) && is_array($cached['posts'])) {
			return $cached;
		}
	}
	$built = lf_seo_coverage_build_report();
	set_transient(LF_SEO_COVERAGE_TRANSIENT, $built, 10 * MINUTE_IN_SECONDS);
	return $built;
}

function lf_seo_coverage_handle_refresh_redirect(): void {
	if (!is_admin() || !isset($_GET['page']) || sanitize_key((string) $_GET['page']) !== 'lf-seo') {
		return;
	}
	if (!isset($_GET['tab']) || sanitize_key((string) $_GET['tab']) !== 'coverage') {
		return;
	}
	if (empty($_GET['lf_seo_coverage_refresh'])) {
		return;
	}
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])), 'lf_seo_coverage_refresh')) {
		return;
	}
	delete_transient(LF_SEO_COVERAGE_TRANSIENT);
	lf_seo_coverage_get_report(true);
	wp_safe_redirect(remove_query_arg(['lf_seo_coverage_refresh', '_wpnonce'], wp_unslash($_SERVER['REQUEST_URI'] ?? '') ?: admin_url('admin.php?page=lf-seo&tab=coverage')));
	exit;
}
add_action('admin_init', 'lf_seo_coverage_handle_refresh_redirect', 5);

function lf_seo_render_coverage_tab(): void {
	if (!current_user_can('edit_theme_options')) {
		echo '<p>' . esc_html__('Insufficient permissions.', 'leadsforward-core') . '</p>';
		return;
	}
	if (function_exists('lf_admin_render_quality_summary_strip')) {
		lf_admin_render_quality_summary_strip('seo');
	}
	$view = isset($_GET['coverage_view']) ? sanitize_key((string) $_GET['coverage_view']) : 'all';
	if (!in_array($view, ['all', 'issues'], true)) {
		$view = 'all';
	}
	$type_filter = isset($_GET['coverage_type']) ? sanitize_key((string) $_GET['coverage_type']) : '';
	$allowed_types = ['page', 'post', 'lf_service', 'lf_service_area'];
	if ($type_filter !== '' && !in_array($type_filter, $allowed_types, true)) {
		$type_filter = '';
	}

	$report = lf_seo_coverage_get_report(false);
	$posts = $report['posts'];
	$generated = (int) ($report['generated_at'] ?? 0);
	$truncated = !empty($report['truncated']);

	if ($type_filter !== '') {
		$posts = array_values(array_filter($posts, static function (array $p) use ($type_filter): bool {
			return ($p['type'] ?? '') === $type_filter;
		}));
	}
	if ($view === 'issues') {
		$posts = array_values(array_filter($posts, static function (array $p): bool {
			return ($p['failed_count'] ?? 0) > 0;
		}));
	}

	$total = count($report['posts']);
	$missing_primary = 0;
	$with_any_fail = 0;
	foreach ($report['posts'] as $p) {
		if (!empty($p['failed_labels']) && is_array($p['failed_labels'])) {
			foreach ($p['failed_labels'] as $lab) {
				if ((string) $lab === __('Primary keyword', 'leadsforward-core')) {
					$missing_primary++;
					break;
				}
			}
		}
		if (($p['failed_count'] ?? 0) > 0) {
			$with_any_fail++;
		}
	}

	$base = admin_url('admin.php?page=lf-seo&tab=coverage');

	echo '<p class="description">';
	esc_html_e('This dashboard summarizes the same advisory checks shown in each post’s SEO meta box (keywords, snippet fields, headings, openings, links, images, thumbnails). Nothing here edits content automatically—use links to jump in and fix.', 'leadsforward-core');
	echo '</p>';

	if ($truncated) {
		echo '<div class="notice notice-warning inline"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: max URLs scanned */
				__('Showing the %d most recently modified published URLs. Run sitemap/manifest pruning or raise the theme constant if this site publishes more.', 'leadsforward-core'),
				LF_SEO_COVERAGE_MAX_POSTS
			)
		);
		echo '</p></div>';
	}

	echo '<p><strong>';
	esc_html_e('Summary', 'leadsforward-core');
	echo '</strong> — ';
	echo esc_html(
		sprintf(
			/* translators: 1: total URLs, 2: URLs with at least one failed check */
			__('%1$d published URLs scanned; %2$d with at least one open checklist item.', 'leadsforward-core'),
			(int) ($report['total_queried'] ?? $total),
			(int) $with_any_fail
		)
	);
	if ($missing_primary > 0) {
		echo ' ';
		echo esc_html(
			sprintf(
				/* translators: %d: count */
				_n('%d URL is missing a primary keyword.', '%d URLs are missing a primary keyword.', $missing_primary, 'leadsforward-core'),
				(int) $missing_primary
			)
		);
	}
	echo ' ';
	$built_note = $generated > 0
		? sprintf(
			/* translators: %s: human-readable elapsed time since build */
			__('%s ago', 'leadsforward-core'),
			human_time_diff($generated, (int) time())
		)
		: '—';
	echo esc_html(
		sprintf(
			/* translators: %s: time note or em dash */
			__('Report cached; last built %s.', 'leadsforward-core'),
			$built_note
		)
	);
	echo '</p>';

	$refresh_base = $base;
	if ($view !== 'all') {
		$refresh_base = add_query_arg('coverage_view', $view, $refresh_base);
	}
	if ($type_filter !== '') {
		$refresh_base = add_query_arg('coverage_type', $type_filter, $refresh_base);
	}
	$refresh_url = wp_nonce_url(add_query_arg(['lf_seo_coverage_refresh' => '1'], $refresh_base), 'lf_seo_coverage_refresh');

	echo '<p>';
	echo '<a class="button button-primary" href="' . esc_url($refresh_url) . '">' . esc_html__('Refresh report', 'leadsforward-core') . '</a> ';
	echo '<span class="description">' . esc_html__('Rebuilds counts (runs all checks server-side—may take a few seconds).', 'leadsforward-core') . '</span>';
	echo '</p>';

	echo '<form method="get" class="lf-seo-coverage-filters" style="margin:1rem 0;">';
	echo '<input type="hidden" name="page" value="lf-seo" />';
	echo '<input type="hidden" name="tab" value="coverage" />';
	echo '<label for="coverage_view">' . esc_html__('Show', 'leadsforward-core') . ' </label>';
	echo '<select name="coverage_view" id="coverage_view">';
	echo '<option value="all"' . selected($view, 'all', false) . '>' . esc_html__('All URLs', 'leadsforward-core') . '</option>';
	echo '<option value="issues"' . selected($view, 'issues', false) . '>' . esc_html__('Issues only', 'leadsforward-core') . '</option>';
	echo '</select> ';
	echo '<label for="coverage_type">' . esc_html__('Type', 'leadsforward-core') . ' </label>';
	echo '<select name="coverage_type" id="coverage_type">';
	echo '<option value="">' . esc_html__('All types', 'leadsforward-core') . '</option>';
	foreach ($allowed_types as $t) {
		echo '<option value="' . esc_attr($t) . '"' . selected($type_filter, $t, false) . '>' . esc_html($t) . '</option>';
	}
	echo '</select> ';
	submit_button(__('Filter', 'leadsforward-core'), 'secondary', 'submit', false);
	echo '</form>';

	echo '<table class="widefat striped" style="margin-top:12px;"><thead><tr>';
	echo '<th>' . esc_html__('Title', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Type', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Quality', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Checklist', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Open items', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Actions', 'leadsforward-core') . '</th>';
	echo '</tr></thead><tbody>';
	if ($posts === []) {
		echo '<tr><td colspan="6">';
		echo $view === 'issues'
			? esc_html__('No issues match these filters.', 'leadsforward-core')
			: esc_html__('No published content found.', 'leadsforward-core');
		echo '</td></tr>';
	}
	foreach ($posts as $p) {
		$failed = array_filter((array) ($p['failed_labels'] ?? []));
		$checks = max(1, (int) ($p['checks_total'] ?? 0));
		$fail_n = (int) ($p['failed_count'] ?? 0);
		$passed = $checks - $fail_n;
		$score = (int) ($p['score'] ?? 0);
		$grade = (string) ($p['grade'] ?? '');
		echo '<tr>';
		echo '<td><strong>' . esc_html((string) ($p['title'] ?? '')) . '</strong></td>';
		echo '<td><code>' . esc_html((string) ($p['type'] ?? '')) . '</code></td>';
		echo '<td>';
		if ($score > 0) {
			echo esc_html((string) $score . ($grade !== '' ? ' (' . $grade . ')' : ''));
		} else {
			esc_html_e('Not scored', 'leadsforward-core');
		}
		echo '</td>';
		echo '<td>' . esc_html(sprintf(/* translators: 1 passed, 2 total */ __('✓ %1$d / %2$d', 'leadsforward-core'), $passed, $checks)) . '</td>';
		echo '<td>';
		if ($failed === []) {
			echo '<span style="color:#15803d;font-weight:600;">' . esc_html__('Clear', 'leadsforward-core') . '</span>';
		} else {
			echo esc_html(implode(', ', array_slice($failed, 0, 8)));
			if (count($failed) > 8) {
				echo ' ' . esc_html(sprintf(/* translators: n more */ __('… +%d', 'leadsforward-core'), count($failed) - 8));
			}
		}
		echo '</td>';
		echo '<td>';
		if (!empty($p['edit_url'])) {
			echo '<a href="' . esc_url((string) $p['edit_url']) . '#lf-seo-meta">' . esc_html__('Edit SEO', 'leadsforward-core') . '</a>';
			echo ' ';
		}
		if (!empty($p['view_url'])) {
			echo '<a href="' . esc_url((string) $p['view_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View', 'leadsforward-core') . '</a>';
		}
		echo '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';

	echo '<style>
		.inline.notice{display:inline-block;padding:6px 12px;margin:8px 0;}
		form.lf-seo-coverage-filters{display:flex;flex-wrap:wrap;align-items:center;gap:8px;}
	</style>';
}
