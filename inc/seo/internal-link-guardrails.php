<?php
/**
 * Guardrails for AI-generated HTML: strip links to missing/unpublished internal targets.
 *
 * @package LeadsForward_Core
 * @since 0.1.73
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_is_internal_href(string $href): bool {
	$href = trim($href);
	if ($href === '' || str_starts_with($href, '#')) return false;
	if (preg_match('/^(mailto:|tel:|sms:|javascript:)/i', $href)) return false;
	if (str_starts_with($href, '/')) return true;
	if (!preg_match('/^https?:\/\//i', $href)) return true;
	$site_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
	$host = (string) wp_parse_url($href, PHP_URL_HOST);
	return $site_host !== '' && $host !== '' && strtolower($site_host) === strtolower($host);
}

function lf_internal_href_points_to_published_content(string $href): bool {
	$href = trim($href);
	if ($href === '' || str_starts_with($href, '#')) return true;

	// Normalize to absolute.
	if (str_starts_with($href, '/')) {
		$href = rtrim(home_url('/'), '/') . $href;
	} elseif (!preg_match('/^https?:\/\//i', $href)) {
		$href = rtrim(home_url('/'), '/') . '/' . ltrim($href, '/');
	}

	// Allow homepage and a few known hub routes (even if they are handled by templates).
	$path = (string) wp_parse_url($href, PHP_URL_PATH);
	$path = rtrim($path, '/');
	if ($path === '') return true;
	$allow_paths = [
		'/services',
		'/service-areas',
		'/about-us',
		'/why-choose-us',
		'/contact',
		'/reviews',
		'/blog',
	];
	if (in_array($path, $allow_paths, true)) {
		// If the page exists but is not published, treat as broken.
		$maybe_id = url_to_postid($href);
		if ($maybe_id > 0) {
			$p = get_post($maybe_id);
			return $p instanceof \WP_Post && $p->post_status === 'publish';
		}
		return true;
	}

	$id = url_to_postid($href);
	if ($id <= 0) {
		return false;
	}
	$post = get_post($id);
	return $post instanceof \WP_Post && $post->post_status === 'publish';
}

/**
 * Normalize an internal href into a comparable absolute URL + path.
 *
 * @return array{url:string, path:string}
 */
function lf_normalize_internal_href_for_compare(string $href): array {
	$href = trim($href);
	if ($href === '') {
		return ['url' => '', 'path' => ''];
	}

	// Strip fragment early; it should not affect allowlist matching.
	$hash_pos = strpos($href, '#');
	if ($hash_pos !== false) {
		$href = substr($href, 0, $hash_pos);
		$href = is_string($href) ? $href : '';
	}

	// Normalize to absolute URL (internal only; callers should guard).
	if (str_starts_with($href, '/')) {
		$href = rtrim(home_url('/'), '/') . $href;
	} elseif (!preg_match('/^https?:\/\//i', $href)) {
		$href = rtrim(home_url('/'), '/') . '/' . ltrim($href, '/');
	}

	$parts = wp_parse_url($href);
	$path = '';
	if (is_array($parts)) {
		$path = (string) ($parts['path'] ?? '');
	}
	if ($path === '') {
		$path = '/';
	}

	// Compare using an untrailed path; homepage becomes empty string.
	$path = '/' . ltrim($path, '/');
	$path = untrailingslashit($path);
	if ($path === '/') {
		$path = '';
	}

	// URL compare: scheme/host + normalized path (no query/fragment).
	$home_parts = wp_parse_url(home_url('/'));
	$scheme = is_array($parts) ? (string) ($parts['scheme'] ?? '') : '';
	$host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
	if ($scheme === '' && is_array($home_parts)) {
		$scheme = (string) ($home_parts['scheme'] ?? 'https');
	}
	if ($host === '' && is_array($home_parts)) {
		$host = (string) ($home_parts['host'] ?? '');
	}
	$url = '';
	if ($host !== '') {
		$url = $scheme . '://' . $host . ($path === '' ? '/' : ($path . '/'));
		$url = rtrim($url, '/') . '/';
	}

	return ['url' => $url, 'path' => $path];
}

