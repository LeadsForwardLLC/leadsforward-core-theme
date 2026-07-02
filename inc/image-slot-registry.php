<?php
/**
 * Per-section image slot registry for n8n placement workflows.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Image field keys for a section type from the sections registry.
 *
 * @return list<string>
 */
function lf_image_slot_field_keys_for_section(string $section_type): array {
	if (!function_exists('lf_sections_registry')) {
		return [];
	}
	$registry = lf_sections_registry();
	$base = function_exists('lf_homepage_base_section_type')
		? lf_homepage_base_section_type($section_type)
		: $section_type;
	$section = is_array($registry[$base] ?? null) ? $registry[$base] : (is_array($registry[$section_type] ?? null) ? $registry[$section_type] : []);
	if ($section === [] || !function_exists('lf_image_intelligence_registry_image_fields')) {
		return [];
	}

	return lf_image_intelligence_registry_image_fields($section);
}

/**
 * Canonical slot registry for a page-builder / homepage context key.
 *
 * @return list<array<string, mixed>>
 */
function lf_image_slot_registry_for_context(string $context): array {
	$context = sanitize_key($context);
	$slots = [];

	if ($context === 'homepage' || $context === 'home') {
		if (!function_exists('lf_sections_default_order')) {
			return [];
		}
		foreach (lf_sections_default_order('homepage') as $section_type) {
			foreach (lf_image_slot_field_keys_for_section($section_type) as $field_key) {
				$slots[] = [
					'context' => 'homepage',
					'section_type' => $section_type,
					'field_key' => $field_key,
					'slot' => function_exists('lf_image_intelligence_slot_for_section_field')
						? lf_image_intelligence_slot_for_section_field($section_type, $field_key)
						: $field_key,
					'storage' => 'homepage_option',
				];
			}
		}
		return (array) apply_filters('lf_image_slot_registry_for_context', $slots, $context);
	}

	if (!function_exists('lf_pci_registry')) {
		return [];
	}
	$slug = $context;
	if ($context === 'about') {
		$slug = 'about-us';
	}
	$schema = lf_pci_registry()[$slug] ?? null;
	if (!is_array($schema)) {
		return (array) apply_filters('lf_image_slot_registry_for_context', $slots, $context);
	}
	foreach ((array) ($schema['order'] ?? []) as $section_type) {
		foreach (lf_image_slot_field_keys_for_section($section_type) as $field_key) {
			$slots[] = [
				'context' => $slug,
				'section_type' => $section_type,
				'field_key' => $field_key,
				'slot' => function_exists('lf_image_intelligence_slot_for_section_field')
					? lf_image_intelligence_slot_for_section_field($section_type, $field_key)
					: $field_key,
				'storage' => 'post_meta',
			];
		}
	}

	return (array) apply_filters('lf_image_slot_registry_for_context', $slots, $context);
}

/**
 * @return list<array<string, mixed>>
 */
