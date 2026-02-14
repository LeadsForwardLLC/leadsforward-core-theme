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

add_action('add_attachment', 'lf_invalidate_media_index_cache');
add_action('edit_attachment', 'lf_invalidate_media_index_cache');
add_action('delete_attachment', 'lf_invalidate_media_index_cache');
add_action('admin_menu', 'lf_image_intelligence_register_debug_page');

function lf_invalidate_media_index_cache(): void {
	delete_transient(LF_MEDIA_INDEX_TRANSIENT);
}

function lf_image_intelligence_normalize_filename(string $filename): string {
	$filename = strtolower(trim($filename));
	$filename = preg_replace('/\.[a-z0-9]+$/', '', $filename);
	$filename = preg_replace('/[^a-z0-9]+/', '-', $filename);
	$filename = trim((string) $filename, '-');
	return $filename;
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
	foreach ($ids as $attachment_id) {
		$attachment_id = (int) $attachment_id;
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

		$index[] = [
			'id' => $attachment_id,
			'filename' => $filename,
			'normalized_filename' => $normalized_filename,
			'title' => $title,
			'alt' => $alt,
			'caption' => $caption,
			'attached_post_id' => $attached_post_id,
			'tokens' => $tokens,
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

	return [
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
		$candidates[] = [
			'id' => $id,
			'filename' => (string) ($item['normalized_filename'] ?? ''),
			'vector' => lf_image_intelligence_score_vector($score),
			'hash' => lf_image_intelligence_seed_hash($seed, $slot, (string) ($item['normalized_filename'] ?? '')),
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

function lf_image_intelligence_maybe_set_alt_text(int $attachment_id, array $context): void {
	if ($attachment_id <= 0) {
		return;
	}
	$current_alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
	if ($current_alt !== '') {
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
	if ($service_name === '') {
		$service_name = trim((string) ($context['primary_keyword'] ?? ''));
	}
	if ($service_name === '' || $city === '' || $business === '') {
		return;
	}
	$alt = sprintf('%s in %s by %s', $service_name, $city, $business);
	update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
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
	if (is_numeric($value) && (int) $value === 0) {
		return true;
	}
	return false;
}

/**
 * Pre-prime deterministic image distribution for current site content.
 *
 * @return array<string,int>
 */
function lf_prime_image_distribution_for_site(): array {
	$summary = ['featured_set' => 0, 'processed' => 0];
	lf_build_media_index();

	$post_ids = [];
	$front_id = (int) get_option('page_on_front');
	if ($front_id > 0) {
		$post_ids[] = $front_id;
	}
	$post_ids = array_merge($post_ids, get_posts([
		'post_type' => ['lf_service', 'lf_service_area'],
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
		if ($featured > 0 && !has_post_thumbnail($post_id)) {
			set_post_thumbnail($post_id, $featured);
			lf_image_intelligence_maybe_set_alt_text($featured, $context);
			$summary['featured_set']++;
		}
	}

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