/**
 * Build an allowlist of internal URLs/paths from the sitemap page index.
 *
 * Safety: if the index is missing/empty, return enforce=false and callers should
 * fall back to "broken-link only" behavior.
 *
 * Allowlist source option: lf_sitemap_page_index
 * Shape: slug_resolved -> {post_id,status,type}; include only status=publish
 *
 * @return array{enforce:bool, paths:array<string,true>, urls:array<string,true>}
 */
function lf_internal_link_sitemap_allowlist(): array {
	$raw = get_option('lf_sitemap_page_index', '');
	if (!is_string($raw) || trim($raw) === '') {
		return ['enforce' => false, 'paths' => [], 'urls' => []];
	}

	$decoded = json_decode($raw, true);
	if (!is_array($decoded) || $decoded === []) {
		return ['enforce' => false, 'paths' => [], 'urls' => []];
	}

	$paths = [];
	$urls = [];
	foreach ($decoded as $slug_resolved => $row) {
		if (!is_array($row)) {
			continue;
		}
		$status = sanitize_key((string) ($row['status'] ?? ''));
		if ($status !== 'publish') {
			continue;
		}
		$post_id = (int) ($row['post_id'] ?? 0);
		if ($post_id <= 0) {
			continue;
		}
		$permalink = get_permalink($post_id);
		if (!is_string($permalink) || trim($permalink) === '') {
			continue;
		}
		$norm = lf_normalize_internal_href_for_compare($permalink);
		if ($norm['path'] !== '') {
			$paths[$norm['path']] = true;
		} else {
			// homepage
			$paths[''] = true;
		}
		if ($norm['url'] !== '') {
			$urls[$norm['url']] = true;
		}
	}

	if ($paths === [] && $urls === []) {
		return ['enforce' => false, 'paths' => [], 'urls' => []];
	}

	return ['enforce' => true, 'paths' => $paths, 'urls' => $urls];
}

/**
 * Whether an internal href is allowed by the sitemap allowlist.
 *
 * @param array{enforce:bool, paths:array<string,true>, urls:array<string,true>} $allow
 */
function lf_internal_href_is_allowed_by_sitemap(string $href, array $allow): bool {
	if (empty($allow['enforce'])) {
		return true;
	}
	$norm = lf_normalize_internal_href_for_compare($href);
	$path = $norm['path'];
	if (isset($allow['paths'][$path])) {
		return true;
	}
	$url = $norm['url'];
	return $url !== '' && isset($allow['urls'][$url]);
}

/**
 * Remove broken internal links from an HTML blob while preserving anchor text.
 */
function lf_strip_broken_internal_links_from_html(string $html): string {
	$html = (string) $html;
	if ($html === '' || stripos($html, '<a') === false) {
		return $html;
	}
	$allow = lf_internal_link_sitemap_allowlist();
	$prev = libxml_use_internal_errors(true);
	try {
		$doc = new \DOMDocument();
		$wrapped = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
		if (@$doc->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR)) {
			$links = $doc->getElementsByTagName('a');
			$to_remove = [];
			foreach ($links as $a) {
				if (!$a instanceof \DOMElement) continue;
				$href = (string) $a->getAttribute('href');
				if (!lf_is_internal_href($href)) {
					continue;
				}
				if (!lf_internal_href_points_to_published_content($href)) {
					$to_remove[] = $a;
					continue;
				}
				if (!lf_internal_href_is_allowed_by_sitemap($href, $allow)) {
					$to_remove[] = $a;
				}
			}
			foreach ($to_remove as $a) {
				$parent = $a->parentNode;
				if (!$parent) continue;
				$text = $doc->createTextNode($a->textContent ?? '');
				$parent->replaceChild($text, $a);
			}
			$body = $doc->getElementsByTagName('body')->item(0);
			if ($body instanceof \DOMElement) {
				$out = '';
				foreach ($body->childNodes as $child) {
					$out .= $doc->saveHTML($child);
				}
				$html = $out;
			}
		}
	} catch (\Throwable $e) {
		// ignore
	}
	libxml_clear_errors();
	libxml_use_internal_errors($prev);
	return $html;
}

