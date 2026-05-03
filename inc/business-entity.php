<?php
/**
 * Business Entity: single source of truth for Local SEO.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

function lf_business_entity_public_email_domain(string $email): string {
	$email = strtolower(trim($email));
	if ($email === '' || strpos($email, '@') === false) {
		return '';
	}
	$parts = explode('@', $email);
	$domain = trim((string) end($parts));
	$domain = preg_replace('/:\d+$/', '', $domain);
	return is_string($domain) ? $domain : '';
}

function lf_business_entity_site_root_domain(): string {
	$host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
	$host = strtolower(trim($host));
	$host = preg_replace('/:\d+$/', '', $host);
	$host = preg_replace('/^www\./', '', $host);
	return $host;
}

function lf_business_entity_public_email(string $stored): string {
	$stored = trim((string) $stored);
	$site_domain = lf_business_entity_site_root_domain();
	if ($stored !== '' && is_email($stored)) {
		$ed = lf_business_entity_public_email_domain($stored);
		$ok = ($site_domain !== '' && $ed !== '' && ($ed === $site_domain || str_ends_with($ed, '.' . $site_domain)));
		if ($ok) {
			return $stored;
		}
	}
	// Hard fallback: never leak a non-domain inbox. Use a safe default on this domain.
	if ($site_domain !== '') {
		return 'info@' . $site_domain;
	}
	return '';
}

/**
 * Allowed HTML for pasted Google Maps (and similar) iframe embeds.
 *
 * @return array<string, array<string, bool>>
 */
function lf_map_embed_allowed_iframe_kses(): array {
	return [
		'iframe' => [
			'src' => true,
			'width' => true,
			'height' => true,
			'style' => true,
			'loading' => true,
			'referrerpolicy' => true,
			'allowfullscreen' => true,
			'title' => true,
			'allow' => true,
			'class' => true,
			'id' => true,
			'name' => true,
			'frameborder' => true,
		],
	];
}

function lf_business_entity_parse_list($raw): array {
	if (is_array($raw)) {
		$items = $raw;
	} else {
		$items = array_filter(array_map('trim', explode("\n", (string) $raw)));
	}
	$out = [];
	foreach ($items as $item) {
		$item = sanitize_text_field((string) $item);
		if ($item !== '') {
			$out[] = $item;
		}
	}
	$out = array_values(array_unique($out));
	return array_slice($out, 0, 50);
}

function lf_business_entity_address_string(array $parts): string {
	$street = trim((string) ($parts['street'] ?? ''));
	$city = trim((string) ($parts['city'] ?? ''));
	$state = trim((string) ($parts['state'] ?? ''));
	$zip = trim((string) ($parts['zip'] ?? ''));
	$line2 = trim(implode(' ', array_filter([$city, $state, $zip])));
	$full = trim(implode(', ', array_filter([$street, $line2])));
	return $full;
}

