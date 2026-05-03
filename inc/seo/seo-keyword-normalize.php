<?php
/**
 * Primary keyword cleanup + SERP-friendly phrasing (title-case, redundant city trimming).
 *
 * Used when composing meta titles/descriptions; does not alter checklist matching by itself—
 * sitemap reconcile may optionally persist normalized keywords coming from polluted Airtable cells.
 *
 * @package LeadsForward_Core
 * @since 0.1.162
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Strip common Airtable/CSV pollution: headline pasted into Keyword, duplicate city, duplicated tail clauses.
 *
 * @param string      $stored    Raw `_lf_seo_primary_keyword`.
 * @param int         $post_id   Used for heading compare (optional 0).
 * @param string|null $post_title If known without loading post (reconcile paths).
 */
function lf_seo_normalize_primary_keyword_core(string $stored, int $post_id = 0, ?string $post_title_override = null): string {
	$kw = preg_replace('/\s+/u', ' ', trim($stored));
	if ($kw === '') {
		return '';
	}
	$post_type = '';
	$title = $post_title_override !== null ? trim($post_title_override) : '';
	if ($post_id > 0 && $title === '') {
		$post = get_post($post_id);
		if ($post instanceof \WP_Post) {
			$title = trim((string) $post->post_title);
			$post_type = (string) $post->post_type;
		}
	} elseif ($post_id > 0) {
		$pt = get_post_type($post_id);
		$post_type = is_string($pt) ? $pt : '';
	}

	if ($title !== '') {
		$nxt = lf_seo_strip_post_title_echo_from_keyword($kw, $title);
		if ($nxt !== $kw && $nxt !== '') {
			$kw = $nxt;
		}
	}

	$post_like = ($post_type === 'post');

	if ($post_like && mb_strlen($kw, 'UTF-8') > 90) {
		$colon_tail = lf_seo_keyword_after_last_colon($kw);
		if ($colon_tail !== '' && lf_seo_string_looks_sluglike_keyword_tail($colon_tail)) {
			$kw = $colon_tail;
		}
	}

	$city = lf_seo_get_city_for_normalize();
	if ($city !== '') {
		$kw = lf_seo_collapse_adjacent_duplicate_place($kw, $city);
	}

	return preg_replace('/\s+/u', ' ', trim($kw));
}

/**
 * @internal
 */
function lf_seo_get_city_for_normalize(): string {
	if (function_exists('lf_seo_get_city_name')) {
		return trim((string) lf_seo_get_city_name());
	}
	return '';
}

/**
 * Normalize keyword synced from sheet before persisting meta.
 */
function lf_seo_normalize_airtable_keyword_for_storage(string $keyword, string $page_title): string {
	return lf_seo_normalize_primary_keyword_core(trim($keyword), 0, trim($page_title));
}

function lf_seo_title_case_display_phrase(string $phrase): string {
	$phrase = preg_replace('/\s+/u', ' ', trim($phrase));
	if ($phrase === '') {
		return '';
	}
	$s = mb_strtolower($phrase, 'UTF-8');
	if (function_exists('mb_convert_case')) {
		$s = (string) mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
	} else {
		$s = ucwords($s);
	}
	return preg_replace('/\s+/u', ' ', trim($s));
}

/**
 * Short SERP-facing brand ("AccuLevel - Schaumburg IL #21" → "AccuLevel").
 */
function lf_seo_short_brand_for_serp(?string $full_brand = null): string {
	$b = trim($full_brand ?? (function_exists('lf_seo_get_brand_name') ? lf_seo_get_brand_name() : (string) get_bloginfo('name')));
	if ($b === '') {
		return '';
	}
	foreach ([' | ', ' – ', ' - ', ' — '] as $sep) {
		$pos = strpos($b, $sep);
		if ($pos !== false && $pos > 2 && $pos < 80) {
			$b = trim(substr($b, 0, $pos));
			break;
		}
	}
	return trim($b);
}

/**
 * Case-insensitive check that $phrase materially includes $city token.
 */
function lf_seo_phrase_contains_place(string $phrase, string $place): bool {
	$phrase = trim($phrase);
	$place = trim($place);
	if ($phrase === '' || $place === '' || mb_strlen($place, 'UTF-8') < 3) {
		return false;
	}
	return function_exists('mb_stripos')
		? mb_stripos($phrase, $place, 0, 'UTF-8') !== false
		: stripos($phrase, $place) !== false;
}

/**
 * Smart truncation for meta descriptions (~160 graphemes).
 */
