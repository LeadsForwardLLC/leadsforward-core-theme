<?php
/**
 * Image helpers: placeholder seeding and safe media access.
 *
 * @package LeadsForward_Core
 * @since 0.1.0
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_PLACEHOLDER_IMAGE_OPTION = 'lf_placeholder_image_id';
const LF_PLACEHOLDER_IMAGE_FILENAME = 'leadsforward-placeholder.png';
const LF_PLACEHOLDER_IMAGE_RELATIVE_PATH = '/assets/images/leadsforward-placeholder.png';
const LF_PLACEHOLDER_IMAGE_SEED_LOCK = 'lf_placeholder_seed_lock';

/**
 * Hash the bundled placeholder source file.
 */
function lf_placeholder_source_hash(): string {
	$source_file = LF_THEME_DIR . LF_PLACEHOLDER_IMAGE_RELATIVE_PATH;
	if (!is_readable($source_file)) {
		return '';
	}
	$hash = @md5_file($source_file);
	return is_string($hash) ? strtolower($hash) : '';
}

/**
 * Ensure media functions are available when sideloading.
 */
function lf_images_require_media_functions(): void {
	if (!function_exists('media_handle_sideload')) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
}

/**
 * Detect whether attachment looks like the theme placeholder asset.
 */
function lf_is_placeholder_attachment_candidate(int $attachment_id): bool {
	$attachment_id = (int) $attachment_id;
	if ($attachment_id <= 0) {
		return false;
	}
	$file = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
	$filename = strtolower((string) basename($file));
	$title = strtolower((string) get_the_title($attachment_id));
	$excerpt = strtolower((string) get_post_field('post_excerpt', $attachment_id));
	$content = strtolower((string) get_post_field('post_content', $attachment_id));
	$stack = trim($filename . ' ' . $title . ' ' . $excerpt . ' ' . $content);
	if ($stack === '') {
		return false;
	}
	if (strpos($stack, 'leadsforward placeholder') !== false || strpos($stack, 'leadsforward default placeholder image') !== false) {
		return true;
	}
	if (strpos($stack, 'placeholder') !== false && (strpos($stack, 'leadsforward') !== false || strpos($stack, 'leadforward') !== false || (strpos($stack, 'lead') !== false && strpos($stack, 'forw') !== false))) {
		return true;
	}
	if (strpos($filename, 'leadsforward-placeholder') !== false || strpos($filename, 'leadforward-placeholder') !== false) {
		return true;
	}
	$source_hash = lf_placeholder_source_hash();
	if ($source_hash === '') {
		return false;
	}
	$attached_file = (string) get_attached_file($attachment_id);
	if ($attached_file === '' || !is_readable($attached_file)) {
		return false;
	}
	$attached_hash = @md5_file($attached_file);
	return is_string($attached_hash) && strtolower($attached_hash) === $source_hash;
}

/**
 * Find existing placeholder attachment IDs, newest first.
 *
 * @param bool $scan_all When true, scan all image attachments (slower, but exhaustive).
 * @return int[]
 */
function lf_find_existing_placeholder_attachment_ids(bool $scan_all = false): array {
	$args = [
		'post_type' => 'attachment',
		'post_status' => 'inherit',
		'post_mime_type' => 'image',
		'posts_per_page' => -1,
		'orderby' => 'date',
		'order' => 'DESC',
		'fields' => 'ids',
		'no_found_rows' => true,
	];
	if (!$scan_all) {
		$args['s'] = 'placeholder';
	}
	$candidates = get_posts($args);
	$found = [];
	foreach ($candidates as $candidate_id) {
		$attachment_id = (int) $candidate_id;
		if ($attachment_id <= 0) {
			continue;
		}
		if (lf_is_placeholder_attachment_candidate($attachment_id)) {
			$found[] = $attachment_id;
		}
	}
	return $found;
}

/**
 * Keep one placeholder attachment and remove duplicate copies.
 */
function lf_dedupe_placeholder_attachments(array $attachment_ids, int $preferred_keep_id = 0): int {
	$attachment_ids = array_values(array_unique(array_map('intval', $attachment_ids)));
	if (empty($attachment_ids)) {
		return 0;
	}
	$keep_id = (int) $attachment_ids[0];
	if ($preferred_keep_id > 0 && in_array($preferred_keep_id, $attachment_ids, true)) {
		$keep_id = $preferred_keep_id;
	}
	if ($keep_id <= 0) {
		return 0;
	}
	foreach (array_slice($attachment_ids, 1) as $duplicate_id) {
		$duplicate_id = (int) $duplicate_id;
		if ($duplicate_id > 0 && $duplicate_id !== $keep_id) {
			wp_delete_attachment($duplicate_id, true);
		}
	}
	return $keep_id;
}

