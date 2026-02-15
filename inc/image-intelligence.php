<?php
/**
 * Deterministic media intelligence + matching for orchestrated image assignment.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_MEDIA_INDEX_TRANSIENT = 'lf_media_index_cache';
const LF_MEDIA_VISION_OPTION = 'lf_media_vision_annotations';

add_action('add_attachment', 'lf_invalidate_media_index_cache');
add_action('edit_attachment', 'lf_invalidate_media_index_cache');
add_action('delete_attachment', 'lf_invalidate_media_index_cache');
add_action('add_attachment', 'lf_image_intelligence_finalize_uploaded_attachment', 20);
add_filter('wp_handle_upload_prefilter', 'lf_image_intelligence_upload_prefilter');
add_filter('wp_editor_set_quality', 'lf_image_intelligence_editor_quality', 10, 2);
add_filter('wp_generate_attachment_metadata', 'lf_image_intelligence_optimize_uploaded_image', 10, 2);
add_action('admin_menu', 'lf_image_intelligence_register_debug_page');

function lf_invalidate_media_index_cache(): void {
	delete_transient(LF_MEDIA_INDEX_TRANSIENT);
}

function lf_image_intelligence_get_vision_annotations(): array {
	$raw = get_option(LF_MEDIA_VISION_OPTION, []);
	return is_array($raw) ? $raw : [];
}

function lf_image_intelligence_update_vision_annotations(array $annotations): void {
	update_option(LF_MEDIA_VISION_OPTION, $annotations, false);
}

function lf_image_intelligence_normalize_filename(string $filename): string {
	$filename = strtolower(trim($filename));
	$filename = preg_replace('/\.[a-z0-9]+$/', '', $filename);
	$filename = preg_replace('/[^a-z0-9]+/', '-', $filename);
	$filename = trim((string) $filename, '-');
	return $filename;
}

function lf_image_intelligence_is_placeholder_asset(int $attachment_id): bool {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return false;
	}
	$manual_skip = (string) get_post_meta($attachment_id, '_lf_skip_auto_distribution', true);
	if ($manual_skip === '1') {
		return true;
	}
	$file = (string) get_attached_file($attachment_id);
	$filename = strtolower((string) basename($file));
	$title = strtolower((string) get_the_title($attachment_id));
	$caption = strtolower((string) wp_get_attachment_caption($attachment_id));
	$stack = trim($filename . ' ' . $title . ' ' . $caption);
	$markers = [
		'placeholder',
		'stock-interior',
		'leadforward-placeholder',
		'sample-image',
	];
	foreach ($markers as $marker) {
		if ($marker !== '' && strpos($stack, $marker) !== false) {
			return true;
		}
	}
	return false;
}

function lf_image_intelligence_upload_context_defaults(): array {
	$keywords = get_option('lf_homepage_keywords', []);
	$primary = is_array($keywords) ? sanitize_text_field((string) ($keywords['primary'] ?? '')) : '';
	$city = sanitize_text_field((string) get_option('lf_homepage_city', ''));
	$niche = sanitize_text_field((string) (defined('LF_HOMEPAGE_NICHE_OPTION') ? get_option(LF_HOMEPAGE_NICHE_OPTION, '') : ''));
	$service_name = $primary !== '' ? $primary : ($niche !== '' ? $niche : __('service image', 'leadsforward-core'));
	return [
		'primary_keyword' => $primary,
		'city' => $city,
		'niche' => $niche,
		'service_name' => $service_name,
	];
}

function lf_image_intelligence_upload_base_slug(): string {
	$context = lf_image_intelligence_upload_context_defaults();
	$parts = array_filter([
		(string) ($context['primary_keyword'] ?? ''),
		(string) ($context['city'] ?? ''),
		(string) ($context['niche'] ?? ''),
	]);
	if (empty($parts)) {
		$parts = [(string) get_bloginfo('name'), 'upload'];
	}
	$slug = sanitize_title(implode(' ', $parts));
	return $slug !== '' ? $slug : 'site-image';
}

function lf_image_intelligence_next_upload_index(): int {
	$current = (int) get_option('lf_image_upload_counter', 0);
	$next = $current + 1;
	update_option('lf_image_upload_counter', $next, false);
	return $next;
}

function lf_image_intelligence_generate_upload_filename(string $original_name): string {
	$ext = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
	if ($ext === '') {
		$ext = 'jpg';
	}
	$base = lf_image_intelligence_upload_base_slug();
	$index = lf_image_intelligence_next_upload_index();
	return sprintf('%s-%03d.%s', $base, $index, $ext);
}

function lf_image_intelligence_rename_attachment_file(int $attachment_id, string $target_base_slug): bool {
	$attachment_id = (int) $attachment_id;
	$target_base_slug = sanitize_title($target_base_slug);
	if ($attachment_id <= 0 || $target_base_slug === '') {
		return false;
	}
	$current_path = (string) get_attached_file($attachment_id);
	if ($current_path === '' || !file_exists($current_path)) {
		return false;
	}
	$dir = dirname($current_path);
	$ext = strtolower((string) pathinfo($current_path, PATHINFO_EXTENSION));
	if ($ext === '') {
		$ext = 'jpg';
	}
	$target_name = wp_unique_filename($dir, $target_base_slug . '.' . $ext);
	$target_path = trailingslashit($dir) . $target_name;
	if ($target_path === $current_path) {
		return true;
	}
	$renamed = @rename($current_path, $target_path);
	if (!$renamed) {
		return false;
	}
	update_attached_file($attachment_id, $target_path);
	$metadata = wp_get_attachment_metadata($attachment_id);
	$upload_dir = wp_get_upload_dir();
	$basedir = (string) ($upload_dir['basedir'] ?? '');
	if (is_array($metadata) && $basedir !== '') {
		$relative = ltrim(str_replace(trailingslashit($basedir), '', $target_path), '/');
		$metadata['file'] = $relative;
		wp_update_attachment_metadata($attachment_id, $metadata);
	}
	return true;
}

function lf_image_intelligence_upload_prefilter(array $file): array {
	$name = (string) ($file['name'] ?? '');
	if ($name === '') {
		return $file;
	}
	$type = wp_check_filetype($name);
	$mime = (string) ($type['type'] ?? '');
	if (strpos($mime, 'image/') !== 0) {
		return $file;
	}
	$file['name'] = lf_image_intelligence_generate_upload_filename($name);
	return $file;
}

function lf_image_intelligence_editor_quality(int $quality, string $mime_type): int {
	if (in_array($mime_type, ['image/jpeg', 'image/webp', 'image/avif'], true)) {
		return 72;
	}
	return $quality;
}

function lf_image_intelligence_optimize_uploaded_image(array $metadata, int $attachment_id): array {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return $metadata;
	}
	if ((string) get_post_meta($attachment_id, '_lf_image_processing_lock', true) === '1') {
		return $metadata;
	}
	update_post_meta($attachment_id, '_lf_image_processing_lock', '1');
	if ((string) get_post_meta($attachment_id, '_lf_image_optimized', true) === '1') {
		delete_post_meta($attachment_id, '_lf_image_processing_lock');
		return $metadata;
	}
	$mime = (string) get_post_mime_type($attachment_id);
	if (strpos($mime, 'image/') !== 0) {
		delete_post_meta($attachment_id, '_lf_image_processing_lock');
		return $metadata;
	}
	$file = (string) get_attached_file($attachment_id);
	if ($file === '' || !file_exists($file)) {
		delete_post_meta($attachment_id, '_lf_image_processing_lock');
		return $metadata;
	}
	if ($mime === 'image/png' && (string) get_post_meta($attachment_id, '_lf_image_png_converted', true) !== '1') {
		if (!lf_image_intelligence_png_has_transparency($file)) {
			$converted = lf_image_intelligence_convert_png_to_jpeg($attachment_id, $file);
			if (is_array($converted)) {
				update_post_meta($attachment_id, '_lf_image_png_converted', '1');
				update_post_meta($attachment_id, '_lf_image_optimized', '1');
				delete_post_meta($attachment_id, '_lf_image_processing_lock');
				return $converted;
			}
		}
	}
	$editor = wp_get_image_editor($file);
	if (is_wp_error($editor)) {
		delete_post_meta($attachment_id, '_lf_image_processing_lock');
		return $metadata;
	}
	$size = method_exists($editor, 'get_size') ? $editor->get_size() : [];
	$width = (int) ($size['width'] ?? 0);
	$height = (int) ($size['height'] ?? 0);
	if ($width > 0 && $height > 0 && ($width > 1600 || $height > 1600)) {
		$editor->resize(1600, 1600, false);
	}
	if (method_exists($editor, 'set_quality')) {
		$editor->set_quality(72);
	}
	$saved = $editor->save($file);
	if (!is_wp_error($saved)) {
		$current_size = @filesize($file);
		if (is_int($current_size) && $current_size > 120000) {
			if (method_exists($editor, 'set_quality')) {
				$editor->set_quality(62);
			}
			$editor->save($file);
		}
	}
	if (!is_wp_error($saved)) {
		update_post_meta($attachment_id, '_lf_image_optimized', '1');
		lf_image_intelligence_store_image_profile($attachment_id);
	}
	delete_post_meta($attachment_id, '_lf_image_processing_lock');
	return $metadata;
}

function lf_image_intelligence_store_image_profile(int $attachment_id): void {
	$file = (string) get_attached_file($attachment_id);
	if ($file === '' || !file_exists($file)) {
		return;
	}
	$size = @getimagesize($file);
	$width = is_array($size) ? (int) ($size[0] ?? 0) : 0;
	$height = is_array($size) ? (int) ($size[1] ?? 0) : 0;
	$bytes = @filesize($file);
	$mime = (string) get_post_mime_type($attachment_id);
	$profile = [
		'width' => $width,
		'height' => $height,
		'bytes' => is_int($bytes) ? $bytes : 0,
		'mime' => $mime,
		'ext' => strtolower((string) pathinfo($file, PATHINFO_EXTENSION)),
		'updated' => time(),
	];
	update_post_meta($attachment_id, '_lf_image_profile', $profile);
}

function lf_image_intelligence_png_has_transparency(string $file): bool {
	if ($file === '' || !file_exists($file)) {
		return false;
	}
	$handle = @fopen($file, 'rb');
	if (!$handle) {
		return false;
	}
	$header = fread($handle, 64 * 1024);
	fclose($handle);
	if (!is_string($header) || strlen($header) < 33) {
		return false;
	}
	// PNG color type byte in IHDR chunk payload (offset 25)
	$color_type = ord($header[25]);
	if (in_array($color_type, [4, 6], true)) {
		return true;
	}
	// Palette transparency chunk
	return strpos($header, 'tRNS') !== false;
}

function lf_image_intelligence_convert_png_to_jpeg(int $attachment_id, string $source_file): array {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0 || $source_file === '' || !file_exists($source_file)) {
		return [];
	}
	$editor = wp_get_image_editor($source_file);
	if (is_wp_error($editor)) {
		return [];
	}
	if (method_exists($editor, 'set_quality')) {
		$editor->set_quality(72);
	}
	$dir = dirname($source_file);
	$base = sanitize_title((string) pathinfo($source_file, PATHINFO_FILENAME));
	if ($base === '') {
		$base = 'image';
	}
	$jpg_name = wp_unique_filename($dir, $base . '.jpg');
	$jpg_path = trailingslashit($dir) . $jpg_name;
	$saved = $editor->save($jpg_path, 'image/jpeg');
	if (is_wp_error($saved)) {
		return [];
	}
	update_attached_file($attachment_id, $jpg_path);
	wp_update_post([
		'ID' => $attachment_id,
		'post_mime_type' => 'image/jpeg',
	]);
	$new_metadata = wp_generate_attachment_metadata($attachment_id, $jpg_path);
	if (is_array($new_metadata)) {
		wp_update_attachment_metadata($attachment_id, $new_metadata);
	}
	lf_image_intelligence_store_image_profile($attachment_id);
	if ($source_file !== $jpg_path && file_exists($source_file)) {
		@unlink($source_file);
	}
	return is_array($new_metadata) ? $new_metadata : [];
}

/**
 * @return string[]
 */
