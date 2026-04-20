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
 * Remove broken internal links from an HTML blob while preserving anchor text.
 */
function lf_strip_broken_internal_links_from_html(string $html): string {
	$html = (string) $html;
	if ($html === '' || stripos($html, '<a') === false) {
		return $html;
	}
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

