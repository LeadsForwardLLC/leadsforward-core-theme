<?php
/**
 * Schema.org JSON-LD output. LocalBusiness, Service, FAQPage, Review.
 * Toggleable via Theme Options > Schema; fails silently if data incomplete.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('wp_head', 'lf_output_schema_json_ld', 5);

/**
 * Output all applicable schema scripts in head. Respects toggles; no output if incomplete.
 */
function lf_output_schema_json_ld(): void {
	$scripts = [];

	// LocalBusiness (global) — requires name; address or geo recommended.
	if (lf_schema_toggle_on('lf_schema_local_business') && apply_filters('lf_schema_output_local_business', true)) {
		$local = lf_build_local_business_schema();
		if (!empty($local)) {
			$scripts[] = $local;
		}
	}

	// Organization (global) — logo + sameAs if available.
	if (lf_schema_toggle_on('lf_schema_organization') && apply_filters('lf_schema_output_organization', true)) {
		$org = lf_build_organization_schema();
		if (!empty($org)) {
			$scripts[] = $org;
		}
	}

	// WebSite schema with SearchAction.
	if (apply_filters('lf_schema_output_website', true)) {
		$site = lf_build_website_schema();
		if (!empty($site)) {
			$scripts[] = $site;
		}
	}

	// BreadcrumbList per page.
	if (apply_filters('lf_schema_output_breadcrumbs', true)) {
		$crumbs = lf_build_breadcrumb_schema();
		if (!empty($crumbs)) {
			$scripts[] = $crumbs;
		}
	}

	// Service schema on single lf_service.
	if (is_singular('lf_service') && lf_schema_toggle_on('lf_schema_service')) {
		$service = lf_build_service_schema();
		if (!empty($service)) {
			$scripts[] = $service;
		}
	}

	// Service area pages: reinforce LocalBusiness + areaServed.
	if (is_singular('lf_service_area')) {
		$area = lf_build_service_area_schema();
		if (!empty($area)) {
			$scripts[] = $area;
		}
	}

	// FAQPage when FAQs present: single lf_faq, archive lf_faq, or filter for block context.
	$faq_schema = lf_build_faq_page_schema();
	if (!empty($faq_schema) && lf_schema_toggle_on('lf_schema_faq')) {
		$scripts[] = $faq_schema;
	}

	// Review / AggregateRating when testimonials present (global or single service).
	if (lf_schema_toggle_on('lf_schema_review')) {
		$review = lf_build_review_schema();
		if (!empty($review)) {
			$scripts[] = $review;
		}
	}

	foreach ($scripts as $json) {
		echo '<script type="application/ld+json">' . "\n" . wp_json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n" . '</script>' . "\n";
	}
}

/**
 * Whether a schema toggle is on. Default true if option not set.
 */
function lf_schema_toggle_on(string $option_name): bool {
	if (function_exists('lf_seo_get_setting')) {
		if ($option_name === 'lf_schema_local_business') {
			return (bool) lf_seo_get_setting('schema.enable_local_business', true);
		}
		if ($option_name === 'lf_schema_service') {
			return (bool) lf_seo_get_setting('schema.enable_service', true);
		}
	}
	$val = lf_get_option($option_name, 'option', true);
	if (is_bool($val)) {
		return $val;
	}
	return (int) $val !== 0;
}

/**
 * Build LocalBusiness schema from global business options. Fail silently if no name.
 */