function lf_image_intelligence_tokenize(string $value): array {
	$value = strtolower(trim($value));
	if ($value === '') {
		return [];
	}
	$value = preg_replace('/[^a-z0-9]+/', ' ', $value);
	$parts = preg_split('/\s+/', (string) $value);
	if (!is_array($parts)) {
		return [];
	}
	$tokens = array_values(array_unique(array_filter(array_map('trim', $parts))));
	return $tokens;
}

/**
 * Build and cache full image metadata/token index.
 *
 * @return array<int,array<string,mixed>>
 */
function lf_build_media_index(): array {
	$cached = get_transient(LF_MEDIA_INDEX_TRANSIENT);
	if (is_array($cached)) {
		return $cached;
	}

	$ids = get_posts([
		'post_type' => 'attachment',
		'post_mime_type' => 'image',
		'post_status' => 'inherit',
		'posts_per_page' => -1,
		'orderby' => 'ID',
		'order' => 'ASC',
		'fields' => 'ids',
		'no_found_rows' => true,
	]);

	$index = [];
	$vision_map = lf_image_intelligence_get_vision_annotations();
	foreach ($ids as $attachment_id) {
		$attachment_id = (int) $attachment_id;
		if (lf_image_intelligence_is_placeholder_asset($attachment_id)) {
			continue;
		}
		$file = (string) get_attached_file($attachment_id);
		$filename = $file !== '' ? basename($file) : '';
		if ($filename === '') {
			continue;
		}
		$normalized_filename = lf_image_intelligence_normalize_filename($filename);
		$title = (string) get_the_title($attachment_id);
		$alt = (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
		$caption = (string) wp_get_attachment_caption($attachment_id);
		$attached_post_id = (int) get_post_field('post_parent', $attachment_id);

		$tokens = [];
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($normalized_filename));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($title));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($alt));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($caption));
		$tokens = array_values(array_unique($tokens));
		$vision = is_array($vision_map[$attachment_id] ?? null) ? $vision_map[$attachment_id] : [];
		$vision_description = (string) ($vision['description'] ?? '');
		$vision_alt = (string) ($vision['alt_text'] ?? '');
		$vision_keywords = is_array($vision['keywords'] ?? null) ? $vision['keywords'] : [];
		$vision_service_slugs = is_array($vision['service_slugs'] ?? null) ? $vision['service_slugs'] : [];
		$vision_area_slugs = is_array($vision['area_slugs'] ?? null) ? $vision['area_slugs'] : [];
		$vision_page_types = is_array($vision['page_types'] ?? null) ? $vision['page_types'] : [];
		$vision_slots = is_array($vision['slots'] ?? null) ? $vision['slots'] : [];
		$vision_keywords = array_values(array_filter(array_map('sanitize_text_field', $vision_keywords)));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($vision_description));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize($vision_alt));
		$tokens = array_merge($tokens, lf_image_intelligence_tokenize(implode(' ', $vision_keywords)));
		$tokens = array_values(array_unique($tokens));

		$index[] = [
			'id' => $attachment_id,
			'filename' => $filename,
			'normalized_filename' => $normalized_filename,
			'title' => $title,
			'alt' => $alt,
			'caption' => $caption,
			'attached_post_id' => $attached_post_id,
			'tokens' => $tokens,
			'vision_description' => $vision_description,
			'vision_alt_text' => $vision_alt,
			'vision_keywords' => $vision_keywords,
			'vision_service_slugs' => array_values(array_map('sanitize_title', $vision_service_slugs)),
			'vision_area_slugs' => array_values(array_map('sanitize_title', $vision_area_slugs)),
			'vision_page_types' => array_values(array_map('sanitize_key', $vision_page_types)),
			'vision_slots' => array_values(array_map('sanitize_key', $vision_slots)),
		];
	}

	usort($index, static function (array $a, array $b): int {
		$name_cmp = strcmp((string) ($a['normalized_filename'] ?? ''), (string) ($b['normalized_filename'] ?? ''));
		if ($name_cmp !== 0) {
			return $name_cmp;
		}
		return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
	});

	set_transient(LF_MEDIA_INDEX_TRANSIENT, $index, 12 * HOUR_IN_SECONDS);
	return $index;
}

