<?php
/**
 * Canonical fleet page section orders — single source of truth.
 *
 * @package LeadsForward_Core
 * @since 0.1.199
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Ensure FAQ accordion sits immediately before the final CTA band.
 *
 * @param list<string> $order
 * @return list<string>
 */
function lf_fleet_ensure_faq_before_final_cta(array $order): array {
	$order = array_values(array_filter($order, static function (string $type): bool {
		return $type !== '';
	}));
	if (!in_array('faq_accordion', $order, true) || !in_array('cta', $order, true)) {
		return $order;
	}
	$order = array_values(array_filter($order, static function (string $type): bool {
		return $type !== 'faq_accordion';
	}));
	$cta_index = array_search('cta', $order, true);
	if ($cta_index === false) {
		$order[] = 'faq_accordion';
	} else {
		array_splice($order, (int) $cta_index, 0, ['faq_accordion']);
	}
	return $order;
}

/**
 * Fleet marketing page slug => ordered section types.
 *
 * @return array<string, list<string>>
 */
function lf_fleet_page_section_orders(): array {
	$templates = [
		'about-us' => ['hero', 'content_image', 'benefits', 'image_content', 'process', 'faq_accordion', 'cta'],
		'why-choose-us' => ['hero', 'benefits', 'content_image', 'image_content', 'faq_accordion', 'cta'],
		'services' => ['hero', 'service_intro', 'content_image', 'faq_accordion', 'cta'],
		'service-areas' => ['hero', 'service_areas', 'faq_accordion', 'cta'],
		'reviews' => ['hero', 'trust_reviews', 'faq_accordion', 'cta'],
		'blog' => ['hero', 'blog_posts', 'faq_accordion', 'cta'],
		'faq' => ['hero', 'faq_accordion', 'cta'],
		'contact' => ['hero', 'map_nap', 'faq_accordion', 'cta'],
		'sitemap' => ['hero', 'sitemap_links'],
		'privacy-policy' => ['hero', 'content'],
		'terms-of-service' => ['hero', 'content'],
		'thank-you' => ['hero', 'content', 'faq_accordion'],
	];

	foreach ($templates as $slug => $order) {
		$templates[$slug] = lf_fleet_ensure_faq_before_final_cta($order);
	}

	return $templates;
}

/**
 * @return list<string>
 */
function lf_fleet_page_section_order(string $slug): array {
	$slug = sanitize_title($slug);
	$orders = lf_fleet_page_section_orders();
	if (isset($orders[$slug])) {
		return $orders[$slug];
	}
	return ['hero', 'content'];
}

/**
 * CPT default section orders (non-page contexts).
 *
 * @return array<string, list<string>>
 */
function lf_fleet_cpt_section_orders(): array {
	return [
		'service' => lf_fleet_ensure_faq_before_final_cta([
			'hero',
			'trust_bar',
			'service_details',
			'service_details',
			'benefits',
			'process',
			'faq_accordion',
			'cta',
		]),
		'service_area' => lf_fleet_ensure_faq_before_final_cta([
			'hero',
			'trust_bar',
			'content_image',
			'benefits',
			'content_image',
			'image_content',
			'process',
			'related_links',
			'nearby_areas',
			'faq_accordion',
			'cta',
		]),
		'post' => ['hero', 'content', 'related_links', 'cta'],
	];
}

/**
 * Render visible breadcrumb trail for interior heroes.
 */
function lf_render_breadcrumb_trail(): void {
	if (!function_exists('lf_breadcrumb_items') || is_front_page()) {
		return;
	}
	$items = lf_breadcrumb_items();
	if (!is_array($items) || count($items) < 2) {
		return;
	}
	echo '<nav class="lf-breadcrumbs" aria-label="' . esc_attr__('Breadcrumb', 'leadsforward-core') . '">';
	echo '<ol class="lf-breadcrumbs__list">';
	foreach ($items as $index => $item) {
		if (!is_array($item)) {
			continue;
		}
		$label = trim((string) ($item['label'] ?? ''));
		$url = trim((string) ($item['url'] ?? ''));
		if ($label === '') {
			continue;
		}
		$is_last = $index === count($items) - 1;
		echo '<li class="lf-breadcrumbs__item">';
		if (!$is_last && $url !== '') {
			echo '<a class="lf-breadcrumbs__link" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
		} else {
			echo '<span class="lf-breadcrumbs__current" aria-current="page">' . esc_html($label) . '</span>';
		}
		echo '</li>';
	}
	echo '</ol>';
	echo '</nav>';
}
