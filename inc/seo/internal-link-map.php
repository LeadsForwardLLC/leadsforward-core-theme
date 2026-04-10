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
add_action('admin_post_lf_internal_link_map_apply_suggestion', 'lf_internal_link_map_apply_suggestion_action');

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
 * @return list<array{href:string,anchor:string}>
 */
function lf_internal_link_map_extract_links(string $html): array {
	$html = (string) $html;
	if ($html === '' || stripos($html, '<a') === false) {
		return [];
	}
	$results = [];
	$prev = libxml_use_internal_errors(true);
	try {
		$doc = new \DOMDocument();
		$wrapped = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
		if (@$doc->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR)) {
			$link_nodes = $doc->getElementsByTagName('a');
			foreach ($link_nodes as $a) {
				if (!$a instanceof \DOMElement) continue;
				$href = trim((string) $a->getAttribute('href'));
				if ($href !== '') {
					$anchor = trim((string) $a->textContent);
					$results[] = ['href' => $href, 'anchor' => $anchor];
				}
			}
		}
	} catch (\Throwable $e) {
		// ignore
	}
	libxml_clear_errors();
	libxml_use_internal_errors($prev);
	if ($results === [] && preg_match_all('/<a\s+[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $m)) {
		foreach ($m[2] as $i => $href) {
			$href = trim((string) $href);
			if ($href !== '') {
				$anchor_raw = isset($m[3][$i]) ? (string) $m[3][$i] : '';
				$anchor = trim(wp_strip_all_tags($anchor_raw));
				$results[] = ['href' => $href, 'anchor' => $anchor];
			}
		}
	}
	return $results;
}

/**
 * @return list<string>
 */
function lf_internal_link_map_extract_hrefs(string $html): array {
	$hrefs = [];
	foreach (lf_internal_link_map_extract_links($html) as $link) {
		$href = trim((string) ($link['href'] ?? ''));
		if ($href !== '') {
			$hrefs[] = $href;
		}
	}
	return array_values(array_unique($hrefs));
}