/**
 * @param string[] $needles
 * @param string[] $haystack
 */
function lf_image_intelligence_count_matches(array $needles, array $haystack): int {
	if (empty($needles) || empty($haystack)) {
		return 0;
	}
	$map = array_fill_keys($haystack, true);
	$count = 0;
	foreach ($needles as $needle) {
		if (isset($map[$needle])) {
			$count++;
		}
	}
	return $count;
}

/**
 * @return array<string,mixed>
 */
function lf_image_intelligence_score_item(array $item, array $context): array {
	$tokens = is_array($item['tokens'] ?? null) ? $item['tokens'] : [];
	$normalized_filename = (string) ($item['normalized_filename'] ?? '');
	$service_slug = (string) ($context['service_slug'] ?? '');
	$city_slug = (string) ($context['city_slug'] ?? '');
	$niche_slug = (string) ($context['niche_slug'] ?? '');
	$primary_tokens = is_array($context['primary_tokens'] ?? null) ? $context['primary_tokens'] : [];
	$secondary_tokens = is_array($context['secondary_tokens'] ?? null) ? $context['secondary_tokens'] : [];

	$service_exact = $service_slug !== '' && strpos($normalized_filename, $service_slug) !== false;
	$city_exact = $city_slug !== '' && in_array($city_slug, $tokens, true);
	$niche_exact = $niche_slug !== '' && in_array($niche_slug, $tokens, true);
	$primary_count = lf_image_intelligence_count_matches($primary_tokens, $tokens);
	$secondary_count = lf_image_intelligence_count_matches($secondary_tokens, $tokens);
	$is_generic = in_array('general', $tokens, true);
	$area_slug = (string) ($context['area_slug'] ?? '');
	$page_type = (string) ($context['page_type'] ?? '');
	$vision_service = $service_slug !== '' && in_array($service_slug, is_array($item['vision_service_slugs'] ?? null) ? $item['vision_service_slugs'] : [], true);
	$vision_area = $area_slug !== '' && in_array($area_slug, is_array($item['vision_area_slugs'] ?? null) ? $item['vision_area_slugs'] : [], true);
	$vision_page = $page_type !== '' && in_array($page_type, is_array($item['vision_page_types'] ?? null) ? $item['vision_page_types'] : [], true);

	return [
		'vision_service' => $vision_service ? 1 : 0,
		'vision_area' => $vision_area ? 1 : 0,
		'vision_page' => $vision_page ? 1 : 0,
		'service_exact' => $service_exact ? 1 : 0,
		'city_exact' => $city_exact ? 1 : 0,
		'niche_exact' => $niche_exact ? 1 : 0,
		'primary_match' => $primary_count > 0 ? 1 : 0,
		'secondary_match' => $secondary_count > 0 ? 1 : 0,
		'generic' => $is_generic ? 1 : 0,
		'primary_count' => $primary_count,
		'secondary_count' => $secondary_count,
	];
}

