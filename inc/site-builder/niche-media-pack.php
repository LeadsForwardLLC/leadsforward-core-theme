<?php
/**
 * Niche media pack import + slot assignment for Site Builder.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_NICHE_PACK_ATTACHMENT_MAP_OPTION = 'lf_niche_pack_attachment_map';

/**
 * Resolve niche slug from manifest or site options.
 *
 * @param array<string, mixed> $manifest
 */
function lf_site_builder_niche_slug_from_manifest(array $manifest): string {
	$slug = sanitize_title((string) ($manifest['business']['niche_slug'] ?? ''));
	if ($slug !== '') {
		return $slug;
	}
	$niche = sanitize_title((string) ($manifest['business']['niche'] ?? ''));
	if ($niche !== '') {
		return $niche;
	}
	return sanitize_title((string) get_option('lf_homepage_niche_slug', 'foundation-repair'));
}

/**
 * Load pack.json for a niche.
 *
 * @return array<string, mixed>|null
 */
function lf_site_builder_load_niche_pack(string $niche_slug): ?array {
	$niche_slug = sanitize_title($niche_slug);
	if ($niche_slug === '') {
		return null;
	}
	$path = LF_THEME_DIR . '/assets/niche-packs/' . $niche_slug . '/pack.json';
	if (!is_readable($path)) {
		return null;
	}
	$raw = file_get_contents($path);
	if (!is_string($raw) || $raw === '') {
		return null;
	}
	$data = json_decode($raw, true);
	return is_array($data) ? $data : null;
}

/**
 * Replace {city} and {business} tokens in alt text.
 */
function lf_site_builder_pack_alt_text(string $template, array $manifest): string {
	$city = trim((string) ($manifest['business']['primary_city'] ?? ($manifest['business']['address']['city'] ?? get_option('lf_city_region', ''))));
	$business = trim((string) ($manifest['business']['name'] ?? get_option('lf_business_name', get_bloginfo('name'))));
	$out = str_replace(['{city}', '{business}'], [$city, $business], $template);
	return sanitize_text_field($out);
}

/**
 * Import pack images into the media library (cached per image_key).
 *
 * @param array<string, mixed> $pack
 * @param array<string, mixed> $manifest
 * @return array<string, int>
 */
function lf_site_builder_import_pack_images(array $pack, array $manifest, string $niche_slug): array {
	$images = is_array($pack['images'] ?? null) ? $pack['images'] : [];
	if ($images === []) {
		return [];
	}
	$cached = get_option(LF_NICHE_PACK_ATTACHMENT_MAP_OPTION, []);
	$cached = is_array($cached) ? $cached : [];
	$pack_cache_key = 'pack:' . sanitize_title($niche_slug);
	$pack_map = is_array($cached[$pack_cache_key] ?? null) ? $cached[$pack_cache_key] : [];
	$imported = [];

	if (function_exists('lf_images_require_media_functions')) {
		lf_images_require_media_functions();
	}

	$pack_dir = LF_THEME_DIR . '/assets/niche-packs/' . sanitize_title($niche_slug);
	foreach ($images as $image_key => $spec) {
		if (!is_string($image_key) || $image_key === '' || !is_array($spec)) {
			continue;
		}
		$existing_id = (int) ($pack_map[$image_key] ?? 0);
		if ($existing_id > 0 && get_post($existing_id)) {
			$imported[$image_key] = $existing_id;
			continue;
		}
		$file = (string) ($spec['file'] ?? '');
		if ($file === '') {
			continue;
		}
		$source = $pack_dir . '/' . ltrim($file, '/');
		if (!is_readable($source)) {
			continue;
		}
		$tmp = wp_tempnam(basename($file));
		if (!$tmp || !@copy($source, $tmp)) {
			continue;
		}
		$file_array = [
			'name' => basename($file),
			'tmp_name' => $tmp,
		];
		$attachment_id = media_handle_sideload($file_array, 0, null);
		if (is_wp_error($attachment_id)) {
			@unlink($tmp);
			continue;
		}
		$alt = lf_site_builder_pack_alt_text((string) ($spec['alt'] ?? ''), $manifest);
		if ($alt !== '') {
			update_post_meta((int) $attachment_id, '_wp_attachment_image_alt', $alt);
			wp_update_post([
				'ID' => (int) $attachment_id,
				'post_title' => $alt,
			]);
		}
		$imported[$image_key] = (int) $attachment_id;
		$pack_map[$image_key] = (int) $attachment_id;
	}

	if ($pack_map !== []) {
		$cached[$pack_cache_key] = $pack_map;
		update_option(LF_NICHE_PACK_ATTACHMENT_MAP_OPTION, $cached, false);
	}

	return $imported;
}

/**
 * Find homepage section keys matching a section type.
 *
 * @param array<string, mixed> $home_config
 * @return string[]
 */
function lf_site_builder_homepage_section_keys_for_type(array $home_config, string $section_type): array {
	$section_type = sanitize_key($section_type);
	$keys = [];
	foreach (array_keys($home_config) as $section_id) {
		$section_id = (string) $section_id;
		$base = function_exists('lf_homepage_base_section_type') ? lf_homepage_base_section_type($section_id) : $section_id;
		if ($section_id === $section_type || $base === $section_type) {
			$keys[] = $section_id;
		}
	}
	return $keys;
}

/**
 * Apply one pack assignment to homepage config.
 *
 * @param array<string, mixed> $home_config
 */