function lf_build_local_business_schema(): array {
	$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
	$name = $entity['name'] ?? lf_get_option('lf_business_name', 'option');
	if (empty($name) || !is_string($name)) {
		return [];
	}
	$type = $entity['category'] ?? 'LocalBusiness';
	$type = is_string($type) && $type !== '' ? $type : 'LocalBusiness';
	$schema = [
		'@context' => 'https://schema.org',
		'@type'    => $type,
		'@id'      => home_url('/#lf-localbusiness'),
		'name'     => $name,
		'url'      => home_url('/'),
	];
	if (!empty($entity['legal_name'])) {
		$schema['legalName'] = $entity['legal_name'];
	}
	$phone = $entity['phone_display'] ?? lf_get_option('lf_business_phone', 'option');
	if (!empty($phone)) {
		$schema['telephone'] = $phone;
	}
	$email = $entity['email'] ?? lf_get_option('lf_business_email', 'option');
	if (!empty($email)) {
		$schema['email'] = $email;
	}
	$address_str = $entity['address'] ?? lf_get_option('lf_business_address', 'option');
	$address_parts = $entity['address_parts'] ?? [];
	if (!empty($address_str)) {
		$address = ['@type' => 'PostalAddress'];
		$street = $address_parts['street'] ?? $address_str;
		if (!empty($street)) {
			$address['streetAddress'] = $street;
		}
		if (!empty($address_parts['city'])) {
			$address['addressLocality'] = $address_parts['city'];
		}
		if (!empty($address_parts['state'])) {
			$address['addressRegion'] = $address_parts['state'];
		}
		if (!empty($address_parts['zip'])) {
			$address['postalCode'] = $address_parts['zip'];
		}
		if (count($address) > 1) {
			$schema['address'] = $address;
		}
	}
	$geo = $entity['geo'] ?? lf_get_option('lf_business_geo', 'option');
	if (is_array($geo) && isset($geo['lat'], $geo['lng']) && $geo['lat'] !== '' && $geo['lng'] !== '') {
		$schema['geo'] = [
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $geo['lat'],
			'longitude' => (float) $geo['lng'],
		];
	}
	$hours = $entity['hours'] ?? lf_get_option('lf_business_hours', 'option');
	if (!empty($hours)) {
		$schema['openingHours'] = $hours;
	}
	if (!empty($entity['description'])) {
		$schema['description'] = $entity['description'];
	}
	$logo_id = (int) ($entity['logo_id'] ?? 0);
	$logo_url = function_exists('lf_business_entity_image_url') ? lf_business_entity_image_url($logo_id, 'medium') : '';
	if ($logo_url) {
		$schema['logo'] = $logo_url;
	}
	$primary_image_id = (int) ($entity['primary_image_id'] ?? 0);
	$image_url = function_exists('lf_business_entity_image_url') ? lf_business_entity_image_url($primary_image_id, 'large') : '';
	if ($image_url) {
		$schema['image'] = $image_url;
	}
	$same_as = $entity['same_as'] ?? [];
	if (!empty($same_as)) {
		$schema['sameAs'] = array_values($same_as);
	}
	$areas = function_exists('lf_business_entity_area_served') ? lf_business_entity_area_served($entity) : [];
	if (!empty($areas)) {
		$schema['areaServed'] = array_map(function ($area) {
			return ['@type' => 'Place', 'name' => $area];
		}, $areas);
	}
	if (!empty($entity['founding_year'])) {
		$schema['foundingDate'] = $entity['founding_year'];
	}
	$additional = [];
	if (!empty($entity['license_number'])) {
		$additional[] = ['@type' => 'PropertyValue', 'name' => 'License', 'value' => $entity['license_number']];
	}
	if (!empty($entity['insurance_statement'])) {
		$additional[] = ['@type' => 'PropertyValue', 'name' => 'Insurance', 'value' => $entity['insurance_statement']];
	}
	if (!empty($additional)) {
		$schema['additionalProperty'] = $additional;
	}
	return apply_filters('lf_schema_local_business', $schema);
}

/**
 * Build Organization schema from business entity data.
 */
function lf_build_organization_schema(): array {
	$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
	$name = $entity['name'] ?? '';
	if ($name === '') {
		return [];
	}
	$type = 'Organization';
	if (function_exists('lf_seo_get_setting')) {
		$type = (string) lf_seo_get_setting('schema.organization_type', 'Organization');
	}
	$allowed = [
		'Organization',
		'LocalBusiness',
		'HomeAndConstructionBusiness',
		'ProfessionalService',
		'GeneralContractor',
		'RoofingContractor',
		'Plumber',
		'HVACBusiness',
		'LandscapingBusiness',
	];
	if (!in_array($type, $allowed, true)) {
		$type = 'Organization';
	}
	$schema = [
		'@context' => 'https://schema.org',
		'@type'    => $type,
		'@id'      => home_url('/#lf-organization'),
		'name'     => $name,
		'url'      => home_url('/'),
	];
	$logo_id = (int) ($entity['logo_id'] ?? 0);
	$logo_url = function_exists('lf_business_entity_image_url') ? lf_business_entity_image_url($logo_id, 'medium') : '';
	if ($logo_url) {
		$schema['logo'] = $logo_url;
	}
	$same_as = $entity['same_as'] ?? [];
	if (!empty($same_as)) {
		$schema['sameAs'] = array_values($same_as);
	}
	return apply_filters('lf_schema_organization', $schema);
}

/**
 * Build WebSite schema with SearchAction.
 */
function lf_build_website_schema(): array {
	$name = get_bloginfo('name');
	$schema = [
		'@context' => 'https://schema.org',
		'@type'    => 'WebSite',
		'@id'      => home_url('/#lf-website'),
		'name'     => $name,
		'url'      => home_url('/'),
		'potentialAction' => [
			'@type'       => 'SearchAction',
			'target'      => home_url('/?s={search_term_string}'),
			'query-input' => 'required name=search_term_string',
		],
	];
	return apply_filters('lf_schema_website', $schema);
}