function lf_business_entity_service_areas(): array {
	static $cached = null;
	if ($cached !== null) {
		return $cached;
	}
	if (!post_type_exists('lf_service_area')) {
		$cached = [];
		return $cached;
	}
	$posts = get_posts([
		'post_type' => 'lf_service_area',
		'post_status' => 'publish',
		'posts_per_page' => 50,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);
	$areas = array_values(array_filter(array_map(function ($post) {
		return $post ? get_the_title($post) : '';
	}, $posts)));
	$cached = array_values(array_unique($areas));
	return $cached;
}

function lf_business_entity_get(): array {
	$get = function (string $key, $default = '') {
		return function_exists('lf_get_business_info_value') ? lf_get_business_info_value($key, $default) : get_option('options_' . $key, $default);
	};

	$display_name = (string) $get('lf_business_name', '');
	$legal_name = (string) $get('lf_business_legal_name', '');
	$primary_phone = (string) $get('lf_business_phone_primary', '');
	$tracking_phone = (string) $get('lf_business_phone_tracking', '');
	$display_pref = (string) $get('lf_business_phone_display', 'primary');
	if ($primary_phone === '') {
		$primary_phone = (string) $get('lf_business_phone', '');
	}
	$display_phone = $display_pref === 'tracking' && $tracking_phone !== '' ? $tracking_phone : $primary_phone;
	if ($display_phone === '') {
		$display_phone = (string) $get('lf_business_phone', '');
	}
	$street = (string) $get('lf_business_address_street', '');
	$city = (string) $get('lf_business_address_city', '');
	$state = (string) $get('lf_business_address_state', '');
	$zip = (string) $get('lf_business_address_zip', '');
	$address = lf_business_entity_address_string([
		'street' => $street,
		'city' => $city,
		'state' => $state,
		'zip' => $zip,
	]);
	if ($address === '') {
		$address = (string) $get('lf_business_address', '');
	}
	if ($street === '' && $city === '' && $state === '' && $zip === '' && $address !== '') {
		$street = $address;
	}
	$service_area_type = (string) $get('lf_business_service_area_type', 'address');
	$service_areas = lf_business_entity_service_areas();
	$geo = $get('lf_business_geo', []);
	$hours = (string) $get('lf_business_hours', '');
	$category = (string) $get('lf_business_category', 'HomeAndConstructionBusiness');
	$description = (string) $get('lf_business_short_description', '');
	$primary_image_id = (int) $get('lf_business_primary_image', 0);
	$place_id = (string) $get('lf_business_place_id', '');
	$place_name = (string) $get('lf_business_place_name', '');
	$place_address = (string) $get('lf_business_place_address', '');
	$map_embed = (string) $get('lf_business_map_embed', '');
	$logo_id = function_exists('lf_get_global_option') ? (int) lf_get_global_option('lf_global_logo', 0) : 0;
	$logo_id = $logo_id > 0 ? $logo_id : (int) $get('lf_business_logo', 0);

	$social = [
		'facebook' => (string) $get('lf_business_social_facebook', ''),
		'instagram' => (string) $get('lf_business_social_instagram', ''),
		'youtube' => (string) $get('lf_business_social_youtube', ''),
		'linkedin' => (string) $get('lf_business_social_linkedin', ''),
		'tiktok' => (string) $get('lf_business_social_tiktok', ''),
		'x' => (string) $get('lf_business_social_x', ''),
	];
	$gbp = (string) $get('lf_business_gbp_url', '');
	$same_as = lf_business_entity_parse_list($get('lf_business_same_as', ''));
	foreach ($social as $url) {
		if ($url !== '') {
			$same_as[] = $url;
		}
	}
	if ($gbp !== '') {
		$same_as[] = $gbp;
	}
	$same_as = array_values(array_unique(array_filter($same_as)));

	if (array_filter($social) === [] && $same_as !== []) {
		foreach ($same_as as $candidate) {
			$candidate = trim((string) $candidate);
			if ($candidate === '') {
				continue;
			}
			if (!filter_var($candidate, FILTER_VALIDATE_URL)) {
				continue;
			}
			$host = strtolower((string) wp_parse_url($candidate, PHP_URL_HOST));
			if ($host === '') {
				continue;
			}
			if ($social['facebook'] === '' && str_contains($host, 'facebook.')) {
				$social['facebook'] = $candidate;
			}
			if ($social['instagram'] === '' && str_contains($host, 'instagram.')) {
				$social['instagram'] = $candidate;
			}
			if ($social['youtube'] === '' && (str_contains($host, 'youtube.') || str_contains($host, 'youtu.be'))) {
				$social['youtube'] = $candidate;
			}
			if ($social['linkedin'] === '' && str_contains($host, 'linkedin.')) {
				$social['linkedin'] = $candidate;
			}
			if ($social['tiktok'] === '' && str_contains($host, 'tiktok.')) {
				$social['tiktok'] = $candidate;
			}
			if ($social['x'] === '' && (str_contains($host, 'twitter.') || str_contains($host, 'x.com'))) {
				$social['x'] = $candidate;
			}
		}
	}

	return [
		'name' => $display_name,
		'legal_name' => $legal_name,
		'phone_primary' => $primary_phone,
		'phone_tracking' => $tracking_phone,
		'phone_display_pref' => $display_pref,
		'phone_display' => $display_phone,
		'email' => lf_business_entity_public_email((string) $get('lf_business_email', '')),
		'address' => $address,
		'address_parts' => [
			'street' => $street,
			'city' => $city,
			'state' => $state,
			'zip' => $zip,
		],
		'service_area_type' => $service_area_type,
		'service_areas' => $service_areas,
		'geo' => is_array($geo) ? $geo : [],
		'hours' => $hours,
		'category' => $category,
		'description' => $description,
		'logo_id' => $logo_id,
		'primary_image_id' => $primary_image_id,
		'place_id' => $place_id,
		'place_name' => $place_name,
		'place_address' => $place_address,
		'map_embed' => $map_embed,
		'social' => $social,
		'gbp_url' => $gbp,
		'same_as' => $same_as,
		'founding_year' => (string) $get('lf_business_founding_year', ''),
		'license_number' => (string) $get('lf_business_license_number', ''),
		'insurance_statement' => (string) $get('lf_business_insurance_statement', ''),
	];
}

function lf_business_entity_area_served(array $entity): array {
	$areas = lf_business_entity_service_areas();
	return $areas;
}

function lf_business_entity_image_url(int $attachment_id, string $size = 'large'): string {
	if ($attachment_id <= 0) {
		return '';
	}
	$url = wp_get_attachment_image_url($attachment_id, $size);
	return is_string($url) ? $url : '';
}

/**
 * Whether a URL is an allowed Google Maps embed iframe src (https only).
 */
function lf_google_maps_embed_src_is_allowed(string $url): bool {
	$url = trim($url);
	if ($url === '') {
		return false;
	}
	$p = wp_parse_url($url);
	if (!is_array($p) || empty($p['scheme']) || strtolower((string) $p['scheme']) !== 'https') {
		return false;
	}
	$host = strtolower((string) ($p['host'] ?? ''));
	if ($host === '' || strpos($host, 'google') === false) {
		return false;
	}
	$path = (string) ($p['path'] ?? '');
	if (str_contains($host, 'google.com') && (str_starts_with($path, '/maps') || str_starts_with($path, '/map'))) {
		return true;
	}
	return str_contains($host, 'maps.google');
}

/**
 * Build a Google Maps iframe src from synced business data (no manual iframe paste).
 * Uses optional Maps Embed API key when set; otherwise standard maps?q=…&output=embed URLs.
 *
 * Priority: lat/lng → place_id → street address → city + state → first service area line → manifest primary city → lf_homepage_city.
 *
 * @param array<string,mixed>|null $entity Result of lf_business_entity_get() or equivalent.
 */
function lf_google_maps_auto_embed_src(?array $entity = null): string {
	if ($entity === null && function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
	}
	if (!is_array($entity)) {
		$entity = [];
	}

	$maps_api_key = get_option('lf_maps_api_key', '');
	$maps_api_key = is_string($maps_api_key) ? trim($maps_api_key) : '';

	$place_id = trim((string) ($entity['place_id'] ?? ''));
	if ($place_id !== '' && stripos($place_id, 'place_id:') === 0) {
		$place_id = trim(substr($place_id, strlen('place_id:')));
	}
	if ($place_id !== '' && (strlen($place_id) < 12 || preg_match('/\s/', $place_id) === 1)) {
		$place_id = '';
	}

	$geo = isset($entity['geo']) && is_array($entity['geo']) ? $entity['geo'] : [];
	$lat_raw = $geo['lat'] ?? null;
	$lng_raw = $geo['lng'] ?? null;
	$lat_valid = $lat_raw !== null && $lat_raw !== '' && is_numeric($lat_raw);
	$lng_valid = $lng_raw !== null && $lng_raw !== '' && is_numeric($lng_raw);
	$lat = $lat_valid ? (float) $lat_raw : 0.0;
	$lng = $lng_valid ? (float) $lng_raw : 0.0;
	$has_geo = $lat_valid && $lng_valid && abs($lat) <= 90.0 && abs($lng) <= 180.0;

	$address = trim((string) ($entity['address'] ?? ''));
	$parts = isset($entity['address_parts']) && is_array($entity['address_parts']) ? $entity['address_parts'] : [];
	$city = trim((string) ($parts['city'] ?? ''));
	$state = trim((string) ($parts['state'] ?? ''));

	if ($maps_api_key !== '') {
		if ($place_id !== '') {
			return 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($maps_api_key) . '&q=place_id:' . rawurlencode($place_id);
		}
		if ($has_geo) {
			return 'https://www.google.com/maps/embed/v1/view?key=' . rawurlencode($maps_api_key)
				. '&center=' . rawurlencode((string) $lat . ',' . (string) $lng) . '&zoom=15';
		}
		if ($address !== '') {
			return 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($maps_api_key) . '&q=' . rawurlencode($address);
		}
	}

	if ($has_geo) {
		return 'https://www.google.com/maps?q=' . rawurlencode((string) $lat . ',' . (string) $lng) . '&z=15&output=embed';
	}
	if ($place_id !== '') {
		return 'https://www.google.com/maps?q=place_id:' . rawurlencode($place_id) . '&output=embed';
	}
	if ($address !== '') {
		return 'https://www.google.com/maps?q=' . rawurlencode($address) . '&output=embed';
	}

	$city_query = '';
	if ($city !== '' && $state !== '') {
		$city_query = $city . ', ' . $state;
	} elseif ($city !== '') {
		$city_query = $city;
	}
	if ($city_query === '') {
		$areas = isset($entity['service_areas']) && is_array($entity['service_areas']) ? $entity['service_areas'] : [];
		if ($areas === [] && function_exists('lf_business_entity_service_areas')) {
			$areas = lf_business_entity_service_areas();
		}
		if (!empty($areas[0])) {
			$city_query = trim((string) $areas[0]);
		}
	}
	if ($city_query === '' && function_exists('lf_sitemap_sync_get_primary_city')) {
		$city_query = trim((string) lf_sitemap_sync_get_primary_city());
	}
	if ($city_query === '') {
		$city_query = trim((string) get_option('lf_homepage_city', ''));
	}
	if ($city_query !== '') {
		return 'https://www.google.com/maps?q=' . rawurlencode($city_query) . '&output=embed';
	}

	return '';
}

/**
 * Standard iframe markup for an auto-generated Maps embed src.
 */
function lf_google_maps_embed_iframe_html(string $src, string $title = ''): string {
	$src = trim($src);
	if ($src === '' || !lf_google_maps_embed_src_is_allowed($src)) {
		return '';
	}
	if ($title === '') {
		$title = __('Map', 'leadsforward-core');
	}
	return sprintf(
		'<iframe src="%s" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="%s"></iframe>',
		esc_url($src),
		esc_attr($title)
	);
}
