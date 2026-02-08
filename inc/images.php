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
const LF_PLACEHOLDER_IMAGE_URL = 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=1600&q=80';

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
 * Seed a default placeholder image from Unsplash (cached in Media Library).
 *
 * @return int Attachment ID or 0 on failure.
 */
function lf_seed_placeholder_image(): int {
	$existing = (int) get_option(LF_PLACEHOLDER_IMAGE_OPTION, 0);
	if ($existing && get_post($existing)) {
		return $existing;
	}

	lf_images_require_media_functions();
	$tmp = download_url(LF_PLACEHOLDER_IMAGE_URL);
	if (is_wp_error($tmp)) {
		return 0;
	}

	$file_array = [
		'name'     => 'leadsforward-placeholder.jpg',
		'tmp_name' => $tmp,
	];
	$attachment_id = media_handle_sideload($file_array, 0, __('LeadsForward placeholder image', 'leadsforward-core'));
	if (is_wp_error($attachment_id)) {
		@unlink($tmp);
		return 0;
	}

	update_post_meta($attachment_id, '_wp_attachment_image_alt', __('Modern home interior', 'leadsforward-core'));
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
