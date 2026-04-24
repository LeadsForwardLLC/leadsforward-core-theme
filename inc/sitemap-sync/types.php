<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @return array{ok:bool, slug:string, error:string}
 */
function lf_sitemap_resolve_slug_template(string $template, string $city): array {
	$normalized = lf_sitemap_normalize_slug_template($template);
	if (!$normalized['ok']) {
		return ['ok' => false, 'slug' => '/', 'error' => $normalized['error']];
	}

	$city_slug = sanitize_title($city);
	$slug = $normalized['template'];

	if (strpos($slug, '{city}') !== false) {
		if ($city_slug === '') {
			$slug = str_replace('{city}', '', $slug);
			$slug = lf_sitemap_normalize_slug_path($slug);
			return ['ok' => false, 'slug' => $slug, 'error' => 'missing_city_for_template'];
		}

		$slug = str_replace('{city}', $city_slug, $slug);
	}

	$slug = lf_sitemap_normalize_slug_path($slug);
	return ['ok' => true, 'slug' => $slug, 'error' => ''];
}

function lf_sitemap_spec_key(string $niche, string $slug_template): string {
	$canonical = lf_sitemap_normalize_slug_template_for_key($slug_template);
	$lower = function_exists('wp_strtolower') ? 'wp_strtolower' : 'strtolower';
	return hash('sha256', $lower(trim($niche)) . ':' . $lower($canonical));
}

/**
 * @return array{ok:bool, template:string, error:string}
 */
function lf_sitemap_normalize_slug_template(string $template): array {
	$template = trim($template);
	if ($template === '') {
		return ['ok' => false, 'template' => '', 'error' => 'missing_slug'];
	}

	$template = preg_replace('/\s+/', ' ', $template);
	$template = str_replace(' ', '-', (string) $template);
	$template = preg_replace('#/+#', '/', (string) $template);
	$template = preg_replace('#-+#', '-', (string) $template);

	// Allow only URL-safe characters and the {city} token braces.
	if (preg_match('/[^a-zA-Z0-9\/_\-\{\}]/', (string) $template)) {
		return ['ok' => false, 'template' => '', 'error' => 'invalid_slug_template'];
	}

	// Only allow the {city} token (no other brace usage).
	$without_city = str_replace('{city}', '', (string) $template);
	if (strpos($without_city, '{') !== false || strpos($without_city, '}') !== false) {
		return ['ok' => false, 'template' => '', 'error' => 'invalid_slug_template'];
	}

	// Reject path traversal segments.
	$segments = preg_split('#/+#', (string) $template, -1, PREG_SPLIT_NO_EMPTY);
	foreach ($segments as $seg) {
		if ($seg === '..') {
			return ['ok' => false, 'template' => '', 'error' => 'invalid_slug_template'];
		}
	}

	$template = trim((string) $template, "-/ \t\n\r\0\x0B");
	if ($template === '') {
		return ['ok' => false, 'template' => '', 'error' => 'missing_slug'];
	}

	return ['ok' => true, 'template' => $template, 'error' => ''];
}

function lf_sitemap_normalize_slug_path(string $path): string {
	$path = trim($path);
	$path = preg_replace('/\s+/', ' ', $path);
	$path = str_replace(' ', '-', (string) $path);
	$path = preg_replace('#/+#', '/', (string) $path);
	$path = preg_replace('#-+#', '-', (string) $path);
	$path = trim((string) $path, "-/ \t\n\r\0\x0B");

	if ($path === '') {
		return '/';
	}

	$path = '/' . trim((string) $path, '/') . '/';
	if ($path === '//') {
		return '/';
	}

	return $path;
}

function lf_sitemap_normalize_slug_template_for_key(string $template): string {
	$normalized = lf_sitemap_normalize_slug_template($template);
	$canonical = $normalized['ok'] ? $normalized['template'] : trim($template);
	return lf_sitemap_normalize_slug_path($canonical);
}

