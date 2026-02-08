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
	if (lf_schema_toggle_on('lf_schema_local_business')) {
		$local = lf_build_local_business_schema();
		if (!empty($local)) {
			$scripts[] = $local;
		}
	}

	// Service schema on single lf_service.
	if (is_singular('lf_service')) {
		$service = lf_build_service_schema();
		if (!empty($service)) {
			$scripts[] = $service;
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
	$name = lf_get_option('lf_business_name', 'option');
	if (empty($name) || !is_string($name)) {
		return [];
	}
	$schema = [
		'@context' => 'https://schema.org',
		'@type'    => 'LocalBusiness',
		'name'     => $name,
	];
	$phone = lf_get_option('lf_business_phone', 'option');
	if (!empty($phone)) {
		$schema['telephone'] = $phone;
	}
	$email = lf_get_option('lf_business_email', 'option');
	if (!empty($email)) {
		$schema['email'] = $email;
	}
	$address_str = lf_get_option('lf_business_address', 'option');
	if (!empty($address_str)) {
		$schema['address'] = [
			'@type'           => 'PostalAddress',
			'streetAddress'   => $address_str,
		];
	}
	$geo = lf_get_option('lf_business_geo', 'option');
	if (is_array($geo) && isset($geo['lat'], $geo['lng']) && $geo['lat'] !== '' && $geo['lng'] !== '') {
		$schema['geo'] = [
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $geo['lat'],
			'longitude' => (float) $geo['lng'],
		];
	}
	$hours = lf_get_option('lf_business_hours', 'option');
	if (!empty($hours)) {
		$schema['openingHours'] = $hours;
	}
	$schema['url'] = home_url('/');
	return $schema;
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
	return $schema;
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
