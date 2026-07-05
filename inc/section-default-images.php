<?php
/**
 * Section default placeholder images — bundled assets seeded into the Media Library.
 *
 * Replace files in assets/images/section-defaults/ manually; IDs are cached per context.
 *
 * @package LeadsForward_Core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

const LF_SECTION_DEFAULT_IMAGES_DIR = '/assets/images/section-defaults/';
const LF_SECTION_DEFAULT_IMAGES_OPTION_PREFIX = 'lf_section_default_image_';

/**
 * @return array<string, array{file: string, label: string}>
 */
function lf_section_default_image_registry(): array {
	$registry = [
		'service'       => ['file' => 'service.png', 'label' => 'Default service image'],
		'service_area'  => ['file' => 'service-area.png', 'label' => 'Default service area image'],
		'hero'          => ['file' => 'hero.jpg', 'label' => 'Homepage hero — technician consultation at customer home'],
		'content'       => ['file' => 'content.png', 'label' => 'Default content image'],
		'general'       => ['file' => 'general.png', 'label' => 'Default image'],
		'blog'          => ['file' => 'blog.png', 'label' => 'Default blog image'],
		'project'       => ['file' => 'project.png', 'label' => 'Default project image'],
		'testimonial'   => ['file' => 'testimonial.png', 'label' => 'Default testimonial image'],
	];

	return (array) apply_filters('lf_section_default_image_registry', $registry);
}

/**
 * URL for a section default image file (no Media Library seed).
 */
function lf_get_section_default_image_url(string $context = 'general'): string {
	$registry = lf_section_default_image_registry();
	$ctx = sanitize_key($context);
	if (!isset($registry[$ctx])) {
		$ctx = 'general';
	}
	$file = (string) ($registry[$ctx]['file'] ?? 'general.png');
	$path = LF_THEME_DIR . LF_SECTION_DEFAULT_IMAGES_DIR . $file;
	if (!is_readable($path)) {
		return (defined('LF_THEME_URI') ? LF_THEME_URI : get_template_directory_uri())
			. '/assets/images/leadsforward-placeholder.png';
	}

	return (string) (defined('LF_THEME_URI') ? LF_THEME_URI : get_template_directory_uri())
		. LF_SECTION_DEFAULT_IMAGES_DIR
		. rawurlencode($file);
}

/**
 * Seed a section default image into the Media Library (cached per context).
 */
function lf_seed_section_default_image(string $context = 'general'): int {
	$registry = lf_section_default_image_registry();
	$ctx = sanitize_key($context);
	if (!isset($registry[$ctx])) {
		$ctx = 'general';
	}

	$option_key = LF_SECTION_DEFAULT_IMAGES_OPTION_PREFIX . $ctx;
	$existing = (int) get_option($option_key, 0);
	if ($existing > 0 && get_post($existing)) {
		return $existing;
	}

	if (!function_exists('lf_images_require_media_functions')) {
		return 0;
	}
	lf_images_require_media_functions();

	$file = (string) ($registry[$ctx]['file'] ?? 'general.png');
	$source = LF_THEME_DIR . LF_SECTION_DEFAULT_IMAGES_DIR . $file;
	if (!is_readable($source)) {
		return function_exists('lf_get_placeholder_image_id') ? (int) lf_get_placeholder_image_id() : 0;
	}

	$tmp = wp_tempnam($file);
	if (!$tmp || !@copy($source, $tmp)) {
		return 0;
	}

	$attachment_id = media_handle_sideload(
		['name' => $file, 'tmp_name' => $tmp],
		0,
		null
	);
	if (is_wp_error($attachment_id)) {
		@unlink($tmp);
		return function_exists('lf_get_placeholder_image_id') ? (int) lf_get_placeholder_image_id() : 0;
	}

	$label = (string) ($registry[$ctx]['label'] ?? 'Default image');
	update_post_meta((int) $attachment_id, '_wp_attachment_image_alt', $label);
	wp_update_post([
		'ID'           => (int) $attachment_id,
		'post_title'   => $label,
		'post_excerpt' => '',
		'post_content' => '',
	]);
	update_option($option_key, (int) $attachment_id, true);

	return (int) $attachment_id;
}

/**
 * Attachment ID for a section context; falls back to global placeholder.
 */
function lf_get_section_default_image_id(string $context = 'general'): int {
	$ctx = sanitize_key($context);
	$option_key = LF_SECTION_DEFAULT_IMAGES_OPTION_PREFIX . $ctx;
	$id = (int) get_option($option_key, 0);
	if ($id > 0 && get_post($id)) {
		return $id;
	}
	if (is_admin() && current_user_can('upload_files')) {
		$seeded = lf_seed_section_default_image($ctx);
		if ($seeded > 0) {
			return $seeded;
		}
	}
	if (function_exists('lf_get_placeholder_image_id')) {
		$fallback = (int) lf_get_placeholder_image_id();
		if ($fallback > 0) {
			return $fallback;
		}
	}
	return 0;
}

/**
 * Card / mega-menu thumbnail for a CPT post (featured image or section default).
 */
function lf_get_post_card_thumbnail_id(\WP_Post $post): int {
	$thumb_id = (int) get_post_thumbnail_id($post);
	if ($thumb_id > 0) {
		return $thumb_id;
	}
	$ctx = 'general';
	if ($post->post_type === 'lf_service') {
		$ctx = 'service';
	} elseif ($post->post_type === 'lf_service_area') {
		$ctx = 'service_area';
	} elseif ($post->post_type === 'lf_project') {
		$ctx = 'project';
	}

	return lf_get_section_default_image_id($ctx);
}

add_action('after_switch_theme', static function (): void {
	foreach (array_keys(lf_section_default_image_registry()) as $ctx) {
		lf_seed_section_default_image((string) $ctx);
	}
});
