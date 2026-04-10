<?php
/**
 * Internal Link Map (LeadsForward → Internal Link Map).
 *
 * Scans known HTML sources (inline DOM overrides + section settings) and summarizes internal link relationships
 * between pages/services/service areas/posts.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', 'lf_internal_link_map_register_menu', 45);

function lf_internal_link_map_register_menu(): void {
	add_submenu_page(
		null,
		__('Internal Link Map', 'leadsforward-core'),
		__('Internal Link Map', 'leadsforward-core'),
		'edit_theme_options',
		'lf-internal-link-map',
		'lf_internal_link_map_redirect_to_seo_tab'
	);
}

function lf_internal_link_map_redirect_to_seo_tab(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	wp_safe_redirect(admin_url('admin.php?page=lf-seo&tab=links'));
	exit;
}

/**
 * @return list<string>
 */
function lf_internal_link_map_supported_post_types(): array {
	return ['page', 'post', 'lf_service', 'lf_service_area'];
}

/**
 * @return array{host:string, base:string}
 */
function lf_internal_link_map_site_parts(): array {
	$home = home_url('/');
	$parts = wp_parse_url($home);
	$host = is_array($parts) && isset($parts['host']) ? strtolower((string) $parts['host']) : '';
	return ['host' => $host, 'base' => $home];
}

function lf_internal_link_map_is_internal_href(string $href, string $site_host): bool {
	$href = trim($href);
	if ($href === '' || str_starts_with($href, '#')) return false;
	if (preg_match('/^(mailto:|tel:|sms:|javascript:)/i', $href)) return false;
	if (str_starts_with($href, '/')) return true;
	if (!preg_match('/^https?:\/\//i', $href)) return true;
	$p = wp_parse_url($href);
	$host = is_array($p) && isset($p['host']) ? strtolower((string) $p['host']) : '';
	return $host !== '' && $site_host !== '' && $host === $site_host;
}

function lf_internal_link_map_is_external_href(string $href, string $site_host): bool {
	$href = trim($href);
	if (!preg_match('/^https?:\/\//i', $href)) return false;
	$p = wp_parse_url($href);
	$host = is_array($p) && isset($p['host']) ? strtolower((string) $p['host']) : '';
	return $host !== '' && $site_host !== '' && $host !== $site_host;
}

function lf_internal_link_map_normalize_href(string $href, string $site_base): string {
	$href = trim($href);
	if ($href === '') return '';
	if (str_starts_with($href, '/')) {
		$href = rtrim($site_base, '/') . $href;
	} elseif (!preg_match('/^https?:\/\//i', $href) && !str_starts_with($href, '#')) {
		$href = rtrim($site_base, '/') . '/' . ltrim($href, '/');
	}
	$p = wp_parse_url($href);
	if (!is_array($p) || empty($p['host'])) {
		return $href;
	}
	$scheme = isset($p['scheme']) ? (string) $p['scheme'] : 'https';
	$host = (string) ($p['host'] ?? '');
	$path = isset($p['path']) ? (string) $p['path'] : '/';
	$path = $path === '' ? '/' : $path;
	return $scheme . '://' . $host . rtrim($path, '/');
}

/**
 * @return list<string>
 */
function lf_internal_link_map_extract_hrefs(string $html): array {
	$html = (string) $html;
	if ($html === '' || stripos($html, '<a') === false) {
		return [];
	}
	$hrefs = [];
	$prev = libxml_use_internal_errors(true);
	try {
		$doc = new \DOMDocument();
		$wrapped = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
		if (@$doc->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR)) {
			$links = $doc->getElementsByTagName('a');
			foreach ($links as $a) {
				if (!$a instanceof \DOMElement) continue;
				$href = trim((string) $a->getAttribute('href'));
				if ($href !== '') $hrefs[] = $href;
			}
		}
	} catch (\Throwable $e) {
		// ignore
	}
	libxml_clear_errors();
	libxml_use_internal_errors($prev);
	if ($hrefs === [] && preg_match_all('/<a\s+[^>]*href\s*=\s*(["\'])(.*?)\1/i', $html, $m)) {
		foreach ($m[2] as $href) {
			$href = trim((string) $href);
			if ($href !== '') $hrefs[] = $href;
		}
	}
	return array_values(array_unique(array_filter(array_map('strval', $hrefs))));
}

/**
 * @return list<string>
 */