function lf_site_builder_apply_homepage_assignment(array &$home_config, array $assignment, array $attachment_map): bool {
	$section_type = sanitize_key((string) ($assignment['section_type'] ?? ''));
	$field = sanitize_key((string) ($assignment['field'] ?? ''));
	$image_key = (string) ($assignment['image_key'] ?? '');
	if ($section_type === '' || $field === '' || $image_key === '' || empty($attachment_map[$image_key])) {
		return false;
	}
	$attachment_id = (int) $attachment_map[$image_key];
	$keys = lf_site_builder_homepage_section_keys_for_type($home_config, $section_type);
	if ($keys === []) {
		return false;
	}
	$changed = false;
	foreach ($keys as $section_id) {
		if (!isset($home_config[$section_id]) || !is_array($home_config[$section_id])) {
			continue;
		}
		$current = (int) ($home_config[$section_id][$field] ?? 0);
		if ($current > 0) {
			continue;
		}
		$home_config[$section_id][$field] = $attachment_id;
		if ($field === 'hero_background_image_id') {
			$home_config[$section_id]['hero_background_mode'] = 'image';
		}
		$changed = true;
		break;
	}
	return $changed;
}

/**
 * Apply pack assignment to page-builder posts.
 *
 * @return int Number of posts updated.
 */
function lf_site_builder_apply_pb_assignment(array $assignment, array $attachment_map, string $post_type, int $limit = 0): int {
	$section_type = sanitize_key((string) ($assignment['section_type'] ?? ''));
	$field = sanitize_key((string) ($assignment['field'] ?? ''));
	$image_key = (string) ($assignment['image_key'] ?? '');
	if ($section_type === '' || $field === '' || $image_key === '' || empty($attachment_map[$image_key]) || !defined('LF_PB_META_KEY')) {
		return 0;
	}
	if (!function_exists('lf_pb_get_post_config') || !function_exists('lf_pb_get_context_for_post')) {
		return 0;
	}
	$attachment_id = (int) $attachment_map[$image_key];
	$post_ids = get_posts([
		'post_type' => $post_type,
		'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
		'posts_per_page' => $limit > 0 ? $limit : -1,
		'orderby' => 'menu_order title',
		'order' => 'ASC',
		'fields' => 'ids',
		'no_found_rows' => true,
	]);
	$updated = 0;
	foreach (array_map('intval', $post_ids) as $post_id) {
		$post = get_post($post_id);
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$context = lf_pb_get_context_for_post($post);
		if ($context === '') {
			continue;
		}
		$config = lf_pb_get_post_config($post_id, $context);
		$order = is_array($config['order'] ?? null) ? $config['order'] : [];
		$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
		if ($order === [] || $sections === []) {
			continue;
		}
		$changed = false;
		foreach ($order as $instance_id) {
			$section = $sections[$instance_id] ?? null;
			if (!is_array($section) || empty($section['enabled'])) {
				continue;
			}
			if (sanitize_key((string) ($section['type'] ?? '')) !== $section_type) {
				continue;
			}
			$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
			$current = (int) ($settings[$field] ?? 0);
			if ($current > 0) {
				continue;
			}
			$settings[$field] = $attachment_id;
			if ($field === 'hero_background_image_id') {
				$settings['hero_background_mode'] = 'image';
			}
			$sections[$instance_id]['settings'] = $settings;
			$changed = true;
			break;
		}
		if ($changed) {
			update_post_meta($post_id, LF_PB_META_KEY, [
				'order' => $order,
				'sections' => $sections,
				'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
			]);
			$updated++;
		}
	}
	return $updated;
}

/**
 * Import niche pack and apply image slot assignments.
 *
 * @param array<string, mixed> $manifest
 * @return array{ok: bool, niche: string, images_imported: int, assignments_applied: int, error?: string}
 */
function lf_site_builder_apply_niche_media_pack(array $manifest): array {
	$niche_slug = lf_site_builder_niche_slug_from_manifest($manifest);
	$pack = lf_site_builder_load_niche_pack($niche_slug);
	if ($pack === null) {
		return [
			'ok' => true,
			'niche' => $niche_slug,
			'images_imported' => 0,
			'assignments_applied' => 0,
		];
	}
	$attachment_map = lf_site_builder_import_pack_images($pack, $manifest, $niche_slug);
	$assignments = is_array($pack['assignments'] ?? null) ? $pack['assignments'] : [];
	$applied = 0;

	if (defined('LF_HOMEPAGE_CONFIG_OPTION') && function_exists('lf_get_homepage_section_config')) {
		$home_config = lf_get_homepage_section_config();
		if (is_array($home_config)) {
			$home_changed = false;
			foreach ($assignments as $assignment) {
				if (!is_array($assignment) || (string) ($assignment['target'] ?? '') !== 'homepage') {
					continue;
				}
				if (lf_site_builder_apply_homepage_assignment($home_config, $assignment, $attachment_map)) {
					$applied++;
					$home_changed = true;
				}
			}
			if ($home_changed) {
				update_option(LF_HOMEPAGE_CONFIG_OPTION, $home_config, true);
			}
		}
	}

	foreach ($assignments as $assignment) {
		if (!is_array($assignment)) {
			continue;
		}
		$target = (string) ($assignment['target'] ?? '');
		if ($target === 'service') {
			$limit = isset($assignment['limit']) ? max(0, (int) $assignment['limit']) : 0;
			$applied += lf_site_builder_apply_pb_assignment($assignment, $attachment_map, 'lf_service', $limit);
		}
	}

	return [
		'ok' => true,
		'niche' => $niche_slug,
		'images_imported' => count($attachment_map),
		'assignments_applied' => $applied,
	];
}
