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
		'lf-ops',
		__('Internal Link Map', 'leadsforward-core'),
		__('Internal Link Map', 'leadsforward-core'),
		'edit_theme_options',
		'lf-internal-link-map',
		'lf_internal_link_map_render_page'
	);
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
	if ($href === '') return false;
	if (str_starts_with($href, '#')) return false;
	if (preg_match('/^(mailto:|tel:|sms:|javascript:)/i', $href)) return false;
	if (str_starts_with($href, '/')) return true;
	if (!preg_match('/^https?:\/\//i', $href)) {
		// Relative path like "about-us" is treated as internal-ish.
		return true;
	}
	$p = wp_parse_url($href);
	$host = is_array($p) && isset($p['host']) ? strtolower((string) $p['host']) : '';
	return $host !== '' && $site_host !== '' && $host === $site_host;
}

function lf_internal_link_map_normalize_href(string $href, string $site_base): string {
	$href = trim($href);
	if ($href === '') return '';
	// Strip surrounding whitespace and normalize relative paths.
	if (str_starts_with($href, '/')) {
		$href = rtrim($site_base, '/') . $href;
	} elseif (!preg_match('/^https?:\/\//i', $href) && !str_starts_with($href, '#')) {
		$href = rtrim($site_base, '/') . '/' . ltrim($href, '/');
	}
	// Drop query + fragment for mapping (keep canonical page-to-page).
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
 * @return list<string> hrefs
 */
function lf_internal_link_map_extract_hrefs(string $html): array {
	$html = (string) $html;
	if ($html === '' || stripos($html, '<a') === false) {
		return [];
	}

	$hrefs = [];
	// Prefer DOMDocument for robustness, fall back to regex if it fails.
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

	if ($hrefs === []) {
		if (preg_match_all('/<a\s+[^>]*href\s*=\s*(["\'])(.*?)\1/i', $html, $m)) {
			foreach ($m[2] as $href) {
				$href = trim((string) $href);
				if ($href !== '') $hrefs[] = $href;
			}
		}
	}

	$hrefs = array_values(array_unique(array_filter(array_map('strval', $hrefs))));
	return $hrefs;
}

/**
 * @return array{outbound: array<int, array<int,int>>, broken: array<int, list<string>>}
 */
function lf_internal_link_map_scan(): array {
	$site = lf_internal_link_map_site_parts();
	$site_host = $site['host'];
	$site_base = $site['base'];

	$outbound = [];
	$broken = [];

	$post_types = lf_internal_link_map_supported_post_types();
	$ids = get_posts([
		'post_type' => $post_types,
		'post_status' => ['publish', 'draft'],
		'posts_per_page' => 5000,
		'fields' => 'ids',
	]);

	foreach ($ids as $pid) {
		$pid = (int) $pid;
		$post = get_post($pid);
		if (!$post instanceof \WP_Post) continue;

		$html_blobs = [];

		// 1) Inline DOM overrides (selector-based).
		if (function_exists('lf_ai_get_inline_dom_overrides')) {
			$ov = lf_ai_get_inline_dom_overrides((string) $post->post_type, (string) $pid);
			if (is_array($ov)) {
				foreach ($ov as $selector => $value) {
					if (!is_string($value) || $value === '') continue;
					$html_blobs[] = $value;
				}
			}
		}

		// 2) Page Builder sections settings.
		if (defined('LF_PB_META_KEY')) {
			$pb = get_post_meta($pid, LF_PB_META_KEY, true);
			if (is_array($pb) && isset($pb['sections']) && is_array($pb['sections'])) {
				foreach ($pb['sections'] as $section_id => $row) {
					if (!is_array($row)) continue;
					$settings = $row['settings'] ?? null;
					if (!is_array($settings)) continue;
					foreach ($settings as $k => $v) {
						if (is_string($v) && stripos($v, '<a') !== false) {
							$html_blobs[] = $v;
						}
					}
				}
			}
		}

		if ($html_blobs === []) {
			continue;
		}

		foreach ($html_blobs as $blob) {
			foreach (lf_internal_link_map_extract_hrefs($blob) as $href) {
				if (!lf_internal_link_map_is_internal_href($href, $site_host)) {
					continue;
				}
				$norm = lf_internal_link_map_normalize_href($href, $site_base);
				$target_id = url_to_postid($norm);
				if ($target_id > 0) {
					$outbound[$pid] ??= [];
					$outbound[$pid][$target_id] = (int) (($outbound[$pid][$target_id] ?? 0) + 1);
				} else {
					$broken[$pid] ??= [];
					$broken[$pid][] = $norm;
				}
			}
		}
	}

	// Homepage (context_id = homepage) inline overrides + homepage section config.
	$home_post_id = (int) get_option('page_on_front');
	if ($home_post_id > 0) {
		$home_blobs = [];
		if (function_exists('lf_ai_get_inline_dom_overrides')) {
			$ov = lf_ai_get_inline_dom_overrides('homepage', 'homepage');
			if (is_array($ov)) {
				foreach ($ov as $selector => $value) {
					if (!is_string($value) || $value === '') continue;
					$home_blobs[] = $value;
				}
			}
		}
		if (defined('LF_HOMEPAGE_CONFIG_OPTION')) {
			$cfg = get_option(LF_HOMEPAGE_CONFIG_OPTION, []);
			if (is_array($cfg)) {
				foreach ($cfg as $sid => $row) {
					if (!is_array($row)) continue;
					foreach ($row as $k => $v) {
						if (is_string($v) && stripos($v, '<a') !== false) {
							$home_blobs[] = $v;
						}
					}
				}
			}
		}
		foreach ($home_blobs as $blob) {
			foreach (lf_internal_link_map_extract_hrefs($blob) as $href) {
				if (!lf_internal_link_map_is_internal_href($href, $site_host)) continue;
				$norm = lf_internal_link_map_normalize_href($href, $site_base);
				$target_id = url_to_postid($norm);
				if ($target_id > 0) {
					$outbound[$home_post_id] ??= [];
					$outbound[$home_post_id][$target_id] = (int) (($outbound[$home_post_id][$target_id] ?? 0) + 1);
				} else {
					$broken[$home_post_id] ??= [];
					$broken[$home_post_id][] = $norm;
				}
			}
		}
	}

	// Dedup broken hrefs per source.
	foreach ($broken as $src => $hrefs) {
		$broken[$src] = array_values(array_unique($hrefs));
	}

	return ['outbound' => $outbound, 'broken' => $broken];
}

function lf_internal_link_map_render_page(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}

	$type_filter = isset($_GET['post_type']) ? sanitize_key((string) $_GET['post_type']) : '';
	$q = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';

	$scan = lf_internal_link_map_scan();
	$outbound = $scan['outbound'];
	$broken = $scan['broken'];

	// Build inbound counts.
	$inbound_counts = [];
	foreach ($outbound as $src => $targets) {
		foreach ($targets as $tid => $count) {
			$inbound_counts[$tid] = (int) (($inbound_counts[$tid] ?? 0) + 1);
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
	foreach ($all_ids as $pid) {
		$pid = (int) $pid;
		$post = get_post($pid);
		if (!$post instanceof \WP_Post) continue;
		if ($type_filter !== '' && $post->post_type !== $type_filter) continue;
		$title = $pid === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title($pid);
		if ($q !== '' && stripos($title, $q) === false) continue;

		$out_count = isset($outbound[$pid]) ? count($outbound[$pid]) : 0;
		$in_count = (int) ($inbound_counts[$pid] ?? 0);
		$broken_count = isset($broken[$pid]) ? count($broken[$pid]) : 0;
		$rows[] = [
			'id' => $pid,
			'title' => $title !== '' ? $title : ('#' . $pid),
			'type' => (string) $post->post_type,
			'out' => $out_count,
			'in' => $in_count,
			'broken' => $broken_count,
		];
	}

	usort($rows, static function (array $a, array $b): int {
		// Orphans first, then by broken desc, then title.
		$ao = (int) ($a['in'] ?? 0) === 0 ? 0 : 1;
		$bo = (int) ($b['in'] ?? 0) === 0 ? 0 : 1;
		if ($ao !== $bo) return $ao <=> $bo;
		$ab = (int) ($b['broken'] ?? 0) <=> (int) ($a['broken'] ?? 0);
		if ($ab !== 0) return $ab;
		return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
	});

	$orphans = array_filter($rows, static fn($r) => (int) ($r['in'] ?? 0) === 0);

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Internal Link Map', 'leadsforward-core') . '</h1>';
	echo '<p>' . esc_html__('Shows internal link relationships extracted from inline overrides and section settings.', 'leadsforward-core') . '</p>';

	echo '<div style="display:flex;gap:18px;flex-wrap:wrap;margin:12px 0 18px;">';
	echo '<div class="notice notice-info" style="margin:0;padding:10px 12px;"><strong>' . esc_html__('Pages scanned:', 'leadsforward-core') . '</strong> ' . esc_html((string) count($rows)) . '</div>';
	echo '<div class="notice notice-warning" style="margin:0;padding:10px 12px;"><strong>' . esc_html__('Orphans:', 'leadsforward-core') . '</strong> ' . esc_html((string) count($orphans)) . '</div>';
	echo '</div>';

	echo '<form method="get" style="margin:12px 0 18px;">';
	echo '<input type="hidden" name="page" value="lf-internal-link-map" />';
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
	submit_button(__('Filter', 'leadsforward-core'), 'secondary', '', false);
	echo '</form>';

	echo '<table class="widefat striped">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__('Page', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Type', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Outbound (unique)', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Inbound', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Broken internal', 'leadsforward-core') . '</th>';
	echo '</tr></thead><tbody>';

	foreach ($rows as $r) {
		$pid = (int) $r['id'];
		$title = (string) $r['title'];
		$type = (string) $r['type'];
		$out = (int) $r['out'];
		$in = (int) $r['in'];
		$br = (int) $r['broken'];

		$edit = get_edit_post_link($pid, '');
		$view = get_permalink($pid);
		$is_orphan = $in === 0;

		echo '<tr>';
		echo '<td>';
		echo $is_orphan ? '<strong>' . esc_html($title) . '</strong>' : esc_html($title);
		echo '<div style="margin-top:4px;display:flex;gap:10px;">';
		if ($edit) echo '<a href="' . esc_url($edit) . '">' . esc_html__('Edit', 'leadsforward-core') . '</a>';
		if ($view) echo '<a href="' . esc_url($view) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View', 'leadsforward-core') . '</a>';
		echo '</div>';
		echo '</td>';
		echo '<td>' . esc_html($type) . '</td>';
		echo '<td>' . esc_html((string) $out) . '</td>';
		echo '<td>' . esc_html((string) $in) . ($is_orphan ? ' <span class="dashicons dashicons-warning" title="' . esc_attr__('Orphan', 'leadsforward-core') . '"></span>' : '') . '</td>';
		echo '<td>' . esc_html((string) $br) . '</td>';
		echo '</tr>';
	}

	if ($rows === []) {
		echo '<tr><td colspan="5">' . esc_html__('No pages found for the current filters.', 'leadsforward-core') . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '</div>';
}