function lf_internal_link_map_collect_post_html_blobs(int $pid): array {
	$html_blobs = [];
	$post = get_post($pid);
	if (!$post instanceof \WP_Post) {
		return [];
	}
	if (function_exists('lf_ai_get_inline_dom_overrides')) {
		$ov = lf_ai_get_inline_dom_overrides((string) $post->post_type, (string) $pid);
		if (is_array($ov)) {
			foreach ($ov as $value) {
				if (is_string($value) && $value !== '') {
					$html_blobs[] = $value;
				}
			}
		}
	}
	if (defined('LF_PB_META_KEY')) {
		$pb = get_post_meta($pid, LF_PB_META_KEY, true);
		if (is_array($pb) && isset($pb['sections']) && is_array($pb['sections'])) {
			foreach ($pb['sections'] as $row) {
				if (!is_array($row)) continue;
				$settings = $row['settings'] ?? null;
				if (!is_array($settings)) continue;
				foreach ($settings as $v) {
					if (is_string($v) && stripos($v, '<a') !== false) {
						$html_blobs[] = $v;
					}
				}
			}
		}
	}
	return $html_blobs;
}

/**
 * @return list<string>
 */
function lf_internal_link_map_collect_homepage_html_blobs(): array {
	$home_blobs = [];
	if (function_exists('lf_ai_get_inline_dom_overrides')) {
		$ov = lf_ai_get_inline_dom_overrides('homepage', 'homepage');
		if (is_array($ov)) {
			foreach ($ov as $value) {
				if (is_string($value) && $value !== '') {
					$home_blobs[] = $value;
				}
			}
		}
	}
	if (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
		$cfg = get_option(LF_HOMEPAGE_CONFIG_OPTION, []);
		if (is_array($cfg)) {
			foreach ($cfg as $row) {
				if (!is_array($row)) continue;
				foreach ($row as $v) {
					if (is_string($v) && stripos($v, '<a') !== false) {
						$home_blobs[] = $v;
					}
				}
			}
		}
	}
	return $home_blobs;
}

/**
 * @return array{
 *   internal_outbound: array<int, array<int,int>>,
 *   external_outbound: array<int, array<string,int>>,
 *   broken: array<int, list<string>>
 * }
 */
function lf_internal_link_map_scan(): array {
	$site = lf_internal_link_map_site_parts();
	$site_host = $site['host'];
	$site_base = $site['base'];
	$internal_outbound = [];
	$external_outbound = [];
	$broken = [];

	$post_types = lf_internal_link_map_supported_post_types();
	$ids = get_posts([
		'post_type' => $post_types,
		'post_status' => ['publish', 'draft'],
		'posts_per_page' => 5000,
		'fields' => 'ids',
	]);

	foreach ($ids as $pid_raw) {
		$pid = (int) $pid_raw;
		foreach (lf_internal_link_map_collect_post_html_blobs($pid) as $blob) {
			foreach (lf_internal_link_map_extract_hrefs($blob) as $href) {
				if (lf_internal_link_map_is_internal_href($href, $site_host)) {
					$norm = lf_internal_link_map_normalize_href($href, $site_base);
					$target_id = url_to_postid($norm);
					if ($target_id > 0) {
						$internal_outbound[$pid] ??= [];
						$internal_outbound[$pid][$target_id] = (int) (($internal_outbound[$pid][$target_id] ?? 0) + 1);
					} else {
						$broken[$pid] ??= [];
						$broken[$pid][] = $norm;
					}
				} elseif (lf_internal_link_map_is_external_href($href, $site_host)) {
					$norm = lf_internal_link_map_normalize_href($href, $site_base);
					$external_outbound[$pid] ??= [];
					$external_outbound[$pid][$norm] = (int) (($external_outbound[$pid][$norm] ?? 0) + 1);
				}
			}
		}
	}

	$home_post_id = (int) get_option('page_on_front');
	if ($home_post_id > 0) {
		foreach (lf_internal_link_map_collect_homepage_html_blobs() as $blob) {
			foreach (lf_internal_link_map_extract_hrefs($blob) as $href) {
				if (lf_internal_link_map_is_internal_href($href, $site_host)) {
					$norm = lf_internal_link_map_normalize_href($href, $site_base);
					$target_id = url_to_postid($norm);
					if ($target_id > 0) {
						$internal_outbound[$home_post_id] ??= [];
						$internal_outbound[$home_post_id][$target_id] = (int) (($internal_outbound[$home_post_id][$target_id] ?? 0) + 1);
					} else {
						$broken[$home_post_id] ??= [];
						$broken[$home_post_id][] = $norm;
					}
				} elseif (lf_internal_link_map_is_external_href($href, $site_host)) {
					$norm = lf_internal_link_map_normalize_href($href, $site_base);
					$external_outbound[$home_post_id] ??= [];
					$external_outbound[$home_post_id][$norm] = (int) (($external_outbound[$home_post_id][$norm] ?? 0) + 1);
				}
			}
		}
	}

	foreach ($broken as $src => $hrefs) {
		$broken[$src] = array_values(array_unique($hrefs));
	}

	return [
		'internal_outbound' => $internal_outbound,
		'external_outbound' => $external_outbound,
		'broken' => $broken,
	];
}