/**
 * Cleanup duplicate placeholder attachments and keep one canonical ID.
 *
 * @param bool $scan_all When true, scan the full media library for duplicates.
 */
function lf_cleanup_placeholder_duplicates(bool $scan_all = false): int {
	$existing = (int) get_option(LF_PLACEHOLDER_IMAGE_OPTION, 0);
	$existing_attachments = lf_find_existing_placeholder_attachment_ids($scan_all);
	if ($existing > 0 && lf_is_placeholder_attachment_candidate($existing) && !in_array($existing, $existing_attachments, true)) {
		$existing_attachments[] = $existing;
	}
	if (empty($existing_attachments)) {
		return 0;
	}
	$canonical = lf_dedupe_placeholder_attachments($existing_attachments, $existing);
	if ($canonical > 0) {
		update_option(LF_PLACEHOLDER_IMAGE_OPTION, $canonical, true);
	}
	return $canonical;
}

/**
 * Seed a default placeholder image from bundled theme assets (cached in Media Library).
 *
 * @return int Attachment ID or 0 on failure.
 */
function lf_seed_placeholder_image(): int {
	if (get_transient(LF_PLACEHOLDER_IMAGE_SEED_LOCK)) {
		$existing = (int) get_option(LF_PLACEHOLDER_IMAGE_OPTION, 0);
		return ($existing > 0 && get_post($existing)) ? $existing : 0;
	}
	set_transient(LF_PLACEHOLDER_IMAGE_SEED_LOCK, 1, 30);

	$existing = (int) get_option(LF_PLACEHOLDER_IMAGE_OPTION, 0);
	if ($existing && get_post($existing)) {
		$canonical = lf_cleanup_placeholder_duplicates();
		if ($canonical > 0) {
			$existing = $canonical;
		}
		delete_transient(LF_PLACEHOLDER_IMAGE_SEED_LOCK);
		return $existing;
	}
	$existing_attachment = lf_cleanup_placeholder_duplicates();
	if ($existing_attachment > 0) {
		delete_transient(LF_PLACEHOLDER_IMAGE_SEED_LOCK);
		return $existing_attachment;
	}

	lf_images_require_media_functions();
	$source_file = LF_THEME_DIR . LF_PLACEHOLDER_IMAGE_RELATIVE_PATH;
	if (!is_readable($source_file)) {
		delete_transient(LF_PLACEHOLDER_IMAGE_SEED_LOCK);
		return 0;
	}
	$tmp = wp_tempnam(LF_PLACEHOLDER_IMAGE_FILENAME);
	if (!$tmp || !@copy($source_file, $tmp)) {
		delete_transient(LF_PLACEHOLDER_IMAGE_SEED_LOCK);
		return 0;
	}

	$file_array = [
		'name'     => LF_PLACEHOLDER_IMAGE_FILENAME,
		'tmp_name' => $tmp,
	];
	$attachment_id = media_handle_sideload($file_array, 0, __('LeadsForward default placeholder image', 'leadsforward-core'));
	if (is_wp_error($attachment_id)) {
		@unlink($tmp);
		delete_transient(LF_PLACEHOLDER_IMAGE_SEED_LOCK);
		return 0;
	}

	update_post_meta($attachment_id, '_wp_attachment_image_alt', __('Classic exterior home and landscaped front yard', 'leadsforward-core'));
	wp_update_post([
		'ID' => (int) $attachment_id,
		'post_title' => __('LeadsForward Placeholder', 'leadsforward-core'),
		'post_excerpt' => __('LeadsForward default placeholder image for safe fallback content.', 'leadsforward-core'),
		'post_content' => __('LeadsForward default placeholder image for safe fallback content.', 'leadsforward-core'),
	]);
	$all_placeholders = lf_find_existing_placeholder_attachment_ids();
	if (!in_array((int) $attachment_id, $all_placeholders, true)) {
		$all_placeholders[] = (int) $attachment_id;
	}
	$final_id = lf_dedupe_placeholder_attachments($all_placeholders, (int) $attachment_id);
	update_option(LF_PLACEHOLDER_IMAGE_OPTION, (int) $final_id, true);
	delete_transient(LF_PLACEHOLDER_IMAGE_SEED_LOCK);
	return (int) $final_id;
}

/**
 * Get placeholder image ID if available; seeds on theme activation/admin.
 */
function lf_get_placeholder_image_id(): int {
	$id = (int) get_option(LF_PLACEHOLDER_IMAGE_OPTION, 0);
	if ($id && get_post($id)) {
		return $id;
	}
	if (is_admin() && current_user_can('upload_files')) {
		return lf_seed_placeholder_image();
	}
	return 0;
}

add_action('after_switch_theme', 'lf_seed_placeholder_image');
add_action('admin_init', 'lf_seed_placeholder_image');
