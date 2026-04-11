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

	return [
		'name' => $display_name,
		'legal_name' => $legal_name,
		'phone_primary' => $primary_phone,
		'phone_tracking' => $tracking_phone,
		'phone_display_pref' => $display_pref,
		'phone_display' => $display_phone,
		'email' => (string) $get('lf_business_email', ''),
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
