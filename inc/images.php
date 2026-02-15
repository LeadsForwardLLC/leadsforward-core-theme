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
 * Try to find an existing placeholder attachment by seeded filename.
 */
function lf_find_existing_placeholder_attachment_id(): int {
	$candidates = get_posts([
		'post_type' => 'attachment',
		'post_status' => 'inherit',
		'post_mime_type' => 'image',
		'posts_per_page' => 20,
		'orderby' => 'date',
		'order' => 'DESC',
		's' => 'leadsforward placeholder',
		'fields' => 'ids',
	]);
	foreach ($candidates as $candidate_id) {
		$attachment_id = (int) $candidate_id;
		if ($attachment_id <= 0) {
			continue;
		}
		$file = (string) get_post_meta($attachment_id, '_wp_attached_file', true);
		if ($file !== '' && stripos($file, LF_PLACEHOLDER_IMAGE_FILENAME) !== false) {
			return $attachment_id;
		}
	}
	return 0;
}

/**
 * Seed a default placeholder image from bundled theme assets (cached in Media Library).
 *
 * @return int Attachment ID or 0 on failure.
 */
function lf_seed_placeholder_image(): int {
	$existing = (int) get_option(LF_PLACEHOLDER_IMAGE_OPTION, 0);
	if ($existing && get_post($existing)) {
		return $existing;
	}
	$existing_attachment = lf_find_existing_placeholder_attachment_id();
	if ($existing_attachment > 0) {
		update_option(LF_PLACEHOLDER_IMAGE_OPTION, $existing_attachment, true);
		return $existing_attachment;
	}

	lf_images_require_media_functions();
	$source_file = LF_THEME_DIR . LF_PLACEHOLDER_IMAGE_RELATIVE_PATH;
	if (!is_readable($source_file)) {
		return 0;
	}
	$tmp = wp_tempnam(LF_PLACEHOLDER_IMAGE_FILENAME);
	if (!$tmp || !@copy($source_file, $tmp)) {
		return 0;
	}

	$file_array = [
		'name'     => LF_PLACEHOLDER_IMAGE_FILENAME,
		'tmp_name' => $tmp,
	];
	$attachment_id = media_handle_sideload($file_array, 0, __('LeadsForward default placeholder image', 'leadsforward-core'));
	if (is_wp_error($attachment_id)) {
		@unlink($tmp);
		return 0;
	}

	update_post_meta($attachment_id, '_wp_attachment_image_alt', __('Classic exterior home and landscaped front yard', 'leadsforward-core'));
	wp_update_post([
		'ID' => (int) $attachment_id,
		'post_title' => __('LeadsForward Placeholder', 'leadsforward-core'),
		'post_excerpt' => __('LeadsForward default placeholder image for safe fallback content.', 'leadsforward-core'),
		'post_content' => __('LeadsForward default placeholder image for safe fallback content.', 'leadsforward-core'),
	]);
	update_option(LF_PLACEHOLDER_IMAGE_OPTION, (int) $attachment_id, true);
	return (int) $attachment_id;
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