/**
 * Build BreadcrumbList schema based on lf_breadcrumb_items().
 */
function lf_build_breadcrumb_schema(): array {
	if (!function_exists('lf_breadcrumb_items')) {
		return [];
	}
	$items = lf_breadcrumb_items();
	if (empty($items) || !is_array($items)) {
		return [];
	}
	$list = [];
	$position = 1;
	foreach ($items as $item) {
		$label = $item['label'] ?? '';
		$url = $item['url'] ?? '';
		if ($label === '') {
			continue;
		}
		if ($url === '') {
			if (is_singular()) {
				$url = get_permalink();
			} else {
				$request = isset($GLOBALS['wp']) ? $GLOBALS['wp']->request : '';
				$url = home_url($request ? '/' . ltrim($request, '/') : '/');
			}
		}
		$list[] = [
			'@type' => 'ListItem',
			'position' => $position,
			'name' => $label,
			'item' => $url,
		];
		$position++;
	}
	if (empty($list)) {
		return [];
	}
	return apply_filters('lf_schema_breadcrumbs', [
		'@context' => 'https://schema.org',
		'@type' => 'BreadcrumbList',
		'itemListElement' => $list,
	]);
}

/**
 * Build Service schema for current single lf_service. Fail silently if no post.
 */
function lf_build_service_schema(): array {
	$post = get_queried_object();
	if (!$post || $post->post_type !== 'lf_service') {
		return [];
	}
	$name = get_the_title($post);
	$desc = get_the_excerpt($post) ?: '';
	if ($desc === '') {
		$desc = wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post)), 30);
	}
	if (!$desc) {
		$desc = wp_trim_words(get_the_excerpt($post), 30);
	}
	$schema = [
		'@context'    => 'https://schema.org',
		'@type'       => 'Service',
		'name'        => $name,
		'description' => $desc ?: $name,
		'url'         => get_permalink($post),
	];
	$provider = lf_build_local_business_schema();
	if (!empty($provider)) {
		$schema['provider'] = $provider;
	}
	$catalog = lf_build_offer_catalog_schema();
	if (!empty($catalog)) {
		$schema['hasOfferCatalog'] = $catalog;
	}
	return apply_filters('lf_schema_service', $schema);
}

/**
 * Build OfferCatalog schema from all services.
 */
function lf_build_offer_catalog_schema(): array {
	$services = get_posts([
		'post_type'      => 'lf_service',
		'posts_per_page' => 50,
		'post_status'    => 'publish',
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	]);
	if (empty($services)) {
		return [];
	}
	$items = [];
	$position = 1;
	foreach ($services as $svc) {
		$items[] = [
			'@type'    => 'ListItem',
			'position' => $position,
			'item'     => [
				'@type' => 'Service',
				'name'  => get_the_title($svc),
				'url'   => get_permalink($svc),
			],
		];
		$position++;
	}
	return apply_filters('lf_schema_offer_catalog', [
		'@type' => 'OfferCatalog',
		'name'  => __('Services', 'leadsforward-core'),
		'itemListElement' => $items,
	]);
}

/**
 * Build a lightweight LocalBusiness addendum for service area pages.
 */
function lf_build_service_area_schema(): array {
	$post = get_queried_object();
	if (!$post || $post->post_type !== 'lf_service_area') {
		return [];
	}
	$entity = function_exists('lf_business_entity_get') ? lf_business_entity_get() : [];
	$name = $entity['name'] ?? '';
	if ($name === '') {
		return [];
	}
	$type = $entity['category'] ?? 'LocalBusiness';
	$type = is_string($type) && $type !== '' ? $type : 'LocalBusiness';
	return apply_filters('lf_schema_service_area', [
		'@context' => 'https://schema.org',
		'@type'    => $type,
		'@id'      => home_url('/#lf-localbusiness'),
		'name'     => $name,
		'areaServed' => [
			[
				'@type' => 'Place',
				'name'  => get_the_title($post),
			],
		],
		'url'      => get_permalink($post),
	]);
}

/**
 * Build FAQPage schema. Used on single lf_faq (one item), archive lf_faq (many), or filtered list.
 */