/**
 * @return array<int,mixed>
 */
function lf_image_intelligence_score_vector(array $score): array {
	return [
		(int) ($score['vision_slot'] ?? 0),
		(int) ($score['vision_service'] ?? 0),
		(int) ($score['vision_area'] ?? 0),
		(int) ($score['vision_page'] ?? 0),
		(int) ($score['service_exact'] ?? 0),
		(int) ($score['city_exact'] ?? 0),
		(int) ($score['niche_exact'] ?? 0),
		(int) ($score['primary_match'] ?? 0),
		(int) ($score['secondary_match'] ?? 0),
		(int) ($score['generic'] ?? 0),
		(int) ($score['primary_count'] ?? 0),
		(int) ($score['secondary_count'] ?? 0),
	];
}

/**
 * @param array<int,mixed> $a
 * @param array<int,mixed> $b
 */
function lf_image_intelligence_compare_vectors(array $a, array $b): int {
	$len = max(count($a), count($b));
	for ($i = 0; $i < $len; $i++) {
		$left = (int) ($a[$i] ?? 0);
		$right = (int) ($b[$i] ?? 0);
		if ($left === $right) {
			continue;
		}
		return $right <=> $left;
	}
	return 0;
}

function lf_image_intelligence_seed_hash(string $seed, string $slot, string $filename): int {
	return abs((int) crc32($seed . '|' . $slot . '|' . $filename));
}

function lf_image_intelligence_context_discriminator(array $context): string {
	$parts = [
		(string) ($context['page_type'] ?? ''),
		(string) ($context['service_slug'] ?? ''),
		(string) ($context['area_slug'] ?? ''),
		(string) ($context['city_slug'] ?? ''),
	];
	$tokens = is_array($context['primary_tokens'] ?? null) ? $context['primary_tokens'] : [];
	if (!empty($tokens)) {
		$parts[] = implode('-', array_slice($tokens, 0, 3));
	}
	return implode('|', array_filter($parts));
}

/**
 * @param array<int,array<string,mixed>> $index
 * @param array<string,mixed>            $context
 * @param int[]                          $used_ids
 */
function lf_image_intelligence_pick_slot(array $index, array $context, string $slot, array $used_ids = []): int {
	if (empty($index)) {
		return 0;
	}
	$seed = (string) ($context['variation_seed'] ?? get_option('lf_homepage_variation_seed', 'lf-default-seed'));
	$candidates = [];
	foreach ($index as $item) {
		$id = (int) ($item['id'] ?? 0);
		if ($id <= 0 || in_array($id, $used_ids, true)) {
			continue;
		}
		$score = lf_image_intelligence_score_item($item, $context);
		$vision_slots = is_array($item['vision_slots'] ?? null) ? $item['vision_slots'] : [];
		$score['vision_slot'] = in_array($slot, $vision_slots, true) ? 1 : 0;
		$candidates[] = [
			'id' => $id,
			'filename' => (string) ($item['normalized_filename'] ?? ''),
			'vector' => lf_image_intelligence_score_vector($score),
			'hash' => lf_image_intelligence_seed_hash(
				$seed . '|' . lf_image_intelligence_context_discriminator($context),
				$slot,
				(string) ($item['normalized_filename'] ?? '')
			),
		];
	}
	if (empty($candidates)) {
		return 0;
	}
	usort($candidates, static function (array $a, array $b): int {
		$vector_cmp = lf_image_intelligence_compare_vectors($a['vector'] ?? [], $b['vector'] ?? []);
		if ($vector_cmp !== 0) {
			return $vector_cmp;
		}
		$hash_cmp = ((int) ($a['hash'] ?? 0)) <=> ((int) ($b['hash'] ?? 0));
		if ($hash_cmp !== 0) {
			return $hash_cmp;
		}
		return strcmp((string) ($a['filename'] ?? ''), (string) ($b['filename'] ?? ''));
	});
	return (int) ($candidates[0]['id'] ?? 0);
}