function lf_image_slot_collect_for_homepage(bool $empty_only = true): array {
	if (!function_exists('lf_get_homepage_section_config')) {
		return [];
	}
	$config = lf_get_homepage_section_config();
	if (!is_array($config)) {
		return [];
	}
	$out = [];
	foreach (lf_image_slot_registry_for_context('homepage') as $def) {
		$section_type = (string) ($def['section_type'] ?? '');
		$field_key = (string) ($def['field_key'] ?? '');
		if ($section_type === '' || $field_key === '' || !isset($config[$section_type])) {
			continue;
		}
		$settings = is_array($config[$section_type]) ? $config[$section_type] : [];
		$value = $settings[$field_key] ?? '';
		$is_empty = function_exists('lf_image_intelligence_empty_image_value')
			? lf_image_intelligence_empty_image_value($value)
			: empty($value);
		if ($empty_only && !$is_empty) {
			continue;
		}
		$out[] = array_merge($def, [
			'instance_id' => $section_type,
			'attachment_id' => is_numeric($value) ? (int) $value : 0,
			'section_text_excerpt' => lf_image_slot_section_text_excerpt($section_type, $settings),
		]);
	}

	return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function lf_image_slot_collect_for_post(int $post_id, bool $empty_only = true): array {
	$post_id = absint($post_id);
	if ($post_id <= 0 || !function_exists('lf_pb_get_context_for_post') || !function_exists('lf_pb_get_post_config')) {
		return [];
	}
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return [];
	}

	if ((int) get_option('page_on_front') === $post_id) {
		return lf_image_slot_collect_for_homepage($empty_only);
	}

	$context = lf_pb_get_context_for_post($post);
	if ($context === '') {
		$context = (string) $post->post_name;
	}
	$config = lf_pb_get_post_config($post_id, $context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$out = [];

	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		foreach (lf_image_slot_field_keys_for_section($type) as $field_key) {
			$value = $settings[$field_key] ?? '';
			$is_empty = function_exists('lf_image_intelligence_empty_image_value')
				? lf_image_intelligence_empty_image_value($value)
				: empty($value);
			if ($empty_only && !$is_empty) {
				continue;
			}
			$out[] = [
				'context' => $context,
				'section_type' => $type,
				'instance_id' => (string) $instance_id,
				'field_key' => $field_key,
				'slot' => function_exists('lf_image_intelligence_slot_for_section_field')
					? lf_image_intelligence_slot_for_section_field($type, $field_key)
					: $field_key,
				'storage' => 'post_meta',
				'attachment_id' => is_numeric($value) ? (int) $value : 0,
				'section_text_excerpt' => lf_image_slot_section_text_excerpt($type, $settings),
			];
		}
	}

	$thumb = (int) get_post_thumbnail_id($post_id);
	$thumb_empty = function_exists('lf_image_intelligence_empty_image_value')
		? lf_image_intelligence_empty_image_value($thumb)
		: $thumb <= 0;
	if (!$empty_only || $thumb_empty) {
		$out[] = [
			'context' => $context,
			'section_type' => 'featured',
			'instance_id' => 'featured',
			'field_key' => '_thumbnail_id',
			'slot' => 'featured',
			'storage' => 'post_thumbnail',
			'attachment_id' => $thumb,
			'section_text_excerpt' => wp_trim_words(wp_strip_all_tags((string) $post->post_excerpt ?: $post->post_content), 40, '…'),
		];
	}

	return (array) apply_filters('lf_image_slot_collect_for_post', $out, $post_id, $empty_only);
}

/**
 * @param array<string, mixed> $settings
 */
function lf_image_slot_section_text_excerpt(string $section_type, array $settings): string {
	$parts = [];
	$keys = ['hero_headline', 'hero_subheadline', 'section_heading', 'section_intro', 'section_body', 'cta_headline'];
	foreach ($keys as $key) {
		$val = trim(wp_strip_all_tags((string) ($settings[$key] ?? '')));
		if ($val !== '') {
			$parts[] = $val;
		}
	}
	$text = implode(' ', $parts);
	if ($text === '') {
		return '';
	}
	return wp_trim_words($text, 45, '…');
}

/**
 * @return array<string, mixed>
 */
function lf_image_slot_build_page_payload(int $post_id): array {
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post) {
		return [];
	}
	$context = function_exists('lf_pb_get_context_for_post') ? lf_pb_get_context_for_post($post) : (string) $post->post_name;
	$primary_keyword = (string) get_post_meta($post_id, '_lf_seo_primary_keyword', true);

	return [
		'post_id' => $post_id,
		'post_type' => (string) $post->post_type,
		'slug' => (string) $post->post_name,
		'title' => get_the_title($post),
		'permalink' => get_permalink($post_id),
		'context' => $context !== '' ? $context : (string) $post->post_name,
		'is_front_page' => (int) get_option('page_on_front') === $post_id,
		'primary_keyword' => $primary_keyword,
		'image_slots' => lf_image_slot_collect_for_post($post_id, true),
		'slot_registry' => lf_image_slot_registry_for_context($context !== '' ? $context : (string) $post->post_name),
	];
}