function lf_build_faq_page_schema(): array {
	$items = [];

	if (is_singular('lf_faq')) {
		$post = get_queried_object();
		if ($post) {
			$q = function_exists('get_field') ? get_field('lf_faq_question', $post) : '';
			if (!$q) $q = $post->post_title;
			$a = function_exists('get_field') ? get_field('lf_faq_answer', $post) : $post->post_content;
			if (!$a) $a = '';
			$items[] = [
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags($a),
				],
			];
		}
	} elseif (is_post_type_archive('lf_faq')) {
		$query = new WP_Query([
			'post_type'      => 'lf_faq',
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		]);
		foreach ($query->posts as $p) {
			$q = function_exists('get_field') ? get_field('lf_faq_question', $p) : '';
			if (!$q) $q = $p->post_title;
			$a = function_exists('get_field') ? get_field('lf_faq_answer', $p) : $p->post_content;
			if (!$a) $a = '';
			$items[] = [
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags($a),
				],
			];
		}
		wp_reset_postdata();
	} else {
		$max_items = null;
		if (is_front_page() && function_exists('lf_get_homepage_section_config')) {
			$config = lf_get_homepage_section_config();
			if (!empty($config['faq_accordion']['enabled'])) {
				$max_items = isset($config['faq_accordion']['faq_max_items']) ? (int) $config['faq_accordion']['faq_max_items'] : -1;
			}
		} elseif (is_singular() && function_exists('lf_pb_get_post_config') && function_exists('lf_pb_get_context_for_post')) {
			$post = get_queried_object();
			if ($post instanceof \WP_Post) {
				$context = lf_pb_get_context_for_post($post);
				$config = lf_pb_get_post_config($post->ID, $context);
				$sections = $config['sections'] ?? [];
				foreach ($sections as $section) {
					if (!empty($section['enabled']) && ($section['section_type'] ?? '') === 'faq_accordion') {
						$max_items = isset($section['faq_max_items']) ? (int) $section['faq_max_items'] : -1;
						break;
					}
				}
			}
		}
		if ($max_items !== null) {
			if ($max_items === 0) {
				$max_items = -1;
			}
			$query = new WP_Query([
				'post_type'      => 'lf_faq',
				'posts_per_page' => $max_items > 0 ? $max_items : -1,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			]);
			foreach ($query->posts as $p) {
				$q = function_exists('get_field') ? get_field('lf_faq_question', $p) : '';
				if (!$q) $q = $p->post_title;
				$a = function_exists('get_field') ? get_field('lf_faq_answer', $p) : $p->post_content;
				if (!$a) $a = '';
				$items[] = [
					'@type'          => 'Question',
					'name'           => $q,
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags($a),
					],
				];
			}
			wp_reset_postdata();
		}
	}

	// Filter for other contexts (e.g. page with FAQ block).
	$items = apply_filters('lf_faq_schema_items', $items);
	if (empty($items)) {
		return [];
	}
	return [
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $items,
	];
}

/**
 * Build AggregateRating + Review schema from lf_testimonial. Output when testimonials exist.
 */
function lf_build_review_schema(): array {
	$query = new WP_Query([
		'post_type'      => 'lf_testimonial',
		'posts_per_page' => 50,
		'post_status'    => 'publish',
	]);
	if (!$query->have_posts()) {
		return [];
	}
	$reviews = [];
	$sum = 0;
	$count = 0;
	foreach ($query->posts as $p) {
		$rating = function_exists('get_field') ? (int) get_field('lf_testimonial_rating', $p) : 5;
		$rating = max(1, min(5, $rating));
		$text = function_exists('get_field') ? get_field('lf_testimonial_review_text', $p) : $p->post_content;
		if (!$text) $text = $p->post_title;
		$author = function_exists('get_field') ? get_field('lf_testimonial_reviewer_name', $p) : $p->post_title;
		if (!$author) $author = __('Anonymous', 'leadsforward-core');
		$reviews[] = [
			'@type'        => 'Review',
			'author'       => ['@type' => 'Person', 'name' => $author],
			'reviewRating' => [
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating' => 5,
				'worstRating' => 1,
			],
			'reviewBody'   => wp_strip_all_tags($text),
		];
		$sum += $rating;
		$count++;
	}
	wp_reset_postdata();
	if (empty($reviews)) {
		return [];
	}
	$business = lf_build_local_business_schema();
	if (empty($business)) {
		$business = [
			'@type' => 'LocalBusiness',
			'name'  => lf_get_option('lf_business_name', 'option') ?: get_bloginfo('name'),
		];
	}
	$business['aggregateRating'] = [
		'@type'       => 'AggregateRating',
		'ratingValue' => round($sum / $count, 1),
		'bestRating' => 5,
		'worstRating' => 1,
		'ratingCount' => $count,
		'reviewCount' => $count,
	];
	$business['review'] = array_slice($reviews, 0, 5);
	return $business;
}