/**
 * Match deterministic media assets for a content context.
 *
 * @param array<string,mixed> $context
 * @return array<string,int>
 */
function lf_match_images_for_context(array $context): array {
	$index = lf_build_media_index();
	$slots = ['hero', 'content_image_a', 'image_content_b', 'content_image_c', 'featured'];
	$out = [
		'hero' => 0,
		'content_image_a' => 0,
		'image_content_b' => 0,
		'content_image_c' => 0,
		'featured' => 0,
	];
	if (empty($index)) {
		return $out;
	}

	$service_slug = sanitize_title((string) ($context['service_slug'] ?? ''));
	$area_slug = sanitize_title((string) ($context['area_slug'] ?? ''));
	$niche_slug = sanitize_title((string) ($context['niche'] ?? ''));
	$city_slug = sanitize_title((string) ($context['city'] ?? get_option('lf_homepage_city', '')));
	$primary_keyword = trim((string) ($context['primary_keyword'] ?? ''));
	$secondary = $context['secondary_keywords'] ?? [];
	if (is_string($secondary)) {
		$secondary = preg_split('/\r\n|\r|\n|,/', $secondary);
	}
	if (!is_array($secondary)) {
		$secondary = [];
	}
	$secondary = array_values(array_filter(array_map('sanitize_text_field', $secondary)));

	$score_context = [
		'page_type' => (string) ($context['page_type'] ?? ''),
		'service_slug' => $service_slug,
		'area_slug' => $area_slug,
		'niche_slug' => $niche_slug,
		'city_slug' => $city_slug,
		'primary_tokens' => lf_image_intelligence_tokenize($primary_keyword),
		'secondary_tokens' => lf_image_intelligence_tokenize(implode(' ', $secondary)),
		'variation_seed' => (string) ($context['variation_seed'] ?? ''),
	];

	$used = [];
	foreach ($slots as $slot) {
		$pick = lf_image_intelligence_pick_slot($index, $score_context, $slot, $used);
		if ($pick > 0) {
			$out[$slot] = $pick;
			$used[] = $pick;
		}
	}
	return $out;
}

/**
 * @return array<string,mixed>
 */
function lf_image_intelligence_build_context_for_post(\WP_Post $post, array $overrides = []): array {
	$post_type = (string) $post->post_type;
	$page_type = 'overview';
	if ($post_type === 'lf_service') {
		$page_type = 'service';
	} elseif ($post_type === 'lf_service_area') {
		$page_type = 'service_area';
	} elseif ($post_type === 'page') {
		$page_type = $post->post_name === 'home' ? 'homepage' : 'overview';
	} elseif ($post_type === 'post') {
		$page_type = 'overview';
	}
	$secondary = (string) get_post_meta((int) $post->ID, '_lf_seo_secondary_keywords', true);
	$secondary_list = $secondary === '' ? [] : preg_split('/\r\n|\r|\n|,/', $secondary);
	$secondary_list = is_array($secondary_list) ? array_values(array_filter(array_map('sanitize_text_field', $secondary_list))) : [];

	$context = [
		'page_type' => $page_type,
		'service_slug' => $post_type === 'lf_service' ? $post->post_name : '',
		'area_slug' => $post_type === 'lf_service_area' ? $post->post_name : '',
		'niche' => (string) (defined('LF_HOMEPAGE_NICHE_OPTION') ? get_option(LF_HOMEPAGE_NICHE_OPTION, '') : ''),
		'city' => (string) get_option('lf_homepage_city', ''),
		'primary_keyword' => (string) get_post_meta((int) $post->ID, '_lf_seo_primary_keyword', true),
		'secondary_keywords' => $secondary_list,
		'variation_seed' => (string) get_option('lf_homepage_variation_seed', ''),
		'service_name' => (string) get_the_title($post),
	];
	return array_merge($context, $overrides);
}

function lf_image_intelligence_is_logo_asset(int $attachment_id, array $context): bool {
	$file = (string) get_attached_file($attachment_id);
	$filename = strtolower((string) basename($file));
	$title = strtolower((string) get_the_title($attachment_id));
	$haystack = $filename . ' ' . $title;
	if (strpos($haystack, 'logo') !== false || strpos($haystack, 'icon') !== false || strpos($haystack, 'brandmark') !== false) {
		return true;
	}
	$mime = (string) get_post_mime_type($attachment_id);
	if ($mime === 'image/png' && $file !== '' && function_exists('lf_image_intelligence_png_has_transparency')) {
		return lf_image_intelligence_png_has_transparency($file);
	}
	return false;
}

function lf_image_intelligence_alt_needs_upgrade(string $alt): bool {
	$alt = trim(wp_strip_all_tags($alt));
	if ($alt === '') {
		return true;
	}
	$lower = strtolower($alt);
	$generic = ['image', 'photo', 'upload', 'placeholder'];
	foreach ($generic as $word) {
		if ($lower === $word || strpos($lower, $word . ' ') === 0) {
			return true;
		}
	}
	if (function_exists('mb_strlen') ? mb_strlen($alt) < 16 : strlen($alt) < 16) {
		return true;
	}
	return false;
}

