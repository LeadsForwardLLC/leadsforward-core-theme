<?php
/**
 * CLI checks for lf_google_maps_* helpers (minimal WP stubs).
 */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/../');
}

function wp_parse_url(string $url, int $component = -1) {
	return parse_url($url, $component);
}

function esc_url(string $url): string {
	return $url;
}

function esc_attr(string $text): string {
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function __(string $text, ?string $domain = null): string {
	return $text;
}

function get_option(string $option, $default = false) {
	if ($option === 'lf_maps_api_key') {
		return '';
	}
	if ($option === 'lf_homepage_city') {
		return '';
	}
	return $default;
}

function expect(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, 'FAIL: ' . $msg . PHP_EOL);
		exit(1);
	}
}

require __DIR__ . '/../inc/business-entity.php';

expect(lf_google_maps_embed_src_is_allowed('https://www.google.com/maps?q=test&output=embed'), 'allow google maps https');
expect(!lf_google_maps_embed_src_is_allowed('http://www.google.com/maps?q=x'), 'reject non-https');
expect(!lf_google_maps_embed_src_is_allowed('https://evil.example/maps?q=x'), 'reject non-google host');

$addr_src = lf_google_maps_auto_embed_src([
	'address' => '123 Main St, Tampa, FL',
	'geo' => [],
	'address_parts' => ['city' => '', 'state' => ''],
	'place_id' => '',
	'service_areas' => [],
]);
expect(str_contains($addr_src, 'google.com/maps'), 'address yields maps url');

$city_src = lf_google_maps_auto_embed_src([
	'address' => '',
	'geo' => [],
	'address_parts' => ['city' => 'Schaumburg', 'state' => 'IL'],
	'place_id' => '',
	'service_areas' => [],
]);
expect(str_contains($city_src, 'Schaumburg'), 'city fallback in query');

$html = lf_google_maps_embed_iframe_html($addr_src);
expect(str_contains($html, '<iframe') && str_contains($html, 'src='), 'iframe html');

fwrite(STDOUT, "PASS: google-maps-embed\n");