function lf_internal_link_map_is_weak_anchor(string $anchor): bool {
	$anchor = strtolower(trim(preg_replace('/\s+/', ' ', $anchor)));
	if ($anchor === '') {
		return true;
	}
	$weak = [
		'click here',
		'here',
		'learn more',
		'read more',
		'more',
		'view more',
		'see more',
		'this page',
		'link',
		'visit',
	];
	if (in_array($anchor, $weak, true)) {
		return true;
	}
	return strlen($anchor) <= 3;
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
	if (is_string($post->post_content) && stripos($post->post_content, '<a') !== false) {
		$html_blobs[] = (string) $post->post_content;
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
 *   broken: array<int, list<string>>,
 *   internal_anchor_samples: array<int, array<int, list<string>>>,
 *   weak_internal_anchor_counts: array<int, int>
 * }
 */
function lf_internal_link_map_scan(): array {
	$site = lf_internal_link_map_site_parts();
	$site_host = $site['host'];
	$site_base = $site['base'];
	$internal_outbound = [];
	$external_outbound = [];
	$broken = [];
	$internal_anchor_samples = [];
	$weak_internal_anchor_counts = [];

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
			foreach (lf_internal_link_map_extract_links($blob) as $link) {
				$href = (string) ($link['href'] ?? '');
				$anchor = trim((string) ($link['anchor'] ?? ''));
				if (lf_internal_link_map_is_internal_href($href, $site_host)) {
					$norm = lf_internal_link_map_normalize_href($href, $site_base);
					$target_id = url_to_postid($norm);
					if ($target_id > 0) {
						$internal_outbound[$pid] ??= [];
						$internal_outbound[$pid][$target_id] = (int) (($internal_outbound[$pid][$target_id] ?? 0) + 1);
						if ($anchor !== '') {
							$internal_anchor_samples[$pid] ??= [];
							$internal_anchor_samples[$pid][$target_id] ??= [];
							$internal_anchor_samples[$pid][$target_id][] = $anchor;
						}
					} else {
						$broken[$pid] ??= [];
						$broken[$pid][] = $norm;
					}
					if (lf_internal_link_map_is_weak_anchor($anchor)) {
						$weak_internal_anchor_counts[$pid] = (int) (($weak_internal_anchor_counts[$pid] ?? 0) + 1);
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
			foreach (lf_internal_link_map_extract_links($blob) as $link) {
				$href = (string) ($link['href'] ?? '');
				$anchor = trim((string) ($link['anchor'] ?? ''));
				if (lf_internal_link_map_is_internal_href($href, $site_host)) {
					$norm = lf_internal_link_map_normalize_href($href, $site_base);
					$target_id = url_to_postid($norm);
					if ($target_id > 0) {
						$internal_outbound[$home_post_id] ??= [];
						$internal_outbound[$home_post_id][$target_id] = (int) (($internal_outbound[$home_post_id][$target_id] ?? 0) + 1);
						if ($anchor !== '') {
							$internal_anchor_samples[$home_post_id] ??= [];
							$internal_anchor_samples[$home_post_id][$target_id] ??= [];
							$internal_anchor_samples[$home_post_id][$target_id][] = $anchor;
						}
					} else {
						$broken[$home_post_id] ??= [];
						$broken[$home_post_id][] = $norm;
					}
					if (lf_internal_link_map_is_weak_anchor($anchor)) {
						$weak_internal_anchor_counts[$home_post_id] = (int) (($weak_internal_anchor_counts[$home_post_id] ?? 0) + 1);
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
	foreach ($internal_anchor_samples as $src => $targets) {
		foreach ($targets as $tid => $anchors) {
			$deduped = array_values(array_unique(array_filter(array_map(static fn($a): string => trim((string) $a), $anchors))));
			$internal_anchor_samples[$src][$tid] = array_slice($deduped, 0, 8);
		}
	}

	return [
		'internal_outbound' => $internal_outbound,
		'external_outbound' => $external_outbound,
		'broken' => $broken,
		'internal_anchor_samples' => $internal_anchor_samples,
		'weak_internal_anchor_counts' => $weak_internal_anchor_counts,
	];
}

function lf_internal_link_map_pick_target_id(int $source_id, array $internal_outbound): int {
	$source = get_post($source_id);
	if (!$source instanceof \WP_Post) {
		return 0;
	}
	$supported = lf_internal_link_map_supported_post_types();
	$source_type = (string) $source->post_type;
	$linked = [];
	foreach (array_keys((array) ($internal_outbound[$source_id] ?? [])) as $tid) {
		$linked[(int) $tid] = true;
	}
	$rows = get_posts([
		'post_type' => $supported,
		'post_status' => 'publish',
		'posts_per_page' => 500,
		'orderby' => 'date',
		'order' => 'DESC',
		'fields' => 'ids',
	]);
	if (!is_array($rows) || $rows === []) {
		return 0;
	}
	$inbound_counts = [];
	foreach ($internal_outbound as $src => $targets) {
		foreach ((array) $targets as $tid => $count) {
			$inbound_counts[(int) $tid] = (int) (($inbound_counts[(int) $tid] ?? 0) + 1);
		}
	}
	$candidates = [];
	foreach ($rows as $id_raw) {
		$cid = (int) $id_raw;
		if ($cid <= 0 || $cid === $source_id || isset($linked[$cid])) {
			continue;
		}
		$ctype = (string) get_post_type($cid);
		if (!in_array($ctype, $supported, true)) {
			continue;
		}
		$is_money = in_array($ctype, ['lf_service', 'lf_service_area'], true);
		$score = 0;
		if ($ctype === $source_type) $score += 35;
		if ($is_money) $score += 20;
		$score += (int) (($inbound_counts[$cid] ?? 0) * 3);
		$candidates[] = ['id' => $cid, 'score' => $score];
	}
	if ($candidates === []) {
		return 0;
	}
	usort($candidates, static fn(array $a, array $b): int => ((int) ($b['score'] ?? 0)) <=> ((int) ($a['score'] ?? 0)));
	return (int) ($candidates[0]['id'] ?? 0);
}

/**
 * @return array{content:string,inserted:bool,reason:string}
 */
function lf_internal_link_map_insert_suggested_link(string $content, string $target_url, string $target_title): array {
	$content = (string) $content;
	$target_url = trim($target_url);
	$target_title = trim(wp_strip_all_tags($target_title));
	if ($content === '') {
		return ['content' => $content, 'inserted' => false, 'reason' => 'empty_content'];
	}
	if ($target_url === '' || $target_title === '') {
		return ['content' => $content, 'inserted' => false, 'reason' => 'missing_target'];
	}
	if (stripos($content, $target_url) !== false) {
		return ['content' => $content, 'inserted' => false, 'reason' => 'already_linked'];
	}

	$link_html = '<a href="' . esc_url($target_url) . '">' . esc_html($target_title) . '</a>';
	$inserted = false;
	$updated = preg_replace_callback('/<p\b[^>]*>(.*?)<\/p>/is', static function(array $m) use ($link_html, &$inserted): string {
		$inner = (string) ($m[1] ?? '');
		if ($inserted) return (string) $m[0];
		if (stripos($inner, '<a ') !== false) return (string) $m[0];
		$plain = trim(wp_strip_all_tags($inner));
		if ($plain === '' || strlen($plain) < 35) return (string) $m[0];
		$inserted = true;
		return '<p>' . $inner . ' ' . sprintf(__('See also: %s.', 'leadsforward-core'), $link_html) . '</p>';
	}, $content, 1);
	if (is_string($updated) && $updated !== '' && $inserted) {
		return ['content' => $updated, 'inserted' => true, 'reason' => 'ok'];
	}

	$appended = $content . "\n\n" . '<p>' . sprintf(__('Related page: %s.', 'leadsforward-core'), $link_html) . '</p>';
	return ['content' => $appended, 'inserted' => true, 'reason' => 'ok_append'];
}

function lf_internal_link_map_apply_suggestion_action(): void {
	if (!current_user_can('edit_theme_options')) {
		wp_die(esc_html__('You do not have permission to do that.', 'leadsforward-core'));
	}
	check_admin_referer('lf_internal_link_apply_suggestion');
	$source_id = isset($_POST['source_id']) ? absint($_POST['source_id']) : 0;
	$return_to = isset($_POST['return_to']) ? esc_url_raw((string) $_POST['return_to']) : admin_url('admin.php?page=lf-seo&tab=links');
	if ($source_id <= 0) {
		wp_safe_redirect(add_query_arg(['ilm_notice' => 'invalid_source'], $return_to));
		exit;
	}
	$source = get_post($source_id);
	if (!$source instanceof \WP_Post || $source->post_status !== 'publish') {
		wp_safe_redirect(add_query_arg(['ilm_notice' => 'invalid_source'], $return_to));
		exit;
	}
	$scan = lf_internal_link_map_scan();
	$internal_outbound = (array) ($scan['internal_outbound'] ?? []);
	$target_id = lf_internal_link_map_pick_target_id($source_id, $internal_outbound);
	if ($target_id <= 0) {
		wp_safe_redirect(add_query_arg(['ilm_notice' => 'no_target'], $return_to));
		exit;
	}
	$target_url = get_permalink($target_id);
	$target_title = (string) get_the_title($target_id);
	if (!is_string($target_url) || $target_url === '' || $target_title === '') {
		wp_safe_redirect(add_query_arg(['ilm_notice' => 'no_target'], $return_to));
		exit;
	}
	$current_content = (string) $source->post_content;
	$insert = lf_internal_link_map_insert_suggested_link($current_content, $target_url, $target_title);
	if (!(bool) ($insert['inserted'] ?? false)) {
		wp_safe_redirect(add_query_arg(['ilm_notice' => (string) ($insert['reason'] ?? 'not_inserted')], $return_to));
		exit;
	}
	$updated_content = (string) ($insert['content'] ?? '');
	$result = wp_update_post([
		'ID' => $source_id,
		'post_content' => $updated_content,
	], true);
	if (is_wp_error($result)) {
		wp_safe_redirect(add_query_arg(['ilm_notice' => 'save_failed'], $return_to));
		exit;
	}
	wp_safe_redirect(add_query_arg([
		'ilm_notice' => 'applied',
		'ilm_source' => $source_id,
		'ilm_target' => $target_id,
	], $return_to));
	exit;
}

function lf_internal_link_map_render_embedded_ui(): void {
	if (!current_user_can('edit_theme_options')) {
		return;
	}
	$notice = isset($_GET['ilm_notice']) ? sanitize_key((string) $_GET['ilm_notice']) : '';
	if ($notice !== '') {
		$msg = '';
		$class = 'notice-info';
		if ($notice === 'applied') {
			$src = isset($_GET['ilm_source']) ? absint($_GET['ilm_source']) : 0;
			$tid = isset($_GET['ilm_target']) ? absint($_GET['ilm_target']) : 0;
			$src_title = $src > 0 ? (string) get_the_title($src) : '';
			$target_title = $tid > 0 ? (string) get_the_title($tid) : '';
			$msg = sprintf(__('Applied one suggested internal link on "%1$s" pointing to "%2$s".', 'leadsforward-core'), $src_title !== '' ? $src_title : ('#' . $src), $target_title !== '' ? $target_title : ('#' . $tid));
			$class = 'notice-success';
		} elseif ($notice === 'already_linked') {
			$msg = __('No change made: this target URL already exists in the page content.', 'leadsforward-core');
		} elseif ($notice === 'empty_content') {
			$msg = __('No change made: source page has empty content. Use inline editing/page builder to add link manually.', 'leadsforward-core');
		} elseif ($notice === 'no_target') {
			$msg = __('No eligible suggested target found for this page right now.', 'leadsforward-core');
		} elseif ($notice === 'save_failed') {
			$msg = __('Could not save the post while applying suggestion.', 'leadsforward-core');
			$class = 'notice-error';
		} else {
			$msg = __('Could not apply suggestion for this row.', 'leadsforward-core');
			$class = 'notice-warning';
		}
		echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($msg) . '</p></div>';
	}
	$type_filter = isset($_GET['post_type']) ? sanitize_key((string) $_GET['post_type']) : '';
	$q = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
	$focus_id = isset($_GET['link_page_id']) ? absint($_GET['link_page_id']) : 0;
	$sort = isset($_GET['sort']) ? sanitize_key((string) $_GET['sort']) : 'issues';
	$issues_only = isset($_GET['issues_only']) && $_GET['issues_only'] === '1';
	$quick_filter = isset($_GET['quick']) ? sanitize_key((string) $_GET['quick']) : '';
	$scan = lf_internal_link_map_scan();
	$outbound_internal = is_array($scan['internal_outbound'] ?? null) ? $scan['internal_outbound'] : [];
	$outbound_external = is_array($scan['external_outbound'] ?? null) ? $scan['external_outbound'] : [];
	$broken = is_array($scan['broken'] ?? null) ? $scan['broken'] : [];
	$internal_anchor_samples = is_array($scan['internal_anchor_samples'] ?? null) ? $scan['internal_anchor_samples'] : [];
	$weak_internal_anchor_counts = is_array($scan['weak_internal_anchor_counts'] ?? null) ? $scan['weak_internal_anchor_counts'] : [];
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
		$weak_anchor_count = (int) ($weak_internal_anchor_counts[$pid] ?? 0);
		$is_money_page = in_array((string) $post->post_type, ['lf_service', 'lf_service_area'], true);
		$lead_score = 100;
		if ($in_count === 0) {
			$lead_score -= 40;
		}
		if ($out_internal === 0) {
			$lead_score -= 25;
		}
		if ($broken_count > 0) {
			$lead_score -= min(25, $broken_count * 10);
		}
		if ($weak_anchor_count > 0) {
			$lead_score -= min(20, $weak_anchor_count * 4);
		}
		if ($is_money_page && $in_count < 2) {
			$lead_score -= 10;
		}
		$lead_score = max(0, $lead_score);
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
			'weak_anchors' => $weak_anchor_count,
			'lead_score' => $lead_score,
			'is_money_page' => $is_money_page ? 1 : 0,
			'issues' => ($in_count === 0 || $out_internal === 0 || $broken_count > 0 || $weak_anchor_count > 0) ? 1 : 0,
		];
	}
	$total_weak_anchors = array_reduce($rows, static fn(int $carry, array $row): int => $carry + (int) ($row['weak_anchors'] ?? 0), 0);

	if ($issues_only) {
		$rows = array_values(array_filter($rows, static fn(array $r): bool => (int) ($r['issues'] ?? 0) === 1));
	}
	if ($quick_filter !== '') {
		$rows = array_values(array_filter($rows, static function (array $r) use ($quick_filter): bool {
			switch ($quick_filter) {
				case 'orphans':
					return (int) ($r['in'] ?? 0) === 0;
				case 'no_out':
					return (int) ($r['out_internal'] ?? 0) === 0;
				case 'broken':
					return (int) ($r['broken'] ?? 0) > 0;
				case 'weak':
					return (int) ($r['weak_anchors'] ?? 0) > 0;
				case 'money':
					return (int) ($r['is_money_page'] ?? 0) === 1;
				default:
					return true;
			}
		}));
	}

	usort($rows, static function (array $a, array $b) use ($sort): int {
		switch ($sort) {
			case 'title':
				return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
			case 'lead_score_asc':
				return ((int) ($a['lead_score'] ?? 0) <=> (int) ($b['lead_score'] ?? 0));
			case 'internal_out_desc':
				return ((int) ($b['out_internal'] ?? 0) <=> (int) ($a['out_internal'] ?? 0));
			case 'external_out_desc':
				return ((int) ($b['out_external'] ?? 0) <=> (int) ($a['out_external'] ?? 0));
			case 'inbound_desc':
				return ((int) ($b['in'] ?? 0) <=> (int) ($a['in'] ?? 0));
			case 'weak_anchors_desc':
				return ((int) ($b['weak_anchors'] ?? 0) <=> (int) ($a['weak_anchors'] ?? 0));
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
	$quick_labels = [
		'' => __('All pages', 'leadsforward-core'),
		'orphans' => __('Orphans', 'leadsforward-core'),
		'no_out' => __('No internal out', 'leadsforward-core'),
		'broken' => __('Broken internal', 'leadsforward-core'),
		'weak' => __('Weak anchors', 'leadsforward-core'),
		'money' => __('Money pages', 'leadsforward-core'),
	];

	echo '<h2>' . esc_html__('Internal Link Map', 'leadsforward-core') . '</h2>';
	echo '<p>' . esc_html__('This workspace combines inventory, diagnostics, and lead-generation link strategy in one place.', 'leadsforward-core') . '</p>';
	echo '<p class="description" style="margin-top:-4px;">' . esc_html__('Use "View links" on any row to inspect the full link profile. Focus first on low Lead-flow score pages and money pages.', 'leadsforward-core') . '</p>';
	echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:12px 0 18px;">';
	echo '<div class="notice notice-info" style="margin:0;padding:8px 10px;"><strong>' . esc_html__('Pages scanned:', 'leadsforward-core') . '</strong> ' . esc_html((string) count($rows)) . '</div>';
	echo '<div class="notice notice-warning" style="margin:0;padding:8px 10px;"><strong>' . esc_html__('Orphans:', 'leadsforward-core') . '</strong> ' . esc_html((string) count($orphans)) . '</div>';
	echo '<div class="notice notice-success" style="margin:0;padding:8px 10px;"><strong>' . esc_html__('Internal links (unique):', 'leadsforward-core') . '</strong> ' . esc_html((string) $total_internal_edges) . '</div>';
	echo '<div class="notice notice-info" style="margin:0;padding:8px 10px;"><strong>' . esc_html__('External links (unique):', 'leadsforward-core') . '</strong> ' . esc_html((string) $total_external_edges) . '</div>';
	echo '<div class="notice notice-warning" style="margin:0;padding:8px 10px;"><strong>' . esc_html__('Weak internal anchors:', 'leadsforward-core') . '</strong> ' . esc_html((string) $total_weak_anchors) . '</div>';
	echo '</div>';
	echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 14px;">';
	foreach ($quick_labels as $quick_key => $quick_label) {
		$is_active = ($quick_key === '' && $quick_filter === '') || ($quick_key !== '' && $quick_filter === $quick_key);
		$quick_url = add_query_arg(array_merge($base_args, [
			'quick' => $quick_key,
			'post_type' => $type_filter,
			's' => $q,
			'sort' => $sort,
			'issues_only' => $issues_only ? '1' : '0',
		]), admin_url('admin.php'));
		echo '<a class="button' . ($is_active ? ' button-primary' : '') . '" href="' . esc_url($quick_url) . '">' . esc_html((string) $quick_label) . '</a>';
	}
	echo '</div>';

	$opportunities = [];
	foreach ($rows as $candidate) {
		$priority = 0;
		$reasons = [];
		$actions = [];
		$is_money = (int) ($candidate['is_money_page'] ?? 0) === 1;
		$in = (int) ($candidate['in'] ?? 0);
		$out_internal = (int) ($candidate['out_internal'] ?? 0);
		$broken_count = (int) ($candidate['broken'] ?? 0);
		$weak_count = (int) ($candidate['weak_anchors'] ?? 0);
		if ($is_money) {
			$priority += 30;
			$reasons[] = __('Revenue page type', 'leadsforward-core');
		}
		if ($in === 0) {
			$priority += 30;
			$reasons[] = __('No linking pages in', 'leadsforward-core');
			$actions[] = __('Add 2-3 contextual inbound links from related service/area/blog pages.', 'leadsforward-core');
		}
		if ($out_internal === 0) {
			$priority += 22;
			$reasons[] = __('No internal links out', 'leadsforward-core');
			$actions[] = __('Add links to one related service page and one high-intent next step page.', 'leadsforward-core');
		}
		if ($broken_count > 0) {
			$priority += min(24, $broken_count * 8);
			$reasons[] = sprintf(__('Broken internal URLs: %d', 'leadsforward-core'), $broken_count);
			$actions[] = __('Fix or replace unresolved internal URLs first.', 'leadsforward-core');
		}
		if ($weak_count > 0) {
			$priority += min(20, $weak_count * 5);
			$reasons[] = sprintf(__('Weak anchors: %d', 'leadsforward-core'), $weak_count);
			$actions[] = __('Replace generic anchor text with service + location intent anchors.', 'leadsforward-core');
		}
		if ($priority <= 0) {
			continue;
		}
		$opportunities[] = [
			'id' => (int) ($candidate['id'] ?? 0),
			'title' => (string) ($candidate['title'] ?? ''),
			'type' => (string) ($candidate['type'] ?? ''),
			'priority' => $priority,
			'reasons' => array_values(array_unique($reasons)),
			'actions' => array_values(array_unique($actions)),
		];
	}
	usort($opportunities, static fn(array $a, array $b): int => ((int) ($b['priority'] ?? 0)) <=> ((int) ($a['priority'] ?? 0)));
	echo '<h3 style="margin-top:20px;">' . esc_html__('Priority Opportunity Queue', 'leadsforward-core') . '</h3>';
	echo '<p class="description">' . esc_html__('Most valuable link fixes first, weighted toward contractor revenue pages and lead-flow gaps.', 'leadsforward-core') . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Priority', 'leadsforward-core') . '</th><th>' . esc_html__('Page', 'leadsforward-core') . '</th><th>' . esc_html__('Why it matters', 'leadsforward-core') . '</th><th>' . esc_html__('Recommended next action', 'leadsforward-core') . '</th><th>' . esc_html__('Quick apply', 'leadsforward-core') . '</th></tr></thead><tbody>';
	$opp_limit = min(15, count($opportunities));
	for ($i = 0; $i < $opp_limit; $i++) {
		$opp = $opportunities[$i];
		$edit_link = get_edit_post_link((int) ($opp['id'] ?? 0), '');
		$reasons_text = implode(' | ', array_slice((array) ($opp['reasons'] ?? []), 0, 3));
		$actions_text = implode(' ', array_slice((array) ($opp['actions'] ?? []), 0, 2));
		echo '<tr>';
		echo '<td><strong>' . esc_html((string) ($opp['priority'] ?? 0)) . '</strong></td>';
		echo '<td>';
		if (is_string($edit_link) && $edit_link !== '') {
			echo '<a href="' . esc_url($edit_link) . '">' . esc_html((string) ($opp['title'] ?? '')) . '</a>';
		} else {
			echo esc_html((string) ($opp['title'] ?? ''));
		}
		echo '<div class="description">' . esc_html((string) ($opp['type'] ?? '')) . '</div>';
		echo '</td>';
		echo '<td>' . esc_html($reasons_text) . '</td>';
		echo '<td>' . esc_html($actions_text) . '</td>';
		echo '<td>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('lf_internal_link_apply_suggestion');
		echo '<input type="hidden" name="action" value="lf_internal_link_map_apply_suggestion" />';
		echo '<input type="hidden" name="source_id" value="' . esc_attr((string) ((int) ($opp['id'] ?? 0))) . '" />';
		echo '<input type="hidden" name="return_to" value="' . esc_url(add_query_arg($_GET, admin_url('admin.php?page=lf-seo&tab=links'))) . '" />';
		submit_button(__('Apply 1 suggestion', 'leadsforward-core'), 'secondary small', '', false);
		echo '</form>';
		echo '</td>';
		echo '</tr>';
	}
	if ($opp_limit === 0) {
		echo '<tr><td colspan="5">' . esc_html__('No high-priority opportunities found with current data.', 'leadsforward-core') . '</td></tr>';
	}
	echo '</tbody></table>';

	$service_ids = get_posts([
		'post_type' => 'lf_service',
		'post_status' => 'publish',
		'posts_per_page' => 500,
		'fields' => 'ids',
	]);
	$area_ids = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => 'publish',
		'posts_per_page' => 500,
		'fields' => 'ids',
	]);
	echo '<h3 style="margin-top:20px;">' . esc_html__('Service-to-Area Link Coverage', 'leadsforward-core') . '</h3>';
	echo '<p class="description">' . esc_html__('Coverage score = how many service area pages each service currently links to.', 'leadsforward-core') . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Service page', 'leadsforward-core') . '</th><th>' . esc_html__('Area links coverage', 'leadsforward-core') . '</th><th>' . esc_html__('Top gap opportunities', 'leadsforward-core') . '</th></tr></thead><tbody>';
	if (!empty($service_ids) && !empty($area_ids)) {
		$coverage_rows = [];
		foreach ($service_ids as $sid_raw) {
			$sid = (int) $sid_raw;
			$linked_area_ids = [];
			foreach ($area_ids as $aid_raw) {
				$aid = (int) $aid_raw;
				if (isset($outbound_internal[$sid][$aid])) {
					$linked_area_ids[] = $aid;
				}
			}
			$total_areas = count($area_ids);
			$linked_count = count($linked_area_ids);
			$coverage_pct = $total_areas > 0 ? (int) round(($linked_count / $total_areas) * 100) : 0;
			$gaps = [];
			foreach ($area_ids as $aid_raw) {
				$aid = (int) $aid_raw;
				if (!in_array($aid, $linked_area_ids, true)) {
					$gaps[] = (string) get_the_title($aid);
				}
			}
			$coverage_rows[] = [
				'sid' => $sid,
				'service_title' => (string) get_the_title($sid),
				'linked_count' => $linked_count,
				'total_areas' => $total_areas,
				'coverage_pct' => $coverage_pct,
				'gaps' => $gaps,
			];
		}
		usort($coverage_rows, static fn(array $a, array $b): int => ((int) ($a['coverage_pct'] ?? 0)) <=> ((int) ($b['coverage_pct'] ?? 0)));
		$coverage_limit = min(12, count($coverage_rows));
		for ($i = 0; $i < $coverage_limit; $i++) {
			$row = $coverage_rows[$i];
			$sid = (int) ($row['sid'] ?? 0);
			$service_title = (string) ($row['service_title'] ?? '');
			$service_edit = get_edit_post_link($sid, '');
			$coverage_text = sprintf('%d/%d (%d%%)', (int) ($row['linked_count'] ?? 0), (int) ($row['total_areas'] ?? 0), (int) ($row['coverage_pct'] ?? 0));
			$gaps = array_filter(array_slice((array) ($row['gaps'] ?? []), 0, 4));
			echo '<tr><td>';
			if (is_string($service_edit) && $service_edit !== '') {
				echo '<a href="' . esc_url($service_edit) . '">' . esc_html($service_title) . '</a>';
			} else {
				echo esc_html($service_title);
			}
			echo '</td><td>' . esc_html($coverage_text) . '</td><td>' . esc_html(!empty($gaps) ? implode(', ', $gaps) : __('No major gaps detected', 'leadsforward-core')) . '</td></tr>';
		}
	} else {
		echo '<tr><td colspan="3">' . esc_html__('Need published services and service areas to compute this matrix.', 'leadsforward-core') . '</td></tr>';
	}
	echo '</tbody></table>';

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
		'lead_score_asc' => __('Lowest lead-flow score', 'leadsforward-core'),
		'inbound_desc' => __('Highest inbound', 'leadsforward-core'),
		'internal_out_desc' => __('Highest internal outbound', 'leadsforward-core'),
		'external_out_desc' => __('Highest external outbound', 'leadsforward-core'),
		'weak_anchors_desc' => __('Most weak anchors', 'leadsforward-core'),
		'broken_desc' => __('Most broken links', 'leadsforward-core'),
		'title' => __('A-Z title', 'leadsforward-core'),
	];
	foreach ($sort_options as $sort_value => $sort_label) {
		echo '<option value="' . esc_attr($sort_value) . '"' . selected($sort, $sort_value, false) . '>' . esc_html((string) $sort_label) . '</option>';
	}
	echo '</select>';
	echo '&nbsp;';
	echo '<label style="display:inline-flex;align-items:center;gap:4px;"><input type="checkbox" name="issues_only" value="1"' . checked($issues_only, true, false) . ' /> ' . esc_html__('Issues only', 'leadsforward-core') . '</label>';
	echo '<input type="hidden" name="quick" value="' . esc_attr($quick_filter) . '" />';
	echo '&nbsp;';
	submit_button(__('Filter', 'leadsforward-core'), 'secondary', '', false);
	echo '</form>';

	echo '<table class="widefat striped">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__('Page', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Type', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Lead-flow score', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Internal links out (unique targets)', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Internal targets (sample)', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('External outbound', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('External URLs (sample)', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Linking pages in (unique sources)', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Weak internal anchors', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Broken internal URLs (unresolved)', 'leadsforward-core') . '</th>';
	echo '<th>' . esc_html__('Details', 'leadsforward-core') . '</th>';
	echo '</tr></thead><tbody>';
	foreach ($rows as $r) {
		$pid = (int) $r['id'];
		$title = (string) $r['title'];
		$type = (string) $r['type'];
		$out_internal = (int) $r['out_internal'];
		$out_external = (int) $r['out_external'];
		$in = (int) $r['in'];
		$weak_anchor_count = (int) ($r['weak_anchors'] ?? 0);
		$lead_score = (int) ($r['lead_score'] ?? 0);
		$br = (int) $r['broken'];
		$is_orphan = $in === 0;
		$edit = get_edit_post_link($pid, '');
		$view = get_permalink($pid);
		$detail_url = add_query_arg(
			array_merge($base_args, ['post_type' => $type_filter, 's' => $q, 'sort' => $sort, 'issues_only' => $issues_only ? '1' : '0', 'quick' => $quick_filter, 'link_page_id' => $pid]),
			admin_url('admin.php')
		);
		$internal_targets_preview = [];
		$src_targets = $outbound_internal[$pid] ?? [];
		if (is_array($src_targets) && !empty($src_targets)) {
			arsort($src_targets);
			foreach ($src_targets as $tid => $count) {
				$target_id = (int) $tid;
				$target_title = $target_id === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title($target_id);
				$target_url = get_permalink($target_id);
				if ($target_title === '') {
					$target_title = '#' . $target_id;
				}
				$internal_targets_preview[] = [
					'title' => $target_title,
					'url' => is_string($target_url) ? $target_url : '',
					'count' => (int) $count,
					'anchors' => array_slice((array) ($internal_anchor_samples[$pid][$target_id] ?? []), 0, 2),
				];
				if (count($internal_targets_preview) >= 3) {
					break;
				}
			}
		}
		$external_urls_preview = [];
		$src_external = $outbound_external[$pid] ?? [];
		if (is_array($src_external) && !empty($src_external)) {
			arsort($src_external);
			foreach ($src_external as $external_url => $count) {
				$external_urls_preview[] = ['url' => (string) $external_url, 'count' => (int) $count];
				if (count($external_urls_preview) >= 2) {
					break;
				}
			}
		}
		$internal_targets_full = [];
		if (is_array($src_targets) && !empty($src_targets)) {
			$src_targets_sorted = $src_targets;
			arsort($src_targets_sorted);
			foreach ($src_targets_sorted as $tid => $count) {
				$target_id = (int) $tid;
				$target_title = $target_id === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title($target_id);
				$target_url = get_permalink($target_id);
				if ($target_title === '') {
					$target_title = '#' . $target_id;
				}
				$internal_targets_full[] = [
					'title' => $target_title,
					'url' => is_string($target_url) ? $target_url : '',
					'count' => (int) $count,
					'anchors' => (array) ($internal_anchor_samples[$pid][$target_id] ?? []),
				];
			}
		}
		$external_urls_full = [];
		if (is_array($src_external) && !empty($src_external)) {
			$src_external_sorted = $src_external;
			arsort($src_external_sorted);
			foreach ($src_external_sorted as $external_url => $count) {
				$external_urls_full[] = ['url' => (string) $external_url, 'count' => (int) $count];
			}
		}
		$inbound_sources_full = [];
		$row_inbound_sources = $inbound_sources[$pid] ?? [];
		if (is_array($row_inbound_sources) && !empty($row_inbound_sources)) {
			arsort($row_inbound_sources);
			foreach ($row_inbound_sources as $src_id => $count) {
				$source_id = (int) $src_id;
				$source_title = $source_id === $home_post_id ? __('Homepage', 'leadsforward-core') : (string) get_the_title($source_id);
				if ($source_title === '') {
					$source_title = '#' . $source_id;
				}
				$source_url = get_permalink($source_id);
				$inbound_sources_full[] = [
					'title' => $source_title,
					'url' => is_string($source_url) ? $source_url : '',
					'count' => (int) $count,
				];
			}
		}
		$row_broken = $broken[$pid] ?? [];
		$issue_labels = [];
		if ($is_orphan) {
			$issue_labels[] = __('Orphan', 'leadsforward-core');
		}
		if ($out_internal === 0) {
			$issue_labels[] = __('No internal outbound', 'leadsforward-core');
		}
		if ($br > 0) {
			$issue_labels[] = __('Broken internal URLs', 'leadsforward-core');
		}
		if ($weak_anchor_count > 0) {
			$issue_labels[] = __('Weak anchors', 'leadsforward-core');
		}

		echo '<tr>';
		echo '<td>';
		echo '<a href="' . esc_url($detail_url) . '">' . ($is_orphan ? '<strong>' . esc_html($title) . '</strong>' : esc_html($title)) . '</a>';
		echo '<div style="margin-top:4px;display:flex;gap:10px;">';
		if ($edit) echo '<a href="' . esc_url($edit) . '">' . esc_html__('Edit', 'leadsforward-core') . '</a>';
		if ($view) echo '<a href="' . esc_url($view) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View', 'leadsforward-core') . '</a>';
		echo '</div>';
		if (!empty($issue_labels)) {
			echo '<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">';
			foreach ($issue_labels as $label) {
				echo '<span style="display:inline-block;border:1px solid #d0d7de;border-radius:999px;padding:1px 8px;font-size:11px;line-height:18px;background:#f6f8fa;">' . esc_html((string) $label) . '</span>';
			}
			echo '</div>';
		}
		echo '</td>';
		echo '<td>' . esc_html($type) . '</td>';
		echo '<td><strong>' . esc_html((string) $lead_score) . '</strong></td>';
		echo '<td>' . esc_html((string) $out_internal) . '</td>';
		echo '<td>';
		if (!empty($internal_targets_preview)) {
			echo '<ul style="margin:0 0 0 1rem;">';
			foreach ($internal_targets_preview as $sample) {
				$sample_url = (string) ($sample['url'] ?? '');
				$sample_title = (string) ($sample['title'] ?? '');
				$sample_count = (int) ($sample['count'] ?? 0);
				$sample_anchors = array_slice((array) ($sample['anchors'] ?? []), 0, 2);
				echo '<li>';
				if ($sample_url !== '') {
					echo '<a href="' . esc_url($sample_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($sample_title) . '</a>';
				} else {
					echo esc_html($sample_title);
				}
				echo ' (' . esc_html((string) $sample_count) . ')';
				if (!empty($sample_anchors)) {
					echo '<span class="description"> — ' . esc_html(implode(', ', $sample_anchors)) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<span class="description">' . esc_html__('None', 'leadsforward-core') . '</span>';
		}
		echo '</td>';
		echo '<td>' . esc_html((string) $out_external) . '</td>';
		echo '<td>';
		if (!empty($external_urls_preview)) {
			echo '<ul style="margin:0 0 0 1rem;">';
			foreach ($external_urls_preview as $sample) {
				$sample_url = (string) ($sample['url'] ?? '');
				$sample_count = (int) ($sample['count'] ?? 0);
				echo '<li><a href="' . esc_url($sample_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($sample_url) . '</a> (' . esc_html((string) $sample_count) . ')</li>';
			}
			echo '</ul>';
		} else {
			echo '<span class="description">' . esc_html__('None', 'leadsforward-core') . '</span>';
		}
		echo '</td>';
		echo '<td>' . esc_html((string) $in) . ($is_orphan ? ' <span class="dashicons dashicons-warning" title="' . esc_attr__('Orphan', 'leadsforward-core') . '"></span>' : '') . '</td>';
		echo '<td>' . esc_html((string) $weak_anchor_count) . '</td>';
		echo '<td>' . esc_html((string) $br) . '</td>';
		echo '<td>';
		echo '<details>';
		echo '<summary>' . esc_html__('View links', 'leadsforward-core') . '</summary>';
		echo '<div style="margin-top:8px;min-width:320px;max-width:560px;">';

		echo '<div style="margin-bottom:8px;"><strong>' . esc_html__('Internal targets', 'leadsforward-core') . '</strong><ul style="margin:4px 0 0 1rem;">';
		if (!empty($internal_targets_full)) {
			foreach ($internal_targets_full as $target_row) {
				$t_url = (string) ($target_row['url'] ?? '');
				$t_title = (string) ($target_row['title'] ?? '');
				$t_count = (int) ($target_row['count'] ?? 0);
				$t_anchors = array_slice((array) ($target_row['anchors'] ?? []), 0, 3);
				echo '<li>';
				if ($t_url !== '') {
					echo '<a href="' . esc_url($t_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($t_title) . '</a>';
				} else {
					echo esc_html($t_title);
				}
				echo ' (' . esc_html((string) $t_count) . ')';
				if (!empty($t_anchors)) {
					echo '<span class="description"> — ' . esc_html(implode(', ', $t_anchors)) . '</span>';
				}
				echo '</li>';
			}
		} else {
			echo '<li>' . esc_html__('None', 'leadsforward-core') . '</li>';
		}
		echo '</ul></div>';

		echo '<div style="margin-bottom:8px;"><strong>' . esc_html__('External URLs', 'leadsforward-core') . '</strong><ul style="margin:4px 0 0 1rem;">';
		if (!empty($external_urls_full)) {
			foreach ($external_urls_full as $ext_row) {
				$e_url = (string) ($ext_row['url'] ?? '');
				$e_count = (int) ($ext_row['count'] ?? 0);
				echo '<li><a href="' . esc_url($e_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($e_url) . '</a> (' . esc_html((string) $e_count) . ')</li>';
			}
		} else {
			echo '<li>' . esc_html__('None', 'leadsforward-core') . '</li>';
		}
		echo '</ul></div>';

		echo '<div style="margin-bottom:8px;"><strong>' . esc_html__('Inbound sources', 'leadsforward-core') . '</strong><ul style="margin:4px 0 0 1rem;">';
		if (!empty($inbound_sources_full)) {
			foreach ($inbound_sources_full as $in_row) {
				$i_url = (string) ($in_row['url'] ?? '');
				$i_title = (string) ($in_row['title'] ?? '');
				$i_count = (int) ($in_row['count'] ?? 0);
				echo '<li>';
				if ($i_url !== '') {
					echo '<a href="' . esc_url($i_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($i_title) . '</a>';
				} else {
					echo esc_html($i_title);
				}
				echo ' (' . esc_html((string) $i_count) . ')</li>';
			}
		} else {
			echo '<li>' . esc_html__('None', 'leadsforward-core') . '</li>';
		}
		echo '</ul></div>';

		echo '<div><strong>' . esc_html__('Broken internal URLs', 'leadsforward-core') . '</strong><ul style="margin:4px 0 0 1rem;">';
		if (is_array($row_broken) && !empty($row_broken)) {
			foreach ($row_broken as $broken_href) {
				echo '<li>' . esc_html((string) $broken_href) . '</li>';
			}
		} else {
			echo '<li>' . esc_html__('None', 'leadsforward-core') . '</li>';
		}
		echo '</ul></div>';

		echo '</div>';
		echo '</details>';
		echo '</td>';
		echo '</tr>';
	}
	if ($rows === []) {
		echo '<tr><td colspan="11">' . esc_html__('No pages found for the current filters.', 'leadsforward-core') . '</td></tr>';
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
					$t_url = get_permalink($t);
					echo '<li>';
					if (is_string($t_url) && $t_url !== '') {
						echo '<a href="' . esc_url($t_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($t_title) . '</a>';
					} else {
						echo esc_html($t_title);
					}
					echo ' (' . esc_html((string) $count) . ')</li>';
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

			echo '<div class="card"><h4 style="margin-top:0;">' . esc_html__('Anchor Quality & Lead-flow', 'leadsforward-core') . '</h4><ul style="margin-left:1rem;">';
			$focus_out_internal = isset($outbound_internal[$focus_id]) ? count((array) $outbound_internal[$focus_id]) : 0;
			$focus_inbound = (int) ($inbound_counts[$focus_id] ?? 0);
			$focus_broken = is_array($brk) ? count($brk) : 0;
			$focus_weak = (int) ($weak_internal_anchor_counts[$focus_id] ?? 0);
			$focus_is_money = in_array((string) $focus_post->post_type, ['lf_service', 'lf_service_area'], true);
			$focus_score = 100;
			if ($focus_inbound === 0) $focus_score -= 40;
			if ($focus_out_internal === 0) $focus_score -= 25;
			if ($focus_broken > 0) $focus_score -= min(25, $focus_broken * 10);
			if ($focus_weak > 0) $focus_score -= min(20, $focus_weak * 4);
			if ($focus_is_money && $focus_inbound < 2) $focus_score -= 10;
			$focus_score = max(0, $focus_score);
			echo '<li><strong>' . esc_html__('Lead-flow score', 'leadsforward-core') . ':</strong> ' . esc_html((string) $focus_score) . '/100</li>';
			echo '<li><strong>' . esc_html__('Weak internal anchors', 'leadsforward-core') . ':</strong> ' . esc_html((string) $focus_weak) . '</li>';
			if ($focus_weak > 0) {
				echo '<li>' . esc_html__('Recommendation: replace generic anchors with high-intent service + location phrases.', 'leadsforward-core') . '</li>';
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

