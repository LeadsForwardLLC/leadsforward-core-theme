<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @return array{ok:bool, slug:string, error:string}
 */
function lf_sitemap_resolve_slug_template(string $template, string $city): array {
	$template = trim($template);
	if ($template === '') {
		return ['ok' => false, 'slug' => '', 'error' => 'missing_slug'];
	}

	$city_slug = sanitize_title($city);
	$slug = $template;
	if (strpos($slug, '{city}') !== false) {
		if ($city_slug === '') {
			$slug = str_replace('{city}', '', $slug);
			$slug = preg_replace('#/+#', '/', (string) $slug);
			$slug = preg_replace('#-+#', '-', (string) $slug);
			$slug = trim((string) $slug, "-/ \t\n\r\0\x0B");
			$slug = '/' . trim($slug, '/') . '/';
			return ['ok' => false, 'slug' => $slug, 'error' => 'missing_city_for_template'];
		}
		$slug = str_replace('{city}', $city_slug, $slug);
	}

	$slug = '/' . trim($slug, '/') . '/';
	return ['ok' => true, 'slug' => $slug, 'error' => ''];
}

function lf_sitemap_spec_key(string $niche, string $slug_template): string {
	return hash('sha256', strtolower(trim($niche)) . ':' . strtolower(trim($slug_template)));
}