function lf_image_intelligence_maybe_set_alt_text(int $attachment_id, array $context): void {
	if ($attachment_id <= 0) {
		return;
	}
	$current_alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
	if (!lf_image_intelligence_alt_needs_upgrade($current_alt)) {
		return;
	}
	$business = '';
	if (function_exists('lf_business_entity_get')) {
		$entity = lf_business_entity_get();
		$business = trim((string) ($entity['name'] ?? ''));
	}
	if ($business === '') {
		$business = trim((string) get_bloginfo('name'));
	}
	$city = trim((string) ($context['city'] ?? get_option('lf_homepage_city', '')));
	$service_name = trim((string) ($context['service_name'] ?? ''));
	$primary = trim((string) ($context['primary_keyword'] ?? ''));
	$vision_description = trim((string) get_post_meta($attachment_id, '_lf_vision_description', true));
	if (lf_image_intelligence_is_logo_asset($attachment_id, $context)) {
		$logo_alt = $business !== '' ? sprintf('%s logo', $business) : __('Company logo', 'leadsforward-core');
		update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($logo_alt));
		return;
	}
	if ($service_name === '') {
		$service_name = $primary;
	}
	if ($service_name === '' && $vision_description !== '') {
		$service_name = $vision_description;
	}
	$alt_parts = [];
	if ($service_name !== '') {
		$alt_parts[] = $service_name;
	}
	if ($city !== '' && stripos($service_name, $city) === false) {
		$alt_parts[] = sprintf(__('in %s', 'leadsforward-core'), $city);
	}
	$alt = trim(implode(' ', $alt_parts));
	if ($alt === '' && $primary !== '') {
		$alt = $primary;
	}
	if ($alt === '' && $vision_description !== '') {
		$alt = $vision_description;
	}
	if ($alt === '' && $business !== '') {
		$alt = sprintf(__('%s project image', 'leadsforward-core'), $business);
	}
	$alt = trim(preg_replace('/\s+/', ' ', $alt));
	if (function_exists('mb_substr') && mb_strlen($alt) > 120) {
		$alt = rtrim(mb_substr($alt, 0, 120));
	}
	if ($alt !== '') {
		update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
	}
}

function lf_image_intelligence_finalize_uploaded_attachment(int $attachment_id): void {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return;
	}
	$mime = (string) get_post_mime_type($attachment_id);
	if (strpos($mime, 'image/') !== 0) {
		return;
	}
	$file = (string) get_attached_file($attachment_id);
	$filename = $file !== '' ? basename($file) : '';
	$normalized_filename = lf_image_intelligence_normalize_filename($filename);
	if (preg_match('/^upload-\d+$/', $normalized_filename) === 1 || $normalized_filename === '' || $normalized_filename === 'image') {
		$fallback_slug = lf_image_intelligence_upload_base_slug() . '-' . sprintf('%03d', lf_image_intelligence_next_upload_index());
		if (lf_image_intelligence_rename_attachment_file($attachment_id, $fallback_slug)) {
			$file = (string) get_attached_file($attachment_id);
			$filename = $file !== '' ? basename($file) : $filename;
			$normalized_filename = lf_image_intelligence_normalize_filename($filename);
		}
	}
	$title = trim(str_replace('-', ' ', $normalized_filename));
	if ($title !== '') {
		$title = ucwords($title);
		$business = trim((string) get_bloginfo('name'));
		$city = trim((string) get_option('lf_homepage_city', ''));
		$caption = $city !== ''
			? sprintf('%s serving %s', $title, $city)
			: sprintf('%s by %s', $title, $business !== '' ? $business : get_bloginfo('name'));
		$description = sprintf(
			'%s optimized local SEO asset for section distribution and featured imagery.',
			$caption
		);
		wp_update_post([
			'ID' => $attachment_id,
			'post_title' => $title,
			'post_name' => sanitize_title($title),
			'post_excerpt' => $caption,
			'post_content' => $description,
		]);
	}
	lf_image_intelligence_maybe_set_alt_text($attachment_id, lf_image_intelligence_upload_context_defaults());
	lf_image_intelligence_store_image_profile($attachment_id);
}

function lf_image_intelligence_build_media_candidates_for_vision(int $limit = 200): array {
	$index = lf_build_media_index();
	$out = [];
	foreach (array_slice($index, 0, $limit) as $item) {
		$id = (int) ($item['id'] ?? 0);
		if ($id <= 0) {
			continue;
		}
		$out[] = [
			'attachment_id' => $id,
			'url' => wp_get_attachment_url($id),
			'filename' => (string) ($item['filename'] ?? ''),
			'title' => (string) ($item['title'] ?? ''),
			'alt' => (string) ($item['alt'] ?? ''),
			'caption' => (string) ($item['caption'] ?? ''),
		];
	}
	return $out;
}