function lf_seo_truncate_meta_description_smart(string $text, int $max = 160): string {
	$text = preg_replace('/\s+/u', ' ', trim($text));
	if ($text === '') {
		return '';
	}
	if (!function_exists('mb_strlen') || mb_strlen($text, 'UTF-8') <= $max) {
		return $text;
	}
	$core = mb_substr($text, 0, $max - 1, 'UTF-8');
	$cut = mb_strrpos($core, ' ');
	if ($cut !== false && $cut > max(42, (int) floor(($max - 30) * 0.65))) {
		return rtrim(mb_substr($core, 0, $cut), " \t\n\r\0\x0B,.;:–-") . '…';
	}
	return rtrim($core, " \t\n\r\0\x0B,.;:–-") . '…';
}

// --- Internals --------------------------------------------------------------

/**
 * When Airtable concatenates headline + slug keyword ("Title… foundation repair x"), keep the actionable tail.
 *
 * @internal
 */
function lf_seo_strip_post_title_echo_from_keyword(string $kw, string $title): string {
	$kw = preg_replace('/\s+/u', ' ', trim($kw));
	$title = preg_replace('/\s+/u', ' ', trim($title));
	if ($kw === '' || $title === '' || mb_strlen($title, 'UTF-8') < 10) {
		return $kw;
	}

	$stripped_once = lf_seo_try_strip_heading_prefix_ci($kw, $title)
		?: lf_seo_try_strip_heading_prefix_ci($kw, $title . ':')
		?: lf_seo_try_strip_heading_prefix_ci($kw, $title . ': ');

	return $stripped_once !== '' ? $stripped_once : $kw;
}

/**
 * @internal
 */
function lf_seo_try_strip_heading_prefix_ci(string $kw, string $prefix_raw): string {
	if ($kw === '' || $prefix_raw === '') {
		return '';
	}
	$prefix_clean = preg_replace('/\s+/u', ' ', trim($prefix_raw));
	if ($prefix_clean === '') {
		return '';
	}
	if (!function_exists('mb_stripos')) {
		return stripos($kw, $prefix_clean) === 0
			? preg_replace('/^[\s:|–\-—]+/u', '', trim(substr($kw, strlen($prefix_clean)))) : '';
	}
	if (mb_stripos($kw, $prefix_clean, 0, 'UTF-8') !== 0) {
		return '';
	}
	$plen = mb_strlen($prefix_clean, 'UTF-8');
	$rest = trim((string) mb_substr($kw, $plen, encoding: 'UTF-8'));
	$rest = preg_replace('/^[\s:|–\-—]+/u', '', $rest);

	return $rest !== '' && mb_strlen($rest, 'UTF-8') >= 4 ? $rest : '';
}

/**
 * @internal Tail after final colon—candidate slug-like keyword appendix.
 */
function lf_seo_keyword_after_last_colon(string $kw): string {
	if (strpos($kw, ':') === false) {
		return '';
	}
	$parts = explode(':', $kw);
	while ($parts !== [] && trim((string) end($parts)) === '') {
		array_pop($parts);
	}
	if ($parts === []) {
		return '';
	}
	$tail = trim((string) end($parts));
	if (mb_strlen($tail, 'UTF-8') < 12 || mb_strlen($tail, 'UTF-8') > 120) {
		return '';
	}
	return $tail;
}

/**
 * @internal Heuristic: short service/city style tail vs full headline.
 */
function lf_seo_string_looks_sluglike_keyword_tail(string $s): bool {
	$s = preg_replace('/\s+/u', ' ', trim($s));
	if ($s === '') {
		return false;
	}
	if (strpos($s, ':') !== false) {
		return false;
	}
	$chars = preg_match_all('/[a-z]/i', $s) ?: 0;
	if ($chars < 12) {
		return false;
	}
	/** @phpstan-ignore-next-line intentionally lenient heuristic */
	return mb_strlen($s, 'UTF-8') <= 85;
}

/**
 * @internal Remove first duplicate occurrence of Place … Place clusters (stutters).
 */
function lf_seo_collapse_adjacent_duplicate_place(string $phrase, string $place): string {
	$needle = preg_quote(trim($place), '/');
	if ($needle === '') {
		return $phrase;
	}
	$pattern = '/\b' . $needle . '\b([\s\S]{2,72}?)\b' . $needle . '\b/iu';
	$once = preg_replace($pattern, $place . '$1', $phrase, 1);
	return is_string($once) ? $once : $phrase;
}