function lf_internal_link_map_render_embedded_ui(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$type_filter = isset($_GET['post_type']) ? sanitize_key((string) $_GET['post_type']) : '';
	$q = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
	$focus_id = isset($_GET['link_page_id']) ? absint($_GET['link_page_id']) : 0;
	$sort = isset($_GET['sort']) ? sanitize_key((string) $_GET['sort']) : 'issues';
	$issues_only = isset($_GET['issues_only']) && $_GET['issues_only'] === '1';
	$scan = lf_internal_link_map_scan();
	$outbound_internal = is_array($scan['internal_outbound'] ?? null) ? $scan['internal_outbound'] : [];
	$outbound_external = is_array($scan['external_outbound'] ?? null) ? $scan['external_outbound'] : [];
	$broken = is_array($scan['broken'] ?? null) ? $scan['broken'] : [];
	$inbound_counts = [];
	$inbound_sources = [];
	foreach ($outbound_internal as $src => $targets) {
		foreach ($targets as $tid => $count) {
			$inbound_counts[$tid] = (int) (($inbound_counts[$tid] ?? 0) + 1);
			$inbound_sources[$tid] ??= [];
			$inbound_sources[$tid][$src] = (int) (($inbound_sources[$tid][$src] ?? 0) + (int) $count);
		}
	}

	$post_types = lf_internal_link_map_supported_post_types();
	$home_post_id = (int) get_option('page_on_front');
	$all_ids = get_posts([
		'post_type' => $post_types,
		'post_status' => ['publish', 'draft'],
		'posts_per_page' => 5000,
		'fields' => 'ids',
	]);
	if ($home_post_id > 0 && !in_array($home_post_id, $all_ids, true)) {
		$all_ids[] = $home_post_id;
	}

	$rows = [];
	$total_internal_edges = 0;
	$total_external_edges = 0;
	foreach ($all_ids as $pid_raw) {
		$pid = (int) $pid_raw;
		$post = get_post($pid);
		if (!$post instanceof \WP_Post) continue;
		if ($type_filter !== '' && $post->post_type !== $type_filter) continue;
		$title = $pid === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title($pid);
		if ($q !== '' && stripos($title, $q) === false) continue;
		$out_internal = isset($outbound_internal[$pid]) ? count($outbound_internal[$pid]) : 0;
		$out_external = isset($outbound_external[$pid]) ? count($outbound_external[$pid]) : 0;
		$in_count = (int) ($inbound_counts[$pid] ?? 0);
		$broken_count = isset($broken[$pid]) ? count($broken[$pid]) : 0;
		$total_internal_edges += $out_internal;
		$total_external_edges += $out_external;
		$rows[] = [
			'id' => $pid,
			'title' => $title !== '' ? $title : ('#' . $pid),
			'type' => (string) $post->post_type,
			'out_internal' => $out_internal,
			'out_external' => $out_external,
			'in' => $in_count,
			'broken' => $broken_count,
			'issues' => ($in_count === 0 || $out_internal === 0 || $broken_count > 0) ? 1 : 0,
		];
	}

	if ($issues_only) {
		$rows = array_values(array_filter($rows, static fn(array $r): bool => (int) ($r['issues'] ?? 0) === 1));
	}

	usort($rows, static function (array $a, array $b) use ($sort): int {
		switch ($sort) {
			case 'title':
				return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
			case 'internal_out_desc':
				return ((int) ($b['out_internal'] ?? 0) <=> (int) ($a['out_internal'] ?? 0));
			case 'external_out_desc':
				return ((int) ($b['out_external'] ?? 0) <=> (int) ($a['out_external'] ?? 0));
			case 'inbound_desc':
				return ((int) ($b['in'] ?? 0) <=> (int) ($a['in'] ?? 0));
			case 'broken_desc':
				return ((int) ($b['broken'] ?? 0) <=> (int) ($a['broken'] ?? 0));
			case 'issues':
			default:
				$ai = (int) ($a['issues'] ?? 0);
				$bi = (int) ($b['issues'] ?? 0);
				if ($ai !== $bi) {
					return $bi <=> $ai;
				}
				$ab = (int) ($b['broken'] ?? 0) <=> (int) ($a['broken'] ?? 0);
				if ($ab !== 0) return $ab;
				return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
		}
	});

	$orphans = array_filter($rows, static fn($r) => (int) ($r['in'] ?? 0) === 0);
	$base_page = isset($_GET['page']) && $_GET['page'] === 'lf-seo' ? 'lf-seo' : 'lf-internal-link-map';
	$base_args = ['page' => $base_page];
	if ($base_page === 'lf-seo') {
		$base_args['tab'] = 'links';
	}

	echo '<h2>' . esc_html__('Internal Link Map', 'leadsforward-core') . '</h2>';
	echo '<p>' . esc_html__('Review how pages connect, spot orphaned pages, and compare internal vs outbound external links.', 'leadsforward-core') . '</p>';
	echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:12px 0 18px;">';
	echo '<div class="notice notice-info" style="margin:0;padding:8px 10px;"><strong>' . esc_html__('Pages scanned:', 'leadsforward-core') . '</strong> ' . esc_html((string) count($rows)) . '</div>';
	echo '<div class="notice notice-warning" style="margin:0;padding:8px 10px;"><strong>' . esc_html__('Orphans:', 'leadsforward-core') . '</strong> ' . esc_html((string) count($orphans)) . '</div>';
	echo '<div class="notice notice-success" style="margin:0;padding:8px 10px;"><strong>' . esc_html__('Internal links (unique):', 'leadsforward-core') . '</strong> ' . esc_html((string) $total_internal_edges) . '</div>';
	echo '<div class="notice notice-info" style="margin:0;padding:8px 10px;"><strong>' . esc_html__('External links (unique):', 'leadsforward-core') . '</strong> ' . esc_html((string) $total_external_edges) . '</div>';
	echo '</div>';

	echo '<form method="get" style="margin:12px 0 18px;">';
	foreach ($base_args as $k => $v) {
		echo '<input type="hidden" name="' . esc_attr((string) $k) . '" value="' . esc_attr((string) $v) . '" />';
	}
	echo '<label for="lf-ilm-s" class="screen-reader-text">' . esc_html__('Search', 'leadsforward-core') . '</label>';
	echo '<input id="lf-ilm-s" type="search" name="s" value="' . esc_attr($q) . '" placeholder="' . esc_attr__('Search pages…', 'leadsforward-core') . '" style="min-width:280px;" />';
	echo '&nbsp;';
	echo '<select name="post_type">';
	echo '<option value="">' . esc_html__('All types', 'leadsforward-core') . '</option>';
	foreach ($post_types as $pt) {
		echo '<option value="' . esc_attr($pt) . '"' . selected($type_filter, $pt, false) . '>' . esc_html($pt) . '</option>';
	}
	echo '</select>';
	echo '&nbsp;';
	echo '<select name="sort">';
	$sort_options = [
		'issues' => __('Most issues first', 'leadsforward-core'),
		'inbound_desc' => __('Highest inbound', 'leadsforward-core'),
		'internal_out_desc' => __('Highest internal outbound', 'leadsforward-core'),
		'external_out_desc' => __('Highest external outbound', 'leadsforward-core'),
		'broken_desc' => __('Most broken links', 'leadsforward-core'),
		'title' => __('A-Z title', 'leadsforward-core'),
	];
	foreach ($sort_options as $sort_value => $sort_label) {
		echo '<option value="' . esc_attr($sort_value) . '"' . selected($sort, $sort_value, false) . '>' . esc_html((string) $sort_label) . '</option>';
	}
	echo '</select>';
	echo '&nbsp;';
	echo '<label style="display:inline-flex;align-items:center;gap:4px;"><input type="checkbox" name="issues_only" value="1"' . checked($issues_only, true, false) . ' /> ' . esc_html__('Issues only', 'leadsforward-core') . '</label>';
	echo '&nbsp;';
	submit_button(__('Filter', 'leadsforward-core'), 'secondary', '', false);
	echo '</form>';

	echo '<table class="widefat striped">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__('Page', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Type', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Internal outbound', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('External outbound', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Inbound', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Broken internal', 'leadsforward-core') . '</th>';
	echo '</tr></thead><tbody>';
	foreach ($rows as $r) {
		$pid = (int) $r['id'];
		$title = (string) $r['title'];
		$type = (string) $r['type'];
		$out_internal = (int) $r['out_internal'];
		$out_external = (int) $r['out_external'];
		$in = (int) $r['in'];
		$br = (int) $r['broken'];
		$is_orphan = $in === 0;
		$edit = get_edit_post_link($pid, '');
		$view = get_permalink($pid);
		$detail_url = add_query_arg(
			array_merge($base_args, ['post_type' => $type_filter, 's' => $q, 'sort' => $sort, 'issues_only' => $issues_only ? '1' : '0', 'link_page_id' => $pid]),
			admin_url('admin.php')
		);

		echo '<tr>';
		echo '<td>';
		echo '<a href="' . esc_url($detail_url) . '">' . ($is_orphan ? '<strong>' . esc_html($title) . '</strong>' : esc_html($title)) . '</a>';
		echo '<div style="margin-top:4px;display:flex;gap:10px;">';
		if ($edit) echo '<a href="' . esc_url($edit) . '">' . esc_html__('Edit', 'leadsforward-core') . '</a>';
		if ($view) echo '<a href="' . esc_url($view) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View', 'leadsforward-core') . '</a>';
		echo '</div>';
		echo '</td>';
		echo '<td>' . esc_html($type) . '</td>';
		echo '<td>' . esc_html((string) $out_internal) . '</td>';
		echo '<td>' . esc_html((string) $out_external) . '</td>';
		echo '<td>' . esc_html((string) $in) . ($is_orphan ? ' <span class="dashicons dashicons-warning" title="' . esc_attr__('Orphan', 'leadsforward-core') . '"></span>' : '') . '</td>';
		echo '<td>' . esc_html((string) $br) . '</td>';
		echo '</tr>';
	}
	if ($rows === []) {
		echo '<tr><td colspan="6">' . esc_html__('No pages found for the current filters.', 'leadsforward-core') . '</td></tr>';
	}
	echo '</tbody></table>';

	$edge_rows = [];
	foreach ($outbound_internal as $src => $targets) {
		foreach ($targets as $tid => $count) {
			$src_title = $src === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title((int) $src);
			$tgt_title = $tid === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title((int) $tid);
			$edge_rows[] = ['src' => (int) $src, 'src_title' => $src_title, 'tid' => (int) $tid, 'tgt_title' => $tgt_title, 'count' => (int) $count];
		}
	}
	usort($edge_rows, static fn(array $a, array $b): int => ((int) $b['count']) <=> ((int) $a['count']));
	echo '<h3 style="margin-top:22px;">' . esc_html__('Connection Flow', 'leadsforward-core') . '</h3>';
	echo '<p class="description">' . esc_html__('Top internal link paths by frequency (source -> destination).', 'leadsforward-core') . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('From', 'leadsforward-core') . '</th><th>' . esc_html__('To', 'leadsforward-core') . '</th><th>' . esc_html__('Links', 'leadsforward-core') . '</th></tr></thead><tbody>';
	$edge_limit = min(100, count($edge_rows));
	for ($i = 0; $i < $edge_limit; $i++) {
		$e = $edge_rows[$i];
		echo '<tr><td>' . esc_html((string) $e['src_title']) . '</td><td>' . esc_html((string) $e['tgt_title']) . '</td><td>' . esc_html((string) $e['count']) . '</td></tr>';
	}
	if ($edge_limit === 0) {
		echo '<tr><td colspan="3">' . esc_html__('No internal link paths found.', 'leadsforward-core') . '</td></tr>';
	}
	echo '</tbody></table>';

	if ($focus_id > 0) {
		$focus_post = get_post($focus_id);
		if ($focus_post instanceof \WP_Post) {
			$focus_title = $focus_id === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title($focus_id);
			echo '<h3 style="margin-top:22px;">' . esc_html(sprintf(__('Page Details: %s', 'leadsforward-core'), $focus_title)) . '</h3>';
			echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">';

			echo '<div class="card"><h4 style="margin-top:0;">' . esc_html__('Outbound Internal', 'leadsforward-core') . '</h4><ul style="margin-left:1rem;">';
			$targets = $outbound_internal[$focus_id] ?? [];
			if (is_array($targets) && !empty($targets)) {
				foreach ($targets as $tid => $count) {
					$t = (int) $tid;
					$t_title = $t === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title($t);
					echo '<li>' . esc_html($t_title) . ' (' . esc_html((string) $count) . ')</li>';
				}
			} else {
				echo '<li>' . esc_html__('No internal outbound links found.', 'leadsforward-core') . '</li>';
			}
			echo '</ul></div>';

			echo '<div class="card"><h4 style="margin-top:0;">' . esc_html__('Outbound External', 'leadsforward-core') . '</h4><ul style="margin-left:1rem;">';
			$ext = $outbound_external[$focus_id] ?? [];
			if (is_array($ext) && !empty($ext)) {
				foreach ($ext as $url => $count) {
					echo '<li><a href="' . esc_url((string) $url) . '" target="_blank" rel="noopener noreferrer">' . esc_html((string) $url) . '</a> (' . esc_html((string) $count) . ')</li>';
				}
			} else {
				echo '<li>' . esc_html__('No external outbound links found.', 'leadsforward-core') . '</li>';
			}
			echo '</ul></div>';

			echo '<div class="card"><h4 style="margin-top:0;">' . esc_html__('Inbound Sources', 'leadsforward-core') . '</h4><ul style="margin-left:1rem;">';
			$srcs = $inbound_sources[$focus_id] ?? [];
			if (is_array($srcs) && !empty($srcs)) {
				foreach ($srcs as $src_id => $count) {
					$sid = (int) $src_id;
					$s_title = $sid === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title($sid);
					echo '<li>' . esc_html($s_title) . ' (' . esc_html((string) $count) . ')</li>';
				}
			} else {
				echo '<li>' . esc_html__('No inbound internal links found.', 'leadsforward-core') . '</li>';
			}
			echo '</ul></div>';

			echo '<div class="card"><h4 style="margin-top:0;">' . esc_html__('Broken Internal URLs', 'leadsforward-core') . '</h4><ul style="margin-left:1rem;">';
			$brk = $broken[$focus_id] ?? [];
			if (is_array($brk) && !empty($brk)) {
				foreach ($brk as $href) {
					echo '<li>' . esc_html((string) $href) . '</li>';
				}
			} else {
				echo '<li>' . esc_html__('No broken internal URLs found.', 'leadsforward-core') . '</li>';
			}
			echo '</ul></div>';

			echo '<div class="card"><h4 style="margin-top:0;">' . esc_html__('Suggested Internal Link Targets', 'leadsforward-core') . '</h4><ul style="margin-left:1rem;">';
			$existing_targets = [];
			$focus_targets = $outbound_internal[$focus_id] ?? [];
			if (is_array($focus_targets)) {
				foreach (array_keys($focus_targets) as $k) {
					$existing_targets[(int) $k] = true;
				}
			}
			$candidates = [];
			foreach ($rows as $candidate) {
				$cid = (int) ($candidate['id'] ?? 0);
				if ($cid <= 0 || $cid === $focus_id) continue;
				if (($candidate['type'] ?? '') !== $focus_post->post_type) continue;
				if (isset($existing_targets[$cid])) continue;
				$candidates[] = $candidate;
			}
			usort($candidates, static fn(array $a, array $b): int => ((int) ($b['in'] ?? 0)) <=> ((int) ($a['in'] ?? 0)));
			$shown = 0;
			foreach ($candidates as $candidate) {
				if ($shown >= 5) break;
				$cid = (int) ($candidate['id'] ?? 0);
				$ct = (string) ($candidate['title'] ?? ('#' . $cid));
				$cin = (int) ($candidate['in'] ?? 0);
				echo '<li>' . esc_html($ct) . ' (' . esc_html(sprintf(__('Inbound %d', 'leadsforward-core'), $cin)) . ')</li>';
				$shown++;
			}
			if ($shown === 0) {
				echo '<li>' . esc_html__('No obvious same-type opportunities right now.', 'leadsforward-core') . '</li>';
			}
			echo '</ul></div>';
			echo '</div>';
		}
	}
}

function lf_internal_link_map_render_page(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	echo '<div class="wrap">';
	lf_internal_link_map_render_embedded_ui();
	echo '</div>';
}