function lf_image_intelligence_apply_vision_annotations(array $annotations): array {
	$stored = lf_image_intelligence_get_vision_annotations();
	$applied = 0;
	foreach ($annotations as $row) {
		if (!is_array($row)) {
			continue;
		}
		$attachment_id = absint($row['attachment_id'] ?? 0);
		if ($attachment_id <= 0) {
			continue;
		}
		$post = get_post($attachment_id);
		if (!$post instanceof \WP_Post || $post->post_type !== 'attachment') {
			continue;
		}
		$keywords = $row['keywords'] ?? [];
		if (is_string($keywords)) {
			$keywords = preg_split('/\r\n|\r|\n|,/', $keywords);
		}
		$entry = [
			'description' => sanitize_text_field((string) ($row['description'] ?? '')),
			'alt_text' => sanitize_text_field((string) ($row['alt_text'] ?? '')),
			'keywords' => is_array($keywords) ? array_values(array_filter(array_map('sanitize_text_field', $keywords))) : [],
			'service_slugs' => is_array($row['service_slugs'] ?? null) ? array_values(array_map('sanitize_title', $row['service_slugs'])) : [],
			'area_slugs' => is_array($row['area_slugs'] ?? null) ? array_values(array_map('sanitize_title', $row['area_slugs'])) : [],
			'page_types' => is_array($row['page_types'] ?? null) ? array_values(array_map('sanitize_key', $row['page_types'])) : [],
			'slots' => is_array($row['slots'] ?? null) ? array_values(array_map('sanitize_key', $row['slots'])) : [],
			'recommended_filename' => sanitize_title((string) ($row['recommended_filename'] ?? '')),
		];
		$stored[$attachment_id] = $entry;
		if ($entry['recommended_filename'] !== '') {
			lf_image_intelligence_rename_attachment_file($attachment_id, $entry['recommended_filename']);
		}
		if ($entry['alt_text'] !== '') {
			$current_alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
			if (lf_image_intelligence_alt_needs_upgrade($current_alt)) {
				update_post_meta($attachment_id, '_wp_attachment_image_alt', $entry['alt_text']);
			}
		}
		if ($entry['description'] !== '') {
			update_post_meta($attachment_id, '_lf_vision_description', $entry['description']);
			wp_update_post([
				'ID' => $attachment_id,
				'post_excerpt' => $entry['description'],
				'post_content' => $entry['description'],
			]);
		}
		$applied++;
	}
	lf_image_intelligence_update_vision_annotations($stored);
	lf_invalidate_media_index_cache();
	lf_build_media_index();
	return ['applied' => $applied];
}

/**
 * @param array<string,mixed> $registry_section
 * @return string[]
 */
function lf_image_intelligence_registry_image_fields(array $registry_section): array {
	$keys = [];
	foreach ($registry_section['fields'] ?? [] as $field) {
		if (!is_array($field)) {
			continue;
		}
		if ((string) ($field['type'] ?? '') !== 'image') {
			continue;
		}
		$key = (string) ($field['key'] ?? '');
		if ($key !== '') {
			$keys[] = $key;
		}
	}
	return $keys;
}

function lf_image_intelligence_slot_for_section_field(string $section_type, string $field_key): string {
	if ($section_type === 'hero' || in_array($field_key, ['hero_image_id', 'hero_background_image_id'], true)) {
		return 'hero';
	}
	if ($section_type === 'image_content_b') {
		return 'image_content_b';
	}
	if ($section_type === 'content_image_c') {
		return 'content_image_c';
	}
	if ($section_type === 'content_image_a' || $section_type === 'content_image' || $section_type === 'image_content' || $section_type === 'service_details') {
		return 'content_image_a';
	}
	if ($section_type === 'map_nap') {
		return 'content_image_a';
	}
	return 'content_image_a';
}

function lf_image_intelligence_empty_image_value($value): bool {
	if ($value === '' || $value === null) {
		return true;
	}
	if (is_numeric($value)) {
		$id = (int) $value;
		if ($id === 0) {
			return true;
		}
		if (lf_image_intelligence_is_placeholder_asset($id)) {
			return true;
		}
	}
	return false;
}

function lf_image_intelligence_assign_images_to_post_sections(\WP_Post $post, array $matches, array $context): int {
	if (!function_exists('lf_pb_get_context_for_post') || !function_exists('lf_pb_get_post_config')) {
		return 0;
	}
	$builder_context = lf_pb_get_context_for_post($post);
	if ($builder_context === '') {
		return 0;
	}
	$config = lf_pb_get_post_config((int) $post->ID, $builder_context);
	$order = is_array($config['order'] ?? null) ? $config['order'] : [];
	$sections = is_array($config['sections'] ?? null) ? $config['sections'] : [];
	$registry = function_exists('lf_sections_registry') ? lf_sections_registry() : [];
	if (empty($order) || empty($sections) || empty($registry)) {
		return 0;
	}
	$assigned = 0;
	foreach ($order as $instance_id) {
		$section = $sections[$instance_id] ?? null;
		if (!is_array($section) || empty($section['enabled'])) {
			continue;
		}
		$type = (string) ($section['type'] ?? '');
		if ($type === '' || !isset($registry[$type])) {
			continue;
		}
		$settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
		$image_fields = lf_image_intelligence_registry_image_fields($registry[$type]);
		$changed = false;
		foreach ($image_fields as $field_key) {
			$current = $settings[$field_key] ?? '';
			if (!lf_image_intelligence_empty_image_value($current)) {
				continue;
			}
			$slot = lf_image_intelligence_slot_for_section_field($type, $field_key);
			$image_id = (int) ($matches[$slot] ?? 0);
			if ($image_id <= 0) {
				continue;
			}
			$settings[$field_key] = $image_id;
			lf_image_intelligence_maybe_set_alt_text($image_id, $context);
			$assigned++;
			$changed = true;
		}
		if ($changed) {
			$sections[$instance_id]['settings'] = $settings;
		}
	}
	if ($assigned > 0) {
		$meta_key = defined('LF_PB_META_KEY') ? LF_PB_META_KEY : '_lf_pagebuilder_config';
		update_post_meta((int) $post->ID, $meta_key, [
			'order' => $order,
			'sections' => $sections,
			'seo' => $config['seo'] ?? ['title' => '', 'description' => '', 'noindex' => false],
		]);
	}
	return $assigned;
}

function lf_image_intelligence_assign_images_to_homepage_sections(array $matches, array $context): int {
	if (!function_exists('lf_get_homepage_section_config') || !function_exists('lf_sections_registry')) {
		return 0;
	}
	$config = lf_get_homepage_section_config();
	$registry = lf_sections_registry();
	if (!is_array($config) || empty($config) || !is_array($registry) || empty($registry)) {
		return 0;
	}
	$assigned = 0;
	$changed = false;
	foreach ($config as $section_id => $settings) {
		$section_id = (string) $section_id;
		if (!is_array($settings) || !isset($registry[$section_id])) {
			continue;
		}
		$image_fields = lf_image_intelligence_registry_image_fields($registry[$section_id]);
		foreach ($image_fields as $field_key) {
			$current = $settings[$field_key] ?? '';
			if (!lf_image_intelligence_empty_image_value($current)) {
				continue;
			}
			$slot = lf_image_intelligence_slot_for_section_field($section_id, $field_key);
			$image_id = (int) ($matches[$slot] ?? 0);
			if ($image_id <= 0) {
				continue;
			}
			$config[$section_id][$field_key] = $image_id;
			lf_image_intelligence_maybe_set_alt_text($image_id, $context);
			$assigned++;
			$changed = true;
		}
	}
	if ($changed) {
		update_option(LF_HOMEPAGE_CONFIG_OPTION, $config, true);
	}
	return $assigned;
}

/**
 * Pre-prime deterministic image distribution for current site content.
 *
 * @return array<string,int>
 */
function lf_prime_image_distribution_for_site(): array {
	$summary = ['featured_set' => 0, 'processed' => 0, 'section_images_set' => 0];
	lf_build_media_index();

	$post_ids = [];
	$front_id = (int) get_option('page_on_front');
	if ($front_id > 0) {
		$post_ids[] = $front_id;
	}
	$post_ids = array_merge($post_ids, get_posts([
		'post_type' => ['lf_service', 'lf_service_area', 'lf_project'],
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
	]));
	$post_ids = array_merge($post_ids, get_posts([
		'post_type' => 'page',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'no_found_rows' => true,
		'post_name__in' => ['about-us', 'our-services', 'service-areas', 'reviews', 'contact'],
	]));
	$post_ids = array_values(array_unique(array_map('intval', $post_ids)));

	foreach ($post_ids as $post_id) {
		$post = get_post($post_id);
		if (!$post instanceof \WP_Post) {
			continue;
		}
		$summary['processed']++;
		$context = lf_image_intelligence_build_context_for_post($post);
		$matches = lf_match_images_for_context($context);
		$featured = (int) ($matches['featured'] ?? 0);
		$current_thumb_id = (int) get_post_thumbnail_id($post_id);
		$current_thumb_placeholder = $current_thumb_id > 0 ? lf_image_intelligence_is_placeholder_asset($current_thumb_id) : false;
		if ($featured > 0 && (!has_post_thumbnail($post_id) || $current_thumb_placeholder)) {
			set_post_thumbnail($post_id, $featured);
			lf_image_intelligence_maybe_set_alt_text($featured, $context);
			$summary['featured_set']++;
		}
		$summary['section_images_set'] += lf_image_intelligence_assign_images_to_post_sections($post, $matches, $context);
	}
	$home_keywords = get_option('lf_homepage_keywords', []);
	$home_primary = is_array($home_keywords) ? (string) ($home_keywords['primary'] ?? '') : '';
	$home_secondary = is_array($home_keywords) ? ($home_keywords['secondary'] ?? []) : [];
	if (!is_array($home_secondary)) {
		$home_secondary = [];
	}
	$home_context = [
		'page_type' => 'homepage',
		'niche' => (string) (defined('LF_HOMEPAGE_NICHE_OPTION') ? get_option(LF_HOMEPAGE_NICHE_OPTION, '') : ''),
		'city' => (string) get_option('lf_homepage_city', ''),
		'primary_keyword' => $home_primary,
		'secondary_keywords' => $home_secondary,
		'variation_seed' => (string) get_option('lf_homepage_variation_seed', ''),
		'service_name' => $home_primary,
	];
	$home_matches = lf_match_images_for_context($home_context);
	$summary['section_images_set'] += lf_image_intelligence_assign_images_to_homepage_sections($home_matches, $home_context);

	return $summary;
}

function lf_image_intelligence_register_debug_page(): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	add_management_page(
		__('Image Intelligence Debug', 'leadsforward-core'),
		__('Image Intelligence Debug', 'leadsforward-core'),
		'manage_options',
		'lf-image-intelligence-debug',
		'lf_image_intelligence_render_debug_page'
	);
}

function lf_image_intelligence_render_debug_page(): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	$index = lf_build_media_index();
	$front = (int) get_option('page_on_front');
	$front_post = $front > 0 ? get_post($front) : null;
	$sample_context = $front_post instanceof \WP_Post ? lf_image_intelligence_build_context_for_post($front_post, ['page_type' => 'homepage']) : [];
	$sample_matches = !empty($sample_context) ? lf_match_images_for_context($sample_context) : [];
	?>
	<div class="wrap">
		<h1><?php esc_html_e('Image Intelligence Debug', 'leadsforward-core'); ?></h1>
		<p class="description"><?php esc_html_e('Deterministic media index and sample match output.', 'leadsforward-core'); ?></p>
		<h2><?php esc_html_e('Media Index', 'leadsforward-core'); ?></h2>
		<pre style="max-height:320px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:12px;"><?php echo esc_html(wp_json_encode($index, JSON_PRETTY_PRINT)); ?></pre>
		<h2><?php esc_html_e('Sample Homepage Match', 'leadsforward-core'); ?></h2>
		<pre style="max-height:220px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:12px;"><?php echo esc_html(wp_json_encode(['context' => $sample_context, 'matches' => $sample_matches], JSON_PRETTY_PRINT)); ?></pre>
	</div>
	<?php
}
